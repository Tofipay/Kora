<?php
/** GET /standings.php?league=URL_ID[&lang=ar|en] — league table + top scorers. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$league = api_int('league', api_int('id'));
if ($league <= 0) api_error('Missing league id', [], 400);

api_serve(function () use ($league) {
    $standing = Api::leagueStanding($league);
    $rows = is_array($standing['league'] ?? null)
        ? array_values(array_filter($standing['league'], static fn($r) => is_array($r) && isset($r['team_id'])))
        : [];
    return [
        'league'    => $standing['championship'] ?? $standing['league_info'] ?? null,
        'standings' => $rows,
        'scorers'   => array_slice(Api::leagueScorers($league), 0, 20),
    ];
}, 'standings_' . Lang::current() . '_' . $league, CACHE_TTL_LEAGUES, api_fail_text());
