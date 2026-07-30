<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  hls-core.php — محرّك البروكسي المشترك
 * ───────────────────────────────────────────────────────────────────────────
 *  يستخدمه كل من index.php (وضع request_driven) و worker.php (وضع worker).
 *
 *  المبادئ الأساسية:
 *    • Single Flight  : اتصال واحد فقط بالمصدر لكل قائمة ولكل مقطع.
 *    • Stale-While-Revalidate : من لا يملك القفل يأخذ آخر نسخة صالحة فورًا.
 *    • لا انتظار حاجز (blocking flock) أمام المشاهدين ما دام هناك كاش صالح.
 *    • المقاطع تُحفظ باسم يطابق الرابط العام، فيرسلها Apache/Nginx مباشرة.
 *    • القوائم تُحفظ خارج المسار العام (‎.tofi-cache‎) وتُبنى محليًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (defined('HLS_CORE_LOADED')) {
    return;
}

define('HLS_CORE_LOADED', true);
define('HLS_ROOT', __DIR__);

/* ═════════════════════════ الإعدادات ═════════════════════════ */

function hls_default_config(): array
{
    return [
        'mode' => 'request_driven',

        'source_base_url' => 'https://3.3loka.site:443/live/Abuturki/Abuturki/',
        'public_base_url' => 'https://live-api-tofixtv.tofi-xtv.com',
        'allowed_source_hosts' => ['3loka.site'],

        /*
         * توافق رجعي: هذه هي المفاتيح المستخدمة في النسخة السابقة. تبقى
         * افتراضية حتى لا تتعطّل الروابط الموقّعة الصادرة قبل التحديث.
         * ضع بديلًا لها في config.php أو في متغيّرات البيئة.
         */
        'token_secret' => 'ToFi-X-TV-3Loka-Proxy-Key-2026-7f4a91c8e2b65d03',
        'stream_signing_secret' =>
            'ToFi-Stream-Signing-2026-b7e43a1f9062cd85d8417a3e',

        'app_user_agent' => 'MTX Player',
        'stream_token_ttl' => 3600,
        'token_clock_skew' => 30,

        'playlist_min_refresh_ms' => 500,
        'playlist_max_refresh_ms' => 1500,
        'playlist_hard_stale_min_ms' => 4000,
        'playlist_hard_stale_max_ms' => 8000,
        'playlist_cold_wait_ms' => 400,
        'master_refresh_ms' => 10000,
        'master_hard_stale_ms' => 120000,

        'live_window_segments' => 8,
        'live_window_memory' => 256,
        'shared_media_playlist' => false,

        'playlist_connect_timeout' => 3,
        'playlist_timeout' => 6,
        'playlist_attempts' => 2,
        'segment_connect_timeout' => 3,
        'segment_timeout' => 10,
        'segment_attempts' => 2,
        'segment_wait_ms' => 3000,
        'segment_max_bytes' => 33554432,

        'cache_root' => HLS_ROOT . '/.tofi-cache',
        'segment_cache_root' => HLS_ROOT . '/hls-cache',
        'segment_keep_seconds' => 300,
        'cleanup_interval_seconds' => 30,
        'cleanup_max_files' => 600,

        'viewer_backend' => 'auto',
        'viewer_active_seconds' => 30,
        /*
         * في وضع shared_media_playlist لا يطلب المشغل رابط /stream إلا مرة
         * واحدة، فالجلسة تحتاج مهلة أطول (أو استدعاء ping_url من التطبيق).
         */
        'shared_playlist_viewer_lease' => 120,
        'viewer_touch_min_interval' => 10,
        'viewer_retention_seconds' => 120,
        'redis_host' => '127.0.0.1',
        'redis_port' => 6379,
        'redis_auth' => '',
        'redis_prefix' => 'tofi:v:',

        'metrics_enabled' => false,
        'metrics_token' => '',

        'worker_channels' => [10],
        'worker_prefetch' => true,
        'worker_prefetch_max' => 4,
        'worker_heartbeat_seconds' => 5,

        'allow_insecure_source' => false,
    ];
}

/**
 * أسماء متغيّرات البيئة المدعومة. البيئة تتفوّق على config.php دائمًا.
 */
function hls_environment_map(): array
{
    return [
        'TOFI_MODE' => ['mode', 'string'],
        'TOFI_SOURCE_BASE_URL' => ['source_base_url', 'string'],
        'TOFI_PUBLIC_BASE_URL' => ['public_base_url', 'string'],
        'TOFI_ALLOWED_SOURCE_HOSTS' => ['allowed_source_hosts', 'list'],
        'TOFI_TOKEN_SECRET' => ['token_secret', 'string'],
        'TOFI_STREAM_SIGNING_SECRET' => ['stream_signing_secret', 'string'],
        'TOFI_APP_USER_AGENT' => ['app_user_agent', 'string'],
        'TOFI_STREAM_TOKEN_TTL' => ['stream_token_ttl', 'int'],
        'TOFI_CACHE_ROOT' => ['cache_root', 'string'],
        'TOFI_SEGMENT_CACHE_ROOT' => ['segment_cache_root', 'string'],
        'TOFI_LIVE_WINDOW_SEGMENTS' => ['live_window_segments', 'int'],
        'TOFI_VIEWER_BACKEND' => ['viewer_backend', 'string'],
        'TOFI_SHARED_MEDIA_PLAYLIST' => ['shared_media_playlist', 'bool'],
        'TOFI_METRICS_ENABLED' => ['metrics_enabled', 'bool'],
        'TOFI_METRICS_TOKEN' => ['metrics_token', 'string'],
        'TOFI_WORKER_CHANNELS' => ['worker_channels', 'intlist'],
        'TOFI_ALLOW_INSECURE_SOURCE' => ['allow_insecure_source', 'bool'],
        'TOFI_SEGMENT_KEEP_SECONDS' => ['segment_keep_seconds', 'int'],
    ];
}

/**
 * قراءة متغيّر بيئة بطريقة تعمل على كل الاستضافات.
 *
 * على LiteSpeed/PHP-FPM (وهو ما تستخدمه Hostinger) لا يرى getenv() دائمًا
 * قيم SetEnv الموضوعة في ‎.htaccess‎، لكنها تصل عبر ‎$_SERVER‎.
 */
function hls_env(string $name): ?string
{
    $value = getenv($name);

    if (is_string($value) && $value !== '') {
        return $value;
    }

    foreach ([$_SERVER, $_ENV] as $source) {
        if (isset($source[$name]) && is_scalar($source[$name])) {
            $value = (string) $source[$name];

            if ($value !== '') {
                return $value;
            }
        }
    }

    return null;
}

function hls_config(?string $key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $config = hls_default_config();

        $file = HLS_ROOT . '/config.php';

        if (is_file($file)) {
            /** @noinspection PhpIncludeInspection */
            $loaded = require $file;

            if (is_array($loaded)) {
                foreach ($loaded as $name => $value) {
                    if (!array_key_exists($name, $config)) {
                        $config[$name] = $value;
                        continue;
                    }

                    /*
                     * القيم الفارغة في ملف الإعدادات لا تلغي الافتراضي،
                     * حتى يبقى النظام يعمل عند ترك حقل بلا قيمة.
                     */
                    if ($value === '' || $value === null || $value === []) {
                        continue;
                    }

                    $config[$name] = $value;
                }
            }
        }

        foreach (hls_environment_map() as $variable => $definition) {
            $raw = hls_env($variable);

            if ($raw === null) {
                continue;
            }

            [$name, $type] = $definition;
            $config[$name] = hls_cast_config_value($raw, $type);
        }

        $config['live_window_segments'] = max(
            6,
            min(12, (int) $config['live_window_segments'])
        );
        $config['playlist_cold_wait_ms'] = max(
            50,
            min(500, (int) $config['playlist_cold_wait_ms'])
        );
        $config['public_base_url'] = rtrim(
            (string) $config['public_base_url'],
            '/'
        );
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

function hls_cast_config_value(string $raw, string $type)
{
    switch ($type) {
        case 'int':
            return (int) $raw;
        case 'bool':
            return in_array(
                strtolower($raw),
                ['1', 'true', 'yes', 'on'],
                true
            );
        case 'list':
            return array_values(array_filter(array_map(
                'trim',
                explode(',', $raw)
            )));
        case 'intlist':
            return array_values(array_filter(array_map(
                'intval',
                explode(',', $raw)
            )));
        default:
            return $raw;
    }
}

/* ═════════════════════════ أدوات عامة ═════════════════════════ */

function hls_now_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function hls_ensure_directory(string $directory, int $mode = 0755): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!@mkdir($directory, $mode, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create directory');
    }
}

/**
 * كتابة ذرّية: ملف مؤقت ثم rename. لا يرى أي قارئ ملفًا نصف مكتوب.
 */
function hls_atomic_write(
    string $path,
    string $contents,
    int $mode = 0644
): void {
    hls_ensure_directory(dirname($path));

    $temporary = $path . '.tmp.' . getmypid() . '.'
        . bin2hex(random_bytes(4));

    $handle = @fopen($temporary, 'wb');

    if ($handle === false) {
        throw new RuntimeException('Unable to open temporary file');
    }

    $length = strlen($contents);
    $written = 0;

    while ($written < $length) {
        $chunk = @fwrite($handle, substr($contents, $written));

        if ($chunk === false || $chunk === 0) {
            @fclose($handle);
            @unlink($temporary);
            throw new RuntimeException('Unable to write cache file');
        }

        $written += $chunk;
    }

    @fflush($handle);
    @fclose($handle);
    @chmod($temporary, $mode);

    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to publish cache file');
    }
}

function hls_log(string $message): void
{
    error_log('[ToFi HLS] ' . $message);
}

/* ═════════════════════════ القياسات ═════════════════════════ */

function hls_metric_names(): array
{
    return [
        'upstream_playlist_fetches',
        'upstream_segment_fetches',
        'upstream_errors',
        'playlist_cache_hits',
        'playlist_cache_misses',
        'segment_cache_hits',
        'segment_first_fetch',
        'lock_contention',
        'stale_playlist_served',
        'playlist_wait_resolved',
        'playlist_unavailable',
        'segment_wait_timeout',
    ];
}

function hls_metric_inc(string $name, int $amount = 1): void
{
    if (!hls_config('metrics_enabled')) {
        return;
    }

    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $key = 'tofi_metric_' . $name;

        if (apcu_inc($key, $amount) === false) {
            apcu_store($key, $amount);
        }

        return;
    }

    try {
        $directory = hls_config('cache_root') . '/metrics';
        hls_ensure_directory($directory, 0700);
        $file = $directory . '/' . $name . '.count';
        $handle = @fopen($file, 'c+');

        if ($handle === false) {
            return;
        }

        if (flock($handle, LOCK_EX)) {
            $current = (int) stream_get_contents($handle);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) ($current + $amount));
            fflush($handle);
            flock($handle, LOCK_UN);
        }

        fclose($handle);
    } catch (Throwable $error) {
        /* القياسات لا توقف البث أبدًا. */
    }
}

function hls_metric_snapshot(): array
{
    $values = [];

    foreach (hls_metric_names() as $name) {
        $value = 0;

        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $stored = apcu_fetch('tofi_metric_' . $name);
            $value = is_int($stored) ? $stored : 0;
        } else {
            $file = hls_config('cache_root') . '/metrics/' . $name . '.count';

            if (is_file($file)) {
                $value = (int) @file_get_contents($file);
            }
        }

        $values[$name] = $value;
    }

    return $values;
}

function hls_metric_reset(): void
{
    foreach (hls_metric_names() as $name) {
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            apcu_delete('tofi_metric_' . $name);
            continue;
        }

        $file = hls_config('cache_root') . '/metrics/' . $name . '.count';

        if (is_file($file)) {
            @unlink($file);
        }
    }
}

/* ═════════════════════════ الروابط والمصدر ═════════════════════════ */

function hls_source_allowed(string $url): bool
{
    $parts = @parse_url($url);

    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) $parts['host']);

    if ($scheme !== 'https') {
        if (!($scheme === 'http' && hls_config('allow_insecure_source'))) {
            return false;
        }
    }

    foreach ((array) hls_config('allowed_source_hosts') as $allowed) {
        $allowed = strtolower(trim((string) $allowed));

        if ($allowed === '') {
            continue;
        }

        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }

    return false;
}

function hls_resolve_url(string $baseUrl, string $relativeUrl): string
{
    $relativeUrl = trim($relativeUrl);

    if (preg_match('#^https?://#i', $relativeUrl) === 1) {
        return $relativeUrl;
    }

    $base = @parse_url($baseUrl);

    if (!is_array($base) || empty($base['host'])) {
        throw new RuntimeException('Invalid base URL');
    }

    $scheme = (string) ($base['scheme'] ?? 'https');
    $host = (string) $base['host'];
    $port = isset($base['port']) ? ':' . (int) $base['port'] : '';

    if (str_starts_with($relativeUrl, '//')) {
        return $scheme . ':' . $relativeUrl;
    }

    if (str_starts_with($relativeUrl, '/')) {
        $pathAndQuery = $relativeUrl;
    } else {
        $basePath = (string) ($base['path'] ?? '/');
        $directory = rtrim(
            str_replace('\\', '/', dirname($basePath)),
            '/'
        );
        $pathAndQuery = $directory . '/' . $relativeUrl;
    }

    $query = '';
    $questionPosition = strpos($pathAndQuery, '?');

    if ($questionPosition !== false) {
        $query = substr($pathAndQuery, $questionPosition);
        $pathAndQuery = substr($pathAndQuery, 0, $questionPosition);
    }

    $parts = [];

    foreach (explode('/', $pathAndQuery) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return $scheme . '://' . $host . $port
        . '/' . implode('/', $parts) . $query;
}

function hls_detect_extension(string $url, string $fallback = 'ts'): string
{
    $path = strtolower((string) (@parse_url($url, PHP_URL_PATH) ?? ''));
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    $known = ['ts', 'm3u8', 'm4s', 'mp4', 'm4a', 'aac', 'vtt', 'key', 'webvtt'];

    if (is_string($extension) && in_array($extension, $known, true)) {
        return $extension === 'webvtt' ? 'vtt' : $extension;
    }

    return $fallback;
}

/**
 * الاسم العام للمقطع = بصمة قصيرة من رابط المصدر. الاسم نفسه لكل المشاهدين،
 * ومطابق تمامًا لاسم الملف داخل hls-cache حتى يرسله Apache مباشرة.
 */
function hls_asset_name(string $sourceUrl): string
{
    return substr(
        hash_hmac('sha256', $sourceUrl, (string) hls_config('token_secret')),
        0,
        40
    );
}

function hls_asset_map_path(string $name): string
{
    return hls_config('cache_root') . '/map/'
        . substr($name, 0, 2) . '/' . $name . '.url';
}

/**
 * يسجّل ارتباط الاسم العام برابط المصدر داخل مجلد محمي من الفتح المباشر.
 */
function hls_asset_map_store(string $name, string $sourceUrl): void
{
    $path = hls_asset_map_path($name);

    if (is_file($path)) {
        /* لمسة خفيفة تُبقي الخريطة حيّة أمام التنظيف الدوري. */
        @touch($path);
        return;
    }

    try {
        hls_atomic_write($path, $sourceUrl, 0600);
    } catch (Throwable $error) {
        hls_log('map store failed: ' . $error->getMessage());
    }
}

function hls_asset_map_lookup(string $name): ?string
{
    $path = hls_asset_map_path($name);

    if (!is_file($path)) {
        return null;
    }

    $url = trim((string) @file_get_contents($path));

    if ($url === '' || !hls_source_allowed($url)) {
        return null;
    }

    return $url;
}

function hls_public_asset_url(string $sourceUrl, ?string $forceExtension = null): string
{
    $extension = $forceExtension ?? hls_detect_extension($sourceUrl);
    $name = hls_asset_name($sourceUrl);

    hls_asset_map_store($name, $sourceUrl);

    return hls_config('public_base_url')
        . '/hls-cache/' . $name . '.' . $extension;
}

function hls_source_headers(string $sourceUrl): array
{
    $host = (string) (@parse_url($sourceUrl, PHP_URL_HOST) ?? '');

    if ($host === '') {
        $host = (string) (
            @parse_url((string) hls_config('source_base_url'), PHP_URL_HOST)
            ?? ''
        );
    }

    $scheme = strtolower(
        (string) (@parse_url($sourceUrl, PHP_URL_SCHEME) ?? 'https')
    );
    $origin = ($scheme === 'http' ? 'http://' : 'https://') . $host;

    return [
        'User-Agent' => 'Mozilla/5.0 (Linux; Android 16) '
            . 'AppleWebKit/537.36 Chrome/139.0.0.0 Mobile Safari/537.36',
        'Accept' => '*/*',
        'Referer' => $origin . '/',
        'Origin' => $origin,
        'Cache-Control' => 'no-cache',
    ];
}

/* ═════════════════════════ الاتصال بالمصدر ═════════════════════════ */

/**
 * طلب واحد إلى المصدر بمهلات صارمة. لا يحتجز عامل PHP أكثر من $timeout.
 */
function hls_http_request(
    string $url,
    int $connectTimeout,
    int $timeout,
    int $maxBytes = 0
): array {
    if (!hls_source_allowed($url)) {
        throw new RuntimeException('Source host not allowed');
    }

    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('Unable to initialize cURL');
    }

    $headers = ['Accept-Encoding: identity'];

    foreach (hls_source_headers($url) as $name => $value) {
        if ($value !== '') {
            $headers[] = $name . ': ' . $value;
        }
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => max(1, $connectTimeout),
        CURLOPT_TIMEOUT => max(1, $timeout),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_NOSIGNAL => true,
    ];

    if (defined('CURLPROTO_HTTP') && defined('CURLOPT_PROTOCOLS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }

    if ($maxBytes > 0) {
        $options[CURLOPT_BUFFERSIZE] = 65536;
        $options[CURLOPT_NOPROGRESS] = false;
        $options[CURLOPT_PROGRESSFUNCTION] = static function (
            $resource,
            $downloadSize,
            $downloaded
        ) use ($maxBytes) {
            return ($downloaded > $maxBytes || $downloadSize > $maxBytes)
                ? 1
                : 0;
        };
    }

    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);

    if ($body === false) {
        $message = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('cURL error: ' . $message);
    }

    $response = [
        'body' => (string) $body,
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'effective_url' => (string) curl_getinfo(
            $curl,
            CURLINFO_EFFECTIVE_URL
        ),
        'content_type' => strtolower(trim((string) (
            curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?? ''
        ))),
    ];

    curl_close($curl);

    /* حماية إضافية: إعادة التوجيه يجب أن تبقى داخل النطاق المسموح. */
    if (
        $response['effective_url'] !== ''
        && !hls_source_allowed($response['effective_url'])
    ) {
        throw new RuntimeException('Redirected outside allowed source');
    }

    return $response;
}

function hls_fetch_playlist_body(string $url): array
{
    $attempts = max(1, (int) hls_config('playlist_attempts'));
    $lastError = null;

    /*
     * ميزانية زمنية كلية: المحاولة الثانية تُنفَّذ فقط إن بقي وقت. فشل
     * سريع (اتصال مرفوض) يُعاد فورًا، أما مهلة كاملة فلا تتضاعف.
     */
    $budgetMs = (int) hls_config('playlist_timeout') * 1000;
    $startedAt = hls_now_ms();

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            hls_metric_inc('upstream_playlist_fetches');

            $response = hls_http_request(
                $url,
                (int) hls_config('playlist_connect_timeout'),
                (int) hls_config('playlist_timeout'),
                4 * 1024 * 1024
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new RuntimeException(
                    'Playlist HTTP ' . $response['status']
                );
            }

            if (strpos(ltrim($response['body']), '#EXTM3U') !== 0) {
                throw new RuntimeException('Upstream response is not HLS');
            }

            return $response;
        } catch (Throwable $error) {
            $lastError = $error;
            hls_metric_inc('upstream_errors');

            if (
                $attempt < $attempts
                && (hls_now_ms() - $startedAt) < $budgetMs
            ) {
                /* تشتيت بسيط يمنع تزامن المحاولات بين العمليات. */
                usleep(random_int(80, 220) * 1000);
                continue;
            }

            break;
        }
    }

    throw new RuntimeException(
        $lastError !== null
            ? $lastError->getMessage()
            : 'Unable to load playlist'
    );
}

function hls_fetch_segment_body(string $url, string $extension): array
{
    $attempts = max(1, (int) hls_config('segment_attempts'));
    $maximum = (int) hls_config('segment_max_bytes');
    $lastError = null;

    $budgetMs = (int) hls_config('segment_timeout') * 1000;
    $startedAt = hls_now_ms();

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        try {
            hls_metric_inc('upstream_segment_fetches');

            $response = hls_http_request(
                $url,
                (int) hls_config('segment_connect_timeout'),
                (int) hls_config('segment_timeout'),
                $maximum
            );

            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new RuntimeException(
                    'Asset HTTP ' . $response['status']
                );
            }

            $length = strlen($response['body']);

            if ($length === 0) {
                throw new RuntimeException('Empty asset body');
            }

            if ($length > $maximum) {
                throw new RuntimeException('Asset too large');
            }

            if (!hls_content_type_acceptable(
                $response['content_type'],
                $extension
            )) {
                throw new RuntimeException(
                    'Unexpected content type for asset'
                );
            }

            if (
                $extension === 'ts'
                && !hls_looks_like_transport_stream($response['body'])
            ) {
                throw new RuntimeException('Invalid MPEG-TS segment');
            }

            return $response;
        } catch (Throwable $error) {
            $lastError = $error;
            hls_metric_inc('upstream_errors');

            if (
                $attempt < $attempts
                && (hls_now_ms() - $startedAt) < $budgetMs
            ) {
                usleep(random_int(60, 180) * 1000);
                continue;
            }

            break;
        }
    }

    throw new RuntimeException(
        $lastError !== null
            ? $lastError->getMessage()
            : 'Unable to load asset'
    );
}

function hls_content_type_acceptable(
    string $contentType,
    string $extension
): bool {
    if ($contentType === '') {
        return true;
    }

    $type = trim(explode(';', $contentType)[0]);

    if ($type === '') {
        return true;
    }

    if ($extension === 'vtt') {
        return str_starts_with($type, 'text/')
            || str_contains($type, 'vtt')
            || str_contains($type, 'octet-stream');
    }

    /* صفحة خطأ HTML من المصدر يجب ألا تُحفظ كمقطع. */
    if (str_starts_with($type, 'text/html')) {
        return false;
    }

    return str_starts_with($type, 'video/')
        || str_starts_with($type, 'audio/')
        || str_starts_with($type, 'application/')
        || str_starts_with($type, 'binary/')
        || str_starts_with($type, 'text/plain');
}

function hls_looks_like_transport_stream(string $body): bool
{
    $length = strlen($body);

    if ($length < 377) {
        return false;
    }

    $maximumOffset = min(187, $length - 377);

    for ($offset = 0; $offset <= $maximumOffset; $offset++) {
        if (
            ord($body[$offset]) === 0x47
            && ord($body[$offset + 188]) === 0x47
            && ord($body[$offset + 376]) === 0x47
        ) {
            return true;
        }
    }

    return false;
}

/* ═════════════════════════ تحليل قوائم HLS ═════════════════════════ */

/**
 * يحلّل قائمة m3u8 (رئيسية أو داخلية) ويحوّل كل الروابط إلى روابط عامة.
 */
function hls_parse_playlist(string $body, string $baseUrl): array
{
    $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

    $result = [
        'is_master' => false,
        'is_endlist' => false,
        'version' => 3,
        'target_duration' => 0.0,
        'media_sequence' => 0,
        'discontinuity_sequence' => 0,
        'part_target' => 0.0,
        'hold_back' => 0.0,
        'part_hold_back' => 0.0,
        'independent_segments' => false,
        'allow_cache' => null,
        'playlist_type' => null,
        'segments' => [],
        'master_lines' => [],
    ];

    $currentKey = null;
    $currentMap = null;

    $pending = [
        'duration' => 0.0,
        'title' => '',
        'pdt' => null,
        'discontinuity' => false,
        'byterange' => null,
        'gap' => false,
        'has_extinf' => false,
    ];

    $resetPending = static function () use (&$pending): void {
        $pending = [
            'duration' => 0.0,
            'title' => '',
            'pdt' => null,
            'discontinuity' => false,
            'byterange' => null,
            'gap' => false,
            'has_extinf' => false,
        ];
    };

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);

        if ($line === '') {
            continue;
        }

        if ($line[0] !== '#') {
            $absolute = hls_resolve_url($baseUrl, $line);

            if ($result['is_master']) {
                $result['master_lines'][] = hls_public_asset_url(
                    $absolute,
                    'm3u8'
                );
                $resetPending();
                continue;
            }

            if (!$pending['has_extinf']) {
                /* سطر بلا EXTINF: نتجاهله بدل بناء مقطع خاطئ. */
                $resetPending();
                continue;
            }

            $result['segments'][] = [
                'uri' => $absolute,
                'duration' => $pending['duration'],
                'title' => $pending['title'],
                'pdt' => $pending['pdt'],
                'discontinuity' => $pending['discontinuity'],
                'byterange' => $pending['byterange'],
                'gap' => $pending['gap'],
                'key' => $currentKey,
                'map' => $currentMap,
            ];

            $resetPending();
            continue;
        }

        $upper = strtoupper($line);

        /*
         * ملاحظة مهمة: الفحص يتم بالنقطتين دائمًا. بدونها يبتلع الوسم
         * ‎#EXT-X-MEDIA‎ الوسمَ ‎#EXT-X-MEDIA-SEQUENCE‎ فتُقرأ قائمة
         * الوسائط على أنها قائمة رئيسية.
         */
        if (str_starts_with($upper, '#EXT-X-STREAM-INF:')) {
            $result['is_master'] = true;
            $result['master_lines'][] = $line;
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-I-FRAME-STREAM-INF:')) {
            $result['is_master'] = true;
            $result['master_lines'][] = hls_rewrite_uri_attribute(
                $line,
                $baseUrl,
                'm3u8'
            );
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-MEDIA:')) {
            $result['is_master'] = true;
            $result['master_lines'][] = hls_rewrite_uri_attribute(
                $line,
                $baseUrl,
                'm3u8'
            );
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-SESSION-KEY:')) {
            $result['master_lines'][] = hls_rewrite_uri_attribute(
                $line,
                $baseUrl,
                'key'
            );
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-SESSION-DATA:')) {
            $result['master_lines'][] = $line;
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-VERSION:')) {
            $result['version'] = max(3, (int) substr($line, 15));
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-TARGETDURATION:')) {
            $result['target_duration'] = (float) substr($line, 22);
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-MEDIA-SEQUENCE:')) {
            $result['media_sequence'] = (int) substr($line, 22);
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-DISCONTINUITY-SEQUENCE:')) {
            $result['discontinuity_sequence'] = (int) substr($line, 30);
            continue;
        }

        if ($upper === '#EXT-X-INDEPENDENT-SEGMENTS') {
            $result['independent_segments'] = true;
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-PLAYLIST-TYPE:')) {
            $result['playlist_type'] = strtoupper(trim(substr($line, 21)));
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-PART-INF')) {
            if (preg_match('/PART-TARGET=([0-9.]+)/i', $line, $matches) === 1) {
                $result['part_target'] = (float) $matches[1];
            }
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-SERVER-CONTROL')) {
            if (preg_match('/HOLD-BACK=([0-9.]+)/i', $line, $matches) === 1) {
                $result['hold_back'] = (float) $matches[1];
            }
            if (
                preg_match('/PART-HOLD-BACK=([0-9.]+)/i', $line, $matches) === 1
            ) {
                $result['part_hold_back'] = (float) $matches[1];
            }
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-KEY:')) {
            $currentKey = hls_rewrite_uri_attribute($line, $baseUrl, 'key');
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-MAP:')) {
            $currentMap = hls_rewrite_uri_attribute($line, $baseUrl, null);
            continue;
        }

        if (str_starts_with($upper, '#EXTINF:')) {
            $value = substr($line, 8);
            $comma = strpos($value, ',');

            if ($comma === false) {
                $pending['duration'] = (float) $value;
                $pending['title'] = '';
            } else {
                $pending['duration'] = (float) substr($value, 0, $comma);
                $pending['title'] = substr($value, $comma + 1);
            }

            $pending['has_extinf'] = true;
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-BYTERANGE:')) {
            $pending['byterange'] = trim(substr($line, 17));
            continue;
        }

        if (str_starts_with($upper, '#EXT-X-PROGRAM-DATE-TIME:')) {
            $pending['pdt'] = trim(substr($line, 25));
            continue;
        }

        if ($upper === '#EXT-X-DISCONTINUITY') {
            $pending['discontinuity'] = true;
            continue;
        }

        if ($upper === '#EXT-X-GAP') {
            $pending['gap'] = true;
            continue;
        }

        if ($upper === '#EXT-X-ENDLIST') {
            $result['is_endlist'] = true;
            continue;
        }

        /*
         * الوسوم المتبقية (PART / PRELOAD-HINT / RENDITION-REPORT / SKIP)
         * تخص التسليم منخفض التأخير من المصدر مباشرة، ولا تُعاد كتابتها هنا
         * لأن البروكسي يقدّم مقاطع كاملة فقط. تُستخدم قيمها في حساب
         * سرعة التحديث فقط.
         */
    }

    return $result;
}

/**
 * يعيد كتابة قيمة URI داخل وسم HLS إلى رابط عام يخص البروكسي.
 */
function hls_rewrite_uri_attribute(
    string $line,
    string $baseUrl,
    ?string $forceExtension
): string {
    $rewritten = preg_replace_callback(
        '/URI="([^"]*)"/i',
        static function (array $matches) use ($baseUrl, $forceExtension): string {
            $value = (string) $matches[1];

            if ($value === '') {
                return 'URI=""';
            }

            $absolute = hls_resolve_url($baseUrl, $value);

            return 'URI="' . hls_public_asset_url(
                $absolute,
                $forceExtension
            ) . '"';
        },
        $line
    );

    return is_string($rewritten) ? $rewritten : $line;
}

/**
 * سرعة التحديث المطلوبة لهذه القائمة، مشتقّة من وسوم المصدر نفسها.
 */
function hls_refresh_interval_ms(array $parsed): int
{
    $minimum = (int) hls_config('playlist_min_refresh_ms');
    $maximum = (int) hls_config('playlist_max_refresh_ms');

    if ($parsed['is_master']) {
        return (int) hls_config('master_refresh_ms');
    }

    if ($parsed['is_endlist']) {
        return 30000;
    }

    /* بث منخفض التأخير: نصف مدة الجزء يعطي تحديثًا أسرع. */
    if ($parsed['part_target'] > 0) {
        $interval = (int) round($parsed['part_target'] * 1000);

        return max(200, min($maximum, $interval));
    }

    $target = (float) $parsed['target_duration'];

    if ($target <= 0) {
        $target = 6.0;
    }

    $interval = (int) round(($target * 1000) / 2);

    return max($minimum, min($maximum, $interval));
}

/**
 * أقصى عمر يُسمح بتقديم القائمة القديمة خلاله. قصير عمدًا في البث الحي.
 */
function hls_hard_stale_ms(array $parsed): int
{
    if ($parsed['is_master']) {
        return (int) hls_config('master_hard_stale_ms');
    }

    if ($parsed['is_endlist']) {
        return 600000;
    }

    $target = (float) $parsed['target_duration'];

    if ($target <= 0) {
        $target = 6.0;
    }

    return max(
        (int) hls_config('playlist_hard_stale_min_ms'),
        min(
            (int) hls_config('playlist_hard_stale_max_ms'),
            (int) round($target * 1000)
        )
    );
}

/* ═════════════════════════ النافذة الحية المحلية ═════════════════════════ */

function hls_segment_identity(array $segment): string
{
    return substr(
        hash(
            'sha256',
            $segment['uri'] . '|' . (string) ($segment['byterange'] ?? '')
        ),
        0,
        24
    );
}

function hls_empty_window(): array
{
    return [
        'segments' => [],
        'seen' => [],
        'next_seq' => 0,
        'dseq' => 0,
        'upstream_seq' => -1,
        'pending_discontinuity' => false,
    ];
}

/**
 * يدمج قائمة المصدر داخل النافذة المحلية:
 *   • لا يضيف مقطعًا سبق عرضه (حتى لو أعاده المصدر).
 *   • أرقام التسلسل المحلية تتصاعد دائمًا ولا تعود للخلف.
 *   • يكتشف إعادة ضبط المصدر ويضع ‎#EXT-X-DISCONTINUITY‎ بدل إعادة القديم.
 */
function hls_window_merge(array $window, array $parsed): array
{
    $windowSize = (int) hls_config('live_window_segments');
    $memory = (int) hls_config('live_window_memory');

    $segments = is_array($window['segments'] ?? null)
        ? $window['segments']
        : [];
    $seen = is_array($window['seen'] ?? null) ? $window['seen'] : [];
    $nextSeq = (int) ($window['next_seq'] ?? 0);
    $discontinuitySequence = (int) ($window['dseq'] ?? 0);
    $previousUpstreamSeq = (int) ($window['upstream_seq'] ?? -1);
    $pendingDiscontinuity = (bool) ($window['pending_discontinuity'] ?? false);

    $upstreamSequence = (int) $parsed['media_sequence'];
    $incoming = $parsed['segments'];

    if ($incoming === []) {
        $window['upstream_seq'] = $upstreamSequence;
        return $window;
    }

    /* اكتشاف إعادة ضبط المصدر أو تبديل مصدر القناة. */
    if ($previousUpstreamSeq >= 0) {
        $known = 0;

        foreach ($incoming as $segment) {
            if (isset($seen[hls_segment_identity($segment)])) {
                $known++;
            }
        }

        $sequenceWentBack = $upstreamSequence < $previousUpstreamSeq;
        $sequenceJumped = $upstreamSequence
            > $previousUpstreamSeq + count($incoming) + $windowSize;

        if (($sequenceWentBack || $sequenceJumped) && $known === 0) {
            /*
             * محتوى جديد كليًا مع تسلسل غير متصل: نُفرغ الذاكرة حتى يُقبل
             * المحتوى الجديد، ونضع علامة انقطاع على أول مقطع قادم.
             */
            $seen = [];
            $pendingDiscontinuity = true;
        } elseif ($sequenceWentBack && $known > 0) {
            /*
             * المصدر رجع بتسلسله لكن المحتوى نفسه معروف: لا نضيف شيئًا
             * قديمًا، ونكتفي بتجاهله (منع إعادة تشغيل مقاطع سابقة).
             */
            $pendingDiscontinuity = $pendingDiscontinuity || false;
        }
    }

    $added = 0;

    foreach ($incoming as $segment) {
        $identity = hls_segment_identity($segment);

        if (isset($seen[$identity])) {
            continue;
        }

        $extension = hls_detect_extension($segment['uri']);
        $name = hls_asset_name($segment['uri']);
        hls_asset_map_store($name, $segment['uri']);

        $segments[] = [
            'id' => $identity,
            'seq' => $nextSeq,
            'name' => $name . '.' . $extension,
            'dur' => round((float) $segment['duration'], 3),
            'title' => (string) $segment['title'],
            'pdt' => $segment['pdt'],
            'disc' => ((bool) $segment['discontinuity'])
                || $pendingDiscontinuity,
            'key' => $segment['key'],
            'map' => $segment['map'],
            'br' => $segment['byterange'],
            'gap' => (bool) $segment['gap'],
            'added' => time(),
        ];

        $seen[$identity] = $nextSeq;
        $nextSeq++;
        $added++;
        $pendingDiscontinuity = false;
    }

    /* تقليم النافذة مع تحديث DISCONTINUITY-SEQUENCE بصورة صحيحة. */
    while (count($segments) > $windowSize) {
        $removed = array_shift($segments);

        if (!empty($removed['disc'])) {
            $discontinuitySequence++;
        }
    }

    if (count($seen) > $memory) {
        $seen = array_slice($seen, -$memory, null, true);
    }

    return [
        'segments' => array_values($segments),
        'seen' => $seen,
        'next_seq' => $nextSeq,
        'dseq' => $discontinuitySequence,
        'upstream_seq' => $upstreamSequence,
        'pending_discontinuity' => $pendingDiscontinuity,
        'added_last' => $added,
        'updated' => time(),
    ];
}

/**
 * يبني نص القائمة المحلية من النافذة. لا ENDLIST في البث الحي.
 */
function hls_window_render(array $window, array $parsed): string
{
    $segments = $window['segments'] ?? [];

    if ($segments === []) {
        throw new RuntimeException('Live window is empty');
    }

    $target = (float) $parsed['target_duration'];

    foreach ($segments as $segment) {
        $target = max($target, (float) $segment['dur']);
    }

    if ($target <= 0) {
        $target = 6.0;
    }

    $version = max(3, (int) $parsed['version']);

    foreach ($segments as $segment) {
        if (!empty($segment['br']) && $version < 4) {
            $version = 4;
        }

        if (!empty($segment['map']) && $version < 6) {
            $version = 6;
        }
    }

    $lines = [
        '#EXTM3U',
        '#EXT-X-VERSION:' . $version,
        '#EXT-X-TARGETDURATION:' . (int) ceil($target - 0.001),
        '#EXT-X-MEDIA-SEQUENCE:' . (int) $segments[0]['seq'],
    ];

    if ((int) ($window['dseq'] ?? 0) > 0) {
        $lines[] = '#EXT-X-DISCONTINUITY-SEQUENCE:' . (int) $window['dseq'];
    }

    if (!empty($parsed['independent_segments'])) {
        $lines[] = '#EXT-X-INDEPENDENT-SEGMENTS';
    }

    $activeKey = null;
    $activeMap = null;

    foreach ($segments as $segment) {
        $key = $segment['key'] ?? null;

        if ($key !== $activeKey) {
            if ($key !== null) {
                $lines[] = $key;
            } elseif ($activeKey !== null) {
                $lines[] = '#EXT-X-KEY:METHOD=NONE';
            }

            $activeKey = $key;
        }

        $map = $segment['map'] ?? null;

        if ($map !== null && $map !== $activeMap) {
            $lines[] = $map;
            $activeMap = $map;
        }

        if (!empty($segment['disc'])) {
            $lines[] = '#EXT-X-DISCONTINUITY';
        }

        if (!empty($segment['pdt'])) {
            $lines[] = '#EXT-X-PROGRAM-DATE-TIME:' . $segment['pdt'];
        }

        if (!empty($segment['gap'])) {
            $lines[] = '#EXT-X-GAP';
        }

        if (!empty($segment['br'])) {
            $lines[] = '#EXT-X-BYTERANGE:' . $segment['br'];
        }

        $lines[] = '#EXTINF:'
            . number_format((float) $segment['dur'], 3, '.', '')
            . ',' . (string) ($segment['title'] ?? '');

        $lines[] = hls_config('public_base_url')
            . '/hls-cache/' . $segment['name'];
    }

    return implode("\n", $lines) . "\n";
}

/**
 * قائمة VOD (تحتوي ENDLIST): تُمرَّر كما هي بعد إعادة كتابة الروابط فقط.
 */
function hls_render_static_playlist(array $parsed): string
{
    if ($parsed['is_master']) {
        $lines = ['#EXTM3U', '#EXT-X-VERSION:' . max(3, (int) $parsed['version'])];

        if (!empty($parsed['independent_segments'])) {
            $lines[] = '#EXT-X-INDEPENDENT-SEGMENTS';
        }

        return implode("\n", array_merge($lines, $parsed['master_lines'])) . "\n";
    }

    $target = (float) $parsed['target_duration'];

    foreach ($parsed['segments'] as $segment) {
        $target = max($target, (float) $segment['duration']);
    }

    if ($target <= 0) {
        $target = 6.0;
    }

    $lines = [
        '#EXTM3U',
        '#EXT-X-VERSION:' . max(3, (int) $parsed['version']),
        '#EXT-X-TARGETDURATION:' . (int) ceil($target - 0.001),
        '#EXT-X-MEDIA-SEQUENCE:' . (int) $parsed['media_sequence'],
    ];

    if ((int) $parsed['discontinuity_sequence'] > 0) {
        $lines[] = '#EXT-X-DISCONTINUITY-SEQUENCE:'
            . (int) $parsed['discontinuity_sequence'];
    }

    if (!empty($parsed['independent_segments'])) {
        $lines[] = '#EXT-X-INDEPENDENT-SEGMENTS';
    }

    if ($parsed['playlist_type'] !== null) {
        $lines[] = '#EXT-X-PLAYLIST-TYPE:' . $parsed['playlist_type'];
    }

    $activeKey = null;
    $activeMap = null;

    foreach ($parsed['segments'] as $segment) {
        $key = $segment['key'] ?? null;

        if ($key !== $activeKey) {
            if ($key !== null) {
                $lines[] = $key;
            } elseif ($activeKey !== null) {
                $lines[] = '#EXT-X-KEY:METHOD=NONE';
            }

            $activeKey = $key;
        }

        $map = $segment['map'] ?? null;

        if ($map !== null && $map !== $activeMap) {
            $lines[] = $map;
            $activeMap = $map;
        }

        if (!empty($segment['discontinuity'])) {
            $lines[] = '#EXT-X-DISCONTINUITY';
        }

        if (!empty($segment['pdt'])) {
            $lines[] = '#EXT-X-PROGRAM-DATE-TIME:' . $segment['pdt'];
        }

        if (!empty($segment['gap'])) {
            $lines[] = '#EXT-X-GAP';
        }

        if (!empty($segment['byterange'])) {
            $lines[] = '#EXT-X-BYTERANGE:' . $segment['byterange'];
        }

        $lines[] = '#EXTINF:'
            . number_format((float) $segment['duration'], 3, '.', '')
            . ',' . (string) $segment['title'];

        $lines[] = hls_public_asset_url($segment['uri']);
    }

    if ($parsed['is_endlist']) {
        $lines[] = '#EXT-X-ENDLIST';
    }

    return implode("\n", $lines) . "\n";
}

/* ═════════════════════════ كاش القوائم ═════════════════════════ */

function hls_playlist_key(string $sourceUrl): string
{
    return substr(hash('sha256', 'pl|' . $sourceUrl), 0, 40);
}

function hls_playlist_paths(string $key): array
{
    $directory = hls_config('cache_root') . '/pl/' . substr($key, 0, 2);

    return [
        'dir' => $directory,
        'body' => $directory . '/' . $key . '.m3u8',
        'meta' => $directory . '/' . $key . '.meta',
        'window' => $directory . '/' . $key . '.win',
        'lock' => $directory . '/' . $key . '.lock',
    ];
}

/**
 * يقرأ الميتاداتا أولًا ثم الجسم، فلا يمكن أن يُحسب جسم قديم على أنه حديث.
 */
function hls_playlist_read(array $paths): ?array
{
    clearstatcache(true, $paths['meta']);

    if (!is_file($paths['meta'])) {
        return null;
    }

    $rawMeta = @file_get_contents($paths['meta']);

    if (!is_string($rawMeta) || $rawMeta === '') {
        return null;
    }

    $meta = json_decode($rawMeta, true);

    if (!is_array($meta) || !isset($meta['fetched_at'])) {
        return null;
    }

    $body = @file_get_contents($paths['body']);

    if (!is_string($body) || strpos(ltrim($body), '#EXTM3U') !== 0) {
        return null;
    }

    return [
        'body' => $body,
        'meta' => $meta,
        'age_ms' => max(0, hls_now_ms() - (int) $meta['fetched_at']),
    ];
}

/**
 * يعيد حساب عمر النسخة لحظة الإرسال. مهم جدًا: قد تمر ثوانٍ بين قراءة
 * الكاش ومحاولة الجلب الفاشلة، فلا يجوز الاعتماد على عمر قديم ثم تقديم
 * قائمة منتهية على أنها صالحة.
 */
function hls_entry_servable(?array $entry): ?array
{
    if ($entry === null) {
        return null;
    }

    $age = max(0, hls_now_ms() - (int) $entry['meta']['fetched_at']);

    if ($age > (int) $entry['meta']['hard_stale_ms']) {
        return null;
    }

    $entry['age_ms'] = $age;

    return $entry;
}

function hls_window_read(array $paths): array
{
    if (!is_file($paths['window'])) {
        return hls_empty_window();
    }

    $raw = @file_get_contents($paths['window']);

    if (!is_string($raw) || $raw === '') {
        return hls_empty_window();
    }

    $window = json_decode($raw, true);

    if (!is_array($window) || !isset($window['segments'])) {
        return hls_empty_window();
    }

    return $window + hls_empty_window();
}

/**
 * جلب من المصدر + بناء القائمة المحلية + نشرها ذرّيًا. تُستدعى فقط من
 * العملية التي تملك القفل، فلا يوجد أكثر من اتصال واحد لكل قائمة.
 */
function hls_playlist_refresh(string $sourceUrl, array $paths): array
{
    $response = hls_fetch_playlist_body($sourceUrl);
    $parsed = hls_parse_playlist(
        $response['body'],
        $response['effective_url'] !== ''
            ? $response['effective_url']
            : $sourceUrl
    );

    if ($parsed['is_master']) {
        $body = hls_render_static_playlist($parsed);
        $kind = 'master';
    } elseif ($parsed['is_endlist']) {
        $body = hls_render_static_playlist($parsed);
        $kind = 'vod';
    } else {
        $window = hls_window_merge(hls_window_read($paths), $parsed);
        $body = hls_window_render($window, $parsed);
        $kind = 'live';

        hls_atomic_write(
            $paths['window'],
            (string) json_encode($window, JSON_UNESCAPED_SLASHES),
            0600
        );
    }

    $meta = [
        'fetched_at' => hls_now_ms(),
        'refresh_ms' => hls_refresh_interval_ms($parsed),
        'hard_stale_ms' => hls_hard_stale_ms($parsed),
        'kind' => $kind,
        'target' => (float) $parsed['target_duration'],
        'upstream_seq' => (int) $parsed['media_sequence'],
        'segments' => count($parsed['segments']),
    ];

    hls_atomic_write($paths['body'], $body, 0600);
    hls_atomic_write(
        $paths['meta'],
        (string) json_encode($meta, JSON_UNESCAPED_SLASHES),
        0600
    );

    return [
        'body' => $body,
        'meta' => $meta,
        'age_ms' => 0,
    ];
}

/**
 * ══════════════════════════════════════════════════════════════════════
 *  نقطة الدخول لأي قائمة m3u8 — Single Flight + Stale-While-Revalidate
 * ──────────────────────────────────────────────────────────────────────
 *  • كاش حديث              → إرسال فوري بلا قفل وبلا اتصال بالمصدر.
 *  • كاش قديم وأحدهم يحدّث → إرسال آخر نسخة صالحة فورًا (بدون انتظار).
 *  • لا يوجد كاش           → عملية واحدة تجلب، والبقية تنتظر 400ms كحد
 *                            أقصى ثم تقرأ الملف المنشور.
 *  لا يوجد أي flock حاجز أمام جمهور المشاهدين.
 * ══════════════════════════════════════════════════════════════════════
 */
function hls_playlist_get(string $sourceUrl): array
{
    $key = hls_playlist_key($sourceUrl);
    $paths = hls_playlist_paths($key);
    hls_ensure_directory($paths['dir'], 0700);

    $entry = hls_playlist_read($paths);

    if ($entry !== null && $entry['age_ms'] <= (int) $entry['meta']['refresh_ms']) {
        hls_metric_inc('playlist_cache_hits');
        return $entry;
    }

    hls_metric_inc('playlist_cache_misses');

    $lock = @fopen($paths['lock'], 'c');

    if ($lock === false) {
        $stale = hls_entry_servable($entry);

        if ($stale !== null) {
            hls_metric_inc('stale_playlist_served');
            return $stale;
        }

        throw new RuntimeException('Unable to open playlist lock');
    }

    try {
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            $latest = null;

            try {
                /* ربما نشر غيرنا نسخة حديثة بين القراءة والقفل. */
                $latest = hls_playlist_read($paths);

                if (
                    $latest !== null
                    && $latest['age_ms'] <= (int) $latest['meta']['refresh_ms']
                ) {
                    hls_metric_inc('playlist_cache_hits');
                    return $latest;
                }

                return hls_playlist_refresh($sourceUrl, $paths);
            } catch (Throwable $error) {
                /*
                 * العمر يُعاد حسابه الآن، لا وقت القراءة الأولى: محاولة
                 * الجلب الفاشلة قد تكون استغرقت ثوانٍ.
                 */
                $fallback = hls_entry_servable($latest ?? $entry);

                if ($fallback !== null) {
                    hls_metric_inc('stale_playlist_served');
                    hls_log('serving cached playlist: ' . $error->getMessage());

                    return $fallback;
                }

                hls_metric_inc('playlist_unavailable');
                throw $error;
            } finally {
                @flock($lock, LOCK_UN);
            }
        }

        /* عملية أخرى تحدّث الآن. */
        hls_metric_inc('lock_contention');

        $stale = hls_entry_servable($entry);

        if ($stale !== null) {
            hls_metric_inc('stale_playlist_served');
            return $stale;
        }

        /* لا يوجد كاش صالح: انتظار قصير جدًا للنسخة التي تُبنى الآن. */
        $deadline = hls_now_ms() + (int) hls_config('playlist_cold_wait_ms');

        while (hls_now_ms() < $deadline) {
            usleep(20000);

            $published = hls_entry_servable(hls_playlist_read($paths));

            if ($published !== null) {
                hls_metric_inc('playlist_wait_resolved');
                return $published;
            }
        }

        /*
         * انتهى الانتظار: قد تكون العملية المالكة للقفل ماتت. نحاول مرة
         * واحدة أن نصبح نحن الجالب حتى لا تتوقف القناة نهائيًا.
         */
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            try {
                $published = hls_entry_servable(hls_playlist_read($paths));

                if ($published !== null) {
                    return $published;
                }

                return hls_playlist_refresh($sourceUrl, $paths);
            } finally {
                @flock($lock, LOCK_UN);
            }
        }

        hls_metric_inc('playlist_unavailable');
        throw new RuntimeException('Playlist temporarily unavailable');
    } finally {
        @fclose($lock);
    }
}

function hls_channel_source_url(int $channelId): string
{
    return rtrim((string) hls_config('source_base_url'), '/')
        . '/' . $channelId . '.m3u8';
}

/* ═════════════════════════ كاش المقاطع ═════════════════════════ */

function hls_segment_path(string $name, string $extension): string
{
    return hls_config('segment_cache_root') . '/' . $name . '.' . $extension;
}

/**
 * يجلب المقطع مرة واحدة فقط وينشره باسم ثابت يخدمه الخادم مباشرة لاحقًا.
 *
 * القيمة المعادة:
 *   ['state' => 'exists']              الملف صار موجودًا (وجّه إليه)
 *   ['state' => 'fetched', 'body'=>..] نحن من جلبه الآن (أول طلب فقط)
 *   ['state' => 'busy']                غيرنا يجلبه ولم يصل بعد
 */
function hls_segment_acquire(string $name, string $extension, string $sourceUrl): array
{
    $path = hls_segment_path($name, $extension);
    hls_ensure_directory(dirname($path));

    clearstatcache(true, $path);

    if (is_file($path) && filesize($path) > 0) {
        hls_metric_inc('segment_cache_hits');
        return ['state' => 'exists'];
    }

    $lockPath = hls_config('cache_root') . '/seg/'
        . substr($name, 0, 2) . '/' . $name . '.lock';
    hls_ensure_directory(dirname($lockPath), 0700);

    $lock = @fopen($lockPath, 'c');

    if ($lock === false) {
        throw new RuntimeException('Unable to open segment lock');
    }

    try {
        $deadline = hls_now_ms() + (int) hls_config('segment_wait_ms');

        while (true) {
            if (flock($lock, LOCK_EX | LOCK_NB)) {
                try {
                    clearstatcache(true, $path);

                    if (is_file($path) && filesize($path) > 0) {
                        hls_metric_inc('segment_cache_hits');
                        return ['state' => 'exists'];
                    }

                    hls_metric_inc('segment_first_fetch');
                    $response = hls_fetch_segment_body($sourceUrl, $extension);
                    hls_atomic_write($path, $response['body'], 0644);

                    return [
                        'state' => 'fetched',
                        'body' => $response['body'],
                        'content_type' => $response['content_type'],
                    ];
                } finally {
                    @flock($lock, LOCK_UN);
                }
            }

            hls_metric_inc('lock_contention');

            if (hls_now_ms() >= $deadline) {
                hls_metric_inc('segment_wait_timeout');
                return ['state' => 'busy'];
            }

            usleep(25000);

            clearstatcache(true, $path);

            if (is_file($path) && filesize($path) > 0) {
                hls_metric_inc('segment_cache_hits');
                return ['state' => 'exists'];
            }
        }
    } finally {
        @fclose($lock);
    }
}

/**
 * مفاتيح التشفير لا تُحفظ كملفات ثابتة: المصدر قد يعيد استخدام الرابط نفسه
 * بمحتوى جديد، فنحتفظ بها في كاش قصير خارج المسار العام.
 */
function hls_key_get(string $name, string $sourceUrl): string
{
    $path = hls_config('cache_root') . '/key/'
        . substr($name, 0, 2) . '/' . $name . '.bin';

    if (is_file($path) && (time() - (int) filemtime($path)) < 30) {
        $cached = @file_get_contents($path);

        if (is_string($cached) && $cached !== '') {
            hls_metric_inc('segment_cache_hits');
            return $cached;
        }
    }

    hls_ensure_directory(dirname($path), 0700);
    $lock = @fopen($path . '.lock', 'c');

    if ($lock !== false && flock($lock, LOCK_EX | LOCK_NB)) {
        try {
            hls_metric_inc('segment_first_fetch');
            $response = hls_fetch_segment_body($sourceUrl, 'key');
            hls_atomic_write($path, $response['body'], 0600);

            return $response['body'];
        } catch (Throwable $error) {
            if (is_file($path)) {
                $cached = @file_get_contents($path);

                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            }

            throw $error;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    if ($lock !== false) {
        @fclose($lock);
    }

    if (is_file($path)) {
        $cached = @file_get_contents($path);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
    }

    throw new RuntimeException('Key temporarily unavailable');
}

/* ═════════════════════════ التنظيف الدوري ═════════════════════════ */

/**
 * تنظيف محدود بميزانية زمنية وعدد ملفات، فلا يمسح آلاف الملفات داخل طلب بث.
 */
function hls_cleanup(bool $force = false): int
{
    $cacheRoot = hls_config('cache_root');
    $marker = $cacheRoot . '/cleanup.marker';

    try {
        hls_ensure_directory($cacheRoot, 0700);
    } catch (Throwable $error) {
        return 0;
    }

    if (!$force) {
        clearstatcache(true, $marker);

        if (
            is_file($marker)
            && (time() - (int) filemtime($marker))
                < (int) hls_config('cleanup_interval_seconds')
        ) {
            return 0;
        }
    }

    @touch($marker);

    $now = time();
    $keep = (int) hls_config('segment_keep_seconds');
    $budget = (int) hls_config('cleanup_max_files');
    $removed = 0;
    $segmentRoot = hls_config('segment_cache_root');

    if (is_dir($segmentRoot)) {
        $handle = @opendir($segmentRoot);

        if ($handle !== false) {
            while ($budget-- > 0 && ($item = readdir($handle)) !== false) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $file = $segmentRoot . '/' . $item;

                if (!is_file($file)) {
                    continue;
                }

                $age = $now - (int) @filemtime($file);

                if ($age > $keep) {
                    if (@unlink($file)) {
                        $removed++;
                    }
                }
            }

            closedir($handle);
        }
    }

    /* خرائط الأسماء والأقفال تُنظّف بعمر أطول قليلًا. */
    foreach (['map', 'seg', 'key'] as $folder) {
        $root = $cacheRoot . '/' . $folder;

        if (!is_dir($root)) {
            continue;
        }

        foreach ((array) @scandir($root) as $bucket) {
            if ($bucket === '.' || $bucket === '..' || $bucket === false) {
                continue;
            }

            $bucketPath = $root . '/' . $bucket;

            if (!is_dir($bucketPath)) {
                continue;
            }

            foreach ((array) @scandir($bucketPath) as $item) {
                if ($item === '.' || $item === '..' || $item === false) {
                    continue;
                }

                $file = $bucketPath . '/' . $item;

                if (
                    is_file($file)
                    && ($now - (int) @filemtime($file)) > ($keep * 3)
                ) {
                    if (@unlink($file)) {
                        $removed++;
                    }
                }
            }
        }
    }

    return $removed;
}
