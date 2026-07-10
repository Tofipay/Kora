<?php
/**
 * GET /league.php?id=URL_ID[&lang=ar|en] — league hub:
 * standings + scorers + assists + recent news.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id', api_int('league'));

// No id → the discovered active-leagues list (Leagues page).
if ($id <= 0) {
    api_serve(
        static fn() => Api::allLeagues(),
        'leagues_list_' . Lang::current(),
        CACHE_TTL_LEAGUES,
        api_fail_text()
    );
}

api_serve(function () use ($id) {
    $standing = Api::leagueStanding($id);
    $rows = is_array($standing['league'] ?? null)
        ? array_values(array_filter($standing['league'], static fn($r) => is_array($r) && isset($r['team_id'])))
        : [];
    return [
        'league'    => $standing['championship'] ?? $standing['league_info'] ?? null,
        'standings' => $rows,
        'scorers'   => array_slice(Api::leagueScorers($id), 0, 20),
        'assists'   => array_slice(Api::leagueAssists($id), 0, 20),
        'news'      => array_slice(Api::leagueNews($id), 0, 12),
    ];
}, 'league_' . Lang::current() . '_' . $id, CACHE_TTL_LEAGUES, api_fail_text());
