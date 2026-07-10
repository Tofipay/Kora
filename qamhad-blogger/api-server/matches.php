<?php
/** GET /matches.php?date=YYYY-MM-DD[&lang=ar|en] — all matches for a day. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $date = date('Y-m-d');

$ttl = ($date === date('Y-m-d')) ? CACHE_TTL_LIVE : CACHE_TTL_MATCHES;

api_serve(
    static fn() => Api::matchesByDate($date),
    'matches_' . Lang::current() . '_' . $date,
    $ttl,
    api_fail_text()
);
