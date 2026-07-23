<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Client for www.ysscores.com — data that has no JSON API:
 *
 *  - get_players_team?team={id}  → real squad {G,D,M,F,coach} (verified JSON)
 *  - player profile page         → age / height / weight / nationality / foot
 *                                  (HTML page, parsed text-side, cached long)
 *
 * Same disk cache + stale fallback + negative caching as the API client.
 */
final class Www
{
    private static function base(): string
    {
        return WWW_BASES[Lang::current()] ?? WWW_BASES['ar'];
    }

    private static function fetch(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => 'gzip',
            // Browser UA for the HTML site, PLUS the app identity headers so
            // the AJAX endpoints (squad, etc.) aren't served the anti-bot
            // placeholder either. app-version etc. come from config (one place).
            CURLOPT_HTTPHEADER     => array_merge([
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                'Accept-Language: ' . (Lang::current() === 'ar' ? 'ar,en;q=0.8' : 'en,ar;q=0.8'),
                'app-lang: ' . (Lang::current() === 'en' ? 'en' : 'ar'),
                'app-version: ' . API_APP_VERSION,
                'app-versionname: ' . API_APP_VERSIONNAME,
                'app-platform: ' . API_APP_PLATFORM,
                'timezone: ' . API_TIMEZONE,
            ], $headers),
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) return null;
        return (string)$body;
    }

    /** Cached GET returning decoded JSON (for the site's AJAX endpoints). */
    private static function getJson(string $url, int $ttl): array
    {
        $cached = Cache::get($url, $ttl);
        if ($cached !== null) return is_array($cached) ? $cached : [];
        if (Cache::get($url . '#fail', 60) !== null) {
            $stale = Cache::stale($url);
            return is_array($stale) ? $stale : [];
        }
        $body = self::fetch($url, ['Accept: application/json, text/javascript, */*', 'X-Requested-With: XMLHttpRequest']);
        if ($body !== null && !is_blocked_text($body)) {
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                Cache::set($url, $data);
                return $data;
            }
        }
        Cache::set($url . '#fail', 1);
        $stale = Cache::stale($url);
        return is_array($stale) ? $stale : [];
    }

    /* ================= Team squad ================= */

    /**
     * Real squad from get_players_team: {G, D, M, F, coach}.
     * Player: {row_id, title, player_number, image (FULL cdn url, /player/48),
     *          position (localized string), link (canonical player page)}.
     * Normalized to: [line => [players]], plus 'coach'.
     */
    public static function teamSquad(int $teamId): array
    {
        $url = self::base() . '/get_players_team?team=' . $teamId;
        $d = self::getJson($url, CACHE_TTL_LEAGUES);
        if (!$d) return [];

        $out = ['G' => [], 'D' => [], 'M' => [], 'F' => [], 'coach' => null];
        foreach (['G', 'D', 'M', 'F'] as $line) {
            $grp = $d[$line] ?? [];
            if (!is_array($grp)) continue;
            foreach ($grp as $p) {
                if (!is_array($p) || empty($p['title'])) continue;
                $player = [
                    'row_id'        => (int)($p['row_id'] ?? 0),
                    'title'         => (string)$p['title'],
                    'player_number' => (int)($p['player_number'] ?? 0),
                    'image'         => $p['image'] ?? null, // full URL — media_url() proxies it
                    'position_name' => (string)($p['position'] ?? ''),
                    'link'          => (string)($p['link'] ?? ''),
                ];
                $out[$line][] = $player;
                Registry::recordPlayer([
                    'id' => $player['row_id'], 'title' => $player['title'],
                    'pn' => $player['player_number'],
                    'image' => self::cdnFile((string)($p['image'] ?? '')),
                    'position' => $player['position_name'],
                ], ['tid' => $teamId]);
            }
        }
        if (is_array($d['coach'] ?? null) && !empty($d['coach']['title'])) {
            $out['coach'] = [
                'id'    => (int)($d['coach']['id'] ?? 0),
                'title' => (string)$d['coach']['title'],
                'image' => $d['coach']['image'] ?? null,
            ];
        }
        return ($out['G'] || $out['D'] || $out['M'] || $out['F']) ? $out : [];
    }

    /** Extract the bare filename from a full imgs.ysscores.com URL. */
    public static function cdnFile(string $url): ?string
    {
        if ($url === '') return null;
        $p = parse_url($url, PHP_URL_PATH);
        return $p ? basename($p) : null;
    }

    /* ================= Player profile (HTML page) ================= */

    /**
     * Age / height / weight / nationality / preferred foot from the player
     * page — the only source for these (no JSON endpoint exists upstream).
     * Parsed from the page TEXT (label → nearest value), so it tolerates
     * markup changes; every field is optional and fails soft.
     */
    public static function playerProfile(int $playerId): array
    {
        $url = self::base() . '/player/' . $playerId . '/x';
        $cacheKey = 'www_player_v1_' . Lang::current() . '_' . $playerId;
        $cached = Cache::get($cacheKey, 7 * 24 * 3600);
        if ($cached !== null) return is_array($cached) ? $cached : [];
        if (Cache::get($cacheKey . '#fail', 300) !== null) return [];

        $html = self::fetch($url, ['Accept: text/html']);
        if ($html === null) {
            Cache::set($cacheKey . '#fail', 1);
            return [];
        }

        $out = self::parsePlayerHtml($html);
        Cache::set($cacheKey, $out);
        return $out;
    }

    /**
     * FULL player profile: vitals + statistics broken down per competition +
     * transfer history — parsed from the ysscores player page with the same
     * DOM structure the reference get_player_data.php scraper targets.
     *
     * Returns the canonical shape:
     *   name, image, description,
     *   profile:      { position, age, weight, height, foot }
     *   statistics:   { "<competition name>": { appearances, goals,
     *                    disallowed_goals, man_of_the_match, yellow_cards,
     *                    red_cards, assists, penalties_scored,
     *                    penalties_missed, started, substitute,
     *                    total_team_matches } }
     *   transfers:    [ { date_from, team_from, date_to, team_to, type } ]
     * Every field fails soft; a scrape miss yields [].
     */
    public static function playerFull(int $playerId, string $slug = ''): array
    {
        $path = $playerId . '/' . ($slug !== '' ? rawurlencode($slug) : 'x');
        $url = self::base() . '/player/' . $path;
        $cacheKey = 'www_playerfull_v1_' . Lang::current() . '_' . $playerId;
        $cached = Cache::get($cacheKey, 7 * 24 * 3600);
        if ($cached !== null) return is_array($cached) ? $cached : [];
        if (Cache::get($cacheKey . '#fail', 300) !== null) return [];

        $html = self::fetch($url, ['Accept: text/html']);
        if ($html === null) {
            Cache::set($cacheKey . '#fail', 1);
            return [];
        }

        $out = self::parsePlayerFull($html);
        // Reject a blocked profile (the h1 name becomes an anti-bot placeholder).
        if (is_blocked_text($out['name'] ?? '')) {
            Cache::set($cacheKey . '#fail', 1);
            return [];
        }
        // Fold in the raw-HTML image/flag/cover fallbacks from the light parser
        // so identity resolves even when the structured blocks shift.
        $light = self::parsePlayerHtml($html);
        foreach (['image', 'country_image', 'cover'] as $k) {
            if (empty($out[$k]) && !empty($light[$k])) $out[$k] = $light[$k];
        }
        if (empty($out['profile'])) $out['profile'] = array_filter([
            'position' => $light['position_name'] ?? '',
            'age'      => $light['age'] ?? '',
            'weight'   => $light['weight'] ?? '',
            'height'   => $light['height'] ?? '',
            'foot'     => $light['foot'] ?? '',
        ], fn($v) => $v !== '' && $v !== null);

        // Only cache a genuinely useful scrape; otherwise let it retry sooner.
        if ($out['name'] !== '' || $out['statistics'] || $out['profile']) {
            Cache::set($cacheKey, $out);
        } else {
            Cache::set($cacheKey . '#fail', 1);
        }
        return $out;
    }

    /** DOM parse of the full player page (see playerFull for the shape). */
    public static function parsePlayerFull(string $html): array
    {
        $out = [
            'name' => '', 'image' => '', 'description' => '',
            'profile' => [], 'statistics' => [], 'transfers' => [],
        ];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Force UTF-8 so Arabic is not mangled by libxml's Latin-1 default.
        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html)) {
            libxml_clear_errors();
            return $out;
        }
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $str = fn(string $q, $ctx = null) => trim((string)$xp->evaluate('string(' . $q . ')', $ctx));

        // 1. Identity
        $out['name']        = $str('//h1');
        $out['image']       = $str("//div[contains(@class,'player-img')]/img/@src");
        $out['description'] = $str("//p[contains(@class,'player-subline')]");

        // 2. Physical / profile details (labels come in ar OR en).
        $profileMap = [
            'المركز' => 'position', 'position' => 'position',
            'العمر' => 'age', 'age' => 'age',
            'الوزن' => 'weight', 'weight' => 'weight',
            'الطول' => 'height', 'height' => 'height',
            'القدم' => 'foot', 'foot' => 'foot',
        ];
        foreach ($xp->query("//div[contains(@class,'player-info-item')]") as $item) {
            $label = mb_strtolower($str('./p', $item), 'UTF-8');
            $value = $str('./span/b', $item);
            if ($value !== '' && isset($profileMap[$label])) $out['profile'][$profileMap[$label]] = $value;
        }

        // 3. Statistics per competition (stat titles come in ar OR en).
        $statsMap = [
            // Arabic
            'المشاركات' => 'appearances', 'عدد الاهداف' => 'goals', 'هدف ملغي' => 'disallowed_goals',
            'رجل المباراة' => 'man_of_the_match', 'بطاقة صفراء' => 'yellow_cards', 'بطاقة حمراء' => 'red_cards',
            'تمريرات حاسمة' => 'assists', 'ضربة جزاء' => 'penalties_scored', 'ضربة جزاء ضائعة' => 'penalties_missed',
            'أساسي' => 'started', 'بديل' => 'substitute', 'إجمالي مباريات الفريق' => 'total_team_matches',
            // English
            'appearances' => 'appearances', 'goals' => 'goals', 'disallowed goal' => 'disallowed_goals',
            'man of the match' => 'man_of_the_match', 'yellow card' => 'yellow_cards', 'red card' => 'red_cards',
            'assists' => 'assists', 'penalty' => 'penalties_scored', 'missed penalty' => 'penalties_missed',
            'starter' => 'started', 'substitute' => 'substitute', 'total team matches' => 'total_team_matches',
        ];
        $competitions = [];
        foreach ($xp->query("//div[contains(@class,'champ-filters')]/a") as $tab) {
            $target = ltrim($tab->getAttribute('data-target'), '#');
            $name = $str('./span', $tab);
            if ($name !== '' && $target !== '') $competitions[$target] = $name;
        }
        foreach ($competitions as $target => $compName) {
            $container = $xp->query("//div[contains(@class,'tab-content-item') and contains(@class,'" . $target . "')]")->item(0);
            $compStats = [];
            if ($container) {
                foreach (['general-stats-item', 'penalties-stats'] as $cls) {
                    foreach ($xp->query(".//div[contains(@class,'" . $cls . "')]", $container) as $it) {
                        $val   = $str("./div[contains(@class,'head')]/span", $it);
                        $title = mb_strtolower($str("./div[contains(@class,'title')]", $it), 'UTF-8');
                        if ($title !== '' && isset($statsMap[$title])) $compStats[$statsMap[$title]] = $val;
                    }
                }
                foreach ($xp->query(".//div[contains(@class,'goals-stats-item')]", $container) as $it) {
                    $val   = $str('./b', $it);
                    $title = mb_strtolower($str('./span', $it), 'UTF-8');
                    if ($title !== '' && isset($statsMap[$title])) $compStats[$statsMap[$title]] = $val;
                }
                // Total team matches sometimes lives in a penalty-info-item row.
                if (!isset($compStats['total_team_matches'])) {
                    foreach ($xp->query(".//div[contains(@class,'penalty-info-item')]", $container) as $it) {
                        $lbl = mb_strtolower($str('.//span', $it), 'UTF-8');
                        if (str_contains($lbl, 'total team matches') || str_contains($lbl, 'إجمالي مباريات الفريق')) {
                            $v = $str('.//b', $it);
                            if ($v !== '') { $compStats['total_team_matches'] = $v; break; }
                        }
                    }
                }
            }
            $out['statistics'][$compName] = $compStats;
        }

        // 4. Transfer history
        foreach ($xp->query("//div[contains(@class,'transform-history-item')]") as $row) {
            $t = [
                'date_from' => $str(".//div[contains(@class,'club-wrap')][1]/div[contains(@class,'date-to')]", $row),
                'team_from' => $str(".//div[contains(@class,'club-wrap')][1]/a/span", $row),
                'date_to'   => $str(".//div[contains(@class,'club-wrap')][2]/div[contains(@class,'date-to')]", $row),
                'team_to'   => $str(".//div[contains(@class,'club-wrap')][2]/a/span", $row),
                'type'      => $str(".//div[contains(@class,'exch-wrap')]/span", $row),
            ];
            if ($t['team_from'] !== '' || $t['team_to'] !== '') $out['transfers'][] = $t;
        }

        return $out;
    }

    public static function parsePlayerHtml(string $html): array
    {
        // Normalize to plain text lines for label→value matching
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', (string)$text);
        $text = html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', (string)$text);
        $text = preg_replace('/\s*\n\s*/u', "\n", (string)$text);

        $near = function (array $labels, string $pattern) use ($text): ?string {
            foreach ($labels as $label) {
                if (preg_match('/' . preg_quote($label, '/') . '\s*[:\n ]\s*' . $pattern . '/u', (string)$text, $m)) {
                    return trim($m[1]);
                }
                if (preg_match('/' . $pattern . '\s*[:\n ]\s*' . preg_quote($label, '/') . '/u', (string)$text, $m)) {
                    return trim($m[1]);
                }
            }
            return null;
        };

        $out = [];
        if (($v = $near(['العمر', 'Age'], '(\d{2})')) !== null)             $out['age'] = (int)$v;
        if (($v = $near(['الطول', 'Height'], '(\d{3})')) !== null)          $out['height'] = (int)$v;
        if (($v = $near(['الوزن', 'Weight'], '(\d{2,3})')) !== null)        $out['weight'] = (int)$v;
        if (($v = $near(['الجنسية', 'Nationality'], '([\p{Arabic}A-Za-z ]{2,30})')) !== null) $out['nationality'] = $v;
        if (($v = $near(['القدم المفضلة', 'Preferred Foot', 'Foot'], '([\p{Arabic}A-Za-z]{2,12})')) !== null) $out['foot'] = $v;
        if (($v = $near(['المركز', 'Position', 'Center'], '([\p{Arabic}A-Za-z ]{2,25})')) !== null) $out['position_name'] = $v;

        // Cover / large photo + flag if present in the raw HTML
        if (preg_match('#imgs\.ysscores\.com/player/cover/([A-Za-z0-9._-]+)#', $html, $m)) $out['cover'] = $m[1];
        if (preg_match('#imgs\.ysscores\.com/(?:country|flags)/\d+/([A-Za-z0-9._-]+)#', $html, $m)) $out['country_image'] = $m[1];
        if (preg_match('#imgs\.ysscores\.com/player/1?\d{2}/([A-Za-z0-9._-]+)#', $html, $m)) $out['image'] = $m[1];

        return $out;
    }
}
