<?php
/**
 * GET /channel.php?slug=SLUG | ?name=NAME | ?id=YACINE_ID [&fresh=1]
 * Resolves a TV channel to ready-to-play sources (HLS proxied through this
 * server, DASH with ClearKey where present). The upstream provider host is
 * never exposed to the browser.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\ChannelLib;
use Qamhad\Core\Yacine;

api_require_method(['GET']);

$slug  = fe_slug((string)($_GET['slug'] ?? ''));
$name  = trim((string)($_GET['name'] ?? ''));
$id    = api_int('id');
$fresh = ($_GET['fresh'] ?? '') === '1';

$urls  = [];
$label = '';

// 1) Direct Yacine numeric channel id.
if ($id > 0) {
    $label = 'Channel ' . $id;
    $sources = Yacine::resolveById($id, $fresh);
    return_channel($label, $sources);
}

// 2) Library entry by slug or name.
foreach (ChannelLib::all() as $c) {
    if (($slug !== '' && fe_slug($c['name']) === $slug)
        || ($name !== '' && mb_strtolower($c['name'], 'UTF-8') === mb_strtolower($name, 'UTF-8'))) {
        $label = $c['name'];
        $urls  = $c['urls'];
        break;
    }
}
if ($urls === []) api_error('Channel not found', [], 404);

// Expand every configured url (Yacine → real sources, plain → passthrough).
$servers = [];
foreach ($urls as $u) $servers[] = ['name' => $label, 'url' => $u, 'type' => 'auto'];
$sources = Yacine::expandServers($servers, false);

return_channel($label, $sources);

function return_channel(string $label, array $sources): void
{
    $out = [];
    foreach ($sources as $s) {
        $url = (string)($s['url'] ?? '');
        if ($url === '') continue;
        $out[] = [
            'name' => $s['name'] ?? $label,
            'url'  => str_starts_with($url, '/') ? API_HOST_URL . $url : $url,
            'type' => $s['type'] ?? 'm3u8',
            'drm'  => $s['drm'] ?? null,
        ];
    }
    if ($out === []) api_error('No playable source', [], 502);
    // Short cache — stream tokens rotate quickly.
    api_out(['channel' => $label, 'sources' => $out], 20);
}
