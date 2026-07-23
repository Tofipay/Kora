<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Persistent entity registry (teams + players).
 *
 * Team and player detail pages must not depend on a team happening to have a
 * fixture inside the scanned date window (that was the root cause of
 * /team/arsenal-9825 → 404). Every team and player the site sees anywhere —
 * fixtures, standings, scorers, lineups, events — is recorded here by id, so
 * any entity seen once is resolvable forever after.
 *
 * Writes are batched: entries accumulate in memory and flush once per request
 * on shutdown, keyed per language.
 */
final class Registry
{
    private static array $teams = [];
    private static array $players = [];
    private static array $news = [];
    private static bool  $loaded = false;
    private static bool  $dirty  = false;

    private const MAX = 40000; // hard cap per map

    private static function file(string $which): string
    {
        return SETTINGS_DIR . '/registry_' . $which . '_' . Lang::current() . '.json';
    }

    private static function load(): void
    {
        if (self::$loaded) return;
        self::$loaded = true;
        foreach (['teams', 'players', 'news'] as $which) {
            $f = self::file($which);
            if (is_file($f)) {
                $d = json_decode((string)file_get_contents($f), true);
                if (is_array($d)) {
                    // One-time sanitize: drop any entries poisoned by the old
                    // anti-bot placeholder so they can be re-learned as real.
                    foreach ($d as $id => $row) {
                        $label = is_array($row) ? (string)($row['name'] ?? $row['title'] ?? '') : '';
                        if (is_blocked_text($label)) {
                            unset($d[$id]);
                            self::$dirty = true;
                        }
                    }
                    self::${$which} = $d;
                }
            }
        }
        register_shutdown_function([self::class, 'flush']);
    }

    /* ---------------- Teams ---------------- */

    public static function recordTeam($team, array $league = []): void
    {
        if (!is_array($team)) return;
        $id = (int)($team['row_id'] ?? $team['team_id'] ?? $team['id'] ?? 0);
        if ($id <= 0) return;
        self::load();
        $name  = team_name($team, '');
        // Never persist an anti-bot placeholder name — it would poison the
        // registry long after the real data returns.
        if (is_blocked_text($name)) return;
        $image = $team['image'] ?? $team['logo'] ?? null;
        $cur = self::$teams[$id] ?? [];
        $next = [
            'id'    => $id,
            'name'  => $name !== '' && $name !== '—' ? $name : ($cur['name'] ?? ''),
            'image' => $image ?: ($cur['image'] ?? null),
            'wr'    => (int)($team['world_ranking'] ?? ($cur['wr'] ?? 0)),
            'lid'   => (int)($league['url_id'] ?? ($cur['lid'] ?? 0)),
            'lname' => (string)($league['title'] ?? ($cur['lname'] ?? '')),
        ];
        if ($next !== $cur) { self::$teams[$id] = $next; self::$dirty = true; }
    }

    public static function team(int $id): ?array
    {
        self::load();
        return self::$teams[$id] ?? self::crossLang('teams', $id);
    }

    /**
     * Identity is language-independent: a team/player seen in one language is
     * a valid entity in the other. Fall back to the other language's registry
     * (with its name) so switching languages never 404s a known entity — the
     * localized endpoint fills in the correct name on the live server.
     */
    private static function crossLang(string $which, int $id): ?array
    {
        $other = Lang::current() === 'ar' ? 'en' : 'ar';
        $f = SETTINGS_DIR . '/registry_' . $which . '_' . $other . '.json';
        if (!is_file($f)) return null;
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) && isset($d[$id]) ? $d[$id] : null;
    }

    /* ---------------- Players ---------------- */

    public static function recordPlayer($p, array $extra = []): void
    {
        if (!is_array($p)) return;
        $id = (int)($p['id'] ?? $p['row_id'] ?? $p['player_id'] ?? 0);
        if ($id <= 0) return;
        self::load();
        $name = player_label($p, '');
        if (is_blocked_text($name)) return;
        $cur = self::$players[$id] ?? [];
        $next = [
            'id'      => $id,
            'name'    => $name !== '' ? $name : ($cur['name'] ?? ''),
            'full'    => (string)($p['full_title'] ?? ($cur['full'] ?? '')),
            'image'   => ($p['image'] ?? null) ?: ($cur['image'] ?? null),
            'number'  => (int)($p['pn'] ?? $p['player_number'] ?? ($cur['number'] ?? 0)),
            'country' => (int)(is_numeric($p['country'] ?? null) ? $p['country'] : ($cur['country'] ?? 0)),
            'team'    => (string)($p['team_name'] ?? ($extra['team'] ?? ($cur['team'] ?? ''))),
            'tid'     => (int)($p['team_id'] ?? ($extra['tid'] ?? ($cur['tid'] ?? 0))),
            'lid'     => (int)($extra['lid'] ?? ($cur['lid'] ?? 0)),
            'pos'     => (string)(($p['position'] ?? '') ?: ($cur['pos'] ?? '')),
        ];
        if ($next !== $cur) { self::$players[$id] = $next; self::$dirty = true; }
    }

    public static function player(int $id): ?array
    {
        self::load();
        return self::$players[$id] ?? self::crossLang('players', $id);
    }

    /* ---------------- News ---------------- */

    /**
     * Remember a news list item by id, so the article page can always render
     * the real headline/image/excerpt even when news_detail is blocked or the
     * item has scrolled out of the latest feed. Stores only lightweight fields.
     */
    public static function recordNews($item): void
    {
        if (!is_array($item)) return;
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) return;
        $title = (string)($item['title'] ?? '');
        if ($title === '' || is_blocked_text($title)) return;
        self::load();
        $cur = self::$news[$id] ?? [];
        $next = [
            'id'         => $id,
            'title'      => $title,
            'image'      => ($item['image'] ?? null) ?: ($cur['image'] ?? null),
            'news_desc'  => (string)($item['news_desc'] ?? $item['description'] ?? ($cur['news_desc'] ?? '')),
            'created_at' => (string)($item['created_at'] ?? ($cur['created_at'] ?? '')),
            'slug'       => (string)($item['slug'] ?? ($cur['slug'] ?? '')),
            'link'       => (string)($item['link'] ?? $item['url'] ?? ($cur['link'] ?? '')),
        ];
        if ($next !== $cur) { self::$news[$id] = $next; self::$dirty = true; }
    }

    public static function news(int $id): array
    {
        self::load();
        $hit = self::$news[$id] ?? self::crossLang('news', $id);
        return is_array($hit) ? $hit : [];
    }

    /* ---------------- Persistence ---------------- */

    public static function flush(): void
    {
        if (!self::$dirty) return;
        self::$dirty = false;
        if (!is_dir(SETTINGS_DIR)) @mkdir(SETTINGS_DIR, 0755, true);
        foreach (['teams', 'players', 'news'] as $which) {
            $map = self::${$which};
            if (count($map) > self::MAX) $map = array_slice($map, -self::MAX, null, true);
            @file_put_contents(self::file($which), json_encode($map, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }
}
