<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\VideoFeed;
use TofiXTv\Core\View;

/**
 * Videos section — Btolat highlights (vendored btolat_php_api project),
 * split by championship/team. News-style pagination (5 per page) + an
 * in-site player page that renders the provider the source exposes
 * (YouTube embed / direct MP4 / X post). The legacy /video/{youtubeId}
 * player (used by the match-page videos tab) is kept intact.
 */
final class Videos
{
    /**
     * GET /videos[?champ=KEY] and /videos/page/{n}[?champ=KEY]
     * KEY is a Btolat category key from VideoFeed::categories() (e.g.
     * world-cup, saudi-league, al-ahly). Unknown keys fall back to "all".
     */
    public static function index(int $page = 1): void
    {
        $page  = max(1, $page);
        $champ = isset($_GET['champ'])
            ? strtolower((string)preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['champ']))
            : '';
        $champ = $champ !== '' && VideoFeed::isCategory($champ) ? $champ : 'all';

        // Server-side search (API-backed, like the News section): ?q=…
        $q = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);

        $categories = VideoFeed::categories();
        $result = $q !== ''
            ? VideoFeed::search($q, $page, VideoFeed::PER_PAGE)
            : VideoFeed::page($champ, $page, VideoFeed::PER_PAGE);

        // Past the end of the feed → real 404 (same behaviour as News).
        if ($page > 1 && empty($result['items'])) View::notFound();

        $activeTitle = 'الكل';
        foreach ($categories as $c) {
            if ((string)$c['id'] === $champ) { $activeTitle = (string)$c['title']; break; }
        }
        Settings::trackHit('videos', $q !== '' ? ('بحث: ' . $q) : $activeTitle);

        header('Cache-Control: public, max-age=' . ($q !== '' ? 120 : 600));

        // Pagination links preserve the champ filter AND the search query.
        $qsParts = [];
        if ($champ !== 'all') $qsParts['champ'] = $champ;
        if ($q !== '')        $qsParts['q'] = $q;
        $qs = $qsParts ? '?' . http_build_query($qsParts) : '';
        $pagePath = fn(int $n): string => path($n <= 1 ? 'videos' : 'videos/page/' . $n) . $qs;

        $titleBits = t('videos.title');
        if ($q !== '')            $titleBits .= ' — ' . $q;
        elseif ($champ !== 'all') $titleBits .= ' — ' . $activeTitle;
        if ($page > 1)            $titleBits .= ' — ' . t('news.page', ['n' => $page]);

        $seo = (new Seo())
            ->title($titleBits . ' — ' . \TofiXTv\Core\Lang::siteName())
            ->description($champ !== 'all'
                ? ($activeTitle . ' — ' . t('videos.subtitle'))
                : t('videos.subtitle'))
            ->canonical(path($page > 1 ? 'videos/page/' . $page : 'videos'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
            ]);

        // ItemList structured data for the listed videos (SEO).
        if (!empty($result['items'])) {
            $els = [];
            foreach ($result['items'] as $i => $v) {
                $vid = (int)($v['id'] ?? 0);
                if ($vid < 1) continue;
                $els[] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'url'      => SITE_URL . path('video/' . $vid),
                    'name'     => (string)$v['title'],
                ];
            }
            if ($els) {
                $seo->addJsonLd([
                    '@context'        => 'https://schema.org',
                    '@type'           => 'ItemList',
                    'itemListElement' => $els,
                ]);
            }
        }

        View::page('videos', [
            'categories'  => $categories,
            'champ'       => $champ,
            'activeTitle' => $activeTitle,
            'q'           => $q,
            'items'       => $result['items'],
            'page'        => $page,
            'hasNext'     => $result['has_next'],
            'hasPrev'     => $result['has_prev'],
            'total'       => $result['total'] ?? null,
            'pagePath'    => $pagePath,
        ], $seo);
    }

    /**
     * GET /video/{id} (numeric) — in-site Btolat player page.
     * Renders whatever the source exposes for this video: a YouTube embed,
     * a direct MP4 stream, or an X post link — always inside the site.
     */
    public static function play(int $id): void
    {
        if ($id < 1) View::notFound();

        $video = VideoFeed::find($id);
        if ($video === null) View::notFound();
        Settings::trackHit('video', $video['title']);

        $related = VideoFeed::related($id, 8);

        header('Cache-Control: public, max-age=1800');

        $seo = (new Seo())
            ->title($video['title'] . ' — ' . \TofiXTv\Core\Lang::siteName())
            ->description($video['champ_title'] !== '' ? ($video['title'] . ' · ' . $video['champ_title']) : $video['title'])
            ->type('video.other')
            ->canonical(path('video/' . $id))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
                [$video['title'], path('video/' . $id)],
            ]);
        if ($video['thumbnail'] !== '') $seo->image($video['thumbnail']);

        // VideoObject structured data — embedUrl/contentUrl must link to
        // the ACTUAL video (embeddable player / media file), never this
        // HTML page itself (same Google rule as the video sitemap's
        // player_loc/content_loc).
        $vs = [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $video['title'],
            'description'  => $video['champ_title'] !== '' ? ($video['title'] . ' · ' . $video['champ_title']) : $video['title'],
        ];
        if ($video['thumbnail'] !== '') $vs['thumbnailUrl'] = $video['thumbnail'];
        if (!empty($video['created_at']) && ($ts = to_ts($video['created_at']))) $vs['uploadDate'] = date('c', $ts);
        if (!empty($video['youtube_id'])) {
            $vs['embedUrl']   = 'https://www.youtube.com/embed/' . $video['youtube_id'];
            $vs['contentUrl'] = 'https://www.youtube.com/watch?v=' . $video['youtube_id'];
        } elseif (!empty($video['embed_iframe'])) {
            $vs['embedUrl'] = (string)$video['embed_iframe'];
        } elseif (!empty($video['tweet_id'])) {
            $vs['embedUrl'] = 'https://platform.twitter.com/embed/Tweet.html?id=' . rawurlencode((string)$video['tweet_id']);
        }
        if (!empty($video['media_url'])) $vs['contentUrl'] = $video['media_url'];
        $seo->addJsonLd($vs);

        View::page('video-play', [
            'v'       => $video,
            'related' => $related,
        ], $seo);
    }

    /**
     * GET /video/{ytId} (11-char YouTube id) — legacy in-site YouTube player.
     * Still used by the match-page videos tab (match API video_links).
     */
    public static function watch(string $ytId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $ytId)) View::notFound();

        $title = t('videos.watch_default');
        Settings::trackHit('video', $title);

        header('Cache-Control: public, max-age=1800');

        $seo = (new Seo())
            ->title($title . ' — ' . \TofiXTv\Core\Lang::siteName())
            ->description($title)
            ->image("https://i.ytimg.com/vi/{$ytId}/hqdefault.jpg")
            ->type('video.other')
            ->canonical(path('video/' . $ytId))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
                [$title, path('video/' . $ytId)],
            ]);

        View::page('video-watch', [
            'ytId'    => $ytId,
            'title'   => $title,
            'champ'   => '',
            'date'    => '',
            'related' => VideoFeed::related(0, 8),
        ], $seo);
    }
}
