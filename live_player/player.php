<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  player.php  —  واجهة برمجية (API) لاستخراج روابط البث المباشر
 * ───────────────────────────────────────────────────────────────────────────
 *  يستقبل هذا الملف بارامتر GET اسمه "url"، يجلب الصفحة المستهدفة عبر cURL،
 *  ثم يستخرج منها روابط البث (m3u8 / mpd / ts / mp4 / webm / flv) باستخدام:
 *    - تعبيرات نمطية (Regex)
 *    - DOMDocument + XPath للبحث في عناصر iframe / video / source
 *    - فك تشفير base64 لالتقاط روابط atob() المخفية داخل الجافاسكربت
 *  ويعيد النتيجة بصيغة JSON.
 *
 *  ملاحظة أمنية مهمة:
 *  لأن هذا الملف يجلب روابط يحددها المستخدم من جهة الخادم، فهو عرضة لهجمات
 *  SSRF (طلب موارد داخلية في الشبكة). لذلك أضفنا فلتر أمان يمنع الوصول إلى
 *  العناوين الخاصة/المحلية وعناوين ميتاداتا السحابة. لا تُزل هذا الفلتر.
 * ═══════════════════════════════════════════════════════════════════════════
 */

// ── ترويسات الاستجابة: JSON + السماح بالوصول من أي مصدر (CORS) ──────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('X-Content-Type-Options: nosniff');

// الرد المبكر على طلبات preflight
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── دالة مساعدة: إخراج JSON موحّد ثم إنهاء التنفيذ ──────────────────────────
function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── قراءة الرابط المطلوب والتحقق من صحته ────────────────────────────────────
$targetUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
if ($targetUrl === '') {
    json_out(['title' => '', 'servers' => [], 'error' => 'لم يتم تمرير أي رابط (url).'], 400);
}
if (!preg_match('~^https?://~i', $targetUrl)) {
    json_out(['title' => '', 'servers' => [], 'error' => 'الرابط يجب أن يبدأ بـ http:// أو https://'], 400);
}

/**
 * ── فلتر مانع لهجمات SSRF ───────────────────────────────────────────────────
 * يتحقق من أن اسم النطاق لا يشير إلى عنوان IP خاص أو محجوز أو محلي.
 * يعيد true إذا كان الرابط آمناً للجلب.
 */
function is_safe_url(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;

    // حل جميع عناوين IP المرتبطة بالنطاق (IPv4 + IPv6)
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    $ips = [];
    if ($records) {
        foreach ($records as $r) {
            if (isset($r['ip']))   $ips[] = $r['ip'];
            if (isset($r['ipv6'])) $ips[] = $r['ipv6'];
        }
    }
    // في حال كان host عنوان IP مباشرة
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips[] = $host;
    if (empty($ips)) $ips = @gethostbynamel($host) ?: [];
    if (empty($ips)) return false; // تعذّر التحقق → نرفض احتياطاً

    foreach ($ips as $ip) {
        // رفض العناوين الخاصة والمحجوزة (10.x / 192.168.x / 127.x / ::1 / 169.254.x ...)
        if (!filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // رفض صريح لعنوان ميتاداتا السحابة (AWS/GCP)
        if ($ip === '169.254.169.254') return false;
    }
    return true;
}

if (!is_safe_url($targetUrl)) {
    json_out(['title' => '', 'servers' => [],
        'error' => 'الرابط يشير إلى عنوان داخلي/محظور ولا يمكن جلبه.'], 403);
}

/**
 * ── جلب محتوى صفحة عبر cURL ──────────────────────────────────────────────────
 * يعيد مصفوفة: [ 'body' => المحتوى, 'final' => الرابط النهائي بعد التحويلات ]
 */
function fetch_url(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,   // إرجاع المحتوى كنص بدل طباعته
        CURLOPT_FOLLOWLOCATION => true,   // تتبّع عمليات التحويل (redirects)
        CURLOPT_MAXREDIRS      => 5,      // حد أقصى للتحويلات لمنع الحلقات
        CURLOPT_SSL_VERIFYPEER => false,  // تجاهل التحقق من شهادة SSL
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15,     // مهلة 15 ثانية
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',     // دعم gzip/deflate تلقائياً
        // مُعرّف متصفح Chrome حديث لتجنّب الحجب
        CURLOPT_USERAGENT      =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
          . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en;q=0.9',
        ],
    ]);
    $body  = curl_exec($ch);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
    curl_close($ch);
    return ['body' => ($body === false ? '' : $body), 'final' => $final];
}

/**
 * ── تحديد نوع الرابط من امتداده ─────────────────────────────────────────────
 */
function detect_type(string $url): string {
    $u = strtolower($url);
    if (strpos($u, '.m3u8') !== false) return 'm3u8';
    if (strpos($u, '.mpd')  !== false) return 'mpd';
    if (strpos($u, '.webm') !== false) return 'webm';
    if (strpos($u, '.flv')  !== false) return 'flv';
    if (strpos($u, '.mp4')  !== false) return 'mp4';
    if (strpos($u, '.ts')   !== false) return 'ts';
    return 'stream';
}

/**
 * ── تحويل رابط نسبي إلى رابط مطلق بالاعتماد على رابط الأساس ──────────────────
 */
function absolutize(string $link, string $base): string {
    if ($link === '' || preg_match('~^https?://~i', $link)) return $link;
    if (strpos($link, '//') === 0) {           // //cdn.site/file → أضف البروتوكول
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $link;
    }
    $parts = parse_url($base);
    if (!isset($parts['scheme'], $parts['host'])) return $link;
    $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (strpos($link, '/') === 0) return $origin . $link;   // مسار مطلق
    // مسار نسبي: اربطه بمجلّد الصفحة الحالية
    $path = isset($parts['path']) ? preg_replace('~/[^/]*$~', '/', $parts['path']) : '/';
    return $origin . $path . $link;
}

/**
 * ── استخراج روابط البث من نص خام باستخدام Regex ─────────────────────────────
 * يدعم امتداد الرابط مع باراميترات مثل ?token=...
 */
function extract_by_regex(string $text, string $base, array &$found): void {
    // نمط شامل لكل الامتدادات المدعومة مع دعم query string
    $pattern = '~https?://[^\s"\'<>\\\\)]+?\.(?:m3u8|mpd|ts|mp4|webm|flv)(?:\?[^\s"\'<>\\\\)]*)?~i';
    if (preg_match_all($pattern, $text, $m)) {
        foreach ($m[0] as $link) {
            $link = html_entity_decode($link);
            $found[$link] = detect_type($link);
        }
    }
    // روابط نسبية داخل خصائص src/data-src (نادرة لكنها مفيدة)
    $rel = '~["\']([^"\']+?\.(?:m3u8|mpd|ts|mp4|webm|flv)(?:\?[^"\']*)?)["\']~i';
    if (preg_match_all($rel, $text, $m2)) {
        foreach ($m2[1] as $link) {
            $abs = absolutize(html_entity_decode($link), $base);
            if (preg_match('~^https?://~i', $abs)) $found[$abs] = detect_type($abs);
        }
    }
}

/**
 * ── محاولة فك سلاسل base64 المضمّنة في atob('...') لالتقاط روابط مخفية ───────
 */
function extract_from_base64(string $text, string $base, array &$found): void {
    if (preg_match_all('~atob\(\s*[\'"]([A-Za-z0-9+/=]{8,})[\'"]\s*\)~', $text, $m)) {
        foreach ($m[1] as $b64) {
            $decoded = base64_decode($b64, true);
            if ($decoded !== false && strlen($decoded) > 4) {
                extract_by_regex($decoded, $base, $found);
            }
        }
    }
    // سلاسل base64 طويلة قائمة بذاتها (بدون atob) قد تحوي روابط
    if (preg_match_all('~[\'"]([A-Za-z0-9+/]{40,}={0,2})[\'"]~', $text, $m2)) {
        foreach ($m2[1] as $b64) {
            $decoded = base64_decode($b64, true);
            if ($decoded !== false && preg_match('~\.(m3u8|mpd|mp4)~i', $decoded)) {
                extract_by_regex($decoded, $base, $found);
            }
        }
    }
}

/**
 * ── استخراج الروابط عبر DOMDocument + XPath ─────────────────────────────────
 * نبحث في: iframe[src], video[src], source[src], وخصائص data-* المخصّصة.
 * نعيد أيضاً قائمة روابط الـ iframes لنجلبها لاحقاً بشكل متتابع.
 */
function extract_by_dom(string $html, string $base, array &$found, array &$iframes): void {
    if (trim($html) === '') return;
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);            // كتم تحذيرات HTML غير الصحيح
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    // العنوان من وسم <title> أو og:title
    // (يُقرأ خارجياً عند الحاجة)

    // 1) روابط مصادر الفيديو المباشرة: video[src], source[src]
    foreach ($xp->query('//video/@src | //source/@src') as $attr) {
        $abs = absolutize(trim($attr->value), $base);
        if (preg_match('~^https?://~i', $abs)) $found[$abs] = detect_type($abs);
    }

    // 2) خصائص data-* المخصّصة التي تُخزّن فيها روابط البث عادةً
    $dataAttrs = ['data-src', 'data-url', 'data-stream', 'data-file', 'data-hls', 'data-video'];
    foreach ($dataAttrs as $a) {
        foreach ($xp->query("//*[@{$a}]") as $node) {
            $val = trim($node->getAttribute($a));
            $abs = absolutize($val, $base);
            if (preg_match('~\.(m3u8|mpd|ts|mp4|webm|flv)~i', $abs) && preg_match('~^https?://~i', $abs)) {
                $found[$abs] = detect_type($abs);
            }
        }
    }

    // 3) جمع روابط الـ iframes لتتبّعها لاحقاً
    foreach ($xp->query('//iframe/@src') as $attr) {
        $abs = absolutize(trim($attr->value), $base);
        if (preg_match('~^https?://~i', $abs)) $iframes[$abs] = true;
    }
}

/**
 * ── استخراج عنوان الصفحة (اسم القناة) ───────────────────────────────────────
 */
function extract_title(string $html): string {
    if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
        $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        if ($t !== '') return mb_substr($t, 0, 120);
    }
    if (preg_match('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)~i', $html, $m)) {
        return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return 'قناة بث مباشر';
}

// ═══════════════════════════════════════════════════════════════════════════
//  التنفيذ الرئيسي
// ═══════════════════════════════════════════════════════════════════════════

$found   = [];     // خريطة: الرابط => النوع (لمنع التكرار)
$iframes = [];     // روابط الإطارات التي سنتتبّعها

// (1) جلب الصفحة الرئيسية
$main  = fetch_url($targetUrl);
$html  = $main['body'];
$base  = $main['final'];
$title = extract_title($html);

// (2) استخراج الروابط من الصفحة الرئيسية بكل الطرق
extract_by_regex($html, $base, $found);
extract_from_base64($html, $base, $found);
extract_by_dom($html, $base, $found, $iframes);

// (3) تتبّع الـ iframes: نجلب محتوى كل إطار ونستخرج منه (مع حد أقصى للأمان)
$iframeCount = 0;
foreach (array_keys($iframes) as $iframeUrl) {
    if ($iframeCount >= 5) break;             // حد أقصى 5 إطارات لتجنّب البطء
    if (!is_safe_url($iframeUrl)) continue;    // نفس فلتر SSRF على الإطارات
    $iframeCount++;
    $sub  = fetch_url($iframeUrl);
    $subBase = $sub['final'];
    extract_by_regex($sub['body'], $subBase, $found);
    extract_from_base64($sub['body'], $subBase, $found);
    // إطارات متداخلة (مستوى واحد إضافي فقط)
    $nested = [];
    extract_by_dom($sub['body'], $subBase, $found, $nested);
    foreach (array_keys($nested) as $n) {
        if ($iframeCount >= 5) break;
        if (!is_safe_url($n)) continue;
        $iframeCount++;
        $deep = fetch_url($n);
        extract_by_regex($deep['body'], $deep['final'], $found);
        extract_from_base64($deep['body'], $deep['final'], $found);
    }
}

// (4) بناء قائمة السيرفرات النهائية بترتيب أولوية النوع (m3u8 أولاً)
$priority = ['m3u8' => 1, 'mpd' => 2, 'mp4' => 3, 'webm' => 4, 'flv' => 5, 'ts' => 6, 'stream' => 7];
uasort($found, function ($a, $b) use ($priority) {
    return ($priority[$a] ?? 9) <=> ($priority[$b] ?? 9);
});

$servers = [];
$i = 0;
foreach ($found as $url => $type) {
    $i++;
    $servers[] = [
        'name' => 'مورد ' . $i,
        'url'  => $url,
        'type' => $type,
    ];
}

// (5) الإخراج النهائي بصيغة JSON
json_out([
    'title'   => $title,
    'servers' => $servers,
    'count'   => count($servers),
]);
