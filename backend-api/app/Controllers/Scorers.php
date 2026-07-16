<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Lang;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class Scorers
{
    public static function index(): void
    {
        Settings::trackHit('scorers');
        $boards = [];
        foreach (FAVORITE_LEAGUES as $f) {
            $scorers = array_slice(Api::leagueScorers((int)$f['url_id']), 0, 10);
            if (count($scorers) >= 3) {
                $boards[] = [
                    'url_id'  => (int)$f['url_id'],
                    'title'   => $f[Lang::current()] ?? $f['ar'],
                    'scorers' => $scorers,
                ];
            }
        }

        $seo = (new Seo())
            ->title(t('scorers.title'))
            ->description(t('home.top_scorers') . ' — ' . Lang::siteName())
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.scorers'), path('top-scorers')],
            ]);

        View::page('scorers', ['boards' => $boards], $seo);
    }
}
