<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\Lang;
use TofiXTv\Core\Registry;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\View;

final class Players
{
    /** Star players across the pinned leagues (top scorers). */
    public static function index(): void
    {
        Settings::trackHit('players');
        $players = [];
        foreach (array_slice(FAVORITE_LEAGUES, 0, 6) as $f) {
            foreach (array_slice(Api::leagueScorers((int)$f['url_id']), 0, 10) as $s) {
                $pi = $s['player_info'] ?? [];
                $id = (int)($pi['id'] ?? $s['player_id'] ?? 0);
                if (!$id || isset($players[$id])) continue;
                $s['league'] = ['url_id' => $f['url_id'], 'title' => $f[Lang::current()] ?? $f['ar']];
                $players[$id] = $s;
            }
        }
        $list = array_values($players);
        usort($list, fn($a, $b) => (int)($b['goals'] ?? 0) <=> (int)($a['goals'] ?? 0));

        $seo = (new Seo())
            ->title(t('home.top_scorers'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('home.top_scorers'), path('players')],
            ]);
        View::page('players', ['players' => array_slice($list, 0, 50)], $seo);
    }

    public static function show(string $slug): void
    {
        $id = id_from_slug($slug);
        if (!$id) View::notFound();
        Settings::trackHit('player');

        $hint = query_int('lg');
        $info = null; $comps = []; $totals = ['goals' => 0, 'assists' => 0, 'penalties' => 0];
        $leagueTitle = '';
        $transfers = []; $description = '';

        // (0) FULL profile from the ysscores player page — vitals + statistics
        //     broken down per competition + transfer history. This is the
        //     primary source (same structure as the reference scraper). The
        //     name-slug is cosmetic; ysscores resolves the page by numeric id.
        $nameSlug = trim(preg_replace('/-?\d+$/', '', $slug), '-');
        $full = Api::playerFull($id, $nameSlug);
        $profile = [];
        if (!empty($full)) {
            $description = (string)($full['description'] ?? '');
            $transfers   = is_array($full['transfers'] ?? null) ? $full['transfers'] : [];
            $pr = is_array($full['profile'] ?? null) ? $full['profile'] : [];
            // Map the scraped profile to our normalized vital keys.
            $profile = array_filter([
                'position_name' => (string)($pr['position'] ?? ''),
                'age'           => isset($pr['age'])    ? (int)$pr['age']    : null,
                'height'        => isset($pr['height']) ? (int)$pr['height'] : null,
                'weight'        => isset($pr['weight']) ? (int)$pr['weight'] : null,
                'foot'          => (string)($pr['foot'] ?? ''),
                'image'         => \TofiXTv\Core\Www::cdnFile((string)($full['image'] ?? '')),
                'country_image' => $full['country_image'] ?? null,
                'cover'         => $full['cover'] ?? null,
            ], fn($v) => $v !== null && $v !== '');
            if (!empty($full['name'])) {
                $info = ['id' => $id, 'title' => (string)$full['name'], 'image' => $profile['image'] ?? null];
            }
            // Per-competition statistics → $comps (+ running totals).
            foreach (($full['statistics'] ?? []) as $compName => $st) {
                if (!is_array($st) || $compName === '') continue;
                $stats = self::mapScrapedStats($st);
                $comps[] = ['id' => 0, 'title' => (string)$compName, 'image' => null, 'stats' => $stats];
                $totals['goals']     += $stats['goals'];
                $totals['assists']   += $stats['assists'];
                $totals['penalties'] += $stats['penalties'];
                $leagueTitle = $leagueTitle ?: (string)$compName;
            }
        }
        // Vitals fallback: the lighter text scrape, only if the full one missed.
        if (empty($profile)) $profile = Api::playerProfile($id);

        // (2) Identity + stats: scan scorers/assists across leagues.
        //     Optional ?lg hint tried first. Only when the full scrape gave
        //     no per-competition statistics (offline / structure changed).
        if (!$info || !$comps) {
            $leagueIds = [];
            if ($hint) $leagueIds[] = $hint;
            $leagueIds = array_merge($leagueIds, array_column(FAVORITE_LEAGUES, 'url_id'));
            foreach (array_slice(Api::allLeagues(), 0, 12) as $lg) $leagueIds[] = (int)$lg['url_id'];

            foreach (array_unique($leagueIds) as $lid) {
                $g = null; $a = null; $pen = 0;
                foreach (Api::leagueScorers((int)$lid) as $s) {
                    if ((int)(($s['player_info']['id'] ?? $s['player_id'] ?? 0)) === $id) {
                        $info = $info ?: ($s['player_info'] ?? []);
                        $g = (int)($s['goals'] ?? 0);
                        $pen = (int)($s['score_penalty'] ?? 0);
                    }
                }
                foreach (Api::leagueAssists((int)$lid) as $s) {
                    if ((int)(($s['player_info']['id'] ?? $s['player_id'] ?? 0)) === $id) {
                        $info = $info ?: ($s['player_info'] ?? []);
                        $a = (int)($s['assist'] ?? $s['assists'] ?? 0);
                    }
                }
                if ($g !== null || $a !== null) {
                    $title = self::leagueTitle((int)$lid);
                    $comps[] = ['id' => (int)$lid, 'title' => $title,
                                'stats' => ['goals' => (int)$g, 'assists' => (int)$a, 'penalties' => $pen]];
                    $totals['goals'] += (int)$g; $totals['assists'] += (int)$a; $totals['penalties'] += $pen;
                    $leagueTitle = $leagueTitle ?: $title;
                }
            }
        }

        // (3) Registry — a player seen anywhere before is always resolvable
        //     (search, squads, lineups and scorers all feed it).
        if (!$info && ($reg = Registry::player($id))) {
            $info = ['id' => $id, 'title' => $reg['name'], 'full_title' => $reg['full'],
                     'image' => $reg['image'], 'pn' => $reg['number'], 'country' => $reg['country'],
                     'team_name' => $reg['team'], 'position' => $reg['pos'] ?? ''];
        }
        // (4) Search API — resolves brand-new deep links by id via name lookup impossible,
        //     but the scraped profile alone can carry the page if it found a name.
        if ((!is_array($info) || player_label($info, '') === '') && !empty($profile)) {
            $info = ['id' => $id, 'title' => (string)($profile['title'] ?? ''), 'image' => $profile['image'] ?? null];
        }
        if (!is_array($info) || player_label($info, '') === '') View::notFound();

        // Merge scraped vitals into the normalized model (scrape wins for
        // age/height/weight/nationality/foot; identity fields keep API values).
        foreach (['age', 'height', 'weight', 'nationality', 'foot', 'position_name', 'country_image', 'cover'] as $k) {
            if (!empty($profile[$k]) && empty($info[$k])) $info[$k] = $profile[$k];
        }

        $name = player_label($info, '');
        $player = self::normalize($info, $id);

        $canonical = player_url(['id' => $id, 'title' => $name]);
        if ('/player/' . $slug !== preg_replace('#^/en#', '', $canonical)) {
            View::redirect($canonical . ($hint ? '?lg=' . $hint : ''), 301);
        }

        $seo = (new Seo())
            ->title($name)
            ->description($description !== '' ? $description : ($name . ' — ' . t('player.stats') . ($leagueTitle ? ' · ' . $leagueTitle : '')))
            ->image(player_img($info, '64'))
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('home.top_scorers'), path('players')],
                [$name, $canonical],
            ]);
        $seo->addJsonLd(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $name,
            'image' => SITE_URL . player_img($info, '64'),
            'jobTitle' => $player['position_label'] ?: null,
            'nationality' => $player['nationality'] ?: null,
            'affiliation' => $player['team'] ? ['@type' => 'SportsTeam', 'name' => $player['team']] : null,
        ], fn($v) => $v !== null));

        View::page('player', [
            'id'          => $id,
            'p'           => $info,
            'player'      => $player,
            'name'        => $name,
            'comps'       => $comps,
            'totals'      => $totals,
            'leagueTitle' => $leagueTitle,
            'transfers'   => $transfers,
            'description' => $description,
        ], $seo);
    }

    /** Map a scraped per-competition stat row to our view stat keys. */
    private static function mapScrapedStats(array $st): array
    {
        $i = fn(string $k) => (int)($st[$k] ?? 0);
        return [
            'appearances'  => $i('appearances'),
            'goals'        => $i('goals'),
            'assists'      => $i('assists'),
            'yellow'       => $i('yellow_cards'),
            'red'          => $i('red_cards'),
            'penalties'    => $i('penalties_scored'),
            'miss_pen'     => $i('penalties_missed'),
            'disallowed'   => $i('disallowed_goals'),
            'motm'         => $i('man_of_the_match'),
            'started'      => $i('started'),
            'sub'          => $i('substitute'),
            'team_matches' => $i('total_team_matches'),
            'minutes'      => 0, 'shots' => 0, 'passes' => 0, 'own_goal' => 0,
        ];
    }

    /** First non-empty value across many possible key spellings. */
    private static function pick(array $a, array $keys, $default = '')
    {
        foreach ($keys as $k) {
            if (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null && $a[$k] !== 0 && $a[$k] !== '0') return $a[$k];
        }
        return $default;
    }

    /** Normalize the player payload into a stable view model (defensive keys). */
    private static function normalize(array $info, int $id): array
    {
        $country = (int)self::pick($info, ['country', 'nationality_id', 'country_id'], 0);
        // The API may give the position as a code (ST) OR an Arabic label ("مهاجم ثاني").
        $posLabel = (string)self::pick($info, [
            'position_name', 'center', 'player_center', 'position_title',
            'line_name', 'position_ar', 'position_name_ar',
        ], '');
        $posCode = (string)self::pick($info, ['position', 'player_position', 'pos', 'line'], '');
        // If the "code" field actually contains Arabic, treat it as the label.
        if ($posLabel === '' && preg_match('/\p{Arabic}/u', $posCode)) { $posLabel = $posCode; $posCode = ''; }
        return [
            'id'             => $id,
            'name'           => player_label($info, ''),
            'full'           => (string)self::pick($info, ['full_title', 'full_name', 'title'], ''),
            'number'         => (int)self::pick($info, ['pn', 'player_number', 'shirt_number', 'number', 'jersey'], 0),
            'team'           => (string)self::pick($info, ['team_name', 'team_title', 'current_team'], ''),
            'team_id'        => (int)self::pick($info, ['team_id', 'current_team_id'], 0),
            'team_image'     => self::pick($info, ['team_image', 'team_logo', 'team_img'], null),
            'age'            => self::age($info),
            'height'         => (int)self::pick($info, ['height', 'player_height', 'tall'], 0),
            'weight'         => (int)self::pick($info, ['weight', 'player_weight'], 0),
            'position'       => $posCode,
            'position_label' => $posLabel !== '' ? $posLabel : self::positionLabel($posCode, ''),
            'country'        => $country,
            'country_image'  => self::pick($info, ['country_image', 'country_flag', 'flag', 'nationality_image', 'country_img'], null),
            'nationality'    => (string)self::pick($info, ['country_name', 'nationality', 'nationality_name', 'country_title'], ''),
            'foot'           => (string)self::pick($info, ['foot', 'preferred_foot', 'strong_foot'], ''),
        ];
    }

    private static function age(array $info): int
    {
        if (!empty($info['age'])) return (int)$info['age'];
        $dob = $info['birth_date'] ?? $info['date_of_birth'] ?? $info['dob'] ?? $info['birthday'] ?? null;
        if ($dob) { $ts = strtotime((string)$dob); if ($ts && $ts < time()) return (int)floor((time() - $ts) / 31557600); }
        return 0;
    }

    private static function positionLabel(string $pos, string $fallback): string
    {
        if ($fallback !== '') return $fallback;
        $ar = Lang::current() === 'ar';
        $map = [
            'GK' => $ar ? 'حارس مرمى' : 'Goalkeeper',
            'DF' => $ar ? 'مدافع' : 'Defender', 'D' => $ar ? 'مدافع' : 'Defender',
            'MF' => $ar ? 'وسط' : 'Midfielder', 'M' => $ar ? 'وسط' : 'Midfielder',
            'FW' => $ar ? 'مهاجم' : 'Forward', 'F' => $ar ? 'مهاجم' : 'Forward',
            'CB' => $ar ? 'قلب دفاع' : 'Centre-Back', 'LB' => $ar ? 'ظهير أيسر' : 'Left-Back',
            'RB' => $ar ? 'ظهير أيمن' : 'Right-Back', 'CM' => $ar ? 'وسط' : 'Centre Midfield',
            'LW' => $ar ? 'جناح أيسر' : 'Left Wing', 'RW' => $ar ? 'جناح أيمن' : 'Right Wing',
            'ST' => $ar ? 'قلب هجوم' : 'Striker',
        ];
        return $map[strtoupper($pos)] ?? $pos;
    }

    /** Pull a per-competition stats list from various possible shapes. */
    private static function extractCompetitions(array $raw): array
    {
        $out = [];
        $lists = [];
        foreach (['statics', 'stats', 'championships', 'competitions', 'player_stats'] as $k) {
            if (isset($raw[$k]) && is_array($raw[$k])) { $lists = $raw[$k]; break; }
        }
        foreach ($lists as $row) {
            if (!is_array($row)) continue;
            $c = $row['championship'] ?? $row;
            $out[] = [
                'id'    => (int)($c['url_id'] ?? $c['id'] ?? 0),
                'title' => (string)($c['title'] ?? $row['title'] ?? ''),
                'image' => $c['image'] ?? null,
                'stats' => [
                    'appearances' => (int)($row['play'] ?? $row['matches'] ?? $row['appearances'] ?? 0),
                    'goals'       => (int)($row['goals'] ?? $row['goal'] ?? 0),
                    'assists'     => (int)($row['assist'] ?? $row['assists'] ?? 0),
                    'minutes'     => (int)($row['minutes'] ?? 0),
                    'yellow'      => (int)($row['yellow'] ?? $row['yellow_cards'] ?? 0),
                    'red'         => (int)($row['red'] ?? $row['red_cards'] ?? 0),
                    'own_goal'    => (int)($row['own_goal'] ?? 0),
                    'penalties'   => (int)($row['score_penalty'] ?? $row['penalties'] ?? 0),
                    'miss_pen'    => (int)($row['miss_penalty'] ?? 0),
                    'shots'       => (int)($row['shots'] ?? 0),
                    'passes'      => (int)($row['passes'] ?? 0),
                ],
            ];
        }
        return $out;
    }

    private static function leagueTitle(int $id): string
    {
        foreach (FAVORITE_LEAGUES as $f) {
            if ((int)$f['url_id'] === $id) return $f[Lang::current()] ?? $f['ar'];
        }
        return '';
    }
}
