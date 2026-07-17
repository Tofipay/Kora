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

    /** @var array<string,true> URLs queued for post-response revalidation */
    private static array $revalidate = [];
    private static bool $shutdownArmed = false;

    /** Full request URL for an endpoint (language-aware, deterministic). */
    private static function urlFor(string $endpoint, array $params = []): string
    {
        $params['language'] = $params['language'] ?? (Lang::current() === 'en' ? 'en-US' : 'ar-SA');
        return TMDB_BASE_URL . $endpoint . '?' . http_build_query($params);
    }

    /**
     * GET a TMDB v3 endpoint with stale-while-revalidate disk caching.
     *   fresh cache → served directly · expired cache → served instantly with
     *   ONE coalesced background refresh after the response is flushed ·
     *   nothing cached → one blocking fetch (first request only).
     * @return array decoded JSON ([] on total failure)
     */
    public static function get(string $endpoint, array $params = [], int $ttl = CACHE_TTL_TMDB): array
    {
        $url = self::urlFor($endpoint, $params);
        if (isset(self::$memo[$url])) return self::$memo[$url];

        $file = self::cacheFile($url);
        if (is_file($file)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                if ((time() - (int)filemtime($file)) >= $ttl) self::queueRevalidate($url);
                return self::$memo[$url] = $data;
            }
        }

        $data = self::store($url, self::fetch($url));
        return self::$memo[$url] = ($data ?? []);
    }

    /**
     * Warm several endpoints in ONE parallel round-trip. Entries:
     * [endpoint, params?]. Cached URLs (fresh OR stale) are skipped — stale
     * ones are already instant via get() + background refresh.
     */
    public static function warm(array $requests): void
    {
        $need = [];
        foreach ($requests as $req) {
            $endpoint = is_array($req) ? (string)($req[0] ?? '') : (string)$req;
            if ($endpoint === '') continue;
            $url = self::urlFor($endpoint, is_array($req) ? (array)($req[1] ?? []) : []);
            if (isset(self::$memo[$url]) || is_file(self::cacheFile($url))) continue;
            $need[$url] = true;
        }
        foreach (self::fetchMany(array_keys($need)) as $url => $body) {
            $data = self::store($url, $body);
            if ($data !== null) self::$memo[$url] = $data;
        }
    }

    /** Persist one validated response; null when unusable. */
    private static function store(string $url, ?string $body): ?array
    {
        $data = is_string($body) ? json_decode($body, true) : null;
        if (is_array($data) && !isset($data['status_code'])) {
            if (!is_dir(TMDB_CACHE_DIR)) @mkdir(TMDB_CACHE_DIR, 0755, true);
            @file_put_contents(self::cacheFile($url), $body, LOCK_EX);
            return $data;
        }
        // Upstream failed — keep serving the stale cache rather than an empty page.
        $file = self::cacheFile($url);
        if (is_file($file)) {
            $stale = json_decode((string)file_get_contents($file), true);
            if (is_array($stale)) return $stale;
        }
        return null;
    }

    private static function queueRevalidate(string $url): void
    {
        if (isset(self::$revalidate[$url]) || count(self::$revalidate) >= 8) return;
        if (!Cache::lock('tmdb|' . $url)) return;   // another request is on it
        self::$revalidate[$url] = true;
        if (!self::$shutdownArmed) {
            self::$shutdownArmed = true;
            register_shutdown_function([self::class, 'flushRevalidations']);
        }
    }

    /** Shutdown hook: flush the response, then refresh queued URLs in parallel. */
    public static function flushRevalidations(): void
    {
        if (!self::$revalidate) return;
        $urls = array_keys(self::$revalidate);
        self::$revalidate = [];
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) @ob_end_flush();
            @flush();
        }
        ignore_user_abort(true);
        foreach (self::fetchMany($urls) as $url => $body) {
            self::store($url, $body);
        }
    }

    private static function cacheFile(string $url): string
    {
        return TMDB_CACHE_DIR . '/' . sha1(CACHE_VERSION . '|' . $url) . '.json';
    }

    private static function fetch(string $url): ?string
    {
        $res = self::fetchMany([$url]);
        return $res[$url] ?? null;
    }

    /** Parallel GET via curl_multi. Returns url => body|null. */
    private static function fetchMany(array $urls): array
    {
        $out = [];
        if (!$urls) return $out;
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $url) {
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
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 1.0);
        } while ($active && $status === CURLM_OK);
        foreach ($handles as $url => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $out[$url] = (is_string($body) && $code >= 200 && $code < 300) ? $body : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
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
