<?php
/**
 * Application bootstrap: config, autoload, language detection, routing.
 */
declare(strict_types=1);

require __DIR__ . '/config.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'TofiXTv\\')) {
        $rel = str_replace(['TofiXTv\\', '\\'], ['', '/'], $class);
        $file = APP_DIR . '/' . $rel . '.php';
        if (is_file($file)) require $file;
    }
});

use TofiXTv\Core\Lang;
use TofiXTv\Core\License;
use TofiXTv\Core\Router;
use TofiXTv\Core\View;

/* ---------- Resolve request path ---------- */
$uri  = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
$path = '/' . ltrim($path, '/');

/* ---------- Never serve under /public: 301 to the clean path ----------
 * A /public/... URL means the internal folder prefix leaked at some point
 * (old link, misconfigured router). Answer with one permanent redirect to
 * the clean canonical URL so browsers and Google drop the ugly variant. */
if ($path === '/public' || str_starts_with($path, '/public/')) {
    $clean = substr($path, strlen('/public')) ?: '/';
    $__qs = (string)parse_url($uri, PHP_URL_QUERY);
    header('Location: ' . SITE_URL . implode('/', array_map('rawurlencode', explode('/', $clean))) . ($__qs !== '' ? '?' . $__qs : ''), true, 301);
    header('Cache-Control: public, max-age=86400');
    exit;
}

/* ---------- Canonical host: 301 legacy hosts → aloka-code.shop ----------
 * aloka-code.shop / aloka-code.shop keep serving, but every request is answered
 * with a single permanent redirect to the same path on the primary domain,
 * so all existing rankings and backlinks transfer to www. The web server
 * does this too (public/.htaccess, deploy/nginx.conf) — this PHP guard makes
 * the migration safe on any server configuration. */
$__reqHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($__reqHost !== '' && $__reqHost !== PRIMARY_HOST && in_array($__reqHost, LEGACY_HOSTS, true)) {
    $__qs = (string)parse_url($uri, PHP_URL_QUERY);
    header('Location: ' . SITE_URL . implode('/', array_map('rawurlencode', explode('/', $path))) . ($__qs !== '' ? '?' . $__qs : ''), true, 301);
    header('Cache-Control: public, max-age=86400');
    exit;
}

/* ---------- Repair corrupted URLs (single permanent redirect) ----------
 * 1) Mojibake: UTF-8 bytes that were re-read as Latin-1 and re-encoded
 *    (e.g. %C3%99%C2%83… instead of %D9%83…). Decoding yields Ã/Ù/Ø-style
 *    garbage; converting those code points back to raw bytes restores the
 *    original UTF-8. Repaired paths contain real Arabic, so this can
 *    never trigger twice → no redirect loops.
 * 2) Double percent-encoding (%2520…): a second decode pass.
 */
$__qs = (string)parse_url($uri, PHP_URL_QUERY);
if (str_contains($path, '%')) {
    $again = rawurldecode($path);
    if ($again !== $path && mb_check_encoding($again, 'UTF-8')) {
        header('Location: ' . SITE_URL . implode('/', array_map('rawurlencode', explode('/', $again))) . ($__qs ? '?' . $__qs : ''), true, 301);
        exit;
    }
}
if (!preg_match('/\p{Arabic}/u', $path) && preg_match('/[\x{00C0}-\x{00FF}\x{0152}-\x{2122}]/u', $path)) {
    $bytes = @mb_convert_encoding($path, 'ISO-8859-1', 'UTF-8'); // code points → raw bytes
    if (is_string($bytes) && $bytes !== $path
        && mb_check_encoding($bytes, 'UTF-8')
        && preg_match('/\p{Arabic}/u', $bytes)) {
        header('Location: ' . SITE_URL . implode('/', array_map('rawurlencode', explode('/', $bytes))) . ($__qs ? '?' . $__qs : ''), true, 301);
        exit;
    }
}

/* ---------- Legacy .php URLs → permanent redirects (SEO) ---------- */
require APP_DIR . '/legacy.php';
tofixtv_legacy_redirect($path);

/* ---------- Language: /en/* => English, everything else Arabic ---------- */
$lang = 'ar';
if ($path === '/en' || str_starts_with($path, '/en/')) {
    $lang = 'en';
    $path = substr($path, 3) ?: '/';
}

/* ---------- Persistent language preference (cookie q_lang) ----------
 * The frontend stores the user's EXPLICIT language choice in the q_lang
 * cookie (1 year). Landing on the HOMEPAGE in the other language is answered
 * with one temporary redirect to the preferred version, so the site always
 * reopens in the saved language (refreshes, browser restarts, PWA installs,
 * app WebViews). Scope is deliberately the homepage only and the redirect is
 * a private 302: deep links keep working, crawlers carry no cookies and are
 * never redirected, and nothing about the URL/SEO structure changes. */
$__pref = (string)($_COOKIE['q_lang'] ?? '');
if ($path === '/' && in_array($__pref, ['ar', 'en'], true) && $__pref !== $lang) {
    header('Cache-Control: private, no-store');
    header('Vary: Cookie');
    header('Location: ' . SITE_URL . ($__pref === 'en' ? '/en' : '/'), true, 302);
    exit;
}

Lang::boot($lang);

require APP_DIR . '/helpers.php';

/* ---------- Trailing slash normalization (no duplicate URLs) ---------- */
if ($path !== '/' && str_ends_with($path, '/')) {
    $qs = (string)parse_url($uri, PHP_URL_QUERY);
    View::redirect(Lang::prefix() . rtrim($path, '/') . ($qs ? '?' . $qs : ''), 301);
}

/* ---------- License gate ----------
 * Blocks the whole public site (and JSON APIs) unless the license is active.
 * The admin panel manages its own activation UI (see Admin::dispatch), so it is
 * excluded here. Uses a locally-cached decision — no external request per visit. */
$__isAdmin = ($path === '/' . ADMIN_PATH) || str_starts_with($path, '/' . ADMIN_PATH . '/');
if (!$__isAdmin) {
    License::gate(str_starts_with($path, '/api/') ? 'api' : 'web');
}

/* ---------- Routes ---------- */
$router = new Router();
require APP_DIR . '/routes.php';
$router->dispatch($path, $_SERVER['REQUEST_METHOD'] ?? 'GET');
