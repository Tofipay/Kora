<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Notifier;
use Qamhad\Core\Settings;
use Qamhad\Core\VideoFeed;
use Qamhad\Core\View;

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
     * Paginated video feed for infinite scroll / "show more".
     * GET /api/videos?champ=all&skip=80 → { html, has_more, next_skip }
     * Returns server-rendered cards so markup stays identical to SSR.
     */
    public static function videos(): void
    {
        $champ = isset($_GET['champ']) ? preg_replace('/[^0-9]/', '', (string)$_GET['champ']) : '';
        $champ = $champ !== '' ? $champ : 'all';
        $skip  = isset($_GET['skip']) ? max(0, (int)$_GET['skip']) : 0;

        $res  = VideoFeed::videos($champ, $skip);
        $html = '';
        foreach ($res['data'] as $v) {
            $html .= View::partial('video-card', ['v' => $v]);
        }
        header('Cache-Control: public, max-age=600');
        View::json([
            'ok'        => true,
            'html'      => $html,
            'count'     => $res['count'],
            'has_more'  => $res['has_more'],
            'next_skip' => $res['next_skip'],
        ]);
    }

    public static function match(int $id): void
    {
        $m = Api::matchInfo($id);
        if (empty($m['match_id'])) View::json(['ok' => false], 404);
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
        $raw = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $token = trim((string)($raw['token'] ?? ''));
        if ($token === '' || strlen($token) > 4096) View::json(['ok' => false], 422);

        // Validate requested topics against the known list; empty → 'all'.
        $valid = array_column(notify_topics(), 'slug');
        $topics = array_values(array_intersect(
            is_array($raw['topics'] ?? null) ? array_map('strval', $raw['topics']) : [],
            $valid
        ));
        if (!$topics) $topics = ['all'];

        $tokens = Settings::get('push_tokens', []);
        if (!is_array($tokens)) $tokens = [];

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
        Settings::set('push_tokens', $tokens);
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
     *   * * * * * curl -s https://www.qamhad.com/cron/ping >/dev/null
     * No secret needed: it only re-announces public URLs and is self-throttled
     * (a run with no state changes submits nothing).
     */
    public static function cronPing(): void
    {
        View::json(\Qamhad\Core\Ping::run());
    }
}
