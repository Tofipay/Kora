<?php
/** GET /lineups.php?id=MATCH_ID[&lang=ar|en] — starting XI + substitutes. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(
    static fn() => Api::matchLineup($id),
    'lineups_' . Lang::current() . '_' . $id,
    CACHE_TTL_MATCHES,
    api_fail_text()
);
