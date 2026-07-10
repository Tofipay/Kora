<?php
/**
 * ============================================================================
 *  Qamhad Live — API Server configuration  (https://api.qamhad.com/)
 * ============================================================================
 *  Native PHP 8.3. No framework, no database. Every value below can be
 *  overridden with an environment variable so nothing here needs editing on a
 *  normal install. This file is included by _bootstrap.php on every request.
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

if (!defined('QAMHAD_API')) define('QAMHAD_API', true);

/* Load the proven upstream engine (upstream hosts, anti-bot headers, caching,
 * Yacine decrypt, scrapers). Everything below only wraps it in a public API. */
require_once __DIR__ . '/engine/config.php';

/* ---------------------------------------------------------------------------
 *  Canonical public host of THIS API server.
 * ------------------------------------------------------------------------- */
define('API_HOST_URL', rtrim(getenv('QAMHAD_API_URL') ?: 'https://api.qamhad.com', '/'));

/* ---------------------------------------------------------------------------
 *  CORS — who may call this API from a browser.
 *  The Blogger template lives on your blog domain; list every host that must
 *  reach the API. "*.blogspot.com" style wildcards are supported.
 *  Set QAMHAD_ALLOWED_ORIGINS="https://a.com,https://b.com" to override.
 * ------------------------------------------------------------------------- */
const API_ALLOWED_ORIGINS = [
    'https://www.qamhad.com',
    'https://qamhad.com',
    'https://qamhad.blogspot.com',
    '*.blogspot.com',
    '*.qamhad.com',
];

/* ---------------------------------------------------------------------------
 *  API key.
 *  A *public* key that the Blogger template sends with every request. It does
 *  not protect secrets (the template is public) — it lets you rotate access and
 *  block scrapers that copy your endpoints. Enforcement is OFF by default so a
 *  fresh install works immediately; turn it on once your template ships the key.
 *  Override: QAMHAD_API_KEY, QAMHAD_REQUIRE_KEY=1
 * ------------------------------------------------------------------------- */
define('API_PUBLIC_KEY',  getenv('QAMHAD_API_KEY') ?: 'qamhad-public-2026');
define('API_REQUIRE_KEY', (getenv('QAMHAD_REQUIRE_KEY') ?: '0') === '1');

/* ---------------------------------------------------------------------------
 *  Rate limiting (token bucket per client IP, per rolling window).
 * ------------------------------------------------------------------------- */
define('API_RATE_LIMIT',  (int)(getenv('QAMHAD_RATE_LIMIT')  ?: 120)); // requests…
define('API_RATE_WINDOW', (int)(getenv('QAMHAD_RATE_WINDOW') ?: 60));  // …per N seconds

/* ---------------------------------------------------------------------------
 *  Search-engine verification tokens (returned by /robots.php meta helpers and
 *  exposed to the template through /settings.php). Empty = not set.
 * ------------------------------------------------------------------------- */
const API_VERIFY = [
    'google'   => '',
    'bing'     => '',
    'yandex'   => '',
    'facebook' => '',
];

/* Rate-limit / lightweight state storage. */
define('API_STATE_DIR', STORAGE_DIR . '/state');
