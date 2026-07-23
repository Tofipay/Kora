<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Channel library (admin-managed).
 *
 * A reusable list of channels, each with a name and one or more stream URLs
 * (Yacine API links and/or plain .m3u8). Matches auto-pull their playable
 * links from here: the API's broadcasting-channels list (channel_name +
 * commentator) is matched by name against this library.
 *
 * storage/settings/channels.json:
 *   { "items": [ { "name": "beIN Max 2", "urls": ["http://…/api/channel/1472","https://…/x.m3u8"] } ] }
 */
final class ChannelLib
{
    private static ?array $items = null;

    /** @return array<int,array{name:string,urls:array<int,string>}> */
    public static function all(): array
    {
        if (self::$items === null) {
            $d = Settings::get('channels', []);
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
        // "beIN SPORTS Max 2" ↔ "beIN Max 2"
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
     * Playable servers for a match, auto-pulled from its broadcasting channels.
     * Each matched channel contributes one server per configured URL, labelled
     * with the channel name and its commentator.
     * @return array<int,array{name:string,url:string,type:string,order:int,active:bool}>
     */
    public static function serversForMatch(int $matchId): array
    {
        $servers = [];
        foreach (Api::matchChannels($matchId) as $bc) {
            if (!is_array($bc)) continue;
            $chName = trim((string)($bc['channel_name'] ?? ''));
            if ($chName === '') continue;
            $lib = self::findByName($chName);
            if (!$lib) continue;

            $commentator = trim((string)($bc['commentator_name'] ?? ''));
            $label = $commentator !== '' ? ($chName . ' — ' . $commentator) : $chName;
            $urls  = $lib['urls'];
            foreach ($urls as $j => $url) {
                $servers[] = [
                    'name'   => count($urls) > 1 ? ($label . ' (' . ($j + 1) . ')') : $label,
                    'url'    => $url,
                    'type'   => 'auto',
                    'order'  => 0,
                    'active' => true,
                ];
            }
        }
        return $servers;
    }

    /** True if this match has at least one broadcast channel we can play. */
    public static function hasMatch(int $matchId): bool
    {
        return self::serversForMatch($matchId) !== [];
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
        return Settings::set('channels', ['items' => $clean]);
    }
}
