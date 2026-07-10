<?php
/**
 * ============================================================================
 *  Qamhad Live API — request bootstrap.
 * ============================================================================
 *  Included first by EVERY endpoint. Responsibilities:
 *    - load config + engine autoloader + helpers
 *    - CORS (allow-list) + preflight
 *    - security headers, input hardening (SSRF / traversal / header validation)
 *    - per-IP rate limiting
 *    - optional public API-key gate
 *    - JSON output with GZIP/Brotli, ETag/304, Last-Modified, Cache-Control
 *  Endpoints stay thin: validate params → call the engine → api_out().
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* PSR-style autoloader for the engine (Qamhad\Core\*, Qamhad\Controllers\*). */
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Qamhad\\')) return;
    $rel  = str_replace(['Qamhad\\', '\\'], ['', '/'], $class);
    $file = __DIR__ . '/engine/' . $rel . '.php';
    if (is_file($file)) require $file;
});

use Qamhad\Core\Lang;

/* ---------- language ---------- */
$__lang = (($_GET['lang'] ?? 'ar') === 'en') ? 'en' : 'ar';
Lang::boot($__lang);
require_once __DIR__ . '/engine/helpers.php';
require_once __DIR__ . '/helpers.php';

@date_default_timezone_set(defined('DEFAULT_TZ') ? DEFAULT_TZ : 'Asia/Riyadh');

/* ============================================================================
 *  CORS
 * ========================================================================== */
function api_cors_origin(): string
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return '*';                 // curl / server-to-server
    $host = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $allowed = getenv('QAMHAD_ALLOWED_ORIGINS')
        ? array_map('trim', explode(',', (string)getenv('QAMHAD_ALLOWED_ORIGINS')))
        : API_ALLOWED_ORIGINS;
    foreach ($allowed as $rule) {
        $rule = trim($rule);
        if ($rule === '*') return $origin;
        if (str_starts_with($rule, '*.')) {
            $suffix = substr($rule, 1);             // ".blogspot.com"
            if ($host === substr($suffix, 1) || str_ends_with($host, $suffix)) return $origin;
            continue;
        }
        $ruleHost = strtolower((string)parse_url($rule, PHP_URL_HOST)) ?: strtolower($rule);
        if ($host === $ruleHost) return $origin;
    }
    return API_HOST_URL; // not allow-listed: reflect our own host (blocks the XHR)
}

header('Access-Control-Allow-Origin: ' . api_cors_origin());
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-API-Key, X-Requested-With, If-None-Match');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin, Accept-Encoding, Accept-Language');

/* Security headers. */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Cross-Origin-Resource-Policy: cross-origin');
header_remove('X-Powered-By');

/* Preflight ends here. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ============================================================================
 *  Rate limiting  (token bucket per IP, file backed, atomic)
 * ========================================================================== */
function api_client_ip(): string
{
    // Cloudflare / proxy aware, then validated.
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
        $v = (string)($_SERVER[$h] ?? '');
        if ($v === '') continue;
        $v = trim(explode(',', $v)[0]);
        if (filter_var($v, FILTER_VALIDATE_IP)) return $v;
    }
    return '0.0.0.0';
}

function api_rate_guard(): void
{
    if (API_RATE_LIMIT <= 0) return;
    if (!is_dir(API_STATE_DIR)) @mkdir(API_STATE_DIR, 0755, true);
    $ip   = api_client_ip();
    $file = API_STATE_DIR . '/rl_' . md5($ip) . '.json';
    $now  = time();
    $win  = max(1, API_RATE_WINDOW);

    $fh = @fopen($file, 'c+');
    if (!$fh) return;                                // fail-open: never hard-block on FS error
    @flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh) ?: '';
    $data = json_decode($raw, true);
    $start = is_array($data) ? (int)($data['start'] ?? 0) : 0;
    $count = is_array($data) ? (int)($data['count'] ?? 0) : 0;
    if ($now - $start >= $win) { $start = $now; $count = 0; }
    $count++;

    $remaining = max(0, API_RATE_LIMIT - $count);
    header('X-RateLimit-Limit: ' . API_RATE_LIMIT);
    header('X-RateLimit-Remaining: ' . $remaining);
    header('X-RateLimit-Reset: ' . ($start + $win));

    @ftruncate($fh, 0);
    @rewind($fh);
    @fwrite($fh, json_encode(['start' => $start, 'count' => $count]));
    @flock($fh, LOCK_UN);
    @fclose($fh);

    if ($count > API_RATE_LIMIT) {
        header('Retry-After: ' . ($start + $win - $now));
        api_error(Lang::current() === 'en'
            ? 'Too many requests. Please slow down.'
            : 'طلبات كثيرة جداً. يرجى التمهّل قليلاً.', [], 429);
    }
}

/* ============================================================================
 *  Optional public API-key gate
 * ========================================================================== */
function api_key_guard(): void
{
    if (!API_REQUIRE_KEY) return;
    $given = (string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($given === '' || !hash_equals(API_PUBLIC_KEY, $given)) {
        api_error(Lang::current() === 'en' ? 'Invalid API key.' : 'مفتاح API غير صالح.', [], 401);
    }
}

/* ============================================================================
 *  JSON output — GZIP/Brotli, ETag/304, Last-Modified, Cache-Control
 * ========================================================================== */
function api_send(array $payload, int $ttl, int $status = 200): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) $json = '{"ok":false,"error":"encoding"}';

    $etag = '"' . md5($json) . '"';
    $lastMod = gmdate('D, d M Y H:i:s', time()) . ' GMT';

    header('Content-Type: application/json; charset=utf-8');
    if ($status === 200 && $ttl > 0) {
        header('Cache-Control: public, max-age=' . $ttl . ', s-maxage=' . ($ttl * 2) . ', stale-while-revalidate=30');
    } else {
        header('Cache-Control: no-store');
    }
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastMod);
    header('X-Api-Version: 1.0');

    // Conditional request → 304.
    $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($status === 200 && $inm !== '' && $inm === $etag) {
        http_response_code(304);
        exit;
    }

    http_response_code($status);

    // Compression: Brotli when available, else GZIP; honour Accept-Encoding.
    $accept = (string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
    $out = $json;
    if (function_exists('brotli_compress') && str_contains($accept, 'br')) {
        $c = @brotli_compress($json, 5);
        if ($c !== false) { header('Content-Encoding: br'); $out = $c; }
    } elseif (str_contains($accept, 'gzip') && !ini_get('zlib.output_compression')) {
        $c = @gzencode($json, 6);
        if ($c !== false) { header('Content-Encoding: gzip'); $out = $c; }
    }
    header('Content-Length: ' . strlen($out));
    echo $out;
    exit;
}

/** Success envelope. */
function api_out(array $data, int $ttl = CACHE_TTL_MATCHES, bool $stale = false, array $extra = []): void
{
    api_send(array_merge([
        'ok'    => true,
        'stale' => $stale,
        'lang'  => Lang::current(),
        'count' => array_is_list($data) ? count($data) : null,
        'ts'    => time(),
        'data'  => $data,
    ], $extra), $ttl);
}

/** Friendly error, optionally carrying a stale fallback snapshot. */
function api_error(string $message, $fallback = null, int $status = 502): void
{
    $hasFallback = $fallback !== null && $fallback !== [];
    api_send([
        'ok'    => $hasFallback,
        'stale' => true,
        'error' => $message,
        'lang'  => Lang::current(),
        'ts'    => time(),
        'data'  => $fallback ?? [],
    ], 0, $hasFallback ? 200 : $status);
}

/** Localized "briefly unavailable" text. */
function api_fail_text(): string
{
    return Lang::current() === 'en'
        ? 'Live data is briefly unavailable. Showing the latest saved results — please try again shortly.'
        : 'تعذّر جلب البيانات المباشرة مؤقتاً. نعرض آخر نتائج محفوظة — يرجى المحاولة بعد قليل.';
}

/**
 * Run $producer (returns array). Retries once on empty, then serves the last
 * good on-disk snapshot; refreshes the snapshot on success.
 */
function api_serve(callable $producer, string $snapshot, int $ttl, string $failMsg): void
{
    $dir  = CACHE_DIR;
    $file = $dir . '/snap_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $snapshot) . '.json';

    for ($attempt = 0; $attempt < 2; $attempt++) {
        try { $data = $producer(); }
        catch (\Throwable $e) { $data = []; }
        if (is_array($data) && $data !== []) {
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            api_out($data, $ttl, false);
        }
        if ($attempt === 0) usleep(300000);
    }
    if (is_file($file)) {
        $cached = json_decode((string)file_get_contents($file), true);
        if (is_array($cached) && $cached !== []) api_out($cached, 60, true);
    }
    api_error($failMsg, [], 502);
}

/** Only GET/POST reach endpoints. */
function api_require_method(array $methods = ['GET']): void
{
    $m = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($m, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        api_error('Method not allowed.', [], 405);
    }
}

/** Validated positive integer from a query param. */
function api_int(string $name, int $default = 0): int
{
    $v = $_GET[$name] ?? $_POST[$name] ?? $default;
    return (ctype_digit((string)$v)) ? (int)$v : $default;
}

/* Guards run for every real request (after preflight). */
api_rate_guard();
api_key_guard();
