<?php
declare(strict_types=1);

namespace Qamhad\Core;

use BtolatApi\BtolatScraper;
use BtolatApi\Cache as BtolatCache;
use BtolatApi\Config as BtolatConfig;
use BtolatApi\HttpClient as BtolatHttp;

/**
 * ============================================================
 *  VideoFeed — Btolat highlights feed (btolat_php_api adapter)
 * ============================================================
 *
 * The «الفيديوهات» section is powered by the vendored Btolat Videos
 * JSON API project (btolat_php_api/ at the site root — used verbatim,
 * nothing modified). This adapter only:
 *   1. boots the vendored classes with the site's storage for caching,
 *   2. maps Btolat items onto the shape the existing views expect
 *      (title / video_url / thumbnail / champ_title / created_at / id),
 *   3. provides News-style pagination (5 per site page) over the
 *      source batches (~15 per source page),
 *   4. exposes single-video details (provider / embed / media URLs)
 *      for the in-site watch page.
 *
 * Source mechanics (per the project's docs):
 *   - category "all"  → /videos + POST /api/video/LoadMore/0 cursor
 *     (lastRowId + lasRowDate), ~15 items per batch
 *   - leagues/teams   → {path}?p=N direct source pagination
 *   - detail          → /video/{id} exposes youtube / x / direct media
 */
final class VideoFeed
{
    /** Items per source batch (observed & documented: 15). */
    private const SOURCE_BATCH = 15;

    /** Site page size (News-style pagination). */
    public const PER_PAGE = 5;

    private static ?BtolatScraper $scraper = null;

    /** Boot the vendored Btolat project once per request. */
    private static function scraper(): BtolatScraper
    {
        if (self::$scraper !== null) return self::$scraper;

        $base = dirname(APP_DIR) . '/btolat_php_api/src';
        require_once $base . '/Config.php';
        require_once $base . '/Cache.php';
        require_once $base . '/HttpClient.php';
        require_once $base . '/BtolatScraper.php';

        // Cache inside the site's storage (shared-hosting write perms are
        // already granted there). TTL comes from the vendored Config (300s).
        $cacheDir = STORAGE_DIR . '/cache/btolat';
        return self::$scraper = new BtolatScraper(new BtolatHttp(new BtolatCache($cacheDir)));
    }

    /* ============================================================
     *  Public API (used by Videos controller / ApiJson / views)
     * ============================================================ */

    /**
     * The English site keeps the previous YSScores feed (its upstream has a
     * dedicated /en/video section); Arabic uses Btolat. All entry points
     * below dispatch on this flag.
     */
    private static function useYs(): bool
    {
        return Lang::current() === 'en';
    }

    /**
     * Championship/team tabs for the filter bar.
     * Arabic → the vendored Btolat ready-made category list (id = slug key).
     * English → YSScores categories (id = numeric champ id).
     *
     * @return array<int,array{id:string,title:string}>
     */
    public static function categories(): array
    {
        if (self::useYs()) return YsVideoFeed::categories();
        self::scraper(); // ensure classes are loaded
        $out = [];
        foreach (BtolatConfig::categories() as $key => $cat) {
            $out[] = ['id' => (string)$key, 'title' => (string)$cat['name']];
        }
        return $out;
    }

    /** Whether a category key exists (used to sanitize ?champ=). */
    public static function isCategory(string $key): bool
    {
        if (self::useYs()) return $key === 'all' || preg_match('/^\d+$/', $key) === 1;
        self::scraper();
        return isset(BtolatConfig::categories()[$key]);
    }

    /**
     * One SITE page of videos — News-style pagination, PER_PAGE per page.
     *
     * Site pages map by absolute raw index onto source batches of 15
     * (15 % 5 == 0 → a site page never straddles two batches): numbering
     * is stable, nothing repeats, nothing is lost, one source fetch per
     * page. A short batch (<15) means the end of the feed.
     *
     * @return array{items:array<int,array>,page:int,has_next:bool,has_prev:bool}
     */
    public static function page(string $champ = 'all', int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $champ = self::isCategory($champ) ? $champ : 'all';
        $page  = max(1, $page);

        // English section → previous YSScores feed (already page-based).
        if (self::useYs()) return YsVideoFeed::page($champ, $page, $perPage);

        $offset     = ($page - 1) * $perPage;                  // absolute raw index
        $sourcePage = intdiv($offset, self::SOURCE_BATCH) + 1; // 1-based source page
        $inBatch    = $offset % self::SOURCE_BATCH;

        $batch = self::batch($champ, $sourcePage);
        $raw   = $batch['videos'];

        $slice = array_slice($raw, $inBatch, $perPage);

        // Drop incomplete videos (no title or no page link) — never render
        // an empty card.
        $items = array_values(array_filter(
            array_map([self::class, 'normalize'], $slice),
            static fn(array $v): bool => $v['title'] !== '' && $v['video_url'] !== ''
        ));

        // Next page exists if this batch holds items beyond our window, or
        // the batch is FULL and the source reports more pages.
        $batchFull = count($raw) >= self::SOURCE_BATCH;
        $hasNext   = count($raw) > $inBatch + $perPage
                  || ($batchFull && !empty($batch['has_more']));

        return [
            'items'    => $items,
            'page'     => $page,
            'has_next' => $hasNext,
            'has_prev' => $page > 1,
        ];
    }

    /**
     * Single video details for the in-site watch page. Returns the
     * normalized card fields plus provider / embed_url / external_url /
     * media_url / youtube_id as discovered by the vendored scraper,
     * or null when the id can't be fetched.
     */
    public static function find(int $id): ?array
    {
        try {
            $d = self::scraper()->video($id);
        } catch (\Throwable $e) {
            self::log('video ' . $id . ' → ' . $e->getMessage());
            return null;
        }
        if (trim((string)($d['title'] ?? '')) === '') return null;

        $embed    = (string)($d['embed_url'] ?? '');
        $external = (string)($d['external_url'] ?? '');
        $media    = (string)($d['media_url'] ?? '');

        // YouTube id — from the youtube provider OR an ld+json embedURL that
        // points at YouTube (the v1.0.1 scraper reports those as 'ld_json').
        $ytId = null;
        if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?(?:.*&)?v=)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $embed, $m)) {
            $ytId = $m[1];
        }

        // X (Twitter) status id — from x.com OR twitter.com links — used for
        // the IN-SITE tweet embed (platform.twitter.com/embed/Tweet.html).
        $tweetId = null;
        $xUrl    = null;
        foreach ([$external, $embed] as $u) {
            if ($u !== '' && preg_match('#(?:x\.com|twitter\.com)/[^/]+/status/(\d+)#i', $u, $m)) {
                $tweetId = $m[1];
                // Normalize the outbound button to x.com regardless of source.
                $xUrl = preg_replace('#^https?://(?:www\.)?twitter\.com#i', 'https://x.com', preg_replace('/\?.*$/', '', $u));
                break;
            }
        }

        // Direct stream: mp4 vs HLS (m3u8 plays through the site's hls.js).
        $isHls = $media !== '' && preg_match('/\.m3u8(\?|$)/i', $media) === 1;

        // Generic external embed player (e.g. vortexvisionworks and friends,
        // reported by the scraper's ld+json pass as 'external_embed'):
        // anything with an https embed URL that is neither YouTube nor X
        // renders in-site inside a plain iframe.
        $externalEmbed = null;
        if ($ytId === null && $tweetId === null && $embed !== ''
            && preg_match('#^https://#i', $embed) === 1) {
            $externalEmbed = $embed;
        }

        return [
            'id'           => $id,
            'title'        => trim((string)$d['title']),
            'thumbnail'    => (string)($d['thumbnail'] ?? ''),
            'champ_title'  => trim((string)($d['category']['name'] ?? '')),
            'provider'     => $d['provider'] ?? null,
            'embed_url'    => $embed !== '' ? $embed : null,
            'external_url' => $external !== '' ? $external : null,
            'media_url'    => $media !== '' ? $media : null,
            'is_hls'       => $isHls,
            'youtube_id'   => $ytId,
            'tweet_id'     => $tweetId,
            'x_url'        => $xUrl,
            'embed_iframe' => $externalEmbed,
        ];
    }

    /**
     * Related videos for the watch page: the freshest items from the same
     * pool ("all", first batches), excluding the current id.
     *
     * @return array<int,array>
     */
    public static function related(int $excludeId, int $limit = 8): array
    {
        $out = [];
        for ($p = 1; $p <= 2 && count($out) < $limit; $p++) {
            $batch = self::batch('all', $p);
            foreach ($batch['videos'] as $v) {
                if ((int)($v['id'] ?? 0) === $excludeId) continue;
                $n = self::normalize($v);
                if ($n['title'] === '' || $n['video_url'] === '') continue;
                $out[] = $n;
                if (count($out) >= $limit) break;
            }
            if (empty($batch['has_more'])) break;
        }
        return $out;
    }

    /* ============================================================
     *  Internal
     * ============================================================ */

    /**
     * One source batch (page of ~15) via the vendored scraper, with a
     * stale-tolerant guard: on upstream failure an empty ok-shaped batch
     * is returned so the section degrades to its empty state instead of
     * a fatal error. (The vendored Cache already absorbs repeat traffic.)
     *
     * @return array{videos:array<int,array>,has_more:bool}
     */
    private static function batch(string $champ, int $sourcePage): array
    {
        try {
            $r = self::scraper()->videos($champ, $sourcePage, 1);
            return ['videos' => $r['videos'], 'has_more' => (bool)$r['has_more']];
        } catch (\Throwable $e) {
            self::log("videos {$champ} p{$sourcePage} → " . $e->getMessage());
            return ['videos' => [], 'has_more' => false];
        }
    }

    /** Map a Btolat item onto the card shape the views already use. */
    private static function normalize(array $item): array
    {
        $thumb = (string)($item['thumbnail'] ?? '');
        // Never show a provider placeholder image — brand fallback instead.
        if (preg_match('#(^|/)[a-z0-9_]*default\.#i', $thumb)) $thumb = '';

        return [
            'id'          => (int)($item['id'] ?? 0),
            'title'       => trim((string)($item['title'] ?? '')),
            'video_url'   => (string)($item['page_url'] ?? ''),
            'video_type'  => 'btolat',
            'youtube_id'  => null,
            'thumbnail'   => $thumb,
            'champ_title' => trim((string)($item['category']['name'] ?? '')),
            'created_at'  => (string)($item['published_at'] ?? $item['published_date'] ?? ''),
        ];
    }

    /** Append a line to the video feed log (best-effort, capped). */
    private static function log(string $msg): void
    {
        $file = defined('SETTINGS_DIR') ? SETTINGS_DIR . '/videos.log' : null;
        if (!$file) return;
        if (is_file($file) && filesize($file) > 262144) @file_put_contents($file, '');
        @file_put_contents($file, '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
    }
}
