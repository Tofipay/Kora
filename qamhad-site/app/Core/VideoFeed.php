<?php
declare(strict_types=1);

namespace Qamhad\Core;

/**
 * ============================================================
 *  VideoFeed — YSScores highlights feed (server-side, cached)
 * ============================================================
 *
 * Ports the standalone YSScores video scraper into the project so the
 * new «الفيديوهات» section reuses the app's on-disk cache and never
 * exposes a separate public endpoint. No project API is changed.
 *
 * Upstream (discovered, unofficial):
 *   GET  /ar/video        → page carrying the CSRF token + category anchors
 *   POST /ar/video_champ  → first page of a category   (field: champ)
 *   POST /ar/video_more   → subsequent pages (field: champ + raw_skip)
 *
 * Everything is cached on disk (Cache) so the upstream site is hit at
 * most once per TTL per (champ, skip) — keeping the section fast and
 * resilient (stale-fallback on failure), exactly like the rest of the app.
 */
final class VideoFeed
{
    private const HOST      = 'https://www.ysscores.com/';
    private const PAGE_SIZE = 80;                 // upstream page size
    private const RETRIES   = 2;                  // curl attempts on transient failure
    private const UA        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                            . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                            . 'Chrome/125.0.0.0 Safari/537.36';

    /** Per-request memo of the CSRF handshake so one page view = one session. */
    private static ?array $session = null;

    /**
     * Language-aware upstream base. The video section exists per language on
     * the source (…/ar/video vs …/en/video); pick it from the active site
     * language so an English visitor gets English titles. Cache keys derive
     * from this, so ar/en feeds are cached independently.
     */
    private static function base(): string
    {
        return self::HOST . (Lang::current() === 'en' ? 'en' : 'ar') . '/';
    }

    /* ============================================================
     *  Public API
     * ============================================================ */

    /**
     * Championship tabs for the filter bar. Always starts with "الكل".
     * Cached for CACHE_TTL_LEAGUES (1h) — categories rarely change.
     *
     * @return array<int,array{id:string,title:string}>
     */
    public static function categories(): array
    {
        $key = self::base() . 'video#categories';
        $cached = Cache::get($key, CACHE_TTL_LEAGUES);
        if (is_array($cached) && $cached) return $cached;

        $html = self::session()['body'] ?? '';
        $cats = [['id' => 'all', 'title' => 'الكل']];
        if ($html !== '' && preg_match_all(
            '/champ_id=["\']([^"\']+)["\'][^>]*title=["\']([^"\']+)["\']/i',
            $html,
            $m
        )) {
            $seen = [];
            for ($i = 0, $n = count($m[0]); $i < $n; $i++) {
                $id = trim($m[1][$i]);
                if ($id === '' || isset($seen[$id])) continue;
                $seen[$id] = true;
                $cats[] = ['id' => $id, 'title' => html_entity_decode($m[2][$i], ENT_QUOTES, 'UTF-8')];
            }
        }
        if (count($cats) > 1) Cache::set($key, $cats);
        else { $stale = Cache::stale($key); if (is_array($stale) && $stale) return $stale; }
        return $cats;
    }

    /**
     * One page of videos for a category. Newest first (upstream order).
     * Cached per (champ, skip) for CACHE_TTL_NEWS (15min), stale-fallback.
     *
     * @return array{success:bool,champ:string,skip:int,count:int,
     *               has_more:bool,next_skip:?int,data:array<int,array>}
     */
    public static function videos(string $champ = 'all', int $skip = 0): array
    {
        $champ = preg_match('/^\d+$/', $champ) ? $champ : 'all';
        $skip  = max(0, $skip);

        $key = self::base() . "video#feed|{$champ}|{$skip}";
        $cached = Cache::get($key, CACHE_TTL_NEWS);
        if (is_array($cached) && !empty($cached['success'])) return $cached;

        $out = self::fetchVideos($champ, $skip);
        if (!empty($out['success'])) {
            Cache::set($key, $out);
            return $out;
        }
        // Upstream hiccup → last good page for this slice, else an empty ok set.
        $stale = Cache::stale($key);
        if (is_array($stale) && !empty($stale['success'])) return $stale;
        return ['success' => true, 'champ' => $champ, 'skip' => $skip,
                'count' => 0, 'has_more' => false, 'next_skip' => null, 'data' => []];
    }

    /**
     * Find a single video (title, championship, date) by its YouTube id,
     * scanning the first few pages of the "all" feed. Used by the in-site
     * watch page for metadata + related videos without a lookup endpoint.
     *
     * @return array{video:?array, related:array<int,array>}
     */
    public static function findByYoutubeId(string $ytId, int $scanPages = 3): array
    {
        $pool = [];
        for ($p = 0; $p < $scanPages; $p++) {
            $res = self::videos('all', $p * self::PAGE_SIZE);
            foreach ($res['data'] as $v) $pool[] = $v;
            if (empty($res['has_more'])) break;
        }
        $found = null;
        foreach ($pool as $v) {
            if (($v['youtube_id'] ?? '') === $ytId) { $found = $v; break; }
        }
        // Related = other YouTube videos, newest first, excluding self.
        $related = [];
        foreach ($pool as $v) {
            if (($v['youtube_id'] ?? '') !== '' && ($v['youtube_id'] ?? '') !== $ytId) {
                $related[] = $v;
            }
            if (count($related) >= 12) break;
        }
        return ['video' => $found, 'related' => $related];
    }

    /* ============================================================
     *  Internal
     * ============================================================ */

    /** Fetch + normalize one video page from the upstream AJAX endpoint. */
    private static function fetchVideos(string $champ, int $skip): array
    {
        $s = self::session();
        if (empty($s['csrf'])) {
            return ['success' => false, 'error' => 'no-csrf'];
        }

        $endpoint = $skip > 0 ? 'video_more' : 'video_champ';
        $post = ['champ' => $champ];
        if ($skip > 0) $post['raw_skip'] = $skip;

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token: ' . $s['csrf'],
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . self::base() . 'video',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Accept-Language: ar,en;q=0.9',
        ];
        if (!empty($s['cookies'])) $headers[] = 'Cookie: ' . $s['cookies'];

        [$code, $raw] = self::curl(self::base() . $endpoint, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($code !== 200) return ['success' => false, 'error' => "http-{$code}"];

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) return ['success' => false, 'error' => 'bad-json'];

        $videos = array_map([self::class, 'normalize'], $data);
        // YSScores keeps a "more" affordance as long as the current page
        // returned ANY videos; the end is reached when a page returns none
        // (next_skip → null). Matches video_champ/video_more + raw_skip (+80).
        $hasMore = count($videos) > 0;

        return [
            'success'   => true,
            'champ'     => $champ,
            'skip'      => $skip,
            'count'     => count($videos),
            'has_more'  => $hasMore,
            'next_skip' => $hasMore ? $skip + self::PAGE_SIZE : null,
            'data'      => $videos,
        ];
    }

    /** Normalize one raw upstream item into the shape the views expect. */
    private static function normalize(array $item): array
    {
        $url  = (string)($item['link'] ?? '');
        $type = 'other';
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) $type = 'youtube';
        elseif (str_contains($url, 'fifa.com')) $type = 'fifa';

        $ytId = null;
        if ($type === 'youtube' && preg_match('#(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            $ytId = $m[1];
        }

        // Prefer the YouTube thumbnail (clean, fast CDN); otherwise use the
        // upstream image if it is an absolute URL; never a provider default.
        $thumb = '';
        if ($ytId) {
            $thumb = "https://i.ytimg.com/vi/{$ytId}/hqdefault.jpg";
        } else {
            $t = (string)($item['v_image'] ?? '');
            // Skip provider placeholder logos (default.png, video_default.png,
            // *_default.*) — the card falls back to the Qamhad brand mark.
            if ($t !== '' && !preg_match('#(^|/)[a-z0-9_]*default\.#i', $t)) {
                if (preg_match('#^https?://#i', $t)) $thumb = $t;
                elseif ($t !== '') $thumb = 'https://www.ysscores.com/' . ltrim(preg_replace('#^[./]+#', '', $t), '/');
            }
        }

        return [
            'title'       => trim((string)($item['title'] ?? '')),
            'video_url'   => $url,
            'video_type'  => $type,
            'youtube_id'  => $ytId,
            'thumbnail'   => $thumb,
            'champ_title' => trim((string)($item['champ_title'] ?? $item['tournament'] ?? '')),
            'created_at'  => (string)($item['created_at'] ?? ''),
        ];
    }

    /** Lazy CSRF/cookie handshake, memoized for the current request. */
    private static function session(): array
    {
        if (self::$session !== null) return self::$session;

        [$code, $resp, $hdr] = self::curl(self::base() . 'video', [CURLOPT_HEADER => true], true);
        $body = is_string($resp) ? substr($resp, (int)$hdr) : '';
        $head = is_string($resp) ? substr($resp, 0, (int)$hdr) : '';

        $cookies = '';
        if (preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)/mi', $head, $cm) && !empty($cm[1])) {
            $cookies = implode('; ', $cm[1]);
        }
        $csrf = '';
        foreach ([
            '/<meta\s+name=["\']_token["\']\s+content=["\']([^"\']+)["\']/i',
            '/<meta\s+content=["\']([^"\']+)["\']\s+name=["\']_token["\']/i',
            '/window\.__CSRF\s*=\s*["\']([^"\']+)["\']/i',
        ] as $pat) {
            if (preg_match($pat, $body, $mm)) { $csrf = $mm[1]; break; }
        }

        return self::$session = ['csrf' => $csrf, 'cookies' => $cookies, 'body' => $body];
    }

    /**
     * curl wrapper with retry + smart timeout + error logging.
     * Retries transient failures (network error / 5xx / 429) with a short
     * backoff; 4xx are returned immediately (not retryable). Returns
     * [httpCode, body|response, headerSize?].
     *
     * @return array{0:int,1:?string,2:int}
     */
    private static function curl(string $url, array $opts = [], bool $withHeader = false): array
    {
        $lastCode = 0; $resp = null; $hsz = 0;

        for ($attempt = 1; $attempt <= self::RETRIES; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, $opts + [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => self::UA,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_ENCODING       => 'gzip',
            ]);
            $resp = curl_exec($ch);
            $lastCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = $resp === false ? (string)curl_error($ch) : '';
            $hsz  = $withHeader ? (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE) : 0;
            curl_close($ch);

            // Success (2xx/3xx) → return immediately.
            if ($resp !== false && $lastCode >= 200 && $lastCode < 400) {
                return [$lastCode, (string)$resp, $hsz];
            }
            // Non-retryable client errors → return as-is.
            if ($lastCode >= 400 && $lastCode < 500 && $lastCode !== 429) {
                self::log("GET {$url} → HTTP {$lastCode}");
                return [$lastCode, $resp === false ? null : (string)$resp, $hsz];
            }
            // Transient: log and back off before the next attempt.
            self::log("GET {$url} attempt {$attempt}/" . self::RETRIES
                . " → " . ($lastCode ?: 'network') . ($err !== '' ? " ({$err})" : ''));
            if ($attempt < self::RETRIES) usleep(300000 * $attempt);
        }
        return [$lastCode, $resp === false ? null : (string)$resp, $hsz];
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
