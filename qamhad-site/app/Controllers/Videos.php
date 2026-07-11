<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\VideoFeed;
use Qamhad\Core\View;

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

        $categories = VideoFeed::categories();
        $result     = VideoFeed::page($champ, $page, VideoFeed::PER_PAGE);

        // Past the end of the feed → real 404 (same behaviour as News).
        if ($page > 1 && empty($result['items'])) View::notFound();

        $activeTitle = 'الكل';
        foreach ($categories as $c) {
            if ((string)$c['id'] === $champ) { $activeTitle = (string)$c['title']; break; }
        }
        Settings::trackHit('videos', $activeTitle);

        header('Cache-Control: public, max-age=600');

        $qs = $champ !== 'all' ? '?champ=' . rawurlencode($champ) : '';
        $pagePath = fn(int $n): string => path($n <= 1 ? 'videos' : 'videos/page/' . $n) . $qs;

        $seo = (new Seo())
            ->title(t('videos.title') . ($page > 1 ? ' — ' . t('news.page', ['n' => $page]) : '') . ' — ' . \Qamhad\Core\Lang::siteName())
            ->description(t('videos.subtitle'))
            ->canonical(path($page > 1 ? 'videos/page/' . $page : 'videos'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
            ]);

        View::page('videos', [
            'categories'  => $categories,
            'champ'       => $champ,
            'activeTitle' => $activeTitle,
            'items'       => $result['items'],
            'page'        => $page,
            'hasNext'     => $result['has_next'],
            'hasPrev'     => $result['has_prev'],
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
            ->title($video['title'] . ' — ' . \Qamhad\Core\Lang::siteName())
            ->description($video['champ_title'] !== '' ? ($video['title'] . ' · ' . $video['champ_title']) : $video['title'])
            ->type('video.other')
            ->canonical(path('video/' . $id))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
                [$video['title'], path('video/' . $id)],
            ]);
        if ($video['thumbnail'] !== '') $seo->image($video['thumbnail']);

        // VideoObject structured data — the player_loc is the site's own page.
        $vs = [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $video['title'],
            'description'  => $video['champ_title'] !== '' ? ($video['title'] . ' · ' . $video['champ_title']) : $video['title'],
            'embedUrl'     => SITE_URL . path('video/' . $id),
        ];
        if ($video['thumbnail'] !== '') $vs['thumbnailUrl'] = $video['thumbnail'];
        if (!empty($video['youtube_id'])) $vs['contentUrl'] = 'https://www.youtube.com/watch?v=' . $video['youtube_id'];
        elseif (!empty($video['media_url'])) $vs['contentUrl'] = $video['media_url'];
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
            ->title($title . ' — ' . \Qamhad\Core\Lang::siteName())
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
