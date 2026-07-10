<?php
/**
 * GET /player.php?id=N[&slug=name][&lang=ar|en] — full player profile
 * (vitals + per-competition statistics + transfer history).
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing player id', [], 400);

$slug = preg_replace('/[^\p{L}\p{N}\- ]/u', '', (string)($_GET['slug'] ?? '')) ?: '';

api_serve(
    static fn() => Api::playerFull($id, $slug),
    'player_' . Lang::current() . '_' . $id,
    CACHE_TTL_LEAGUES,
    api_fail_text()
);
