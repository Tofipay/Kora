<?php
/** GET /status.php — deeper status: cache stats + a live upstream probe. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Cache;

$today = date('Y-m-d');
$sample = [];
try { $sample = Api::matchesByDate($today); } catch (\Throwable $e) { $sample = []; }

api_out([
    'service'   => 'qamhad-api',
    'status'    => 'ok',
    'php'       => PHP_VERSION,
    'time'      => date('c'),
    'upstream'  => [
        'reachable'     => $sample !== [],
        'today'         => $today,
        'match_count'   => count($sample),
    ],
    'cache'     => Cache::stats(),
    'rate_limit'=> ['limit' => API_RATE_LIMIT, 'window' => API_RATE_WINDOW],
    'endpoints' => [
        'matches', 'match', 'live', 'news', 'news-details', 'search', 'channels',
        'channel', 'player', 'team', 'league', 'standings', 'topscorers',
        'statistics', 'videos', 'comments', 'formations', 'lineups', 'events',
        'settings', 'media', 'stream', 'sitemap', 'robots', 'health', 'status',
    ],
], 0);
