<?php
/** GET /api/live.php[?lang=ar|en] — matches currently in play. */
require __DIR__ . '/_boot.php';

use TofiXTv\Core\Api;
use TofiXTv\Core\Lang;

$today = date('Y-m-d');
$all   = Api::matchesByDate($today);

// Empty "no live matches right now" is a VALID answer — only fall back to the
// snapshot when the upstream fetch itself produced nothing at all.
if ($all === []) {
    $file = CACHE_DIR . '/matches_' . Lang::current() . '_' . $today . '.json';
    if (is_file($file)) {
        $all = json_decode((string)file_get_contents($file), true) ?: [];
    }
    if ($all === []) api_error(api_fail_text(), [], 502);
}

$live = array_values(array_filter($all, fn($m) => is_array($m) && match_state($m)['key'] === 'live'));
api_out($live, CACHE_TTL_LIVE);
