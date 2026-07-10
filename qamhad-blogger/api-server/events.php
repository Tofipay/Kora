<?php
/** GET /events.php?id=MATCH_ID[&lang=ar|en] — match event timeline. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(
    static fn() => array_values(Api::matchEvents($id)),
    'events_' . Lang::current() . '_' . $id,
    CACHE_TTL_LIVE,
    api_fail_text()
);
