<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * AI Tool System — the centralized API-access layer of the assistant.
 *
 * Every data source of the platform is exposed as a "tool" (provider
 * method); the intent layer in Ai.php selects tools dynamically from the
 * user's question. Providers NEVER invent data — each one wraps a real
 * platform API and returns normalized card/data arrays.
 *
 * Providers:
 *   MatchProvider   → searchMatches / teamForm / matchGoals   (matches API,
 *                     any date: past seasons, live, future schedules)
 *   LeagueProvider  → standings / topScorers / leagues        (standings,
 *                     scorers, competitions APIs)
 *   NewsProvider    → news                                    (news API)
 *   CinemaProvider  → movies / series (via Ai.php builders + TMDB API)
 *   ChannelProvider → channels                                (channel libs)
 *   SearchProvider  → universal (teams / players / cinema fallback chain)
 */
final class AiTools
{
    /** Registry — tool name → description (for docs and prompt exposure). */
    public const REGISTRY = [
        'matches_by_date' => 'All matches for a given date (past, today or future)',
        'match_search'    => 'Global match search: teams + optional date, across past/live/future',
        'team_form'       => 'Last N results of a team (any season available upstream)',
        'team_schedule'   => 'Upcoming fixtures of a team',
        'match_goals'     => 'Goal scorers and minutes of a specific match',
        'standings'       => 'League standings table',
        'top_scorers'     => 'League top scorers',
        'news'            => 'Latest news, filterable by team/player/keyword',
        'channels'        => 'TV channel libraries',
    ];

    /* ==================== Date / entity detection ==================== */

    /**
     * Parse a date expression out of a (normalized) question.
     * Supports relative words in both languages and explicit dates.
     * @return array{date:?string, rest:string} date = Y-m-d
     */
    public static function extractDate(string $norm): array
    {
        $today = time();
        $map = [
            '/\b(اليوم|النهارده|today|tonight)\b/ui'          => 0,
            '/\b(امس|البارحه|مبارح|yesterday)\b/ui'           => -1,
            '/\b(اول امس|قبل امس)\b/ui'                        => -2,
            '/\b(غدا|بكره|بكرا|tomorrow)\b/ui'                 => 1,
            '/\b(بعد غد|بعد بكره)\b/ui'                        => 2,
        ];
        foreach ($map as $re => $off) {
            if (preg_match($re, $norm)) {
                return ['date' => date('Y-m-d', strtotime(($off >= 0 ? '+' : '') . $off . ' days', $today)),
                        'rest' => trim((string)preg_replace($re, ' ', $norm))];
            }
        }

        // Explicit numeric dates: 2026-07-19 · 19/7/2026 · 19-7-2026.
        if (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $norm, $m)) {
            $d = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            if (strtotime($d)) return ['date' => $d, 'rest' => trim(str_replace($m[0], ' ', $norm))];
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $norm, $m)) {
            $d = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            if (strtotime($d)) return ['date' => $d, 'rest' => trim(str_replace($m[0], ' ', $norm))];
        }

        // Text dates: 19 يوليو 2026 / 19 July 2026 / 19 يوليو, 2026.
        $months = [
            'يناير' => 1, 'جانفي' => 1, 'january' => 1, 'jan' => 1,
            'فبراير' => 2, 'فيفري' => 2, 'february' => 2, 'feb' => 2,
            'مارس' => 3, 'march' => 3, 'mar' => 3,
            'ابريل' => 4, 'أبريل' => 4, 'apr' => 4, 'april' => 4,
            'مايو' => 5, 'may' => 5,
            'يونيو' => 6, 'june' => 6, 'jun' => 6,
            'يوليو' => 7, 'يوليه' => 7, 'july' => 7, 'jul' => 7,
            'اغسطس' => 8, 'أغسطس' => 8, 'august' => 8, 'aug' => 8,
            'سبتمبر' => 9, 'september' => 9, 'sep' => 9,
            'اكتوبر' => 10, 'أكتوبر' => 10, 'october' => 10, 'oct' => 10,
            'نوفمبر' => 11, 'november' => 11, 'nov' => 11,
            'ديسمبر' => 12, 'december' => 12, 'dec' => 12,
        ];
        if (preg_match('/\b(\d{1,2})\s+([\p{L}]+)\s*,?\s*(\d{4})\b/u', $norm, $m)) {
            $monthKey = self::normLower($m[2]);
            foreach ($months as $name => $num) {
                $n = self::normLower($name);
                if ($monthKey === $n || mb_stripos($monthKey, $n) !== false) {
                    $d = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$num, (int)$m[1]);
                    if (strtotime($d)) return ['date' => $d, 'rest' => trim(str_replace($m[0], ' ', $norm))];
                }
            }
        }

        return ['date' => null, 'rest' => $norm];
    }

    /** Team-vs-team tokenizer (attached Arabic waw, ضد, vs, ×, hyphen). */
    public static function teamTokens(string $q): array
    {
        $tokens = preg_split('/\s+(?:ضد|مع|امام|أمام|vs\.?|versus|and|x)\s+|\s+و(?=\S)\s*|\s*[×–]\s*|\s+-\s+/ui', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Strip surrounding punctuation and generic query words. This turns
        // "مباريات إسبانيا" into "إسبانيا" and "آخر 5 مباريات الهلال" into
        // "الهلال" so the upstream team search is not poisoned by chatter.
        $clean = static fn(string $t): string => trim((string)preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $t));
        $stripGeneric = static fn(string $t): string => trim((string)preg_replace(
            '/^(?:مباريات|مباراة|مباراه|نتائج|نتيجة|جدول|مواعيد|موعد|schedule|fixtures|calendar|results?|matches?|games?|match(?:es)?|news|اخبار|خبر)\s+/ui',
            '',
            preg_replace('/\s+(?:مباريات|مباراة|مباراه|نتائج|نتيجة|جدول|مواعيد|موعد|schedule|fixtures|calendar|results?|matches?|games?|match(?:es)?|news|اخبار|خبر)\s*$/ui', '', $t) ?? $t
        ));
        $tokens = array_values(array_filter(array_map(static fn(string $t): string => $stripGeneric($clean($t)), $tokens), fn($t) => mb_strlen($t) >= 2));
        return array_slice($tokens, 0, 3);
    }

    private static function normLower(string $s): string
    {
        return mb_strtolower(str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $s));
    }

    /**
     * Common Arabic → Latin aliases used to improve team/league resolution
     * when the upstream search endpoint is English-biased.
     */
    public static function aliasQueries(string $q): array
    {
        $q = trim($q);
        if ($q === '') return [];

        $map = [
            'اسبانيا' => ['Spain', 'Espana'],
            'إسبانيا' => ['Spain', 'Espana'],
            'الارجنتين' => ['Argentina'],
            'الأرجنتين' => ['Argentina'],
            'فرنسا' => ['France'],
            'المانيا' => ['Germany', 'Deutschland'],
            'ألمانيا' => ['Germany', 'Deutschland'],
            'البرازيل' => ['Brazil'],
            'المغرب' => ['Morocco'],
            'البرتغال' => ['Portugal'],
            'ايطاليا' => ['Italy'],
            'إيطاليا' => ['Italy'],
            'إنجلترا' => ['England'],
            'انجلترا' => ['England'],
            'السعودية' => ['Saudi Arabia', 'Saudi'],
            'المملكة العربية السعودية' => ['Saudi Arabia', 'Saudi'],
            'قطر' => ['Qatar'],
            'الامارات' => ['UAE', 'United Arab Emirates'],
            'الإمارات' => ['UAE', 'United Arab Emirates'],
            'الولايات المتحدة' => ['USA', 'United States'],
            'امريكا' => ['USA', 'United States'],
            'أمريكا' => ['USA', 'United States'],
            'المكسيك' => ['Mexico'],
            'هولندا' => ['Netherlands', 'Holland'],
            'هولندا' => ['Netherlands', 'Holland'],
            'بلجيكا' => ['Belgium'],
            'السنغال' => ['Senegal'],
            'الجزائر' => ['Algeria'],
            'تونس' => ['Tunisia'],
            'مصر' => ['Egypt'],
            'اليابان' => ['Japan'],
            'كوريا' => ['Korea', 'South Korea'],
        ];

        $out = [$q];
        $n = self::normLower($q);
        foreach ($map as $k => $vals) {
            if (mb_stripos($n, self::normLower($k)) !== false) {
                foreach ($vals as $v) $out[] = $v;
            }
        }

        // Also split on team-vs-team wording and expand each side.
        foreach (self::teamTokens($q) as $t) {
            $nt = self::normLower($t);
            foreach ($map as $k => $vals) {
                if (mb_stripos($nt, self::normLower($k)) !== false) {
                    foreach ($vals as $v) $out[] = $v;
                }
            }
        }

        return array_values(array_unique(array_filter($out, fn($s) => mb_strlen(trim((string)$s)) >= 2)));
    }

    private static function nameHits(array $m, array $tokens): int
    {
        $names = self::normLower(team_name(team_of($m, 'home')) . ' ' . team_name(team_of($m, 'away')));
        $hits = 0;
        foreach ($tokens as $t) {
            if ($t !== '' && mb_stripos($names, self::normLower($t)) !== false) $hits++;
        }
        return $hits;
    }

    /* ==================== MatchProvider ==================== */

    /**
     * GLOBAL match search — never limited to today.
     *
     * Strategy (first hit wins):
     *   1. Explicit date → that day's feed, filtered by team tokens.
     *   2. ±3-day window around today (covers "this week" questions).
     *   3. Team history/schedule: resolve the first token to a real team via
     *      the platform search API, then scan its FULL results + fixtures
     *      buckets (any season upstream provides) — this answers
     *      "how did Spain vs Argentina end?" months later and
     *      "when is Al Nassr vs Al Ahli?" for future rounds.
     *
     * @param string $prefer 'past' | 'future' | 'any' (from question phrasing)
     * @return array raw match payloads (renderable via Ai card builders)
     */
    public static function searchMatches(array $tokens, ?string $date, string $prefer = 'any', int $limit = 3): array
    {
        $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => mb_strlen($t) >= 2));
        if ($date !== null) {
            $pool = [];
            foreach (Api::matchesByDate($date) as $m) {
                if (!$tokens || self::nameHits($m, $tokens) >= min(count($tokens), 2)) $pool[] = $m;
                if (!$tokens && count($pool) >= 8) break;
            }
            if ($pool || !$tokens) return array_slice($pool, 0, $tokens ? $limit : 8);
        }
        if (!$tokens) return [];

        // 2) Wider near window.
        $best = [];
        for ($i = -14; $i <= 14; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                $hits = self::nameHits($m, $tokens);
                if ($hits === 0) continue;
                $best[] = ['hits' => $hits, 'dist' => abs($i), 'm' => $m];
            }
        }
        if ($best) {
            usort($best, fn($a, $b) => [$b['hits'], -$a['dist']] <=> [$a['hits'], -$b['dist']]);
            if (count($tokens) >= 2) {
                $both = array_values(array_filter($best, fn($x) => $x['hits'] >= 2));
                if ($both) return array_column(array_slice($both, 0, $limit), 'm');
            } else {
                return array_column(array_slice($best, 0, $limit), 'm');
            }
        }

        // 3) Team-resolution from the whole query first, then each token.
        $teams = [];
        $queries = [];
        foreach (array_merge([implode(' ', $tokens)], $tokens) as $query) {
            foreach (self::aliasQueries($query) as $alias) $queries[] = $alias;
        }
        $queries = array_values(array_unique($queries));
        foreach ($queries as $query) {
            $sr = Api::search($query);
            foreach (array_slice($sr['teams'] ?? [], 0, 3) as $t) {
                if (!is_array($t) || empty($t['row_id'])) continue;
                $teams[(int)$t['row_id']] = $t;
            }
        }
        if (!$teams) {
            $team = self::resolveTeam($tokens[0]);
            if ($team) $teams[(int)$team['row_id']] = $team;
        }
        if (!$teams) return [];

        $pool = [];
        $opponent = $tokens[1] ?? '';
        foreach ($teams as $team) {
            $b = Api::teamMatchesBuckets((int)$team['row_id']);
            $results  = $b['results'];
            $fixtures = $b['fixtures'];
            $filter = function (array $list) use ($opponent): array {
                if ($opponent === '') return $list;
                return array_values(array_filter($list, fn($m) => self::nameHits($m, [$opponent]) >= 1));
            };
            $results  = $filter($results);
            $fixtures = $filter($fixtures);
            $ordered = match ($prefer) {
                'past'   => array_merge($results, $fixtures),
                'future' => array_merge($fixtures, $results),
                default  => array_merge($fixtures ? [$fixtures[0]] : [], $results, array_slice($fixtures, 1)),
            };
            foreach ($ordered as $m) {
                if (!is_array($m) || empty($m['match_id'])) continue;
                $pool[(int)$m['match_id']] = $m;
            }
        }
        if (!$pool) return [];
        $list = array_values($pool);
        usort($list, function (array $a, array $b) use ($tokens, $prefer): int {
            $ah = self::nameHits($a, $tokens);
            $bh = self::nameHits($b, $tokens);
            if ($ah !== $bh) return $bh <=> $ah;
            if ($prefer === 'future') {
                return ((int)($a['match_timestamp'] ?? 0)) <=> ((int)($b['match_timestamp'] ?? 0));
            }
            return ((int)($b['match_timestamp'] ?? 0)) <=> ((int)($a['match_timestamp'] ?? 0));
        });
        return array_slice($list, 0, $limit);
    }

    /** Resolve a free-text token to a real team via the platform search API. */
    public static function resolveTeam(string $token): ?array
    {
        foreach (self::aliasQueries($token) as $q) {
            $sr = Api::search($q);
            $t = $sr['teams'][0] ?? null;
            $team = is_array($t) ? (is_array($t['name'] ?? null) ? $t['name'] : $t) : null;
            if (is_array($team) && !empty($team['row_id'])) return $team;
        }
        return null;
    }

    /** Last N finished matches of a team ("آخر 5 مباريات الهلال"). */
    public static function teamForm(string $token, int $n = 5): array
    {
        $team = self::resolveTeam($token);
        if (!$team) return ['team' => null, 'matches' => []];
        $b = Api::teamMatchesBuckets((int)$team['row_id']);
        return ['team' => $team, 'matches' => array_slice($b['results'], 0, max(1, min(8, $n)))];
    }

    /** Upcoming fixtures of a team. */
    public static function teamSchedule(string $token, int $n = 4): array
    {
        $team = self::resolveTeam($token);
        if (!$team) return ['team' => null, 'matches' => []];
        $b = Api::teamMatchesBuckets((int)$team['row_id']);
        return ['team' => $team, 'matches' => array_slice($b['fixtures'], 0, max(1, min(8, $n)))];
    }

    /**
     * Goal scorers of a match (follow-up questions: "who scored?").
     * @return array{home:array, away:array, match:?array}
     */
    public static function matchGoals(int $matchId): array
    {
        $info = Api::matchInfo($matchId);
        if (empty($info['match_id'])) return ['home' => [], 'away' => [], 'match' => null];
        $info = Api::unifyMatchState($info);
        $eventsData = Api::matchEvents($matchId);
        $events = is_array($eventsData['events'] ?? null) ? $eventsData['events'] : ($info['events'] ?? []);
        if (!is_array($events)) $events = [];
        $homeId = (int)($info['home_team']['row_id'] ?? 0);
        $out = ['home' => [], 'away' => [], 'match' => $info];
        foreach ($events as $ev) {
            if (!is_array($ev)) continue;
            $key = event_type($ev)['key'];
            if (!in_array($key, ['goal', 'penalty', 'owngoal'], true)) continue;
            $minute = (int)($ev['time_minute'] ?? 0);
            $plus   = (int)($ev['time_plus'] ?? 0);
            $row = [
                'player' => player_label($ev['player_name'] ?? null, '—'),
                'minute' => $minute . ($plus > 0 ? '+' . $plus : ''),
                'kind'   => $key,
            ];
            // Own goals count for the OPPOSITE side.
            $side = ((int)($ev['team_id'] ?? 0) === $homeId) ? 'home' : 'away';
            if ($key === 'owngoal') $side = $side === 'home' ? 'away' : 'home';
            $out[$side][] = $row;
        }
        return $out;
    }

    /* ==================== LeagueProvider ==================== */

    /** Find a competition by (partial) name. */
    public static function resolveLeague(string $q): ?array
    {
        $q = trim($q);
        if ($q === '') return null;
        $qn = self::normLower($q);
        $best = null;
        foreach (Api::allLeagues() as $lg) {
            $title = (string)($lg['title'] ?? '');
            if ($title === '') continue;
            if (mb_stripos(self::normLower($title), $qn) !== false) {
                // Prefer the shortest title containing the query (most specific).
                if ($best === null || mb_strlen($title) < mb_strlen((string)$best['title'])) $best = $lg;
            }
        }
        return $best;
    }

    /** @return array{league:array, rows:array}|null standings table rows */
    public static function standings(string $leagueQuery): ?array
    {
        $lg = self::resolveLeague($leagueQuery) ?? self::resolveLeague('دوري');
        if (!$lg) return null;
        $data = Api::leagueStanding((int)$lg['url_id']);
        $rows = is_array($data['league'] ?? null)
            ? array_values(array_filter($data['league'], fn($r) => isset($r['team_id'])))
            : [];
        return $rows ? ['league' => $lg, 'rows' => $rows] : null;
    }

    /** @return array{league:array, rows:array}|null top scorers */
    public static function topScorers(string $leagueQuery): ?array
    {
        $lg = self::resolveLeague($leagueQuery);
        if (!$lg) return null;
        $rows = Api::leagueScorers((int)$lg['url_id']);
        return $rows ? ['league' => $lg, 'rows' => array_slice($rows, 0, 8)] : null;
    }

    /* ==================== NewsProvider ==================== */

    /**
     * Latest news, optionally filtered by team/player/keyword, with an
     * explicit count ("آخر 5 أخبار" / "latest 5 news").
     */
    public static function news(string $topic, int $limit = 4): array
    {
        $topic = trim($topic);
        if ($topic !== '' && mb_strlen($topic) >= 2) {
            $team = self::resolveTeam($topic);
            if ($team) {
                $items = Api::teamNews((int)$team['row_id']);
                if ($items) return array_slice($items, 0, $limit);
            }
            $pool = array_merge(Api::newsPage(1)['items'], Api::allNewsPage()['last_news']);
            $qn = self::normLower($topic);
            $hits = array_values(array_filter($pool, fn($n) =>
                mb_stripos(self::normLower((string)($n['title'] ?? '')), $qn) !== false));
            return array_slice($hits, 0, $limit);
        }
        return array_slice(Api::newsPage(1)['items'], 0, $limit);
    }

    /* ==================== ChannelProvider ==================== */

    public static function channels(string $name = '', int $limit = 6): array
    {
        $all = [];
        foreach (ChannelLib::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        foreach (AppChannels::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        $names = array_keys($all);
        if ($name !== '') {
            $qn = self::normLower($name);
            $names = array_values(array_filter($names, fn($n) => mb_stripos(self::normLower($n), $qn) !== false));
        }
        return array_slice($names, 0, $limit);
    }
}
