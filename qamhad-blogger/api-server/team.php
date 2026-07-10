<?php
/** GET /team.php?id=N[&lang=ar|en] — team fixtures/results + squad + news. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing team id', [], 400);

api_serve(function () use ($id) {
    $buckets = Api::teamMatchesBuckets($id);
    return [
        'team'     => $buckets['team'] ?? null,
        'league'   => $buckets['league'] ?? null,
        'fixtures' => $buckets['fixtures'] ?? [],
        'results'  => $buckets['results'] ?? [],
        'squad'    => Api::teamSquad($id),
        'news'     => array_slice(Api::teamNews($id), 0, 10),
    ];
}, 'team_' . Lang::current() . '_' . $id, CACHE_TTL_MATCHES, api_fail_text());
