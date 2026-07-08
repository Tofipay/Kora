<?php
declare(strict_types=1);

namespace Qamhad\Core;

/**
 * First-party stream proxy for Yacine sources.
 *
 * The browser talks only to this endpoint, never to the (often blocked) origin
 * host — so live streams play without a VPN and without http-on-https mixed
 * content. It forwards the per-channel headers (User-Agent / Referer …) the
 * origin requires, and rewrites HLS playlists so every segment stays on the
 * proxy. HMAC-signed URLs prevent open-proxy abuse.
 *
 * Reachable two ways (both call serve()):
 *   - /api/stream.php  (direct file execution)
 *   - /stream          (front-controller route — works on any server config)
 * URLs are produced by Yacine::proxyUrl() with the same signing secret.
 */
final class StreamProxy
{
    public static function serve(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        while (ob_get_level() > 0) { @ob_end_clean(); }

        $secret = defined('YACINE_PROXY_SECRET') ? YACINE_PROXY_SECRET : '';

        $url = isset($_GET['url']) ? trim((string) $_GET['url']) : '';
        $sig = isset($_GET['sig']) ? (string) $_GET['sig'] : '';
        $h   = isset($_GET['h'])   ? (string) $_GET['h']   : '';

        if ($url === '') { self::fail(400, 'missing url'); }

        // verify signature
        $params = ['url' => $url];
        if ($h !== '') { $params['h'] = $h; }
        ksort($params);
        if (!hash_equals(hash_hmac('sha256', http_build_query($params), $secret), $sig)) {
            self::fail(403, 'invalid signature');
        }

        // validate URL / block internal hosts (SSRF)
        if (!filter_var($url, FILTER_VALIDATE_URL)) { self::fail(400, 'bad url'); }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) { self::fail(400, 'bad scheme'); }
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            self::fail(400, 'blocked host');
        }

        // decode forwarded stream headers
        $streamHeaders = [];
        if ($h !== '') {
            $decoded = json_decode((string) base64_decode($h, true), true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) {
                    if (is_string($k) && is_string($v)) {
                        $k = preg_replace('/[^A-Za-z0-9\-]/', '', $k) ?? '';
                        $v = str_replace(["\r", "\n"], '', $v);
                        if ($k !== '') { $streamHeaders[$k] = $v; }
                    }
                }
            }
        }

        $ua = $streamHeaders['User-Agent'] ?? (defined('YACINE_UA') ? YACINE_UA : 'Mozilla/5.0');

        $requestHeaders = [];
        foreach ($streamHeaders as $k => $v) {
            if (strcasecmp($k, 'User-Agent') === 0) { continue; }
            $requestHeaders[] = "{$k}: {$v}";
        }
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '') { $requestHeaders[] = 'Range: ' . str_replace(["\r", "\n"], '', $range); }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $isSegment = in_array($ext, ['ts', 'm4s', 'mp4', 'aac', 'mp3', 'vtt', 'webvtt', 'jpg', 'png', 'key'], true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $requestHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTP_VERSION   => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY    => true,
            CURLOPT_BUFFERSIZE     => 262144,
        ]);

        if (!$isSegment) {
            $body   = curl_exec($ch);
            $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ctype  = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
            $effUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            if ($body === false || $code >= 400) { self::fail($code ?: 502, 'upstream error'); }
            $text = (string) $body;

            if (str_contains($text, '#EXTM3U') || str_contains($ctype, 'mpegurl')) {
                header('Content-Type: application/vnd.apple.mpegurl');
                header('Cache-Control: no-cache, no-store');
                header('Access-Control-Allow-Origin: *');
                echo self::rewritePlaylist($text, $effUrl, $h, $secret);
                return;
            }
            if ($ctype !== '') { header('Content-Type: ' . $ctype); }
            header('Access-Control-Allow-Origin: *');
            echo $text;
            return;
        }

        // stream a segment straight through
        $sent = false;
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
            $trim = trim($header);
            if ($trim !== '') {
                $low = strtolower($trim);
                foreach (['content-type', 'content-length', 'content-range', 'accept-ranges'] as $a) {
                    if (str_starts_with($low, $a . ':')) { header($trim); }
                }
            }
            return strlen($header);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$sent) {
            if (!$sent) {
                if ((int) curl_getinfo($ch, CURLINFO_HTTP_CODE) === 206) { http_response_code(206); }
                header('Access-Control-Allow-Origin: *');
                $sent = true;
            }
            echo $chunk;
            flush();
            return strlen($chunk);
        });
        curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($err !== 0 && !$sent) { self::fail(502, 'stream error'); }
    }

    private static function fail(int $code, string $msg): void
    {
        http_response_code($code ?: 502);
        header('Content-Type: text/plain; charset=utf-8');
        echo $msg;
        exit;
    }

    /** Rewrite every URL in an HLS playlist to pass back through this proxy. */
    private static function rewritePlaylist(string $playlist, string $baseUrl, string $h, string $secret): string
    {
        $base  = preg_replace('#[^/]*$#', '', $baseUrl);
        $lines = preg_split('/\r\n|\r|\n/', $playlist) ?: [];
        $out   = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') { $out[] = $line; continue; }
            if (str_starts_with($trim, '#')) {
                if (str_contains($trim, 'URI="')) {
                    $line = preg_replace_callback('/URI="([^"]+)"/', function ($m) use ($base, $h, $secret) {
                        return 'URI="' . self::proxify(self::absolutize($m[1], $base), $h, $secret) . '"';
                    }, $line);
                }
                $out[] = $line;
                continue;
            }
            $out[] = self::proxify(self::absolutize($trim, $base), $h, $secret);
        }
        return implode("\n", $out);
    }

    private static function absolutize(string $url, string $base): string
    {
        if (preg_match('#^https?://#i', $url)) { return $url; }
        if (str_starts_with($url, '//')) { return 'https:' . $url; }
        if (str_starts_with($url, '/')) {
            $p = parse_url($base);
            $authority = ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
            return ($p['scheme'] ?? 'https') . '://' . $authority . $url;
        }
        return $base . $url;
    }

    private static function proxify(string $url, string $h, string $secret): string
    {
        $params = ['url' => $url];
        if ($h !== '') { $params['h'] = $h; }
        ksort($params);
        $params['sig'] = hash_hmac('sha256', http_build_query($params), $secret);
        // Keep the same entry point the request came in on (/stream or
        // /api/stream.php) so playlists stay on the working path.
        $base = defined('YACINE_PROXY_PATH') ? YACINE_PROXY_PATH : '/api/stream.php';
        return $base . '?' . http_build_query($params);
    }
}
