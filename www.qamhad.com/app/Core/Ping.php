<?php
declare(strict_types=1);

namespace Qamhad\Core;

/**
 * Search-engine change notifier. Watches every match in the live window
 * (yesterday → tomorrow) and, whenever a match changes state or score
 * (kickoff, goal, half-time, full-time), immediately:
 *
 *   1. submits the match URLs (ar + en) to IndexNow (Bing/Yandex/Seznam/Naver)
 *   2. pings Google + Bing with the fresh sitemap-match.xml
 *
 * State is kept in storage/settings/pingstate.json as match_id → hash, so
 * only genuine changes trigger a submission (no spam, no rate-limit risk).
 * Driven by /cron/ping (see routes.php) — call it every 1–5 minutes:
 *   * * * * * curl -s https://www.qamhad.com/cron/ping >/dev/null
 */
final class Ping
{
    /** Minimum seconds between two sitemap pings (avoid hammering endpoints). */
    private const SITEMAP_PING_COOLDOWN = 300;

    /**
     * Diff the current match window against the stored state and notify
     * search engines about every changed page. Returns a summary array
     * the cron endpoint prints as JSON.
     */
    public static function run(): array
    {
        $state = Settings::get('pingstate', []);
        if (!is_array($state)) $state = [];
        $prev = is_array($state['matches'] ?? null) ? $state['matches'] : [];

        $current = [];
        $changedIds = [];
        $urls = [];

        $original = Lang::current();
        foreach ([-1, 0, 1] as $offset) {
            $date = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' day'));
            foreach (Api::matchesByDate($date) as $m) {
                $id = (int)($m['match_id'] ?? 0);
                if (!$id) continue;
                $st = match_state($m);
                // Hash of everything a crawler would see change on the page.
                $hash = ($st['key'] ?? '') . '|' . (int)($m['home_scores'] ?? 0)
                      . '-' . (int)($m['away_scores'] ?? 0) . '|' . (string)($m['match_status'] ?? '');
                $current[$id] = $hash;
                if (($prev[(string)$id] ?? $prev[$id] ?? null) === $hash) continue;

                $changedIds[] = $id;
                foreach (['ar', 'en'] as $lang) {
                    Lang::boot($lang);
                    $urls[] = absolute_url(match_url($m));
                }
            }
        }
        Lang::boot($original);

        $submitted = false;
        $pinged = false;
        if ($urls) {
            // Live/matches hub pages change with every score too.
            $urls[] = SITE_URL . '/';
            $urls[] = SITE_URL . '/matches';
            $urls[] = SITE_URL . '/live';
            $urls[] = SITE_URL . '/sitemap-match.xml';
            $submitted = IndexNow::submit($urls);

            $lastPing = (int)($state['last_sitemap_ping'] ?? 0);
            if (time() - $lastPing >= self::SITEMAP_PING_COOLDOWN) {
                $pinged = self::pingSitemaps();
                if ($pinged) $state['last_sitemap_ping'] = time();
            }
        }

        $state['matches'] = $current;
        $state['last_run'] = time();
        Settings::set('pingstate', $state);

        return [
            'checked'   => count($current),
            'changed'   => count($changedIds),
            'urls'      => count($urls),
            'indexnow'  => $submitted,
            'sitemap_ping' => $pinged,
        ];
    }

    /**
     * Ping Google + Bing with the match sitemap. Google retired its ping
     * endpoint in 2023 (it may return 404 — harmless); Bing still honours
     * pings and additionally receives everything through IndexNow. Kept
     * best-effort: sitemaps in robots.txt + Search Console remain the
     * primary discovery channel.
     */
    public static function pingSitemaps(): bool
    {
        $sitemap = rawurlencode(SITE_URL . '/sitemap-match.xml');
        $ok = false;
        foreach ([
            'https://www.google.com/ping?sitemap=' . $sitemap,
            'https://www.bing.com/ping?sitemap=' . $sitemap,
        ] as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300) $ok = true;
        }
        return $ok;
    }
}
