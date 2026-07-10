<?php
/**
 * GET /settings.php[&lang=ar|en] — public front-end configuration for the
 * Blogger template (brand, colours, menus, social, ads, analytics, featured
 * leagues). The XML template reads this once on load and themes itself, so no
 * template editing is ever required after install.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_method(['GET']);

api_out(fe_settings(), CACHE_TTL_LEAGUES);
