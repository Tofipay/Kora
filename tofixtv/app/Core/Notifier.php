<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Match-event scanner: diffs today's fixtures against the previous run and
 * broadcasts kick-off / goal / half-time / full-time push notifications over
 * FCM HTTP v1 (via the Fcm class).
 *
 * Shared by both entry points:
 *   - deploy/notify-worker.php   (server cron: php notify-worker.php)
 *   - GET /cron/notify?key=…     (URL cron: wget, for shared hosting)
 */
final class Notifier
{
    private static function stateFile(): string
    {
        return SETTINGS_DIR . '/notify-state.json';
    }

    /**
     * Secret key for the URL-triggered cron. Generated once and persisted in
     * the fcm settings so the admin panel can show a ready-to-use wget line.
     */
    public static function cronKey(): string
    {
        $fcm = Settings::get('fcm', []);
        if (!is_array($fcm)) $fcm = [];
        $key = (string)($fcm['cronKey'] ?? '');
        if ($key === '') {
            $key = bin2hex(random_bytes(16));
            Settings::merge('fcm', ['cronKey' => $key]);
        }
        return $key;
    }

    /**
     * Scan fixtures, detect events since the last run, broadcast each one.
     *
     * @return array{ok:bool,events:int,sent:int,skipped?:string}
     */
    public static function scanAndBroadcast(): array
    {
        if (!Fcm::isConfigured()) {
            return ['ok' => false, 'events' => 0, 'sent' => 0, 'skipped' => 'Service Account not configured'];
        }

        $fcm    = Settings::get('fcm', []);
        $events = is_array($fcm) ? ($fcm['events'] ?? []) : [];

        $stateFile = self::stateFile();
        $prev = is_file($stateFile) ? (json_decode((string)file_get_contents($stateFile), true) ?: []) : [];
        $next = [];
        $queue = [];
        $changedUrls = [];   // pinged to IndexNow for fast re-crawl

        foreach (Api::matchesByDate() as $m) {
            $id = (int)($m['match_id'] ?? 0);
            if (!$id) continue;
            $home = team_name(team_of($m, 'home'));
            $away = team_name(team_of($m, 'away'));
            $hs = (int)($m['home_scores'] ?? 0);
            $as = (int)($m['away_scores'] ?? 0);
            $status = (int)($m['status'] ?? 0);
            $next[$id] = ['s' => $status, 'h' => $hs, 'a' => $as];

            $p = $prev[$id] ?? null;
            if ($p === null) continue; // first sighting — no diff to notify about
            $url = absolute_url(match_url($m));
            $vs  = "{$home} × {$away}";

            // The match's championship → per-league topic ("lg_{url_id}"),
            // so each push only reaches visitors subscribed to that league.
            $champId = (int)($m['championship']['url_id'] ?? 0);
            $topic   = $champId > 0 ? 'lg_' . $champId : null;

            // Any score/status change → the match page content changed.
            if ($p['s'] !== $status || $p['h'] !== $hs || $p['a'] !== $as) {
                $changedUrls[] = $url;
            }

            if (!empty($events['match_start']) && $p['s'] === 0 && in_array($status, [1, 2, 3], true)) {
                $queue[] = ["⚽ بدأت المباراة", $vs, $url, $topic];
            }
            if (!empty($events['goal']) && ($hs > $p['h'] || $as > $p['a'])) {
                $queue[] = ["⚽ هدف! {$hs} - {$as}", $vs, $url, $topic];
            }
            if (!empty($events['half_time']) && $p['s'] === 1 && $status === 2) {
                $queue[] = ["نهاية الشوط الأول {$hs} - {$as}", $vs, $url, $topic];
            }
            if (!empty($events['full_time']) && $p['s'] !== 4 && $status === 4) {
                $queue[] = ["انتهت المباراة {$hs} - {$as}", $vs, $url, $topic];
            }
        }

        @file_put_contents($stateFile, (string)json_encode($next), LOCK_EX);

        // Freshness: tell IndexNow which match pages (and the live/home hubs)
        // changed so Bing/Yandex re-crawl within minutes, not days.
        if ($changedUrls) {
            $changedUrls[] = SITE_URL . '/';
            $changedUrls[] = absolute_url(path('live'));
            IndexNow::submit($changedUrls);
        }

        $sent = 0;
        foreach (array_slice($queue, 0, 12) as [$title, $body, $url, $topic]) {
            $r = Fcm::broadcast($title, $body, $url, $topic);
            $sent += (int)($r['sent'] ?? 0);
        }

        return ['ok' => true, 'events' => count($queue), 'sent' => $sent, 'indexnow' => count($changedUrls)];
    }
}
