<?php
/** GET /api/leagues.php[?lang=ar|en] — every competition seen in the fixtures
 *  window (favourites pinned first), used by the app's Leagues / Notifications. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_serve(
    fn() => array_values(Api::allLeagues()),
    'leagues_' . Lang::current(),
    CACHE_TTL_LEAGUES,
    api_fail_text()
);
