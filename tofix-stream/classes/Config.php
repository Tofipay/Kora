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
}
