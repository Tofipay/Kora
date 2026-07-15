<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Per-match streaming configuration (admin managed).
 *
 * storage/settings/streams.json:
 *   { "<matchId>": {
 *       "mode": "internal" | "external",
 *       "external_url": "https://…",           // used when mode = external
 *       "servers": [
 *          { "name":"SSC HD", "url":"https://…/live.m3u8",
 *            "type":"m3u8", "order":1, "active":true }
 *       ]
 *   } }
 */
final class Streams
{
    public const TYPES = ['m3u8', 'mpd', 'ts', 'ism', 'isml', 'rtsp', 'mp4', 'mkv', 'webm', 'auto'];

    private static ?array $all = null;

    private static function load(): array
    {
        if (self::$all === null) {
            $d = Settings::get('streams', []);
            self::$all = is_array($d) ? $d : [];
        }
        return self::$all;
    }

    /** Raw config for a match (or empty). */
    public static function forMatch(int $matchId): array
    {
        $all = self::load();
        $cfg = $all[(string)$matchId] ?? [];
        return is_array($cfg) ? $cfg : [];
    }

    /** Active servers, sorted, with a resolved playable type. */
    public static function servers(int $matchId): array
    {
        $cfg = self::forMatch($matchId);
        $servers = array_values(array_filter(
            $cfg['servers'] ?? [],
            fn($s) => is_array($s) && !empty($s['url']) && !empty($s['active'])
        ));
        usort($servers, fn($a, $b) => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
        foreach ($servers as &$s) {
            $s['type'] = self::resolveType((string)($s['url'] ?? ''), (string)($s['type'] ?? 'auto'));
        }
        unset($s);
        return $servers;
    }

    public static function mode(int $matchId): string
    {
        return (self::forMatch($matchId)['mode'] ?? 'internal') === 'external' ? 'external' : 'internal';
    }

    public static function externalUrl(int $matchId): string
    {
        return (string)(self::forMatch($matchId)['external_url'] ?? '');
    }

    /** Only http/https external links are ever surfaced (blocks javascript:, data:, etc.). */
    private static function isHttpUrl(string $url): bool
    {
        return (bool)preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** Whether a Watch button should show for this match. */
    public static function isWatchable(int $matchId): bool
    {
        if (self::mode($matchId) === 'external') {
            return self::isHttpUrl(self::externalUrl($matchId));
        }
        return count(self::servers($matchId)) > 0;
    }

    /** Where the Watch button points. */
    public static function watchUrl(int $matchId): string
    {
        return self::mode($matchId) === 'external'
            ? self::externalUrl($matchId)
            : path('watch/' . $matchId);
    }

    public static function watchTarget(int $matchId): string
    {
        return self::mode($matchId) === 'external' ? '_blank' : '_self';
    }

    /** Infer the stream container from the URL when set to "auto". */
    public static function resolveType(string $url, string $type): string
    {
        if ($type !== '' && $type !== 'auto') return $type;
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        foreach (['m3u8', 'mpd', 'ism', 'isml', 'mp4', 'mkv', 'webm', 'ts'] as $ext) {
            if (str_ends_with($path, '.' . $ext)) return $ext;
        }
        if (str_starts_with(strtolower($url), 'rtsp:')) return 'rtsp';
        return 'm3u8'; // most live streams are HLS
    }

    /* ---------------- Admin persistence ---------------- */

    public static function save(int $matchId, array $cfg): bool
    {
        $all = self::load();
        $servers = [];
        foreach ($cfg['servers'] ?? [] as $i => $s) {
            $name = trim((string)($s['name'] ?? ''));
            $url  = trim((string)($s['url'] ?? ''));
            if ($url === '' && $name === '') continue;
            $type = in_array($s['type'] ?? 'auto', self::TYPES, true) ? $s['type'] : 'auto';
            $servers[] = [
                'name'   => $name !== '' ? $name : ('Server ' . ($i + 1)),
                'url'    => $url,
                'type'   => $type,
                'order'  => (int)($s['order'] ?? ($i + 1)),
                'active' => !empty($s['active']),
            ];
        }
        usort($servers, fn($a, $b) => $a['order'] <=> $b['order']);

        $entry = [
            'mode'         => ($cfg['mode'] ?? 'internal') === 'external' ? 'external' : 'internal',
            'external_url' => trim((string)($cfg['external_url'] ?? '')),
            'servers'      => $servers,
            'updated_at'   => date('c'),
        ];
        if ($entry['mode'] === 'internal' && $entry['external_url'] === '' && empty($servers)) {
            unset($all[(string)$matchId]);          // nothing configured → drop the entry
        } else {
            $all[(string)$matchId] = $entry;
        }
        self::$all = $all;
        return Settings::set('streams', $all);
    }

    /** Match ids that have any stream configured (for the admin list). */
    public static function configuredIds(): array
    {
        return array_map('intval', array_keys(self::load()));
    }
}
