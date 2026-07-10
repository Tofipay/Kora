<?php
/** GET /topscorers.php?league=URL_ID[&type=goals|assists][&lang=ar|en]. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$league = api_int('league', api_int('id'));
if ($league <= 0) api_error('Missing league id', [], 400);
$type = (($_GET['type'] ?? 'goals') === 'assists') ? 'assists' : 'goals';

api_serve(function () use ($league, $type) {
    return $type === 'assists'
        ? array_slice(Api::leagueAssists($league), 0, 40)
        : array_slice(Api::leagueScorers($league), 0, 40);
}, 'topscorers_' . $type . '_' . Lang::current() . '_' . $league, CACHE_TTL_LEAGUES, api_fail_text());
