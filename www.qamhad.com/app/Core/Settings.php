<?php
declare(strict_types=1);

namespace Qamhad\Core;

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

    /* ---- Analytics: ultra-light page counter ---- */

    public static function trackHit(string $type): void
    {
        $f = SETTINGS_DIR . '/analytics.json';
        $d = self::get('analytics', []);
        if (!is_array($d)) $d = [];
        $day = date('Y-m-d');
        $d['total'] = (int)($d['total'] ?? 0) + 1;
        $d['days'][$day]['total'] = (int)($d['days'][$day]['total'] ?? 0) + 1;
        $d['days'][$day][$type]  = (int)($d['days'][$day][$type] ?? 0) + 1;
        // keep last 60 days
        if (isset($d['days']) && count($d['days']) > 60) {
            ksort($d['days']);
            $d['days'] = array_slice($d['days'], -60, null, true);
        }
        self::set('analytics', $d);
    }
}
