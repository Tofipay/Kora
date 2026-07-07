<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

/**
 * First-party media proxy: /media/{kind}/{size?}/{file}
 *
 * - Streams upstream CDN images under the site's own domain
 * - Disk cache (storage/cache/media) with long TTL
 * - On-the-fly WebP conversion when the client supports it (GD)
 * - Strong caching headers + ETag/304, CDN ready (Cache-Control: public, immutable)
 */
final class Media
{
    public static function serve(string $path): void
    {
        $path = ltrim($path, '/');

        // Validate: kind[/size]/file — filenames are hash-like tokens upstream
        if (!preg_match('#^([a-z]+)(?:/(\d{2,4}))?/([A-Za-z0-9._\-]+\.(?:png|jpe?g|gif|webp))$#i', $path, $m)) {
            self::fail(404);
        }
        [, $kind, $size, $file] = $m;
        if (!isset(MEDIA_KINDS[$kind])) self::fail(404);
        if ($size !== '' && !in_array($size, MEDIA_KINDS[$kind], true)) self::fail(404);

        // Fallback size to try if the requested render is missing upstream.
        $fb = (string)(MEDIA_FALLBACK_SIZE[$kind] ?? '');
        $fallbackSize = ($size !== '' && $fb !== '' && $fb !== $size) ? $fb : '';

        $upstreamPath = $kind . ($size !== '' ? "/{$size}" : '') . '/' . $file;
        $wantsWebp = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'image/webp')
            && function_exists('imagewebp')
            && !preg_match('/\.(webp|gif)$/i', $file);

        $cacheKey  = md5($upstreamPath);
        $baseFile  = MEDIA_CACHE_DIR . '/' . $cacheKey;
        $metaFile  = $baseFile . '.meta';
        $webpFile  = $baseFile . '.webp';

        if (!is_dir(MEDIA_CACHE_DIR)) @mkdir(MEDIA_CACHE_DIR, 0755, true);

        // Fetch + store when missing/expired.
        //
        // Failure handling is split by cause — a single missing image must
        // NEVER suppress the whole proxy:
        //  - HTTP 404 upstream → per-file negative marker (retry after 6h),
        //    only this file serves the placeholder;
        //  - network/timeout error → global 60s backoff so a real outage
        //    doesn't stack connection timeouts across page loads.
        $downFlag = MEDIA_CACHE_DIR . '/.upstream-down';
        $missFile = $baseFile . '.miss';
        $upstreamDown = is_file($downFlag) && (time() - (int)filemtime($downFlag) < 60);
        $knownMissing = is_file($missFile) && (time() - (int)filemtime($missFile) < 6 * 3600);
        $fresh = is_file($baseFile) && (time() - (int)filemtime($baseFile) < CACHE_TTL_MEDIA);

        if (!$fresh && !$upstreamDown && !$knownMissing) {
            $bin = self::fetch(UPSTREAM_IMG . '/' . $upstreamPath);
            // Requested render missing → retry once at the kind's fallback size.
            if ($bin === 'notfound' && $fallbackSize !== '') {
                $alt = self::fetch(UPSTREAM_IMG . '/' . $kind . '/' . $fallbackSize . '/' . $file);
                if (is_array($alt)) $bin = $alt;
            }
            if (is_array($bin)) {
                @file_put_contents($baseFile, $bin['body'], LOCK_EX);
                @file_put_contents($metaFile, $bin['type'], LOCK_EX);
                @unlink($webpFile); // invalidate converted copy
                @unlink($missFile);
            } elseif ($bin === 'notfound') {
                @touch($missFile);
                if (!is_file($baseFile)) self::fail(404);
            } else { // network error / timeout
                @touch($downFlag);
                if (!is_file($baseFile)) self::fail(404);
            }
        } elseif (!is_file($baseFile)) {
            self::fail(404);
        }

        $type = is_file($metaFile) ? trim((string)file_get_contents($metaFile)) : 'image/png';
        $sendFile = $baseFile;

        if ($wantsWebp) {
            if (!is_file($webpFile)) self::convertWebp($baseFile, $webpFile, $type);
            if (is_file($webpFile) && filesize($webpFile) > 0) {
                $sendFile = $webpFile;
                $type = 'image/webp';
            }
        }

        $etag = '"' . md5($cacheKey . filemtime($sendFile) . $type) . '"';
        header('Cache-Control: public, max-age=604800, s-maxage=2592000, immutable');
        header('Vary: Accept');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $type);
        header('Content-Length: ' . (string)filesize($sendFile));
        readfile($sendFile);
        exit;
    }

    /** @return array{body:string,type:string}|'notfound'|null null = network error */
    private static function fetch(string $url): array|string|null
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => 'gzip',
            // Same official app headers as the data API — some CDN edges apply
            // the identical anti-bot gate to image requests.
            CURLOPT_HTTPHEADER     => api_headers(),
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($body === false || $errno !== 0 || $code === 0) return null;          // transport failure
        if ($code === 404 || $code === 410) return 'notfound';                    // this file only
        if ($code !== 200 || strlen((string)$body) === 0) return null;            // upstream trouble
        if (!str_starts_with($type, 'image/')) {
            // Some CDNs return octet-stream; infer from magic bytes
            $type = self::sniff((string)$body) ?? 'image/png';
        }
        return ['body' => (string)$body, 'type' => $type];
    }

    private static function sniff(string $bin): ?string
    {
        if (str_starts_with($bin, "\x89PNG")) return 'image/png';
        if (str_starts_with($bin, "\xFF\xD8")) return 'image/jpeg';
        if (str_starts_with($bin, 'GIF8')) return 'image/gif';
        if (substr($bin, 8, 4) === 'WEBP') return 'image/webp';
        return null;
    }

    private static function convertWebp(string $src, string $dst, string $type): void
    {
        if (!function_exists('imagewebp')) return;
        $img = match ($type) {
            'image/png'  => @imagecreatefrompng($src),
            'image/jpeg' => @imagecreatefromjpeg($src),
            default      => @imagecreatefromstring((string)file_get_contents($src)),
        };
        if (!$img) return;
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        @imagewebp($img, $dst, 82);
        imagedestroy($img);
    }

    private static function fail(int $code): void
    {
        http_response_code($code);
        // Serve the neutral placeholder so broken upstreams never break layouts
        $ph = PUBLIC_DIR . '/assets/img/placeholder.svg';
        if (is_file($ph)) {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=3600');
            readfile($ph);
        }
        exit;
    }
}
