<?php
/**
 * GET /formations.php?id=MATCH_ID[&lang=ar|en] — formation strings + positioned
 * players per side, derived from the lineup payload.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing match id', [], 400);

api_serve(function () use ($id) {
    $data  = Api::matchLineup($id);
    $sides = is_array($data['lineup'] ?? null) ? $data['lineup'] : [];
    $out   = [];
    foreach ($sides as $tid => $side) {
        if (!is_array($side)) continue;
        $players = [];
        foreach (($side['lineup'] ?? []) as $lp) {
            if (!is_array($lp)) continue;
            $players[] = [
                'player'   => is_array($lp['player'] ?? null) ? $lp['player'] : [],
                'number'   => $lp['shirt_number'] ?? $lp['number'] ?? null,
                'position' => $lp['position'] ?? $lp['player_position'] ?? null,
                'x'        => $lp['x'] ?? null,
                'y'        => $lp['y'] ?? null,
            ];
        }
        $out[] = [
            'team_id'   => (int)$tid,
            'formation' => (string)($side['formation'] ?? $side['plan'] ?? ''),
            'coach'     => $side['coach'] ?? null,
            'players'   => $players,
        ];
    }
    return $out;
}, 'formations_' . Lang::current() . '_' . $id, CACHE_TTL_MATCHES, api_fail_text());
