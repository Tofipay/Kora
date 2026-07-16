<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\ChannelLib;
use Qamhad\Core\Lang;
use Qamhad\Core\VideoFeed;
use Qamhad\Core\Yacine;

/**
 * First-party JSON API served THROUGH the front controller (router), so the
 * mobile app works on ANY hosting — even where /api/*.php files are not
 * executed directly and every request is funnelled to index.php.
 *
 * Envelope: { ok, stale, lang, count, data }. Registered for both
 * /api/{name} and /api/{name}.php in routes.php.
 */
final class AppApi
{
    /** Emit the standard success envelope and stop. */
    private static function emit($data, int $ttl = 60): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Cache-Control: public, max-age=' . max(0, $ttl));
        }
        echo json_encode([
            'ok'    => true,
            'stale' => false,
            'lang'  => Lang::current(),
            'count' => is_array($data) ? count($data) : null,
            'data'  => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function fail(string $message, int $status = 400): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($status);
        }
        echo json_encode(['ok' => false, 'error' => $message, 'data' => []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** GET /api/matches[.php]?date=YYYY-MM-DD */
    public static function matches(): void
    {
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
        $ttl = ($date === date('Y-m-d')) ? 60 : 600;
        self::emit(array_values(Api::matchesByDate($date)), $ttl);
    }

    /** GET /api/live[.php] */
    public static function live(): void
    {
        $all  = Api::matchesByDate(date('Y-m-d'));
        $live = array_values(array_filter(
            $all,
            static fn($m) => is_array($m) && match_state($m)['key'] === 'live'
        ));
        self::emit($live, 60);
    }

    /** GET /api/news[.php]?page=N | ?id=N
     *  List returns { items, current_page, last_page, ... } (object); the app
     *  reads data.items. Detail returns the article object. */
    public static function news(): void
    {
        if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
            self::emit(Api::newsDetail((int)$_GET['id']), 900);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        self::emit(Api::newsPage($page), 900);
    }

    /** GET /api/standings[.php]?league=URL_ID */
    public static function standings(): void
    {
        $league = (int)($_GET['league'] ?? $_GET['id'] ?? 0);
        if ($league <= 0) self::fail('Missing league id');
        $standing = Api::leagueStanding($league);
        $rows = is_array($standing['league'] ?? null)
            ? array_values(array_filter($standing['league'], fn($r) => is_array($r) && isset($r['team_id'])))
            : [];
        self::emit([
            'standings' => $rows,
            'scorers'   => array_slice(Api::leagueScorers($league), 0, 20),
        ], 3600);
    }

    /** GET /api/team[.php]?id=N */
    public static function team(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) self::fail('Missing team id');
        $buckets = Api::teamMatchesBuckets($id);
        self::emit([
            'team'     => $buckets['team'] ?? null,
            'league'   => $buckets['league'] ?? null,
            'fixtures' => $buckets['fixtures'] ?? [],
            'results'  => $buckets['results'] ?? [],
            'squad'    => Api::teamSquad($id),
        ], 600);
    }

    /** GET /api/player[.php]?id=N&slug=... */
    public static function player(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) self::fail('Missing player id');
        $slug = preg_replace('/[^\p{L}\p{N}\- ]/u', '', (string)($_GET['slug'] ?? '')) ?: '';
        self::emit(Api::playerFull($id, $slug), 3600);
    }

    /** GET /api/videos[.php]?champ=&page=&q=&id= */
    public static function videos(): void
    {
        if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
            $v = VideoFeed::find((int)$_GET['id']);
            self::emit(['items' => $v ? [$v] : [], 'has_next' => false, 'page' => 1], 900);
        }
        $champ = isset($_GET['champ'])
            ? strtolower((string)preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['champ']))
            : 'all';
        $champ = $champ !== '' && VideoFeed::isCategory($champ) ? $champ : 'all';
        $page  = max(1, (int)($_GET['page'] ?? 1));
        $q     = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);

        $res = $q !== ''
            ? VideoFeed::search($q, $page, VideoFeed::PER_PAGE)
            : VideoFeed::page($champ, $page, VideoFeed::PER_PAGE);

        self::emit([
            'items'      => array_values($res['items'] ?? []),
            'has_next'   => (bool)($res['has_next'] ?? false),
            'page'       => (int)($res['page'] ?? $page),
            'categories' => array_values(VideoFeed::categories()),
        ], 600);
    }

    /** GET /api/channels[.php] */
    public static function channels(): void
    {
        self::emit(array_values(ChannelLib::all()), 3600);
    }

    /** GET /api/leagues[.php] */
    public static function leagues(): void
    {
        self::emit(array_values(Api::allLeagues()), 3600);
    }

    /** GET /api/search[.php]?q=QUERY */
    public static function search(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) self::emit(['player' => [], 'teams' => []], 60);
        if (mb_strlen($q) > 60) $q = mb_substr($q, 0, 60);
        self::emit(Api::search($q), 900);
    }

    /** GET /api/match_info[.php]?id=N */
    public static function matchInfo(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) self::fail('Missing match id');
        self::emit(Api::matchInfo($id), 60);
    }

    /**
     * GET /api/match_full[.php]?id=N
     * Everything the Match Center screen shows on the website, normalized for
     * the app: match info, timeline events, lineups (both sides), head-to-head
     * stats, broadcast channels, league standings and top scorers.
     */
    public static function matchFull(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) self::fail('Missing match id');
        $payload = build_match_full($id);
        if ($payload === null) self::fail('Match not found', 404);
        self::emit($payload, !empty($payload['live']) ? 30 : 300);
    }

    /**
     * GET /api/resolve[.php]?url=<channel/stream url>
     * Server-side resolve of a channel URL into ready-to-play sources — Yacine
     * API links are decrypted and their HLS is returned as an absolute
     * first-party /stream proxy URL that ExoPlayer can play directly; plain
     * m3u8/mpd links are passed through. Returns { sources:[{name,url,type}] }.
     */
    public static function resolve(): void
    {
        $url = trim((string)($_GET['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) self::fail('Missing url');

        $sources = [];
        foreach (Yacine::expandServers([['name' => 'Server', 'url' => $url]]) as $s) {
            $u = (string)($s['url'] ?? '');
            if ($u === '') continue;
            // Make the first-party /stream proxy path absolute for ExoPlayer.
            if ($u[0] === '/') $u = rtrim(SITE_URL, '/') . $u;
            $sources[] = [
                'name' => (string)($s['name'] ?? 'Server'),
                'url'  => $u,
                'type' => (string)($s['type'] ?? 'auto'),
            ];
        }
        self::emit(['sources' => $sources], 30);
    }
}
