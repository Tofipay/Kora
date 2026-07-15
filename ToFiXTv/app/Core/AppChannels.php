<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Android-app channel library (admin-managed) — INDEPENDENT of the website
 * channel library (ChannelLib). Used ONLY when the request comes from the
 * Android app (User-Agent: com.tofixtv.app); it never affects normal visitors.
 *
 * Each channel has a name and one or more app stream URLs. Multiple URLs may
 * be separated by commas or new lines, e.g.:
 *   http://ver3.yacinelive.com/api/channel/1473?tofi-api&tofiUrlname=beIN Max 1-HD,
 *   http://ver3.yacinelive.com/api/channel/1472?tofi-api&tofiUrlname=beIN Max 1-SD
 *
 * storage/settings/app_channels.json:
 *   { "items": [ { "name": "beIN Max 1", "urls": ["http://…/1473?tofi-api&…"] } ] }
 */
final class AppChannels
{
    private static ?array $items = null;

    /** @return array<int,array{name:string,urls:array<int,string>}> */
    public static function all(): array
    {
        if (self::$items === null) {
            $d = Settings::get('app_channels', []);
            $items = is_array($d['items'] ?? null) ? $d['items'] : [];
            $out = [];
            foreach ($items as $it) {
                if (!is_array($it)) continue;
                $name = trim((string)($it['name'] ?? ''));
                $urls = self::urlsOf($it);
                if ($name !== '' && $urls) $out[] = ['name' => $name, 'urls' => $urls];
            }
            self::$items = $out;
        }
        return self::$items;
    }

    /** @return array<int,string> */
    private static function urlsOf(array $it): array
    {
        $urls = [];
        foreach ((array)($it['urls'] ?? []) as $u) {
            $u = trim((string)$u);
            if ($u !== '' && preg_match('#^https?://#i', $u)) $urls[] = $u;
        }
        return $urls;
    }

    /** Split a raw admin input (URLs separated by commas and/or new lines). */
    public static function splitUrls(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n|,(?=\s*https?:\/\/)/i', $raw) ?: [] as $u) {
            $u = trim($u, " \t,");
            if ($u !== '') $out[] = $u;
        }
        return $out;
    }

    /** Normalize a channel name for fuzzy matching (case/spaces/"sports"). */
    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = str_replace(['sports', 'sport'], '', $s);
        $s = (string)preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    private static function namesMatch(string $broadcast, string $lib): bool
    {
        $a = self::norm($broadcast);
        $b = self::norm($lib);
        if ($a === '' || $b === '') return false;
        if ($a === $b) return true;
        // "beIN SPORTS Max 1" ↔ "beIN Max 1"
        return (mb_strlen($b) >= 4 && str_contains($a, $b))
            || (mb_strlen($a) >= 4 && str_contains($b, $a));
    }

    /** Library entry matching a broadcast channel name, or null. */
    public static function findByName(string $name): ?array
    {
        foreach (self::all() as $c) {
            if (self::namesMatch($name, $c['name'])) return $c;
        }
        return null;
    }

    /**
     * App URLs for a match, auto-pulled from its broadcasting channels: the
     * API's channel list is matched by name against this library and every
     * URL of every matched channel is collected (order preserved).
     * @return array<int,string>
     */
    public static function urlsForMatch(int $matchId): array
    {
        $urls = [];
        foreach (Api::matchChannels($matchId) as $bc) {
            if (!is_array($bc)) continue;
            $chName = trim((string)($bc['channel_name'] ?? ''));
            if ($chName === '') continue;
            $lib = self::findByName($chName);
            if (!$lib) continue;
            foreach ($lib['urls'] as $u) {
                if (!in_array($u, $urls, true)) $urls[] = $u;
            }
        }
        return $urls;
    }

    /* ---------------- Admin persistence ---------------- */

    /** @param array<int,array{name?:string,urls?:array}> $items */
    public static function save(array $items): bool
    {
        $clean = [];
        foreach ($items as $it) {
            $name = trim((string)($it['name'] ?? ''));
            $urls = [];
            foreach ((array)($it['urls'] ?? []) as $u) {
                $u = trim((string)$u);
                if ($u !== '') $urls[] = $u;
            }
            if ($name === '' || !$urls) continue;
            $clean[] = ['name' => $name, 'urls' => array_values($urls)];
        }
        self::$items = null; // reset cache
        return Settings::set('app_channels', ['items' => $clean]);
    }
}
