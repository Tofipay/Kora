<?php
/**
 * Dynamic sitemaps for the Blogger front-end.
 *   /sitemap.php               → sitemap index
 *   /sitemap.php?type=main     → core sections + featured leagues
 *   /sitemap.php?type=news     → Google-News sitemap (recent articles)
 *   /sitemap.php?type=video    → video sitemap (highlights)
 *   /sitemap.php?type=image    → image sitemap (news covers)
 * URLs point at the public Blogger site (QAMHAD_BLOG_URL) using canonical
 * query-string deep links (?view=…&id=…) that the template routes to a page.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\VideoFeed;

api_require_method(['GET']);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=1800');

$base = blog_url();
$type = (string)($_GET['type'] ?? 'index');

function loc(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
/** Clean SEO path on the blog domain (matches the theme's pathname routing). */
function deep(string $base, string $path): string
{
    return $base . '/' . ltrim($path, '/');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

if ($type === 'index') {
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (['main', 'news', 'video', 'image'] as $t) {
        echo "  <sitemap><loc>" . loc(API_HOST_URL . '/sitemap.php?type=' . $t) . "</loc>"
           . "<lastmod>" . date('c') . "</lastmod></sitemap>\n";
    }
    echo '</sitemapindex>';
    exit;
}

if ($type === 'news') {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
       . 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
    $items = Api::newsPage(1)['items'] ?? [];
    foreach (array_slice($items, 0, 100) as $n) {
        $id = (int)($n['id'] ?? 0);
        if ($id <= 0) continue;
        $title = (string)($n['title'] ?? '');
        $date  = date('c', strtotime((string)($n['created_at'] ?? 'now')) ?: time());
        echo "  <url><loc>" . loc(deep($base, news_url($n))) . "</loc>\n"
           . "    <news:news><news:publication><news:name>" . loc(SITE_NAME_AR)
           . "</news:name><news:language>ar</news:language></news:publication>"
           . "<news:publication_date>{$date}</news:publication_date>"
           . "<news:title>" . loc($title) . "</news:title></news:news></url>\n";
    }
    echo '</urlset>';
    exit;
}

if ($type === 'video') {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
       . 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
    $vids = VideoFeed::videos('all', 0)['data'] ?? [];
    foreach (array_slice($vids, 0, 80) as $v) {
        $yt = (string)($v['youtube_id'] ?? '');
        if ($yt === '') continue;
        echo "  <url><loc>" . loc(deep($base, '/video/' . $yt)) . "</loc>\n"
           . "    <video:video><video:thumbnail_loc>" . loc((string)($v['thumbnail'] ?? '')) . "</video:thumbnail_loc>"
           . "<video:title>" . loc((string)($v['title'] ?? '')) . "</video:title>"
           . "<video:description>" . loc(mb_substr((string)($v['title'] ?? ''), 0, 200)) . "</video:description>"
           . "<video:player_loc>" . loc('https://www.youtube.com/embed/' . $yt) . "</video:player_loc>"
           . "</video:video></url>\n";
    }
    echo '</urlset>';
    exit;
}

if ($type === 'image') {
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
       . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach (array_slice(Api::newsPage(1)['items'] ?? [], 0, 100) as $n) {
        $id  = (int)($n['id'] ?? 0);
        $img = api_media('news', '640', $n['image'] ?? null);
        if ($id <= 0 || $img === '') continue;
        echo "  <url><loc>" . loc(deep($base, news_url($n))) . "</loc>\n"
           . "    <image:image><image:loc>" . loc($img) . "</image:loc>"
           . "<image:caption>" . loc((string)($n['title'] ?? '')) . "</image:caption></image:image></url>\n";
    }
    echo '</urlset>';
    exit;
}

/* type=main (default) */
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$urls = [$base . '/'];
foreach (['live', 'matches', 'leagues', 'standings', 'top-scorers', 'news', 'videos', 'about', 'contact', 'privacy'] as $v) {
    $urls[] = deep($base, $v);
}
foreach (FAVORITE_LEAGUES as $l) $urls[] = $base . league_url(['url_id' => $l['url_id'], 'title' => $l['ar']]);
foreach ($urls as $u) {
    echo "  <url><loc>" . loc($u) . "</loc><changefreq>hourly</changefreq><priority>0.8</priority></url>\n";
}
echo '</urlset>';
