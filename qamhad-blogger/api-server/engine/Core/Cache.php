<?php
declare(strict_types=1);

namespace Qamhad\Core;

/**
 * File cache with stale-fallback: when the upstream API fails we serve the
 * last known good payload instead of an empty page.
 */
final class Cache
{
    public static function path(string $key): string
    {
        $ns = defined('CACHE_VERSION') ? CACHE_VERSION . '|' : '';
        return CACHE_DIR . '/' . md5($ns . $key) . '.json';
    }

    /** @return mixed|null */
    public static function get(string $key, int $ttl)
    {
        $file = self::path($key);
        if (is_file($file) && (time() - (int)filemtime($file) < $ttl)) {
            $data = json_decode((string)file_get_contents($file), true);
            if (json_last_error() === JSON_ERROR_NONE) return $data;
        }
        return null;
    }

    /** Last stored value regardless of age (stale fallback). */
    public static function stale(string $key)
    {
        return self::get($key, PHP_INT_MAX);
    }

    public static function set(string $key, $data): void
    {
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::path($key), json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** Remove every cached API payload. Returns number of files removed. */
    public static function flush(): int
    {
        $n = 0;
        foreach (glob(CACHE_DIR . '/*.json') ?: [] as $f) {
            if (@unlink($f)) $n++;
        }
        return $n;
    }

    public static function stats(): array
    {
        $files = glob(CACHE_DIR . '/*.json') ?: [];
        $size = 0;
        foreach ($files as $f) $size += (int)filesize($f);
        $media = glob(MEDIA_CACHE_DIR . '/*') ?: [];
        $msize = 0;
        foreach ($media as $f) $msize += (int)filesize($f);
        return [
            'api_files'   => count($files),
            'api_bytes'   => $size,
            'media_files' => count($media),
            'media_bytes' => $msize,
        ];
    }

    public static function flushMedia(): int
    {
        $n = 0;
        foreach (glob(MEDIA_CACHE_DIR . '/*') ?: [] as $f) {
            if (is_file($f) && @unlink($f)) $n++;
        }
        return $n;
    }
}
