<?php
/** GET /channels.php[&lang=ar|en] — the TV-channel library (names + slugs). */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\ChannelLib;

api_require_method(['GET']);

$items = [];
foreach (ChannelLib::all() as $c) {
    $items[] = [
        'name'    => $c['name'],
        'slug'    => fe_slug($c['name']),
        'sources' => count($c['urls']),
        // A play URL the front-end can open directly; resolution happens there.
        'play'    => API_HOST_URL . '/channel.php?slug=' . rawurlencode(fe_slug($c['name'])),
    ];
}

api_out($items, CACHE_TTL_LEAGUES);
