<?php
/** GET /api/resolve.php?url=<channel/stream url> — resolve a channel URL into
 *  ready-to-play sources (Yacine API links are decrypted server-side and their
 *  HLS returned as an absolute first-party /stream proxy URL). */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Yacine;

$url = trim((string)($_GET['url'] ?? ''));
if ($url === '' || !preg_match('#^https?://#i', $url)) api_error('Missing url', [], 400);

$sources = [];
foreach (Yacine::expandServers([['name' => 'Server', 'url' => $url]]) as $s) {
    $u = (string)($s['url'] ?? '');
    if ($u === '') continue;
    if ($u[0] === '/') $u = rtrim(SITE_URL, '/') . $u;
    $sources[] = [
        'name' => (string)($s['name'] ?? 'Server'),
        'url'  => $u,
        'type' => (string)($s['type'] ?? 'auto'),
    ];
}
api_out(['sources' => $sources], 30);
