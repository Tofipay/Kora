<?php
/** GET /live.php[?lang=ar|en] — matches currently in play (across today + neighbours). */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

// Live games can straddle midnight in either direction; scan yesterday→tomorrow.
$all = [];
foreach (['-1 day', 'now', '+1 day'] as $rel) {
    foreach (Api::matchesByDate(date('Y-m-d', strtotime($rel))) as $m) {
        if (is_array($m) && isset($m['match_id'])) $all[(int)$m['match_id']] = $m;
    }
}

if ($all === []) {
    $file = CACHE_DIR . '/snap_matches_' . Lang::current() . '_' . date('Y-m-d') . '.json';
    if (is_file($file)) $all = json_decode((string)file_get_contents($file), true) ?: [];
    if ($all === []) api_error(api_fail_text(), [], 502);
}

$live = array_values(array_filter($all, static fn($m) => is_array($m) && match_state($m)['key'] === 'live'));
api_out($live, CACHE_TTL_LIVE);
