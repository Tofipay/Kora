<?php
/**
 * ============================================================================
 *  Front controller — pretty URLs for the API server.
 * ============================================================================
 *  With the bundled .htaccess (or the nginx config in the README) every request
 *  that isn't a real file lands here. It supports both styles so the template
 *  can call whichever the host allows:
 *      /matches.php?date=…        (direct file — also works without rewrites)
 *      /matches?date=…            (pretty)
 *      /media/teams/64/x.png      (pretty media)
 *      /stream?url=…&sig=…        (pretty stream)
 *  A bare "/" returns a small JSON index of the API.
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/router.php';

$uri  = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = trim(rawurldecode($uri), '/');

/* Pretty media: /media/{kind}/{size}/{file} → media.php?p=… */
if (preg_match('#^media/(.+)$#i', $path, $m)) {
    $_GET['p'] = $m[1];
    require __DIR__ . '/media.php';
    exit;
}

/* Static theme assets (app.css, logos, placeholders). On Apache/nginx the
 * web server serves these directly; this makes them work on any host too. */
if (preg_match('#^assets/([A-Za-z0-9._\-]+)$#', $path, $m)) {
    $file = __DIR__ . '/assets/' . $m[1];
    if (is_file($file)) {
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
                  'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                  'webp' => 'image/webp', 'ico' => 'image/x-icon', 'json' => 'application/json'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream') . '; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=604800');
        readfile($file);
        exit;
    }
    http_response_code(404);
    exit;
}

/* Admin panel: /admin, /admin/channels, … → the original admin controller. */
if ($path === 'admin' || $path === 'admin.php' || str_starts_with($path, 'admin/') || str_starts_with($path, 'admin.php/')) {
    require __DIR__ . '/admin.php';
    exit;
}

/* Front-controller route for the streaming proxy (/stream?url=…&sig=…). */
if ($path === 'stream') {
    require __DIR__ . '/stream.php';
    exit;
}

/* Root → API index. */
if ($path === '' || $path === 'index.php') {
    require_once __DIR__ . '/config.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=600');
    echo json_encode([
        'ok'      => true,
        'service' => 'Qamhad Live API',
        'version' => '1.0',
        'docs'    => API_HOST_URL . '/status.php',
        'endpoints' => array_values(array_unique(array_map(
            static fn($f) => basename($f, '.php'),
            require_endpoint_names()
        ))),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/* First path segment is the endpoint name (works for /matches or /matches.php). */
$name = explode('/', $path)[0];

/* Direct ".php" hits routed here (some hosts funnel everything to index.php):
 * serve the real endpoint file if it is one we ship, never an internal include. */
if (str_ends_with($name, '.php')) {
    $allow = array_values(api_routes());
    $allow[] = 'proxy.php';
    if (in_array($name, $allow, true) && is_file(__DIR__ . '/' . $name)) {
        require __DIR__ . '/' . $name;
        exit;
    }
}

api_dispatch($name);

/** List of unique endpoint filenames (for the root index). */
function require_endpoint_names(): array
{
    return array_values(api_routes());
}
