<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * ToFi X Tv AI Assistant — site-data-first answer engine.
 *
 * Design (anti-hallucination by construction):
 *   1. Every question is first resolved against the SITE's own data —
 *      matches feed, teams/players search, TMDB cinema catalogue, news,
 *      channel libraries. When entities are found, the interactive cards
 *      (match/movie/series/news/channel/team/player/league) are built
 *      SERVER-SIDE from real payloads with real site URLs; the language
 *      model never invents a score, a date or a link.
 *   2. Only general/conversational questions reach the LLM, with a strict
 *      system prompt (answer from provided context, admit uncertainty,
 *      never guess results) and a compact context of today's fixtures.
 *   3. Everything is cached: entity resolutions ride the existing disk
 *      cache; single-turn LLM answers are cached for an hour.
 *
 * Admin control: Settings group "ai" — enabled flag + provider overrides
 * (base_url / api_key / model). Disabling hides the whole widget.
 */
final class Ai
{
    /* Provider defaults — overridable at runtime from Admin → المساعد الذكي. */
    private const DEFAULT_BASE_URL = 'https://api.bluesminds.com/v1';
    private const DEFAULT_API_KEY  = 'sk-u29IjaYVANsQ5UQa73CAZ3WRfYVDx3Ngoux0ClKse8eRL25D';
    private const DEFAULT_MODEL    = 'gpt-5.2-chat';

    private const MAX_MSG_LEN  = 600;
    private const MAX_HISTORY  = 8;
    private const RATE_LIMIT   = 14;    // requests per RATE_WINDOW per IP
    private const RATE_WINDOW  = 60;    // seconds

    public static function config(): array
    {
        $s = Settings::get('ai', []);
        if (!is_array($s)) $s = [];
        return [
            'enabled'  => !array_key_exists('enabled', $s) || !empty($s['enabled']),
            'base_url' => rtrim((string)($s['base_url'] ?? '') ?: self::DEFAULT_BASE_URL, '/'),
            'api_key'  => (string)($s['api_key'] ?? '') ?: self::DEFAULT_API_KEY,
            'model'    => (string)($s['model'] ?? '') ?: self::DEFAULT_MODEL,
        ];
    }

    /** Widget visibility (admin can stop/hide the assistant site-wide). */
    public static function enabled(): bool
    {
        return self::config()['enabled'];
    }

    /* ==================== Rate limiting (per IP, file-based) ==================== */

    public static function rateLimited(): bool
    {
        $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'ai-rate|' . md5($ip);
        $row = Cache::get($key, self::RATE_WINDOW);
        $n   = is_array($row) ? (int)($row['n'] ?? 0) : 0;
        if ($n >= self::RATE_LIMIT) return true;
        if ($n === 0) Cache::set($key, ['n' => 1]);
        else {
            // Keep the original window start (don't slide it on every hit).
            $row['n'] = $n + 1;
            @file_put_contents(Cache::path($key), json_encode($row));
        }
        return false;
    }

    /* ==================== Entry point ==================== */

    /**
     * @param string $message  raw user message (sanitized here)
     * @param array  $history  [{role:'user'|'assistant', content:string}, …]
     * @return array{text:string, cards:array, suggestions:array}
     */
    public static function handle(string $message, array $history = []): array
    {
        $q = trim(strip_tags($message));
        $q = mb_substr($q, 0, self::MAX_MSG_LEN);
        if ($q === '') {
            return ['text' => self::t('empty'), 'cards' => [], 'suggestions' => self::suggestions()];
        }

        // 1) Site data first — deterministic cards, zero hallucination.
        //    A resolved intent with an honest "not found" text is also final
        //    (never hand a data question to the LLM to guess at).
        $found = self::resolve($q);
        if ($found['cards'] || $found['text'] !== '') {
            return [
                'text'        => $found['text'],
                'cards'       => array_slice($found['cards'], 0, 6),
                'suggestions' => $found['suggestions'] ?: self::suggestions(),
            ];
        }

        // 2) General questions → LLM with strict grounding.
        $answer = self::chat($q, $history);
        return [
            'text'        => $answer ?? self::t('unavailable'),
            'cards'       => [],
            'suggestions' => self::suggestions(),
        ];
    }

    /* ==================== Intent + entity resolution ==================== */

    /** @return array{text:string, cards:array, suggestions:array} */
    private static function resolve(string $q): array
    {
        $ar   = Lang::current() === 'ar';
        $norm = self::normalize($q);
        $out  = ['text' => '', 'cards' => [], 'suggestions' => []];

        // ---- Fixed intents (fast keyword routes; patterns match the
        //      NORMALIZED text: أ→ا and ة→ه) ----
        if (preg_match('/(مباريات اليوم|مباريات\s*$|today.?s? matches|matches today)/ui', $norm)) {
            $cards = self::matchCards(Api::matchesByDate(), 6);
            return ['text' => $cards ? self::t('today_intro') : self::t('no_matches'),
                    'cards' => $cards, 'suggestions' => self::suggestions('matches')];
        }
        if (preg_match('/(مباشر|live)/ui', $norm) && !preg_match('/(فيلم|مسلسل|movie|series)/ui', $norm)) {
            $live = array_values(array_filter(Api::matchesByDate(), fn($m) => match_state($m)['key'] === 'live'));
            $cards = self::matchCards($live, 6);
            return ['text' => $cards ? self::t('live_intro') : self::t('no_live'),
                    'cards' => $cards, 'suggestions' => self::suggestions('matches')];
        }
        if (preg_match('/(احدث|جديد).*(افلام|فيلم)|latest movies|new movies/ui', $norm)) {
            $rows = CinemaPolicy::filterList(Tmdb::nowPlayingMovies()['results'] ?? [], 'movie');
            $cards = array_map([self::class, 'movieCardFrom'], array_slice($rows, 0, 4));
            if ($cards) return ['text' => self::t('movie_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('cinema')];
        }
        if (preg_match('/(احدث|جديد).*(مسلسلات|مسلسل)|latest series|new series/ui', $norm)) {
            $rows = CinemaPolicy::filterList(Tmdb::onTheAirTv()['results'] ?? [], 'tv');
            $cards = array_map(fn($tv) => self::seriesCardFrom($tv, false), array_slice($rows, 0, 4));
            if ($cards) return ['text' => self::t('series_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('cinema')];
        }

        // ---- Movie ----
        if (preg_match('/^\s*(?:فيلم|افلام|movie|film)\s+(.{2,})$/ui', $norm, $m)) {
            $cards = self::movieCards(trim($m[1]));
            if ($cards) return ['text' => self::t('movie_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('cinema')];
            return ['text' => self::t('not_found_movie'), 'cards' => [], 'suggestions' => self::suggestions('cinema')];
        }
        // ---- Series ----
        if (preg_match('/^\s*(?:مسلسل|مسلسلات|series|show|anime|انمي)\s+(.{2,})$/ui', $norm, $m)) {
            $cards = self::seriesCards(trim($m[1]));
            if ($cards) return ['text' => self::t('series_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('cinema')];
            return ['text' => self::t('not_found_series'), 'cards' => [], 'suggestions' => self::suggestions('cinema')];
        }
        // ---- News ----
        if (preg_match('/^\s*(?:اخبار|خبر|news(?:\s+(?:of|about))?)\s*(.*)$/ui', $norm, $m)) {
            $topic = trim($m[1]);
            $cards = self::newsCards($topic);
            if ($cards) return ['text' => self::t('news_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('news')];
            return ['text' => self::t('not_found_news'), 'cards' => [], 'suggestions' => self::suggestions('news')];
        }
        // ---- Channel ----
        if (preg_match('/^\s*(?:قناه|قنوات|channel)\s*(.*)$/ui', $norm, $m)) {
            $cards = self::channelCards(trim($m[1]));
            if ($cards) return ['text' => self::t('channel_intro'), 'cards' => $cards, 'suggestions' => self::suggestions('matches')];
        }

        // ---- Match: "فرنسا والأرجنتين" / "france vs argentina" / "مباراة X" ----
        $matchQuery = preg_replace('/^\s*(?:مباراه|ماتش|match)\s+/ui', '', $norm);
        $pairCards = self::findMatchCards($matchQuery);
        if ($pairCards) {
            return ['text' => self::t('match_intro'), 'cards' => $pairCards, 'suggestions' => self::suggestions('matches')];
        }

        // ---- General search: teams/players → cinema → news headline ----
        if (mb_strlen($norm) >= 2) {
            $sr = Api::search($norm);
            $cards = [];
            foreach (array_slice($sr['teams'] ?? [], 0, 2) as $t) {
                $team = is_array($t['name'] ?? null) ? $t['name'] : $t;
                if (!empty($team['row_id'])) $cards[] = self::teamCard($team);
            }
            foreach (array_slice($sr['player'] ?? [], 0, 2) as $p) {
                $pl = is_array($p['name'] ?? null) ? $p['name'] : $p;
                if (!empty($pl['row_id'])) $cards[] = self::playerCard($pl);
            }
            // A single strong team hit → also surface its next/last matches.
            if (count($cards) === 1 && ($cards[0]['type'] ?? '') === 'team') {
                $cards = array_merge($cards, self::teamMatchCards((int)$cards[0]['id'], 2));
            }
            if ($cards) {
                return ['text' => self::t('entity_intro'), 'cards' => array_values(array_filter($cards)), 'suggestions' => self::suggestions()];
            }
            // Cinema fallback (bare titles like "Avatar" / "Squid Game")
            $cine = self::cinemaMultiCards($norm);
            if ($cine) return ['text' => self::t('cinema_intro'), 'cards' => $cine, 'suggestions' => self::suggestions('cinema')];
            // League name?
            $lg = self::leagueCard($norm);
            if ($lg) return ['text' => self::t('entity_intro'), 'cards' => [$lg], 'suggestions' => self::suggestions('matches')];
        }

        return $out;
    }

    /* ==================== Card builders (real site data + real URLs) ==================== */

    private static function matchCards(array $matches, int $max): array
    {
        $cards = [];
        foreach ($matches as $m) {
            if (!is_array($m) || empty($m['match_id'])) continue;
            $state = match_state($m);
            $home = team_of($m, 'home');
            $away = team_of($m, 'away');
            $cards[] = [
                'type'   => 'match',
                'id'     => (int)$m['match_id'],
                'url'    => match_url($m),
                'home'   => ['name' => team_name($home), 'img' => team_img($home)],
                'away'   => ['name' => team_name($away), 'img' => team_img($away)],
                'league' => (string)($m['championship']['title'] ?? ''),
                'state'  => $state['key'],
                'label'  => $state['label'],
                'score'  => $state['started'] ? ((int)($m['home_scores'] ?? 0) . ' - ' . (int)($m['away_scores'] ?? 0)) : '',
                'time'   => format_ts_time((int)($m['match_timestamp'] ?? 0)),
                'date'   => (string)($m['match_date'] ?? ''),
            ];
            if (count($cards) >= $max) break;
        }
        return $cards;
    }

    /** Match by team names in a ±3-day window ("فرنسا والأرجنتين", "X vs Y"). */
    private static function findMatchCards(string $q): array
    {
        // Split "A و B" / "A والب" (attached waw) / "A vs B" / "A ضد B" / "A × B".
        $tokens = preg_split('/\s+(?:ضد|مع|vs\.?|versus|and|x)\s+|\s+و(?=\S)\s*|\s*[×–]\s*|\s+-\s+/ui', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => mb_strlen($t) >= 2));
        if (!$tokens) $tokens = [trim($q)];
        if (mb_strlen($tokens[0]) < 2) return [];
        // Compare against NORMALIZED team names (hamza/ta-marbuta tolerant).
        $tokens = array_map(fn($t) => mb_strtolower(self::normalize($t)), array_slice($tokens, 0, 3));

        $best = [];
        for ($i = -1; $i <= 3; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                $names = mb_strtolower(self::normalize(
                    team_name(team_of($m, 'home')) . ' ' . team_name(team_of($m, 'away'))));
                $hits = 0;
                foreach ($tokens as $t) {
                    if (mb_stripos($names, $t) !== false) $hits++;
                }
                if ($hits === 0) continue;
                $best[] = ['hits' => $hits, 'i' => abs($i), 'm' => $m];
            }
        }
        if (!$best) return [];
        usort($best, fn($a, $b) => [$b['hits'], -$a['i']] <=> [$a['hits'], -$b['i']]);
        // Require both teams when the user named two; single-token queries pass.
        if (count($tokens) >= 2 && $best[0]['hits'] < 2) return [];
        return self::matchCards(array_column(array_slice($best, 0, 3), 'm'), 3);
    }

    private static function teamMatchCards(int $teamId, int $max): array
    {
        $b = Api::teamMatchesBuckets($teamId);
        $list = array_merge(array_slice($b['fixtures'], 0, $max), array_slice($b['results'], 0, 1));
        return self::matchCards($list, $max);
    }

    private static function movieCardFrom(array $mv): array
    {
        return [
            'type'     => 'movie',
            'id'       => (int)($mv['id'] ?? 0),
            'url'      => movie_url($mv),
            'title'    => Tmdb::titleOf($mv),
            'year'     => Tmdb::yearOf($mv),
            'rating'   => (float)($mv['vote_average'] ?? 0) > 0 ? Tmdb::rating($mv['vote_average']) : '',
            'age'      => CinemaPolicy::ratingLabel(CinemaPolicy::itemFor('movie', (int)($mv['id'] ?? 0))['rating']),
            'poster'   => tmdb_poster($mv['poster_path'] ?? null, 'w185'),
            'overview' => excerpt((string)($mv['overview'] ?? ''), 120),
        ];
    }

    private static function seriesCardFrom(array $tv, bool $enrich): array
    {
        $seasons = $episodes = 0;
        if ($enrich) { // season/episode counts live on the (cached) detail payload
            $full = Tmdb::tv((int)($tv['id'] ?? 0));
            $seasons  = (int)($full['number_of_seasons'] ?? 0);
            $episodes = (int)($full['number_of_episodes'] ?? 0);
        }
        return [
            'type'     => 'series',
            'id'       => (int)($tv['id'] ?? 0),
            'url'      => series_url($tv),
            'title'    => Tmdb::titleOf($tv),
            'year'     => Tmdb::yearOf($tv),
            'rating'   => (float)($tv['vote_average'] ?? 0) > 0 ? Tmdb::rating($tv['vote_average']) : '',
            'age'      => CinemaPolicy::ratingLabel(CinemaPolicy::itemFor('tv', (int)($tv['id'] ?? 0))['rating']),
            'poster'   => tmdb_poster($tv['poster_path'] ?? null, 'w185'),
            'overview' => excerpt((string)($tv['overview'] ?? ''), 120),
            'seasons'  => $seasons,
            'episodes' => $episodes,
        ];
    }

    private static function movieCards(string $title, int $max = 3): array
    {
        $res = Tmdb::get('/search/movie', ['query' => $title, 'include_adult' => 'false']);
        $rows = CinemaPolicy::filterList($res['results'] ?? [], 'movie');
        $rows = array_values(array_filter($rows, fn($r) => !empty($r['id'])));
        return array_map([self::class, 'movieCardFrom'], array_slice($rows, 0, $max));
    }

    private static function seriesCards(string $title, int $max = 3): array
    {
        $res = Tmdb::get('/search/tv', ['query' => $title, 'include_adult' => 'false']);
        $rows = CinemaPolicy::filterList($res['results'] ?? [], 'tv');
        $rows = array_values(array_filter($rows, fn($r) => !empty($r['id'])));
        $cards = [];
        foreach (array_slice($rows, 0, $max) as $i => $tv) {
            $cards[] = self::seriesCardFrom($tv, $i === 0);
        }
        return $cards;
    }

    /** Mixed movie+tv lookup for bare titles ("Avatar", "Squid Game"). */
    private static function cinemaMultiCards(string $title, int $max = 3): array
    {
        $res = Tmdb::searchMulti($title);
        $rows = CinemaPolicy::filterList(array_values(array_filter(
            $res['results'] ?? [],
            fn($r) => in_array($r['media_type'] ?? '', ['movie', 'tv'], true)
                && !empty($r['id'])
                && (float)($r['popularity'] ?? 0) > 1.5
        )));
        $cards = [];
        foreach (array_slice($rows, 0, $max) as $i => $it) {
            $cards[] = Tmdb::typeOf($it) === 'tv'
                ? self::seriesCardFrom($it, $i === 0)
                : self::movieCardFrom($it);
        }
        return $cards;
    }

    private static function newsCards(string $topic, int $max = 4): array
    {
        $topic = trim($topic);
        // Team-scoped news ("أخبار ريال مدريد") via the real team-news endpoint.
        if ($topic !== '' && mb_strlen($topic) >= 2) {
            $sr = Api::search($topic);
            $team = $sr['teams'][0]['name'] ?? null;
            if (is_array($team) && !empty($team['row_id'])) {
                $items = Api::teamNews((int)$team['row_id']);
                if ($items) return self::newsItemCards($items, $max);
            }
            // Headline keyword match across the latest feeds.
            $pool = array_merge(Api::newsPage(1)['items'], Api::allNewsPage()['last_news']);
            $hits = array_values(array_filter($pool, fn($n) =>
                mb_stripos((string)($n['title'] ?? ''), $topic) !== false));
            if ($hits) return self::newsItemCards($hits, $max);
            return [];
        }
        return self::newsItemCards(Api::newsPage(1)['items'], $max);
    }

    private static function newsItemCards(array $items, int $max): array
    {
        $cards = [];
        foreach ($items as $n) {
            if (!is_array($n) || empty($n['id']) || empty($n['title'])) continue;
            $cards[] = [
                'type'  => 'news',
                'id'    => (int)$n['id'],
                'url'   => news_url($n),
                'title' => (string)$n['title'],
                'img'   => news_img($n, '300'),
                'time'  => time_ago($n['created_at'] ?? ($n['date'] ?? '')),
            ];
            if (count($cards) >= $max) break;
        }
        return $cards;
    }

    private static function channelCards(string $name, int $max = 4): array
    {
        $all = [];
        foreach (ChannelLib::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        foreach (AppChannels::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        $names = array_keys($all);
        if ($name !== '') {
            $names = array_values(array_filter($names, fn($n) => mb_stripos($n, $name) !== false));
        }
        $cards = [];
        foreach (array_slice($names, 0, $max) as $n) {
            $cards[] = ['type' => 'channel', 'name' => $n, 'url' => path('live')];
        }
        return $cards;
    }

    private static function teamCard(array $team): array
    {
        return [
            'type'  => 'team',
            'id'    => (int)($team['row_id'] ?? 0),
            'url'   => team_url($team),
            'title' => team_name($team),
            'img'   => team_img($team),
        ];
    }

    private static function playerCard(array $p): array
    {
        return [
            'type'  => 'player',
            'id'    => (int)($p['row_id'] ?? 0),
            'url'   => player_url($p),
            'title' => player_label($p),
            'img'   => player_img($p),
        ];
    }

    private static function leagueCard(string $q): ?array
    {
        foreach (Api::allLeagues() as $lg) {
            $title = (string)($lg['title'] ?? '');
            if ($title !== '' && mb_stripos($title, $q) !== false) {
                return [
                    'type'  => 'league',
                    'id'    => (int)$lg['url_id'],
                    'url'   => league_url($lg),
                    'title' => $title,
                    'img'   => league_img($lg),
                ];
            }
        }
        return null;
    }

    /* ==================== LLM (general questions only) ==================== */

    private static function chat(string $q, array $history): ?string
    {
        // Single-turn answers are cacheable for an hour.
        $cacheKey = 'ai-chat|' . Lang::current() . '|' . md5($q);
        if (!$history) {
            $hit = Cache::get($cacheKey, 3600);
            if (is_string($hit) && $hit !== '') return $hit;
        }

        $ar = Lang::current() === 'ar';
        $sys = $ar
            ? "أنت مساعد موقع ToFi X Tv (توفي إكس تيفي) — منصة عربية للمباريات المباشرة والأخبار الرياضية والأفلام والمسلسلات."
              . " أجب بإيجاز وبنفس لغة المستخدم. لا تخمّن أبداً نتائج المباريات أو مواعيدها أو أي معلومة غير مؤكدة؛"
              . " إن لم تكن متأكداً قل بوضوح: لا أملك معلومة مؤكدة عن ذلك، واقترح على المستخدم تصفح القسم المناسب في الموقع."
              . " لا تذكر مواقع منافسة ولا روابط خارجية. تاريخ اليوم: " . date('Y-m-d') . '.'
            : "You are the assistant of ToFi X Tv — a platform for live football, sports news, movies and series."
              . " Answer briefly in the user's language. NEVER guess match results, kickoff times or any unverified fact;"
              . " if unsure, clearly say you don't have confirmed information and point the user to the right site section."
              . " Never mention competitor sites or external links. Today's date: " . date('Y-m-d') . '.';

        // Ground with a compact snapshot of today's fixtures (real data).
        $lines = [];
        foreach (array_slice(Api::matchesByDate(), 0, 12) as $m) {
            $st = match_state($m);
            $lines[] = team_name(team_of($m, 'home')) . ' vs ' . team_name(team_of($m, 'away'))
                . ' | ' . ($m['championship']['title'] ?? '')
                . ' | ' . ($st['key'] === 'upcoming' ? format_ts_time((int)($m['match_timestamp'] ?? 0)) : $st['label'])
                . ($st['started'] ? ' | ' . (int)($m['home_scores'] ?? 0) . '-' . (int)($m['away_scores'] ?? 0) : '');
        }
        if ($lines) {
            $sys .= $ar ? "\n\nمباريات اليوم على الموقع:\n" : "\n\nToday's fixtures on the site:\n";
            $sys .= implode("\n", $lines);
        }

        $messages = [['role' => 'system', 'content' => $sys]];
        foreach (array_slice($history, -self::MAX_HISTORY) as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = mb_substr(trim(strip_tags((string)($h['content'] ?? ''))), 0, self::MAX_MSG_LEN);
            if ($content !== '') $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $q];

        $out = self::llm($messages);
        if ($out !== null && !$history) Cache::set($cacheKey, $out);
        return $out;
    }

    /** Raw chat-completions call. Returns assistant text or null. */
    public static function llm(array $messages, int $maxTokens = 600): ?string
    {
        $cfg = self::config();
        if ($cfg['api_key'] === '') return null;
        $ch = curl_init($cfg['base_url'] . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['api_key'],
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => $cfg['model'],
                'messages'    => $messages,
                'max_tokens'  => $maxTokens,
                'temperature' => 0.4,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) return null;
        $data = json_decode($body, true);
        $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        return $text !== '' ? $text : null;
    }

    /* ==================== Copy + suggestions ==================== */

    private static function normalize(string $q): string
    {
        $q = preg_replace('/\s+/u', ' ', trim($q)) ?? $q;
        // Arabic normalization: hamza/alef variants + ta marbuta (typo tolerance).
        return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $q);
    }

    private static function t(string $key): string
    {
        $ar = Lang::current() === 'ar';
        $map = [
            'empty'           => [$ar ? 'اكتب سؤالك وسأساعدك فوراً 👋' : 'Type your question and I\'ll help right away 👋'],
            'today_intro'     => [$ar ? 'إليك أبرز مباريات اليوم على توفي إكس تيفي:' : "Here are today's top matches on ToFi X Tv:"],
            'live_intro'      => [$ar ? 'هذه المباريات الجارية الآن:' : 'These matches are live right now:'],
            'no_live'         => [$ar ? 'لا توجد مباريات مباشرة في هذه اللحظة. تفقد مباريات اليوم:' : 'No matches are live at this moment. Check today\'s fixtures:'],
            'no_matches'      => [$ar ? 'لا توجد مباريات مسجلة اليوم.' : 'No matches are scheduled today.'],
            'match_intro'     => [$ar ? 'وجدت هذه المباراة لك:' : 'I found this match for you:'],
            'movie_intro'     => [$ar ? 'إليك ما وجدته في الأفلام:' : 'Here\'s what I found in movies:'],
            'series_intro'    => [$ar ? 'إليك ما وجدته في المسلسلات:' : 'Here\'s what I found in series:'],
            'cinema_intro'    => [$ar ? 'وجدت هذه النتائج في الأفلام والمسلسلات:' : 'I found these in movies & series:'],
            'news_intro'      => [$ar ? 'أحدث الأخبار المتعلقة بطلبك:' : 'The latest related news:'],
            'channel_intro'   => [$ar ? 'القنوات المتوفرة تجدها داخل صفحات المباريات المباشرة:' : 'Available channels are inside the live match pages:'],
            'entity_intro'    => [$ar ? 'إليك ما وجدته في بيانات الموقع:' : 'Here\'s what I found on the site:'],
            'not_found_movie' => [$ar ? 'لم أجد هذا الفيلم في مكتبة الموقع. جرّب اسماً آخر أو تصفح قسم الأفلام.' : 'I couldn\'t find that movie in the site library. Try another title or browse the movies section.'],
            'not_found_series'=> [$ar ? 'لم أجد هذا المسلسل في مكتبة الموقع. جرّب اسماً آخر أو تصفح قسم المسلسلات.' : 'I couldn\'t find that series. Try another title or browse the series section.'],
            'not_found_news'  => [$ar ? 'لم أجد أخباراً مطابقة الآن. تصفح قسم الأخبار لآخر المستجدات.' : 'No matching news right now. Browse the news section for the latest.'],
            'unavailable'     => [$ar ? 'تعذر الوصول للمساعد الذكي حالياً — حاول مرة أخرى بعد قليل، أو استخدم البحث في الموقع.' : 'The assistant is temporarily unavailable — try again shortly, or use the site search.'],
        ];
        return $map[$key][0] ?? '';
    }

    /** Contextual quick-suggestion chips. */
    public static function suggestions(string $ctx = ''): array
    {
        $ar = Lang::current() === 'ar';
        $all = [
            'matches' => $ar ? 'مباريات اليوم' : "Today's matches",
            'live'    => $ar ? 'المباريات المباشرة' : 'Live matches',
            'movies'  => $ar ? 'أحدث الأفلام' : 'Latest movies',
            'series'  => $ar ? 'أحدث المسلسلات' : 'Latest series',
            'news'    => $ar ? 'الأخبار الرياضية' : 'Sports news',
        ];
        $q = [
            'matches' => $ar ? 'مباريات اليوم' : 'today matches',
            'live'    => $ar ? 'المباريات المباشرة' : 'live matches',
            'movies'  => $ar ? 'أحدث الأفلام' : 'latest movies',
            'series'  => $ar ? 'أحدث المسلسلات' : 'latest series',
            'news'    => $ar ? 'أخبار' : 'news',
        ];
        $order = match ($ctx) {
            'matches' => ['live', 'matches', 'news', 'movies'],
            'cinema'  => ['movies', 'series', 'matches', 'live'],
            'news'    => ['news', 'matches', 'live', 'movies'],
            default   => ['matches', 'live', 'movies', 'series', 'news'],
        };
        $out = [];
        foreach ($order as $k) $out[] = ['label' => $all[$k], 'q' => $q[$k]];
        return $out;
    }
}
