<?php

/**
 * classes/Config.php
 * -----------------------------------------------------------------------------
 * غلاف (Wrapper) بسيط للوصول إلى إعدادات المشروع بأسلوب النقطة (dot notation).
 *
 * مثال:  Config::get('app.name');  Config::get('ffmpeg.binary', 'ffmpeg');
 *
 * يطبّق نمط Singleton الخفيف بحيث تُحمَّل مصفوفة الإعدادات مرّة واحدة فقط.
 *
 * @package ToFiXStream\Core
 */

declare(strict_types=1);

namespace ToFiXStream;

final class Config
{
    /** @var array<string,mixed> مصفوفة الإعدادات المحمّلة. */
    private static array $items = [];

    /** @var bool هل تمّ التحميل بالفعل؟ */
    private static bool $loaded = false;

    /**
     * تحميل ملف الإعدادات إلى الذاكرة. يُستدعى تلقائيًا عند أوّل قراءة.
     *
     * @param string|null $file مسار ملف الإعدادات (اختياري).
     */
    public static function load(?string $file = null): void
    {
        $file ??= dirname(__DIR__) . '/config/config.php';
        self::$items = require $file;
        self::$loaded = true;
    }

    /**
     * قراءة قيمة إعداد عبر مسار منقّط.
     *
     * @param string $key     المسار مثل "security.rate_limit.max".
     * @param mixed  $default القيمة الافتراضية إذا لم يوجد المفتاح.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }

        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * إرجاع كامل مصفوفة الإعدادات (للاستخدام الداخلي/التشخيص).
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }
        return self::$items;
    }

    /**
     * العنوان العام للمنصّة الذي تُبنى منه روابط إعادة البثّ.
     *
     * إن كان APP_URL مضبوطًا في الإعدادات يُستخدم كما هو؛ وإلا يُكتشف تلقائيًا
     * من الطلب الحالي (البروتوكول + النطاق + مجلّد التطبيق داخل جذر الويب).
     * هكذا يظهر الرابط الجديد بنطاقك الحقيقي دون أي إعداد يدوي.
     */
    public static function baseUrl(): string
    {
        $configured = (string) self::get('app.base_url', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        // من سطر الأوامر لا يوجد طلب — نعيد قيمة افتراضية.
        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
            return 'http://localhost:8080';
        }

        // اكتشاف البروتوكول (يراعي البروكسيات/موازِنات الأحمال).
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        // اكتشاف المسار الفرعي إن كان التطبيق داخل مجلّد ضمن جذر الويب.
        $path = '';
        $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : '';
        $appRoot = realpath((string) self::get('paths.root'));
        if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
            $path = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        }

        return rtrim("{$scheme}://{$host}{$path}", '/');
    }
}
