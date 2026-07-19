<?php

/**
 * classes/HlsProxy.php
 * -----------------------------------------------------------------------------
 * البروكسي الذكي — قلب المنصّة.
 *
 * الوظيفة:
 *   1. سحب المانيفست (m3u8 / mpd) من المصدر الأصلي عبر cURL.
 *   2. إعادة كتابة كل الروابط الداخلية (ts / m4s / mp4 / init / key / sub-playlists)
 *      لتمرّ من خلال هذا البروكسي بدل المصدر — فيبقى الرابط الأصلي مخفيًّا.
 *   3. بثّ المقاطع (segments) والمفاتيح مباشرة إلى العميل (streaming passthrough).
 *   4. إخفاء الهيدرات الكاشفة للمصدر (Server, X-Powered-By...).
 *
 * يدعم:
 *   - HLS: master + media playlists، الروابط النسبية والمطلقة، وسوم EXT-X-KEY,
 *     EXT-X-MAP, EXT-X-MEDIA (URI=...)، والوسوم التي تحمل روابط بديلة.
 *   - MPEG-DASH: عناصر <BaseURL> و media/initialization templates.
 *
 * آلية الإخفاء:
 *   نُرمّز الرابط الأصلي (base64url) داخل باراميتر "u" موقّع بـ HMAC حتى لا
 *   يستطيع أحد حقن روابط عشوائية (SSRF protection)، فلا يظهر المصدر إطلاقًا.
 *
 * @package ToFiXStream\Proxy
 */

declare(strict_types=1);

namespace ToFiXStream;

final class HlsProxy
{
    /** أنواع المحتوى التي تُعامل كمانيفست يحتاج إعادة كتابة. */
    private const MANIFEST_HINTS = [
        'application/vnd.apple.mpegurl',
        'application/x-mpegurl',
        'audio/mpegurl',
        'application/dash+xml',
    ];

    /** نقطة نهاية هذا البروكسي (تُبنى من الإعدادات). */
    private string $endpoint;

    public function __construct()
    {
        $this->endpoint = Config::baseUrl() . '/proxy/index.php';
    }

    // -------------------------------------------------------------------------
    // توليد الروابط الموقّعة الداخلية
    // -------------------------------------------------------------------------

    /**
     * بناء رابط بروكسي موقّع لعنوان أصلي (يُستخدم عند إعادة كتابة المانيفست).
     *
     * @param string $originalUrl الرابط الأصلي الكامل.
     */
    public function proxifiedUrl(string $originalUrl): string
    {
        $encoded = rtrim(strtr(base64_encode($originalUrl), '+/', '-_'), '=');
        $sig = $this->signUrl($encoded);
        return $this->endpoint . '?u=' . $encoded . '&s=' . $sig;
    }

    /**
     * توقيع الرابط المرمّز لمنع حقن روابط خارجية (SSRF).
     */
    private function signUrl(string $encoded): string
    {
        $key = (string) Config::get('security.secret_key');
        return substr(hash_hmac('sha256', $encoded, $key), 0, 24);
    }

    /**
     * فكّ وتحقّق من رابط مرمّز؛ يُعيد الرابط الأصلي أو null إن كان التوقيع خاطئًا.
     */
    public function resolveUrl(string $encoded, string $signature): ?string
    {
        if (!hash_equals($this->signUrl($encoded), $signature)) {
            return null;
        }
        $padded = str_pad($encoded, strlen($encoded) % 4 === 0 ? strlen($encoded) : strlen($encoded) + 4 - (strlen($encoded) % 4), '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($decoded === false || !preg_match('#^https?://#i', $decoded)) {
            return null;
        }
        return $decoded;
    }

    // -------------------------------------------------------------------------
    // التعامل مع الطلبات
    // -------------------------------------------------------------------------

    /**
     * تمرير طلب إلى المصدر الأصلي مع إعادة كتابة المانيفست عند اللزوم.
     * تكتب المخرجات مباشرة إلى الخرج (echo/stream) وتضبط الهيدرات المناسبة.
     *
     * @param string $originalUrl الرابط الأصلي المطلوب.
     */
    public function handle(string $originalUrl): void
    {
        // 1) جلب الرأس ونوع المحتوى أولًا بطلب خفيف نحدّده لاحقًا.
        $result = $this->fetch($originalUrl);
        if ($result === null) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Bad Gateway: تعذّر الوصول إلى المصدر.';
            Logger::warning('فشل البروكسي في جلب المصدر', ['url' => $originalUrl]);
            return;
        }

        [$body, $contentType, $httpCode] = $result;

        // 2) هل هو مانيفست؟ (بالاعتماد على نوع المحتوى أو امتداد الرابط).
        if ($this->isManifest($originalUrl, $contentType)) {
            $rewritten = $this->rewriteManifest($body, $originalUrl);
            $isDash = str_contains(strtolower($contentType), 'dash') || str_ends_with(strtolower(parse_url($originalUrl, PHP_URL_PATH) ?? ''), '.mpd');
            $outType = $isDash ? 'application/dash+xml' : 'application/vnd.apple.mpegurl';

            $this->emitCommonHeaders();
            header('Content-Type: ' . $outType);
            // البثّ المباشر لا يُكاش طويلًا.
            header('Cache-Control: no-cache, no-store, must-revalidate');
            http_response_code($httpCode ?: 200);
            echo $rewritten;
            return;
        }

        // 3) محتوى ثنائي (segment/key/init): مرّره كما هو.
        $this->emitCommonHeaders();
        header('Content-Type: ' . ($contentType ?: 'application/octet-stream'));
        header('Cache-Control: public, max-age=' . (int) Config::get('proxy.segment_ttl', 10));
        http_response_code($httpCode ?: 200);
        echo $body;
    }

    // -------------------------------------------------------------------------
    // جلب المحتوى من المصدر
    // -------------------------------------------------------------------------

    /**
     * جلب رابط عبر cURL مع إخفاء هويّة الزائر وإرسال هيدرات مناسبة.
     *
     * @return array{0:string,1:string,2:int}|null [body, contentType, httpCode]
     */
    private function fetch(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $host = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        $headers = [
            'Accept: */*',
            'Connection: keep-alive',
        ];
        // انتحال Referer/Origin للمصادر التي تحمي روابطها بذلك.
        if (Config::get('proxy.spoof_referer', true)) {
            $headers[] = 'Referer: ' . $host . '/';
            $headers[] = 'Origin: ' . $host;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => (bool) Config::get('proxy.follow_redirects', true),
            CURLOPT_MAXREDIRS      => (int) Config::get('proxy.max_redirects', 5),
            CURLOPT_USERAGENT      => (string) Config::get('proxy.upstream_ua'),
            CURLOPT_TIMEOUT        => (int) Config::get('proxy.timeout', 15),
            CURLOPT_CONNECTTIMEOUT => (int) Config::get('proxy.connect_timeout', 8),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '', // دعم gzip تلقائيًا.
        ]);

        // احترام بروكسي الخروج القياسي إن وُجد في البيئة (بيئات محجوبة/حاويات).
        $envProxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy') ?: '';
        if ($envProxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $envProxy);
            // تجاوز البروكسي للمضيفين المحلّيين/الداخليين (NO_PROXY) — مهمّ
            // لمصادر localhost/الشبكة الداخلية أو مخرجات FFmpeg المحلّية.
            $noProxy = getenv('NO_PROXY') ?: getenv('no_proxy') ?: 'localhost,127.0.0.1,::1';
            curl_setopt($ch, CURLOPT_NOPROXY, $noProxy);
        }
        // استخدام حزمة الشهادات المخصّصة إن كانت معرّفة في البيئة.
        $caBundle = getenv('CURL_CA_BUNDLE') ?: '';
        if ($caBundle !== '' && is_file($caBundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            Logger::warning('cURL error في البروكسي', ['url' => $url, 'err' => curl_error($ch)]);
            curl_close($ch);
            return null;
        }
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [(string) $body, $contentType, $httpCode];
    }

    // -------------------------------------------------------------------------
    // كشف نوع المانيفست
    // -------------------------------------------------------------------------

    /**
     * هل الرابط/النوع يمثّل مانيفست HLS أو DASH؟
     */
    private function isManifest(string $url, string $contentType): bool
    {
        $ct = strtolower($contentType);
        foreach (self::MANIFEST_HINTS as $hint) {
            if (str_contains($ct, $hint)) {
                return true;
            }
        }
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        return str_ends_with($path, '.m3u8') || str_ends_with($path, '.mpd');
    }

    // -------------------------------------------------------------------------
    // إعادة كتابة المانيفست
    // -------------------------------------------------------------------------

    /**
     * توجيه إعادة الكتابة حسب نوع المانيفست (HLS نصّي أو DASH XML).
     */
    private function rewriteManifest(string $body, string $baseUrl): string
    {
        $path = strtolower((string) (parse_url($baseUrl, PHP_URL_PATH) ?? ''));
        if (str_ends_with($path, '.mpd') || str_contains($body, '<MPD')) {
            return $this->rewriteDash($body, $baseUrl);
        }
        return $this->rewriteHls($body, $baseUrl);
    }

    /**
     * إعادة كتابة مانيفست HLS (m3u8) سطرًا بسطر.
     *
     * القواعد:
     *   - السطر الذي لا يبدأ بـ # ويحمل رابطًا => رابط مقطع/بلاي-ليست => نُبدّله.
     *   - وسوم تحمل URI="..." (EXT-X-KEY, EXT-X-MAP, EXT-X-MEDIA) => نستبدل URI.
     */
    private function rewriteHls(string $body, string $baseUrl): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($trim === '') {
                $out[] = $line;
                continue;
            }

            // وسوم تحتوي روابط داخل URI="..."
            if (str_starts_with($trim, '#')) {
                if (preg_match('/URI="([^"]+)"/i', $trim)) {
                    $line = preg_replace_callback(
                        '/URI="([^"]+)"/i',
                        fn (array $m): string => 'URI="' . $this->proxifiedUrl($this->absolutize($m[1], $baseUrl)) . '"',
                        $line
                    );
                }
                $out[] = $line;
                continue;
            }

            // سطر رابط عادي (مقطع أو بلاي-ليست فرعية).
            $absolute = $this->absolutize($trim, $baseUrl);
            $out[] = $this->proxifiedUrl($absolute);
        }

        return implode("\n", $out);
    }

    /**
     * إعادة كتابة مانيفست DASH (MPD): نعالج BaseURL وقوالب media/initialization
     * وأي روابط مطلقة داخل الوثيقة.
     */
    private function rewriteDash(string $body, string $baseUrl): string
    {
        // 1) عناصر <BaseURL>...</BaseURL>
        $body = preg_replace_callback(
            '#<BaseURL>\s*([^<]+?)\s*</BaseURL>#i',
            fn (array $m): string => '<BaseURL>' . $this->proxifiedUrl($this->absolutize(trim($m[1]), $baseUrl)) . '</BaseURL>',
            $body
        ) ?? $body;

        // 2) سمات media="..." و initialization="..." (قد تحوي قوالب $Number$).
        foreach (['media', 'initialization', 'sourceURL'] as $attr) {
            $body = preg_replace_callback(
                '/' . $attr . '="([^"]+)"/i',
                function (array $m) use ($baseUrl, $attr): string {
                    // القوالب التي تحوي $...$ نُبقيها نسبيّة ونمرّر الجذر عبر BaseURL،
                    // أمّا الروابط المطلقة فنبدّلها مباشرة.
                    if (str_contains($m[1], '$')) {
                        return $m[0];
                    }
                    return $attr . '="' . $this->proxifiedUrl($this->absolutize($m[1], $baseUrl)) . '"';
                },
                $body
            ) ?? $body;
        }

        return $body;
    }

    /**
     * تحويل رابط نسبي إلى مطلق بالاعتماد على رابط المانيفست الأساسي.
     */
    private function absolutize(string $ref, string $base): string
    {
        $ref = trim($ref);

        // رابط مطلق بالفعل.
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        // رابط يبدأ من جذر النطاق.
        if (str_starts_with($ref, '/')) {
            return $origin . $ref;
        }

        // رابط بلا بروتوكول (//cdn...).
        if (str_starts_with($ref, '//')) {
            return $scheme . ':' . $ref;
        }

        // رابط نسبي بالنسبة لمجلّد المانيفست.
        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $origin . $dir . '/' . $ref;
    }

    // -------------------------------------------------------------------------
    // الهيدرات
    // -------------------------------------------------------------------------

    /**
     * إرسال هيدرات مشتركة: CORS للمشغّلات، وإزالة الكاشف عن المصدر.
     */
    private function emitCommonHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('X-Powered-By: ToFi X Stream');
        header_remove('Server');
    }
}
