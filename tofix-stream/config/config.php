<?php

/**
 * config/config.php
 * -----------------------------------------------------------------------------
 * الملف المركزي لإعدادات منصة ToFi X Stream.
 *
 * يُعيد هذا الملف مصفوفة إعدادات واحدة تُستهلك عبر الكلاس Config.
 * كل قيمة قابلة للتجاوز عن طريق متغيّرات البيئة (Environment Variables) حتى
 * يبقى الكود ثابتًا بين بيئات التطوير والإنتاج دون تعديل مباشر.
 *
 * @package ToFiXStream\Config
 */

declare(strict_types=1);

/**
 * دالة مساعدة صغيرة لقراءة متغيّر بيئة مع قيمة افتراضية.
 *
 * @param string $key     اسم المتغيّر.
 * @param mixed  $default القيمة الافتراضية عند غياب المتغيّر.
 * @return mixed
 */
$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    // تحويل القيم المنطقية النصّية إلى Boolean حقيقي.
    return match (strtolower((string) $value)) {
        'true', '1', 'yes', 'on'  => true,
        'false', '0', 'no', 'off' => false,
        default                    => $value,
    };
};

// الجذر المطلق للمشروع (مجلد tofix-stream).
$root = dirname(__DIR__);

return [

    // ---------------------------------------------------------------------
    // معلومات عامة عن التطبيق
    // ---------------------------------------------------------------------
    'app' => [
        'name'      => 'ToFi X Stream',
        'version'   => '1.0.0',
        'env'       => $env('APP_ENV', 'production'),      // production | development
        'debug'     => $env('APP_DEBUG', false),
        'timezone'  => $env('APP_TIMEZONE', 'UTC'),
        // العنوان العام للمنصّة، يُستخدم لبناء الروابط الجديدة لإعادة البث.
        // اتركه فارغًا (الافتراضي) ليُكتشف نطاقك تلقائيًا من الطلب،
        // أو اضبط APP_URL يدويًا مثل: https://test.tofi-xtv.com
        'base_url'  => rtrim((string) $env('APP_URL', ''), '/'),
        'locale'    => 'ar',
    ],

    // ---------------------------------------------------------------------
    // مسارات نظام الملفات
    // ---------------------------------------------------------------------
    'paths' => [
        'root'     => $root,
        'config'   => $root . '/config',
        'classes'  => $root . '/classes',
        'storage'  => $root . '/storage',   // ملفات JSON للتخزين
        'streams'  => $root . '/streams',   // (غير مستخدم — أُبقي للتوافق)
        'cache'    => $root . '/cache',      // كاش البروكسي والمانيفست
        'logs'     => $root . '/logs',       // ملفات السجلّات
        'assets'   => $root . '/assets',
    ],

    // ---------------------------------------------------------------------
    // إعدادات التخزين (JSON Storage)
    // ---------------------------------------------------------------------
    'storage' => [
        'channels_file'  => $root . '/storage/channels.json',
        'settings_file'  => $root . '/storage/settings.json',
        'stats_file'     => $root . '/storage/stats.json',
        // قفل الكتابة لتفادي تلف الملف عند الطلبات المتزامنة.
        'use_file_lock'  => true,
    ],

    // ---------------------------------------------------------------------
    // البروكسي الذكي (HLS/DASH Proxy)
    // ---------------------------------------------------------------------
    'proxy' => [
        // مدّة صلاحية كاش المانيفست بالثواني (البثّ المباشر يحتاج قيمة صغيرة).
        'manifest_ttl'   => (int) $env('PROXY_MANIFEST_TTL', 2),
        'segment_ttl'    => (int) $env('PROXY_SEGMENT_TTL', 10),
        // تفعيل الكاش ودمج الطلبات: اتصال واحد بالمصدر مهما كثر المشاهدون.
        'cache_enabled'  => $env('PROXY_CACHE', true),
        // مدّة بقاء المقطع في كاش القرص (يخدم كل المشاهدين من نسخة واحدة).
        'segment_cache_ttl' => (int) $env('PROXY_SEGMENT_CACHE_TTL', 120),
        // User-Agent يُرسل للمصدر الأصلي. الافتراضي VLC لأنّ أغلب سيرفرات IPTV
        // تقبل مشغّلات معروفة فقط وترفض الوكلاء المجهولين.
        'upstream_ua'    => $env('PROXY_UA', 'VLC/3.0.20 LibVLC/3.0.20'),
        // مهلة الاتصال بالمصدر الأصلي.
        'timeout'        => (int) $env('PROXY_TIMEOUT', 20),
        'connect_timeout'=> (int) $env('PROXY_CONNECT_TIMEOUT', 8),
        // انتحال Referer/Origin — يُعطّل افتراضيًا لأنّ بعض سيرفرات IPTV ترفض
        // الطلبات التي تحمل Referer. فعّله فقط للمصادر التي تتطلّبه.
        'spoof_referer'  => $env('PROXY_SPOOF_REFERER', false),
        'follow_redirects' => true,
        'max_redirects'  => 5,
    ],

    // ---------------------------------------------------------------------
    // الأمن (Security)
    // ---------------------------------------------------------------------
    'security' => [
        // المفتاح السرّي لتوقيع الروابط والتوكنات (غيّره في الإنتاج!).
        'secret_key'      => $env('APP_SECRET', 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_KEY'),
        // مدّة صلاحية الرابط الموقّع بالثواني.
        'signed_url_ttl'  => (int) $env('SIGNED_URL_TTL', 3600),
        // الحماية من الاقتباس المباشر للروابط (Hotlink Protection).
        'hotlink_protection' => $env('HOTLINK_PROTECTION', false),
        // قائمة النطاقات المسموح لها بتضمين المشغّل (Referer allowlist).
        'allowed_referers' => array_filter(explode(',', (string) $env('ALLOWED_REFERERS', ''))),
        // قائمة عناوين IP المسموح لها (فارغة = الجميع).
        'ip_allowlist'    => array_filter(explode(',', (string) $env('IP_ALLOWLIST', ''))),
        // تحديد معدّل الطلبات (Rate Limit) لكل IP في نافذة زمنية.
        // القيمة مرتفعة لأن مشاهد HLS الواحد يجلب المانيفست + عدّة مقاطع كل دقيقة.
        'rate_limit'      => [
            'enabled'  => $env('RATE_LIMIT', true),
            'max'      => (int) $env('RATE_LIMIT_MAX', 600),   // عدد الطلبات لكل IP
            'window'   => (int) $env('RATE_LIMIT_WINDOW', 60), // خلال كم ثانية
        ],
        // مفتاح API للوصول إلى نقاط النهاية الإدارية.
        'api_key'         => $env('API_KEY', 'tofix_admin_key_change_me'),
    ],

    // ---------------------------------------------------------------------
    // إعدادات لوحة التحكم
    // ---------------------------------------------------------------------
    'dashboard' => [
        'title'          => 'ToFi X Stream — Control Panel',
        'items_per_page' => 12,
        // تصنيفات القنوات الافتراضية.
        'categories'     => ['Sports', 'News', 'Movies', 'Kids', 'Music', 'Documentary', 'Entertainment', 'General'],
    ],
];
