<?php
declare(strict_types=1);

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *  snrt_player.php — مستخرج ومشغّل M3U8 عام (Generic HLS Sniffer + Proxy)
 * ───────────────────────────────────────────────────────────────────────────
 *  لم يبقَ الملف مخصصًا لموقع واحد: أعطه رابط أي صفحة، ويقوم بنفسه
 *  باستخراج رابط البث (m3u8 / mpd / mp4) ثم تشغيله عبر PHP مثل 1DM.
 *
 *  الاستخدام:
 *    snrt_player.php                                  → واجهة إدخال الرابط
 *    snrt_player.php?url=https://site.com/live/bbc    → قائمة HLS جاهزة للتشغيل
 *    snrt_player.php?url=...&play=1                   → مشغّل HTML مدمج
 *    snrt_player.php?url=...&Quality=720p             → جودة محددة
 *    snrt_player.php?url=...&Quality=auto             → Master بكل الجودات
 *    snrt_player.php?url=...&info=1                   → JSON: الجودات والحالة
 *    snrt_player.php?url=...&extract=1                → JSON: كل الروابط المكتشفة
 *    snrt_player.php?url=...&raw=1                    → الرابط الأصلي + الترويسات
 *    snrt_player.php?url=...&debug=1                  → تقرير استخراج مفصّل
 *    snrt_player.php?name=arryadia                    → قنوات محفوظة (presets)
 *    snrt_player.php?presets=1                        → قائمة القنوات المحفوظة
 *
 *  مثال يعمل كما هو (حتى مع = و # داخل الرابط):
 *    snrt_player.php?url=https://www.parsatv.com/m/name=BBC-News#news
 *
 *  خيارات إضافية:
 *    &hint=bbc        كلمة ترجيح لاختيار البث الصحيح عند تعدد الروابط
 *    &depth=2         عمق تتبع iframes/scripts (0..3)
 *    &referer=...     Referer مخصص يُرسل للمصدر
 *    &verify=0        تعطيل التحقق من صلاحية الروابط (أسرع، أقل دقة)
 *    &nocache=1       تجاهل الذاكرة المؤقتة
 *    &all=1           مع extract=1: إظهار المرشحين المرفوضين أيضًا
 *
 *  كيف يعمل الاستخراج (شبيه 1DM):
 *    1) يجلب الصفحة بترويسات متصفح حقيقي مع دعم الكوكيز والتحويلات.
 *    2) يفكّ تشفير JS المضغوط (p,a,c,k,e,d) وسلاسل base64 و\x/\u.
 *    3) يفحص HTML و JSON و JS ووسوم <source>/<video>/data-*
 *       ومفاتيح المشغّلات (file/src/hls/stream/sources...).
 *    4) يتتبّع الـ iframes وملفات JS/JSON التي تحمل كلمات دلالية.
 *    5) يرتّب المرشحين بنقاط، ثم يتحقق من كل رابط فعليًا (#EXTM3U).
 *    6) يعيد كتابة القوائم ليمر كل شيء عبر PHP بروابط موقّعة HMAC.
 *
 *  مهم قبل النشر: غيّر PROXY_SIGNING_KEY، ويُفضّل ضبط ACCESS_KEY.
 * ═══════════════════════════════════════════════════════════════════════════
 */

// ───────────────────────────── الإعدادات ─────────────────────────────

/** مفتاح توقيع روابط البروكسي — غيّره إلى قيمة سرية خاصة بك. */
const PROXY_SIGNING_KEY = 'change-me-4f1c9d8e2b7a6053c1d4e9f8a2b7c6d5'
    . 'e0f3a8b1c4d7e2f5a9b6c3d8e1f4a7b2';

/** إن وضعت قيمة هنا يصبح استخدام الملف محصورًا بمن يعرفها: &key=... */
const ACCESS_KEY = '';

const USER_AGENT = 'Mozilla/5.0 (Linux; Android 14; SM-S918B) '
    . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 '
    . 'Mobile Safari/537.36';

/** أقصى حجم يُقرأ من صفحة/سكربت أثناء البحث (بايت). */
const MAX_SCAN_BYTES = 4194304;

/** أقصى حجم قائمة تشغيل تُقرأ (بايت). */
const MAX_PLAYLIST_BYTES = 2097152;

/** عمق تتبع iframes/scripts الافتراضي. */
const DEFAULT_DEPTH = 2;

/** أقصى عدد صفحات/ملفات تُجلب في عملية استخراج واحدة. */
const MAX_FETCHES = 14;

/** أقصى عدد روابط يتم التحقق منها فعليًا. */
const MAX_PROBES = 9;

/** أقصى عدد المرشحين "الضعاف" (بلا امتداد) الذين نجرّبهم. */
const MAX_WEAK_CANDIDATES = 6;

/** مدة تخزين نتيجة الاستخراج (ثانية). */
const CACHE_TTL = 120;

/** السماح بجلب عناوين داخلية/محلية (اتركه false لمنع SSRF). */
const ALLOW_PRIVATE_TARGETS = false;

/**
 * منافذ ممنوعة (خدمات داخلية). أي منفذ آخر مسموح لأن مواقع البث
 * تستخدم منافذ غير قياسية مثل 8000 و8081 و25461.
 */
const BLOCKED_PORTS = [
    22, 23, 25, 53, 110, 135, 137, 138, 139, 143, 389, 445, 465, 587,
    993, 995, 1433, 1521, 2049, 2375, 2376, 3306, 3389, 5432, 5601,
    5900, 5984, 6379, 8086, 9042, 9200, 9300, 11211, 27017, 27018,
];

/** ترويسات لا يجوز تمريرها من المصدر إلى العميل. */
const HOP_HEADERS = [
    'connection', 'keep-alive', 'transfer-encoding', 'upgrade',
    'proxy-authenticate', 'proxy-authorization', 'te', 'trailer',
    'content-encoding', 'content-length', 'set-cookie', 'strict-transport-security',
];

// ───────────────────────── ترويسات الاستجابة ─────────────────────────

function sendCors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Range, Content-Type, Origin');
    header(
        'Access-Control-Expose-Headers: '
        . 'Content-Length, Content-Range, Accept-Ranges, Content-Type'
    );
    header('X-Content-Type-Options: nosniff');
}

function noStore(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/**
 * @param array<string, mixed> $data
 */
function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    noStore();

    echo (string) json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/**
 * @param array<string, mixed> $extra
 */
function failJson(string $message, int $status = 400, array $extra = []): void
{
    sendJson(['ok' => false, 'error' => $message] + $extra, $status);
}

// ─────────────────────── قراءة معطيات الطلب ───────────────────────

/**
 * الأسماء المحفوظة للسكربت — أي شيء آخر بعد url= يُعتبر جزءًا من الرابط.
 *
 * @return array<int, string>
 */
function reservedParams(): array
{
    return [
        'Quality', 'quality', 'q', 'name', 'presets', 'channels', 'info',
        'extract', 'debug', 'play', 'embed', 'raw', 'format', 'hint',
        'depth', 'referer', 'origin', 'verify', 'nocache', 'all', 'key',
        'resource', 'sig', 'ua',
    ];
}

function rawQueryString(): string
{
    return (string) ($_SERVER['QUERY_STRING'] ?? '');
}

/**
 * يستخرج قيمة url من الاستعلام الخام حتى لو احتوت = أو & أو #.
 * مثال: ?url=https://site.com/m/name=BBC-News&Quality=720p
 */
function requestedTargetUrl(): string
{
    $query = rawQueryString();

    if ($query === '') {
        return '';
    }

    $position = null;
    foreach (['url=', 'link=', 'u='] as $marker) {
        $found = strpos($query, $marker);
        if ($found === 0 || ($found !== false && $query[$found - 1] === '&')) {
            $position = $found + strlen($marker);
            break;
        }
    }

    if ($position === null) {
        return trim((string) ($_GET['url'] ?? $_GET['link'] ?? $_GET['u'] ?? ''));
    }

    $rest = substr($query, $position);

    // نقطع عند أول & يتبعه اسم معطى محفوظ فقط، حتى لا نكسر روابط فيها &.
    $cut = strlen($rest);
    $offset = 0;
    while (true) {
        $ampersand = strpos($rest, '&', $offset);
        if ($ampersand === false) {
            break;
        }

        $tail = substr($rest, $ampersand + 1);
        foreach (reservedParams() as $reserved) {
            if (
                stripos($tail, $reserved . '=') === 0
                || strcasecmp($tail, $reserved) === 0
            ) {
                $cut = $ampersand;
                break 2;
            }
        }

        $offset = $ampersand + 1;
    }

    $value = substr($rest, 0, $cut);
    $value = str_replace('+', '%20', $value);
    $value = rawurldecode($value);

    // نزيل الـ fragment (يُستخدم كتلميح لاسم القناة).
    $hash = strpos($value, '#');
    if ($hash !== false) {
        $value = substr($value, 0, $hash);
    }

    return trim($value);
}

/** التلميح المأخوذ من #fragment إن أرسله العميل. */
function fragmentHint(): string
{
    foreach ([rawQueryString(), (string) ($_SERVER['REQUEST_URI'] ?? '')] as $source) {
        $hash = strpos($source, '#');
        if ($hash !== false) {
            $value = rawurldecode(substr($source, $hash + 1));
            if (trim($value) !== '') {
                return trim($value);
            }
        }
    }

    return '';
}

function boolParam(string $name, bool $default = false): bool
{
    if (!isset($_GET[$name])) {
        return $default;
    }

    $value = strtolower(trim((string) $_GET[$name]));

    if ($value === '') {
        return true;
    }

    return !in_array($value, ['0', 'no', 'false', 'off'], true);
}

function stringParam(string $name, string $default = ''): string
{
    return isset($_GET[$name]) ? trim((string) $_GET[$name]) : $default;
}

// ─────────────────────── أدوات الروابط والأمان ───────────────────────

function normalizeInputUrl(string $url): string
{
    $url = trim($url);
    $url = (string) preg_replace('/[\r\n\t]+/', '', $url);

    if ($url === '') {
        throw new InvalidArgumentException('لم يتم إرسال رابط. استخدم ?url=');
    }

    if (preg_match('~^//~', $url)) {
        $url = 'https:' . $url;
    } elseif (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        throw new InvalidArgumentException('الرابط المُرسل غير صالح.');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('يُدعم http/https فقط.');
    }

    return $url;
}

function isPublicHost(string $host): bool
{
    if (ALLOW_PRIVATE_TARGETS) {
        return true;
    }

    $host = strtolower(trim($host, " \t[]"));
    if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local') {
        return false;
    }

    $addresses = [];
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $addresses[] = $host;
    } else {
        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            $addresses = $resolved;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = (string) $record['ipv6'];
                }
            }
        }
    }

    if ($addresses === []) {
        // تعذّر الحل: نسمح ونترك cURL يفشل، دون فتح باب العناوين الحرفية.
        return filter_var($host, FILTER_VALIDATE_IP) === false;
    }

    foreach ($addresses as $address) {
        $isPublic = filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($isPublic === false) {
            return false;
        }
    }

    return true;
}

function assertSafeUrl(string $url): void
{
    $parts = parse_url($url);

    if (
        $parts === false
        || empty($parts['host'])
        || isset($parts['user'])
        || isset($parts['pass'])
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
    ) {
        throw new InvalidArgumentException('رابط المصدر غير صالح.');
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535 || in_array($port, BLOCKED_PORTS, true))) {
        throw new InvalidArgumentException('منفذ المصدر غير مسموح.');
    }

    if (!isPublicHost((string) $parts['host'])) {
        throw new InvalidArgumentException('عنوان المصدر غير مسموح (شبكة داخلية).');
    }
}

function urlOrigin(string $url): string
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return $scheme . '://' . $parts['host'] . $port;
}

function urlHost(string $url): string
{
    return strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
}

/** النطاق الجذري التقريبي (site.com من a.b.site.com). */
function registrableDomain(string $host): string
{
    $host = strtolower($host);
    $parts = explode('.', $host);
    $count = count($parts);

    if ($count < 3) {
        return $host;
    }

    $secondLevel = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac'];
    if (in_array($parts[$count - 2], $secondLevel, true) && strlen($parts[$count - 1]) <= 3) {
        return implode('.', array_slice($parts, -3));
    }

    return implode('.', array_slice($parts, -2));
}

function resolveUrl(string $baseUrl, string $relativeUrl): string
{
    $relativeUrl = trim($relativeUrl);
    if ($relativeUrl === '') {
        return $baseUrl;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $relativeUrl)) {
        return $relativeUrl;
    }

    $base = parse_url($baseUrl);
    if ($base === false || empty($base['scheme']) || empty($base['host'])) {
        throw new RuntimeException('تعذر تكوين رابط المورد.');
    }

    if (substr($relativeUrl, 0, 2) === '//') {
        return $base['scheme'] . ':' . $relativeUrl;
    }

    $fragment = '';
    $fragmentPosition = strpos($relativeUrl, '#');
    if ($fragmentPosition !== false) {
        $fragment = substr($relativeUrl, $fragmentPosition);
        $relativeUrl = substr($relativeUrl, 0, $fragmentPosition);
    }

    $query = '';
    $queryPosition = strpos($relativeUrl, '?');
    if ($queryPosition !== false) {
        $query = substr($relativeUrl, $queryPosition);
        $relativePath = substr($relativeUrl, 0, $queryPosition);
    } else {
        $relativePath = $relativeUrl;
    }

    $basePath = (string) ($base['path'] ?? '/');

    if ($relativePath === '') {
        $path = $basePath === '' ? '/' : $basePath;
    } elseif (substr($relativePath, 0, 1) === '/') {
        $path = $relativePath;
    } else {
        $directory = preg_replace('~/[^/]*$~', '/', $basePath);
        $path = ($directory === null || $directory === '' ? '/' : $directory) . $relativePath;
    }

    $segments = explode('/', $path);
    $normalized = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($normalized);
            continue;
        }

        $normalized[] = $segment;
    }

    $normalizedPath = '/' . implode('/', $normalized);
    if (substr($path, -1) === '/' && substr($normalizedPath, -1) !== '/') {
        $normalizedPath .= '/';
    }

    $port = isset($base['port']) ? ':' . $base['port'] : '';

    return $base['scheme'] . '://' . $base['host'] . $port
        . $normalizedPath . $query . $fragment;
}

/**
 * @return array<string, string>
 */
function getUrlQuery(string $url): array
{
    $parts = parse_url($url);
    $query = [];

    if ($parts !== false && isset($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }

    $flat = [];
    foreach ($query as $name => $value) {
        if (is_scalar($value)) {
            $flat[(string) $name] = (string) $value;
        }
    }

    return $flat;
}

/**
 * @param array<string, string|int|null> $changes
 */
function changeUrlQuery(string $url, array $changes): string
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        throw new InvalidArgumentException('تعذر تعديل الرابط.');
    }

    $query = [];
    if (isset($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }

    foreach ($changes as $name => $value) {
        if ($value === null) {
            unset($query[$name]);
        } else {
            $query[$name] = (string) $value;
        }
    }

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = (string) ($parts['path'] ?? '/');
    $newQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $parts['scheme'] . '://' . $parts['host'] . $port . $path
        . ($newQuery !== '' ? '?' . $newQuery : '') . $fragment;
}

// ───────────────────────────── طبقة HTTP ─────────────────────────────

/**
 * ملف كوكيز ثابت لكل مضيف، حتى تبقى الجلسة صالحة بين طلب الصفحة وطلبات
 * المقاطع اللاحقة (كل مقطع يصل في عملية PHP مستقلة). لا نخلط كوكيز
 * المواقع ببعضها، ونبدأ جلسة جديدة كل ساعة.
 */
function cookieJarPath(string $url = ''): string
{
    static $paths = [];

    $host = $url !== '' ? urlHost($url) : 'default';
    if ($host === '') {
        $host = 'default';
    }

    if (isset($paths[$host])) {
        return $paths[$host];
    }

    $directory = sys_get_temp_dir() . '/generic_player_jars';
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
    }

    $path = $directory . '/' . hash_hmac('sha256', $host, PROXY_SIGNING_KEY) . '.jar';

    if (is_file($path) && (time() - (int) filemtime($path)) > 3600) {
        @unlink($path);
    }

    if (!is_file($path)) {
        @touch($path);
        @chmod($path, 0600);
    }

    $paths[$host] = $path;

    return $path;
}

/**
 * جلب مورد من المصدر مع تتبع يدوي للتحويلات وفحص أمني لكل خطوة.
 *
 * @param array{
 *     referer?: string,
 *     origin?: string,
 *     accept?: string,
 *     max_bytes?: int,
 *     range?: string,
 *     head?: bool,
 *     timeout?: int
 * } $options
 *
 * @return array{
 *     ok: bool,
 *     status: int,
 *     body: string,
 *     content_type: string,
 *     final_url: string,
 *     headers: array<string, string>,
 *     truncated: bool,
 *     error: string
 * }
 */
function httpFetch(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('إضافة cURL غير مفعّلة في PHP.');
    }

    $maxBytes = (int) ($options['max_bytes'] ?? MAX_SCAN_BYTES);
    $headOnly = (bool) ($options['head'] ?? false);
    $timeout = (int) ($options['timeout'] ?? 25);
    $currentUrl = $url;
    $result = [
        'ok' => false,
        'status' => 0,
        'body' => '',
        'content_type' => '',
        'final_url' => $url,
        'headers' => [],
        'truncated' => false,
        'error' => '',
    ];

    for ($hop = 0; $hop < 6; $hop++) {
        assertSafeUrl($currentUrl);

        $responseHeaders = [];
        $body = '';
        $truncated = false;

        $curl = curl_init($currentUrl);
        if ($curl === false) {
            throw new RuntimeException('تعذر تشغيل cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_NOBODY => $headOnly,
            CURLOPT_COOKIEJAR => cookieJarPath($currentUrl),
            CURLOPT_COOKIEFILE => cookieJarPath($currentUrl),
            CURLOPT_HTTPHEADER => buildRequestHeaders($currentUrl, $options),
            CURLOPT_HEADERFUNCTION => static function (
                $handle,
                string $line
            ) use (&$responseHeaders): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if ($trimmed === '') {
                    return $length;
                }

                if (stripos($trimmed, 'HTTP/') === 0) {
                    $responseHeaders = [];
                    return $length;
                }

                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    if ($name !== '') {
                        $responseHeaders[$name] = trim(substr($line, $separator + 1));
                    }
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $handle,
                string $chunk
            ) use (&$body, &$truncated, $maxBytes): int {
                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    $body = substr($body, 0, $maxBytes);
                    $truncated = true;
                    return 0;
                }

                return strlen($chunk);
            },
        ]);

        $executed = curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $errorMessage = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $contentType = (string) (curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '');
        $effectiveUrl = (string) (curl_getinfo($curl, CURLINFO_EFFECTIVE_URL) ?: $currentUrl);
        curl_close($curl);

        $writeAborted = $truncated && in_array(
            $errorNumber,
            [CURLE_WRITE_ERROR, CURLE_ABORTED_BY_CALLBACK],
            true
        );

        if ($executed === false && $errorNumber !== 0 && !$writeAborted) {
            $result['error'] = $errorMessage;
            $result['status'] = $status;
            return $result;
        }

        $result = [
            'ok' => $status >= 200 && $status < 400,
            'status' => $status,
            'body' => $body,
            'content_type' => $contentType,
            'final_url' => $effectiveUrl,
            'headers' => $responseHeaders,
            'truncated' => $truncated,
            'error' => '',
        ];

        $isRedirect = in_array($status, [301, 302, 303, 307, 308], true);
        $location = $responseHeaders['location'] ?? '';

        if (!$isRedirect || $location === '') {
            return $result;
        }

        $currentUrl = resolveUrl($effectiveUrl, $location);
    }

    $result['error'] = 'عدد التحويلات كبير جدًا.';

    return $result;
}

/**
 * @param array<string, mixed> $options
 *
 * @return array<int, string>
 */
function buildRequestHeaders(string $url, array $options): array
{
    $referer = (string) ($options['referer'] ?? '');
    $origin = (string) ($options['origin'] ?? '');

    if ($referer === '') {
        $referer = urlOrigin($url) . '/';
    }

    if ($origin === '') {
        $origin = rtrim(urlOrigin($referer), '/');
    }

    $accept = (string) ($options['accept'] ?? '*/*');
    $isDocument = stripos($accept, 'text/html') !== false;

    $headers = [
        'Accept: ' . $accept,
        'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Upgrade-Insecure-Requests: 1',
    ];

    // طلب الصفحة يجب أن يبدو تصفّحًا حقيقيًا لا XHR، فبعض الحمايات تفحص ذلك.
    if ($isDocument) {
        $headers[] = 'Sec-Fetch-Dest: document';
        $headers[] = 'Sec-Fetch-Mode: navigate';
        $headers[] = 'Sec-Fetch-User: ?1';
        $headers[] = 'Sec-Fetch-Site: ' . ($referer === '' ? 'none' : 'same-origin');
    } else {
        $headers[] = 'Sec-Fetch-Dest: empty';
        $headers[] = 'Sec-Fetch-Mode: cors';
        $headers[] = 'Sec-Fetch-Site: cross-site';
    }

    if ($referer !== '' && $referer !== '/') {
        $headers[] = 'Referer: ' . $referer;
    }

    if ($origin !== '') {
        $headers[] = 'Origin: ' . $origin;
    }

    $range = (string) ($options['range'] ?? '');
    if ($range !== '' && preg_match('/^bytes=\d*-\d*(?:,\s*\d*-\d*)*$/i', $range)) {
        $headers[] = 'Range: ' . $range;
    }

    return $headers;
}

// ───────────────────── فك التشفير واستخراج النصوص ─────────────────────

/** فك ضغط JS المُعبّأ بـ Dean Edwards packer. */
function packerEncode(int $index, int $base): string
{
    $output = '';

    do {
        $digit = $index % $base;
        $output = ($digit > 35
            ? chr($digit + 29)
            : base_convert((string) $digit, 10, 36)) . $output;
        $index = intdiv($index, $base);
    } while ($index > 0);

    return $output;
}

/**
 * @return array<int, string>
 */
function unpackPackedJs(string $source, int $depth = 0): array
{
    if ($depth > 3 || strlen($source) > MAX_SCAN_BYTES) {
        return [];
    }

    $pattern = '~eval\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,'
        . '\s*[dr]\s*\)\s*\{.*?\}\s*\(\s*(["\'])(.*?)\1\s*,\s*(\d+)\s*,\s*(\d+)'
        . '\s*,\s*(["\'])(.*?)\5\s*\.\s*split\(\s*(["\'])\|\7\s*\)~s';

    if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $unpacked = [];

    foreach ($matches as $match) {
        $payload = $match[2];
        $radix = max(2, (int) $match[3]);
        $count = (int) $match[4];
        $keys = explode('|', $match[6]);

        $payload = str_replace(['\\\\', "\\'", '\\"', '\\n', '\\t'], ['\\', "'", '"', "\n", "\t"], $payload);

        // خريطة الرمز → الكلمة، ثم استبدال بمرور واحد حتى لا تُستبدل النتائج.
        $map = [];
        for ($index = 0; $index < $count; $index++) {
            $key = $keys[$index] ?? '';
            if ($key !== '') {
                $map[packerEncode($index, $radix)] = $key;
            }
        }

        $decoded = preg_replace_callback(
            '/\w+/',
            static fn (array $match): string => $map[$match[0]] ?? $match[0],
            $payload
        );

        $payload = $decoded ?? $payload;
        $unpacked[] = $payload;

        // بعض المواقع تضغط الكود أكثر من مرة.
        foreach (unpackPackedJs($payload, $depth + 1) as $nested) {
            $unpacked[] = $nested;
        }
    }

    return $unpacked;
}

/** فك \xNN و \uNNNN و \/ داخل النصوص. */
function decodeEscapes(string $text): string
{
    $decoded = preg_replace_callback(
        '/\\\\u([0-9a-fA-F]{4})|\\\\x([0-9a-fA-F]{2})/',
        static function (array $match): string {
            $code = isset($match[2]) && $match[2] !== ''
                ? hexdec($match[2])
                : hexdec($match[1]);

            return mb_chr((int) $code, 'UTF-8') ?: '';
        },
        $text
    );

    $decoded = $decoded ?? $text;

    return str_replace(['\\/', '\\\\/'], '/', $decoded);
}

/**
 * نصوص مشتقة من الأصل لزيادة فرص العثور على الروابط.
 *
 * @return array<string, string>
 */
function textVariants(string $text): array
{
    $variants = ['raw' => $text];

    $escaped = decodeEscapes($text);
    if ($escaped !== $text) {
        $variants['unescaped'] = $escaped;
    }

    if (strpos($text, '&') !== false) {
        $entities = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($entities !== $text) {
            $variants['entities'] = $entities;
        }
    }

    if (
        stripos($text, 'http%3a') !== false
        || stripos($text, '%2f') !== false
        || stripos($text, 'unescape(') !== false
        || stripos($text, 'decodeURIComponent(') !== false
    ) {
        $variants['urldecoded'] = urldecode($text);
    }

    // "https://cdn" + "/live/x.m3u8" → "https://cdn/live/x.m3u8"
    $joined = collapseStringConcat($text);
    if ($joined !== $text) {
        $variants['concat'] = $joined;
    }

    // var base = "https://cdn/live"; player.load(base + "?id=1");
    $inlined = inlineJsStringVars($joined);
    if ($inlined !== null && $inlined !== $joined) {
        $variants['js_vars'] = $inlined;
    }

    foreach (unpackPackedJs($text) as $index => $unpacked) {
        $variants['packed_' . $index] = $unpacked;
        $nested = decodeEscapes($unpacked);
        if ($nested !== $unpacked) {
            $variants['packed_' . $index . '_unescaped'] = $nested;
        }
    }

    $base64 = base64Payloads($text);
    if ($base64 !== '') {
        $variants['base64'] = $base64;
    }

    return $variants;
}

/** يدمج السلاسل المتلاصقة: "a" + "b" → "ab" (يكرّر حتى تستقر). */
function collapseStringConcat(string $text): string
{
    if (strpos($text, '+') === false) {
        return $text;
    }

    for ($pass = 0; $pass < 5; $pass++) {
        $joined = preg_replace('~(["\'])\s*\+\s*\1~', '', $text);

        if ($joined === null || $joined === $text) {
            break;
        }

        $text = $joined;
    }

    return $text;
}

/**
 * يستبدل متغيّرات JS النصية بقيمها ثم يدمج الوصل، لالتقاط روابط مثل:
 *   var base = "https://cdn/live"; player.load(base + "?id=1");
 */
function inlineJsStringVars(string $text): ?string
{
    $pattern = '~(?:var|let|const)\s+([A-Za-z_$][\w$]*)\s*=\s*'
        . '(["\'])((?:\\\\.|(?!\2)[^\\\\]){4,500})\2~';

    if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        return null;
    }

    $map = [];
    foreach ($matches as $match) {
        $value = $match[3];

        // نكتفي بالقيم التي قد تشكّل جزءًا من رابط.
        if (preg_match('~[/:?.=]~', $value)) {
            $map[$match[1]] = $value;
        }

        if (count($map) >= 200) {
            break;
        }
    }

    if ($map === []) {
        return null;
    }

    $replaced = preg_replace_callback(
        '~\b[A-Za-z_$][\w$]*\b~',
        static fn (array $match): string => isset($map[$match[0]])
            ? '"' . $map[$match[0]] . '"'
            : $match[0],
        $text
    );

    return $replaced === null ? null : collapseStringConcat($replaced);
}

/** يجمع محتوى سلاسل base64 التي تحوي روابط. */
function base64Payloads(string $text): string
{
    $found = [];

    $patterns = [
        '~atob\(\s*["\']([A-Za-z0-9+/=\s]{16,})["\']~i',
        '~base64_decode\(\s*["\']([A-Za-z0-9+/=]{16,})["\']~i',
        '~["\']([A-Za-z0-9+/]{40,}={0,2})["\']~',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches)) {
            continue;
        }

        foreach ($matches[1] as $candidate) {
            $clean = (string) preg_replace('/\s+/', '', $candidate);
            if ($clean === '' || strlen($clean) % 4 !== 0) {
                $clean = rtrim($clean, '=');
                $padding = strlen($clean) % 4;
                if ($padding !== 0) {
                    $clean .= str_repeat('=', 4 - $padding);
                }
            }

            $decoded = base64_decode($clean, true);
            if (
                $decoded === false
                || $decoded === ''
                || !preg_match('~https?://|\.m3u8|/hls/~i', $decoded)
            ) {
                continue;
            }

            $found[] = $decoded;
            if (count($found) >= 40) {
                break 2;
            }
        }
    }

    return implode("\n", $found);
}

// ───────────────────── تصنيف الروابط وتنقيتها ─────────────────────

function trimUrlToken(string $url): string
{
    $url = trim($url);
    $url = (string) preg_replace('/[\r\n\t\s]+$/', '', $url);
    $url = rtrim($url, "\\\"'`,;:.)]}>*");

    return $url;
}

/** يعيد نوع المورد (hls|dash|file|audio) أو null إن لم يكن بثًا. */
function mediaKind(string $url): ?string
{
    $lower = strtolower($url);
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

    if (preg_match('/\.(m3u8|m3u)$/', $path) || strpos($lower, 'm3u8') !== false) {
        return 'hls';
    }

    if (substr($path, -4) === '.mpd' || strpos($lower, '.mpd?') !== false) {
        return 'dash';
    }

    if (preg_match('/\.(mp4|m4v|webm|flv|mkv|mov)$/', $path)) {
        return 'file';
    }

    if (preg_match('/\.(mp3|aac|m4a|ogg|opus)$/', $path)) {
        return 'audio';
    }

    if (preg_match('~/hls[/?]|chunklist|/manifest|manifest\.|/playlist[/?.]|/master[/?.]~', $lower)) {
        return 'hls';
    }

    if (preg_match('~[?&](?:type|format|ext)=m3u8~', $lower)) {
        return 'hls';
    }

    return null;
}

/**
 * هل يصلح الرابط ليكون نقطة بث ديناميكية (بلا امتداد m3u8 ظاهر)؟
 * نستبعد الأصول الثابتة والصفحات، ونقبل نقاط php/api ذات الكلمات الدلالية.
 */
function isProbableStreamEndpoint(string $url): bool
{
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
    $query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');

    if (preg_match('/\.(js|css|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|xml|txt|pdf|zip|map)$/', $path)) {
        return false;
    }

    if (preg_match('/\.(html?|xhtml)$/', $path)) {
        return false;
    }

    // كونه مُمرَّرًا لمشغّل هو الدليل، فلا نشترط كلمة دلالية في الرابط.
    return $query !== ''
        || (bool) preg_match('/\.(php|json|aspx?|ashx|jsp|cgi|do)$/', $path);
}

function isPlaylistUrl(string $url): bool
{
    $kind = mediaKind($url);

    return $kind === 'hls' || $kind === 'dash';
}

/**
 * @return array<int, string>
 */
function collectAbsoluteUrls(string $text): array
{
    if (!preg_match_all('~\bhttps?://[^\s"\'`<>\\\\{}|^\[\]]{6,600}~i', $text, $matches)) {
        return [];
    }

    $urls = [];
    foreach ($matches[0] as $candidate) {
        $candidate = trimUrlToken($candidate);
        if ($candidate !== '') {
            $urls[] = $candidate;
        }
    }

    return $urls;
}

/**
 * مفاتيح إعدادات المشغّلات: file: "...", source: '...', hls_url = "..."
 *
 * @return array<int, string>
 */
function collectConfigValues(string $text): array
{
    $keys = 'file|files|src|source|sources|url|uri|hls|hlsUrl|hls_url|m3u8'
        . '|stream|streamUrl|stream_url|streamURL|playlist|playlistUrl|manifest'
        . '|manifestUrl|mpd|dash|dashUrl|video|videoUrl|link|play_url|playUrl'
        . '|channel_url|cdn|host|server|path';

    $values = [];

    if (preg_match_all(
        '~["\']?(?:' . $keys . ')["\']?\s*[:=]\s*["\']([^"\']{4,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $values[] = $value;
        }
    }

    if (preg_match_all(
        '~<(?:source|video|audio|track)\b[^>]*?\bsrc\s*=\s*["\']([^"\']{4,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $values[] = $value;
        }
    }

    if (preg_match_all(
        '~\bdata-(?:src|file|url|hls|stream|playlist|video|link|m3u8|source)\s*='
        . '\s*["\']([^"\']{4,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $values[] = $value;
        }
    }

    // استدعاءات المشغّلات: shaka.load("…") / hls.loadSource("…") /
    // jwplayer().setup("…") / videojs src("…") / player.play("…")
    $methods = 'load|loadSource|loadVideo|attachSource|setSrc|setSource|src'
        . '|setup|play|playStream|initPlayer|startPlayer|createPlayer';

    if (preg_match_all(
        '~\b(?:' . $methods . ')\s*\(\s*["\']([^"\']{6,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $values[] = $value;
        }
    }

    return $values;
}

/**
 * @return array<int, string>
 */
function collectIframeUrls(string $text): array
{
    $urls = [];

    if (preg_match_all(
        '~<iframe\b[^>]*?\b(?:data-)?src\s*=\s*["\']([^"\']{4,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $urls[] = $value;
        }
    }

    return $urls;
}

/**
 * ملفات JS/JSON/طلبات AJAX التي يُرجّح أنها تحمل رابط البث.
 *
 * @return array<int, string>
 */
function collectSubResourceUrls(string $text): array
{
    $urls = [];

    if (preg_match_all(
        '~<script\b[^>]*?\bsrc\s*=\s*["\']([^"\']{4,700})["\']~i',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $value) {
            $urls[] = $value;
        }
    }

    $ajax = '~(?:fetch|axios(?:\.(?:get|post))?|\$\.(?:get|post|getJSON)|open)'
        . '\(\s*["\']([^"\']{4,700})["\']~i';
    if (preg_match_all($ajax, $text, $matches)) {
        foreach ($matches[1] as $value) {
            $urls[] = $value;
        }
    }

    if (preg_match_all('~["\'](?:url|ajax|api|endpoint)["\']?\s*:\s*["\']([^"\']{4,700})["\']~i', $text, $matches)) {
        foreach ($matches[1] as $value) {
            $urls[] = $value;
        }
    }

    return $urls;
}

function looksLikeStreamEndpoint(string $url): bool
{
    $lower = strtolower($url);

    if (mediaKind($url) !== null) {
        return true;
    }

    $keywords = [
        'player', 'stream', 'hls', 'live', 'embed', 'source', 'sources',
        'channel', 'getlink', 'getvideo', 'ajax', 'api', 'token', 'config',
        'jwplayer', 'clappr', 'video', 'watch', 'tv', 'cdn', 'play',
    ];

    foreach ($keywords as $keyword) {
        if (strpos($lower, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

// ───────────────────────── محرك الاستخراج ─────────────────────────

/**
 * @param array<string, mixed> $context
 * @param array<string, array<string, mixed>> $candidates
 */
function addCandidate(
    array &$candidates,
    string $url,
    string $baseUrl,
    string $source,
    int $depth,
    array $context,
    int $bonus = 0,
    bool $allowWeak = false
): void {
    $url = trimUrlToken($url);
    if ($url === '' || preg_match('~^(?:data|blob|javascript|mailto|about):~i', $url)) {
        return;
    }

    try {
        $absolute = resolveUrl($baseUrl, $url);
    } catch (Throwable $error) {
        return;
    }

    if (!preg_match('~^https?://~i', $absolute)) {
        return;
    }

    $kind = mediaKind($absolute);

    if ($kind === null) {
        // رابط بلا امتداد معروف (مثل get.php?id=x). كثير من المواقع تقدّم
        // الـ HLS من نقطة كهذه، فنقبله كمرشح ضعيف ويحسمه الفحص الفعلي.
        if (!$allowWeak || !isProbableStreamEndpoint($absolute)) {
            return;
        }

        $kind = 'unknown';
        $bonus -= 40;
    }

    $key = strtolower($absolute);
    if (isset($candidates[$key])) {
        $candidates[$key]['bonus'] += $bonus;
        $candidates[$key]['sources'][$source] = true;
        return;
    }

    $candidates[$key] = [
        'url' => $absolute,
        'kind' => $kind,
        'sources' => [$source => true],
        'depth' => $depth,
        'page' => $baseUrl,
        'referer' => (string) ($context['referer'] ?? ''),
        'bonus' => $bonus,
        'score' => 0,
        'verified' => false,
        'status' => null,
        'master' => false,
        'variants' => [],
        'note' => '',
    ];
}

/**
 * يمشي داخل بنية JSON باحثًا عن روابط البث مع ترجيح أسماء المفاتيح.
 *
 * @param mixed $data
 * @param array<string, array<string, mixed>> $candidates
 * @param array<string, mixed> $context
 */
function walkJsonForMedia(
    $data,
    string $baseUrl,
    array &$candidates,
    int $depth,
    array $context,
    string $keyName = '',
    int $level = 0
): void {
    if ($level > 8) {
        return;
    }

    if (is_string($data)) {
        if (strlen($data) < 4 || strlen($data) > 2000) {
            return;
        }

        $bonus = 0;
        $lowerKey = strtolower($keyName);

        if (strpos($lowerKey, 'no_timeshift') !== false) {
            $bonus += 60;
        }

        foreach (['stream', 'hls', 'm3u8', 'file', 'src', 'playlist', 'manifest', 'url'] as $good) {
            if (strpos($lowerKey, $good) !== false) {
                $bonus += 25;
                break;
            }
        }

        foreach (['ad', 'ads', 'thumb', 'poster', 'logo', 'preview', 'trailer'] as $bad) {
            if ($lowerKey === $bad || strpos($lowerKey, $bad) === 0) {
                $bonus -= 120;
                break;
            }
        }

        addCandidate($candidates, $data, $baseUrl, 'json:' . ($keyName ?: 'value'), $depth, $context, $bonus);
        return;
    }

    if (!is_array($data)) {
        return;
    }

    foreach ($data as $key => $value) {
        walkJsonForMedia(
            $value,
            $baseUrl,
            $candidates,
            $depth,
            $context,
            is_string($key) ? $key : $keyName,
            $level + 1
        );
    }
}

/**
 * تلميحات الاسم المستخرجة من الرابط نفسه ومن #fragment و&hint.
 *
 * @return array<int, string>
 */
function hintTokens(string $targetUrl, string $extraHint): array
{
    $sources = [$extraHint];

    $parts = parse_url($targetUrl);
    if ($parts !== false) {
        $path = (string) ($parts['path'] ?? '');
        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
        if ($segments !== []) {
            $sources[] = (string) end($segments);
        }

        foreach (getUrlQuery($targetUrl) as $name => $value) {
            if (preg_match('/^(name|id|ch|channel|chan|tv|stream|slug|q)$/i', (string) $name)) {
                $sources[] = $value;
            }
        }

        // روابط مثل /m/name=BBC-News
        if (preg_match_all('~(?:name|channel|ch|id)[=:]([A-Za-z0-9._-]{2,60})~i', $path, $matches)) {
            foreach ($matches[1] as $value) {
                $sources[] = $value;
            }
        }
    }

    $tokens = [];
    foreach ($sources as $source) {
        $source = strtolower(trim($source));
        if ($source === '') {
            continue;
        }

        $source = (string) preg_replace('/\.(html?|php|aspx?)$/', '', $source);
        foreach (preg_split('/[^a-z0-9]+/', $source) ?: [] as $token) {
            $stopWords = [
                'www', 'com', 'net', 'org', 'live', 'watch', 'index', 'player',
                'embed', 'video', 'name', 'channel', 'chan', 'stream', 'html',
                'php', 'the', 'tv',
            ];

            if (strlen($token) >= 3 && !in_array($token, $stopWords, true)) {
                $tokens[$token] = true;
            }
        }
    }

    return array_keys($tokens);
}

/**
 * @param array<string, mixed> $candidate
 * @param array<string, mixed> $context
 */
function scoreCandidate(array $candidate, array $context): int
{
    $url = strtolower($candidate['url']);
    $score = (int) $candidate['bonus'];

    $score += match ($candidate['kind']) {
        'hls' => 120,
        'dash' => 70,
        'file' => 30,
        'audio' => 20,
        'unknown' => 10,
        default => 0,
    };

    if ($candidate['verified']) {
        $score += 1000;
    }

    if ($candidate['master']) {
        $score += 150;
    }

    foreach (['master', 'playlist', 'index.m3u8', 'chunklist', 'live', 'manifest'] as $good) {
        if (strpos($url, $good) !== false) {
            $score += 20;
        }
    }

    foreach (['ads', '/ad/', 'advert', 'preroll', 'preview', 'trailer', 'sample', 'thumb', 'blank', 'test'] as $bad) {
        if (strpos($url, $bad) !== false) {
            $score -= 200;
        }
    }

    if (strpos($url, 'audio_only') !== false || strpos($url, 'audio-only') !== false) {
        $score -= 60;
    }

    $tokens = (array) ($context['hints'] ?? []);
    foreach ($tokens as $token) {
        if (is_string($token) && $token !== '' && strpos($url, $token) !== false) {
            $score += 220;
        }
    }

    // الأقرب للصفحة الأصلية أفضل من نتائج iframe العميقة.
    $score -= (int) $candidate['depth'] * 15;

    if (count($candidate['sources']) > 1) {
        $score += 25;
    }

    return $score;
}

/**
 * @return array<string, mixed>
 */
function newDiscoveryContext(string $targetUrl): array
{
    $referer = stringParam('referer');
    $locked = $referer !== '';

    if ($referer === '') {
        $referer = urlOrigin($targetUrl) . '/';
    }

    return [
        'referer' => $referer,
        'referer_locked' => $locked,
        'origin' => rtrim(urlOrigin($referer), '/'),
        'hints' => hintTokens($targetUrl, stringParam('hint', fragmentHint())),
        'target' => $targetUrl,
    ];
}

/**
 * البحث الكامل عن مرشحي البث في صفحة وما تفرّع عنها.
 *
 * @param array<string, mixed> $context
 *
 * @return array{
 *     candidates: array<int, array<string, mixed>>,
 *     visited: array<int, array<string, mixed>>
 * }
 */
function discoverCandidates(string $targetUrl, array $context, int $maxDepth): array
{
    $candidates = [];
    $visited = [];
    $seen = [];
    $queue = [[
        'url' => $targetUrl,
        'depth' => 0,
        'referer' => (string) $context['referer'],
        'role' => 'page',
    ]];
    $fetches = 0;

    while ($queue !== [] && $fetches < MAX_FETCHES) {
        $job = array_shift($queue);
        $jobKey = strtolower($job['url']);

        if (isset($seen[$jobKey])) {
            continue;
        }
        $seen[$jobKey] = true;

        try {
            assertSafeUrl($job['url']);
        } catch (Throwable $error) {
            continue;
        }

        $fetches++;
        $response = httpFetch($job['url'], [
            'referer' => $job['referer'],
            'origin' => rtrim(urlOrigin($job['referer']), '/'),
            'accept' => $job['role'] === 'page'
                ? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                : 'application/json, text/javascript, text/plain, */*',
            'max_bytes' => MAX_SCAN_BYTES,
        ]);

        $visited[] = [
            'url' => $job['url'],
            'final_url' => $response['final_url'],
            'role' => $job['role'],
            'depth' => $job['depth'],
            'status' => $response['status'],
            'bytes' => strlen($response['body']),
            'error' => $response['error'],
        ];

        $body = $response['body'];
        if ($body === '') {
            continue;
        }

        $baseUrl = $response['final_url'];

        // المتصفح يرسل عنوان المستند الحاوي كـ Referer، لذا نستخدم صفحة
        // العثور نفسها (وهي عنوان الـ iframe عند التتبع) إلا إن حدّده المستخدم.
        $pageContext = [
            'referer' => ($context['referer_locked'] ?? false)
                ? (string) $context['referer']
                : $baseUrl,
        ] + $context;

        // (1) JSON منظّم: أدق طريقة عندما تكون الاستجابة JSON.
        $trimmedBody = ltrim($body);
        if ($trimmedBody !== '' && (($trimmedBody[0] === '{') || $trimmedBody[0] === '[')) {
            $decoded = json_decode($trimmedBody, true);
            if (is_array($decoded)) {
                walkJsonForMedia($decoded, $baseUrl, $candidates, $job['depth'], $pageContext);
            }
        }

        // (2) كل نسخ النص (خام / مفكوك / packed / base64).
        foreach (textVariants($body) as $variantName => $variant) {
            $variantBonus = $variantName === 'raw' ? 0 : 15;

            foreach (collectAbsoluteUrls($variant) as $found) {
                addCandidate(
                    $candidates,
                    $found,
                    $baseUrl,
                    'text:' . $variantName,
                    $job['depth'],
                    $pageContext,
                    $variantBonus
                );
            }

            foreach (collectConfigValues($variant) as $found) {
                addCandidate(
                    $candidates,
                    $found,
                    $baseUrl,
                    'config:' . $variantName,
                    $job['depth'],
                    $pageContext,
                    $variantBonus + 35,
                    true // سياق مشغّل قوي: نقبل حتى الروابط بلا امتداد.
                );
            }

            // (3) JSON مضمّن داخل السكربتات.
            if ($variantName === 'raw') {
                foreach (embeddedJsonBlocks($variant) as $block) {
                    $decodedBlock = json_decode($block, true);
                    if (is_array($decodedBlock)) {
                        walkJsonForMedia(
                            $decodedBlock,
                            $baseUrl,
                            $candidates,
                            $job['depth'],
                            $pageContext
                        );
                    }
                }
            }
        }

        if ($job['depth'] >= $maxDepth) {
            continue;
        }

        // (4) تتبع iframes.
        foreach (collectIframeUrls($body) as $iframe) {
            $absolute = safeResolve($baseUrl, $iframe);
            if ($absolute === null) {
                continue;
            }

            $queue[] = [
                'url' => $absolute,
                'depth' => $job['depth'] + 1,
                'referer' => $baseUrl,
                'role' => 'iframe',
            ];
        }

        // (5) تتبع ملفات JS/JSON ذات الكلمات الدلالية.
        $subResources = 0;
        foreach (collectSubResourceUrls($body) as $resource) {
            if ($subResources >= 6) {
                break;
            }

            $absolute = safeResolve($baseUrl, $resource);
            if ($absolute === null || !looksLikeStreamEndpoint($absolute)) {
                continue;
            }

            if (mediaKind($absolute) !== null) {
                continue; // سُجّل كمرشح بالفعل.
            }

            $host = urlHost($absolute);
            $baseHost = urlHost($baseUrl);
            $sameSite = registrableDomain($host) === registrableDomain($baseHost);
            $strongKeyword = (bool) preg_match(
                '~(?:stream|hls|m3u8|getlink|source|player|live|embed|channel)~i',
                $absolute
            );

            if (!$sameSite && !$strongKeyword) {
                continue;
            }

            $subResources++;
            $queue[] = [
                'url' => $absolute,
                'depth' => $job['depth'] + 1,
                'referer' => $baseUrl,
                'role' => 'sub',
            ];
        }
    }

    return ['candidates' => array_values($candidates), 'visited' => $visited];
}

function safeResolve(string $baseUrl, string $relative): ?string
{
    $relative = trim($relative);
    if ($relative === '' || preg_match('~^(?:data|blob|javascript|about|mailto):~i', $relative)) {
        return null;
    }

    try {
        $absolute = resolveUrl($baseUrl, $relative);
    } catch (Throwable $error) {
        return null;
    }

    return preg_match('~^https?://~i', $absolute) ? $absolute : null;
}

/**
 * كتل JSON المضمّنة في الصفحة (مثل window.config = {...}).
 *
 * @return array<int, string>
 */
function embeddedJsonBlocks(string $text): array
{
    $blocks = [];

    // نمط الأقواس المتداخلة مكلف على الملفات الضخمة، فنحدّه بأول 512KB.
    if (strlen($text) > 524288) {
        $text = substr($text, 0, 524288);
    }

    if (preg_match_all(
        '~(?:=|\(|:)\s*(\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\})~s',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $block) {
            if (
                strlen($block) > 24
                && strlen($block) < 200000
                && preg_match('~https?:|m3u8|\.mpd~i', $block)
            ) {
                $blocks[] = $block;
            }

            if (count($blocks) >= 25) {
                break;
            }
        }
    }

    if (preg_match_all(
        '~<script[^>]*type\s*=\s*["\'](?:application/json|application/ld\+json)["\'][^>]*>(.*?)</script>~is',
        $text,
        $matches
    )) {
        foreach ($matches[1] as $block) {
            $blocks[] = trim($block);
        }
    }

    return $blocks;
}

// ─────────────────── التحقق من الروابط وقراءة الجودات ───────────────────

/**
 * @return array<string, string>
 */
function parseHlsAttributes(string $attributes): array
{
    $result = [];

    preg_match_all('/([A-Z0-9-]+)=("[^"]*"|[^,]*)/i', $attributes, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $result[strtoupper($match[1])] = trim($match[2], "\" \t");
    }

    return $result;
}

/**
 * @return array<int, array<string, mixed>>
 */
function parseMasterVariants(string $playlist, string $masterUrl): array
{
    $lines = preg_split('/\r\n|\r|\n/', $playlist);
    if ($lines === false) {
        return [];
    }

    $variants = [];
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $line = trim($lines[$i]);
        if (strpos($line, '#EXT-X-STREAM-INF:') !== 0) {
            continue;
        }

        $attributes = parseHlsAttributes(substr($line, strlen('#EXT-X-STREAM-INF:')));
        $variantUri = null;

        for ($j = $i + 1; $j < $lineCount; $j++) {
            $candidate = trim($lines[$j]);
            if ($candidate === '' || substr($candidate, 0, 1) === '#') {
                continue;
            }

            $variantUri = $candidate;
            $i = $j;
            break;
        }

        if ($variantUri === null) {
            continue;
        }

        $width = null;
        $height = null;

        if (
            isset($attributes['RESOLUTION'])
            && preg_match('/^(\d+)x(\d+)$/i', $attributes['RESOLUTION'], $resolution)
        ) {
            $width = (int) $resolution[1];
            $height = (int) $resolution[2];
        }

        $bandwidth = isset($attributes['BANDWIDTH'])
            ? (int) $attributes['BANDWIDTH']
            : (int) ($attributes['AVERAGE-BANDWIDTH'] ?? 0);

        $variants[] = [
            'label' => $height !== null
                ? $height . 'p'
                : ($bandwidth > 0 ? (string) round($bandwidth / 1000) . 'kbps' : 'variant'),
            'resolution' => $width !== null && $height !== null ? $width . 'x' . $height : null,
            'width' => $width,
            'height' => $height,
            'bandwidth' => $bandwidth,
            'codecs' => $attributes['CODECS'] ?? null,
            'url' => inheritAuthQuery($masterUrl, resolveUrl($masterUrl, $variantUri)),
        ];
    }

    usort($variants, static function (array $first, array $second): int {
        $firstScore = ((int) ($first['height'] ?? 0) * 10000000) + (int) ($first['bandwidth'] ?? 0);
        $secondScore = ((int) ($second['height'] ?? 0) * 10000000) + (int) ($second['bandwidth'] ?? 0);

        return $secondScore <=> $firstScore;
    });

    return $variants;
}

function isHlsPlaylist(string $body, string $contentType, string $url): bool
{
    if (strpos(ltrim($body), '#EXTM3U') === 0) {
        return true;
    }

    if (stripos($contentType, 'mpegurl') !== false) {
        return true;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

    return (bool) preg_match('/\.(m3u8|m3u)$/i', $path);
}

function isDashManifest(string $body, string $contentType, string $url): bool
{
    if (stripos($contentType, 'dash+xml') !== false) {
        return true;
    }

    if (stripos($body, '<MPD') !== false) {
        return true;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

    return (bool) preg_match('/\.mpd$/i', $path);
}

/**
 * @param array<string, mixed> $candidate
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>
 */
function probeCandidate(array $candidate, array $context): array
{
    $url = (string) $candidate['url'];

    try {
        $url = prepareUpstreamUrl($url, $context);
    } catch (Throwable $error) {
        $candidate['note'] = 'تعذر تجهيز الرابط: ' . $error->getMessage();
        return $candidate;
    }

    $candidate['url'] = $url;

    try {
        if ($candidate['kind'] === 'file' || $candidate['kind'] === 'audio') {
            $response = httpFetch($url, [
                'referer' => (string) $candidate['referer'],
                'range' => 'bytes=0-1023',
                'max_bytes' => 65536,
                'timeout' => 15,
            ]);

            $candidate['status'] = $response['status'];
            $candidate['verified'] = $response['ok'];
            $candidate['note'] = $response['error'];

            return $candidate;
        }

        $response = httpFetch($url, [
            'referer' => (string) $candidate['referer'],
            'accept' => 'application/vnd.apple.mpegurl, application/x-mpegURL, '
                . 'application/dash+xml, */*',
            'max_bytes' => MAX_PLAYLIST_BYTES,
            'timeout' => 20,
        ]);

        $candidate['status'] = $response['status'];
        $candidate['note'] = $response['error'];

        if (!$response['ok']) {
            return $candidate;
        }

        if ($candidate['kind'] === 'dash') {
            $candidate['verified'] = isDashManifest(
                $response['body'],
                $response['content_type'],
                $response['final_url']
            );

            return $candidate;
        }

        // المرشح الضعيف: نحسم نوعه من محتوى الرد نفسه لا من امتداده.
        if ($candidate['kind'] === 'unknown') {
            $body = $response['body'];
            $type = $response['content_type'];

            if (strpos(ltrim($body), '#EXTM3U') === 0 || stripos($type, 'mpegurl') !== false) {
                $candidate['kind'] = 'hls';
            } elseif (isDashManifest($body, $type, $response['final_url'])) {
                $candidate['kind'] = 'dash';
                $candidate['verified'] = true;
                return $candidate;
            } elseif (preg_match('~^(?:video|audio)/~i', $type)) {
                $candidate['kind'] = 'file';
                $candidate['verified'] = true;
                return $candidate;
            } else {
                $candidate['note'] = 'نقطة غير بثّية (الرد ليس قائمة تشغيل).';
                return $candidate;
            }
        }

        if (!isHlsPlaylist($response['body'], $response['content_type'], $response['final_url'])) {
            $candidate['note'] = 'الاستجابة ليست M3U8.';
            return $candidate;
        }

        $candidate['verified'] = true;
        $candidate['url'] = $response['final_url'];
        $candidate['body'] = $response['body'];
        $candidate['master'] = strpos($response['body'], '#EXT-X-STREAM-INF') !== false;
        $candidate['variants'] = $candidate['master']
            ? parseMasterVariants($response['body'], $response['final_url'])
            : [];
    } catch (Throwable $error) {
        $candidate['note'] = $error->getMessage();
    }

    return $candidate;
}

/**
 * @param array<int, array<string, mixed>> $candidates
 * @param array<string, mixed> $context
 *
 * @return array<int, array<string, mixed>>
 */
function rankAndVerify(array $candidates, array $context, bool $verify): array
{
    foreach ($candidates as $index => $candidate) {
        $candidates[$index]['score'] = scoreCandidate($candidate, $context);
    }

    usort(
        $candidates,
        static fn (array $a, array $b): int => $b['score'] <=> $a['score']
    );

    if (!$verify) {
        return $candidates;
    }

    $probes = 0;
    $weakProbes = 0;

    foreach ($candidates as $index => $candidate) {
        if ($probes >= MAX_PROBES) {
            break;
        }

        if ($candidate['kind'] === 'unknown') {
            if ($weakProbes >= MAX_WEAK_CANDIDATES) {
                continue;
            }

            $weakProbes++;
        }

        $probes++;
        $probed = probeCandidate($candidate, $context);
        $probed['score'] = scoreCandidate($probed, $context);
        $candidates[$index] = $probed;

        // إن نجح رابط HLS فلا داعي لإتعاب المصدر أكثر.
        if ($probed['verified'] && $probed['kind'] === 'hls') {
            break;
        }
    }

    usort($candidates, static function (array $a, array $b): int {
        if ($a['verified'] !== $b['verified']) {
            return $a['verified'] ? -1 : 1;
        }

        return $b['score'] <=> $a['score'];
    });

    return $candidates;
}

// ──────────────── توقيع/تجديد روابط المصادر (إضافات مواقع) ────────────────

/**
 * مزوّدو التوقيع: نواة الملف عامة، وهذه إضافات صغيرة لمواقع تحتاج توقيعًا.
 *
 * @return array<int, array{host: string, handler: callable}>
 */
function tokenProviders(): array
{
    return [
        [
            'host' => 'cdn.live.easybroadcast.io',
            'handler' => 'easybroadcastToken',
        ],
    ];
}

/**
 * @param array<string, mixed> $context
 */
function prepareUpstreamUrl(string $url, array $context): string
{
    $host = urlHost($url);

    foreach (tokenProviders() as $provider) {
        if ($host !== $provider['host']) {
            continue;
        }

        $handler = $provider['handler'];
        if (is_callable($handler)) {
            return (string) $handler($url, $context);
        }
    }

    return $url;
}

/**
 * توقيع روابط EasyBroadcast (تُستخدم مثلًا لقنوات SNRT المحفوظة).
 *
 * @param array<string, mixed> $context
 */
function easybroadcastToken(string $url, array $context): string
{
    $query = getUrlQuery($url);
    $expires = isset($query['expires']) ? (int) $query['expires'] : 0;

    if (
        isset($query['token'], $query['token_path'])
        && $query['token'] !== ''
        && $expires > time() + 45
    ) {
        return $url;
    }

    $cleanUrl = changeUrlQuery($url, [
        'token' => null,
        'expires' => null,
        'token_path' => null,
    ]);

    $response = httpFetch(
        'https://token.easybroadcast.io/all?url=' . rawurlencode($cleanUrl),
        [
            'referer' => 'https://snrt.player.easybroadcast.io/',
            'origin' => 'https://snrt.player.easybroadcast.io',
            'accept' => 'text/plain, application/json, */*',
            'max_bytes' => 32768,
            'timeout' => 15,
        ]
    );

    if (!$response['ok']) {
        return $url;
    }

    $raw = trim($response['body']);
    $tokenData = [];

    if ($raw !== '' && substr($raw, 0, 1) === '{') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $tokenData = $decoded;
        }
    } else {
        parse_str(ltrim($raw, '?'), $tokenData);
    }

    $token = (string) ($tokenData['token'] ?? '');
    $tokenPath = (string) ($tokenData['token_path'] ?? '');
    $newExpires = (int) ($tokenData['expires'] ?? 0);

    if (
        $token === ''
        || !preg_match('/^[A-Za-z0-9._~-]+$/', $token)
        || $tokenPath === ''
        || $newExpires <= time()
    ) {
        return $url;
    }

    return changeUrlQuery($cleanUrl, [
        'token' => $token,
        'expires' => $newExpires,
        'token_path' => $tokenPath,
    ]);
}

/**
 * وراثة معطيات الحماية من القائمة الأم إلى المقاطع (Akamai/CloudFront/nginx…).
 */
function inheritAuthQuery(string $sourceUrl, string $targetUrl): string
{
    if (urlHost($sourceUrl) !== urlHost($targetUrl)) {
        return $targetUrl;
    }

    $sourceQuery = getUrlQuery($sourceUrl);
    if ($sourceQuery === []) {
        return $targetUrl;
    }

    $targetQuery = getUrlQuery($targetUrl);
    $authPattern = '~^(?:token|token_path|tokens?|auth|authtoken|sig|signature|hash|hmac'
        . '|md5|key|keypairid|key-pair-id|policy|expires|expire|exp|e|st|ttl|nva|nvb'
        . '|hdnts|hdnea|hdntl|wmsauthsign|akamai_token|secure|sk|s|uid|sid|session'
        . '|sessionid|user|client|ip|cid|tid|pid|v|t)$~i';

    $changes = [];
    foreach ($sourceQuery as $name => $value) {
        if (
            $value !== ''
            && !array_key_exists($name, $targetQuery)
            && preg_match($authPattern, (string) $name)
        ) {
            $changes[(string) $name] = $value;
        }
    }

    return $changes === [] ? $targetUrl : changeUrlQuery($targetUrl, $changes);
}

// ─────────────────────────── بروكسي موقّع ───────────────────────────

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): ?string
{
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
        return null;
    }

    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    return $decoded === false ? null : $decoded;
}

function currentScriptPath(): string
{
    $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '/snrt_player.php');

    if ($path === '' || substr($path, 0, 1) !== '/' || preg_match('/[\r\n?#&]/', $path)) {
        return '/snrt_player.php';
    }

    return $path;
}

/**
 * @param array<string, mixed> $context
 */
function buildProxyUrl(string $upstreamUrl, array $context, bool $isPlaylist = false): string
{
    $payload = (string) json_encode([
        'u' => $upstreamUrl,
        'r' => (string) ($context['referer'] ?? ''),
        'p' => $isPlaylist ? 1 : 0,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $encoded = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $encoded, PROXY_SIGNING_KEY);

    return currentScriptPath()
        . '?resource=' . $encoded
        . '&sig=' . $signature
        . (ACCESS_KEY !== '' ? '&key=' . rawurlencode(ACCESS_KEY) : '');
}

/**
 * @return array{url: string, referer: string, playlist: bool}
 */
function verifyProxyRequest(string $encoded, string $signature): array
{
    $expected = hash_hmac('sha256', $encoded, PROXY_SIGNING_KEY);
    if ($signature === '' || !hash_equals($expected, $signature)) {
        throw new InvalidArgumentException('توقيع رابط المورد غير صالح.');
    }

    $payload = base64UrlDecode($encoded);
    if ($payload === null) {
        throw new InvalidArgumentException('رابط المورد غير صالح.');
    }

    $data = json_decode($payload, true);
    if (!is_array($data) || !isset($data['u']) || !is_string($data['u'])) {
        throw new InvalidArgumentException('بيانات المورد غير صالحة.');
    }

    assertSafeUrl($data['u']);

    return [
        'url' => $data['u'],
        'referer' => is_string($data['r'] ?? null) ? $data['r'] : '',
        'playlist' => (int) ($data['p'] ?? 0) === 1,
    ];
}

/**
 * إعادة كتابة قائمة HLS لتمر كل الروابط عبر هذا الملف.
 *
 * @param array<string, mixed> $context
 */
function rewritePlaylist(string $playlist, string $playlistUrl, array $context): string
{
    $lines = preg_split('/\r\n|\r|\n/', $playlist);
    if ($lines === false) {
        return $playlist;
    }

    $nextIsPlaylist = false;

    foreach ($lines as &$line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        if (substr($trimmed, 0, 1) !== '#') {
            $absolute = inheritAuthQuery($playlistUrl, resolveUrl($playlistUrl, $trimmed));
            $line = buildProxyUrl(
                $absolute,
                $context,
                $nextIsPlaylist || isPlaylistUrl($absolute)
            );
            $nextIsPlaylist = false;
            continue;
        }

        if (
            stripos($trimmed, '#EXT-X-STREAM-INF') === 0
            || stripos($trimmed, '#EXT-X-I-FRAME-STREAM-INF') === 0
        ) {
            $nextIsPlaylist = true;
        }

        if (stripos($line, 'URI=') === false) {
            continue;
        }

        $isMediaTag = stripos($trimmed, '#EXT-X-MEDIA') === 0
            || stripos($trimmed, '#EXT-X-I-FRAME-STREAM-INF') === 0;

        $rewritten = preg_replace_callback(
            '~(^|[,:\s])URI=(?:"([^"]*)"|([^,\s]+))~i',
            static function (array $match) use ($playlistUrl, $context, $isMediaTag): string {
                $uri = isset($match[2]) && $match[2] !== ''
                    ? $match[2]
                    : (string) ($match[3] ?? '');

                if ($uri === '') {
                    return $match[0];
                }

                $absolute = inheritAuthQuery($playlistUrl, resolveUrl($playlistUrl, $uri));

                return $match[1] . 'URI="'
                    . buildProxyUrl($absolute, $context, $isMediaTag || isPlaylistUrl($absolute))
                    . '"';
            },
            $line
        );

        if ($rewritten !== null) {
            $line = $rewritten;
        }
    }
    unset($line);

    return implode("\n", $lines);
}

/**
 * إعادة كتابة مبدئية لملف DASH (BaseURL والقوالب المطلقة).
 *
 * @param array<string, mixed> $context
 */
function rewriteDashManifest(string $manifest, string $manifestUrl, array $context): string
{
    $rewritten = preg_replace_callback(
        '~<BaseURL>\s*([^<]+?)\s*</BaseURL>~i',
        static function (array $match) use ($manifestUrl, $context): string {
            $absolute = inheritAuthQuery($manifestUrl, resolveUrl($manifestUrl, $match[1]));

            return '<BaseURL>' . htmlspecialchars($absolute, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '</BaseURL>';
        },
        $manifest
    );

    if ($rewritten === null) {
        return $manifest;
    }

    if (stripos($rewritten, '<BaseURL>') === false) {
        $directory = (string) preg_replace('~/[^/]*(\?.*)?$~', '/', $manifestUrl);
        $rewritten = (string) preg_replace(
            '~(<MPD\b[^>]*>)~i',
            '$1<BaseURL>' . htmlspecialchars($directory, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</BaseURL>',
            $rewritten,
            1
        );
    }

    return $rewritten;
}

/** تمرير مورد (مقطع/مفتاح) مباشرة إلى العميل بشكل انسيابي. */
function streamProxiedResource(string $url, string $referer): void
{
    assertSafeUrl($url);

    $headOnly = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
    $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    $headersSent = false;
    $needsRewrite = false;
    $responseHeaders = [];
    $status = 0;
    $currentUrl = $url;

    for ($hop = 0; $hop < 6; $hop++) {
        assertSafeUrl($currentUrl);

        $responseHeaders = [];
        $status = 0;
        $redirectTo = null;

        $curl = curl_init($currentUrl);
        if ($curl === false) {
            throw new RuntimeException('تعذر تشغيل cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_NOBODY => $headOnly,
            CURLOPT_COOKIEJAR => cookieJarPath($currentUrl),
            CURLOPT_COOKIEFILE => cookieJarPath($currentUrl),
            CURLOPT_HTTPHEADER => buildRequestHeaders($currentUrl, [
                'referer' => $referer,
                'range' => $range,
            ]),
            CURLOPT_HEADERFUNCTION => static function (
                $handle,
                string $line
            ) use (&$responseHeaders, &$status): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if ($trimmed === '') {
                    return $length;
                }

                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $trimmed, $match)) {
                    $status = (int) $match[1];
                    $responseHeaders = [];
                    return $length;
                }

                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    if ($name !== '') {
                        $responseHeaders[$name] = trim(substr($line, $separator + 1));
                    }
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $handle,
                string $chunk
            ) use (
                &$headersSent,
                &$needsRewrite,
                &$responseHeaders,
                &$status,
                $currentUrl
            ): int {
                if (!$headersSent) {
                    // نقاط بلا امتداد قد تعيد قائمة تشغيل؛ نكتشفها من أول
                    // البايتات ونوقف التمرير لتُعاد كتابتها بدل تسريبها خامًا.
                    $type = $responseHeaders['content-type'] ?? '';
                    if (
                        stripos($type, 'mpegurl') !== false
                        || strpos(ltrim($chunk), '#EXTM3U') === 0
                        || stripos($type, 'dash+xml') !== false
                    ) {
                        $needsRewrite = true;
                        return 0;
                    }

                    emitProxyHeaders($status, $responseHeaders, $currentUrl);
                    $headersSent = true;
                }

                echo $chunk;
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();

                return strlen($chunk);
            },
        ]);

        curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $errorMessage = curl_error($curl);
        curl_close($curl);

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $location = $responseHeaders['location'] ?? '';
            if ($location !== '' && !$headersSent) {
                $redirectTo = resolveUrl($currentUrl, $location);
            }
        }

        if ($redirectTo !== null) {
            $currentUrl = $redirectTo;
            continue;
        }

        if ($needsRewrite) {
            servePlaylistResource($currentUrl, $referer);
        }

        if ($errorNumber !== 0 && !$headersSent) {
            throw new RuntimeException('فشل الاتصال بالمصدر: ' . $errorMessage);
        }

        break;
    }

    if (!$headersSent) {
        emitProxyHeaders($status, $responseHeaders, $currentUrl);
    }

    exit;
}

/**
 * @param array<string, string> $headers
 */
function emitProxyHeaders(int $status, array $headers, string $url): void
{
    http_response_code($status > 0 ? $status : 502);

    $contentType = $headers['content-type'] ?? guessContentType($url);
    header('Content-Type: ' . $contentType);
    header_remove('X-Powered-By');

    // نمرّر البايتات كما هي في هذا المسار، لذا نمرّر content-encoding معها.
    $forward = [
        'content-range', 'accept-ranges', 'content-length',
        'last-modified', 'etag', 'content-encoding',
    ];

    foreach ($forward as $name) {
        if (isset($headers[$name]) && !in_array($name, HOP_HEADERS, true)) {
            header(ucwords($name, '-') . ': ' . $headers[$name]);
        }
    }

    if (!isset($headers['accept-ranges'])) {
        header('Accept-Ranges: bytes');
    }

    header('Cache-Control: public, max-age=20');
    header_remove('Pragma');
}

function guessContentType(string $url): string
{
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

    return match (true) {
        (bool) preg_match('/\.(m3u8|m3u)$/', $path) => 'application/vnd.apple.mpegurl',
        (bool) preg_match('/\.mpd$/', $path) => 'application/dash+xml',
        (bool) preg_match('/\.ts$/', $path) => 'video/mp2t',
        (bool) preg_match('/\.(mp4|m4s|m4v)$/', $path) => 'video/mp4',
        (bool) preg_match('/\.webm$/', $path) => 'video/webm',
        (bool) preg_match('/\.(aac|m4a)$/', $path) => 'audio/aac',
        (bool) preg_match('/\.mp3$/', $path) => 'audio/mpeg',
        (bool) preg_match('/\.vtt$/', $path) => 'text/vtt',
        default => 'application/octet-stream',
    };
}

function serveProxiedResource(string $encoded, string $signature): void
{
    $request = verifyProxyRequest($encoded, $signature);
    $context = ['referer' => $request['referer']];
    $url = prepareUpstreamUrl($request['url'], $context);

    if (!$request['playlist'] && !isPlaylistUrl($url)) {
        // قد تكون هذه نقطة تعيد قائمة تشغيل رغم أن رابطها بلا امتداد،
        // فيستشعر التمرير ذلك ويعود إلى مسار إعادة الكتابة بدل تمريرها خامًا.
        streamProxiedResource($url, $request['referer']);
    }

    servePlaylistResource($url, $request['referer']);
}

function servePlaylistResource(string $url, string $referer): void
{
    $headOnly = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
    $response = httpFetch($url, [
        'referer' => $referer,
        'accept' => 'application/vnd.apple.mpegurl, application/x-mpegURL, '
            . 'application/dash+xml, */*',
        'max_bytes' => MAX_PLAYLIST_BYTES,
        'head' => $headOnly,
        'timeout' => 30,
    ]);

    if ($response['status'] === 0) {
        throw new RuntimeException(
            'فشل الاتصال بالمصدر: ' . ($response['error'] !== '' ? $response['error'] : 'غير معروف')
        );
    }

    $body = $response['body'];
    $finalUrl = $response['final_url'];
    $proxyContext = ['referer' => $referer];

    if (isHlsPlaylist($body, $response['content_type'], $finalUrl)) {
        $body = $headOnly ? '' : rewritePlaylist($body, $finalUrl, $proxyContext);
        http_response_code($response['status']);
        header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
        noStore();
    } elseif (isDashManifest($body, $response['content_type'], $finalUrl)) {
        $body = $headOnly ? '' : rewriteDashManifest($body, $finalUrl, $proxyContext);
        http_response_code($response['status']);
        header('Content-Type: application/dash+xml; charset=utf-8');
        noStore();
    } else {
        http_response_code($response['status']);
        header('Content-Type: ' . ($response['content_type'] !== ''
            ? $response['content_type']
            : guessContentType($finalUrl)));
        header('Cache-Control: public, max-age=20');
        header_remove('Pragma');
    }

    if (!$headOnly) {
        header('Content-Length: ' . strlen($body));
        echo $body;
    }

    exit;
}

// ───────────────────────── الذاكرة المؤقتة ─────────────────────────

function cacheDirectory(): string
{
    $directory = sys_get_temp_dir() . '/generic_player_cache';

    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    return $directory;
}

/**
 * @return array<string, mixed>|null
 */
function cacheGet(string $key): ?array
{
    if (boolParam('nocache')) {
        return null;
    }

    $file = cacheDirectory() . '/' . sha1($key) . '.json';
    if (!is_file($file) || (time() - (int) filemtime($file)) > CACHE_TTL) {
        return null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/**
 * @param array<string, mixed> $data
 */
function cachePut(string $key, array $data): void
{
    $file = cacheDirectory() . '/' . sha1($key) . '.json';
    $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($encoded !== false) {
        @file_put_contents($file, $encoded, LOCK_EX);
    }
}

// ──────────────────────── القنوات المحفوظة ────────────────────────

/**
 * قنوات جاهزة (اختيارية) — النواة تعمل مع أي موقع بدون هذه القائمة.
 *
 * @return array<string, array<string, string>>
 */
function presets(): array
{
    $snrtReferer = 'https://snrt.player.easybroadcast.io/';

    return [
        'al-aoula' => ['title' => 'Al Aoula', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_aloula_w1dqfwm'],
        'laayoune' => ['title' => 'Laayoune TV', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_laayoune_pgagr52'],
        'arryadia' => ['title' => 'Arryadia', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_arryadia_k2tgcj0'],
        'athaqafia' => ['title' => 'Athaqafia', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_arrabia_hthcj4p'],
        'almaghribia' => ['title' => 'Al Maghribia', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_almaghribia_83tz85q'],
        'assadissa' => ['title' => 'Assadissa', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_assadissa_7b7u5n1'],
        'tamazight' => ['title' => 'Tamazight', 'kind' => 'tv', 'referer' => $snrtReferer, 'url' => 'https://api.easybroadcast.io/api/events/73_tamazight_tccybxt'],
        'alidaa-alwatania' => ['title' => 'Al Idaa Al Watania', 'kind' => 'radio', 'referer' => $snrtReferer, 'url' => 'https://cdn.live.easybroadcast.io/live/radio_nationale/playlist.m3u8'],
        'chaine-inter' => ['title' => 'Chaine Inter', 'kind' => 'radio', 'referer' => $snrtReferer, 'url' => 'https://cdn.live.easybroadcast.io/live/radio_inter/playlist.m3u8'],
        'idaat-mohammed-assadiss' => ['title' => 'Idaat Mohammed Assadiss', 'kind' => 'radio', 'referer' => $snrtReferer, 'url' => 'https://cdn.live.easybroadcast.io/live/radio_med_VI/playlist.m3u8'],
        'alidaa-alamazighia' => ['title' => 'Al Idaa Al Amazighia', 'kind' => 'radio', 'referer' => $snrtReferer, 'url' => 'https://cdn.live.easybroadcast.io/live/radio_amazigh/playlist.m3u8'],
    ];
}

/**
 * @return array<string, string>
 */
function presetAliases(): array
{
    return [
        'aloula' => 'al-aoula',
        'aoula' => 'al-aoula',
        'al_aoula' => 'al-aoula',
        'laayoun' => 'laayoune',
        'al-ayoune' => 'laayoune',
        'sports' => 'arryadia',
        'riyadia' => 'arryadia',
        'arrabia' => 'athaqafia',
        'althaqafia' => 'athaqafia',
        'al-maghribia' => 'almaghribia',
        'maghribia' => 'almaghribia',
        'as-sadissa' => 'assadissa',
        'sadissa' => 'assadissa',
        'amazigh-tv' => 'tamazight',
        'watania-radio' => 'alidaa-alwatania',
        'radio-watania' => 'alidaa-alwatania',
        'inter' => 'chaine-inter',
        'quran' => 'idaat-mohammed-assadiss',
        'mohammed-assadiss' => 'idaat-mohammed-assadiss',
        'amazighia-radio' => 'alidaa-alamazighia',
    ];
}

/**
 * @return array<string, string>
 */
function resolvePreset(string $name): array
{
    $name = strtolower(trim($name));
    $aliases = presetAliases();
    $name = $aliases[$name] ?? $name;
    $all = presets();

    if (!isset($all[$name])) {
        throw new InvalidArgumentException(
            'القناة المحفوظة غير موجودة. استخدم presets=1 لعرض القائمة، '
            . 'أو url= لأي موقع آخر.'
        );
    }

    return ['name' => $name] + $all[$name];
}

// ───────────────────────── اختيار الجودة والإخراج ─────────────────────────

function normalizeQuality(string $quality): string
{
    $quality = strtolower(trim($quality));
    $quality = (string) preg_replace('/\s+/', '', $quality);

    if ($quality === '') {
        return 'auto';
    }

    if (preg_match('/^\d+$/', $quality)) {
        return $quality . 'p';
    }

    return $quality;
}

/**
 * @param array<int, array<string, mixed>> $variants
 *
 * @return array<string, mixed>|null
 */
function chooseVariant(array $variants, string $quality): ?array
{
    if ($variants === []) {
        return null;
    }

    if (in_array($quality, ['best', 'source', 'highest', 'max', 'hd'], true)) {
        return $variants[0];
    }

    if (in_array($quality, ['worst', 'lowest', 'min', 'low'], true)) {
        return $variants[count($variants) - 1];
    }

    foreach ($variants as $variant) {
        if (strtolower((string) $variant['label']) === $quality) {
            return $variant;
        }

        if (
            isset($variant['resolution'])
            && strtolower((string) $variant['resolution']) === $quality
        ) {
            return $variant;
        }
    }

    // أقرب ارتفاع للمطلوب (مثال: 700p → 720p).
    if (preg_match('/^(\d+)p$/', $quality, $match)) {
        $wanted = (int) $match[1];
        $closest = null;
        $bestDelta = PHP_INT_MAX;

        foreach ($variants as $variant) {
            $height = (int) ($variant['height'] ?? 0);
            if ($height <= 0) {
                continue;
            }

            $delta = abs($height - $wanted);
            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $closest = $variant;
            }
        }

        if ($closest !== null && $bestDelta <= 200) {
            return $closest;
        }
    }

    return null;
}

function outputPlaylist(string $playlist): void
{
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header('Content-Disposition: inline; filename="stream.m3u8"');
    header('Content-Length: ' . strlen($playlist));
    noStore();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo $playlist;
    }

    exit;
}

// ─────────────────────────── مشغّل HTML ───────────────────────────

function renderPlayerPage(string $targetUrl, string $quality, string $title): void
{
    $streamSrc = '';
    if ($targetUrl !== '') {
        $streamSrc = currentScriptPath()
            . '?url=' . rawurlencode($targetUrl)
            . '&Quality=' . rawurlencode($quality === '' ? 'auto' : $quality)
            . (stringParam('hint') !== '' ? '&hint=' . rawurlencode(stringParam('hint')) : '')
            . (stringParam('referer') !== '' ? '&referer=' . rawurlencode(stringParam('referer')) : '')
            . (ACCESS_KEY !== '' ? '&key=' . rawurlencode(ACCESS_KEY) : '');
    }

    $safeUrl = htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title !== '' ? $title : 'مشغّل البث', ENT_QUOTES, 'UTF-8');
    $script = htmlspecialchars(currentScriptPath(), ENT_QUOTES, 'UTF-8');

    // داخل <script> لا تُفكّ كيانات HTML، لذا نستخدم json_encode للقيم.
    $jsFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        | JSON_HEX_APOS | JSON_HEX_QUOT;
    $jsStream = (string) json_encode($streamSrc, $jsFlags);
    $jsPage = (string) json_encode($targetUrl, $jsFlags);
    $jsScript = (string) json_encode(currentScriptPath(), $jsFlags);

    header('Content-Type: text/html; charset=utf-8');
    noStore();

    echo <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle}</title>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
<style>
:root { color-scheme: dark; }
* { box-sizing: border-box; }
body {
    margin: 0; background: #0b0f16; color: #e8edf5;
    font-family: system-ui, "Segoe UI", Tahoma, sans-serif;
}
.wrap { max-width: 980px; margin: 0 auto; padding: 18px; }
h1 { font-size: 18px; margin: 0 0 14px; font-weight: 700; }
h1 span { color: #4ea1ff; }
form { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
input[type=url], input[type=text] {
    flex: 1 1 320px; min-width: 0; padding: 11px 13px; border-radius: 10px;
    border: 1px solid #23303f; background: #121926; color: #e8edf5; font-size: 14px;
}
button {
    padding: 11px 18px; border-radius: 10px; border: 0; cursor: pointer;
    background: #2b6cff; color: #fff; font-size: 14px; font-weight: 600;
}
button.ghost { background: #1b2534; color: #cfe0ff; }
.video-box { position: relative; background: #000; border-radius: 14px; overflow: hidden; }
video { width: 100%; aspect-ratio: 16/9; display: block; background: #000; }
.bar {
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    margin-top: 12px; font-size: 13px;
}
select {
    padding: 9px 11px; border-radius: 9px; background: #121926;
    color: #e8edf5; border: 1px solid #23303f; font-size: 13px;
}
.status { color: #8fa3bd; }
.status.err { color: #ff8080; }
.links { margin-top: 14px; font-size: 13px; color: #8fa3bd; word-break: break-all; }
.links a { color: #4ea1ff; }
code { background: #121926; padding: 2px 6px; border-radius: 6px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>مشغّل <span>M3U8</span> العام — أي موقع</h1>

    <form method="get" action="{$script}">
        <input type="text" name="url" value="{$safeUrl}" placeholder="https://example.com/live/channel">
        <input type="hidden" name="play" value="1">
        <button type="submit">تشغيل</button>
        <button type="button" class="ghost" onclick="showExtract()">الروابط المكتشفة</button>
    </form>

    <div class="video-box">
        <video id="v" controls autoplay playsinline muted></video>
    </div>

    <div class="bar">
        <select id="levels" title="الجودة"><option value="-1">تلقائي</option></select>
        <button class="ghost" onclick="reload()">إعادة المحاولة</button>
        <span id="status" class="status">جاهز</span>
    </div>

    <div class="links" id="links"></div>
</div>

<script>
var STREAM = {$jsStream};
var PAGE = {$jsPage};
var SCRIPT = {$jsScript};
var video = document.getElementById('v');
var statusEl = document.getElementById('status');
var levelsEl = document.getElementById('levels');
var hls = null;

function say(text, isError) {
    statusEl.textContent = text;
    statusEl.className = 'status' + (isError ? ' err' : '');
}

function showExtract() {
    if (!PAGE) { return; }
    window.open(SCRIPT + '?url=' + encodeURIComponent(PAGE) + '&extract=1', '_blank');
}

function reload() { start(); }

function start() {
    if (!STREAM) { say('أدخل رابط صفحة البث للبدء'); return; }
    if (hls) { hls.destroy(); hls = null; }
    levelsEl.innerHTML = '<option value="-1">تلقائي</option>';

    if (window.Hls && Hls.isSupported()) {
        hls = new Hls({
            lowLatencyMode: true,
            backBufferLength: 30,
            manifestLoadingTimeOut: 30000,
            fragLoadingTimeOut: 40000
        });
        hls.on(Hls.Events.MANIFEST_PARSED, function (e, data) {
            say('يشغّل — ' + data.levels.length + ' جودة');
            data.levels.forEach(function (level, index) {
                var option = document.createElement('option');
                option.value = String(index);
                option.textContent = (level.height ? level.height + 'p' : '')
                    + (level.bitrate ? ' · ' + Math.round(level.bitrate / 1000) + 'kbps' : '');
                levelsEl.appendChild(option);
            });
            video.play().catch(function () {});
        });
        hls.on(Hls.Events.ERROR, function (e, data) {
            if (!data.fatal) { return; }
            say('خطأ: ' + data.details, true);
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) { hls.startLoad(); }
            else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) { hls.recoverMediaError(); }
        });
        hls.loadSource(STREAM);
        hls.attachMedia(video);
    } else {
        video.src = STREAM;
        video.addEventListener('error', function () { say('تعذر التشغيل في هذا المتصفح', true); });
        say('تشغيل أصلي (HLS مدمج)');
    }
}

levelsEl.addEventListener('change', function () {
    if (hls) { hls.currentLevel = parseInt(levelsEl.value, 10); }
});

if (STREAM) {
    document.getElementById('links').innerHTML =
        'رابط القائمة: <a href="' + STREAM + '">' + STREAM + '</a>';
    start();
} else {
    say('أدخل رابط أي صفحة بث ثم اضغط تشغيل');
    document.getElementById('links').innerHTML =
        'أمثلة: <code>?url=https://site.com/live/bbc&amp;play=1</code> · '
        + '<code>?url=...&amp;extract=1</code> · <code>?name=arryadia&amp;play=1</code>';
}
</script>
</body>
</html>
HTML;

    exit;
}

// ─────────────────────────── المسار الرئيسي ───────────────────────────

/** يسمح باستيراد الملف في الاختبارات دون تنفيذ الطلب. */
if (defined('GENERIC_PLAYER_LIBRARY_ONLY') && GENERIC_PLAYER_LIBRARY_ONLY === true) {
    return;
}

sendCors();
noStore();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

@set_time_limit(180);
@ini_set('default_socket_timeout', '30');

try {
    if (ACCESS_KEY !== '' && !hash_equals(ACCESS_KEY, stringParam('key'))) {
        failJson('مفتاح الوصول غير صحيح.', 403);
    }

    // 1) تمرير الموارد الموقّعة (المقاطع/القوائم الفرعية/المفاتيح).
    if (isset($_GET['resource'])) {
        serveProxiedResource((string) $_GET['resource'], (string) ($_GET['sig'] ?? ''));
    }

    // 2) قائمة القنوات المحفوظة.
    if (boolParam('presets') || boolParam('channels')) {
        $list = [];
        foreach (presets() as $name => $preset) {
            $list[] = [
                'name' => $name,
                'title' => $preset['title'],
                'kind' => $preset['kind'],
                'url' => currentScriptPath() . '?name=' . rawurlencode($name) . '&Quality=auto',
            ];
        }

        sendJson([
            'ok' => true,
            'note' => 'هذه اختصارات فقط — استخدم ?url= مع أي موقع آخر.',
            'presets' => $list,
        ]);
    }

    // 3) تحديد الهدف: رابط مباشر أو قناة محفوظة.
    $targetUrl = requestedTargetUrl();
    $presetTitle = '';
    $presetReferer = '';

    if ($targetUrl === '' && stringParam('name') !== '') {
        $preset = resolvePreset(stringParam('name'));
        $targetUrl = $preset['url'];
        $presetTitle = $preset['title'];
        $presetReferer = $preset['referer'] ?? '';
    }

    $quality = normalizeQuality(stringParam('Quality', stringParam('quality', stringParam('q', 'auto'))));

    if ($targetUrl === '') {
        // لا رابط: نعرض واجهة الإدخال (أو JSON لمن يطلب JSON).
        if (boolParam('info') || boolParam('extract') || strtolower(stringParam('format')) === 'json') {
            failJson('لم يتم إرسال رابط. استخدم ?url=https://example.com/page', 400, [
                'usage' => [
                    'stream' => currentScriptPath() . '?url=PAGE_URL',
                    'player' => currentScriptPath() . '?url=PAGE_URL&play=1',
                    'extract' => currentScriptPath() . '?url=PAGE_URL&extract=1',
                    'presets' => currentScriptPath() . '?presets=1',
                ],
            ]);
        }

        renderPlayerPage('', $quality, 'مشغّل M3U8 العام');
    }

    $targetUrl = normalizeInputUrl($targetUrl);

    // نفحص هدف المستخدم مباشرة ليحصل على سبب الرفض الحقيقي (لا "لم يوجد بث").
    assertSafeUrl($targetUrl);

    if (boolParam('play') || boolParam('embed')) {
        renderPlayerPage($targetUrl, $quality, $presetTitle);
    }

    $context = newDiscoveryContext($targetUrl);
    if ($presetReferer !== '' && stringParam('referer') === '') {
        $context['referer'] = $presetReferer;
        $context['referer_locked'] = true;
        $context['origin'] = rtrim(urlOrigin($presetReferer), '/');
    }

    $depth = max(0, min(3, (int) stringParam('depth', (string) DEFAULT_DEPTH)));
    $verify = boolParam('verify', true);
    $wantsDebug = boolParam('debug');
    $wantsExtract = boolParam('extract');

    $cacheKey = 'v2|' . $targetUrl . '|' . implode(',', $context['hints'])
        . '|' . $depth . '|' . $context['referer'];

    $selected = null;
    $candidates = [];
    $visited = [];
    $fromCache = false;

    // المسار السريع: الرابط المُرسل هو بث مباشرة.
    if (mediaKind($targetUrl) !== null) {
        $direct = [
            'url' => $targetUrl,
            'kind' => (string) mediaKind($targetUrl),
            'sources' => ['input' => true],
            'depth' => 0,
            'page' => $targetUrl,
            'referer' => (string) $context['referer'],
            'bonus' => 500,
            'score' => 0,
            'verified' => false,
            'status' => null,
            'master' => false,
            'variants' => [],
            'note' => 'رابط بث مُرسل مباشرة.',
        ];

        $candidates = [$verify ? probeCandidate($direct, $context) : $direct];
        $candidates[0]['score'] = scoreCandidate($candidates[0], $context);
        $selected = $candidates[0];
    } else {
        $cached = $wantsExtract || $wantsDebug ? null : cacheGet($cacheKey);

        if ($cached !== null && isset($cached['url'])) {
            $fromCache = true;
            $selected = [
                'url' => (string) $cached['url'],
                'kind' => (string) ($cached['kind'] ?? 'hls'),
                'sources' => ['cache' => true],
                'depth' => 0,
                'page' => $targetUrl,
                'referer' => (string) ($cached['referer'] ?? $context['referer']),
                'bonus' => 0,
                'score' => 0,
                'verified' => true,
                'status' => 200,
                'master' => (bool) ($cached['master'] ?? false),
                'variants' => [],
                'note' => 'من الذاكرة المؤقتة.',
            ];
        } else {
            $discovery = discoverCandidates($targetUrl, $context, $depth);
            $visited = $discovery['visited'];
            $candidates = rankAndVerify($discovery['candidates'], $context, $verify);

            foreach ($candidates as $candidate) {
                if (!$verify || $candidate['verified']) {
                    $selected = $candidate;
                    break;
                }
            }
        }
    }

    // 4) تقرير الاستخراج (extract / debug).
    if ($wantsExtract || $wantsDebug) {
        $showAll = boolParam('all') || $wantsDebug;
        $list = [];

        foreach ($candidates as $candidate) {
            if (!$showAll && $verify && !$candidate['verified']) {
                continue;
            }

            $entry = [
                'url' => $candidate['url'],
                'kind' => $candidate['kind'],
                'verified' => $candidate['verified'],
                'status' => $candidate['status'],
                'master' => $candidate['master'],
                'score' => $candidate['score'],
                'found_in' => array_keys($candidate['sources']),
                'page' => $candidate['page'],
                // رابط تشغيل خاص بهذا الرابط تحديدًا (مع Referer الصحيح).
                'play' => currentScriptPath()
                    . '?url=' . rawurlencode((string) $candidate['url'])
                    . '&referer=' . rawurlencode((string) $candidate['referer'])
                    . '&play=1',
                'proxy' => buildProxyUrl(
                    (string) $candidate['url'],
                    ['referer' => (string) $candidate['referer']],
                    in_array($candidate['kind'], ['hls', 'dash'], true)
                        || isPlaylistUrl((string) $candidate['url'])
                ),
                'referer' => $candidate['referer'],
                'qualities' => array_map(
                    static fn (array $variant): array => [
                        'label' => $variant['label'],
                        'resolution' => $variant['resolution'],
                        'bandwidth' => $variant['bandwidth'],
                    ],
                    $candidate['variants']
                ),
            ];

            if ($candidate['note'] !== '') {
                $entry['note'] = $candidate['note'];
            }

            $list[] = $entry;
        }

        $report = [
            'ok' => $list !== [],
            'target' => $targetUrl,
            'hints' => $context['hints'],
            'referer' => $context['referer'],
            'depth' => $depth,
            'verified_only' => !$showAll,
            'count' => count($list),
            'streams' => $list,
        ];

        if ($wantsDebug) {
            $report['visited'] = $visited;
            $report['selected'] = $selected['url'] ?? null;
        }

        sendJson($report, $list === [] ? 404 : 200);
    }

    if ($selected === null) {
        failJson('لم يتم العثور على رابط بث صالح في هذه الصفحة.', 404, [
            'target' => $targetUrl,
            'hint' => 'جرّب &depth=3 أو &verify=0 أو &extract=1&all=1 لرؤية المرشحين.',
            'candidates' => array_slice(array_map(
                static fn (array $candidate): array => [
                    'url' => $candidate['url'],
                    'kind' => $candidate['kind'],
                    'status' => $candidate['status'],
                    'note' => $candidate['note'],
                ],
                $candidates
            ), 0, 10),
        ]);
    }

    $streamContext = ['referer' => (string) $selected['referer']];
    $streamUrl = prepareUpstreamUrl((string) $selected['url'], $streamContext);

    if (!$fromCache && $selected['verified']) {
        cachePut($cacheKey, [
            'url' => $selected['url'],
            'kind' => $selected['kind'],
            'referer' => $selected['referer'],
            'master' => $selected['master'],
        ]);
    }

    // 5) الرابط الأصلي كما يفعل 1DM عند "نسخ الرابط".
    if (boolParam('raw')) {
        sendJson([
            'ok' => true,
            'target' => $targetUrl,
            'stream_url' => $streamUrl,
            'kind' => $selected['kind'],
            'master' => $selected['master'],
            'headers' => [
                'User-Agent' => USER_AGENT,
                'Referer' => (string) $selected['referer'],
                'Origin' => rtrim(urlOrigin((string) $selected['referer']), '/'),
            ],
            'proxy_url' => buildProxyUrl(
                $streamUrl,
                $streamContext,
                in_array($selected['kind'], ['hls', 'dash'], true)
                    || isPlaylistUrl($streamUrl)
            ),
        ]);
    }

    // 6) الملفات غير HLS: تمرير مباشر.
    if ($selected['kind'] === 'file' || $selected['kind'] === 'audio') {
        streamProxiedResource($streamUrl, (string) $selected['referer']);
    }

    if ($selected['kind'] === 'dash') {
        $manifest = httpFetch($streamUrl, [
            'referer' => (string) $selected['referer'],
            'accept' => 'application/dash+xml, */*',
            'max_bytes' => MAX_PLAYLIST_BYTES,
        ]);

        if (!$manifest['ok']) {
            throw new RuntimeException('فشل فتح ملف DASH. HTTP ' . $manifest['status']);
        }

        header('Content-Type: application/dash+xml; charset=utf-8');
        noStore();
        echo rewriteDashManifest($manifest['body'], $manifest['final_url'], $streamContext);
        exit;
    }

    // 7) HLS: نجلب القائمة (إن لم تكن محفوظة من التحقق).
    $playlistBody = isset($selected['body']) ? (string) $selected['body'] : '';
    $playlistUrl = $streamUrl;

    if ($playlistBody === '' || $fromCache) {
        $response = httpFetch($streamUrl, [
            'referer' => (string) $selected['referer'],
            'accept' => 'application/vnd.apple.mpegurl, application/x-mpegURL, */*',
            'max_bytes' => MAX_PLAYLIST_BYTES,
        ]);

        if (!$response['ok']) {
            throw new RuntimeException('فشل فتح قائمة البث. HTTP ' . $response['status']);
        }

        if (!isHlsPlaylist($response['body'], $response['content_type'], $response['final_url'])) {
            throw new RuntimeException('الرابط المستخرج ليس M3U8 صالحًا.');
        }

        $playlistBody = $response['body'];
        $playlistUrl = $response['final_url'];
    }

    $variants = parseMasterVariants($playlistBody, $playlistUrl);

    // 8) معلومات الجودات.
    if (boolParam('info')) {
        sendJson([
            'ok' => true,
            'target' => $targetUrl,
            'title' => $presetTitle !== '' ? $presetTitle : null,
            'stream_url' => $playlistUrl,
            'kind' => 'hls',
            'is_master' => $variants !== [],
            'requested_quality' => $quality,
            'available_qualities' => array_map(
                static fn (array $variant): array => [
                    'label' => $variant['label'],
                    'resolution' => $variant['resolution'],
                    'bandwidth' => $variant['bandwidth'],
                    'codecs' => $variant['codecs'],
                    'url' => currentScriptPath() . '?url=' . rawurlencode($targetUrl)
                        . '&Quality=' . rawurlencode((string) $variant['label']),
                ],
                $variants
            ),
            'player' => currentScriptPath() . '?url=' . rawurlencode($targetUrl) . '&play=1',
        ]);
    }

    // 9) auto → Master كامل بكل الجودات.
    if (in_array($quality, ['auto', 'master', ''], true) || $variants === []) {
        outputPlaylist(rewritePlaylist($playlistBody, $playlistUrl, $streamContext));
    }

    $variant = chooseVariant($variants, $quality);
    if ($variant === null) {
        failJson('الجودة المطلوبة غير متوفرة.', 404, [
            'requested_quality' => $quality,
            'available_qualities' => array_map(
                static fn (array $item): string => (string) $item['label'],
                $variants
            ),
        ]);
    }

    $variantUrl = prepareUpstreamUrl((string) $variant['url'], $streamContext);
    $variantResponse = httpFetch($variantUrl, [
        'referer' => (string) $selected['referer'],
        'accept' => 'application/vnd.apple.mpegurl, application/x-mpegURL, */*',
        'max_bytes' => MAX_PLAYLIST_BYTES,
    ]);

    if (!$variantResponse['ok']) {
        throw new RuntimeException('فشل فتح الجودة المختارة. HTTP ' . $variantResponse['status']);
    }

    if (!isHlsPlaylist(
        $variantResponse['body'],
        $variantResponse['content_type'],
        $variantResponse['final_url']
    )) {
        throw new RuntimeException('ملف الجودة المختارة ليس M3U8 صالحًا.');
    }

    outputPlaylist(rewritePlaylist(
        $variantResponse['body'],
        $variantResponse['final_url'],
        $streamContext
    ));
} catch (InvalidArgumentException $error) {
    failJson($error->getMessage(), 400);
} catch (Throwable $error) {
    failJson($error->getMessage(), 502);
}
