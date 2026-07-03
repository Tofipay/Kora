<?php
/**
 * ysscores_scraper.php
 * ---------------------------------------------------------------
 * يجلب مباريات موقع YSScores الرسمي حسب التاريخ المطلوب فقط.
 *
 * الاستخدام:
 *      ysscores_scraper.php?date=2026-07-02
 *      ysscores_scraper.php?date=2026-07-03
 *      ysscores_scraper.php                 (يعرض مباريات الصفحة كما هي)
 *
 * آلية العمل (بدون أي قيم ثابتة وبدون الاعتماد على تاريخ اليوم):
 *  1) يجلب HTML الحقيقي للصفحة الرسمية /ar/index في كل طلب.
 *  2) يحلل السورس لاكتشاف كيفية تغيير التاريخ داخل الصفحة نفسها:
 *       - توكن CSRF (موقع Laravel).
 *       - أي نداء AJAX / fetch / XMLHttpRequest داخل سكربتات الصفحة.
 *       - أي <form> يحتوي حقل تاريخ (أسهم التنقل / أيقونة التقويم).
 *       - أي رابط داخل الصفحة يحمل باراميتر تاريخ.
 *  3) ينفذ نفس الطلب الداخلي الذي تستعمله الصفحة (بنفس الكوكيز
 *     والتوكن وترويسة X-Requested-With) لجلب مباريات التاريخ المطلوب.
 *  4) يتحقق أن الاستجابة تخص التاريخ المطلوب فعلاً (يقرأ تاريخ كل
 *     مباراة من خصائص عناصرها) ويفلتر بصرامة، وإن لم تحمل العناصر
 *     تاريخًا يجري "اختبار استجابة" بطلب تاريخ مجاور ومقارنة النتيجتين
 *     للتأكد أن الـ endpoint يحترم باراميتر التاريخ.
 *  5) إن لم توجد مباريات في التاريخ المطلوب يرجع {"status":true,"data":[]}
 * ---------------------------------------------------------------
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
mb_internal_encoding('UTF-8');

header('Content-Type: application/json; charset=utf-8');

final class YSScoresScraper
{
    private const BASE_URL   = 'https://www.ysscores.com';
    private const INDEX_PATH = '/ar/index';
    private const TIMEOUT    = 25;

    private const USER_AGENT =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
        '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    /** @var string ملف الكوكيز (ضروري لجلسة Laravel و CSRF) */
    private string $cookieFile;

    private ?string $csrfToken  = null;
    private ?string $lastError  = null;

    /** كاش نتائج اختبار "هل يحترم الـ endpoint التاريخ؟" */
    private array $honorsCache = [];

    /** كاش استجابات الطلبات داخل نفس التنفيذ لتقليل عدد الطلبات */
    private array $responseCache = [];

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'ysscores_cookies_' . md5(__FILE__) . '.txt';
    }

    /* ============================================================
     * HTTP
     * ============================================================ */

    private function request(string $url, ?array $post = null, bool $ajax = false): ?string
    {
        $cacheKey = ($post === null ? 'GET ' : 'POST ' . http_build_query($post) . ' ') . $url . ($ajax ? ' A' : '');
        if (array_key_exists($cacheKey, $this->responseCache)) {
            return $this->responseCache[$cacheKey];
        }

        $ch = curl_init();
        if ($ch === false) {
            $this->lastError = 'cURL init failed';
            return null;
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en;q=0.8',
            'Referer: ' . self::BASE_URL . self::INDEX_PATH,
        ];
        if ($ajax) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            if ($this->csrfToken !== null) {
                $headers[] = 'X-CSRF-TOKEN: ' . $this->csrfToken;
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',            // gzip/deflate/br تلقائيًا
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            $this->lastError = 'HTTP request failed: ' . ($err ?: ('status ' . $code));
            return $this->responseCache[$cacheKey] = null;
        }
        if ($code >= 400) {
            $this->lastError = 'HTTP status ' . $code . ' for ' . $url;
            return $this->responseCache[$cacheKey] = null;
        }

        return $this->responseCache[$cacheKey] = $body;
    }

    /** يحول رابطًا نسبيًا مستخرجًا من السورس إلى رابط مطلق داخل الموقع الرسمي فقط */
    private function absoluteUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        if ($url === '' || $url[0] === '#'
            || stripos($url, 'javascript:') === 0 || stripos($url, 'data:') === 0) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            // نقبل فقط روابط نفس الموقع الرسمي
            return (stripos($host, 'ysscores.com') !== false) ? $url : null;
        }
        if (str_starts_with($url, '//')) {
            $host = parse_url('https:' . $url, PHP_URL_HOST) ?: '';
            return (stripos($host, 'ysscores.com') !== false) ? 'https:' . $url : null;
        }
        return self::BASE_URL . '/' . ltrim($url, '/');
    }

    /* ============================================================
     * تحليل صفحة الفهرس واكتشاف آلية تغيير التاريخ من السورس
     * ============================================================ */

    private function loadDom(string $html): ?DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // فرض UTF-8 عند التحليل
        $ok = $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $ok ? new DOMXPath($dom) : null;
    }

    private function extractCsrfToken(string $html): void
    {
        if (preg_match('/<meta[^>]+name=["\']csrf-token["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']csrf-token["\']/i', $html, $m)
            || preg_match('/name=["\']_token["\'][^>]*value=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/["\']_token["\']\s*[:=]\s*["\']([^"\']+)["\']/i', $html, $m)) {
            $this->csrfToken = $m[1];
        }
    }

    /**
     * يكتشف من السورس الحقيقي جميع الطرق المحتملة التي تستعملها الصفحة
     * لتغيير التاريخ، مرتبة حسب الأولوية:
     *   - نماذج تحتوي حقل تاريخ (form action + اسم الحقل الفعلي).
     *   - روابط AJAX داخل سكربتات الصفحة قريبة من كلمات date/day/matches.
     *   - روابط <a href> تحمل باراميتر تاريخ.
     *   - صفحات المباريات المذكورة في تنقل الصفحة نفسها (مع اسم حقل التاريخ
     *     المكتشف من عنصر الـ datepicker إن وُجد).
     *
     * كل مرشح: ['method' => GET|POST, 'url' => ..., 'param' => اسم حقل التاريخ]
     */
    private function discoverDateEndpoints(string $html, DOMXPath $xp): array
    {
        $candidates = [];
        $push = function (string $method, ?string $url, string $param) use (&$candidates): void {
            if ($url === null) {
                return;
            }
            $key = strtoupper($method) . ' ' . $url . ' ' . $param;
            foreach ($candidates as $c) {
                if ($c['key'] === $key) {
                    return;
                }
            }
            $candidates[] = [
                'key'    => $key,
                'method' => strtoupper($method),
                'url'    => $url,
                'param'  => $param,
            ];
        };

        /* اسم حقل التاريخ الحقيقي كما تعرّفه الصفحة (datepicker) */
        $dateParam = 'date';
        $dateInputs = $xp->query(
            '//input[@type="date"'
            . ' or contains(@class,"date") or contains(@id,"date")'
            . ' or contains(@name,"date") or contains(@class,"datepicker")]'
        );
        if ($dateInputs !== false) {
            foreach ($dateInputs as $inp) {
                /** @var DOMElement $inp */
                $n = trim($inp->getAttribute('name'));
                if ($n !== '') {
                    $dateParam = $n;
                    break;
                }
            }
        }

        /* 1) نماذج تحتوي حقل تاريخ */
        $forms = $xp->query('//form');
        if ($forms !== false) {
            foreach ($forms as $form) {
                /** @var DOMElement $form */
                $inner = $form->ownerDocument->saveHTML($form);
                if (!preg_match('/date|يوم|تاريخ|calendar|picker/iu', (string) $inner)) {
                    continue;
                }
                $action = $this->absoluteUrl($form->getAttribute('action') ?: self::INDEX_PATH);
                $method = strtoupper($form->getAttribute('method') ?: 'GET');
                $pName  = $dateParam;
                foreach ($xp->query('.//input', $form) ?: [] as $inp) {
                    /** @var DOMElement $inp */
                    $n = trim($inp->getAttribute('name'));
                    if ($n !== '' && preg_match('/date|day/i', $n) && strpos($n, '_token') === false) {
                        $pName = $n;
                        break;
                    }
                }
                $push($method, $action, $pName);
            }
        }

        /* 2) روابط AJAX داخل السكربتات المضمنة في الصفحة */
        if (preg_match_all('#<script\b[^>]*>(.*?)</script>#si', $html, $scripts)) {
            foreach ($scripts[1] as $js) {
                if ($js === '' || !preg_match('/date|matches|day/i', $js)) {
                    continue;
                }
                if (preg_match_all(
                    '/(?:url\s*[:=]\s*|fetch\s*\(\s*|\.open\s*\(\s*["\'](?:GET|POST)["\']\s*,\s*|\$\.(?:post|get|ajax)\s*\(\s*)["\']([^"\']+)["\']/i',
                    $js,
                    $urls
                )) {
                    foreach ($urls[1] as $raw) {
                        $pos = strpos($js, $raw);
                        $ctx = substr($js, max(0, $pos - 400), 900);
                        // نتجاهل روابط لا علاقة لها بالمباريات/التاريخ
                        if (!preg_match('/date|day|matches|match/i', $raw . ' ' . $ctx)) {
                            continue;
                        }
                        $abs = $this->absoluteUrl($raw);
                        if ($abs === null) {
                            continue;
                        }
                        // اسم الباراميتر من سياق النداء إن وُجد: data: { X_date: ... }
                        $p = $dateParam;
                        if (preg_match('/["\']?([A-Za-z_]*date[A-Za-z_]*)["\']?\s*:/i', $ctx, $pm)) {
                            $p = $pm[1];
                        }
                        $isPost = (bool) preg_match('/\.post\s*\(|type\s*[:=]\s*["\']POST|method\s*[:=]\s*["\']POST/i', $ctx);
                        $push($isPost ? 'POST' : 'GET', $abs, $p);
                        $push($isPost ? 'GET' : 'POST', $abs, $p); // الاتجاه الآخر احتياطًا
                    }
                }
            }
        }

        /* 3) عناصر تحمل روابط في خصائص data-* (أسهم السابق/التالي) */
        $dataAttrNodes = $xp->query('//*[@data-url or @data-href or @data-action or @data-link]');
        if ($dataAttrNodes !== false) {
            foreach ($dataAttrNodes as $node) {
                /** @var DOMElement $node */
                foreach (['data-url', 'data-href', 'data-action', 'data-link'] as $a) {
                    $v = $node->getAttribute($a);
                    if ($v !== '' && preg_match('/date|day|match/i', $v . ' ' . $node->getAttribute('class'))) {
                        $push('GET', $this->absoluteUrl($v), $dateParam);
                        $push('POST', $this->absoluteUrl($v), $dateParam);
                    }
                }
            }
        }

        /* 4) روابط <a> تحمل باراميتر تاريخ (طريقة الأسهم إذا كانت روابط عادية) */
        $anchors = $xp->query('//a[contains(@href,"date")]');
        if ($anchors !== false) {
            foreach ($anchors as $a) {
                /** @var DOMElement $a */
                $href = $a->getAttribute('href');
                if (preg_match('/[?&]([A-Za-z_]*date[A-Za-z_]*)=/i', $href, $pm)) {
                    $base = preg_replace('/[?&]' . preg_quote($pm[1], '/') . '=[^&]*/', '', $href);
                    $push('GET', $this->absoluteUrl($base), $pm[1]);
                }
            }
        }

        /* 5) صفحات قوائم المباريات المذكورة داخل تنقل الصفحة نفسها */
        $navLinks = $xp->query('//a[contains(@href,"matches") or contains(@href,"index")]');
        if ($navLinks !== false) {
            foreach ($navLinks as $a) {
                /** @var DOMElement $a */
                $href = $this->absoluteUrl($a->getAttribute('href'));
                if ($href !== null && preg_match('#/(ar|en)/(today_matches|index)$#', $href)) {
                    $push('GET', $href, $dateParam);
                    $push('POST', $href, $dateParam);
                }
            }
        }

        /* احتياط أخير على نفس الموقع الرسمي فقط */
        $push('GET', self::BASE_URL . '/ar/today_matches', $dateParam);
        $push('POST', self::BASE_URL . '/ar/today_matches', $dateParam);
        $push('GET', self::BASE_URL . self::INDEX_PATH, $dateParam);
        $push('POST', self::BASE_URL . self::INDEX_PATH, $dateParam);

        return $candidates;
    }

    /** ينفذ مرشح endpoint لتاريخ معين ويعيد HTML قابل للتحليل */
    private function fetchCandidate(array $candidate, string $date): ?string
    {
        if ($candidate['method'] === 'POST') {
            $post = [$candidate['param'] => $date];
            if ($this->csrfToken !== null) {
                $post['_token'] = $this->csrfToken;
            }
            $body = $this->request($candidate['url'], $post, true);
        } else {
            $sep  = (strpos($candidate['url'], '?') === false) ? '?' : '&';
            $url  = $candidate['url'] . $sep . rawurlencode($candidate['param']) . '=' . rawurlencode($date);
            $body = $this->request($url, null, true)
                 ?? $this->request($url, null, false);
        }
        if ($body === null) {
            return null;
        }
        return $this->extractHtmlPayload($body);
    }

    /** بعض الاستجابات الداخلية ترجع JSON يحتوي HTML؛ نستخرج الجزء القابل للتحليل */
    private function extractHtmlPayload(string $body): string
    {
        $trimmed = ltrim($body);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $json = json_decode($trimmed, true);
            if (is_array($json)) {
                $best = '';
                array_walk_recursive($json, function ($v) use (&$best): void {
                    if (is_string($v) && strpos($v, '<') !== false && mb_strlen($v) > mb_strlen($best)) {
                        $best = $v;
                    }
                });
                if ($best !== '') {
                    return $best;
                }
            }
        }
        return $body;
    }

    /* ============================================================
     * تحليل المباريات من HTML
     * ============================================================ */

    /** يلتقط تاريخًا/وقتًا من خصائص عنصر (Y-m-d أو ISO أو Unix timestamp) */
    private function detectDateTime(array $attrs): array
    {
        $date = null;
        $time = null;
        $ts   = null;

        foreach ($attrs as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            // ISO كامل مع وقت
            if ($ts === null && preg_match('/(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2})?/', $value, $m)) {
                $date = $date ?? $m[1];
                $time = $time ?? $m[2];
                $dt = date_create($m[0]);
                if ($dt !== false) {
                    $ts = $dt->getTimestamp();
                }
                continue;
            }
            // تاريخ فقط
            if ($date === null && preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $value, $m)) {
                $date = $m[1];
                continue;
            }
            // Unix timestamp (ثوانٍ أو ميلي ثانية)
            if ($ts === null && preg_match('/\b(\d{13}|\d{10})\b/', $value, $m)) {
                $cand = (int) $m[1];
                if ($cand > 4102444800) { // ميلي ثانية
                    $cand = intdiv($cand, 1000);
                }
                if ($cand > 946684800 && $cand < 4102444800) { // نطاق منطقي 2000..2100
                    $ts = $cand;
                }
            }
        }

        if ($ts !== null) {
            if ($date === null) {
                $date = gmdate('Y-m-d', $ts);
            }
            if ($time === null) {
                $time = gmdate('H:i', $ts);
            }
        }

        return [$date, $time, $ts];
    }

    private function nodeAttributes(DOMElement $el): array
    {
        $attrs = [];
        foreach ($el->attributes as $attr) {
            $attrs[$attr->nodeName] = $attr->nodeValue;
        }
        return $attrs;
    }

    private function firstText(DOMXPath $xp, string $query, DOMNode $ctx): ?string
    {
        $n = $xp->query($query, $ctx);
        if ($n !== false && $n->length > 0) {
            $t = trim(preg_replace('/\s+/u', ' ', $n->item(0)->textContent));
            return ($t !== '') ? $t : null;
        }
        return null;
    }

    private function firstAttr(DOMXPath $xp, string $query, DOMNode $ctx): ?string
    {
        $n = $xp->query($query, $ctx);
        if ($n !== false && $n->length > 0) {
            $v = trim((string) $n->item(0)->nodeValue);
            return ($v !== '') ? $v : null;
        }
        return null;
    }

    /** يستخرج جميع المباريات وكل البيانات المتاحة من مقطع HTML */
    private function parseMatches(string $html): array
    {
        $xp = $this->loadDom($html);
        if ($xp === null) {
            return [];
        }

        // عناصر المباريات كما تعرفها الصفحة الرسمية
        $nodes = $xp->query('//a[contains(concat(" ", normalize-space(@class), " "), " ajax-match-item ")]');
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xp->query('//*[@match_id] | //*[@data-match-id] | //a[contains(@href,"/match/")]');
        }
        if ($nodes === false) {
            return [];
        }

        $matches = [];
        $seen    = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $attrs = $this->nodeAttributes($node);

            /* معرف المباراة: من الخاصية أو من رابط المباراة نفسه */
            $matchId = $attrs['match_id'] ?? $attrs['data-match-id'] ?? null;
            $href    = $attrs['href'] ?? '';
            if ($matchId === null && preg_match('#/match/(\d+)#', $href, $m)) {
                $matchId = $m[1];
            }
            if ($matchId !== null && isset($seen[$matchId])) {
                continue;
            }
            if ($matchId !== null) {
                $seen[$matchId] = true;
            }

            /* بيانات البطولة: من غلاف المجموعة التي تسبق/تحوي المباراة */
            $champTitle = null;
            $champImage = null;
            $champUrl   = null;
            $champId    = null;

            $wrapper = $xp->query(
                'ancestor::*[contains(@class,"matches-wrapper") or contains(@class,"championship")][1]',
                $node
            );
            $champCtx = ($wrapper !== false && $wrapper->length > 0) ? $wrapper->item(0) : null;
            if ($champCtx !== null) {
                $champTitle = $this->firstText($xp, './/a[contains(@class,"champ-title")]', $champCtx);
                $champUrl   = $this->firstAttr($xp, './/a[contains(@class,"champ-title")]/@href', $champCtx);
                $champImage = $this->firstAttr(
                    $xp,
                    './/a[contains(@class,"champ-title")]//img/@data-src'
                    . ' | .//a[contains(@class,"champ-title")]//img/@data-img'
                    . ' | .//a[contains(@class,"champ-title")]//img/@src',
                    $champCtx
                );
            }
            if ($champUrl !== null && preg_match('#/championship/(\d+)#', $champUrl, $m)) {
                $champId = (int) $m[1];
            }
            if ($champId === null && isset($attrs['champ_id'])) {
                $champId = (int) $attrs['champ_id'];
            }

            /* الفريقان: من خصائص العنصر أولًا ثم من عناصره الداخلية */
            $homeName  = $attrs['home_name']  ?? $this->firstText($xp, './/*[contains(@class,"first-team")]//*[contains(@class,"team-name") or contains(@class,"name")]', $node);
            $awayName  = $attrs['away_name']  ?? $this->firstText($xp, './/*[contains(@class,"second-team")]//*[contains(@class,"team-name") or contains(@class,"name")]', $node);
            $homeImage = $attrs['home_image'] ?? $this->firstAttr($xp, './/*[contains(@class,"first-team")]//img/@data-src | .//*[contains(@class,"first-team")]//img/@src', $node);
            $awayImage = $attrs['away_image'] ?? $this->firstAttr($xp, './/*[contains(@class,"second-team")]//img/@data-src | .//*[contains(@class,"second-team")]//img/@src', $node);
            $homeId    = $attrs['home_id'] ?? $attrs['home_team_id'] ?? null;
            $awayId    = $attrs['away_id'] ?? $attrs['away_team_id'] ?? null;

            /* النتيجة */
            $homeScoreRaw = $this->firstText($xp, './/*[contains(@class,"first-team-result")]', $node);
            $awayScoreRaw = $this->firstText($xp, './/*[contains(@class,"second-team-result")]', $node);
            $homeScore = ($homeScoreRaw !== null && is_numeric($homeScoreRaw)) ? (int) $homeScoreRaw : null;
            $awayScore = ($awayScoreRaw !== null && is_numeric($awayScoreRaw)) ? (int) $awayScoreRaw : null;

            /* الحالة */
            $statusText = $this->firstText($xp, './/*[contains(@class,"result-status-text") or contains(@class,"match-status") or contains(@class,"status")]', $node);
            $classAttr  = $attrs['class'] ?? '';
            $isLive     = (bool) preg_match('/\blive\b|جارية|مباشر/iu', $classAttr . ' ' . (string) $statusText);
            $isFinished = (bool) preg_match('/انتهت|finished|full[- ]?time|\bft\b/iu', (string) $statusText . ' ' . $classAttr);
            $statusCode = $isFinished ? 4 : ($isLive ? 1 : 0);

            /* التاريخ والوقت: من خصائص العنصر وخصائص عناصره الداخلية */
            $allValues = array_values($attrs);
            foreach ($xp->query('.//*[@*]', $node) ?: [] as $child) {
                if ($child instanceof DOMElement) {
                    foreach ($child->attributes as $ca) {
                        $allValues[] = $ca->nodeValue;
                    }
                }
            }
            [$matchDate, $matchTime, $matchTs] = $this->detectDateTime($allValues);

            /* الوقت كما تعرضه الصفحة له الأولوية (بتوقيت الموقع وليس UTC) */
            $timeText = $this->firstText($xp, './/*[contains(@class,"match-time") or contains(@class,"time")]', $node);
            if ($timeText !== null && preg_match('/\b(\d{1,2}):(\d{2})\b/u', $timeText, $m)) {
                $matchTime = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
            } elseif ($matchTime === null
                && preg_match('/\b(\d{1,2}):(\d{2})\b/u', (string) $node->textContent, $m)) {
                $matchTime = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
            }

            /* مؤشرات التغطية إن وجدت كخصائص (تشكيلة/فيديو/إحصائيات/أحداث) */
            $flag = static function (array $attrs, array $names): ?int {
                foreach ($names as $n) {
                    if (isset($attrs[$n])) {
                        return (int) $attrs[$n];
                    }
                }
                return null;
            };

            $matches[] = [
                'match_id'        => ($matchId !== null) ? (int) $matchId : null,
                'match_url'       => $this->absoluteUrl($href),
                'championship_id' => $champId,
                'championship'    => [
                    'id'    => $champId,
                    'title' => $champTitle,
                    'image' => ($champImage !== null) ? $this->absoluteUrl($champImage) ?? $champImage : null,
                    'url'   => ($champUrl !== null) ? $this->absoluteUrl($champUrl) : null,
                ],
                'home_team'       => [
                    'id'    => ($homeId !== null) ? (int) $homeId : null,
                    'title' => $homeName,
                    'image' => ($homeImage !== null) ? $this->absoluteUrl($homeImage) ?? $homeImage : null,
                ],
                'away_team'       => [
                    'id'    => ($awayId !== null) ? (int) $awayId : null,
                    'title' => $awayName,
                    'image' => ($awayImage !== null) ? $this->absoluteUrl($awayImage) ?? $awayImage : null,
                ],
                'match_date'      => $matchDate,
                'match_time'      => $matchTime,
                'match_timestamp' => $matchTs,
                'home_scores'     => $homeScore,
                'away_scores'     => $awayScore,
                'status'          => $statusCode,
                'status_text'     => $statusText,
                'live'            => $isLive ? 1 : 0,
                'events'          => $flag($attrs, ['events', 'has_events']),
                'lineup'          => $flag($attrs, ['lineup', 'has_lineup']),
                'statics'         => $flag($attrs, ['statics', 'statistics', 'has_statics']),
                'video'           => $flag($attrs, ['video', 'has_video']),
                'channel'         => $attrs['channel'] ?? $attrs['channel_name'] ?? null,
                'commentator'     => $attrs['commentator'] ?? $attrs['comm'] ?? null,
                // جميع الخصائص الخام كما وردت في سورس الصفحة (أي بيانات إضافية)
                'attributes'      => $attrs,
            ];
        }

        return $matches;
    }

    /* ============================================================
     * التحقق من أن الـ endpoint يحترم باراميتر التاريخ
     * ============================================================ */

    private function matchIdsSignature(array $matches): string
    {
        $ids = array_map(
            static fn(array $m) => $m['match_id'] ?? ($m['match_url'] ?? ''),
            $matches
        );
        sort($ids);
        return md5(json_encode($ids));
    }

    /**
     * يطلب من نفس الـ endpoint تاريخًا مجاورًا ويقارن مجموعة المباريات؛
     * اختلاف المجموعتين يعني أن الـ endpoint يستجيب فعلاً لباراميتر التاريخ.
     */
    private function endpointHonorsDate(array $candidate, string $date, array $matchesForDate): bool
    {
        $key = $candidate['key'];
        if (isset($this->honorsCache[$key])) {
            return $this->honorsCache[$key];
        }

        $probe = date_create($date);
        if ($probe === false) {
            return $this->honorsCache[$key] = false;
        }
        $probe->modify('+1 day');
        $probeHtml = $this->fetchCandidate($candidate, $probe->format('Y-m-d'));
        if ($probeHtml === null) {
            return $this->honorsCache[$key] = false;
        }
        $probeMatches = $this->parseMatches($probeHtml);

        $honors = $this->matchIdsSignature($matchesForDate) !== $this->matchIdsSignature($probeMatches);

        // لو تطابقت المجموعتان وكلتاهما غير فارغة فالـ endpoint يتجاهل التاريخ
        if (!$honors && empty($matchesForDate) && empty($probeMatches)) {
            // يومان فارغان: نعتبره محترمًا للتاريخ (لا يوجد ما يعرضه)
            $honors = true;
        }

        return $this->honorsCache[$key] = $honors;
    }

    /* ============================================================
     * الواجهة الرئيسية
     * ============================================================ */

    public function getMatches(?string $requestedDate): array
    {
        /* التحقق من صيغة التاريخ */
        if ($requestedDate !== null) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $requestedDate, $m)
                || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return $this->fail('Invalid date format, expected YYYY-MM-DD', 400);
            }
        }

        /* 1) جلب السورس الحقيقي للصفحة الرسمية في كل طلب */
        $indexHtml = $this->request(self::BASE_URL . self::INDEX_PATH);
        if ($indexHtml === null) {
            return $this->fail('Failed to fetch official page: ' . ($this->lastError ?? 'unknown error'), 502);
        }
        $this->extractCsrfToken($indexHtml);

        $xp = $this->loadDom($indexHtml);
        if ($xp === null) {
            return $this->fail('Failed to parse official page HTML', 500);
        }

        /* بدون باراميتر تاريخ: نعرض مباريات الصفحة كما تعرضها هي (لا نعتمد على تاريخ السيرفر) */
        if ($requestedDate === null) {
            return $this->ok($this->parseMatches($indexHtml), null);
        }

        /* 2) اكتشاف آلية تغيير التاريخ من السورس نفسه */
        $candidates = $this->discoverDateEndpoints($indexHtml, $xp);

        $fallback = null; // أفضل نتيجة غير مؤكدة (احتياط)

        foreach ($candidates as $candidate) {
            $html = $this->fetchCandidate($candidate, $requestedDate);
            if ($html === null) {
                continue;
            }
            $matches = $this->parseMatches($html);

            $withDates = array_filter($matches, static fn(array $x) => $x['match_date'] !== null);

            if (!empty($withDates)) {
                /* العناصر تحمل تواريخها: نفلتر بصرامة على التاريخ المطلوب */
                $exact = array_values(array_filter(
                    $matches,
                    static fn(array $x) => $x['match_date'] === $requestedDate
                        || $x['match_date'] === null // عناصر بلا تاريخ ضمن استجابة مؤرخة تُنسب لنفس اليوم
                ));
                // إن كانت الاستجابة تخص التاريخ المطلوب فعلاً ففيها عنصر واحد مؤرخ به على الأقل
                $hasRequested = !empty(array_filter(
                    $matches,
                    static fn(array $x) => $x['match_date'] === $requestedDate
                ));
                if ($hasRequested) {
                    return $this->ok($this->stampDate($exact, $requestedDate), $requestedDate);
                }
                /* الاستجابة مؤرخة بيوم آخر: الـ endpoint إما تجاهل التاريخ أو لا مباريات ذلك اليوم */
                if ($this->endpointHonorsDate($candidate, $requestedDate, $matches)) {
                    // يحترم التاريخ لكن لا توجد مباريات بالتاريخ المطلوب
                    return $this->ok([], $requestedDate);
                }
                continue; // يتجاهل التاريخ: جرّب المرشح التالي
            }

            if (empty($matches)) {
                /* استجابة بلا مباريات: نقبلها فقط إن ثبت أن الـ endpoint يحترم التاريخ */
                if ($this->endpointHonorsDate($candidate, $requestedDate, $matches)) {
                    return $this->ok([], $requestedDate);
                }
                continue;
            }

            /* مباريات بلا تواريخ في خصائصها: نتحقق أن الـ endpoint يحترم التاريخ */
            if ($this->endpointHonorsDate($candidate, $requestedDate, $matches)) {
                return $this->ok($this->stampDate($matches, $requestedDate), $requestedDate);
            }
            if ($fallback === null) {
                $fallback = $matches;
            }
        }

        /* لم يثبت أي endpoint استجابته للتاريخ */
        if ($fallback !== null) {
            // آخر احتياط: لا نستطيع تأكيد التاريخ فنرجع فارغًا بدل بيانات يوم آخر
            return $this->ok([], $requestedDate);
        }

        return $this->ok([], $requestedDate);
    }

    /** يثبت التاريخ المطلوب على العناصر التي لم يُكتشف تاريخها ضمن استجابة مؤكدة */
    private function stampDate(array $matches, string $date): array
    {
        foreach ($matches as &$m) {
            if ($m['match_date'] === null) {
                $m['match_date'] = $date;
            }
            if ($m['match_timestamp'] === null && $m['match_time'] !== null) {
                $dt = date_create($m['match_date'] . ' ' . $m['match_time']);
                if ($dt !== false) {
                    $m['match_timestamp'] = $dt->getTimestamp();
                }
            }
        }
        unset($m);
        return array_values($matches);
    }

    private function ok(array $matches, ?string $date): array
    {
        $live = count(array_filter($matches, static fn(array $m) => $m['live'] === 1));
        return [
            'status'       => true,
            'status_code'  => 200,
            'message'      => 'successful request',
            'date'         => $date,
            'matches_live' => $live,
            'data'         => array_values($matches),
            'errors'       => 0,
        ];
    }

    private function fail(string $message, int $code): array
    {
        return [
            'status'      => false,
            'status_code' => $code,
            'message'     => $message,
            'data'        => [],
            'errors'      => 1,
        ];
    }
}

/* ================================================================
 * نقطة الدخول
 * ================================================================ */

try {
    $date = isset($_GET['date']) && $_GET['date'] !== '' ? (string) $_GET['date'] : null;

    $scraper = new YSScoresScraper();
    $result  = $scraper->getMatches($date);

    if (isset($result['status_code']) && is_int($result['status_code']) && $result['status_code'] >= 400) {
        http_response_code($result['status_code']);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'      => false,
        'status_code' => 500,
        'message'     => 'Internal error: ' . $e->getMessage(),
        'data'        => [],
        'errors'      => 1,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
