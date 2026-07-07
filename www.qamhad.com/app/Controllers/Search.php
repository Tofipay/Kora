<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Seo;
use Qamhad\Core\Settings;
use Qamhad\Core\View;

final class Search
{
    public static function index(): void
    {
        Settings::trackHit('search');
        $q = trim((string)($_GET['q'] ?? ''));
        $q = mb_substr(strip_tags($q), 0, 80);

        $teams = []; $leagues = []; $matches = []; $news = []; $players = [];

        if (mb_strlen($q) >= 2) {
            // Real search API (/api/search/{query}) — players + teams upstream.
            $api = Api::search($q);
            foreach ($api['teams'] as $tRow) {
                $t = $tRow['name'] ?? null;
                if (is_array($t) && !empty($t['row_id'])) $teams[(int)$t['row_id']] = $t;
            }
            foreach ($api['player'] as $pRow) {
                $p = $pRow['name'] ?? null;
                if (is_array($p) && !empty($p['row_id'])) $players[(int)$p['row_id']] = $p;
            }

            $needle = mb_strtolower($q);
            $has = fn($s) => $s !== '' && mb_strpos(mb_strtolower((string)$s), $needle) !== false;

            // Matches + teams within the active window
            for ($i = -3; $i <= 7; $i++) {
                $d = date('Y-m-d', strtotime("{$i} days"));
                foreach (Api::matchesByDate($d) as $m) {
                    $h = team_of($m, 'home'); $a = team_of($m, 'away');
                    $hHit = $has($h['title'] ?? '') || $has($h['full_title'] ?? '');
                    $aHit = $has($a['title'] ?? '') || $has($a['full_title'] ?? '');
                    if ($hHit || $aHit || $has($m['championship']['title'] ?? '')) {
                        $key = (int)($m['match_id'] ?? 0);
                        $matches[$key] = $m;
                    }
                    foreach ([[$h, $hHit], [$a, $aHit]] as [$tm, $hit]) {
                        if ($hit) $teams[(int)($tm['row_id'] ?? 0)] = $tm;
                    }
                }
            }

            foreach (Api::allLeagues() as $lg) {
                if ($has($lg['title'] ?? '')) $leagues[] = $lg;
            }

            for ($p = 1; $p <= 3; $p++) {
                foreach (Api::newsPage($p)['items'] as $n) {
                    if ($has($n['title'] ?? '') || $has($n['news_desc'] ?? '')) $news[] = $n;
                }
            }
        }

        $seo = (new Seo())
            ->title($q !== '' ? t('search.results', ['q' => $q]) : t('search.title'))
            ->canonical(path('search'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('search.title'), path('search')],
            ]);

        View::page('search', [
            'q'       => $q,
            'teams'   => array_values($teams),
            'players' => array_slice(array_values($players), 0, 16),
            'leagues' => array_slice($leagues, 0, 12),
            'matches' => array_slice(array_values($matches), 0, 20),
            'news'    => array_slice($news, 0, 12),
        ], $seo);
    }
}
