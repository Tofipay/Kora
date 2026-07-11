<?php
/**
 * ============================================================================
 *  render.php — server-rendered page FRAGMENTS for the Blogger theme.
 * ============================================================================
 *  The Blogger shell fetches:  /render.php?path=/match/real-madrid-123&lang=ar
 *  and receives the EXACT original Qamhad Live markup for that page's <main>
 *  content (same views, same classes, same app.css), plus its SEO metadata:
 *
 *    { ok, status, path, html, title, description, canonical, jsonld[] }
 *
 *  This is a true 1:1 conversion: the design, cards, tables, timelines,
 *  formations and colours are the project's own — not a re-implementation.
 *  Internal links stay as clean paths (/match/…, /league/…) so the shell can
 *  intercept them for instant navigation; media/asset URLs are rewritten to
 *  absolute API URLs so images load from api.qamhad.com.
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

if (!defined('QAMHAD_RENDER')) define('QAMHAD_RENDER', true);
require_once __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Qamhad\\')) return;
    $rel  = str_replace(['Qamhad\\', '\\'], ['', '/'], $class);
    $file = __DIR__ . '/engine/' . $rel . '.php';
    if (is_file($file)) require $file;
});

use Qamhad\Core\Lang;
use Qamhad\Core\Router;

/* ---------- CORS (allow-list; reflects the blog origin) ---------- */
function render_cors(): string
{
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return '*';
    $host = strtolower((string)parse_url($origin, PHP_URL_HOST));
    $allowed = getenv('QAMHAD_ALLOWED_ORIGINS')
        ? array_map('trim', explode(',', (string)getenv('QAMHAD_ALLOWED_ORIGINS')))
        : API_ALLOWED_ORIGINS;
    foreach ($allowed as $rule) {
        if ($rule === '*') return $origin;
        if (str_starts_with($rule, '*.') && str_ends_with($host, substr($rule, 1))) return $origin;
        $rh = strtolower((string)parse_url($rule, PHP_URL_HOST)) ?: strtolower($rule);
        if ($host === $rh) return $origin;
    }
    return API_HOST_URL;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . render_cors());
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    http_response_code(204);
    exit;
}

/* ---------- Resolve + validate the requested app path ---------- */
$lang = (($_GET['lang'] ?? 'ar') === 'en') ? 'en' : 'ar';
$raw  = (string)($_GET['path'] ?? '/');
$path = '/' . ltrim((string)(parse_url($raw, PHP_URL_PATH) ?: '/'), '/');
$path = rawurldecode($path);
// Strip a leading /en (language handled separately) and normalise.
if ($path === '/en' || str_starts_with($path, '/en/')) { $lang = 'en'; $path = substr($path, 3) ?: '/'; }

/* Only page routes may be rendered as fragments — never media/api/stream/admin. */
$ALLOW = '#^/($|matches$|matches/\d{4}-\d\d-\d\d$|today$|tomorrow$|yesterday$|live$'
       . '|match/|league$|leagues$|league/|team$|teams$|team/|player$|players$|player/'
       . '|news$|news/|videos$|videos/|video/|standings$|top-scorers$|scorers$'
       . '|search$|favorites$|about$|privacy$|terms$|contact$)#u';
if (!preg_match($ALLOW, $path)) {
    header('Access-Control-Allow-Origin: ' . render_cors());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_renderable', 'path' => $path], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Make the app behave exactly as if this path was requested directly. */
$_SERVER['REQUEST_URI']    = ($lang === 'en' ? '/en' : '') . $path;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['q'] = $_GET['q'] ?? ($_GET['query'] ?? '');   // search page reads ?q

Lang::boot($lang);
require_once __DIR__ . '/engine/helpers.php';

/* ---------- Capture the rendered page, survive exit()/redirects ---------- */
$GLOBALS['__render_done'] = false;
register_shutdown_function('render_finish');
ob_start();

try {
    $router = new Router();
    require __DIR__ . '/engine/routes.php';
    $router->dispatch($path, 'GET');
} catch (\Throwable $e) {
    // fall through to finish() which will emit whatever buffered + a 500 note
    if (ob_get_level() > 0) { /* keep buffer */ }
}
render_finish();

/**
 * Runs once (normal return OR after a controller exit()/redirect). Extracts the
 * <main> fragment + SEO from the buffered document and emits JSON.
 */
function render_finish(): void
{
    if (!empty($GLOBALS['__render_done'])) return;
    $GLOBALS['__render_done'] = true;

    $doc    = ob_get_level() > 0 ? (string)ob_get_clean() : '';
    $status = http_response_code() ?: 200;

    // A controller may 301 to the canonical slug — tell the client to refetch.
    $location = '';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Location:') === 0) $location = trim(substr($h, 9));
    }
    header_remove();
    header('Access-Control-Allow-Origin: ' . render_cors());
    header('Vary: Origin');
    header('Content-Type: application/json; charset=utf-8');

    if ($location !== '') {
        $p = (string)(parse_url($location, PHP_URL_PATH) ?: '/');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'redirect' => $p], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    [$main, $meta] = render_extract($doc);
    http_response_code(in_array($status, [301, 302], true) ? 200 : $status);
    header('Cache-Control: public, max-age=' . ($status === 200 ? 45 : 0));
    echo json_encode([
        'ok'          => $status < 400,
        'status'      => $status,
        'path'        => $_SERVER['REQUEST_URI'] ?? '/',
        'html'        => $main,
        'title'       => $meta['title'],
        'description' => $meta['description'],
        'jsonld'      => $meta['jsonld'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Pull the <main> inner HTML + SEO out of a full rendered document. */
function render_extract(string $doc): array
{
    $meta = ['title' => '', 'description' => '', 'jsonld' => []];

    if (preg_match('#<title>(.*?)</title>#si', $doc, $m)) {
        $meta['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('#<meta\s+name="description"\s+content="([^"]*)"#i', $doc, $m)) {
        $meta['description'] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $doc, $mm)) {
        foreach ($mm[1] as $blk) {
            $blk = trim($blk);
            if ($blk !== '') $meta['jsonld'][] = $blk;
        }
    }

    $main = '';
    if (preg_match('#<main\b[^>]*>(.*)</main>#si', $doc, $m)) {
        $main = $m[1];
    }
    $main = render_rewrite_assets($main);
    return [$main, $meta];
}

/**
 * Rewrite root-relative MEDIA/ASSET urls to absolute API urls so images/logos
 * load from api.qamhad.com. Internal PAGE links (/match/…, /league/…) are left
 * relative on purpose — the shell intercepts them for instant navigation.
 */
function render_rewrite_assets(string $html): string
{
    $api = API_HOST_URL;
    // src|href|poster|data-src="/media/..." | "/assets/..." | "/favicon..."
    $html = preg_replace_callback(
        '#(\b(?:src|href|poster|data-src|data-poster)=")(/(?:media|assets|favicon)[^"]*)(")#i',
        static fn($m) => $m[1] . $api . $m[2] . $m[3],
        $html
    ) ?? $html;
    // srcset (comma-separated) — prefix each root-relative candidate
    $html = preg_replace_callback(
        '#(\bsrcset=")([^"]*)(")#i',
        static function ($m) use ($api) {
            $set = preg_replace('#(^|,\s*)(/(?:media|assets)[^\s,]*)#', '$1' . $api . '$2', $m[2]);
            return $m[1] . $set . $m[3];
        },
        $html
    ) ?? $html;
    return $html;
}
