<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class Matches
{
    /** @param string $day today|tomorrow|yesterday|Y-m-d */
    public static function day(string $day): void
    {
        Settings::trackHit('matches');

        $named = [
            'today'     => date('Y-m-d'),
            'tomorrow'  => date('Y-m-d', strtotime('+1 day')),
            'yesterday' => date('Y-m-d', strtotime('-1 day')),
        ];
        $date = $named[$day] ?? $day;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) View::notFound();

        $matches = Api::matchesByDate($date);
        $grouped = group_matches_by_league($matches);

        $label = match ($day) {
            'today'     => t('day.today'),
            'tomorrow'  => t('day.tomorrow'),
            'yesterday' => t('day.yesterday'),
            default     => format_date_long($date),
        };

        $seo = (new Seo())
            ->title(t('matches.title') . ' — ' . $label)
            ->description(t('matches.title') . ' ' . format_date_long($date) . ' — ' . \Qamhad\Core\Lang::siteName())
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.matches'), path('matches')],
                [$label, path('matches/' . $date)],
            ]);
        // ItemList of the day's fixtures — each entry a complete SportsEvent.
        if ($matches) {
            $listName = \Qamhad\Core\Lang::current() === 'ar'
                ? ('مباريات ' . $label . ' بث مباشر')
                : ($label . ' matches live');
            $seo->addJsonLd(Seo::matchListSchema($matches, $listName, path('matches/' . $date)));
        }

        View::page('matches', [
            'date'     => $date,
            'dayKey'   => $day,
            'label'    => $label,
            'matches'  => $matches,
            'grouped'  => $grouped,
            'liveOnly' => false,
        ], $seo);
    }

    public static function live(): void
    {
        Settings::trackHit('live');
        $matches = array_values(array_filter(Api::matchesByDate(), fn($m) => match_state($m)['key'] === 'live'));
        $seo = (new Seo())
            ->title(t('home.live'))
            ->description(t('home.live') . ' — ' . \Qamhad\Core\Lang::siteName())
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.live'), path('live')],
            ]);
        if ($matches) {
            $listName = \Qamhad\Core\Lang::current() === 'ar'
                ? 'مباريات اليوم بث مباشر الآن'
                : 'Live matches now';
            $seo->addJsonLd(Seo::matchListSchema($matches, $listName, path('live')));
        }

        View::page('matches', [
            'date'     => date('Y-m-d'),
            'dayKey'   => 'live',
            'label'    => t('home.live'),
            'matches'  => $matches,
            'grouped'  => group_matches_by_league($matches),
            'liveOnly' => true,
        ], $seo);
    }
}
