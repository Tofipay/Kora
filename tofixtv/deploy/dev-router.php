<?php
// Dev router for PHP's built-in server — mimics the .htaccess rewrites:
//   php -S 0.0.0.0:8080 deploy/dev-router.php
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . '/public' . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) return false;
require $root . '/public/index.php';
