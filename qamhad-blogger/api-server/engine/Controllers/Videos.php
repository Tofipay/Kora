<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\VideoFeed;
use Qamhad\Core\View;

/**
 * Videos section — YSScores highlights, split by championship.
 * Listing (with client-side infinite scroll / search) + an in-site
 * YouTube watch page. Reuses VideoFeed's on-disk cache; no new API.
 */
final class Videos
{
    /** Videos per page — News-style numbered pagination. */
    private const PER_PAGE = 5;

    /**
     * GET /videos[?champ=ID] and /videos/page/{n}[?champ=ID]
     * Highlights grid with championship tabs + prev/next + page numbers.
     */
    public static function index(int $page = 1): void
    {
        $page  = max(1, $page);
        $champ = isset($_GET['champ']) ? preg_replace('/[^0-9]/', '', (string)$_GET['champ']) : '';
        $champ = $champ !== '' ? $champ : 'all';

        $categories = VideoFeed::categories();
        $result     = VideoFeed::page($champ, $page, self::PER_PAGE);

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

    /** GET /video/{ytId} — professional in-site YouTube player page. */
    public static function watch(string $ytId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $ytId)) View::notFound();

        $lookup  = VideoFeed::findByYoutubeId($ytId);
        $video   = $lookup['video'];
        $related = $lookup['related'];

        // Title/championship: from the feed when known, else a sensible default
        // so a shared/deep-linked id still renders a valid page.
        $title = $video['title'] ?? t('videos.watch_default');
        Settings::trackHit('video', $title);
        $champ = $video['champ_title'] ?? '';
        $date  = $video['created_at'] ?? '';

        header('Cache-Control: public, max-age=1800');

        $seo = (new Seo())
            ->title($title . ' — ' . \Qamhad\Core\Lang::siteName())
            ->description($champ !== '' ? ($title . ' · ' . $champ) : $title)
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
            'champ'   => $champ,
            'date'    => $date,
            'related' => $related,
        ], $seo);
    }
}
