<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\Notifier;
use TofiXTv\Core\Settings;
use TofiXTv\Core\VideoFeed;
use TofiXTv\Core\View;

/**
 * Internal JSON endpoints used by the frontend (live refresh, newsletter, push).
 */
final class ApiJson
{
    /** Compact live payload polled by match cards. */
    public static function liveScores(): void
    {
        $out = [];
        foreach (Api::matchesByDate() as $m) {
            $state = match_state($m);
            $row = [
                'id'     => (int)($m['match_id'] ?? 0),
                'state'  => $state['key'],
                'label'  => $state['label'],
                'hs'     => (int)($m['home_scores'] ?? 0),
                'as'     => (int)($m['away_scores'] ?? 0),
            ];
            if (!empty($state['clock'])) {
                $row['st'] = (int)($state['status'] ?? 0);   // period status
                $row['ps'] = (int)$state['clock']['start'];  // period start ts (0 = unknown)
                $row['min'] = (int)$state['clock']['minute'];
            }
            $out[] = $row;
        }
        View::json(['ok' => true, 'ts' => time(), 'matches' => $out]);
    }

    /**
     * Paginated video feed (Btolat source, 5 per page like the section).
     * GET /api/videos?champ=all&page=2 → { html, has_next, page }
     * Returns server-rendered cards so markup stays identical to SSR.
     */
    public static function videos(): void
    {
        $champ = isset($_GET['champ'])
            ? strtolower((string)preg_replace('/[^a-z0-9\-]/i', '', (string)$_GET['champ']))
            : '';
        $champ = $champ !== '' && VideoFeed::isCategory($champ) ? $champ : 'all';
        $page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        $res  = VideoFeed::page($champ, $page);
        $html = '';
        foreach ($res['items'] as $v) {
            $html .= View::partial('video-card', ['v' => $v]);
        }
        header('Cache-Control: public, max-age=600');
        View::json([
            'ok'       => true,
            'html'     => $html,
            'count'    => count($res['items']),
            'page'     => $res['page'],
            'has_next' => $res['has_next'],
        ]);
    }

    public static function match(int $id): void
    {
        $m = Api::matchInfo($id);
        if (empty($m['match_id'])) View::json(['ok' => false], 404);
        $m = Api::unifyMatchState($m);   // same status source as the listings
        $state = match_state($m);
        $events = [];
        foreach (array_slice(is_array($m['events'] ?? null) ? $m['events'] : [], 0, 40) as $ev) {
            $et = event_type($ev);
            $events[] = [
                'type'   => $et['key'],
                'label'  => $et['key'] === 'period' ? period_label($ev) : $et['label'],
                'minute' => (int)($ev['time_minute'] ?? 0),
                'plus'   => (int)($ev['time_plus'] ?? 0),
                'team'   => (int)($ev['team_id'] ?? 0),
                'player' => player_label($ev['player_name'] ?? null),
                'assist' => player_label($ev['assist_player_name'] ?? null),
            ];
        }
        View::json([
            'ok'    => true,
            'id'    => $id,
            'state' => $state['key'],
            'label' => $state['label'],
            'hs'    => (int)($m['home_scores'] ?? 0),
            'as'    => (int)($m['away_scores'] ?? 0),
            'events'=> $events,
        ]);
    }

    /**
     * AI Assistant endpoint. POST {message, history?} → {ok, text, cards,
     * suggestions}. Same-origin only, rate limited per IP, all input
     * sanitized server-side; cards are built exclusively from site data.
     */
    public static function aiChat(): void
    {
        header('Cache-Control: no-store');
        if (!\TofiXTv\Core\Ai::enabled()) View::json(['ok' => false, 'error' => 'disabled'], 403);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') View::json(['ok' => false, 'error' => 'method'], 405);

        // CSRF/same-origin guard: browsers always send Origin on cross-site
        // POSTs — reject any that doesn't match our host.
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $oh = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?: ''));
            $sh = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $sh = preg_replace('/:\d+$/', '', $sh);
            $oh = preg_replace('/:\d+$/', '', $oh);
            if ($oh !== '' && $sh !== '' && $oh !== $sh) View::json(['ok' => false, 'error' => 'origin'], 403);
        }

        if (\TofiXTv\Core\Ai::rateLimited()) View::json(['ok' => false, 'error' => 'rate_limited'], 429);

        $raw = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($raw)) $raw = [];
        $message = (string)($raw['message'] ?? '');
        $history = [];
        foreach (array_slice(is_array($raw['history'] ?? null) ? $raw['history'] : [], -8) as $h) {
            if (is_array($h)) $history[] = ['role' => (string)($h['role'] ?? 'user'), 'content' => (string)($h['content'] ?? '')];
        }
        // Context Engine: the page the visitor is currently viewing (hint only).
        $page = is_array($raw['page'] ?? null) ? $raw['page'] : [];
        // Conversation memory: last entity discussed, echoed by the client.
        $memory = is_array($raw['memory'] ?? null) ? $raw['memory'] : [];
        // Language hint from the widget (site language of the current page).
        $langHint = in_array($raw['lang'] ?? '', ['ar', 'en'], true) ? (string)$raw['lang'] : '';

        $res = \TofiXTv\Core\Ai::handle($message, $history, $page, $memory, $langHint);
        View::json(['ok' => true] + $res);
    }

    /** Newsletter signup — stored locally, exportable from the admin panel. */
    public static function newsletter(): void
    {
        $raw = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;
        $email = filter_var(trim((string)($raw['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email) View::json(['ok' => false, 'error' => 'invalid_email'], 422);

        $list = Settings::get('newsletter', []);
        if (!is_array($list)) $list = [];
        $emails = array_column($list, 'email');
        if (!in_array($email, $emails, true)) {
            $list[] = ['email' => $email, 'at' => date('c')];
            Settings::set('newsletter', $list);
        }
        View::json(['ok' => true]);
    }

    /** Store FCM tokens + their subscribed topics for the notifications manager. */
    public static function pushSubscribe(): void
    {
        // CORS + method flexibility: some hosting setups 301 every request
        // to the canonical host, which flips a POST into a GET (→ the
        // "تعذر الحفظ — HTTP 404" bug). The endpoint therefore answers GET
        // with query params too, and carries ACAO so the response stays
        // readable even when a redirect moves the call across hosts.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $raw = json_decode((string)file_get_contents('php://input'), true) ?: [];
        if ((!is_array($raw) || $raw === []) && isset($_GET['token'])) {
            $qTopics = trim((string)($_GET['topics'] ?? ''));
            $raw = [
                'token'   => (string)$_GET['token'],
                'topics'  => $qTopics !== '' ? explode(',', $qTopics) : [],
                'disable' => !empty($_GET['disable']),
            ];
        }
        $token = trim((string)($raw['token'] ?? ''));
        if ($token === '' || strlen($token) > 4096) View::json(['ok' => false], 422);

        $tokens = Settings::get('push_tokens', []);
        if (!is_array($tokens)) $tokens = [];

        // Full opt-out: {disable:true} removes the token → no more pushes.
        if (!empty($raw['disable'])) {
            $tokens = array_values(array_filter($tokens, static fn($row): bool =>
                !is_array($row) || ($row['token'] ?? '') !== $token
            ));
            Settings::set('push_tokens', $tokens);
            \TofiXTv\Core\Fcm::log('subscribe', 'opt-out …' . substr($token, -8) . ' (total ' . count($tokens) . ')');
            View::json(['ok' => true, 'disabled' => true]);
        }

        // Topic slugs are pattern-validated ("lg_{url_id}" / "all") rather
        // than checked against today's league list — competitions rotate
        // with the fixtures window, and an off-season league subscription
        // must survive until it plays again. Empty → 'all'.
        $requested = is_array($raw['topics'] ?? null) ? array_map('strval', $raw['topics']) : [];
        $topics = array_values(array_unique(array_filter(
            array_slice($requested, 0, 300),
            static fn(string $s): bool => $s === 'all' || preg_match('/^lg_\d{1,10}$/', $s) === 1
        )));
        if (!$topics) $topics = ['all'];

        // Update in place if the token already exists (re-saving new topics),
        // otherwise append.
        $found = false;
        foreach ($tokens as &$row) {
            if (is_array($row) && ($row['token'] ?? '') === $token) {
                $row['topics'] = $topics;
                $row['at']     = date('c');
                $found = true;
                break;
            }
        }
        unset($row);
        if (!$found) {
            $tokens[] = ['token' => $token, 'at' => date('c'), 'topics' => $topics];
            if (count($tokens) > 20000) $tokens = array_slice($tokens, -20000);
        }
        // Settings::set reports the actual DISK write — surface storage
        // problems to the client instead of a silent success with an empty
        // subscriber list ("admin sees no subscribers").
        $persisted = Settings::set('push_tokens', $tokens);
        \TofiXTv\Core\Fcm::log('subscribe', ($persisted ? 'ok' : 'WRITE FAILED')
            . ' …' . substr($token, -8) . ' topics=' . implode(',', $topics)
            . ' (total ' . count($tokens) . ')');
        if (!$persisted) View::json(['ok' => false, 'error' => 'storage_write_failed'], 500);

        View::json(['ok' => true, 'topics' => $topics]);
    }

    /**
     * URL-triggered cron for shared hosting (wget/curl every minute).
     * GET /cron/notify?key=SECRET  → runs the match-event scan + broadcast.
     * The secret is shown in Admin → Notifications and compared in constant time.
     */
    public static function cronNotify(): void
    {
        $given    = (string)($_GET['key'] ?? '');
        $expected = Notifier::cronKey();
        if ($given === '' || !hash_equals($expected, $given)) {
            View::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        View::json(Notifier::scanAndBroadcast());
    }

    /**
     * URL-triggered indexing cron: diffs match states/scores and notifies
     * search engines (IndexNow + sitemap ping) about every changed page.
     * GET /cron/ping — call every 1–5 minutes:
     *   * * * * * curl -s https://aloka-code.shop/cron/ping >/dev/null
     * No secret needed: it only re-announces public URLs and is self-throttled
     * (a run with no state changes submits nothing).
     */
    public static function cronPing(): void
    {
        View::json(\TofiXTv\Core\Ping::run());
    }
}
