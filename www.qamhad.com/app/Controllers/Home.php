<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Lang;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class Home
{
    public static function index(): void
    {
        Settings::trackHit('home');

        $todayMatches = Api::matchesByDate();
        // When today is sparse (off-season / few competitions), fold in the next
        // day so the homepage never looks empty — the reference apps do the same.
        if (count(group_matches_by_league($todayMatches)) < 3) {
            foreach (Api::matchesByDate(date('Y-m-d', strtotime('+1 day'))) as $m) $todayMatches[] = $m;
        }
        $live = array_values(array_filter($todayMatches, fn($m) => match_state($m)['key'] === 'live'));
        $leagues = Api::allLeagues();
        $newsPage = Api::newsPage(1);
        $news = $newsPage['items'];

        // all_news_page (verified): featured article + important news +
        // the real "popular teams" list the reference site shows.
        $newsHub = Api::allNewsPage();
        if (!empty($newsHub['important']) || !empty($newsHub['main'])) {
            $merged = [];
            if ($newsHub['main']) $merged[] = $newsHub['main'];
            foreach (array_merge($newsHub['important'], $news) as $n) {
                if (!isset($n['id'])) continue;
                $merged[(int)$n['id']] = $n;
            }
            $news = array_values($merged);
        }

        // Featured: pinned-league matches first, then highest ranking
        $favIds = array_column(FAVORITE_LEAGUES, 'url_id');
        $featured = array_values(array_filter($todayMatches, function ($m) use ($favIds) {
            return in_array((int)($m['championship']['url_id'] ?? 0), $favIds, true);
        }));
        usort($featured, fn($a, $b) => (int)($a['match_timestamp'] ?? 0) <=> (int)($b['match_timestamp'] ?? 0));
        $featured = array_slice($featured, 0, 6);

        // Popular teams: the real last_teams list from all_news_page,
        // topped up from today's fixtures when short.
        $teams = [];
        foreach ($newsHub['last_teams'] as $tm) {
            $tid = (int)($tm['row_id'] ?? 0);
            if ($tid && !isset($teams[$tid])) $teams[$tid] = $tm;
        }
        foreach (array_merge($featured, $todayMatches) as $m) {
            if (count($teams) >= 14) break;
            foreach (['home', 'away'] as $side) {
                $tm = team_of($m, $side);
                $tid = (int)($tm['row_id'] ?? 0);
                if ($tid && !isset($teams[$tid])) $teams[$tid] = $tm;
            }
        }
        $teams = array_slice($teams, 0, 14, true);

        // Standings + scorers preview for the first pinned league that has them
        $standingLeague = null; $standingRows = []; $scorers = [];
        foreach (array_slice(FAVORITE_LEAGUES, 0, 4) as $f) {
            $st = Api::leagueStanding((int)$f['url_id']);
            $rows = is_array($st['league'] ?? null) ? array_values(array_filter($st['league'], fn($r) => isset($r['team_id']))) : [];
            if (count($rows) >= 4) {
                $standingLeague = ['url_id' => $f['url_id'], 'title' => $f[Lang::current()] ?? $f['ar']];
                $standingRows = array_slice($rows, 0, 8);
                $scorers = array_slice(Api::leagueScorers((int)$f['url_id']), 0, 5);
                break;
            }
        }

        // Aggregate stats strip
        $finished = array_filter($todayMatches, fn($m) => match_state($m)['key'] === 'finished');
        $goals = 0;
        foreach ($finished as $m) $goals += (int)($m['home_scores'] ?? 0) + (int)($m['away_scores'] ?? 0);
        $stats = [
            'matches' => count($todayMatches),
            'live'    => count($live),
            'finished'=> count($finished),
            'goals'   => $goals,
            'leagues' => count(group_matches_by_league($todayMatches)),
        ];

        // --- Dynamic homepage SEO targeting "مباريات اليوم بث مباشر" ---
        $ar = Lang::current() === 'ar';
        $liveN = count($live);
        $todayN = count($todayMatches);
        if ($ar) {
            $seoTitle = 'مباريات اليوم بث مباشر'
                . ($liveN ? ' — ' . $liveN . ' مباراة مباشرة الآن' : ' — مواعيد ونتائج مباشرة');
            $seoDesc  = 'مشاهدة مباريات اليوم بث مباشر' . ($todayN ? ' (' . $todayN . ' مباراة)' : '')
                . ': بث مباشر مباريات اليوم، النتائج المباشرة لحظة بلحظة، المواعيد، القنوات الناقلة والتشكيلات — '
                . 'أهم مباريات اليوم بدون تقطيع في مكان واحد.';
        } else {
            $seoTitle = 'Today’s Matches — Live Streaming'
                . ($liveN ? ' · ' . $liveN . ' live now' : '');
            $seoDesc  = 'Watch today’s football matches live: live streaming, real-time scores, '
                . 'kickoff times, TV channels and lineups — all in one place.';
        }

        $seo = (new Seo())->title($seoTitle)->description($seoDesc);
        $seo->addJsonLd($seo->organizationSchema());
        $seo->addJsonLd($seo->websiteSchema());
        // ItemList of today's fixtures — coverage + freshness for the target query.
        if ($todayMatches) {
            $seo->addJsonLd(Seo::matchListSchema(
                $todayMatches,
                $ar ? 'مباريات اليوم بث مباشر' : 'Today’s matches — live',
                path('/')
            ));
        }

        View::page('home', [
            'sections'       => Settings::homeSections(),
            'todayMatches'   => $todayMatches,
            'grouped'        => group_matches_by_league($todayMatches),
            'live'           => $live,
            'featured'       => $featured,
            'leagues'        => array_slice($leagues, 0, 12),
            'news'           => array_slice($news, 0, 9),
            'teams'          => array_values($teams),
            'standingLeague' => $standingLeague,
            'standingRows'   => $standingRows,
            'scorers'        => $scorers,
            'stats'          => $stats,
        ], $seo);
    }
}
