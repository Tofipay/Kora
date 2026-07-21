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
        // Explicit dates: 2026-07-19 · 19/7/2026 · 19-7-2026
        if (preg_match('/\b(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/', $norm, $m)) {
            $d = sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            if (strtotime($d)) return ['date' => $d, 'rest' => trim(str_replace($m[0], ' ', $norm))];
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/', $norm, $m)) {
            $d = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            if (strtotime($d)) return ['date' => $d, 'rest' => trim(str_replace($m[0], ' ', $norm))];
        }
        return ['date' => null, 'rest' => $norm];
    }

    /** Team-vs-team tokenizer (attached Arabic waw, ضد, vs, ×, hyphen). */
    public static function teamTokens(string $q): array
    {
        $tokens = preg_split('/\s+(?:ضد|مع|امام|أمام|vs\.?|versus|and|x)\s+|\s+و(?=\S)\s*|\s*[×–]\s*|\s+-\s+/ui', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Strip surrounding punctuation (a trailing ؟/?/. must never stick to a
        // team name — it breaks the substring match against the fixtures feed).
        $clean = static fn(string $t): string => trim((string)preg_replace(
            '/^[\s\p{P}]+|[\s\p{P}]+$/u', '', $t));
        $tokens = array_values(array_filter(array_map($clean, $tokens), fn($t) => mb_strlen($t) >= 2));
        return array_slice($tokens, 0, 3);
    }

    private static function normLower(string $s): string
    {
        return mb_strtolower(str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $s));
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
        $tokens = array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
        if ($date !== null) {
            $pool = [];
            foreach (Api::matchesByDate($date) as $m) {
                if (!$tokens || self::nameHits($m, $tokens) >= min(count($tokens), 2)) $pool[] = $m;
                if (!$tokens && count($pool) >= 8) break;
            }
            if ($pool || !$tokens) return array_slice($pool, 0, $tokens ? $limit : 8);
        }
        if (!$tokens) return [];

        // 2) Near window.
        $best = [];
        for ($i = -3; $i <= 3; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                $hits = self::nameHits($m, $tokens);
                if ($hits === 0) continue;
                $best[] = ['hits' => $hits, 'dist' => abs($i), 'm' => $m];
            }
        }
        if ($best) {
            usort($best, fn($a, $b) => [$b['hits'], -$a['dist']] <=> [$a['hits'], -$b['dist']]);
            // For a two-team query, keep only matches where BOTH names hit —
            // otherwise a single shared name ("النصر") drags in unrelated games.
            if (count($tokens) >= 2) {
                $both = array_values(array_filter($best, fn($x) => $x['hits'] >= 2));
                if ($both) return array_column(array_slice($both, 0, $limit), 'm');
            } else {
                return array_column(array_slice($best, 0, $limit), 'm');
            }
        }

        // 3) Full team history + schedule (all seasons upstream exposes).
        $team = self::resolveTeam($tokens[0]);
        if (!$team) return [];
        $b = Api::teamMatchesBuckets((int)$team['row_id']);
        $results  = $b['results'];    // finished, newest first upstream
        $fixtures = $b['fixtures'];   // upcoming
        $opponent = $tokens[1] ?? '';
        $filter = function (array $list) use ($opponent): array {
            if ($opponent === '') return $list;
            return array_values(array_filter($list, fn($m) => self::nameHits($m, [$opponent]) >= 1));
        };
        $results  = $filter($results);
        $fixtures = $filter($fixtures);
        $pool = match ($prefer) {
            'past'   => array_merge($results, $fixtures),
            'future' => array_merge($fixtures, $results),
            default  => array_merge($fixtures ? [$fixtures[0]] : [], $results, array_slice($fixtures, 1)),
        };
        return array_slice($pool, 0, $limit);
    }

    /** Resolve a free-text token to a real team via the platform search API. */
    public static function resolveTeam(string $token): ?array
    {
        $sr = Api::search($token);
        $t = $sr['teams'][0] ?? null;
        $team = is_array($t) ? (is_array($t['name'] ?? null) ? $t['name'] : $t) : null;
        return (is_array($team) && !empty($team['row_id'])) ? $team : null;
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
