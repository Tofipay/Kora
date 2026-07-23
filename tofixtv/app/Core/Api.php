<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Scores/news API client. The upstream host is selected by the active
 * language (ar => api-ar, en => api-en) and every response is cached on disk.
 */
final class Api
{
    public static function base(string $service): string
    {
        $lang = Lang::current();
        return API_BASES[$lang][$service] ?? API_BASES['ar'][$service];
    }

    /** @var array<string,true> URLs queued for post-response revalidation */
    private static array $revalidate = [];
    private static bool $shutdownArmed = false;

    /**
     * Raw GET with stale-while-revalidate caching. Returns decoded array or [].
     *
     * Freshness model (the "pages block on the upstream" fix):
     *   - fresh cache → served directly (as before)
     *   - EXPIRED cache → the stale payload is served INSTANTLY and one
     *     background revalidation is queued to run AFTER the response is
     *     flushed (see flushRevalidations). Concurrent visitors are coalesced
     *     by a lock so the upstream sees a single refresh, not one per user.
     *   - nothing cached at all → one blocking fetch (first request only).
     * The 60s negative cache after a failure is unchanged.
     */
    public static function get(string $url, int $ttl = CACHE_TTL_MATCHES): array
    {
        $cached = Cache::get($url, $ttl);
        if ($cached !== null) return is_array($cached) ? $cached : [];

        $stale = Cache::stale($url);
        if (is_array($stale)) {
            // Serve stale now; refresh in the background unless the upstream
            // just failed (negative cache: back off for 60s).
            if (Cache::get($url . '#fail', 60) === null) self::queueRevalidate($url);
            return $stale;
        }

        if (Cache::get($url . '#fail', 60) !== null) return [];

        // Cold miss — first request ever for this key: fetch synchronously.
        $data = self::store($url, self::fetch($url));
        return $data ?? [];
    }

    /**
     * Validate + persist one upstream response body.
     * Returns the decoded payload, or null when the body was unusable
     * (in which case the negative cache is armed).
     */
    private static function store(string $url, ?string $body): ?array
    {
        if ($body !== null) {
            // Reject anti-bot "download the app" placeholder responses (real
            // names replaced by sentinels) so they never get cached. News is
            // exempt — it can legitimately mention the app by name.
            if (!str_contains($url, '/News/') && is_blocked_text($body)) {
                Cache::set($url . '#fail', 1);
                return null;
            }
            $data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                Cache::set($url, $data);
                return $data;
            }
        }
        Cache::set($url . '#fail', 1);
        return null;
    }

    /** Queue a URL for one coalesced refresh after the response is sent. */
    private static function queueRevalidate(string $url): void
    {
        if (isset(self::$revalidate[$url]) || count(self::$revalidate) >= 6) return;
        if (!Cache::lock($url)) return;           // another request is on it
        self::$revalidate[$url] = true;
        if (!self::$shutdownArmed) {
            self::$shutdownArmed = true;
            register_shutdown_function([self::class, 'flushRevalidations']);
        }
    }

    /**
     * Shutdown hook: hand the response to the client first (FastCGI) or flush
     * every buffer, then refresh the queued URLs in ONE parallel batch. The
     * visitor never waits on this.
     */
    public static function flushRevalidations(): void
    {
        if (!self::$revalidate) return;
        $urls = array_keys(self::$revalidate);
        self::$revalidate = [];
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) @ob_end_flush();
            @flush();
        }
        ignore_user_abort(true);
        foreach (self::fetchMany($urls) as $url => $body) {
            self::store($url, $body);
        }
    }

    /**
     * Warm a set of URLs in ONE parallel round-trip. Only URLs with no cache
     * entry at all are fetched (fresh/stale hits are already instant via
     * get()); with a warm() call first, a controller's follow-up get() calls
     * all hit disk instead of paying N sequential upstream round-trips.
     */
    public static function warm(array $urls): void
    {
        $need = [];
        foreach ($urls as $url) {
            if (!is_string($url) || $url === '') continue;
            if (Cache::age($url) !== null) continue;              // fresh or stale exists
            if (Cache::get($url . '#fail', 60) !== null) continue; // backing off
            $need[$url] = true;
        }
        if (!$need) return;
        foreach (self::fetchMany(array_keys($need)) as $url => $body) {
            self::store($url, $body);
        }
    }

    private static function fetch(string $url): ?string
    {
        $res = self::fetchMany([$url]);
        return $res[$url] ?? null;
    }

    /**
     * Parallel GET via curl_multi. Returns url => body|null.
     * Same headers/limits as the historical single fetch.
     */
    private static function fetchMany(array $urls): array
    {
        $out = [];
        if (!$urls) return $out;
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING       => 'gzip',
                // Official mobile-app headers — WITHOUT these the API returns a
                // "download the app" placeholder instead of real data. Centralized
                // in config so an API-expiry is a one-file edit.
                CURLOPT_HTTPHEADER     => api_headers(),
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) curl_multi_select($mh, 1.0);
        } while ($active && $status === CURLM_OK);
        foreach ($handles as $url => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $out[$url] = ($body !== false && $body !== null && $code >= 200 && $code < 300)
                ? (string)$body : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    /* ================= Matches ================= */

    /** @var array<string,array> per-request memo — day scans hit the same dates repeatedly */
    private static array $dayMemo = [];

    public static function matchesByDate(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $memoKey = Lang::current() . '|' . $date;
        if (isset(self::$dayMemo[$memoKey])) return self::$dayMemo[$memoKey];

        $ttl = ($date === date('Y-m-d')) ? CACHE_TTL_LIVE : CACHE_TTL_MATCHES;

        // The FIRST bracket takes an explicit championship-id list. Passing
        // the tracked-competition ids returns every one of those leagues for
        // the day — including non-followed ones (followed=0) that the plain
        // followed-only feed ([]/…/L) hides. That empty-bracket feed is the
        // reason "only the World Cup showed"; the id list is not filtered.
        // The followed/all modes are then merged on top (deduped by match_id)
        // so any active competition outside the tracked list still appears —
        // the union can only ADD matches.
        $listJson = '[' . implode(',', array_map(static fn($id) => '"' . $id . '"', MATCHES_FOLLOW_IDS)) . ']';
        $urls = [
            self::base('matches') . "/matches/matches_date_get/{$date}/" . rawurlencode($listJson) . "/[]/[]/L/180",
        ];
        foreach (MATCHES_DATE_MODES as $mode) {
            $urls[] = self::base('matches') . "/matches/matches_date_get/{$date}/[]/[]/[]/{$mode}/180";
        }

        // Cold cache: fetch all mode feeds in ONE parallel round-trip instead
        // of three sequential upstream calls.
        self::warm($urls);

        $rows = [];
        foreach ($urls as $url) {
            $res = self::get($url, $ttl);
            $list = $res['data'] ?? [];
            if (!is_array($list)) continue;
            foreach ($list as $m) {
                if (!is_array($m) || !isset($m['match_id'])) continue;
                $rows[(int)$m['match_id']] = $m;
            }
        }
        $rows = array_values($rows);

        foreach ($rows as $m) {
            $lg = $m['championship'] ?? [];
            Registry::recordTeam(team_of($m, 'home'), is_array($lg) ? $lg : []);
            Registry::recordTeam(team_of($m, 'away'), is_array($lg) ? $lg : []);
        }
        return self::$dayMemo[$memoKey] = $rows;
    }

    /**
     * Unified match-status resolver — the single source of truth used by the
     * match detail page and the /api/match endpoint.
     *
     * The listings feed (matches_date_get) and the detail endpoint
     * (match_info) are cached independently upstream and can disagree for
     * minutes: the classic symptom is a list card showing 90+17′ live while
     * the detail page still renders "not started" with a countdown. This
     * overlays the live fields of whichever payload is FURTHER ALONG
     * (upcoming < live < finished; within live, the higher total score wins —
     * goals only ever increase) onto the detail payload.
     *
     * Zero performance cost: it reads ONLY the day feed that match pages
     * already load for their JSON-LD fixtures list (memoized per request,
     * disk-cached otherwise) — no new endpoints, no extra polling.
     */
    public static function unifyMatchState(array $info): array
    {
        $id   = (int)($info['match_id'] ?? 0);
        $date = (string)($info['match_date'] ?? '');
        if ($id < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $info;

        // Only matches around their kickoff window can meaningfully disagree.
        // Today's feed is already loaded by the match page; yesterday's is
        // consulted only for matches that may still be running past midnight.
        $today = date('Y-m-d');
        $kick  = match_kickoff_ts($info);
        if ($date !== $today
            && !($date === date('Y-m-d', strtotime('-1 day')) && $kick > 0 && time() - $kick < 6 * 3600)) {
            return $info;
        }

        $row = null;
        foreach (self::matchesByDate($date) as $m) {
            if ((int)($m['match_id'] ?? 0) === $id) { $row = $m; break; }
        }
        if (!$row) return $info;

        $rank = static fn(array $m): int => match (match_state($m)['key']) {
            'finished' => 2,
            'live'     => 1,
            default    => 0,
        };
        $ri = $rank($info);
        $rr = $rank($row);
        $rowFresher = $rr > $ri
            || ($rr === 1 && $ri === 1
                && ((int)($row['home_scores'] ?? 0) + (int)($row['away_scores'] ?? 0))
                 > ((int)($info['home_scores'] ?? 0) + (int)($info['away_scores'] ?? 0)));
        if (!$rowFresher) return $info;

        foreach (['status', 'live', 'ht_time', 'minutes', 'home_scores', 'away_scores', 'score_time', 'ex_time'] as $k) {
            if (array_key_exists($k, $row)) $info[$k] = $row[$k];
        }
        return $info;
    }

    /* ================= Teams (real endpoints, verified JSON) ================= */

    /**
     * /matches/team_matches/{id} — buckets: online / coming / end /
     * postponed / cancel, each { data: [full match objects], next_page }.
     * Returns ['fixtures' => [], 'results' => [], 'team' => ?, 'league' => ?].
     */
    public static function teamMatchesBuckets(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/team_matches/{$id}", CACHE_TTL_MATCHES);
        $d = $res['data'] ?? null;
        if (!is_array($d)) return ['fixtures' => [], 'results' => [], 'team' => null, 'league' => null];

        $pull = function ($bucket) use ($d): array {
            $b = $d[$bucket] ?? null;
            $list = is_array($b) ? ($b['data'] ?? $b) : [];
            return is_array($list)
                ? array_values(array_filter($list, fn($x) => is_array($x) && isset($x['match_id'])))
                : [];
        };

        $fixtures = array_merge($pull('online'), $pull('coming'), $pull('postponed'));
        $results  = $pull('end');

        $team = null; $league = null;
        foreach (array_merge($fixtures, $results) as $m) {
            $lg = is_array($m['championship'] ?? null) ? $m['championship'] : [];
            $h = team_of($m, 'home'); $a = team_of($m, 'away');
            Registry::recordTeam($h, $lg);
            Registry::recordTeam($a, $lg);
            if (!$team) {
                if ((int)($h['row_id'] ?? 0) === $id) $team = $h;
                elseif ((int)($a['row_id'] ?? 0) === $id) $team = $a;
            }
            if (!$league && $lg) $league = $lg;
        }
        return ['fixtures' => $fixtures, 'results' => $results, 'team' => $team, 'league' => $league];
    }

    /** Real squad {G,D,M,F,coach} from the website AJAX (no API exists). */
    public static function teamSquad(int $id): array
    {
        return Www::teamSquad($id);
    }

    /** /News/news_team/{id} — verified working. */
    public static function teamNews(int $id, int $page = 1): array
    {
        $res = self::get(self::base('news') . "/News/news_team/{$id}?page={$page}", CACHE_TTL_NEWS);
        $d = $res['data'] ?? null;
        if (is_array($d)) {
            $items = $d['data'] ?? $d;
            if (is_array($items) && isset($items[0]['title'])) {
                foreach ($items as $it) Registry::recordNews($it);
                return array_values($items);
            }
        }
        return [];
    }

    /* ================= Search (real: /api/search/{query}) ================= */

    /** @return array{player: array, teams: array} */
    public static function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return ['player' => [], 'teams' => []];
        $res = self::get(self::base('matches') . '/search/' . rawurlencode($query), CACHE_TTL_NEWS);
        $d = $res['data'] ?? [];
        $players = is_array($d['player'] ?? null) ? $d['player'] : [];
        $teams   = is_array($d['teams'] ?? null) ? $d['teams'] : [];
        foreach ($players as $p) {
            if (is_array($p['name'] ?? null)) Registry::recordPlayer($p['name']);
        }
        foreach ($teams as $t) {
            if (is_array($t['name'] ?? null)) Registry::recordTeam($t['name']);
        }
        return ['player' => $players, 'teams' => $teams];
    }

    /* ================= Players ================= */

    /**
     * Player vitals. There is NO player JSON API upstream (all
     * players/player_info variants return 404 — verified) — the data lives in
     * the website's player page, scraped and cached by Www::playerProfile().
     */
    public static function playerProfile(int $id): array
    {
        return Www::playerProfile($id);
    }

    /**
     * Full player profile — vitals + per-competition statistics + transfer
     * history, scraped from the ysscores player page (same structure as the
     * reference get_player_data.php). Returns the canonical shape documented
     * on Www::playerFull(), or [] when the scrape yields nothing.
     */
    public static function playerFull(int $id, string $slug = ''): array
    {
        return Www::playerFull($id, $slug);
    }

    public static function matchInfo(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/match_info/{$id}", CACHE_TTL_LIVE);
        return is_array($res['data'] ?? null) ? $res['data'] : [];
    }

    public static function matchEvents(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/matches_event/{$id}", CACHE_TTL_LIVE);
        return is_array($res['data'] ?? null) ? $res['data'] : [];
    }

    public static function matchLineup(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/matches_lineup/{$id}", CACHE_TTL_MATCHES);
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $sides = is_array($data['lineup'] ?? null) ? $data['lineup'] : [];
        foreach ($sides as $tid => $side) {
            foreach (['lineup', 'substitutions'] as $grp) {
                foreach (($side[$grp] ?? []) as $lp) {
                    if (is_array($lp['player'] ?? null)) Registry::recordPlayer($lp['player'], ['tid' => (int)$tid]);
                }
            }
        }
        return $data;
    }

    public static function matchStats(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/statics_match/{$id}", CACHE_TTL_LIVE);
        return is_array($res['data'] ?? null) ? $res['data'] : [];
    }

    public static function matchReferees(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/referees_match/{$id}", CACHE_TTL_MATCHES);
        return is_array($res['data'] ?? null) ? array_values($res['data']) : [];
    }

    public static function matchChannels(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/channel_match/{$id}", CACHE_TTL_MATCHES);
        return is_array($res['data'] ?? null) ? array_values($res['data']) : [];
    }

    /* ================= Leagues ================= */

    public static function leagueStanding(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/league_standing/{$id}", CACHE_TTL_LEAGUES);
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        if (is_array($data['league'] ?? null)) {
            foreach ($data['league'] as $row) {
                if (is_array($row['team_name'] ?? null)) Registry::recordTeam($row['team_name'], ['url_id' => $id]);
            }
        }
        return $data;
    }

    public static function leagueScorers(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/league_scorers/{$id}", CACHE_TTL_LEAGUES);
        $list = self::playerList($res['data'] ?? [], ['scorers', 'scorer', 'goals', 'players']);
        foreach ($list as $s) {
            if (is_array($s['player_info'] ?? null)) Registry::recordPlayer($s['player_info'], ['lid' => $id]);
        }
        return $list;
    }

    public static function leagueAssists(int $id): array
    {
        $res = self::get(self::base('matches') . "/matches/league_assist/{$id}", CACHE_TTL_LEAGUES);
        $list = self::playerList($res['data'] ?? [], ['assist', 'assists', 'players']);
        foreach ($list as $s) {
            if (is_array($s['player_info'] ?? null)) Registry::recordPlayer($s['player_info'], ['lid' => $id]);
        }
        return $list;
    }

    private static function playerList($data, array $preferKeys): array
    {
        if (!is_array($data)) return [];
        foreach ($preferKeys as $k) {
            if (isset($data[$k]) && is_array($data[$k])) return array_values($data[$k]);
        }
        $first = reset($data);
        if (is_array($first) && (isset($first['player_info']) || isset($first['player']) || isset($first['player_id']))) {
            return array_values($data);
        }
        foreach ($data as $v) {
            if (is_array($v)) {
                $fv = reset($v);
                if (is_array($fv) && (isset($fv['player_info']) || isset($fv['player']) || isset($fv['player_id']))) {
                    return array_values($v);
                }
            }
        }
        return [];
    }

    /**
     * Persistent url_id → logo filename map. Logos only appear in fixture
     * payloads, so off-season leagues (and pinned favourites) would lose
     * theirs — every sighting is recorded here instead.
     */
    public static function leagueImageMap(): array
    {
        $map = \TofiXTv\Core\Settings::get('league_images', []);
        return is_array($map) ? $map : [];
    }

    public static function leagueImage(int $urlId): ?string
    {
        return self::leagueImageMap()[(string)$urlId] ?? null;
    }

    /**
     * Active leagues discovered from a window of fixtures, favourites pinned first.
     */
    public static function allLeagues(): array
    {
        $lang = Lang::current();
        $key  = "ALL_LEAGUES_v2_{$lang}";
        $cached = Cache::get($key, CACHE_TTL_LEAGUES);
        if ($cached !== null) return $cached;

        $imgMap = self::leagueImageMap();
        $mapDirty = false;

        $leagues = [];
        for ($i = -2; $i <= 7; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (self::matchesByDate($d) as $m) {
                $c = $m['championship'] ?? null;
                $uid = $c['url_id'] ?? null;
                if (!is_array($c) || !$uid) continue;
                if (!empty($c['image']) && ($imgMap[(string)$uid] ?? null) !== $c['image']) {
                    $imgMap[(string)$uid] = (string)$c['image'];
                    $mapDirty = true;
                }
                if (!isset($leagues[$uid])) {
                    $leagues[$uid] = [
                        'url_id'   => (int)$uid,
                        'title'    => (string)($c['title'] ?? ''),
                        'image'    => $c['image'] ?? null,
                        'followed' => (int)($c['followed'] ?? 0),
                        'ranking'  => (int)($c['ranking'] ?? 999),
                    ];
                }
            }
        }
        if ($mapDirty) \TofiXTv\Core\Settings::set('league_images', $imgMap);

        $favIds = [];
        foreach (FAVORITE_LEAGUES as $f) {
            $uid = (int)$f['url_id'];
            $favIds[$uid] = true;
            if (isset($leagues[$uid])) {
                $leagues[$uid]['followed'] = 1;
                if (empty($leagues[$uid]['image'])) {
                    $leagues[$uid]['image'] = $imgMap[(string)$uid] ?? null;
                }
            } else {
                $leagues[$uid] = [
                    'url_id'   => $uid,
                    'title'    => $f[$lang] ?? $f['ar'],
                    'image'    => $imgMap[(string)$uid] ?? null,
                    'followed' => 1,
                    'ranking'  => 0,
                ];
            }
        }

        $list = array_values($leagues);
        usort($list, function ($a, $b) use ($favIds) {
            $fa = isset($favIds[$a['url_id']]) ? 0 : 1;
            $fb = isset($favIds[$b['url_id']]) ? 0 : 1;
            if ($fa !== $fb) return $fa - $fb;
            if ($a['followed'] !== $b['followed']) return $b['followed'] - $a['followed'];
            return $a['ranking'] <=> $b['ranking'];
        });

        Cache::set($key, $list);
        return $list;
    }

    /* ================= News ================= */

    /**
     * Drop "download the app" promo/placeholder items so they never render as
     * a card (whose detail is then blocked and 404s). Real articles pass through.
     */
    private static function cleanNews($items): array
    {
        if (!is_array($items)) return [];
        $items = array_values(array_filter($items, fn($it) =>
            is_array($it) && !is_blocked_text((string)($it['title'] ?? ''))));
        return EditorialNews::applyUpstreamList($items);
    }

    public static function newsPage(int $page = 1): array
    {
        $page = max(1, $page);
        $source = self::upstreamNewsPage($page);
        $items = EditorialNews::mergePage($source['items'], $page);
        return [
            'items'        => $items,
            'current_page' => $source['current_page'],
            'last_page'    => $source['last_page'],
            'total'        => max($source['total'], count($items)),
            'per_page'     => $source['per_page'],
        ];
    }

    /** Raw upstream list for the admin overlay editor (no hide/override layer). */
    public static function upstreamNewsPage(int $page = 1): array
    {
        $page = max(1, $page);
        $res  = self::get(self::base('news') . "/News/latest_news?page={$page}", CACHE_TTL_NEWS);
        $d    = $res['data'] ?? [];
        $items = array_values(array_filter((array)($d['data'] ?? []), static fn($it) =>
            is_array($it) && !is_blocked_text((string)($it['title'] ?? ''))
        ));
        foreach ($items as $it) Registry::recordNews($it);
        return [
            'items'        => $items,
            'current_page' => (int)($d['current_page'] ?? $page),
            'last_page'    => (int)($d['last_page'] ?? $page),
            'total'        => (int)($d['total'] ?? 0),
            'per_page'     => (int)($d['per_page'] ?? 10),
        ];
    }

    public static function newsDetail(int $id): array
    {
        $data = self::upstreamNewsDetail($id);
        return $data ? EditorialNews::applyUpstreamItem($data) : [];
    }

    /** Raw upstream detail for admin editing; public callers use newsDetail(). */
    public static function upstreamNewsDetail(int $id): array
    {
        $res = self::get(self::base('news') . "/News/news_detail/{$id}", CACHE_TTL_NEWS);
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        // The news_detail endpoint sometimes returns the anti-bot "download the
        // app" article in place of the real one. Detect it (title/body) and
        // treat it as unavailable so the caller can fall back to list metadata.
        if ($data) {
            $probe = (string)($data['title'] ?? '') . ' ' . (string)($data['full_news'] ?? '') . ' ' . (string)($data['news_desc'] ?? '');
            if (is_blocked_text($probe)) return [];
        }
        return $data;
    }

    /**
     * Find a news list item by id across the latest feed + news hub. Used to
     * render a real headline/image/excerpt when news_detail is blocked.
     */
    public static function findNewsItem(int $id): array
    {
        $pools = [];
        foreach ([1, 2] as $p) $pools[] = self::newsPage($p)['items'];
        $hub = self::allNewsPage();
        $pools[] = $hub['important'];
        $pools[] = $hub['last_news'];
        if ($hub['main']) $pools[] = [$hub['main']];
        foreach ($pools as $pool) {
            foreach ((array)$pool as $it) {
                if (is_array($it) && (int)($it['id'] ?? 0) === $id) {
                    $probe = (string)($it['title'] ?? '') . ' ' . (string)($it['news_desc'] ?? '');
                    if (!is_blocked_text($probe)) return $it;
                }
            }
        }
        // Persistent news index — resolves items that have scrolled out of the
        // live feeds but were seen (and recorded) on a previous page view.
        $saved = Registry::news($id);
        return $saved ? EditorialNews::applyUpstreamItem($saved) : [];
    }

    /**
     * /News/all_news_page — verified: { main: featured article,
     * important: [articles], last_teams: [{team:{row_id,title,image}}],
     * last_news: [articles] }. Powers the featured strip + popular teams.
     */
    public static function allNewsPage(): array
    {
        $res = self::get(self::base('news') . '/News/all_news_page', CACHE_TTL_NEWS);
        $d = $res['data'] ?? [];
        if (!is_array($d)) return ['main' => null, 'important' => [], 'last_teams' => [], 'last_news' => []];
        $teams = [];
        foreach (($d['last_teams'] ?? []) as $row) {
            $t = is_array($row) ? ($row['team'] ?? $row) : null;
            if (is_array($t) && !empty($t['row_id'])) {
                $teams[] = $t;
                Registry::recordTeam($t);
            }
        }
        $main      = is_array($d['main'] ?? null) && !empty($d['main']['id']) && !is_blocked_text((string)($d['main']['title'] ?? '')) ? EditorialNews::applyUpstreamItem($d['main']) : null;
        if (!$main) $main = null;
        $important = self::cleanNews($d['important'] ?? []);
        $lastNews  = self::cleanNews($d['last_news'] ?? []);
        if ($main) Registry::recordNews($main);
        foreach ($important as $it) Registry::recordNews($it);
        foreach ($lastNews as $it) Registry::recordNews($it);
        return [
            'main'       => $main,
            'important'  => $important,
            'last_teams' => $teams,
            'last_news'  => $lastNews,
        ];
    }

    public static function leagueNews(int $id, int $page = 1): array
    {
        $res = self::get(self::base('news') . "/News/news_league/{$id}?page={$page}", CACHE_TTL_NEWS);
        $d = $res['data'] ?? [];
        $items = self::cleanNews((isset($d['data']) && is_array($d['data'])) ? $d['data'] : (is_array($d) ? $d : []));
        foreach ($items as $it) Registry::recordNews($it);
        return $items;
    }
}
