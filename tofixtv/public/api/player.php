<?php
/** GET /api/player.php?id=N[&slug=name][&lang=ar|en] — full player profile
 *  (vitals + per-competition statistics + transfer history). */
require __DIR__ . '/_boot.php';

use TofiXTv\Core\Api;
use TofiXTv\Core\Lang;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) api_error('Missing player id', [], 400);

$slug = preg_replace('/[^\p{L}\p{N}\- ]/u', '', (string)($_GET['slug'] ?? '')) ?: '';

api_serve(
    fn() => Api::playerFull($id, $slug),
    'player_' . Lang::current() . '_' . $id,
    CACHE_TTL_LEAGUES,
    api_fail_text()
);
