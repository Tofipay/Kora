<?php
/**
 * Cache control + background refresh.
 *   GET /cache.php                      → cache statistics
 *   GET /cache.php?action=flush&key=…   → clear API cache      (needs admin key)
 *   GET /cache.php?action=flush-media&key=…  → clear media cache
 *   GET /cache.php?action=warm&key=…    → pre-warm today's feeds (cron friendly)
 * Admin key = QAMHAD_CACHE_KEY env (falls back to the public API key).
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Cache;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$action = (string)($_GET['action'] ?? 'stats');
$adminKey = getenv('QAMHAD_CACHE_KEY') ?: API_PUBLIC_KEY;

function cache_admin_guard(string $adminKey): void
{
    $given = (string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($given === '' || !hash_equals($adminKey, $given)) {
        api_error('Forbidden', [], 403);
    }
}

switch ($action) {
    case 'flush':
        cache_admin_guard($adminKey);
        api_out(['flushed' => Cache::flush()], 0);
    case 'flush-media':
        cache_admin_guard($adminKey);
        api_out(['flushed_media' => Cache::flushMedia()], 0);
    case 'warm':
        cache_admin_guard($adminKey);
        $warmed = [];
        foreach (['-1 day', 'now', '+1 day'] as $rel) {
            $d = date('Y-m-d', strtotime($rel));
            $warmed[$d] = count(Api::matchesByDate($d));
        }
        Api::allLeagues();
        Api::newsPage(1);
        api_out(['warmed' => $warmed], 0);
    case 'stats':
    default:
        api_out(['cache' => Cache::stats(), 'lang' => Lang::current()], 0);
}
