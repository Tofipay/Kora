<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\ChannelLib;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\Streams;
use TofiXTv\Core\View;

final class MatchCenter
{
    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if (!$id) View::notFound();

        // Warm every match endpoint in ONE parallel batch (cold cache used to
        // pay 6 sequential upstream round-trips before first paint).
        $mb = Api::base('matches');
        Api::warm([
            "{$mb}/matches/match_info/{$id}",
            "{$mb}/matches/matches_event/{$id}",
            "{$mb}/matches/matches_lineup/{$id}",
            "{$mb}/matches/statics_match/{$id}",
            "{$mb}/matches/referees_match/{$id}",
            "{$mb}/matches/channel_match/{$id}",
        ]);

        $info = Api::matchInfo($id);
        if (empty($info) || empty($info['match_id'])) View::notFound();

        // Unified status resolver: the detail payload can lag the listings
        // feed (list shows 90+17′, detail still "not started") — overlay the
        // fresher live fields from the already-loaded day feed so this page
        // always agrees with the match lists.
        $info = Api::unifyMatchState($info);

        Settings::trackHit('match', team_name(team_of($info, 'home')) . ' × ' . team_name(team_of($info, 'away')));

        // Canonical slug URL — redirect /match/123 or stale slugs
        $canonical = match_url($info);
        if ('/match/' . $slug !== preg_replace('#^/en#', '', $canonical)) {
            View::redirect($canonical, 301);
        }

        $eventsData = Api::matchEvents($id);
        $events = is_array($eventsData['events'] ?? null) ? $eventsData['events'] : ($info['events'] ?? []);
        if (!is_array($events)) $events = [];

        // Period-by-period scores (extra time + penalty shootout) ride on the
        // matches_event payload; fall back to match_info if it ever carries them.
        $periodsSrc = is_array($eventsData) && $eventsData ? $eventsData : $info;
        $periods = match_periods($periodsSrc);

        // Per-player shootout (taker, order, scored/missed) for the shootout UI.
        $homeIdC = (int)($info['home_team']['row_id'] ?? 0);
        $awayIdC = (int)($info['away_team']['row_id'] ?? 0);
        $shootout = penalty_shootout($periodsSrc, $homeIdC, $awayIdC)
                 ?? penalty_shootout($info, $homeIdC, $awayIdC);

        $lineupData = Api::matchLineup($id);
        $lineups = is_array($lineupData['lineup'] ?? null) ? $lineupData['lineup'] : [];

        $statsData = Api::matchStats($id);
        $stats = is_array($statsData['statics'] ?? null) ? array_values($statsData['statics']) : [];
        if (empty($stats) && is_array($info['statics'] ?? null)) $stats = array_values($info['statics']);

        $referees = Api::matchReferees($id);
        $channels = Api::matchChannels($id);

        $leagueId = (int)($info['championship']['url_id'] ?? 0);
        $standing = $leagueId ? Api::leagueStanding($leagueId) : [];
        $standingRows = is_array($standing['league'] ?? null)
            ? array_values(array_filter($standing['league'], fn($r) => isset($r['team_id'])))
            : [];
        $scorers = $leagueId ? array_slice(Api::leagueScorers($leagueId), 0, 10) : [];
        $leagueNews = $leagueId ? array_slice(Api::leagueNews($leagueId), 0, 6) : [];

        $home = team_of($info, 'home');
        $away = team_of($info, 'away');
        $state = match_state($info);

        // Freshness signals: live pages change constantly (score/clock), finished
        // pages are stable. Truthful Last-Modified + ETag let crawlers revalidate
        // cheaply (304) and prioritise re-crawling live matches without hammering
        // settled ones.
        $kickoff = (int)($info['match_timestamp'] ?? 0) ?: time();
        $mtime = match ($state['key'] ?? 'upcoming') {
            'live'     => time(),
            'finished' => min($kickoff + 7200, time()),
            default    => min($kickoff, time()),
        };
        // Android app (User-Agent: com.aloka.live.app) — resolve the app stream
        // link (direct per-match link, else the app channel library). The
        // value may be a normal http(s) URL OR an opaque encrypted token; it
        // is passed through untouched. Purely additive: '' for normal visitors.
        $isApp = is_tofix_app();
        $appWatchUrl = $isApp ? \TofiXTv\Core\AppLinks::resolveForMatch($id) : '';
        // The HTML differs for the app User-Agent, so caches must key on it.
        header('Vary: User-Agent');

        // Fingerprint of EVERYTHING the admin can change about this match's
        // watch experience (stream servers, channel library matches, app
        // links, app channel library). Any add/edit/delete flips the ETag,
        // so the page re-renders instantly instead of serving a stale
        // "watch now" button from the browser cache.
        $watchCfg = sha1(json_encode([
            Streams::forMatch($id),
            ChannelLib::serversForMatch($id),
            \TofiXTv\Core\AppLinks::allForMatch($id),
            \TofiXTv\Core\AppChannels::urlsForMatch($id),
        ], JSON_UNESCAPED_UNICODE));

        // Always revalidate (cheap 304 when nothing changed — the validators
        // below make that the common case) so admin link changes appear on
        // the very next page load. Page speed is unaffected: unchanged pages
        // answer with an empty 304, changed ones render from the disk cache.
        header('Cache-Control: public, max-age=0, must-revalidate');
        http_cache_validate($mtime, 'match-' . $id . '|' . ($state['key'] ?? '')
            . '|' . (int)($info['home_scores'] ?? 0) . '-' . (int)($info['away_scores'] ?? 0)
            . '|' . (string)($info['match_status'] ?? '')
            . '|w:' . $watchCfg
            . ($isApp ? '|app|' . sha1($appWatchUrl) : ''));

        // Dynamic, state-aware SEO strings derived purely from live match data
        // (teams, league, venue, kickoff, score, broadcast channels).
        $sm = match_seo($info, $state, $channels);
        $vsTitle = team_name($home) . ' ' . t('match.vs') . ' ' . team_name($away);

        $seo = (new Seo())
            ->title($sm['title'])
            ->description($sm['description'])
            ->type('article')
            ->canonical(SITE_URL . $canonical)
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.matches'), path('matches')],
                [(string)($info['championship']['title'] ?? ''), league_url($info['championship'] ?? [])],
                [$vsTitle, $canonical],
            ]);
        $seo->addJsonLd(Seo::sportsEventSchema($info, $channels));
        $faqSchema = Seo::matchFaqSchema($sm['faq']);
        if ($faqSchema) $seo->addJsonLd($faqSchema);
        // ItemList of today's fixtures — coverage/freshness signal for
        // «مباريات اليوم بث مباشر» on every match page (capped small).
        $todayList = Api::matchesByDate();
        if ($todayList) {
            $seo->addJsonLd(Seo::matchListSchema(
                $todayList,
                \TofiXTv\Core\Lang::current() === 'ar' ? 'مباريات اليوم بث مباشر' : "Today's matches live",
                path('matches'),
                10
            ));
        }

        View::page('match', [
            'm'            => $info,
            'home'         => $home,
            'away'         => $away,
            'state'        => $state,
            // SEO headings (visually hidden, design unchanged)
            'seoH1'        => $sm['h1'],
            'seoH2'        => $sm['h2'],
            'events'       => $events,
            'periods'      => $periods,
            'shootout'     => $shootout,
            'lineups'      => $lineups,
            'stats'        => $stats,
            'referees'     => $referees,
            'channels'     => $channels,
            'standingRows' => $standingRows,
            'scorers'      => $scorers,
            'leagueNews'   => $leagueNews,
            // Watchable if admin configured streams OR the match's broadcast
            // channels are in the library. The button only SHOWS while live
            // (see the view's $state['live'] check).
            'watchable'    => Streams::isWatchable($id) || ChannelLib::hasMatch($id),
            'watchUrl'     => Streams::watchUrl($id),
            'watchTarget'  => Streams::watchTarget($id),
            // Android app only (User-Agent: com.aloka.live.app): the blue app
            // button replaces the orange one whenever a link exists — the
            // match does NOT have to be live. Empty for normal visitors.
            'isApp'        => $isApp,
            'appWatchUrl'  => $appWatchUrl,
            // Real match_info extras (verified fields)
            'stadium'      => (string)($info['Stadium'] ?? ''),
            'roundLabel'   => is_string($info['round'] ?? null) ? $info['round'] : '',
            'teamWins'     => is_array($info['teamWins'] ?? null) ? $info['teamWins'] : [],
            'playedResult' => is_array($info['played_result'] ?? null) ? $info['played_result'] : [],
        ], $seo);
    }
}
