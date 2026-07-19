<?php

/**
 * classes/Response.php
 * -----------------------------------------------------------------------------
 * أدوات موحّدة لإرجاع استجابات JSON من طبقة الـ API بصيغة ثابتة:
 *   { "success": bool, "data": mixed, "error": ?string, "meta": ?object }
 *
 * @package ToFiXStream\Http
 */

declare(strict_types=1);

namespace ToFiXStream;

final class Response
{
    /**
     * إرسال استجابة JSON وإنهاء التنفيذ.
     *
     * @param mixed $data
     * @param array<string,mixed> $meta
     */
    public static function json(mixed $data, int $status = 200, array $meta = []): never
    {
        self::headers($status);
        echo json_encode([
            'success' => $status < 400,
            'data'    => $data,
            'error'   => null,
            'meta'    => $meta ?: null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * إرسال استجابة خطأ موحّدة.
     *
     * @param string|array<int,string> $error
     */
    public static function error(string|array $error, int $status = 400): never
    {
        self::headers($status);
        echo json_encode([
            'success' => false,
            'data'    => null,
            'error'   => is_array($error) ? implode(' ', $error) : $error,
            'errors'  => is_array($error) ? $error : [$error],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * ضبط هيدرات JSON و CORS.
     */
    private static function headers(int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('X-Content-Type-Options: nosniff');
        }
    }
}
