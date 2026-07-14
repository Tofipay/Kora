<?php
/** GET /api/match_full.php?id=N[&lang=ar|en] — the full Match Center payload
 *  (info, events, lineups, stats, channels, standings, scorers) used by the
 *  app's match-detail screen. Mirrors the /api/match_full router endpoint. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Lang;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(
    function () use ($id) {
        $payload = build_match_full($id);
        return is_array($payload) ? $payload : [];
    },
    'match_full_' . Lang::current() . '_' . $id,
    CACHE_TTL_LIVE,
    api_fail_text()
);
