<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * TMDB client — powers the الأفلام / المسلسلات (Cinema) section.
 *
 * Same design rules as the sports Api core:
 *   - every response is cached on disk (TMDB_CACHE_DIR) with a TTL
 *   - a stale copy is served when the upstream is down (fail-soft)
 *   - nothing upstream-specific ever leaks into the HTML (posters go
 *     through tmdb_img() which builds image.tmdb.org URLs client-cacheable)
 */
final class Tmdb
{
    /** In-request memo so repeated calls (home page rows) hit the API once. */
    private static array $memo = [];

    /**
     * GET a TMDB v3 endpoint with disk caching + stale fallback.
     * @return array decoded JSON ([] on total failure)
     */
    public static function get(string $endpoint, array $params = [], int $ttl = CACHE_TTL_TMDB): array
    {
        $params['language'] = $params['language'] ?? (Lang::current() === 'en' ? 'en-US' : 'ar-SA');
        $url = TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);

        if (isset(self::$memo[$url])) return self::$memo[$url];

        $file = self::cacheFile($url);
        if (is_file($file) && (time() - (int)filemtime($file)) < $ttl) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) return self::$memo[$url] = $data;
        }

        $body = self::fetch($url);
        $data = is_string($body) ? json_decode($body, true) : null;

        if (is_array($data) && !isset($data['status_code'])) {
            if (!is_dir(TMDB_CACHE_DIR)) @mkdir(TMDB_CACHE_DIR, 0755, true);
            @file_put_contents($file, $body, LOCK_EX);
            return self::$memo[$url] = $data;
        }

        // Upstream failed — serve the stale cache rather than an empty page.
        if (is_file($file)) {
            $stale = json_decode((string)file_get_contents($file), true);
            if (is_array($stale)) return self::$memo[$url] = $stale;
        }
        return self::$memo[$url] = [];
    }

    private static function cacheFile(string $url): string
    {
        return TMDB_CACHE_DIR . '/' . sha1(CACHE_VERSION . '|' . $url) . '.json';
    }

    private static function fetch(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . TMDB_BEARER_TOKEN,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return (is_string($body) && $code >= 200 && $code < 300) ? $body : null;
    }

    /* ---------------- Lists ---------------- */

    public static function trending(string $mediaType = 'all', string $window = 'week', int $page = 1): array
    {
        return self::get("/trending/{$mediaType}/{$window}", ['page' => $page]);
    }

    public static function popularMovies(int $page = 1): array  { return self::get('/movie/popular',    ['page' => $page]); }
    public static function topRatedMovies(int $page = 1): array { return self::get('/movie/top_rated',  ['page' => $page]); }
    public static function upcomingMovies(int $page = 1): array { return self::get('/movie/upcoming',   ['page' => $page]); }
    public static function nowPlayingMovies(int $page = 1): array { return self::get('/movie/now_playing', ['page' => $page]); }

    public static function popularTv(int $page = 1): array     { return self::get('/tv/popular',      ['page' => $page]); }
    public static function topRatedTv(int $page = 1): array    { return self::get('/tv/top_rated',    ['page' => $page]); }
    public static function airingTodayTv(int $page = 1): array { return self::get('/tv/airing_today', ['page' => $page]); }
    public static function onTheAirTv(int $page = 1): array    { return self::get('/tv/on_the_air',   ['page' => $page]); }

    /* ---------------- Details ---------------- */

    public static function movie(int $id): array
    {
        return self::get("/movie/{$id}", ['append_to_response' => 'videos,credits,similar,recommendations']);
    }

    public static function tv(int $id): array
    {
        return self::get("/tv/{$id}", ['append_to_response' => 'videos,credits,similar,recommendations']);
    }

    public static function tvSeason(int $id, int $season): array
    {
        return self::get("/tv/{$id}/season/{$season}");
    }

    /* ---------------- Search / discover ---------------- */

    public static function searchMulti(string $q, int $page = 1): array
    {
        return self::get('/search/multi', ['query' => $q, 'page' => $page, 'include_adult' => 'false']);
    }

    public static function moviesByGenre(int $genreId, int $page = 1): array
    {
        return self::get('/discover/movie', ['with_genres' => $genreId, 'page' => $page, 'sort_by' => 'popularity.desc']);
    }

    public static function tvByGenre(int $genreId, int $page = 1): array
    {
        return self::get('/discover/tv', ['with_genres' => $genreId, 'page' => $page, 'sort_by' => 'popularity.desc']);
    }

    public static function movieGenres(): array { return self::get('/genre/movie/list', [], 7 * 24 * 3600); }
    public static function tvGenres(): array    { return self::get('/genre/tv/list',    [], 7 * 24 * 3600); }

    /** id => name map for one media type ('movie'|'tv'). */
    public static function genreMap(string $type): array
    {
        $list = $type === 'tv' ? self::tvGenres() : self::movieGenres();
        $map = [];
        foreach (($list['genres'] ?? []) as $g) {
            if (isset($g['id'], $g['name'])) $map[(int)$g['id']] = (string)$g['name'];
        }
        return $map;
    }

    /* ---------------- Embed players ---------------- */

    /**
     * Embed URL for the in-page player. $source: vidsrc | vidsrccc | videasy.
     */
    public static function embedUrl(int $id, string $type = 'movie', ?int $season = null, ?int $episode = null, string $source = 'vidsrc'): string
    {
        $base = match ($source) {
            'vidsrccc' => PLAYER_VIDSRC_CC,
            'videasy'  => PLAYER_VIDEASY,
            default    => PLAYER_VIDSRC_TO,
        };
        if ($type === 'movie') return "{$base}/movie/{$id}";
        $url = "{$base}/tv/{$id}";
        if ($season !== null) {
            $url .= "/{$season}";
            if ($episode !== null) $url .= "/{$episode}";
        }
        return $url;
    }

    /* ---------------- Formatting helpers ---------------- */

    public static function rating($v): string
    {
        return number_format((float)$v, 1);
    }

    /** Title of a mixed trending item (movie => title, tv => name). */
    public static function titleOf(array $item): string
    {
        return (string)($item['title'] ?? $item['name'] ?? '');
    }

    /** Release year of a mixed item. */
    public static function yearOf(array $item): string
    {
        return substr((string)($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4);
    }

    /** 'movie' or 'tv' for a mixed trending/search item. */
    public static function typeOf(array $item): string
    {
        $t = (string)($item['media_type'] ?? '');
        if ($t === 'movie' || $t === 'tv') return $t;
        return isset($item['first_air_date']) || isset($item['name']) ? 'tv' : 'movie';
    }
}
