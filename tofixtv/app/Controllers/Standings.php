<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\Lang;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\View;

final class Standings
{
    public static function index(): void
    {
        Settings::trackHit('standings');
        $tables = [];
        foreach (FAVORITE_LEAGUES as $f) {
            $st = Api::leagueStanding((int)$f['url_id']);
            $rows = is_array($st['league'] ?? null)
                ? array_values(array_filter($st['league'], fn($r) => isset($r['team_id'])))
                : [];
            if (count($rows) >= 3) {
                $tables[] = [
                    'url_id' => (int)$f['url_id'],
                    'title'  => $f[Lang::current()] ?? $f['ar'],
                    'rows'   => $rows,
                ];
            }
        }

        $seo = (new Seo())
            ->title(t('standings.title'))
            ->description(t('home.standings') . ' — ' . Lang::siteName())
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.standings'), path('standings')],
            ]);

        View::page('standings', ['tables' => $tables], $seo);
    }
}
