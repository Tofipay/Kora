<?php
/** GET /api/matches.php?date=YYYY-MM-DD[&lang=ar|en] — all matches for a day. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$ttl = ($date === date('Y-m-d')) ? CACHE_TTL_LIVE : CACHE_TTL_MATCHES;

api_serve(
    fn() => Api::matchesByDate($date),
    'matches_' . Lang::current() . '_' . $date,
    $ttl,
    api_fail_text()
);
