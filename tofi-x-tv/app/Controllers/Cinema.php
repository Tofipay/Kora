<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Lang;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\Tmdb;
use TofiXTv\Core\View;

/**
 * Cinema — الأفلام والمسلسلات.
 * Movies & series hubs, detail pages, genre browsing and search, all in the
 * same visual identity as the sports pages. Data: TMDB (disk-cached).
 */
final class Cinema
{
    /* ---------------- Movies hub ---------------- */

    public static function movies(): void
    {
        Settings::trackHit('movies');

        $trending   = Tmdb::trending('movie', 'week');
        $popular    = Tmdb::popularMovies();
        $topRated   = Tmdb::topRatedMovies();
        $nowPlaying = Tmdb::nowPlayingMovies();
        $upcoming   = Tmdb::upcomingMovies();
        $genres     = Tmdb::movieGenres()['genres'] ?? [];

        $hero = array_slice($trending['results'] ?? ($popular['results'] ?? []), 0, 5);

        $seo = (new Seo())
            ->title(t('cinema.movies_title'))
            ->description(t('cinema.movies_desc'))
            ->canonical(path('movies'))
            ->breadcrumbs([[t('nav.home'), path('/')], [t('nav.movies'), path('movies')]]);
        $seo->addJsonLd($seo->websiteSchema());
        $seo->addJsonLd(Seo::cinemaListSchema(array_slice($popular['results'] ?? [], 0, 20), 'movie', t('nav.movies'), path('movies')));
        if (!empty($hero[0]['backdrop_path'])) $seo->image(tmdb_backdrop($hero[0]['backdrop_path']));

        View::page('movies', [
            'hero'       => $hero,
            'popular'    => array_slice($popular['results'] ?? [], 0, 18),
            'topRated'   => array_slice($topRated['results'] ?? [], 0, 18),
            'nowPlaying' => array_slice($nowPlaying['results'] ?? [], 0, 18),
            'upcoming'   => array_slice($upcoming['results'] ?? [], 0, 18),
            'genres'     => $genres,
        ], $seo);
    }

    /* ---------------- Series hub ---------------- */

    public static function series(): void
    {
        Settings::trackHit('series');

        $trending    = Tmdb::trending('tv', 'week');
        $popular     = Tmdb::popularTv();
        $topRated    = Tmdb::topRatedTv();
        $airingToday = Tmdb::airingTodayTv();
        $onTheAir    = Tmdb::onTheAirTv();
        $genres      = Tmdb::tvGenres()['genres'] ?? [];

        $hero = array_slice($trending['results'] ?? ($popular['results'] ?? []), 0, 5);

        $seo = (new Seo())
            ->title(t('cinema.series_title'))
            ->description(t('cinema.series_desc'))
            ->canonical(path('series'))
            ->breadcrumbs([[t('nav.home'), path('/')], [t('nav.series'), path('series')]]);
        $seo->addJsonLd(Seo::cinemaListSchema(array_slice($popular['results'] ?? [], 0, 20), 'tv', t('nav.series'), path('series')));
        if (!empty($hero[0]['backdrop_path'])) $seo->image(tmdb_backdrop($hero[0]['backdrop_path']));

        View::page('series', [
            'hero'        => $hero,
            'popular'     => array_slice($popular['results'] ?? [], 0, 18),
            'topRated'    => array_slice($topRated['results'] ?? [], 0, 18),
            'airingToday' => array_slice($airingToday['results'] ?? [], 0, 18),
            'onTheAir'    => array_slice($onTheAir['results'] ?? [], 0, 18),
            'genres'      => $genres,
        ], $seo);
    }

    /* ---------------- Movie detail ---------------- */

    public static function movie(string $slug): void
    {
        $id = id_from_slug($slug);
        if ($id < 1) { View::notFound(); return; }

        $movie = Tmdb::movie($id);
        if (empty($movie['id'])) { View::notFound(); return; }

        // Canonical slug redirect (bare id or wrong slug → one 301)
        $canonical = movie_url($movie);
        $requested = path('movie/' . $slug);
        if ($requested !== $canonical) View::redirect($canonical);

        $title = (string)($movie['title'] ?? '');
        $desc  = excerpt((string)($movie['overview'] ?? ''), 280)
            ?: t('cinema.movie_fallback_desc', ['title' => $title]);

        $seo = (new Seo())
            ->title(t('cinema.watch_movie', ['title' => $title]))
            ->description($desc)
            ->canonical($canonical)
            ->type('video.movie')
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.movies'), path('movies')],
                [$title, $canonical],
            ]);
        if (!empty($movie['backdrop_path'])) $seo->image(tmdb_backdrop($movie['backdrop_path']));
        $seo->addJsonLd(Seo::movieSchema($movie, absolute_url($canonical)));

        $videos  = array_values(array_filter($movie['videos']['results'] ?? [], fn($v) => ($v['site'] ?? '') === 'YouTube'));
        $trailer = array_values(array_filter($videos, fn($v) => ($v['type'] ?? '') === 'Trailer'))[0] ?? ($videos[0] ?? null);

        View::page('movie', [
            'movie'   => $movie,
            'cast'    => array_slice($movie['credits']['cast'] ?? [], 0, 12),
            'similar' => array_slice($movie['similar']['results'] ?? [], 0, 12),
            'recommended' => array_slice($movie['recommendations']['results'] ?? [], 0, 12),
            'trailer' => $trailer,
            'embed'   => [
                'vidsrc'   => Tmdb::embedUrl($id, 'movie'),
                'vidsrccc' => Tmdb::embedUrl($id, 'movie', null, null, 'vidsrccc'),
                'videasy'  => Tmdb::embedUrl($id, 'movie', null, null, 'videasy'),
            ],
        ], $seo);
    }

    /* ---------------- Series detail ---------------- */

    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if ($id < 1) { View::notFound(); return; }

        $tv = Tmdb::tv($id);
        if (empty($tv['id'])) { View::notFound(); return; }

        $canonical = series_url($tv);
        $requested = path('series/' . $slug);
        if ($requested !== $canonical) View::redirect($canonical);

        $title = (string)($tv['name'] ?? '');
        $desc  = excerpt((string)($tv['overview'] ?? ''), 280)
            ?: t('cinema.series_fallback_desc', ['title' => $title]);

        $seo = (new Seo())
            ->title(t('cinema.watch_series', ['title' => $title]))
            ->description($desc)
            ->canonical($canonical)
            ->type('video.tv_show')
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.series'), path('series')],
                [$title, $canonical],
            ]);
        if (!empty($tv['backdrop_path'])) $seo->image(tmdb_backdrop($tv['backdrop_path']));
        $seo->addJsonLd(Seo::tvSeriesSchema($tv, absolute_url($canonical)));

        // Season/episode picker state (?season=N&episode=M — crawl-safe defaults)
        $seasons = array_values(array_filter($tv['seasons'] ?? [], fn($s) => (int)($s['season_number'] ?? 0) > 0));
        $curSeason  = max(1, query_int('season', (int)($seasons[0]['season_number'] ?? 1)));
        $curEpisode = max(1, query_int('episode', 1));

        $videos  = array_values(array_filter($tv['videos']['results'] ?? [], fn($v) => ($v['site'] ?? '') === 'YouTube'));
        $trailer = array_values(array_filter($videos, fn($v) => ($v['type'] ?? '') === 'Trailer'))[0] ?? ($videos[0] ?? null);

        View::page('series-show', [
            'tv'      => $tv,
            'seasons' => $seasons,
            'curSeason'  => $curSeason,
            'curEpisode' => $curEpisode,
            'cast'    => array_slice($tv['credits']['cast'] ?? [], 0, 12),
            'similar' => array_slice($tv['similar']['results'] ?? [], 0, 12),
            'recommended' => array_slice($tv['recommendations']['results'] ?? [], 0, 12),
            'trailer' => $trailer,
            'embed'   => [
                'vidsrc'   => Tmdb::embedUrl($id, 'tv', $curSeason, $curEpisode),
                'vidsrccc' => Tmdb::embedUrl($id, 'tv', $curSeason, $curEpisode, 'vidsrccc'),
                'videasy'  => Tmdb::embedUrl($id, 'tv', $curSeason, $curEpisode, 'videasy'),
            ],
        ], $seo);
    }

    /* ---------------- Genre browsing ---------------- */

    public static function genre(string $type, string $slug, int $page = 1): void
    {
        $type = $type === 'series' ? 'tv' : 'movie';
        $id = id_from_slug($slug);
        if ($id < 1) { View::notFound(); return; }

        $page = max(1, min(500, $page));
        $data = $type === 'tv' ? Tmdb::tvByGenre($id, $page) : Tmdb::moviesByGenre($id, $page);
        $map  = Tmdb::genreMap($type);
        $name = $map[$id] ?? t('cinema.genre');

        $sectionPath = $type === 'tv' ? 'series' : 'movies';
        $base = path($sectionPath . '/genre/' . slugify($name, 'genre') . '-' . $id);

        $seo = (new Seo())
            ->title($name . ' — ' . t($type === 'tv' ? 'nav.series' : 'nav.movies') . ($page > 1 ? ' — ' . t('misc.page') . ' ' . $page : ''))
            ->description(t('cinema.genre_desc', ['genre' => $name]))
            ->canonical($base . ($page > 1 ? '/page/' . $page : ''))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t($type === 'tv' ? 'nav.series' : 'nav.movies'), path($sectionPath)],
                [$name, $base],
            ]);

        View::page('cinema-genre', [
            'type'    => $type,
            'name'    => $name,
            'items'   => $data['results'] ?? [],
            'page'    => $page,
            'pages'   => min(500, (int)($data['total_pages'] ?? 1)),
            'base'    => $base,
            'genres'  => ($type === 'tv' ? Tmdb::tvGenres() : Tmdb::movieGenres())['genres'] ?? [],
        ], $seo);
    }

    /* ---------------- Cinema search ---------------- */

    public static function search(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $page = max(1, query_int('page', 1));

        $results = [];
        $pages = 1;
        if (mb_strlen($q) >= 2) {
            $data = Tmdb::searchMulti($q, $page);
            $results = array_values(array_filter(
                $data['results'] ?? [],
                fn($r) => in_array($r['media_type'] ?? '', ['movie', 'tv'], true)
            ));
            $pages = min(500, (int)($data['total_pages'] ?? 1));
        }

        $seo = (new Seo())
            ->title($q !== '' ? t('cinema.search_for', ['q' => $q]) : t('cinema.search'))
            ->description(t('cinema.search_desc'))
            ->canonical(path('cinema/search'))
            ->breadcrumbs([[t('nav.home'), path('/')], [t('cinema.search'), path('cinema/search')]]);

        View::page('cinema-search', [
            'q'       => $q,
            'items'   => $results,
            'page'    => $page,
            'pages'   => $pages,
        ], $seo);
    }
}
