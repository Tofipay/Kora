<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class News
{
    public static function index(int $page): void
    {
        Settings::trackHit('news');
        $page = max(1, $page);
        $data = Api::newsPage($page);

        if ($page > 1 && empty($data['items'])) View::notFound();

        $seo = (new Seo())
            ->title(t('news.title') . ($page > 1 ? ' — ' . t('news.page', ['n' => $page]) : ''))
            ->description(t('news.latest') . ' — ' . \Qamhad\Core\Lang::siteName())
            ->canonical(path($page > 1 ? "news/page/{$page}" : 'news'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.news'), path('news')],
            ]);

        View::page('news', [
            'items' => $data['items'],
            'page'  => $data['current_page'],
            'last'  => $data['last_page'],
            'total' => $data['total'],
        ], $seo);
    }

    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if (!$id) View::notFound();
        Settings::trackHit('article');

        $n = Api::newsDetail($id);
        // If the detail endpoint was blocked (anti-bot placeholder), fall back
        // to the real list metadata (headline + image + excerpt) so the reader
        // never sees the fake "download the app" article.
        $partial = false;
        if (empty($n) || empty($n['id'])) {
            $n = Api::findNewsItem($id);
            $partial = !empty($n);
        }
        if (empty($n) || empty($n['id'])) View::notFound();

        $canonical = news_url($n);
        if ('/news/' . $slug !== preg_replace('#^/en#', '', $canonical)) {
            View::redirect($canonical, 301);
        }

        // Related: recent items from the latest feed (excluding self)
        $related = array_values(array_filter(
            Api::newsPage(1)['items'],
            fn($r) => (int)($r['id'] ?? 0) !== $id
        ));

        // Freshness validators for crawlers (Discover recrawls fresh articles).
        $published = to_ts($n['created_at'] ?? null) ?: time();
        header('Cache-Control: public, max-age=600');
        http_cache_validate(min($published, time()), 'news-' . $id);

        $seo = (new Seo())
            ->title((string)($n['title'] ?? ''))
            ->description(excerpt((string)($n['news_desc'] ?? $n['title'] ?? ''), 250))
            // 1200px cover — Google Discover requires large images
            // (max-image-preview:large is set globally in the layout head).
            ->image(news_img($n, '1200'))
            ->type('article')
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.news'), path('news')],
                [(string)($n['title'] ?? ''), $canonical],
            ]);
        $seo->addJsonLd(Seo::articleSchema($n, SITE_URL . $canonical));

        View::page('article', [
            'n'       => $n,
            'partial' => $partial,
            'related' => array_slice($related, 0, 6),
        ], $seo);
    }
}
