<?php
/**
 * Route table — every URL is clean (no .php, no query-string routing).
 * English versions live under /en (stripped before dispatch).
 */
declare(strict_types=1);

use Qamhad\Controllers\{
    Home, Matches, MatchCenter, Leagues, Teams, Players, News, Search,
    Standings, Scorers, StaticPages, Sitemap, Media, Admin, ApiJson, Videos, AppApi
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
$router->get('/watch/{id:\d+}', fn($a) => \Qamhad\Controllers\Watch::show((int)$a['id']));
$router->get('/watch/{id:\d+}/src', fn($a) => \Qamhad\Controllers\Watch::source((int)$a['id']));

/* ---------- Yacine channels (decrypt → servers list → player) ---------- */
$router->get('/yacine/{id:\d+}', fn($a) => \Qamhad\Controllers\YacineWatch::channel((int)$a['id']));
$router->get('/yacine/{id:\d+}/src', fn($a) => \Qamhad\Controllers\YacineWatch::source((int)$a['id']));
$router->get('/yacine/{id:\d+}/{index:\d+}', fn($a) => \Qamhad\Controllers\YacineWatch::play((int)$a['id'], (int)$a['index']));
/* Stream proxy as a front-controller route (works on any server config). */
$router->get('/stream', fn() => \Qamhad\Core\StreamProxy::serve());

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

$router->get('/search', [Search::class, 'index']);
$router->get('/favorites', [StaticPages::class, 'favorites']);

/* ---------- Static / legal ---------- */
$router->get('/about',   fn() => StaticPages::page('about'));
$router->get('/privacy', fn() => StaticPages::page('privacy'));
$router->get('/terms',   fn() => StaticPages::page('terms'));
$router->get('/contact', fn() => StaticPages::page('contact'));
$router->get('/offline', [StaticPages::class, 'offline']);

/* ---------- SEO ---------- */
$router->get('/sitemap.xml', [Sitemap::class, 'index']);
$router->get('/sitemap-ar.xml', [Sitemap::class, 'arabic']);
$router->get('/sitemap-en.xml', [Sitemap::class, 'english']);
$router->get('/sitemap-match.xml', [Sitemap::class, 'matches']);
$router->get('/sitemap-news.xml', [Sitemap::class, 'news']);
$router->get('/sitemap-images.xml', [Sitemap::class, 'images']);
$router->get('/sitemap-video.xml', [Sitemap::class, 'videos']);

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

/* ---------- App JSON API — served THROUGH the router so it works on ANY host
 * (even where /api/*.php files are not executed directly). Registered for both
 * the clean path and the .php path the app calls. ---------- */
foreach ([
    'matches'    => 'matches',
    'live'       => 'live',
    'news'       => 'news',
    'standings'  => 'standings',
    'team'       => 'team',
    'player'     => 'player',
    'videos'     => 'videos',
    'channels'   => 'channels',
    'leagues'    => 'leagues',
    'search'     => 'search',
    'match_info' => 'matchInfo',
] as $ep => $method) {
    $router->get('/api/' . $ep,          fn() => AppApi::$method());
    $router->get('/api/' . $ep . '.php', fn() => AppApi::$method());
}

/* ---------- URL-triggered cron (shared hosting: wget/curl) ---------- */
$router->get('/cron/notify', [ApiJson::class, 'cronNotify']);
/* IndexNow + sitemap ping when any match changes state/score (cron: every 1-5 min). */
$router->get('/cron/ping', [ApiJson::class, 'cronPing']);

/* ---------- Admin ---------- */
$router->any('/' . ADMIN_PATH, fn() => Admin::dispatch(''));
$router->any('/' . ADMIN_PATH . '/{action:.+}', fn($a) => Admin::dispatch($a['action']));
