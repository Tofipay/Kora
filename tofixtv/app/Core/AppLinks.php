<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Android-app match links (admin-managed) — the "روابط مباريات التطبيق" tab.
 *
 * Maps a match id to the value the Android app should open. The value may be
 * a normal http(s) URL (e.g. http://ver3.yacinelive.com/api/channel/1473) OR
 * any opaque string / encrypted token — it is stored and returned EXACTLY as
 * entered (no URL validation, no character stripping of +, /, = …). Used ONLY
 * when the request comes from the Android app (User-Agent: com.tofixtv.app);
 * the normal website behaviour is completely untouched.
 *
 * storage/settings/app_links.json:
 *   { "<matchId>": { "url": "http://… | <token>", "title": "…", "updated_at": "…" } }
 */
final class AppLinks
{
    private static ?array $all = null;

    private static function load(): array
    {
        if (self::$all === null) {
            $d = Settings::get('app_links', []);
            self::$all = is_array($d) ? $d : [];
        }
        return self::$all;
    }

    /**
     * Direct app value stored for a match ('' when none). Returned EXACTLY as
     * stored — may be an http(s) URL or an opaque encrypted token. No URL
     * validation and no character filtering are applied.
     */
    public static function forMatch(int $matchId): string
    {
        $e = self::load()[(string)$matchId] ?? null;
        return is_array($e) ? (string)($e['url'] ?? '') : '';
    }

    /**
     * The URL the Android app's blue "شاهد المباراة الآن" button should open.
     * Priority: 1) direct per-match link, 2) first link auto-pulled from the
     * app channel library via the match's broadcasting channels.
     * Returns '' when neither source has a link (→ no app button).
     */
    public static function resolveForMatch(int $matchId): string
    {
        $direct = self::forMatch($matchId);
        if ($direct !== '') return $direct;
        $urls = AppChannels::urlsForMatch($matchId);
        return $urls[0] ?? '';
    }

    /**
     * All candidate app URLs for a match (direct link first, then the
     * channel-library links). Deduplicated, order preserved.
     * @return array<int,string>
     */
    public static function allForMatch(int $matchId): array
    {
        $urls = [];
        $direct = self::forMatch($matchId);
        if ($direct !== '') $urls[] = $direct;
        foreach (AppChannels::urlsForMatch($matchId) as $u) {
            if (!in_array($u, $urls, true)) $urls[] = $u;
        }
        return $urls;
    }

    /* ---------------- Admin persistence ---------------- */

    /** Save (or clear when $url is '') the app link for one match. */
    public static function save(int $matchId, string $url, string $title = ''): bool
    {
        $all = self::load();
        $url = trim($url);
        if ($url === '') {
            unset($all[(string)$matchId]);
        } else {
            $all[(string)$matchId] = [
                'url'        => $url,
                'title'      => trim($title),
                'updated_at' => date('c'),
            ];
        }
        self::$all = $all;
        return Settings::set('app_links', $all);
    }

    /** @param array<int|string,string> $map matchId => url ('' clears) */
    public static function saveMany(array $map, array $titles = []): bool
    {
        $all = self::load();
        foreach ($map as $mid => $url) {
            $mid = (int)$mid;
            if ($mid < 1) continue;
            $url = trim((string)$url);
            if ($url === '') {
                unset($all[(string)$mid]);
            } else {
                $all[(string)$mid] = [
                    'url'        => $url,
                    'title'      => trim((string)($titles[$mid] ?? ($all[(string)$mid]['title'] ?? ''))),
                    'updated_at' => date('c'),
                ];
            }
        }
        self::$all = $all;
        return Settings::set('app_links', $all);
    }

    /** Match ids that have a direct app link (for the admin list). */
    public static function configuredIds(): array
    {
        return array_map('intval', array_keys(self::load()));
    }

    /** Stored title for a configured match (admin list fallback). */
    public static function titleOf(int $matchId): string
    {
        $e = self::load()[(string)$matchId] ?? null;
        return is_array($e) ? trim((string)($e['title'] ?? '')) : '';
    }
}
