<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Cinema content policy — admin-controlled visibility, app-only access and
 * age ratings for movies & series.
 *
 * Storage (storage/settings/cinema.json via Settings):
 *   {
 *     "items": {
 *       "movie:603": {"access":"all|app|off","rating":"g|13|16|18",
 *                      "title":"…","poster":"/abc.jpg","year":"1999","at":"…"},
 *       "tv:1399":  {…}
 *     },
 *     "block18": {"global":false,"web":false,"app":false}
 *   }
 *
 * Untouched titles default to access=all / rating=g, so the catalogue keeps
 * working exactly as before until an admin overrides something.
 */
final class CinemaPolicy
{
    public const ACCESS  = ['all', 'app', 'off'];
    public const RATINGS = ['g', '13', '16', '18'];

    private static ?array $memo = null;

    private static function data(): array
    {
        if (self::$memo !== null) return self::$memo;
        $d = Settings::get('cinema', []);
        if (!is_array($d)) $d = [];
        $d['items']   = is_array($d['items'] ?? null) ? $d['items'] : [];
        $d['block18'] = is_array($d['block18'] ?? null) ? $d['block18'] : [];
        $d['modes']   = is_array($d['modes'] ?? null) ? $d['modes'] : [];
        return self::$memo = $d;
    }

    private static function persist(array $d): void
    {
        self::$memo = $d;
        Settings::set('cinema', $d);
    }

    /** Normalized key: "movie:603" / "tv:1399". */
    public static function key(string $type, int $id): string
    {
        return ($type === 'tv' ? 'tv' : 'movie') . ':' . $id;
    }

    /** All managed items (key => row). */
    public static function items(): array
    {
        return self::data()['items'];
    }

    /** Policy row for one title (defaults for unmanaged titles). */
    public static function itemFor(string $type, int $id): array
    {
        $row = self::data()['items'][self::key($type, $id)] ?? [];
        return [
            'access' => in_array($row['access'] ?? '', self::ACCESS, true) ? $row['access'] : 'all',
            'rating' => in_array((string)($row['rating'] ?? ''), self::RATINGS, true) ? (string)$row['rating'] : 'g',
            'download_url' => self::safeDownloadUrl((string)($row['download_url'] ?? '')),
        ];
    }

    /**
     * SECTION-WIDE display modes: one switch per catalogue.
     *   all → website + app (default) · app → app only · off → disabled.
     * Section modes only gate the PLAYER — pages, titles, descriptions,
     * metadata, structured data and internal links stay exactly as before
     * (SEO untouched), so nothing is ever hidden from listings by a mode.
     * @return array{movie:string, tv:string}
     */
    public static function modes(): array
    {
        $m = self::data()['modes'];
        return [
            'movie' => in_array($m['movie'] ?? '', self::ACCESS, true) ? $m['movie'] : 'all',
            'tv'    => in_array($m['tv'] ?? '', self::ACCESS, true) ? $m['tv'] : 'all',
        ];
    }

    public static function saveMode(string $type, string $mode): void
    {
        if (!in_array($mode, self::ACCESS, true)) return;
        $d = self::data();
        $d['modes'][$type === 'tv' ? 'tv' : 'movie'] = $mode;
        self::persist($d);
    }

    /** 18+ blocking switches. */
    public static function block18(): array
    {
        $b = self::data()['block18'];
        return [
            'global' => !empty($b['global']),
            'web'    => !empty($b['web']),
            'app'    => !empty($b['app']),
        ];
    }

    public static function saveBlock18(array $b): void
    {
        $d = self::data();
        $d['block18'] = [
            'global' => !empty($b['global']),
            'web'    => !empty($b['web']),
            'app'    => !empty($b['app']),
        ];
        self::persist($d);
    }

    /**
     * Save/patch one title's policy. $meta (title/poster/year) is recorded so
     * the admin list can render the catalogue without re-querying TMDB.
     */
    public static function set(string $type, int $id, array $patch, array $meta = []): void
    {
        if ($id < 1) return;
        $d = self::data();
        $key = self::key($type, $id);
        $row = is_array($d['items'][$key] ?? null) ? $d['items'][$key] : [];
        if (isset($patch['access']) && in_array($patch['access'], self::ACCESS, true)) $row['access'] = $patch['access'];
        if (isset($patch['rating']) && in_array((string)$patch['rating'], self::RATINGS, true)) $row['rating'] = (string)$patch['rating'];
        if (array_key_exists('download_url', $patch)) {
            $row['download_url'] = self::safeDownloadUrl((string)$patch['download_url']);
        }
        foreach (['title', 'poster', 'year'] as $mk) {
            if (!empty($meta[$mk])) $row[$mk] = mb_substr((string)$meta[$mk], 0, 200);
        }
        $row['at'] = date('c');
        // A fully-default row is dead weight — drop it to keep the file lean.
        if (($row['access'] ?? 'all') === 'all' && ($row['rating'] ?? 'g') === 'g'
            && ($row['download_url'] ?? '') === '' && count($d['items']) > 400) {
            unset($d['items'][$key]);
        } else {
            $d['items'][$key] = $row;
        }
        self::persist($d);
    }

    /** Bulk access change for a list of keys ("movie:1","tv:2",…). */
    public static function bulkAccess(array $keys, string $access): int
    {
        if (!in_array($access, self::ACCESS, true)) return 0;
        $d = self::data();
        $n = 0;
        foreach ($keys as $key) {
            if (!preg_match('/^(movie|tv):(\d{1,12})$/', (string)$key, $m)) continue;
            $row = is_array($d['items'][$key] ?? null) ? $d['items'][$key] : [];
            $row['access'] = $access;
            $row['at'] = date('c');
            $d['items'][$key] = $row;
            $n++;
        }
        if ($n) self::persist($d);
        return $n;
    }

    public static function remove(string $key): void
    {
        $d = self::data();
        unset($d['items'][$key]);
        self::persist($d);
    }

    /**
     * Final access decision for one title in the CURRENT request context
     * (website vs official Android app, per is_tofix_app()).
     *
     * @return array{visible:bool, playable:bool, locked:bool, rating:string,
     *               reason:string} reason: ''|'disabled'|'app_only'|'age'
     */
    public static function decision(string $type, int $id): array
    {
        $p = self::itemFor($type, $id);
        $isApp = is_tofix_app();
        $out = ['visible' => true, 'playable' => true, 'locked' => false,
                'rating' => $p['rating'], 'reason' => ''];

        if ($p['access'] === 'off') {
            return ['visible' => false, 'playable' => false, 'locked' => false,
                    'rating' => $p['rating'], 'reason' => 'disabled'];
        }
        if ($p['rating'] === '18') {
            $b = self::block18();
            if ($b['global'] || (!$isApp && $b['web']) || ($isApp && $b['app'])) {
                return ['visible' => false, 'playable' => false, 'locked' => false,
                        'rating' => $p['rating'], 'reason' => 'age'];
            }
        }
        if ($p['access'] === 'app' && !$isApp) {
            // Stays visible (SEO + discovery) but plays only inside the app.
            $out['playable'] = false;
            $out['locked'] = true;
            $out['reason'] = 'app_only';
        }

        // Section-wide mode on top of the per-title policy. Never touches
        // `visible` — the page, its metadata and its listing entries remain
        // fully crawlable; only the player is withheld.
        if ($out['playable']) {
            $mode = self::modes()[$type === 'tv' ? 'tv' : 'movie'];
            if ($mode === 'off') {
                $out['playable'] = false;
                $out['reason'] = 'disabled';
            } elseif ($mode === 'app' && !$isApp) {
                $out['playable'] = false;
                $out['locked'] = true;
                $out['reason'] = 'app_only';
            }
        }
        return $out;
    }

    /**
     * Strip titles hidden in the current context from a TMDB result list.
     * Used on hubs / rows / browse / search so disabled content disappears
     * everywhere without touching the upstream payloads.
     */
    public static function filterList(array $items, ?string $type = null): array
    {
        if (!self::data()['items'] && !self::block18()['global'] && !self::block18()['web'] && !self::block18()['app']) {
            return $items; // fast path: nothing managed
        }
        return array_values(array_filter($items, static function ($it) use ($type) {
            if (!is_array($it) || empty($it['id'])) return true;
            $t = $type ?? Tmdb::typeOf($it);
            if ($t !== 'movie' && $t !== 'tv') return true;
            return self::decision($t, (int)$it['id'])['visible'];
        }));
    }

    /** Display label for a rating code. */
    public static function ratingLabel(string $rating): string
    {
        return match ($rating) {
            '13' => '+13',
            '16' => '+16',
            '18' => '+18',
            default => Lang::current() === 'ar' ? 'عام' : 'G',
        };
    }

    private static function safeDownloadUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
