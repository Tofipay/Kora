<?php
/**
 * Route table — every URL is clean (no .php, no query-string routing).
 * English versions live under /en (stripped before dispatch).
 */
declare(strict_types=1);

use TofiXTv\Controllers\{
    Home, Matches, MatchCenter, Leagues, Teams, Players, News, Search,
    Standings, Scorers, StaticPages, Sitemap, Media, Admin, ApiJson, Videos
};

/* ---------- Core pages ---------- */
$router->get('/', [Home::class, 'index']);

$router->get('/matches', fn() => Matches::day('today'));
$router->get('/today', fn() => Matches::day('today'));
$router->get('/tomorrow', fn() => Matches::day('tomorrow'));
$router->get('/yesterday', fn() => Matches::day('yesterday'));
$router->get('/live', fn() => Matches::live());
$router->get('/matches/{date:\d\d\d\d-\d\d-\d\d}', fn($a) => Matches::day($a['date']));

$router->get('/match/{slug}', fn($a) => MatchCenter::show($a['slug']));
$router->get('/watch/{id:\d+}', fn($a) => \TofiXTv\Controllers\Watch::show((int)$a['id']));
$router->get('/watch/{id:\d+}/src', fn($a) => \TofiXTv\Controllers\Watch::source((int)$a['id']));

/* ---------- Yacine channels (decrypt → servers list → player) ---------- */
$router->get('/yacine/{id:\d+}', fn($a) => \TofiXTv\Controllers\YacineWatch::channel((int)$a['id']));
$router->get('/yacine/{id:\d+}/src', fn($a) => \TofiXTv\Controllers\YacineWatch::source((int)$a['id']));
$router->get('/yacine/{id:\d+}/{index:\d+}', fn($a) => \TofiXTv\Controllers\YacineWatch::play((int)$a['id'], (int)$a['index']));
/* Stream proxy as a front-controller route (works on any server config). */
$router->get('/stream', fn() => \TofiXTv\Core\StreamProxy::serve());

$router->get('/leagues', [Leagues::class, 'index']);
$router->get('/league/{slug}', fn($a) => Leagues::show($a['slug']));

$router->get('/teams', [Teams::class, 'index']);
$router->get('/team/{slug}', fn($a) => Teams::show($a['slug']));

$router->get('/players', [Players::class, 'index']);
$router->get('/player/{slug}', fn($a) => Players::show($a['slug']));

$router->get('/news', fn() => News::index(1));
$router->get('/news/page/{n:\d+}', fn($a) => News::index((int)$a['n']));
$router->get('/news/{slug}', fn($a) => News::show($a['slug']));

/* ---------- Videos (highlights — Btolat source) ---------- */
$router->get('/videos', fn() => Videos::index(1));
$router->get('/videos/page/{n:\d+}', fn($a) => Videos::index((int)$a['n']));
/* Numeric id → Btolat in-site player. Must be registered BEFORE the legacy
   YouTube-id pattern so digits never fall through to it. */
$router->get('/video/{id:\d+}', fn($a) => Videos::play((int)$a['id']));
/* Legacy 11-char YouTube ids (match-page videos tab). The router uses "}" as
   the placeholder terminator, so no {11} quantifier — length-checked in watch(). */
$router->get('/video/{ytId:[A-Za-z0-9_\-]+}', fn($a) => Videos::watch($a['ytId']));

$router->get('/standings', [Standings::class, 'index']);
$router->get('/top-scorers', [Scorers::class, 'index']);

/* ---------- Cinema: الأفلام والمسلسلات (TMDB-powered) ---------- */
$router->get('/movies', [\TofiXTv\Controllers\Cinema::class, 'movies']);
$router->get('/series', [\TofiXTv\Controllers\Cinema::class, 'series']);
/* Category "view all" pages (registered BEFORE the {slug} detail patterns). */
$router->get('/movies/browse/{cat:[a-z\-]+}', fn($a) => \TofiXTv\Controllers\Cinema::browse('movies', $a['cat']));
$router->get('/movies/browse/{cat:[a-z\-]+}/page/{n:\d+}', fn($a) => \TofiXTv\Controllers\Cinema::browse('movies', $a['cat'], (int)$a['n']));
$router->get('/series/browse/{cat:[a-z\-]+}', fn($a) => \TofiXTv\Controllers\Cinema::browse('series', $a['cat']));
$router->get('/series/browse/{cat:[a-z\-]+}/page/{n:\d+}', fn($a) => \TofiXTv\Controllers\Cinema::browse('series', $a['cat'], (int)$a['n']));
/* Genre browsing (registered BEFORE the {slug} detail patterns). */
$router->get('/movies/genre/{slug}', fn($a) => \TofiXTv\Controllers\Cinema::genre('movies', $a['slug']));
$router->get('/movies/genre/{slug}/page/{n:\d+}', fn($a) => \TofiXTv\Controllers\Cinema::genre('movies', $a['slug'], (int)$a['n']));
$router->get('/series/genre/{slug}', fn($a) => \TofiXTv\Controllers\Cinema::genre('series', $a['slug']));
$router->get('/series/genre/{slug}/page/{n:\d+}', fn($a) => \TofiXTv\Controllers\Cinema::genre('series', $a['slug'], (int)$a['n']));
/* Cinema search. */
$router->get('/cinema/search', [\TofiXTv\Controllers\Cinema::class, 'search']);
/* Detail pages: /movie/{slug}-{id} · /series/{slug}-{id}. */
$router->get('/movie/{slug}', fn($a) => \TofiXTv\Controllers\Cinema::movie($a['slug']));
$router->get('/series/{slug}', fn($a) => \TofiXTv\Controllers\Cinema::show($a['slug']));

$router->get('/search', [Search::class, 'index']);
$router->get('/favorites', [StaticPages::class, 'favorites']);

/* ---------- Static / legal ---------- */
$router->get('/about',   fn() => StaticPages::page('about'));
$router->get('/privacy', fn() => StaticPages::page('privacy'));
$router->get('/terms',   fn() => StaticPages::page('terms'));
$router->get('/contact', fn() => StaticPages::page('contact'));
$router->get('/offline', [StaticPages::class, 'offline']);
/* Settings & More hub (mobile bottom-nav "More" tab) + cookie pages.
   Additive routes only — nothing existing moved. */
$router->get('/more',            [StaticPages::class, 'more']);
$router->get('/cookies',         fn() => StaticPages::page('cookies'));
$router->get('/cookie-settings', [StaticPages::class, 'cookieSettings']);

/* ---------- SEO ---------- */
$router->get('/sitemap.xml', [Sitemap::class, 'index']);
$router->get('/sitemap-ar.xml', [Sitemap::class, 'arabic']);
$router->get('/sitemap-en.xml', [Sitemap::class, 'english']);
$router->get('/sitemap-match.xml', [Sitemap::class, 'matches']);
$router->get('/sitemap-news.xml', [Sitemap::class, 'news']);
$router->get('/sitemap-images.xml', [Sitemap::class, 'images']);
$router->get('/sitemap-video.xml', [Sitemap::class, 'videos']);
$router->get('/sitemap-cinema.xml', [Sitemap::class, 'cinema']);
/* robots.txt is generated dynamically so the sitemap URLs always carry the
 * CURRENT host — the site stays domain-agnostic. */
$router->get('/robots.txt', [Sitemap::class, 'robots']);

/* ---------- First-party media proxy ---------- */
$router->get('/media/{path:.+}', fn($a) => Media::serve($a['path']));

/* ---------- Internal JSON API (live refresh, PWA) ---------- */
$router->get('/api/live-scores', [ApiJson::class, 'liveScores']);
$router->get('/api/videos', [ApiJson::class, 'videos']);
$router->get('/api/match/{id:\d+}', fn($a) => ApiJson::match((int)$a['id']));
$router->post('/api/newsletter', [ApiJson::class, 'newsletter']);
// any(): POST (JSON body), GET (query fallback — survives host-canonical
// 301s that flip POST→GET on some hosting setups) and OPTIONS (preflight).
$router->any('/api/push-subscribe', [ApiJson::class, 'pushSubscribe']);

/* ---------- URL-triggered cron (shared hosting: wget/curl) ---------- */
$router->get('/cron/notify', [ApiJson::class, 'cronNotify']);
/* IndexNow + sitemap ping when any match changes state/score (cron: every 1-5 min). */
$router->get('/cron/ping', [ApiJson::class, 'cronPing']);

/* ---------- Admin ---------- */
$router->any('/' . ADMIN_PATH, fn() => Admin::dispatch(''));
$router->any('/' . ADMIN_PATH . '/{action:.+}', fn($a) => Admin::dispatch($a['action']));
