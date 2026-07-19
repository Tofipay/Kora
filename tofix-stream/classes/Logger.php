<?php

/**
 * classes/Logger.php
 * -----------------------------------------------------------------------------
 * مُسجّل أحداث بسيط يكتب رسائل مُنسّقة في ملفات يومية داخل مجلّد logs/.
 * يدعم مستويات: debug, info, warning, error.
 *
 * @package ToFiXStream\Core
 */

declare(strict_types=1);

namespace ToFiXStream;

final class Logger
{
    /** مستويات السجل المدعومة. */
    public const DEBUG   = 'DEBUG';
    public const INFO    = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR   = 'ERROR';

    /**
     * كتابة رسالة في السجل.
     *
     * @param string $level   مستوى السجل (استخدم ثوابت الكلاس).
     * @param string $message نصّ الرسالة.
     * @param array<string,mixed> $context بيانات إضافية تُسلسل كـ JSON.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = Config::get('paths.logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** اختصارات لكل مستوى. */
    public static function debug(string $m, array $c = []): void   { self::log(self::DEBUG, $m, $c); }
    public static function info(string $m, array $c = []): void    { self::log(self::INFO, $m, $c); }
    public static function warning(string $m, array $c = []): void { self::log(self::WARNING, $m, $c); }
    public static function error(string $m, array $c = []): void   { self::log(self::ERROR, $m, $c); }
}
