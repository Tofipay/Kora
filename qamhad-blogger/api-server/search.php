<?php
/** GET /search.php?q=QUERY[&lang=ar|en] — players + teams matching a query. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$q = trim((string)($_GET['q'] ?? $_GET['query'] ?? ''));
// Strip control chars; keep letters/numbers/space/dash across scripts.
$q = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $q) ?? '';
$q = mb_substr($q, 0, 60, 'UTF-8');

if (mb_strlen($q) < 2) {
    api_out(['query' => $q, 'players' => [], 'teams' => []], 0);
}

api_serve(function () use ($q) {
    $res = Api::search($q);
    return [
        'query'   => $q,
        'players' => $res['player'] ?? [],
        'teams'   => $res['teams'] ?? [],
    ];
}, 'search_' . Lang::current() . '_' . md5($q), CACHE_TTL_NEWS, api_fail_text());
