<?php
/**
 * GET /videos.php[?champ=all|ID][&skip=0][&lang=ar|en] — highlights feed
 * (infinite scroll), and category tabs on the first page.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\VideoFeed;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$champ = isset($_GET['champ']) ? preg_replace('/[^0-9]/', '', (string)$_GET['champ']) : '';
$champ = $champ !== '' ? $champ : 'all';
$skip  = max(0, api_int('skip', 0));

api_serve(function () use ($champ, $skip) {
    $res = VideoFeed::videos($champ, $skip);
    $out = [
        'champ'     => $res['champ'],
        'skip'      => $res['skip'],
        'count'     => $res['count'],
        'has_more'  => $res['has_more'],
        'next_skip' => $res['next_skip'],
        'videos'    => $res['data'],
    ];
    if ($skip === 0) $out['categories'] = VideoFeed::categories();
    return $out;
}, 'videos_' . Lang::current() . '_' . $champ . '_' . $skip, CACHE_TTL_NEWS, api_fail_text());
