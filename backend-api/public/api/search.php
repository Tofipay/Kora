<?php
/** GET /api/search.php?q=QUERY[&lang=ar|en] — global search (players + teams). */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) api_out(['player' => [], 'teams' => []], 60);
if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);

api_serve(
    fn() => Api::search($q),
    'search_' . Lang::current() . '_' . md5($q),
    CACHE_TTL_NEWS,
    api_fail_text()
);
