<?php
/** GET /robots.php — robots.txt for the API host (points crawlers to the sitemap). */
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$api  = API_HOST_URL;
echo "User-agent: *\n";
echo "Allow: /media\n";
echo "Allow: /settings.php\n";
echo "Disallow: /engine/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /cache.php\n";
echo "\n";
echo "Sitemap: {$api}/sitemap.php\n";
