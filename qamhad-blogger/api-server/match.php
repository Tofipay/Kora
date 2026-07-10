<?php
/**
 * GET /match.php?id=N[&lang=ar|en] — full match center:
 * info + live state + events + lineups + formation + statistics + channels.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\ChannelLib;
use Qamhad\Core\Yacine;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(function () use ($id) {
    $info = Api::matchInfo($id);
    if (empty($info['match_id'])) return [];

    $events = is_array($info['events'] ?? null) && $info['events']
        ? $info['events'] : Api::matchEvents($id);
    $lineup = Api::matchLineup($id);
    $stats  = Api::matchStats($id);
    $state  = match_state($info);

    // Playable broadcast channels for this match, resolved through the proxy.
    $servers = Yacine::expandServers(ChannelLib::serversForMatch($id), true);
    $channels = [];
    foreach ($servers as $s) {
        $channels[] = [
            'name' => $s['name'],
            'url'  => str_starts_with((string)$s['url'], '/') ? API_HOST_URL . $s['url'] : $s['url'],
            'type' => $s['type'],
            'drm'  => $s['drm'] ?? null,
        ];
    }

    return [
        'info'     => $info,
        'state'    => $state,
        'periods'  => match_periods(is_array($info) ? $info : []),
        'events'   => is_array($events) ? array_values($events) : [],
        'lineup'   => $lineup,
        'stats'    => $stats,
        'channels' => $channels,
        'watchable'=> $channels !== [],
    ];
}, 'match_' . Lang::current() . '_' . $id, CACHE_TTL_LIVE, api_fail_text());
