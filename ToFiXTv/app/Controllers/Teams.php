<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\Registry;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\View;

final class Teams
{
    /** Popular teams derived from the active fixture window. */
    public static function index(): void
    {
        Settings::trackHit('teams');
        $teams = [];
        for ($i = -2; $i <= 7; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                foreach (['home', 'away'] as $side) {
                    $t = team_of($m, $side);
                    $id = (int)($t['row_id'] ?? 0);
                    if ($id && !isset($teams[$id])) {
                        $t['league'] = $m['championship'] ?? [];
                        $teams[$id] = $t;
                    }
                }
            }
        }
        // World-ranked national sides & followed clubs first
        $list = array_values($teams);
        usort($list, function ($a, $b) {
            $wa = (int)($a['world_ranking'] ?? 0); $wb = (int)($b['world_ranking'] ?? 0);
            if (($wa > 0) !== ($wb > 0)) return ($wb > 0) <=> ($wa > 0);
            if ($wa > 0 && $wb > 0) return $wa <=> $wb;
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        $seo = (new Seo())
            ->title(t('home.popular_teams'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('home.popular_teams'), path('teams')],
            ]);
        View::page('teams', ['teams' => array_slice($list, 0, 60)], $seo);
    }

    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if (!$id) View::notFound();
        Settings::trackHit('team');

        // (1) Real endpoint: /matches/team_matches/{id} — verified buckets
        // online/coming/end/postponed/cancel with full match objects. Gives
        // identity + old AND new matches for ANY team, no window dependency.
        $buckets  = Api::teamMatchesBuckets($id);
        $team     = $buckets['team'];
        $league   = $buckets['league'];
        $fixtures = $buckets['fixtures'];
        $results  = $buckets['results'];

        // (2) Merge the day-window scan (adds anything the buckets missed).
        $seenM = [];
        foreach (array_merge($fixtures, $results) as $x) $seenM[(int)($x['match_id'] ?? 0)] = true;
        for ($i = -7; $i <= 14; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                $h = team_of($m, 'home'); $a = team_of($m, 'away');
                $isHome = (int)($h['row_id'] ?? 0) === $id;
                $isAway = (int)($a['row_id'] ?? 0) === $id;
                if (!$isHome && !$isAway) continue;
                $team = $team ?: ($isHome ? $h : $a);
                $league = $league ?: ($m['championship'] ?? null);
                if (!empty($seenM[(int)($m['match_id'] ?? 0)])) continue;
                if (match_state($m)['key'] === 'finished') $results[] = $m; else $fixtures[] = $m;
            }
        }

        // (3) Registry — a team seen anywhere before is always resolvable.
        if (!$team && ($reg = Registry::team($id))) {
            $team = ['row_id' => $id, 'title' => $reg['name'], 'image' => $reg['image'], 'world_ranking' => $reg['wr']];
            if (!$league && !empty($reg['lid'])) $league = ['url_id' => $reg['lid'], 'title' => $reg['lname']];
        }
        if (!$team || team_name($team, '') === '') View::notFound();

        usort($fixtures, fn($a, $b) => (int)($a['match_timestamp'] ?? 0) <=> (int)($b['match_timestamp'] ?? 0));
        usort($results, fn($a, $b) => (int)($b['match_timestamp'] ?? 0) <=> (int)($a['match_timestamp'] ?? 0));

        $canonical = team_url(['row_id' => $id, 'title' => team_name($team)]);
        if ('/team/' . $slug !== preg_replace('#^/en#', '', $canonical)) {
            View::redirect($canonical, 301);
        }

        // Standing row + table for the team's league
        $leagueId = (int)($league['url_id'] ?? 0);
        $standingRows = []; $teamRow = null;
        if ($leagueId) {
            $st = Api::leagueStanding($leagueId);
            $standingRows = is_array($st['league'] ?? null)
                ? array_values(array_filter($st['league'], fn($r) => isset($r['team_id'])))
                : [];
            foreach ($standingRows as $r) {
                if ((int)($r['team_id'] ?? 0) === $id) { $teamRow = $r; break; }
            }
        }

        // ---- Squad: real {G,D,M,F,coach} from get_players_team (verified) ----
        $coach = null;
        $squadGroups = [];
        $squad = Api::teamSquad($id);
        if ($squad) {
            $coach = $squad['coach'] ?? null;
            $labels = ['F' => t('team.line_fw'), 'M' => t('team.line_mf'),
                       'D' => t('team.line_df'), 'G' => t('team.line_gk')];
            foreach (['F', 'M', 'D', 'G'] as $line) {
                if (!empty($squad[$line])) {
                    $squadGroups[] = ['label' => $labels[$line], 'players' => $squad[$line]];
                }
            }
        } else {
            // Fallback: aggregate from this team's recent lineups.
            $squadRaw = [];
            foreach (array_slice($results, 0, 6) as $rm) {
                $lu = Api::matchLineup((int)($rm['match_id'] ?? 0));
                $sides = is_array($lu['lineup'] ?? null) ? $lu['lineup'] : [];
                if (!isset($sides[$id]) || !is_array($sides[$id])) continue;
                foreach (['lineup', 'substitutions'] as $grp) {
                    foreach (($sides[$id][$grp] ?? []) as $lp) {
                        $p = $lp['player'] ?? [];
                        $pid = (int)($p['row_id'] ?? $p['id'] ?? 0);
                        if ($pid && !isset($squadRaw[$pid])) {
                            $squadRaw[$pid] = ['player' => $p, 'position' => $p['position'] ?? ($lp['position'] ?? '')];
                        }
                    }
                }
            }
            $squadGroups = self::groupSquad(array_values($squadRaw));
        }

        // Top players from league scorers filtered by this team
        $topPlayers = [];
        if ($leagueId) {
            $tname = team_name($team);
            foreach (Api::leagueScorers($leagueId) as $s) {
                $pi = $s['player_info'] ?? [];
                if (($pi['team_name'] ?? '') === $tname || (int)($pi['team_id'] ?? 0) === $id) $topPlayers[] = $s;
            }
        }

        // Team news → league news fallback
        $teamNews = Api::teamNews($id);
        if (!$teamNews && $leagueId) $teamNews = array_slice(Api::leagueNews($leagueId), 0, 8);

        // Simple team stats from recent results
        $won = $drawn = $lost = $gf = $ga = 0; $form = [];
        foreach (array_slice($results, 0, 10) as $rm) {
            $isHome = (int)(team_of($rm, 'home')['row_id'] ?? 0) === $id;
            $mine = (int)($isHome ? ($rm['home_scores'] ?? 0) : ($rm['away_scores'] ?? 0));
            $theirs = (int)($isHome ? ($rm['away_scores'] ?? 0) : ($rm['home_scores'] ?? 0));
            $gf += $mine; $ga += $theirs;
            if ($mine > $theirs) { $won++; $form[] = 'W'; }
            elseif ($mine < $theirs) { $lost++; $form[] = 'L'; }
            else { $drawn++; $form[] = 'D'; }
        }

        $seo = (new Seo())
            ->title(team_name($team))
            ->description(team_name($team) . ' — ' . t('team.fixtures') . ', ' . t('team.results') . ', ' . t('team.standing') . ', ' . t('team.news'))
            ->image(team_img($team, '128'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('home.popular_teams'), path('teams')],
                [team_name($team), $canonical],
            ]);
        $seo->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'SportsTeam',
            'name' => team_name($team),
            'sport' => 'Soccer',
            'logo' => SITE_URL . team_img($team, '128'),
            'memberOf' => $league ? ['@type' => 'SportsOrganization', 'name' => (string)($league['title'] ?? '')] : null,
        ]);

        View::page('team', [
            'id'         => $id,
            'team'       => $team,
            'league'     => $league,
            'fixtures'   => array_slice($fixtures, 0, 30),
            'results'    => array_slice($results, 0, 30),
            'standingRows' => $standingRows,
            'teamRow'    => $teamRow,
            'squadGroups'=> $squadGroups,
            'coach'      => $coach,
            'teamNews'   => $teamNews,
            'topPlayers' => array_slice($topPlayers, 0, 8),
            'summary'    => ['won' => $won, 'drawn' => $drawn, 'lost' => $lost, 'gf' => $gf, 'ga' => $ga, 'form' => $form],
        ], $seo);
    }

    /**
     * Group squad rows into GK / DF / MF / FW sections. Handles both position
     * codes (GK, LW, ST…) and the API's line letters (G/D/M/S/F), plus Arabic
     * position strings.
     */
    private static function groupSquad(array $rows): array
    {
        $lines = ['FW' => [], 'MF' => [], 'DF' => [], 'GK' => []];
        foreach ($rows as $row) {
            $p = $row['player'] ?? [];
            if (!is_array($p) || player_label($p, '') === '') continue;
            $line = self::positionLine((string)($row['position'] ?? $p['position'] ?? ''), $p);
            $lines[$line][] = $p;
        }
        $labels = [
            'GK' => t('team.line_gk'), 'DF' => t('team.line_df'),
            'MF' => t('team.line_mf'), 'FW' => t('team.line_fw'),
        ];
        // Reference order: forwards → midfield → defence → keepers
        $out = [];
        foreach (['FW', 'MF', 'DF', 'GK'] as $k) {
            if (!empty($lines[$k])) $out[] = ['label' => $labels[$k], 'players' => $lines[$k]];
        }
        return $out;
    }

    private static function positionLine(string $pos, array $p): string
    {
        $pos = trim($pos);
        // Arabic position strings from the API
        if (preg_match('/حارس|مرمى|جول/u', $pos)) return 'GK';
        if (preg_match('/مداف|ظهير|قلب دفاع|ستوبر/u', $pos)) return 'DF';
        if (preg_match('/مهاجم|هجوم|رأس حربة|جناح|صانع/u', $pos)) return 'FW';
        if (preg_match('/وسط|ارتكاز|لاعب وسط/u', $pos)) return 'MF';
        // Latin codes / API line letters
        $u = strtoupper($pos);
        if ($u === '' && !empty($p['player_number']) && (int)$p['player_number'] === 1) return 'GK';
        return match (true) {
            str_starts_with($u, 'G')                                   => 'GK',
            in_array($u, ['D', 'DF', 'CB', 'LB', 'RB', 'RWB', 'LWB'], true) || str_starts_with($u, 'D') => 'DF',
            in_array($u, ['F', 'FW', 'ST', 'CF', 'S', 'SS'], true) || str_starts_with($u, 'F') || $u === 'LW' || $u === 'RW' => 'FW',
            default                                                    => 'MF',
        };
    }
}
