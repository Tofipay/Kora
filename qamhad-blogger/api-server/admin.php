<?php
/**
 * ============================================================================
 *  admin.php — the original Qamhad Live admin panel, on the API host.
 * ============================================================================
 *  Reachable at:
 *      https://api.qamhad.com/admin            (dashboard, after login)
 *      https://api.qamhad.com/admin/channels   (manage TV/stream channels)
 *      https://api.qamhad.com/admin/branding | /homepage | /notifications | /cache
 *
 *  First login password (change it in Settings after logging in):
 *      qamhad-admin-2026      (constant ADMIN_DEFAULT_PASSWORD in engine/config.php)
 *
 *  The panel writes to storage/settings/*.json (channels.json, branding.json …)
 *  which every public endpoint and render.php read — so adding a channel here
 *  makes it play on the site immediately.
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Qamhad\\')) return;
    $rel  = str_replace(['Qamhad\\', '\\'], ['', '/'], $class);
    $file = __DIR__ . '/engine/' . $rel . '.php';
    if (is_file($file)) require $file;
});

use Qamhad\Core\Lang;
use Qamhad\Controllers\Admin;

Lang::boot((($_GET['lang'] ?? 'ar') === 'en') ? 'en' : 'ar');
require_once __DIR__ . '/engine/helpers.php';

/* Derive the admin sub-action from the request, however it was reached:
 *   /admin              → ''         (dashboard)
 *   /admin/channels     → 'channels'
 *   /admin.php?action=… → explicit
 */
$action = (string)($_GET['action'] ?? '');
if ($action === '') {
    $uriPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $uriPath = rawurldecode($uriPath);
    if (preg_match('#/admin(?:\.php)?/?(.*)$#', $uriPath, $m)) {
        $action = trim($m[1], '/');
    }
}

Admin::dispatch($action);
