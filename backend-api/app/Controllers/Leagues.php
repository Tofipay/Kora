<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class Leagues
{
    public static function index(): void
    {
        Settings::trackHit('leagues');
        $leagues = Api::allLeagues();
        $seo = (new Seo())
            ->title(t('leagues.title'))
            ->description(t('leagues.title') . ' — ' . \Qamhad\Core\Lang::siteName())
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.leagues'), path('leagues')],
            ]);
        View::page('leagues', ['leagues' => $leagues], $seo);
    }

    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if (!$id) View::notFound();
        Settings::trackHit('league');

        $standing = Api::leagueStanding($id);
        $rows = is_array($standing['league'] ?? null)
            ? array_values(array_filter($standing['league'], fn($r) => isset($r['team_id'])))
            : [];
        $rules = is_array($standing['rules_list'] ?? null) ? $standing['rules_list'] : [];

        $scorers = Api::leagueScorers($id);
        $assists = Api::leagueAssists($id);
        $news = array_slice(Api::leagueNews($id), 0, 8);

        // Fixtures/results: scan a window of cached day fixtures for this league
        $fixtures = []; $results = []; $title = ''; $image = null;
        for ($i = -7; $i <= 10; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                if ((int)($m['championship']['url_id'] ?? 0) !== $id) continue;
                $title = $title ?: (string)($m['championship']['title'] ?? '');
                $image = $image ?: ($m['championship']['image'] ?? null);
                if (match_state($m)['key'] === 'finished') $results[] = $m;
                else $fixtures[] = $m;
            }
        }
        usort($fixtures, fn($a, $b) => (int)($a['match_timestamp'] ?? 0) <=> (int)($b['match_timestamp'] ?? 0));
        usort($results, fn($a, $b) => (int)($b['match_timestamp'] ?? 0) <=> (int)($a['match_timestamp'] ?? 0));

        if ($title === '') {
            foreach (FAVORITE_LEAGUES as $f) {
                if ((int)$f['url_id'] === $id) { $title = $f[\Qamhad\Core\Lang::current()] ?? $f['ar']; break; }
            }
        }
        $image = $image ?: Api::leagueImage($id);
        if ($title === '' && empty($rows) && empty($scorers) && empty($fixtures) && empty($results)) {
            View::notFound();
        }
        $title = $title ?: t('nav.leagues');

        $canonical = league_url(['url_id' => $id, 'title' => $title]);
        if ('/league/' . $slug !== preg_replace('#^/en#', '', $canonical)) {
            View::redirect($canonical, 301);
        }

        $seo = (new Seo())
            ->title($title . ' — ' . t('standings.title') . '، ' . t('scorers.title'))
            ->description($title . ': ' . t('standings.title') . ', ' . t('league.fixtures') . ', ' . t('league.results') . ', ' . t('scorers.title'))
            ->image($image ? league_img($image, '96') : '/assets/brand/og-default.png')
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.leagues'), path('leagues')],
                [$title, $canonical],
            ]);

        View::page('league', [
            'id'       => $id,
            'title'    => $title,
            'image'    => $image,
            'rows'     => $rows,
            'rules'    => $rules,
            'scorers'  => $scorers,
            'assists'  => $assists,
            'news'     => $news,
            'fixtures' => array_slice($fixtures, 0, 30),
            'results'  => array_slice($results, 0, 30),
        ], $seo);
    }
}
