<?php
declare(strict_types=1);

/**
 * router.php — محاكي قواعد ‎.htaccess‎ لخادم PHP المدمج (اختبار فقط).
 *
 *      php -S 127.0.0.1:8802 -t . tests/router.php
 *
 * ينفّذ نفس ترتيب القواعد الموجود في ‎.htaccess‎:
 *   1) المقطع الموجود فعلًا داخل hls-cache يُرسَل مباشرة بلا PHP.
 *   2) المسارات المحمية تُمنع.
 *   3) باقي المسارات تُحوَّل إلى index.php.
 *
 * ويسجّل عدّادين: كم طلبًا خدمه الخادم كملف ثابت، وكم طلبًا دخل PHP.
 * هذا ما يثبت أن المقاطع المحفوظة لا تمر عبر PHP مرة ثانية.
 */

$root = dirname(__DIR__);
$counters = __DIR__ . '/.router-counters.json';

$uri = (string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);

$countHit = static function (string $key) use ($counters): void {
    $handle = @fopen($counters, 'c+');

    if ($handle === false) {
        return;
    }

    if (flock($handle, LOCK_EX)) {
        $data = json_decode((string) stream_get_contents($handle), true);

        if (!is_array($data)) {
            $data = [];
        }

        $data[$key] = (int) ($data[$key] ?? 0) + 1;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);
};

if ($uri === '/__router/stats') {
    header('Content-Type: application/json');

    echo is_file($counters)
        ? (string) file_get_contents($counters)
        : '{}';

    return true;
}

if ($uri === '/__router/reset') {
    @unlink($counters);
    header('Content-Type: application/json');
    echo '{"reset":true}';

    return true;
}

/* ── القاعدة 2 في ‎.htaccess‎: ملف ثابت موجود داخل hls-cache ── */
if (
    preg_match(
        '#^/hls-cache/([A-Za-z0-9_-]{8,4096})\.(ts|m4s|mp4|m4a|aac|vtt)$#',
        $uri,
        $matches
    ) === 1
) {
    $file = $root . '/hls-cache/' . $matches[1] . '.' . $matches[2];

    clearstatcache(true, $file);

    if (is_file($file) && filesize($file) > 0) {
        $countHit('static');

        /*
         * يخدمه الخادم مباشرة تمامًا كما يفعل Apache — بلا index.php.
         * نرسله من هنا (وليس بـ return false) لأن خادم PHP المدمج يحدّد
         * الملف عند بداية الطلب، فلو أُنشئ المقطع بعد ذلك بأجزاء من الثانية
         * أعاد 404 وهميًا لا وجود له في Apache.
         */
        $types = [
            'ts' => 'video/mp2t',
            'm4s' => 'video/iso.segment',
            'mp4' => 'video/mp4',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'vtt' => 'text/vtt; charset=utf-8',
        ];

        header('Content-Type: ' . ($types[$matches[2]] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('Accept-Ranges: bytes');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400, s-maxage=86400, immutable');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($file);
        }

        return true;
    }
}

/* ── قواعد المنع ── */
if (
    preg_match('#^/\.(?!well-known/)#', $uri) === 1
    || preg_match(
        '#^/(?:hls-core|worker|viewers|override|config|config\.example)\.php$#',
        $uri
    ) === 1
    || preg_match('#^/(?:tests|loadtest|systemd|supervisor)(?:/|$)#', $uri) === 1
    || preg_match('#\.(?:md|txt|sqlite|service|conf|log)$#i', $uri) === 1
    || preg_match('#^/overrides/.*\.(?:php|json)$#i', $uri) === 1
    || $uri === '/'
    || $uri === '/index.php'
) {
    $countHit('forbidden');
    http_response_code(403);
    $__errorStatus = 403;
    include $root . '/error.php';

    return true;
}

/* ── ملف ثابت آخر موجود فعلًا (مقاطع القوالب…) ── */
$candidate = $root . rawurldecode($uri);

if (is_file($candidate) && !str_ends_with($candidate, '.php')) {
    $countHit('static');

    return false;
}

/* ── صفحات PHP المسموح فتحها مباشرة (لوحة التحكم وصفحة الخطأ) ── */
if (
    preg_match('#^/(panel|error|selfcheck)\.php$#', $uri) === 1
    && is_file($root . $uri)
) {
    $countHit('page');

    require $root . $uri;

    return true;
}

/* ── التوجيه إلى index.php بنفس قواعد ‎.htaccess‎ ── */
$routes = [
    '#^/watch/([0-9]+(?:-[0-9]+){0,2})/index\.m3u8$#' =>
        ['action' => 'redirect_token', 'channels' => 1],
    '#^/api/token/([0-9]+(?:-[0-9]+){0,2})$#' =>
        ['action' => 'issue_token', 'channels' => 1],
    '#^/api/viewer/leave/([0-9]+(?:-[0-9]+){0,2})$#' =>
        ['action' => 'viewer_leave', 'channels' => 1],
    '#^/api/viewer/ping/([0-9]+(?:-[0-9]+){0,2})$#' =>
        ['action' => 'viewer_ping', 'channels' => 1],
    '#^/api/metrics$#' => ['action' => 'metrics'],
    '#^/stream/([0-9]+(?:-[0-9]+){0,2})/index\.m3u8$#' =>
        ['channels' => 1],
    '#^/hls-cache/([A-Za-z0-9_-]{8,4096})\.([a-z0-9]{2,5})$#' =>
        ['action' => 'asset', 'name' => 1, 'ext' => 2],
];

foreach ($routes as $pattern => $mapping) {
    if (preg_match($pattern, $uri, $parts) !== 1) {
        continue;
    }

    foreach ($mapping as $key => $value) {
        $_GET[$key] = is_int($value) ? $parts[$value] : $value;
    }

    $countHit('php');
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    require $root . '/index.php';

    return true;
}

$countHit('notfound');
http_response_code(404);
$__errorStatus = 404;
include $root . '/error.php';

return true;
