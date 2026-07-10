<?php
/**
 * First-party image proxy.
 *   /media.php?p=teams/64/abc.png        (query form — works everywhere)
 *   /media/teams/64/abc.png              (pretty form — via router/.htaccess)
 * Streams upstream CDN images under this API's own domain, disk-cached with a
 * long TTL, WebP on the fly, ETag/304. The real CDN host is never exposed.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Qamhad\\')) return;
    $rel  = str_replace(['Qamhad\\', '\\'], ['', '/'], $class);
    $file = __DIR__ . '/engine/' . $rel . '.php';
    if (is_file($file)) require $file;
});
require_once __DIR__ . '/engine/helpers.php';

header('Access-Control-Allow-Origin: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('X-Content-Type-Options: nosniff');

$path = (string)($_GET['p'] ?? $_GET['path'] ?? '');
\Qamhad\Core\MediaProxy::serve($path);
