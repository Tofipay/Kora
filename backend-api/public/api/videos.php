<?php
/** GET /api/videos.php[?champ=all&page=1&q=&id=][&lang=ar|en]
 *  Structured highlight-video feed for the app (native list + player).
 *  - id: return a single video object
 *  - q:  server-side search
 *  - champ/page: paginated category feed
 *  Envelope data: { items:[…], has_next:bool, page:int }. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Lang;
use Qamhad\Core\VideoFeed;

$lang = Lang::current();

// Single video by id.
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $id = (int)$_GET['id'];
    api_serve(function () use ($id) {
        $v = VideoFeed::find($id);
        return $v ? ['items' => [$v], 'has_next' => false, 'page' => 1] : [];
    }, 'video_' . $lang . '_' . $id, CACHE_TTL_NEWS, api_fail_text());
}

$champ = isset($_GET['champ'])
    ? strtolower((string)preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['champ']))
    : 'all';
$champ = $champ !== '' && VideoFeed::isCategory($champ) ? $champ : 'all';
$page  = max(1, (int)($_GET['page'] ?? 1));
$q     = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);

$snapshot = $q !== ''
    ? 'videos_search_' . $lang . '_' . md5($q) . '_' . $page
    : 'videos_' . $lang . '_' . $champ . '_' . $page;

api_serve(function () use ($q, $champ, $page) {
    $res = $q !== ''
        ? VideoFeed::search($q, $page, VideoFeed::PER_PAGE)
        : VideoFeed::page($champ, $page, VideoFeed::PER_PAGE);
    return [
        'items'      => array_values($res['items'] ?? []),
        'has_next'   => (bool)($res['has_next'] ?? false),
        'page'       => (int)($res['page'] ?? $page),
        'categories' => array_values(VideoFeed::categories()),
    ];
}, $snapshot, CACHE_TTL_NEWS, api_fail_text());
