<?php
declare(strict_types=1);

namespace Qamhad\Controllers;

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

/**
 * Multilingual sitemaps — all <loc> URLs live on the canonical domain
 * (https://www.qamhad.com) via absolute_url()/SITE_URL.
 *
 *  /sitemap.xml         sitemap INDEX → the five child sitemaps below
 *  /sitemap-ar.xml      Arabic urlset (static pages, leagues, teams, news)
 *  /sitemap-en.xml      English urlset
 *  /sitemap-match.xml   match pages, generated dynamically from the JSON
 *                       feed: live now, today, tomorrow, day after tomorrow,
 *                       upcoming week and recently finished — each with a
 *                       truthful <lastmod>, <changefreq> and <priority>
 *  /sitemap-news.xml    Google News (latest articles, ar + en)
 *  /sitemap-images.xml  image sitemap (news covers, team & league logos)
 *
 * Every <loc> is percent-encoded via absolute_url() — ASCII-safe.
 */
final class Sitemap
{
    private const XHTML = ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';

    public static function arabic(): void  { self::urlset(['ar']); }
    public static function english(): void { self::urlset(['en']); }

    /* ---------------- sitemap index ---------------- */

    public static function index(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        http_cache_validate(strtotime('today') ?: time(), 'sitemap-index-' . date('Y-m-d-H'));

        $now = date('c');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (['sitemap-ar.xml', 'sitemap-en.xml', 'sitemap-match.xml', 'sitemap-news.xml', 'sitemap-images.xml', 'sitemap-video.xml'] as $f) {
            echo '  <sitemap><loc>' . htmlspecialchars(SITE_URL . '/' . $f, ENT_XML1) . '</loc>'
                . '<lastmod>' . $now . '</lastmod></sitemap>' . "\n";
        }
        echo '</sitemapindex>';
        exit;
    }

    /* ---------------- per-language urlsets ---------------- */

    private static function urlset(array $langs): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        http_cache_validate(strtotime('today') ?: time(), 'sitemap-' . implode('', $langs) . '-' . date('Y-m-d-H'));

        $original = Lang::current();
        $urls = [];
        $todayMod = date('c', strtotime('today') ?: time());

        foreach ($langs as $lang) {
            Lang::boot($lang);
            $pfx = Lang::prefix();

            // Static pages — with hreflang alternates both ways
            $statics = ['', '/matches', '/live', '/today', '/tomorrow', '/yesterday',
                        '/leagues', '/teams', '/players', '/news', '/standings',
                        '/top-scorers', '/about', '/privacy', '/terms', '/contact'];
            foreach ($statics as $p) {
                $urls[] = [
                    'loc'  => absolute_url(($pfx . $p) ?: '/'),
                    'freq' => in_array($p, ['', '/matches', '/live', '/news'], true) ? 'hourly' : 'daily',
                    'prio' => $p === '' ? '1.0' : (in_array($p, ['/matches', '/live', '/news'], true) ? '0.9' : '0.6'),
                    'lastmod' => $todayMod,
                    'alt'  => [
                        'ar' => absolute_url($p ?: '/'),
                        'en' => absolute_url('/en' . $p),
                    ],
                ];
            }

            // Leagues
            foreach (array_slice(Api::allLeagues(), 0, 40) as $lg) {
                $id = (int)($lg['url_id'] ?? 0);
                if (!$id) continue;
                $urls[] = [
                    'loc' => absolute_url(league_url($lg)),
                    'freq' => 'daily', 'prio' => '0.7',
                    'lastmod' => $todayMod,
                    'alt' => [
                        'ar' => absolute_url("/league/{$id}"),
                        'en' => absolute_url("/en/league/{$id}"),
                    ],
                ];
            }

            // Teams from the active fixtures
            $teams = [];
            foreach ([date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))] as $d) {
                foreach (Api::matchesByDate($d) as $m) {
                    foreach (['home', 'away'] as $side) {
                        $t = team_of($m, $side);
                        $tid = (int)($t['row_id'] ?? 0);
                        if ($tid && !isset($teams[$tid])) $teams[$tid] = $t;
                    }
                }
            }
            foreach (array_slice($teams, 0, 80, true) as $tid => $t) {
                $urls[] = [
                    'loc' => absolute_url(team_url($t)),
                    'freq' => 'daily', 'prio' => '0.6',
                    'lastmod' => $todayMod,
                    'alt' => [
                        'ar' => absolute_url("/team/{$tid}"),
                        'en' => absolute_url("/en/team/{$tid}"),
                    ],
                ];
            }

            // Latest news (2 pages)
            for ($p = 1; $p <= 2; $p++) {
                foreach (Api::newsPage($p)['items'] as $n) {
                    $id = (int)($n['id'] ?? 0);
                    if (!$id) continue;
                    $urls[] = [
                        'loc' => absolute_url(news_url($n)),
                        'freq' => 'daily', 'prio' => '0.8',
                        'lastmod' => date('c', to_ts($n['created_at'] ?? null) ?: time()),
                        'alt' => [
                            'ar' => absolute_url("/news/{$id}"),
                            'en' => absolute_url("/en/news/{$id}"),
                        ],
                    ];
                }
            }
        }

        Lang::boot($original);
        self::emitUrlset($urls);
    }

    /* ---------------- match sitemap (dynamic, JSON-driven) ---------------- */

    /**
     * sitemap-match.xml — the crawl heartbeat of the site.
     *
     * Windows covered (generated from the live JSON feed on every request):
     *   live now              priority 1.0  changefreq always   lastmod now
     *   finished < 48h        priority 0.8  changefreq hourly   lastmod end
     *   today (upcoming)      priority 0.9  changefreq hourly   lastmod today
     *   tomorrow              priority 0.8  changefreq daily
     *   day after tomorrow    priority 0.7  changefreq daily
     *   upcoming (+3 … +6)    priority 0.6  changefreq daily
     *
     * Both language URLs are listed with xhtml:link hreflang alternates
     * (ar / en / x-default), so Google discovers every match page with its
     * SEO slug: /match/{home}-{away}-{id} → «مباراة X وY بث مباشر | قمهد لايف».
     */
    public static function matches(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');

        // Validate BEFORE building: a matching If-None-Match must not cost the
        // full build. The seed is a 5-minute time bucket — fresh enough for
        // live-score crawling, cheap enough that conditional GETs short-circuit.
        $bucket = intdiv(time(), 300);
        http_cache_validate($bucket * 300, 'sitemap-match-' . $bucket);

        // Whole-body disk cache: a cold build fans out to many upstream calls
        // (8 dates x 2 languages x 3 feed URLs); if the upstream is slow or
        // down that could exceed PHP's execution limit and 500 the sitemap.
        // Serving the last rendered body for 5 minutes caps the worst case.
        $bodyKey = 'sitemap-match-body-v1';
        $cached = \Qamhad\Core\Cache::get($bodyKey, 300);
        if (is_string($cached) && $cached !== '') {
            echo $cached;
            exit;
        }

        $original = Lang::current();
        $urls = [];
        $liveSeen = false;

        // day offset => [default priority, default changefreq]
        $windows = [
            -1 => ['0.8', 'hourly'],   // yesterday: fresh results
             0 => ['0.9', 'hourly'],   // today
             1 => ['0.8', 'daily'],    // tomorrow
             2 => ['0.7', 'daily'],    // day after tomorrow
             3 => ['0.6', 'daily'],
             4 => ['0.6', 'daily'],
             5 => ['0.6', 'daily'],
             6 => ['0.6', 'daily'],
        ];

        foreach (['ar', 'en'] as $lang) {
            Lang::boot($lang);
            foreach ($windows as $offset => [$prio, $freq]) {
                $date = date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' day'));
                foreach (Api::matchesByDate($date) as $m) {
                    $id = (int)($m['match_id'] ?? 0);
                    if (!$id) continue;

                    $state = match_state($m);
                    $key   = $state['key'] ?? 'upcoming';
                    $ts    = (int)($m['match_timestamp'] ?? 0) ?: to_ts($m['match_date'] ?? '');

                    if ($key === 'live') {
                        $p = '1.0'; $f = 'always'; $mod = time();
                        $liveSeen = true;
                    } elseif ($key === 'finished') {
                        $end = $ts ? $ts + 7200 : time();
                        $recent = (time() - $end) < 48 * 3600;
                        $p = $recent ? '0.8' : '0.6';
                        $f = $recent ? 'hourly' : 'daily';
                        $mod = min($end, time());
                    } else {
                        $p = $prio; $f = $freq;
                        // Upcoming pages change as kickoff nears (channels,
                        // lineups) — stamp today's build date, never a future
                        // time (a future lastmod would be discarded).
                        $mod = strtotime('today') ?: time();
                    }

                    $urls[] = [
                        'loc'     => absolute_url(match_url($m)),
                        'prio'    => $p,
                        'freq'    => $f,
                        'lastmod' => date('c', $mod),
                        'alt'     => [
                            'ar' => absolute_url("/match/{$id}"),
                            'en' => absolute_url("/en/match/{$id}"),
                        ],
                    ];
                }
            }
        }

        Lang::boot($original);

        ob_start();
        self::emitUrlset($urls, false);
        $xml = (string)ob_get_clean();
        \Qamhad\Core\Cache::set($bodyKey, $xml);
        echo $xml;
        exit;
    }

    /* ---------------- shared urlset emitter ---------------- */

    /** @param array<int,array<string,mixed>> $urls */
    private static function emitUrlset(array $urls, bool $terminate = true): void
    {
        $seen = [];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . self::XHTML . '>' . "\n";
        foreach ($urls as $u) {
            if (isset($seen[$u['loc']])) continue;
            $seen[$u['loc']] = true;
            echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            foreach (($u['alt'] ?? []) as $hl => $href) {
                echo '<xhtml:link rel="alternate" hreflang="' . $hl . '" href="' . htmlspecialchars($href, ENT_XML1) . '"/>';
            }
            if (!empty($u['alt']['ar'])) {
                echo '<xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($u['alt']['ar'], ENT_XML1) . '"/>';
            }
            if (!empty($u['lastmod'])) echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
            echo '<changefreq>' . $u['freq'] . '</changefreq><priority>' . $u['prio'] . '</priority></url>' . "\n";
        }
        echo '</urlset>';
        if ($terminate) exit;
    }

    /* ---------------- Google News (ar + en) ---------------- */

    public static function news(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=1800');

        $original = Lang::current();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        foreach (['ar' => SITE_NAME_AR, 'en' => SITE_NAME_EN] as $lang => $pubName) {
            Lang::boot($lang);
            foreach (array_slice(Api::newsPage(1)['items'], 0, 50) as $n) {
                $loc = absolute_url(news_url($n));
                $date = date('c', to_ts($n['created_at'] ?? null) ?: time());
                $title = htmlspecialchars((string)($n['title'] ?? ''), ENT_XML1);
                echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
                    . '<news:news><news:publication><news:name>' . htmlspecialchars($pubName, ENT_XML1) . '</news:name>'
                    . '<news:language>' . $lang . '</news:language></news:publication>'
                    . '<news:publication_date>' . $date . '</news:publication_date>'
                    . '<news:title>' . $title . '</news:title></news:news></url>' . "\n";
            }
        }

        Lang::boot($original);
        echo '</urlset>';
        exit;
    }

    /* ---------------- Image sitemap ---------------- */

    public static function images(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        $original = Lang::current();
        Lang::boot('ar');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        // News covers — 1200px renders for Google Discover eligibility
        foreach (array_slice(Api::newsPage(1)['items'], 0, 30) as $n) {
            if (empty($n['image'])) continue;
            echo '  <url><loc>' . htmlspecialchars(absolute_url(news_url($n)), ENT_XML1) . '</loc>'
                . '<image:image><image:loc>' . htmlspecialchars(absolute_url(news_img($n, '1200')), ENT_XML1) . '</image:loc>'
                . '<image:title>' . htmlspecialchars((string)($n['title'] ?? ''), ENT_XML1) . '</image:title></image:image></url>' . "\n";
        }

        // Team + league logos from today's fixtures
        $done = [];
        foreach (Api::matchesByDate() as $m) {
            $lg = $m['championship'] ?? [];
            if (!empty($lg['image']) && empty($done['l' . $lg['url_id']])) {
                $done['l' . $lg['url_id']] = true;
                echo '  <url><loc>' . htmlspecialchars(absolute_url(league_url($lg)), ENT_XML1) . '</loc>'
                    . '<image:image><image:loc>' . htmlspecialchars(absolute_url(league_img($lg, '96')), ENT_XML1) . '</image:loc>'
                    . '<image:title>' . htmlspecialchars((string)($lg['title'] ?? ''), ENT_XML1) . '</image:title></image:image></url>' . "\n";
            }
            foreach (['home', 'away'] as $side) {
                $t = team_of($m, $side);
                $tid = (int)($t['row_id'] ?? 0);
                if ($tid && !empty($t['image']) && empty($done['t' . $tid])) {
                    $done['t' . $tid] = true;
                    echo '  <url><loc>' . htmlspecialchars(absolute_url(team_url($t)), ENT_XML1) . '</loc>'
                        . '<image:image><image:loc>' . htmlspecialchars(absolute_url(team_img($t, '128')), ENT_XML1) . '</image:loc>'
                        . '<image:title>' . htmlspecialchars(team_name($t), ENT_XML1) . '</image:title></image:image></url>' . "\n";
                }
            }
        }

        Lang::boot($original);
        echo '</urlset>';
        exit;
    }

    /**
     * sitemap-video.xml — Google video sitemap for the highlights section.
     * Built from the first pages of the cached Btolat feed (fast: cache-hit
     * for regular traffic, one upstream fetch per TTL otherwise). Every
     * player_loc points at the SITE's own /video/{id} page.
     */
    public static function videos(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        http_cache_validate(strtotime('today') ?: time(), 'sitemap-video-' . date('Y-m-d-H'));

        // Up to 6 site pages (30 newest videos) — snappy and always fresh.
        $items = [];
        for ($p = 1; $p <= 6; $p++) {
            $res = \Qamhad\Core\VideoFeed::page('all', $p);
            foreach ($res['items'] as $v) $items[] = $v;
            if (empty($res['has_next'])) break;
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        $seen = [];
        foreach ($items as $v) {
            $id = (int)($v['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $loc   = absolute_url(path('video/' . $id));
            $title = htmlspecialchars((string)$v['title'], ENT_XML1);
            $thumb = htmlspecialchars((string)($v['thumbnail'] ?: SITE_URL . '/assets/brand/og-default.png'), ENT_XML1);
            $ts    = to_ts((string)($v['created_at'] ?? ''));
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
                . '<video:video>'
                . '<video:thumbnail_loc>' . $thumb . '</video:thumbnail_loc>'
                . '<video:title>' . $title . '</video:title>'
                . '<video:description>' . $title . '</video:description>'
                . '<video:player_loc>' . htmlspecialchars($loc, ENT_XML1) . '</video:player_loc>'
                . ($ts ? '<video:publication_date>' . date('c', $ts) . '</video:publication_date>' : '')
                . '<video:family_friendly>yes</video:family_friendly>'
                . '<video:live>no</video:live>'
                . '</video:video></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }
}
