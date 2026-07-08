<?php
/** GET /api/team.php?id=N[&lang=ar|en] — team fixtures/results + squad. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) api_error('Missing team id', [], 400);

api_serve(function () use ($id) {
    $buckets = Api::teamMatchesBuckets($id);
    $squad   = Api::teamSquad($id);
    return [
        'team'     => $buckets['team'] ?? null,
        'league'   => $buckets['league'] ?? null,
        'fixtures' => $buckets['fixtures'] ?? [],
        'results'  => $buckets['results'] ?? [],
        'squad'    => $squad,
    ];
}, 'team_' . Lang::current() . '_' . $id, CACHE_TTL_MATCHES, api_fail_text());
