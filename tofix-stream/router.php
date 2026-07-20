<?php

/**
 * router.php
 * -----------------------------------------------------------------------------
 * موجّه لخادم PHP المدمج (للتطوير المحلّي) حتى تعمل روابط البثّ النظيفة
 * مثل /stream/ID/index.m3u8 تمامًا كما تعمل على Apache/Nginx في الإنتاج.
 *
 * التشغيل:
 *   php -S 0.0.0.0:8080 router.php
 *   ثم افتح:  http://localhost:8080/public/index.php
 *   ورابط البثّ:  http://localhost:8080/stream/CHANNEL_ID/index.m3u8
 *
 * @package ToFiXStream
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// رابط البثّ النظيف (m3u8 أو ts) -> البروكسي.
if (preg_match('#^/stream/([A-Za-z0-9_-]+)(?:/index)?\.(m3u8|ts)$#', $uri, $m)) {
    $_GET['channel'] = $m[1];
    require __DIR__ . '/proxy/index.php';
    return true;
}

// الصفحة الرئيسية.
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// اترك الخادم المدمج يخدم الملفّات الموجودة فعليًا (php/css/js...).
$file = __DIR__ . $uri;
if (is_file($file)) {
    return false;
}

// أي شيء آخر: 404.
http_response_code(404);
echo 'Not Found';
return true;
