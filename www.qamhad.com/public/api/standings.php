<?php
/** GET /api/standings.php?league=URL_ID[&lang=ar|en] — league table + scorers. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

$league = (int)($_GET['league'] ?? $_GET['id'] ?? 0);
if ($league <= 0) api_error('Missing league id', [], 400);

api_serve(function () use ($league) {
    $standing = Api::leagueStanding($league);
    $rows = is_array($standing['league'] ?? null)
        ? array_values(array_filter($standing['league'], fn($r) => is_array($r) && isset($r['team_id'])))
        : [];
    return [
        'standings' => $rows,
        'scorers'   => array_slice(Api::leagueScorers($league), 0, 20),
    ];
}, 'standings_' . Lang::current() . '_' . $league, CACHE_TTL_LEAGUES, api_fail_text());
