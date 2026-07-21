<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * JSON-file settings store used by the admin panel
 * (branding, seo, theme, homepage sections, notifications, analytics).
 */
final class Settings
{
    private static array $memo = [];

    private static function file(string $group): string
    {
        return SETTINGS_DIR . '/' . preg_replace('/[^a-z0-9_\-]/', '', $group) . '.json';
    }

    public static function get(string $group, $default = null)
    {
        if (array_key_exists($group, self::$memo)) return self::$memo[$group];
        $f = self::file($group);
        if (is_file($f)) {
            $d = json_decode((string)file_get_contents($f), true);
            if (json_last_error() === JSON_ERROR_NONE) return self::$memo[$group] = $d;
        }
        return self::$memo[$group] = $default;
    }

    public static function set(string $group, $data): bool
    {
        if (!is_dir(SETTINGS_DIR)) @mkdir(SETTINGS_DIR, 0755, true);
        self::$memo[$group] = $data;
        return (bool)@file_put_contents(
            self::file($group),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public static function merge(string $group, array $patch): bool
    {
        $cur = self::get($group, []);
        if (!is_array($cur)) $cur = [];
        return self::set($group, array_merge($cur, $patch));
    }

    /* ---- Homepage builder: ordered sections with enable flags ---- */

    public static function homeSections(): array
    {
        $defaults = [
            ['id' => 'hero',        'on' => true],
            ['id' => 'live',        'on' => true],
            ['id' => 'today',       'on' => true],
            ['id' => 'featured',    'on' => true],
            ['id' => 'leagues',     'on' => true],
            ['id' => 'news',        'on' => true],
            ['id' => 'movies',      'on' => true],
            ['id' => 'series',      'on' => true],
            ['id' => 'trending',    'on' => true],
            ['id' => 'highlights',  'on' => true],
            ['id' => 'teams',       'on' => true],
            ['id' => 'standings',   'on' => true],
            ['id' => 'scorers',     'on' => true],
            ['id' => 'stats',       'on' => true],
            ['id' => 'app',         'on' => true],
            ['id' => 'newsletter',  'on' => true],
        ];
        $saved = self::get('homepage', null);
        if (!is_array($saved) || empty($saved['sections'])) return $defaults;
        $out = [];
        $known = array_column($defaults, null, 'id');
        foreach ($saved['sections'] as $s) {
            $id = $s['id'] ?? '';
            if (isset($known[$id])) {
                $out[] = ['id' => $id, 'on' => (bool)($s['on'] ?? true)];
                unset($known[$id]);
            }
        }
        foreach ($known as $rest) $out[] = $rest; // new sections appended
        return $out;
    }

    /* ---- Analytics: light first-party page counter ---- */

    /**
     * Record one page view. Everything captured here is derived truthfully
     * from the current request (no third-party tracker):
     *   - daily totals + per-type totals (existing behaviour, unchanged)
     *   - traffic source (google/facebook/telegram/direct/referral) from Referer
     *   - device OS + browser from the User-Agent
     *   - optional top-entity ranking per type ($label = match/news/video title)
     *
     * @param string $type  section key: match|news|videos|video|league|home…
     * @param string $label optional entity label for the "most viewed" boards
     */
    public static function trackHit(string $type, string $label = ''): void
    {
        $d = self::get('analytics', []);
        if (!is_array($d)) $d = [];
        $day = date('Y-m-d');

        $d['total'] = (int)($d['total'] ?? 0) + 1;
        $d['days'][$day]['total'] = (int)($d['days'][$day]['total'] ?? 0) + 1;
        $d['days'][$day][$type]   = (int)($d['days'][$day][$type] ?? 0) + 1;

        // Unique visitors per day — sessions keyed by an anonymous IP+UA hash.
        // The seen-set is capped so the file can't grow unbounded; beyond the
        // cap new sessions still count views, uniques just stop increasing
        // (truthful undercount rather than a fabricated estimate).
        $sid = substr(md5(($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 10);
        $seen = is_array($d['days'][$day]['uv_seen'] ?? null) ? $d['days'][$day]['uv_seen'] : [];
        if (!isset($seen[$sid]) && count($seen) < 4000) {
            $seen[$sid] = 1;
            $d['days'][$day]['uv_seen'] = $seen;
            $d['days'][$day]['uv'] = (int)($d['days'][$day]['uv'] ?? 0) + 1;
        }
        if (count($d['days']) > 60) {
            ksort($d['days']);
            $d['days'] = array_slice($d['days'], -60, null, true);
            // The per-session set is only needed for TODAY's dedupe.
            foreach ($d['days'] as $dk => &$row) { if ($dk !== $day) unset($row['uv_seen']); }
            unset($row);
        }

        // Traffic source, device, browser and country — cumulative tallies.
        $bump = function (array &$d, string $bucket, string $key): void {
            if ($key === '') return;
            $d[$bucket][$key] = (int)($d[$bucket][$key] ?? 0) + 1;
            if (count($d[$bucket]) > 120) { arsort($d[$bucket]); $d[$bucket] = array_slice($d[$bucket], 0, 60, true); }
        };
        $bump($d, 'sources',  self::detectSource());
        [$os, $browser] = self::detectClient();
        $bump($d, 'devices',  $os);
        $bump($d, 'browsers', $browser);
        $bump($d, 'countries', self::detectCountry());

        // Most-visited pages (clean path, query stripped, capped).
        $pg = (string)(strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '');
        if ($pg !== '' && mb_strlen($pg) <= 120) {
            $pg = rawurldecode($pg);
            $d['pages'][$pg] = (int)($d['pages'][$pg] ?? 0) + 1;
            if (isset($d['pages']) && count($d['pages']) > 400) {
                arsort($d['pages']);
                $d['pages'] = array_slice($d['pages'], 0, 150, true);
            }
        }

        // Top viewed entities per type (capped, keeps the heaviest hitters).
        if ($label !== '') {
            $k = mb_substr($label, 0, 80);
            $d['top'][$type][$k] = (int)($d['top'][$type][$k] ?? 0) + 1;
            if (isset($d['top'][$type]) && count($d['top'][$type]) > 200) {
                arsort($d['top'][$type]);
                $d['top'][$type] = array_slice($d['top'][$type], 0, 50, true);
            }
        }

        // Live "online now" ping ring — last 5 minutes of unique-ish sessions.
        $now = time();
        $sid = substr(md5(($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 10);
        $d['online'][$sid] = $now;
        foreach (($d['online'] ?? []) as $s => $ts) { if ($now - (int)$ts > 300) unset($d['online'][$s]); }

        self::set('analytics', $d);
    }

    /**
     * Visitor country code, truthfully: CDN geo headers when present
     * (Cloudflare CF-IPCountry and common proxy equivalents), otherwise the
     * region of the browser's first Accept-Language tag, else "other".
     */
    private static function detectCountry(): string
    {
        foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEOIP_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY'] as $h) {
            $c = strtoupper(trim((string)($_SERVER[$h] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' && $c !== 'T1') return $c;
        }
        $al = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if (preg_match('/^[a-z]{2,3}-([A-Za-z]{2})/', trim($al), $m)) return strtoupper($m[1]);
        return 'other';
    }

    /** Classify the referrer into a traffic-source bucket. */
    private static function detectSource(): string
    {
        $ref = strtolower((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($ref === '') return 'direct';
        $host = (string)(parse_url($ref, PHP_URL_HOST) ?: '');
        $self = (string)(parse_url(SITE_URL, PHP_URL_HOST) ?: '');
        if ($self !== '' && str_contains($host, $self)) return 'direct'; // internal nav
        if (str_contains($host, 'google') || str_contains($host, 'bing') || str_contains($host, 'yandex')) return 'google';
        if (str_contains($host, 'facebook') || str_contains($host, 'fb.') || str_contains($host, 'instagram')) return 'facebook';
        if (str_contains($host, 't.me') || str_contains($host, 'telegram')) return 'telegram';
        if (str_contains($host, 'twitter') || str_contains($host, 'x.com')) return 'twitter';
        return 'referral';
    }

    /**
     * Parse OS + browser from the User-Agent (order matters: check the more
     * specific tokens first, e.g. Edg before Chrome, SamsungBrowser before Chrome).
     * @return array{0:string,1:string}
     */
    private static function detectClient(): array
    {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $os = 'other';
        if (preg_match('/Android/i', $ua))                       $os = 'android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua))          $os = 'iphone';
        elseif (preg_match('/Windows/i', $ua))                   $os = 'windows';
        elseif (preg_match('/Mac OS X|Macintosh/i', $ua))        $os = 'mac';
        elseif (preg_match('/SmartTV|SMART-TV|Tizen|Web0S|AppleTV/i', $ua)) $os = 'smarttv';
        elseif (preg_match('/Linux/i', $ua))                     $os = 'linux';

        $browser = 'other';
        if (preg_match('/Edg/i', $ua))                           $browser = 'edge';
        elseif (preg_match('/SamsungBrowser/i', $ua))            $browser = 'samsung';
        elseif (preg_match('/Firefox|FxiOS/i', $ua))             $browser = 'firefox';
        elseif (preg_match('/OPR|Opera/i', $ua))                 $browser = 'opera';
        elseif (preg_match('/Chrome|CriOS/i', $ua))              $browser = 'chrome';
        elseif (preg_match('/Safari/i', $ua))                    $browser = 'safari';
        return [$os, $browser];
    }
}
