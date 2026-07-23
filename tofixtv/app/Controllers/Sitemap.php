<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\ChannelCatalog;
use TofiXTv\Core\Lang;

/**
 * Multilingual sitemaps — all <loc> URLs live on the canonical domain
 * (https://aloka-code.shop) via absolute_url()/SITE_URL.
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
        header('Cache-Control: public, max-age=1800');
        http_cache_validate(strtotime('today') ?: time(), 'sitemap-index-' . date('Y-m-d-H'));

        // Child <loc> URLs are CLEAN — no query-string version tokens.
        // Freshness is signalled the standard way: through <lastmod> here and
        // truthful lastmod/changefreq values inside each child sitemap.
        $children = [
            'sitemap-ar.xml',
            'sitemap-en.xml',
            'sitemap-match.xml',
            'sitemap-news.xml',
            'sitemap-video.xml',
            'sitemap-images.xml',
            'sitemap-cinema.xml',
        ];

        $now = date('c');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($children as $f) {
            echo "  <sitemap>\n"
                . '    <loc>' . htmlspecialchars(SITE_URL . '/' . $f, ENT_XML1) . "</loc>\n"
                . '    <lastmod>' . $now . "</lastmod>\n"
                . "  </sitemap>\n";
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

            // Site pages — with hreflang alternates both ways.
            // changefreq/priority per page class:
            //   home 1.0 · live hub always/0.9 · hot hubs hourly/0.9 ·
            //   listing pages daily/0.6 · legal/static pages yearly/0.5
            $legal = ['/about', '/privacy', '/terms', '/contact'];
            $hot   = ['/matches', '/news', '/videos', '/channels'];
            $statics = ['', '/matches', '/live', '/today', '/tomorrow', '/yesterday',
                        '/leagues', '/teams', '/players', '/news', '/videos', '/standings',
                        '/top-scorers', '/channels', '/about', '/privacy', '/terms', '/contact'];
            foreach ($statics as $p) {
                if ($p === '') { $freq = 'hourly'; $prio = '1.0'; }
                elseif ($p === '/live') { $freq = 'always'; $prio = '0.9'; }
                elseif (in_array($p, $hot, true)) { $freq = 'hourly'; $prio = '0.9'; }
                elseif (in_array($p, $legal, true)) { $freq = 'yearly'; $prio = '0.5'; }
                else { $freq = 'daily'; $prio = '0.6'; }
                $urls[] = [
                    'loc'  => absolute_url(($pfx . $p) ?: '/'),
                    'freq' => $freq,
                    'prio' => $prio,
                    'lastmod' => $todayMod,
                    'alt'  => [
                        'ar' => absolute_url($p ?: '/'),
                        'en' => absolute_url('/en' . $p),
                    ],
                ];
            }

            // Standalone channel groups — all data remains admin-managed JSON.
            foreach (ChannelCatalog::categories() as $category) {
                foreach (ChannelCatalog::groupsForCategory((int)$category['id']) as $group) {
                    $ar = '/channels/' . $category['slug'] . '/' . $group['slug'];
                    $en = '/en' . $ar;
                    $urls[] = [
                        'loc' => absolute_url(channel_group_url($category, $group)),
                        'freq' => 'daily', 'prio' => '0.7', 'lastmod' => $todayMod,
                        'alt' => ['ar' => absolute_url($ar), 'en' => absolute_url($en)],
                    ];
                }
            }

            // Leagues — weekly / 0.7
            foreach (array_slice(Api::allLeagues(), 0, 40) as $lg) {
                $id = (int)($lg['url_id'] ?? 0);
                if (!$id) continue;
                $urls[] = [
                    'loc' => absolute_url(league_url($lg)),
                    'freq' => 'weekly', 'prio' => '0.7',
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
            // Teams — weekly / 0.6
            foreach (array_slice($teams, 0, 80, true) as $tid => $t) {
                $urls[] = [
                    'loc' => absolute_url(team_url($t)),
                    'freq' => 'weekly', 'prio' => '0.6',
                    'lastmod' => $todayMod,
                    'alt' => [
                        'ar' => absolute_url("/team/{$tid}"),
                        'en' => absolute_url("/en/team/{$tid}"),
                    ],
                ];
            }

            // News archive (4 pages deep, newest first)
            for ($p = 1; $p <= 4; $p++) {
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
     * SEO slug: /match/{home}-{away}-{id} → «مباراة X وY بث مباشر | ALOKA Live».
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
        $bodyKey = 'sitemap-match-body-v3';
        $cached = \TofiXTv\Core\Cache::get($bodyKey, 300);
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
        \TofiXTv\Core\Cache::set($bodyKey, $xml);
        echo $xml;
        exit;
    }

    /* ---------------- Cinema sitemap (movies & series) ---------------- */

    /**
     * sitemap-cinema.xml — hub pages, genre pages and the detail pages of the
     * currently trending/popular movies & series (both languages, hreflang
     * alternates like the sports sets). Powered by the same disk-cached TMDB
     * payloads the pages themselves render from, so it costs no extra quota.
     */
    public static function cinema(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        http_cache_validate(strtotime('today') ?: time(), 'sitemap-cinema-' . date('Y-m-d-H'));

        $bodyKey = 'sitemap-cinema-body-v3';
        $cached = \TofiXTv\Core\Cache::get($bodyKey, 3600);
        if (is_string($cached) && $cached !== '') {
            echo $cached;
            exit;
        }

        $original = Lang::current();
        $urls = [];
        $todayMod = date('c', strtotime('today') ?: time());

        foreach (['ar', 'en'] as $lang) {
            Lang::boot($lang);
            $pfx = Lang::prefix();

            // Hubs
            foreach (['/movies', '/series'] as $p) {
                $urls[] = [
                    'loc' => absolute_url($pfx . $p),
                    'freq' => 'daily', 'prio' => '0.9', 'lastmod' => $todayMod,
                    'alt' => ['ar' => absolute_url($p), 'en' => absolute_url('/en' . $p)],
                ];
            }

            // Genre pages
            foreach ([['movie', '/movies'], ['tv', '/series']] as [$type, $section]) {
                foreach (\TofiXTv\Core\Tmdb::genreMap($type) as $gid => $gname) {
                    $slug = slugify($gname, 'genre') . '-' . $gid;
                    $urls[] = [
                        'loc' => absolute_url($pfx . $section . '/genre/' . $slug),
                        'freq' => 'weekly', 'prio' => '0.6', 'lastmod' => $todayMod,
                        'alt' => [
                            'ar' => absolute_url($section . '/genre/' . $slug),
                            'en' => absolute_url('/en' . $section . '/genre/' . $slug),
                        ],
                    ];
                }
            }

            // Trending + popular detail pages
            $movieSets = array_merge(
                \TofiXTv\Core\Tmdb::trending('movie', 'week')['results'] ?? [],
                \TofiXTv\Core\Tmdb::popularMovies()['results'] ?? [],
                \TofiXTv\Core\Tmdb::topRatedMovies()['results'] ?? []
            );
            foreach ($movieSets as $m) {
                $id = (int)($m['id'] ?? 0);
                if (!$id) continue;
                $urls[] = [
                    'loc' => absolute_url(movie_url($m)),
                    'freq' => 'weekly', 'prio' => '0.7', 'lastmod' => $todayMod,
                    'alt' => [
                        'ar' => absolute_url("/movie/{$id}"),
                        'en' => absolute_url("/en/movie/{$id}"),
                    ],
                ];
            }
            $tvSets = array_merge(
                \TofiXTv\Core\Tmdb::trending('tv', 'week')['results'] ?? [],
                \TofiXTv\Core\Tmdb::popularTv()['results'] ?? [],
                \TofiXTv\Core\Tmdb::topRatedTv()['results'] ?? []
            );
            foreach ($tvSets as $t) {
                $id = (int)($t['id'] ?? 0);
                if (!$id) continue;
                $urls[] = [
                    'loc' => absolute_url(series_url($t)),
                    'freq' => 'weekly', 'prio' => '0.7', 'lastmod' => $todayMod,
                    'alt' => [
                        'ar' => absolute_url("/series/{$id}"),
                        'en' => absolute_url("/en/series/{$id}"),
                    ],
                ];
            }
        }

        Lang::boot($original);

        ob_start();
        self::emitUrlset($urls, false);
        $xml = (string)ob_get_clean();
        \TofiXTv\Core\Cache::set($bodyKey, $xml);
        echo $xml;
        exit;
    }

    /* ---------------- robots.txt (dynamic, domain-agnostic) ---------------- */

    /**
     * robots.txt is served by PHP so every Sitemap: line carries the CURRENT
     * host (SITE_URL follows HTTP_HOST) — no hardcoded domain anywhere.
     */
    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Allow: /api/live-scores',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /cron/',
            'Disallow: /search?',
            'Disallow: /cinema/search?',
            'Disallow: /favorites',
            '',
        ];
        foreach (['sitemap.xml', 'sitemap-ar.xml', 'sitemap-en.xml', 'sitemap-match.xml',
                  'sitemap-news.xml', 'sitemap-images.xml', 'sitemap-video.xml',
                  'sitemap-cinema.xml'] as $f) {
            $lines[] = 'Sitemap: ' . SITE_URL . '/' . $f;
        }
        echo implode("\n", $lines) . "\n";
        exit;
    }

    /* ---------------- shared urlset emitter ---------------- */

    /**
     * Emit a urlset with EVERY tag on its own indented line. The <loc>
     * element contains ONLY the page URL — lastmod / changefreq / priority
     * live in their own sibling tags, so the URL can never appear glued to
     * metadata, in any XML viewer or parser.
     * @param array<int,array<string,mixed>> $urls
     */
    private static function emitUrlset(array $urls, bool $terminate = true): void
    {
        $seen = [];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . self::XHTML . '>' . "\n";
        foreach ($urls as $u) {
            if (isset($seen[$u['loc']])) continue;
            $seen[$u['loc']] = true;
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            foreach (($u['alt'] ?? []) as $hl => $href) {
                echo '    <xhtml:link rel="alternate" hreflang="' . $hl . '" href="' . htmlspecialchars($href, ENT_XML1) . '"/>' . "\n";
            }
            if (!empty($u['alt']['ar'])) {
                echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($u['alt']['ar'], ENT_XML1) . '"/>' . "\n";
            }
            if (!empty($u['lastmod'])) echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            echo '    <changefreq>' . $u['freq'] . "</changefreq>\n";
            echo '    <priority>' . $u['prio'] . "</priority>\n";
            echo "  </url>\n";
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

        // Google News requires a stable publication name; both language
        // sections publish under the single brand name "ALOKA Live".
        foreach (['ar' => 'ALOKA Live', 'en' => 'ALOKA Live'] as $lang => $pubName) {
            Lang::boot($lang);
            foreach (array_slice(Api::newsPage(1)['items'], 0, 50) as $n) {
                $loc = absolute_url(news_url($n));
                $date = date('c', to_ts($n['created_at'] ?? null) ?: time());
                $title = htmlspecialchars((string)($n['title'] ?? ''), ENT_XML1);
                echo "  <url>\n"
                    . '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n"
                    . "    <news:news>\n"
                    . "      <news:publication>\n"
                    . '        <news:name>' . htmlspecialchars($pubName, ENT_XML1) . "</news:name>\n"
                    . '        <news:language>' . $lang . "</news:language>\n"
                    . "      </news:publication>\n"
                    . '      <news:publication_date>' . $date . "</news:publication_date>\n"
                    . '      <news:title>' . $title . "</news:title>\n"
                    . "    </news:news>\n"
                    . "  </url>\n";
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

        $imgEntry = function (string $loc, string $img, string $title): void {
            echo "  <url>\n"
                . '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n"
                . "    <image:image>\n"
                . '      <image:loc>' . htmlspecialchars($img, ENT_XML1) . "</image:loc>\n"
                . '      <image:title>' . htmlspecialchars($title, ENT_XML1) . "</image:title>\n"
                . "    </image:image>\n"
                . "  </url>\n";
        };

        // News covers — 1200px renders for Google Discover eligibility
        foreach (array_slice(Api::newsPage(1)['items'], 0, 30) as $n) {
            if (empty($n['image']) && empty($n['editorial_image'])) continue;
            $imgEntry(absolute_url(news_url($n)), absolute_url(news_img($n, '1200')), (string)($n['title'] ?? ''));
        }

        // Channel/group logos uploaded from the new admin catalogue.
        foreach (ChannelCatalog::categories() as $category) {
            foreach (ChannelCatalog::groupsForCategory((int)$category['id']) as $group) {
                if (!empty($group['image'])) {
                    $imgEntry(absolute_url(channel_group_url($category, $group)), absolute_url(catalog_image($group['image'])), ChannelCatalog::label($group));
                }
            }
        }

        // Team + league logos from today's fixtures
        $done = [];
        foreach (Api::matchesByDate() as $m) {
            $lg = $m['championship'] ?? [];
            if (!empty($lg['image']) && empty($done['l' . $lg['url_id']])) {
                $done['l' . $lg['url_id']] = true;
                $imgEntry(absolute_url(league_url($lg)), absolute_url(league_img($lg, '96')), (string)($lg['title'] ?? ''));
            }
            foreach (['home', 'away'] as $side) {
                $t = team_of($m, $side);
                $tid = (int)($t['row_id'] ?? 0);
                if ($tid && !empty($t['image']) && empty($done['t' . $tid])) {
                    $done['t' . $tid] = true;
                    $imgEntry(absolute_url(team_url($t)), absolute_url(team_img($t, '128')), team_name($t));
                }
            }
        }

        Lang::boot($original);
        echo '</urlset>';
        exit;
    }

    /**
     * sitemap-video.xml — Google video sitemap for the highlights section.
     * Walks DEEP into the feed archive (up to 12 source batches ≈ 180
     * videos, newest first) so old highlights keep getting crawled, not
     * just the visible first pages.
     *
     * player_loc / content_loc MUST link to the actual video (an
     * embeddable player or a media file) — Google rejects entries where
     * they equal the page's own <loc>. Real links come from the per-id
     * player store (VideoFeed::playerFor): watch-page visits fill it,
     * and each build tops it up with a small live-fetch budget. Until a
     * video's real link is known its URL is emitted WITHOUT a
     * <video:video> block (a plain, valid sitemap entry).
     */
    public static function videos(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=1800');

        // Validate BEFORE building — a 15-minute bucket keeps conditional
        // GETs from paying for the archive walk.
        $bucket = intdiv(time(), 900);
        http_cache_validate($bucket * 900, 'sitemap-video-' . $bucket);

        // Whole-body disk cache (same pattern as sitemap-match): a cold
        // build fans out to up to 12 upstream LoadMore calls; serving the
        // last rendered body for 15 minutes caps the worst case.
        $bodyKey = 'sitemap-video-body-v5';
        $cached = \TofiXTv\Core\Cache::get($bodyKey, 900);
        if (is_string($cached) && $cached !== '') {
            echo $cached;
            exit;
        }

        $items = \TofiXTv\Core\VideoFeed::archive(12);

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        $seen = [];
        // Live detail fetches allowed per build — fills the player store
        // incrementally (≈15 min per build) without a 180-fetch fan-out.
        $fetchBudget = 15;
        foreach ($items as $v) {
            $id = (int)($v['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $loc   = absolute_url(path('video/' . $id));
            $title = htmlspecialchars((string)$v['title'], ENT_XML1);
            $thumb = htmlspecialchars((string)($v['thumbnail'] ?: SITE_URL . '/assets/brand/og-default.png'), ENT_XML1);
            $ts    = to_ts((string)($v['created_at'] ?? ''));

            $p = \TofiXTv\Core\VideoFeed::storedPlayer($id);
            if ($p === null && $fetchBudget > 0) {
                $fetchBudget--;
                $p = \TofiXTv\Core\VideoFeed::playerFor($id, true);
            }
            $content = (string)($p['content_loc'] ?? '');
            $player  = (string)($p['player_loc'] ?? '');
            // Never point the video tags at the HTML page itself — Google
            // rejects player_loc/content_loc == loc.
            if ($content === $loc) $content = '';
            if ($player === $loc)  $player = '';

            if ($content === '' && $player === '') {
                // Real video link not known (yet) — keep the page crawled
                // with a plain entry; it gains its <video:video> block once
                // the player store learns the link.
                echo "  <url>\n"
                    . '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n"
                    . "    <changefreq>daily</changefreq>\n"
                    . "    <priority>0.8</priority>\n"
                    . "  </url>\n";
                continue;
            }

            echo "  <url>\n"
                . '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n"
                . "    <changefreq>daily</changefreq>\n"
                . "    <priority>0.8</priority>\n"
                . "    <video:video>\n"
                . '      <video:thumbnail_loc>' . $thumb . "</video:thumbnail_loc>\n"
                . '      <video:title>' . $title . "</video:title>\n"
                . '      <video:description>' . $title . "</video:description>\n"
                . ($content !== '' ? '      <video:content_loc>' . htmlspecialchars($content, ENT_XML1) . "</video:content_loc>\n" : '')
                . ($player !== '' ? '      <video:player_loc>' . htmlspecialchars($player, ENT_XML1) . "</video:player_loc>\n" : '')
                . ($ts ? '      <video:publication_date>' . date('c', $ts) . "</video:publication_date>\n" : '')
                . "      <video:family_friendly>yes</video:family_friendly>\n"
                . "      <video:live>no</video:live>\n"
                . "    </video:video>\n"
                . "  </url>\n";
        }
        echo '</urlset>';

        $xml = (string)ob_get_clean();
        \TofiXTv\Core\Cache::set($bodyKey, $xml);
        echo $xml;
        exit;
    }
}
