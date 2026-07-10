<?php
/**
 * ============================================================================
 *  Qamhad Live API — front-facing helpers.
 * ============================================================================
 *  Pure functions shared by the public endpoints, the router and the sitemap
 *  generator. Loaded by _bootstrap.php after the engine helpers.
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

/** Public URL of the Blogger site the template runs on (for sitemaps/OG). */
function blog_url(): string
{
    return rtrim((string)(getenv('QAMHAD_BLOG_URL') ?: 'https://www.qamhad.com'), '/');
}

/** Absolute media-proxy URL on THIS API host: kind/size/filename. */
function api_media(string $kind, string $size, ?string $file): string
{
    $rel = media_url($kind, $size, $file, '');            // engine builder → "/media/…" or ''
    if ($rel === '') return '';
    if (preg_match('#^https?://#i', $rel)) return $rel;   // unknown external image
    return API_HOST_URL . $rel;
}

/** URL/slug-safe token from an Arabic or Latin title. */
function fe_slug(string $s): string
{
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/[\s_]+/u', '-', $s) ?? '';
    $s = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $s) ?? '';
    $s = preg_replace('/-+/', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * Front-end configuration served to the Blogger template (/settings.php).
 * Everything the template needs to theme + wire itself lives here, so the XML
 * never has to be edited after install — change JSON, not markup.
 */
function fe_settings(): array
{
    $branding = \Qamhad\Core\Settings::get('branding', []);
    $analytics = \Qamhad\Core\Settings::get('analytics_config', []);
    if (!is_array($branding))  $branding  = [];
    if (!is_array($analytics)) $analytics = [];

    return [
        'site' => [
            'name_ar' => SITE_NAME_AR,
            'name_en' => SITE_NAME_EN,
            'slogan_ar' => SITE_SLOGAN_AR,
            'slogan_en' => SITE_SLOGAN_EN,
            'email' => SITE_EMAIL,
            'api_base' => API_HOST_URL,
            'blog_url' => blog_url(),
        ],
        'theme' => [
            'primary'   => $branding['primary']   ?? '#0d9488',
            'secondary' => $branding['secondary'] ?? '#0f766e',
            'accent'    => $branding['accent']    ?? '#f59e0b',
            'font_ar'   => $branding['font_ar']   ?? 'Cairo',
            'dark_mode' => $branding['dark_mode'] ?? 'auto',   // auto|dark|light
            'rtl'       => true,
        ],
        'featured_leagues' => array_map(static fn($l) => [
            'id'    => $l['url_id'],
            'ar'    => $l['ar'],
            'en'    => $l['en'],
        ], FAVORITE_LEAGUES),
        'menu' => [
            ['id' => 'home',      'ar' => 'الرئيسية',    'en' => 'Home',      'icon' => 'house'],
            ['id' => 'live',      'ar' => 'مباشر',       'en' => 'Live',      'icon' => 'broadcast'],
            ['id' => 'matches',   'ar' => 'المباريات',   'en' => 'Matches',   'icon' => 'calendar-event'],
            ['id' => 'leagues',   'ar' => 'البطولات',    'en' => 'Leagues',   'icon' => 'trophy'],
            ['id' => 'standings', 'ar' => 'الترتيب',     'en' => 'Standings', 'icon' => 'list-ol'],
            ['id' => 'scorers',   'ar' => 'الهدافون',    'en' => 'Scorers',   'icon' => 'star'],
            ['id' => 'news',      'ar' => 'الأخبار',     'en' => 'News',      'icon' => 'newspaper'],
            ['id' => 'videos',    'ar' => 'الفيديوهات',  'en' => 'Videos',    'icon' => 'play-btn'],
            ['id' => 'channels',  'ar' => 'القنوات',     'en' => 'TV',        'icon' => 'tv'],
            ['id' => 'search',    'ar' => 'بحث',         'en' => 'Search',    'icon' => 'search'],
        ],
        'social' => $branding['social'] ?? [
            'facebook' => '', 'twitter' => '', 'youtube' => '', 'telegram' => '', 'instagram' => '',
        ],
        'ads' => [
            'adsense_client' => $analytics['adsense_client'] ?? '',
            'auto_ads'       => (bool)($analytics['auto_ads'] ?? false),
            'slot_header'    => $analytics['slot_header'] ?? '',
            'slot_infeed'    => $analytics['slot_infeed'] ?? '',
            'slot_article'   => $analytics['slot_article'] ?? '',
        ],
        'analytics' => [
            'ga4'        => $analytics['ga4'] ?? '',
            'gtm'        => $analytics['gtm'] ?? '',
        ],
        'verification' => API_VERIFY,
        'push' => [
            'enabled'   => (bool)($analytics['fcm_enabled'] ?? false),
            'vapid_key' => $analytics['vapid_key'] ?? '',
        ],
    ];
}
