<?php
/** GET /api/match_info.php?id=N[&lang=ar|en] — full match object incl. events,
 *  used by the app's Match Center. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(
    fn() => Api::matchInfo($id),
    'match_info_' . Lang::current() . '_' . $id,
    CACHE_TTL_LIVE,
    api_fail_text()
);
