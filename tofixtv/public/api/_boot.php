<?php
/**
 * ALOKA Live — JSON proxy bootstrap.
 *
 * Every /api/*.php endpoint runs through here. The browser talks ONLY to these
 * first-party endpoints; the external ysscores API (and its required app
 * headers) live entirely server-side. When the upstream URL, headers or app
 * version change, edit app/config.php only — nothing here or in the frontend.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/config.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'TofiXTv\\')) {
        $rel  = str_replace(['TofiXTv\\', '\\'], ['', '/'], $class);
        $file = APP_DIR . '/' . $rel . '.php';
        if (is_file($file)) require $file;
    }
});

use TofiXTv\Core\Lang;

$__lang = (($_GET['lang'] ?? 'ar') === 'en') ? 'en' : 'ar';
Lang::boot($__lang);
require APP_DIR . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Vary: Accept-Language');

/** Emit a success envelope and stop. */
function api_out(array $data, int $ttl = CACHE_TTL_MATCHES, bool $stale = false): void
{
    header('Cache-Control: public, max-age=' . max(0, $ttl));
    echo json_encode([
        'ok'      => true,
        'stale'   => $stale,
        'lang'    => Lang::current(),
        'count'   => is_array($data) ? count($data) : null,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Emit a friendly error (optionally with the last cached snapshot). */
function api_error(string $message, $fallback = null, int $status = 502): void
{
    http_response_code($fallback !== null && $fallback !== [] ? 200 : $status);
    header('Cache-Control: no-store');
    echo json_encode([
        'ok'      => $fallback !== null && $fallback !== [],
        'stale'   => true,
        'error'   => $message,
        'lang'    => Lang::current(),
        'data'    => $fallback ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Run $producer (returns array). Retries once on an empty result, then falls
 * back to the named on-disk snapshot; refreshes the snapshot on success.
 * $snapshot is a bare name like "matches_2026-07-05".
 */
function api_serve(callable $producer, string $snapshot, int $ttl, string $failMsg): void
{
    $file = CACHE_DIR . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $snapshot) . '.json';

    for ($attempt = 0; $attempt < 2; $attempt++) {
        try {
            $data = $producer();
        } catch (\Throwable $e) {
            $data = [];
        }
        if (is_array($data) && $data !== []) {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            api_out($data, $ttl, false);
        }
        if ($attempt === 0) usleep(300000); // 0.3s before the retry
    }

    // Upstream gave nothing twice — serve the last good snapshot if we have one.
    if (is_file($file)) {
        $cached = json_decode((string)file_get_contents($file), true);
        if (is_array($cached) && $cached !== []) api_out($cached, 60, true);
    }
    api_error($failMsg, [], 502);
}

/** Localized, user-friendly failure text. */
function api_fail_text(): string
{
    return Lang::current() === 'en'
        ? 'Live data is briefly unavailable. Showing the latest saved results — please try again shortly.'
        : 'تعذّر جلب البيانات المباشرة مؤقتاً. نعرض آخر نتائج محفوظة — يرجى المحاولة بعد قليل.';
}
