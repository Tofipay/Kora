<?php

/**
 * classes/Security.php
 * -----------------------------------------------------------------------------
 * مسؤول عن كل ما يخصّ الأمن:
 *   - توليد/التحقّق من الروابط الموقّعة (Signed URLs) والتوكنات المؤقّتة.
 *   - حماية الاقتباس (Hotlink Protection) عبر Referer.
 *   - قوائم السماح لعناوين IP.
 *   - تحديد معدّل الطلبات (Rate Limiting) بتخزين ملفّي بسيط.
 *   - التحقّق من مفتاح API الإداري.
 *
 * يعتمد HMAC-SHA256 للتوقيع، وهو آمن وسريع ولا يتطلّب حالة على الخادم.
 *
 * @package ToFiXStream\Security
 */

declare(strict_types=1);

namespace ToFiXStream;

final class Security
{
    /**
     * توليد توكن موقّع لقناة معيّنة صالح لمدّة محدّدة.
     *
     * @param string   $channelId مُعرّف القناة.
     * @param int|null $ttl       مدّة الصلاحية بالثواني.
     * @return array{token:string,expires:int}
     */
    public static function signToken(string $channelId, ?int $ttl = null): array
    {
        $ttl ??= (int) Config::get('security.signed_url_ttl', 3600);
        $expires = time() + $ttl;
        $payload = $channelId . '|' . $expires;
        $signature = self::hmac($payload);
        // التوكن = expiry.signature بترميز آمن للعناوين.
        $token = $expires . '.' . $signature;
        return ['token' => $token, 'expires' => $expires];
    }

    /**
     * التحقّق من توكن قناة.
     *
     * @return bool صحيح إذا كان التوكن صالحًا وغير منتهٍ.
     */
    public static function verifyToken(string $channelId, string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$expires, $signature] = $parts;
        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false; // منتهي الصلاحية.
        }
        $expected = self::hmac($channelId . '|' . $expires);
        return hash_equals($expected, $signature);
    }

    /**
     * حساب توقيع HMAC-SHA256 بترميز آمن للـ URL.
     */
    private static function hmac(string $payload): string
    {
        $key = (string) Config::get('security.secret_key');
        $raw = hash_hmac('sha256', $payload, $key, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * فحص حماية الاقتباس المباشر (Hotlink) بناءً على رأس Referer.
     *
     * @return bool صحيح = مسموح.
     */
    public static function checkReferer(): bool
    {
        if (!Config::get('security.hotlink_protection', false)) {
            return true;
        }
        $allowed = (array) Config::get('security.allowed_referers', []);
        if (!$allowed) {
            return true;
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            return false; // لا نسمح بغياب Referer عند تفعيل الحماية.
        }
        $host = parse_url($referer, PHP_URL_HOST) ?: '';
        foreach ($allowed as $domain) {
            $domain = trim($domain);
            if ($domain !== '' && str_ends_with($host, $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * فحص قائمة السماح لعناوين IP.
     *
     * @return bool صحيح = مسموح.
     */
    public static function checkIp(): bool
    {
        $allowlist = array_filter((array) Config::get('security.ip_allowlist', []));
        if (!$allowlist) {
            return true; // القائمة فارغة = الجميع مسموح.
        }
        $ip = self::clientIp();
        return in_array($ip, array_map('trim', $allowlist), true);
    }

    /**
     * تحديد معدّل الطلبات لكل IP باستخدام عدّاد ملفّي في مجلّد cache.
     *
     * @return bool صحيح = ضمن الحد المسموح، خطأ = تجاوز.
     */
    public static function rateLimit(): bool
    {
        $cfg = (array) Config::get('security.rate_limit', []);
        if (empty($cfg['enabled'])) {
            return true;
        }

        $max = (int) ($cfg['max'] ?? 120);
        $window = (int) ($cfg['window'] ?? 60);
        $ip = self::clientIp();

        $dir = Config::get('paths.cache') . '/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . md5($ip) . '.json';

        $now = time();
        $data = ['count' => 0, 'reset' => $now + $window];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $data = $decoded;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['count'] <= $max;
    }

    /**
     * التحقّق من مفتاح API الإداري من رأس أو باراميتر.
     */
    public static function checkApiKey(): bool
    {
        $expected = (string) Config::get('security.api_key');
        $provided = $_SERVER['HTTP_X_API_KEY']
            ?? ($_GET['api_key'] ?? ($_POST['api_key'] ?? ''));
        return $provided !== '' && hash_equals($expected, (string) $provided);
    }

    /**
     * استخراج عنوان IP الحقيقي للعميل (يراعي البروكسيات الأمامية).
     */
    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', (string) $_SERVER[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}
