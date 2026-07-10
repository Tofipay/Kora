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
    /** GET /videos[?champ=ID] — highlights grid with championship tabs. */
    public static function index(): void
    {
        Settings::trackHit('videos');

        $champ = isset($_GET['champ']) ? preg_replace('/[^0-9]/', '', (string)$_GET['champ']) : '';
        $champ = $champ !== '' ? $champ : 'all';

        $categories = VideoFeed::categories();
        $first      = VideoFeed::videos($champ, 0);

        $activeTitle = 'الكل';
        foreach ($categories as $c) {
            if ((string)$c['id'] === $champ) { $activeTitle = (string)$c['title']; break; }
        }

        header('Cache-Control: public, max-age=600');

        $seo = (new Seo())
            ->title(t('videos.title') . ' — ' . \Qamhad\Core\Lang::siteName())
            ->description(t('videos.subtitle'))
            ->canonical(path('videos'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('videos.title'), path('videos')],
            ]);

        View::page('videos', [
            'categories'  => $categories,
            'champ'       => $champ,
            'activeTitle' => $activeTitle,
            'items'       => $first['data'],
            'hasMore'     => $first['has_more'],
            'nextSkip'    => $first['next_skip'],
        ], $seo);
    }

    /** GET /video/{ytId} — professional in-site YouTube player page. */
    public static function watch(string $ytId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $ytId)) View::notFound();
        Settings::trackHit('video');

        $lookup  = VideoFeed::findByYoutubeId($ytId);
        $video   = $lookup['video'];
        $related = $lookup['related'];

        // Title/championship: from the feed when known, else a sensible default
        // so a shared/deep-linked id still renders a valid page.
        $title = $video['title'] ?? t('videos.watch_default');
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
