<?php
/** GET /statistics.php?id=MATCH_ID[&lang=ar|en] — per-match statistics (possession, shots…). */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(
    static fn() => Api::matchStats($id),
    'stats_' . Lang::current() . '_' . $id,
    CACHE_TTL_LIVE,
    api_fail_text()
);
