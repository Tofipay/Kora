<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Yacine TV channel resolver.
 *
 * Turns a Yacine channel API URL (http://ver3.yacinelive.com/api/channel/1471)
 * into ready-to-play sources for the internal player:
 *
 *   1. Fetch the endpoint server-side (so the browser never touches a blocked
 *      host, and the required app User-Agent is applied).
 *   2. Decrypt: read the "t" response header, then base64-decode → XOR with
 *      (KEY . t) → JSON.
 *   3. Build sources:
 *        - HLS  (url_type 3, .m3u8): proxied through /api/stream.php so blocked
 *          hosts and mixed-content (http on an https page) both work.
 *        - DASH (url_type 1, .mpd) with ClearKey: played directly by dash.js,
 *          with kid/k converted from base64url to hex.
 *
 * Only ADDS capability to the player — existing direct-URL servers are
 * untouched (see expandServers()).
 */
final class Yacine
{
    /** Short cache so the same channel isn't decrypted twice per render. */
    private const CACHE_TTL = 30;

    /** Seconds until the last-resolved link expires (for proactive renewal). */
    private static ?int $lastTtl = null;

    /** Is this server URL a Yacine channel/category API endpoint? */
    public static function isApiUrl(string $url): bool
    {
        $u = strtolower(trim($url));
        if (!preg_match('#^https?://#', $u)) {
            return false;
        }
        return str_contains($u, 'yacinelive.com/api/')
            || (bool) preg_match('#/api/(channel|categories)/\d+#', $u);
    }

    /**
     * Expand a server list: any Yacine API URL becomes its real playable
     * sources; every other server is passed through unchanged.
     *
     * @param array<int,array<string,mixed>> $servers
     * @return array<int,array{name:string,url:string,type:string,drm:?array}>
     */
    public static function expandServers(array $servers, bool $primaryOnly = false): array
    {
        $out = [];
        $minTtl = null;
        foreach ($servers as $s) {
            $name = trim((string) ($s['name'] ?? 'Server'));
            $url  = trim((string) ($s['url'] ?? ''));

            if ($url !== '' && self::isApiUrl($url)) {
                $sources = self::resolveChannel($url);
                if (self::$lastTtl !== null) {
                    $minTtl = $minTtl === null ? self::$lastTtl : min($minTtl, self::$lastTtl);
                }
                if ($sources) {
                    if ($primaryOnly) {
                        // One clean entry per channel (keeps the broadcast label),
                        // playing its best source. Used by the match player.
                        $src = $sources[0];
                        $out[] = ['name' => $name, 'url' => $src['url'], 'type' => $src['type'], 'drm' => $src['drm']];
                    } else {
                        foreach ($sources as $src) {
                            $label = $src['label'] !== '' ? ($name . ' · ' . $src['label']) : $name;
                            $out[] = ['name' => $label, 'url' => $src['url'], 'type' => $src['type'], 'drm' => $src['drm']];
                        }
                    }
                    continue;
                }
                // Resolution failed: keep the original entry (fails gracefully).
            }

            $out[] = [
                'name' => $name,
                'url'  => $url,
                'type' => Streams::resolveType($url, (string) ($s['type'] ?? 'auto')),
                'drm'  => null,
            ];
        }
        self::$lastTtl = $minTtl;
        return $out;
    }

    /**
     * Resolve a channel by numeric id (builds the API URL from the base).
     * $fresh = true skips the short cache to get a NEW stream token — used by
     * the player's auto-reconnect so an expired link is replaced, not retried.
     */
    public static function resolveById(int $id, bool $fresh = false): array
    {
        if ($id <= 0) {
            return [];
        }
        return self::resolveChannel(rtrim(YACINE_API_BASE, '/') . '/api/channel/' . $id, $fresh);
    }

    /** Extract the numeric channel id from a Yacine channel API URL, or null. */
    public static function channelIdFromUrl(string $url): ?int
    {
        if (self::isApiUrl($url) && preg_match('#/api/channel/(\d+)#', $url, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Resolve one channel API URL into sources (HLS first).
     * @return array<int,array{label:string,url:string,type:string,drm:?array}>
     */
    public static function resolveChannel(string $apiUrl, bool $fresh = false): array
    {
        $data    = self::fetchDecrypt($apiUrl, $fresh);
        $streams = $data['data'] ?? [];
        if (!is_array($streams)) {
            return [];
        }

        $sources = [];
        $expiries = [];
        foreach ($streams as $i => $st) {
            if (!is_array($st)) {
                continue;
            }
            $url = trim((string) ($st['url'] ?? ''));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $type    = self::type($st, $url);                 // 'm3u8' | 'mpd'
            $headers = self::headers($st);
            $drm     = self::parseDrm($st['drm'] ?? null);

            // Track the stream-token expiry (?e=<unix>) so the player can renew
            // the link a little BEFORE it dies — seamless long playback.
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            if (!empty($q['e']) && ctype_digit((string) $q['e'])) {
                $expiries[] = (int) $q['e'];
            }

            // DASH plays directly (its CDNs aren't the blocked host); HLS is
            // proxied to bypass the block and fix http-on-https mixed content.
            $playUrl = $type === 'mpd' ? $url : self::proxyUrl($url, $headers);

            $sources[] = [
                'label' => (string) ($st['name'] ?? ('S' . ($i + 1))),
                'url'   => $playUrl,
                'type'  => $type,
                'drm'   => $drm,
            ];
        }

        // Seconds until we should proactively renew (soonest expiry − margin).
        if ($expiries) {
            self::$lastTtl = max(20, min(3600, min($expiries) - time() - 20));
        } else {
            self::$lastTtl = null;
        }

        // Prefer the simplest/most reliable first: HLS without DRM.
        usort($sources, static function ($a, $b) {
            $rank = static fn($s) => $s['type'] === 'm3u8' ? 0 : 1;
            return $rank($a) <=> $rank($b);
        });

        return $sources;
    }

    /** Seconds until the last-resolved channel's link should be renewed (or null). */
    public static function lastTtl(): ?int
    {
        return self::$lastTtl;
    }

    /**
     * Diagnostics for one channel (why won't it play?). For each source it
     * probes the origin from the server, and for HLS it also probes this
     * site's own /api/stream.php proxy — revealing whether the block is
     * bypassed, whether the origin is reachable, and whether the proxy runs.
     * @return array<string,mixed>
     */
    public static function diagnose(int $id): array
    {
        $apiUrl = rtrim(YACINE_API_BASE, '/') . '/api/channel/' . $id;
        $data   = self::fetchDecrypt($apiUrl);
        $streams = is_array($data['data'] ?? null) ? $data['data'] : [];

        $out = [
            'api_url'     => $apiUrl,
            'decrypt_ok'  => $streams !== [],
            'stream_count'=> count($streams),
            'streams'     => [],
        ];
        foreach ($streams as $i => $st) {
            if (!is_array($st)) {
                continue;
            }
            $url     = trim((string) ($st['url'] ?? ''));
            $type    = self::type($st, $url);
            $headers = self::headers($st);

            $row = [
                'label'      => (string) ($st['name'] ?? ('S' . $i)),
                'type'       => $type,
                'has_drm'    => self::parseDrm($st['drm'] ?? null) !== null,
                'origin_url' => $url,
                'origin'     => self::probe($url, $headers),
            ];
            if ($type === 'm3u8') {
                $proxyPath = self::proxyUrl($url, $headers);
                $row['proxy_url'] = $proxyPath;
                $row['proxy']     = self::probe(self::siteBase() . $proxyPath, []);
            }
            $out['streams'][] = $row;
        }
        return $out;
    }

    /** Small ranged GET used by diagnose(): status, type, first bytes. */
    private static function probe(string $url, array $headers): array
    {
        if ($url === '' || !preg_match('#^https?://#i', $url) || !function_exists('curl_init')) {
            return ['error' => 'bad url or no curl'];
        }
        $hh = [];
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'User-Agent') !== 0) {
                $hh[] = "{$k}: {$v}";
            }
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT      => $headers['User-Agent'] ?? YACINE_UA,
            CURLOPT_HTTPHEADER     => $hh,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RANGE          => '0-2047',
        ]);
        $body  = (string) curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ct    = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            'http'         => $code,
            'content_type' => $ct,
            'error'        => $errno ? "cURL {$errno}: {$err}" : null,
            'is_m3u8'      => str_contains($body, '#EXTM3U'),
            'looks_html'   => stripos($body, '<!doctype') !== false || stripos($body, '<html') !== false,
            'bytes'        => strlen($body),
            'head'         => mb_substr((string) preg_replace('/\s+/', ' ', $body), 0, 140, 'UTF-8'),
        ];
    }

    private static function siteBase(): string
    {
        if (defined('SITE_URL')) {
            return SITE_URL;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    // ------------------------------------------------------------------
    // Fetch + decrypt
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    private static function fetchDecrypt(string $url, bool $fresh = false): array
    {
        // Normalise to the configured base to avoid open-ended fetches.
        $url = self::normalizeApiUrl($url);
        if ($url === '') {
            return [];
        }

        $cacheKey = 'yacine_' . md5($url);
        if (!$fresh) {
            $cached = Cache::get($cacheKey, self::CACHE_TTL);
            if (is_array($cached)) {
                return $cached;
            }
        }

        [$body, $rawHeaders] = self::http($url);
        if ($body === '') {
            return [];
        }

        $t = self::timestamp($rawHeaders);
        $decoded = base64_decode($body, true);
        if ($decoded === false || $decoded === '') {
            return [];
        }
        $json = self::xor($decoded, YACINE_KEY . $t);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        Cache::set($cacheKey, $data);
        return $data;
    }

    /** Force the host/scheme to the configured Yacine base (safety). */
    private static function normalizeApiUrl(string $url): string
    {
        $parts = parse_url($url);
        $path  = (string) ($parts['path'] ?? '');
        if (!preg_match('#/api/(channel|categories)/\d+#', $path)) {
            return '';
        }
        $q = isset($parts['query']) ? ('?' . $parts['query']) : '';
        return rtrim(YACINE_API_BASE, '/') . $path . $q;
    }

    /** @return array{0:string,1:string} [body, rawHeaders] */
    private static function http(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['', ''];
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => YACINE_UA,
            CURLOPT_ENCODING       => '',
        ]);
        $resp = curl_exec($ch);
        $ok   = $resp !== false && curl_errno($ch) === 0;
        $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if (!$ok) {
            return ['', ''];
        }
        $resp = (string) $resp;
        return [substr($resp, $hs), substr($resp, 0, $hs)];
    }

    private static function timestamp(string $rawHeaders): string
    {
        if (preg_match('/^t:\s*(.+)$/mi', $rawHeaders, $m)) {
            $t = trim($m[1]);
            if (preg_match('/^\d{1,20}$/', $t)) {
                return $t;
            }
        }
        return (string) time();
    }

    private static function xor(string $data, string $key): string
    {
        $out = '';
        $klen = strlen($key);
        if ($klen === 0) {
            return $data;
        }
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $out .= chr(ord($data[$i]) ^ ord($key[$i % $klen]));
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Source helpers
    // ------------------------------------------------------------------

    private static function type(array $stream, string $url): string
    {
        $ut = $stream['url_type'] ?? null;
        if ($ut === 1 || $ut === '1') {
            return 'mpd';
        }
        if ($ut === 3 || $ut === '3') {
            return 'm3u8';
        }
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        return str_ends_with($path, '.mpd') ? 'mpd' : 'm3u8';
    }

    /** @return array<string,string> */
    private static function headers(array $stream): array
    {
        $h = [];
        if (!empty($stream['headers']) && is_array($stream['headers'])) {
            foreach ($stream['headers'] as $k => $v) {
                if (is_string($k) && is_string($v) && trim($v) !== '') {
                    $h[self::cleanHeaderName($k)] = self::cleanHeaderValue($v);
                }
            }
        }
        $map = ['user_agent' => 'User-Agent', 'referer' => 'Referer', 'referrer' => 'Referer', 'origin' => 'Origin', 'cookie' => 'Cookie'];
        foreach ($map as $src => $dst) {
            if (!empty($stream[$src]) && is_string($stream[$src])) {
                $h[$dst] = self::cleanHeaderValue($stream[$src]);
            }
        }
        return array_filter($h, static fn($k) => $k !== '', ARRAY_FILTER_USE_KEY);
    }

    private static function cleanHeaderName(string $k): string
    {
        return (string) preg_replace('/[^A-Za-z0-9\-]/', '', $k);
    }

    private static function cleanHeaderValue(string $v): string
    {
        return mb_substr(str_replace(["\r", "\n"], '', $v), 0, 1024, 'UTF-8');
    }

    /**
     * ClearKey DRM → dash.js format: ['clearkeys' => ['kidHex' => 'keyHex']].
     * @return array<string,mixed>|null
     */
    private static function parseDrm(mixed $drm): ?array
    {
        if (!is_array($drm) || ($drm['type'] ?? '') !== 'clearkey' || empty($drm['license'])) {
            return null;
        }
        $license = is_string($drm['license']) ? json_decode($drm['license'], true) : $drm['license'];
        if (!is_array($license) || empty($license['keys']) || !is_array($license['keys'])) {
            return null;
        }
        $clearkeys = [];
        foreach ($license['keys'] as $k) {
            if (!is_array($k) || empty($k['kid']) || empty($k['k'])) {
                continue;
            }
            $kid = self::b64uToHex((string) $k['kid']);
            $key = self::b64uToHex((string) $k['k']);
            if ($kid !== '' && $key !== '') {
                $clearkeys[$kid] = $key;
            }
        }
        return $clearkeys ? ['clearkeys' => $clearkeys] : null;
    }

    private static function b64uToHex(string $b64u): string
    {
        $b64 = strtr($b64u, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode($b64, true);
        return $bin === false ? '' : bin2hex($bin);
    }

    // ------------------------------------------------------------------
    // Proxy URL signing (must match public/api/stream.php)
    // ------------------------------------------------------------------

    /** @param array<string,string> $headers */
    private static function proxyUrl(string $url, array $headers): string
    {
        $params = ['url' => $url];
        if ($headers) {
            $params['h'] = base64_encode(json_encode($headers, JSON_UNESCAPED_SLASHES) ?: '{}');
        }
        $params['sig'] = self::sign($params);
        $base = defined('YACINE_PROXY_PATH') ? YACINE_PROXY_PATH : '/stream';
        return $base . '?' . http_build_query($params);
    }

    /** @param array<string,string> $params */
    public static function sign(array $params): string
    {
        unset($params['sig']);
        ksort($params);
        return hash_hmac('sha256', http_build_query($params), YACINE_PROXY_SECRET);
    }
}
