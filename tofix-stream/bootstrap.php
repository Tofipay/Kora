<?php

/**
 * bootstrap.php
 * -----------------------------------------------------------------------------
 * نقطة التهيئة المشتركة لكل نقاط الدخول (dashboard, api, proxy, player).
 *   - تسجيل مُحمِّل تلقائي (PSR-4-lite) لكلاسات مساحة الاسم ToFiXStream.
 *   - تحميل الإعدادات وضبط المنطقة الزمنية ومعالجة الأخطاء.
 *   - إنشاء المجلّدات المطلوبة إن لم توجد.
 *
 * @package ToFiXStream
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1) المُحمِّل التلقائي: يربط الكلاس ToFiXStream\Foo بالملف classes/Foo.php
// -----------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'ToFiXStream\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use ToFiXStream\Config;

// -----------------------------------------------------------------------------
// 2) تحميل الإعدادات
// -----------------------------------------------------------------------------
Config::load(__DIR__ . '/config/config.php');

// -----------------------------------------------------------------------------
// 3) المنطقة الزمنية ووضع الأخطاء
// -----------------------------------------------------------------------------
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

if (Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

// -----------------------------------------------------------------------------
// 4) التأكّد من وجود المجلّدات القابلة للكتابة
// -----------------------------------------------------------------------------
foreach (['storage', 'streams', 'cache', 'logs'] as $key) {
    $dir = Config::get("paths.$key");
    if ($dir && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
