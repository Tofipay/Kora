<?php
/**
 * ToFi X Tv — توفي إكس تيفي
 * Global configuration. No database required — all data comes from the scores API
 * and is cached on disk. Editable settings live in storage/settings/*.json (Admin panel).
 */

declare(strict_types=1);

if (!defined('TOFIXTV')) define('TOFIXTV', true);

/* ---------- Brand ---------- */
define('SITE_NAME_AR',  'توفي إكس تيفي');
define('SITE_NAME_EN',  'ToFi X Tv');
define('SITE_SLOGAN_AR','بث مباشر للمباريات، أخبار كرة القدم، وأحدث الأفلام والمسلسلات في منصة واحدة');
define('SITE_SLOGAN_EN','Live matches, football news, plus the latest movies & series — one platform');
define('SITE_EMAIL',    'info@tofi-xtv.com');

define('BRAND_PRIMARY', '#0f172a');
define('BRAND_SECONDARY', '#1e293b');
define('BRAND_DARK',    '#0f172a');
define('BRAND_ACCENT',  '#3b82f6');
define('BRAND_BG',      '#ffffff');

/* ---------- Base URL (domain-agnostic) ----------
 * SITE_URL is the SEO source of truth: every canonical tag, hreflang,
 * sitemap <loc>, JSON-LD url and Location header is built from it.
 * The site is DOMAIN-AGNOSTIC: SITE_URL is derived from the current
 * request host (HTTP_HOST), so deploying on any domain needs zero code
 * changes. DEFAULT_DOMAIN is only the fallback for CLI contexts (cron,
 * workers) where no request host exists.
 * Override per-environment with TOFIXTV_SITE_URL if a fixed canonical
 * domain is ever desired. */
define('DEFAULT_DOMAIN', 'https://tofi-xtv.com');
define('PRIMARY_DOMAIN', DEFAULT_DOMAIN); // kept for BC with older code paths
define('LEGACY_HOSTS', []);               // no host-based redirects — any domain works

$__envUrl = getenv('TOFIXTV_SITE_URL') ?: '';
$__host   = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($__envUrl !== '') {
    define('SITE_URL', rtrim($__envUrl, '/'));
} elseif ($__host !== '' && preg_match('/^[a-z0-9.\-]+(:\d+)?$/', $__host)) {
    // Current request host — scheme detected proxy-aware (Cloudflare etc.).
    $__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
        || stripos((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https') !== false;
    define('SITE_URL', ($__https ? 'https' : 'http') . '://' . $__host);
} else {
    define('SITE_URL', DEFAULT_DOMAIN);
}
define('PRIMARY_HOST', (string)(parse_url(SITE_URL, PHP_URL_HOST) ?: 'tofi-xtv.com'));

/* ---------- Paths ---------- */
define('APP_DIR',     __DIR__);
define('BASE_DIR',    dirname(__DIR__));
define('PUBLIC_DIR',  BASE_DIR . '/public');
define('STORAGE_DIR', BASE_DIR . '/storage');
define('CACHE_DIR',   STORAGE_DIR . '/cache/api');
define('MEDIA_CACHE_DIR', STORAGE_DIR . '/cache/media');
define('SETTINGS_DIR',STORAGE_DIR . '/settings');

/* ---------- Upstream data APIs (switched automatically by language) ---------- */
const API_BASES = [
    'ar' => [
        'matches' => 'https://api-ar.ysscores.com/api',
        'news'    => 'https://news-ar.ysscores.com/api',
    ],
    'en' => [
        'matches' => 'https://api-en.ysscores.com/api',
        'news'    => 'https://news-en.ysscores.com/api',
    ],
];

/* Upstream image CDN — NEVER printed in HTML. Everything is served through
 * the first-party media proxy: /media/{teams|championship|news|player}/{size}/{file} */
define('UPSTREAM_IMG', 'https://imgs.ysscores.com');

/* ---------- Upstream app identity (anti-bot headers) ----------
 * The API now returns a "download the official app" placeholder (fake team
 * names like "حمّل يلا شووت") unless the request carries the real mobile-app
 * headers. These are sent on EVERY upstream call. When the API bumps its
 * required version, change ONLY the two constants below — every request,
 * page and /api proxy updates automatically (requirement: one place). */
define('API_APP_VERSION',     '543');
define('API_APP_VERSIONNAME', '4.15.0');
define('API_APP_PLATFORM',    'android');
define('API_TIMEZONE',        '180');            // GMT+3, in minutes
define('API_USER_AGENT',      'Dart/3.10 (dart:io)');

/* ---------- Yacine TV channel resolver (internal player) ----------
 * Lets an admin paste a Yacine channel API URL (e.g.
 * http://ver3.yacinelive.com/api/channel/1471) as a server URL. The player
 * decrypts it server-side (T header + key → base64 → XOR → JSON) and plays the
 * real HLS/DASH sources through the first-party stream proxy (/api/stream.php)
 * so blocked hosts work without a VPN. Change the key here if it ever rotates. */
define('YACINE_API_BASE', getenv('YACINE_API_URL') ?: 'http://ver3.yacinelive.com');
define('YACINE_KEY',      getenv('YACINE_API_KEY') ?: 'c!xZj+N9&G@Ev@vw');
define('YACINE_UA',       getenv('YACINE_UA') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');
/* Server secret that signs proxied stream URLs (prevents open-proxy abuse). */
define('YACINE_PROXY_SECRET', getenv('YACINE_PROXY_SECRET') ?: ('tofixtv|' . YACINE_KEY));
/* Entry point for the stream proxy. '/stream' is a front-controller route that
 * works on ANY server config; '/api/stream.php' also works where /api/*.php
 * execute directly. Change here to switch every proxied URL at once. */
define('YACINE_PROXY_PATH', getenv('YACINE_PROXY_PATH') ?: '/stream');

/**
 * Upstream request headers, centralized. app-lang follows the active site
 * language. The Admin panel may override the version at runtime (settings
 * key "api_app") so the headers can be refreshed without editing code.
 * @return array<int,string> cURL-style "Key: value" header lines
 */
function api_headers(?string $lang = null): array
{
    $lang = ($lang ?? (class_exists('TofiXTv\\Core\\Lang') ? \TofiXTv\Core\Lang::current() : 'ar')) === 'en' ? 'en' : 'ar';
    $ver     = API_APP_VERSION;
    $verName = API_APP_VERSIONNAME;
    // Optional runtime override (Admin → Settings). Fails soft if unavailable.
    if (class_exists('TofiXTv\\Core\\Settings')) {
        $ov = \TofiXTv\Core\Settings::get('api_app', []);
        if (is_array($ov)) {
            if (!empty($ov['version']))     $ver     = (string)$ov['version'];
            if (!empty($ov['versionname'])) $verName = (string)$ov['versionname'];
        }
    }
    return [
        'user-agent: ' . API_USER_AGENT,
        'app-lang: ' . $lang,
        'app-brightness: light',
        'timezone: ' . API_TIMEZONE,
        'app-platform: ' . API_APP_PLATFORM,
        'app-versionname: ' . $verName,
        'app-version: ' . $ver,
        'accept: application/json',
    ];
}

/* ---------- Cinema (movies & series) — TMDB upstream ----------
 * Powers the الأفلام/المسلسلات section. Same caching model as the sports
 * feeds: JSON responses cached on disk (storage/cache/tmdb), no database.
 * The bearer token can be rotated from the environment without a deploy. */
define('TMDB_BASE_URL',   'https://api.themoviedb.org/3');
define('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p');
define('TMDB_BEARER_TOKEN', getenv('TMDB_BEARER_TOKEN') ?: 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJlYjJlMmQyOTUwYjI4YjQ4N2U4YWQ0MThlYmMyNGMzMiIsIm5iZiI6MTc2MTQwNjMyNC45MjksInN1YiI6IjY4ZmNlZDc0MGIxNDVlZmI0NTJhYTUwZiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.v6KCUdwR6SeIS2XXSaSXxl4BYMcXhxBqht0znr2Y4io');
define('TMDB_CACHE_DIR',  STORAGE_DIR . '/cache/tmdb');
define('CACHE_TTL_TMDB',  60 * 60);   // 1h — list/detail payloads
/* Embed players (alternate sources selectable in the watch UI). */
define('PLAYER_VIDSRC_TO', 'https://vidsrc.to/embed');
define('PLAYER_VIDSRC_CC', 'https://vidsrc.cc/v2/embed');
define('PLAYER_VIDEASY',   'https://player.videasy.to');

/* Website AJAX host (team squads, player profile pages — data that has no
 * JSON API). Switched by language like the API hosts. */
const WWW_BASES = [
    'ar' => 'https://www.ysscores.com/ar',
    'en' => 'https://www.ysscores.com/en',
];

/* matches_date_get mode segment. "L" returns FOLLOWED competitions only
 * (verified: every league in a real busy-day response has followed=1, which
 * is why sites using only L show few competitions). All listed modes are
 * fetched and merged (deduped by match_id) — an unknown mode fails softly
 * and is negative-cached, so extra entries can only ADD matches. */
const MATCHES_DATE_MODES = ['L', 'A'];

/* Championship-id list injected into the FIRST bracket of matches_date_get:
 *   /matches/matches_date_get/{date}/[ids…]/[]/[]/L/180
 * Passing the explicit tracked-competition ids returns EVERY one of those
 * championships for the day — including non-followed leagues (followed=0)
 * that the plain followed-only feed ([]/…/L) hides. This is the definitive
 * fix for "only the World Cup shows": the empty-bracket feed was silently
 * filtered to followed competitions, this list is not. Verified against a
 * real busy-day response that returned e.g. the Swedish league (followed=0).
 * The union with MATCHES_DATE_MODES below can only ADD more matches. */
const MATCHES_FOLLOW_IDS = [
    '560100', '901130', '41019', '79123', '47037', '47821', '90322', '138192',
    '308191', '573139', '252344', '370347', '796368', '38371', '751343', '784120',
    '91130', '26232', '41480', '154108', '160110', '30067', '799154', '78989',
    '797102', '75166', '888111', '970117', '13178', '690345', '58234', '94164',
    '968378', '474185', '475137', '533123', '884138', '39714', '58553', '58881',
];

/* Media proxy: allowed sizes per kind, plus the fallback size to retry when
 * the upstream CDN has no render at the requested size (e.g. player/100 is
 * missing but player/64 exists; championship logos live at /128). The first
 * entry of each list is the preferred/primary size. */
const MEDIA_KINDS = [
    'teams'        => ['64', '128', '256', '48', '96', '32', '150'],
    'championship' => ['128', '96', '64', '32', '48'],
    'news'         => ['640', '150', '300', '1200'],
    'player'       => ['64', '48', '100', '128', '150', '32'],
    'country'      => ['64', '32'],
    'flags'        => ['64', '32'],
    'coach'        => ['48', '64'],
];
/* When a requested size 404s upstream, retry with this size before failing. */
const MEDIA_FALLBACK_SIZE = [
    'teams'        => '64',
    'championship' => '128',
    'news'         => '640',
    'player'       => '64',
    'country'      => '64',
    'flags'        => '64',
    'coach'        => '48',
];

/* ---------- Cache namespace ----------
 * Bump to invalidate ALL on-disk cache after a change that may have poisoned
 * it — notably the pre-headers "download the app" placeholder data. Old files
 * stop matching the key and are re-fetched fresh with the correct headers. */
define('CACHE_VERSION', '2');

/* Anti-bot placeholder sentinels. When the API blocks a request it swaps real
 * team/championship names for these strings. Any non-news payload containing
 * them is rejected (never cached) so a blocked response can't poison the site. */
const API_BLOCK_SENTINELS = [
    'يلا شووت', 'يلا شوت', 'التطبيق الأصلي', 'انتهت صلاحية هذا التطبيق',
    'yalla shoot', 'yallashoot', 'yalla-shoot',
];

/** True when a string is an anti-bot placeholder (not real data). */
function is_blocked_text(?string $s): bool
{
    if ($s === null || $s === '') return false;
    foreach (API_BLOCK_SENTINELS as $needle) {
        if (mb_stripos($s, $needle) !== false) return true;
    }
    return false;
}

/* ---------- Cache TTLs (seconds) ---------- */
define('CACHE_TTL_LIVE',     60);        // live match data (60s, per spec)
define('CACHE_TTL_MATCHES',  10 * 60);   // day fixtures (10min, per spec)
define('CACHE_TTL_NEWS',     15 * 60);
define('CACHE_TTL_LEAGUES',  60 * 60);
define('CACHE_TTL_MEDIA',    7 * 24 * 3600);

/* ---------- Featured leagues (always pinned; url_id used across the API) ---------- */
const FAVORITE_LEAGUES = [
    ['url_id' => 894789, 'ar' => 'كأس العالم',          'en' => 'World Cup'],
    ['url_id' => 900326, 'ar' => 'الدوري الإنجليزي',     'en' => 'Premier League'],
    ['url_id' => 901074, 'ar' => 'الدوري الإسباني',      'en' => 'LaLiga'],
    ['url_id' => 899984, 'ar' => 'الدوري الإيطالي',      'en' => 'Serie A'],
    ['url_id' => 899867, 'ar' => 'الدوري الألماني',      'en' => 'Bundesliga'],
    ['url_id' => 900705, 'ar' => 'الدوري الفرنسي',       'en' => 'Ligue 1'],
    ['url_id' => 903294, 'ar' => 'دوري روشن السعودي',    'en' => 'Saudi Pro League'],
    ['url_id' => 900620, 'ar' => 'الدوري البرتغالي',     'en' => 'Primeira Liga'],
    ['url_id' => 916145, 'ar' => 'الدوري البرازيلي',     'en' => 'Brasileirão'],
];

/* ---------- Locale / time ---------- */
define('DEFAULT_TZ', 'Asia/Riyadh');
date_default_timezone_set(DEFAULT_TZ);
mb_internal_encoding('UTF-8');

/* ---------- Admin ---------- */
define('ADMIN_PATH', 'admin');           // /admin
define('ADMIN_DEFAULT_PASSWORD', 'tofixtv-admin-2026'); // change on first login (stored hashed)

/* ---------- Sessions (admin + rate limiting only; public pages stay cacheable) ---------- */
function tofixtv_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => function_exists('is_https')
                ? is_https()
                : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    }
}
