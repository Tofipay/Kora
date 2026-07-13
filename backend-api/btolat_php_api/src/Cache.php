<?php

declare(strict_types=1);

namespace BtolatApi;

final class Cache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttl = Config::CACHE_TTL
    ) {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('تعذر إنشاء مجلد التخزين المؤقت.');
        }
    }

    /**
     * @param callable(): string $resolver
     * @return array{value: string, cached: bool}
     */
    public function remember(string $key, callable $resolver, bool $refresh = false): array
    {
        $path = $this->path($key);

        if (!$refresh && is_file($path) && (time() - (int) filemtime($path)) < $this->ttl) {
            $value = file_get_contents($path);
            if ($value !== false) {
                return ['value' => $value, 'cached' => true];
            }
        }

        $value = $resolver();
        $temporary = $path . '.' . bin2hex(random_bytes(5)) . '.tmp';

        if (file_put_contents($temporary, $value, LOCK_EX) !== false) {
            @chmod($temporary, 0664);
            @rename($temporary, $path);
        }

        return ['value' => $value, 'cached' => false];
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . hash('sha256', $key)
            . '.cache';
    }
}
