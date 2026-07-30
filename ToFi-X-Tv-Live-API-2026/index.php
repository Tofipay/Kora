<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  ToFi X Tv — HLS Live Proxy (2026)
 * ───────────────────────────────────────────────────────────────────────────
 *  المسارات العامة (لم تتغيّر، ولا يحتاج التطبيق لأي تعديل):
 *
 *      GET /watch/{channels}/index.m3u8        (يتطلب User-Agent: MTX Player)
 *      GET /api/token/{channels}
 *      GET /stream/{channels}/index.m3u8?expires&viewer&root&token
 *      GET /api/viewer/leave/{channels}
 *      GET /api/viewer/ping/{channels}          ← جديد (اختياري)
 *      GET /hls-cache/{name}.{ext}
 *      GET /api/metrics?key=...                 ← جديد (اختياري)
 *
 *  المنطق الثقيل كله داخل hls-core.php. هذا الملف توجيه وتحقق وإخراج فقط.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/hls-core.php';

/*
 * ثوابت التوافق الرجعي: كانت معرّفة في النسخة السابقة داخل هذا الملف،
 * وتعتمد عليها override.php و viewers.php. القيم تأتي الآن من الإعدادات.
 */
define('SOURCE_BASE_URL', (string) hls_config('source_base_url'));
define('PUBLIC_BASE_URL', (string) hls_config('public_base_url'));
define('TOKEN_SECRET', (string) hls_config('token_secret'));
define('STREAM_SIGNING_SECRET', (string) hls_config('stream_signing_secret'));
define('APP_ALLOWED_USER_AGENT', (string) hls_config('app_user_agent'));
define('STREAM_TOKEN_TTL', (int) hls_config('stream_token_ttl'));
define('TOKEN_CLOCK_SKEW', (int) hls_config('token_clock_skew'));
define('CACHE_ROOT', (string) hls_config('cache_root'));
define('SEGMENT_CACHE_ROOT', (string) hls_config('segment_cache_root'));

$viewerLibrary = __DIR__ . '/viewers.php';

if (is_file($viewerLibrary)) {
    require_once $viewerLibrary;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header(
    'Access-Control-Allow-Headers: '
    . 'Range, Origin, User-Agent, Accept, Content-Type'
);
header(
    'Access-Control-Expose-Headers: '
    . 'Content-Length, Content-Range, Accept-Ranges'
);
header('X-Content-Type-Options: nosniff');

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($requestMethod === 'OPTIONS') {
    header('Access-Control-Max-Age: 86400');
    header('Cache-Control: public, max-age=86400');
    http_response_code(204);
    exit;
}

if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD, OPTIONS');
    failResponse('Method not allowed', 405);
}

try {
    $action = (string) ($_GET['action'] ?? 'playlist');

    switch ($action) {
        case 'issue_token':
        case 'redirect_token':
            handleTokenRequest($action);
            break;

        case 'viewer_leave':
            handleViewerLeave();
            break;

        case 'viewer_ping':
            handleViewerPing();
            break;

        case 'asset':
            handleAssetRequest(
                (string) ($_GET['name'] ?? $_GET['token'] ?? ''),
                strtolower((string) ($_GET['ext'] ?? 'ts'))
            );
            break;

        case 'metrics':
            handleMetricsRequest();
            break;

        default:
            handlePlaylistRequest();
    }
} catch (Throwable $error) {
    hls_log($error->getMessage());
    failResponse('Stream temporarily unavailable', 502);
}

exit;

/* ═════════════════════════ التوكنات ═════════════════════════ */

function handleTokenRequest(string $action): void
{
    requireAppUserAgent();

    $requestedChannels = validateChannelsValue(
        (string) ($_GET['channels'] ?? '')
    );

    $viewerId = function_exists('viewer_new_id')
        ? viewer_new_id()
        : bin2hex(random_bytes(16));

    $rootChannel = firstChannelId($requestedChannels);
    $expires = time() + STREAM_TOKEN_TTL;

    $signedUrl = buildSignedStreamUrl(
        $requestedChannels,
        $expires,
        $viewerId,
        $rootChannel
    );

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if ($action === 'redirect_token') {
        header('Location: ' . $signedUrl, true, 302);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    if (isHeadRequest()) {
        exit;
    }

    /*
     * شكل الاستجابة مطابق للنسخة السابقة حرفًا بحرف حتى لا يتأثر التطبيق.
     */
    echo json_encode([
        'success' => true,
        'channels' => $requestedChannels,
        'url' => $signedUrl,
        'viewer_id' => $viewerId,
        'leave_url' => buildViewerActionUrl(
            'leave',
            $requestedChannels,
            $expires,
            $viewerId,
            $rootChannel
        ),
        'ping_url' => buildViewerActionUrl(
            'ping',
            $requestedChannels,
            $expires,
            $viewerId,
            $rootChannel
        ),
        'expires_at' => $expires,
        'expires_in' => STREAM_TOKEN_TTL,
    ], JSON_UNESCAPED_SLASHES);

    exit;
}

function requireAppUserAgent(): void
{
    $providedUserAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if (
        $providedUserAgent === ''
        || !hash_equals(APP_ALLOWED_USER_AGENT, $providedUserAgent)
    ) {
        failResponse('Unauthorized', 401);
    }
}

function validateChannelsValue(string $channelsValue): string
{
    $channelsValue = trim($channelsValue);

    if (
        $channelsValue === ''
        || preg_match('/^[0-9]+(?:-[0-9]+){0,2}$/', $channelsValue) !== 1
    ) {
        failResponse('Invalid channel IDs', 400);
    }

    return $channelsValue;
}

function firstChannelId(string $channelsValue): int
{
    $parts = explode('-', $channelsValue);

    return (int) ($parts[0] ?? 0);
}

function validateViewerValue(string $viewerId, bool $required): string
{
    $viewerId = strtolower(trim($viewerId));

    if ($viewerId === '' && !$required) {
        return '';
    }

    $valid = function_exists('viewer_valid_id')
        ? viewer_valid_id($viewerId)
        : preg_match('/^[a-f0-9]{32}$/', $viewerId) === 1;

    if (!$valid) {
        failResponse('Invalid viewer session', 403);
    }

    return $viewerId;
}

function validateRootChannel(string $channelsValue, string $rootValue): int
{
    if ($rootValue === '') {
        return firstChannelId($channelsValue);
    }

    if (preg_match('/^[1-9][0-9]{0,5}$/', $rootValue) !== 1) {
        failResponse('Invalid root channel', 403);
    }

    return (int) $rootValue;
}

function createStreamToken(
    string $channelsValue,
    int $expires,
    string $viewerId = '',
    int $rootChannel = 0
): string {
    $payload = $channelsValue . '|' . $expires;

    if ($viewerId !== '') {
        $payload .= '|' . $viewerId . '|' . $rootChannel;
    }

    return hash_hmac('sha256', $payload, STREAM_SIGNING_SECRET);
}

function buildSignedStreamUrl(
    string $channelsValue,
    int $expires,
    string $viewerId,
    int $rootChannel
): string {
    return PUBLIC_BASE_URL
        . '/stream/' . $channelsValue
        . '/index.m3u8?expires=' . $expires
        . '&viewer=' . rawurlencode($viewerId)
        . '&root=' . $rootChannel
        . '&token=' . createStreamToken(
            $channelsValue,
            $expires,
            $viewerId,
            $rootChannel
        );
}

function buildViewerActionUrl(
    string $kind,
    string $channelsValue,
    int $expires,
    string $viewerId,
    int $rootChannel
): string {
    return PUBLIC_BASE_URL
        . '/api/viewer/' . $kind . '/' . $channelsValue
        . '?expires=' . $expires
        . '&viewer=' . rawurlencode($viewerId)
        . '&root=' . $rootChannel
        . '&token=' . createStreamToken(
            $channelsValue,
            $expires,
            $viewerId,
            $rootChannel
        );
}

function validateStreamAccess(
    string $channelsValue,
    string $viewerId = '',
    int $rootChannel = 0
): int {
    $expiresValue = (string) ($_GET['expires'] ?? '');
    $providedToken = strtolower((string) ($_GET['token'] ?? ''));

    if (
        preg_match('/^[0-9]{10}$/', $expiresValue) !== 1
        || preg_match('/^[a-f0-9]{64}$/', $providedToken) !== 1
    ) {
        failResponse('Missing or invalid stream token', 403);
    }

    $expires = (int) $expiresValue;
    $now = time();

    if ($expires < $now - TOKEN_CLOCK_SKEW) {
        failResponse('Stream token expired', 403);
    }

    if ($expires > $now + STREAM_TOKEN_TTL + TOKEN_CLOCK_SKEW) {
        failResponse('Invalid stream expiration', 403);
    }

    $expectedToken = createStreamToken(
        $channelsValue,
        $expires,
        $viewerId,
        $rootChannel
    );

    if (!hash_equals($expectedToken, $providedToken)) {
        failResponse('Invalid stream token', 403);
    }

    return $expires;
}

/* ═════════════════════════ إحصاء المتصلين ═════════════════════════ */

function touchViewerSafely(string $viewerId, int $rootChannel): void
{
    if ($viewerId === '' || !function_exists('viewer_touch')) {
        return;
    }

    try {
        viewer_touch($viewerId, $rootChannel);
    } catch (Throwable $viewerError) {
        /* الإحصاء وظيفة منفصلة: فشلها لا يوقف الفيديو إطلاقًا. */
        error_log('[ToFi Viewers] ' . $viewerError->getMessage());
    }
}

function handleViewerLeave(): void
{
    [$channelsValue, $viewerId, $rootChannel] = validateViewerRequest();

    try {
        if (function_exists('viewer_forget')) {
            viewer_forget($viewerId, $rootChannel);
        }
    } catch (Throwable $viewerError) {
        error_log('[ToFi Viewers] ' . $viewerError->getMessage());
    }

    sendJson(['success' => true]);
}

function handleViewerPing(): void
{
    [$channelsValue, $viewerId, $rootChannel] = validateViewerRequest();

    touchViewerSafely($viewerId, $rootChannel);

    sendJson([
        'success' => true,
        'channels' => $channelsValue,
        'viewer_id' => $viewerId,
        'active' => true,
        'lease_seconds' => (int) hls_config('viewer_active_seconds'),
        'next_ping_in' => max(
            5,
            (int) hls_config('viewer_active_seconds') - 15
        ),
    ]);
}

function validateViewerRequest(): array
{
    $channelsValue = validateChannelsValue((string) ($_GET['channels'] ?? ''));
    $viewerId = validateViewerValue((string) ($_GET['viewer'] ?? ''), true);
    $rootChannel = validateRootChannel(
        $channelsValue,
        (string) ($_GET['root'] ?? '')
    );

    validateStreamAccess($channelsValue, $viewerId, $rootChannel);

    return [$channelsValue, $viewerId, $rootChannel];
}

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (!isHeadRequest()) {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    exit;
}

function handleMetricsRequest(): void
{
    $configured = (string) hls_config('metrics_token');
    $provided = (string) ($_GET['key'] ?? '');

    if (
        !hls_config('metrics_enabled')
        || $configured === ''
        || !hash_equals($configured, $provided)
    ) {
        failResponse('Not found', 404);
    }

    $payload = ['success' => true, 'mode' => (string) hls_config('mode')];

    if (function_exists('viewer_stats')) {
        try {
            $stats = viewer_stats(null);
            $payload['active_viewers'] = (int) $stats['total'];
            $payload['viewer_backend'] = (string) $stats['backend'];
        } catch (Throwable $error) {
            $payload['active_viewers'] = -1;
        }
    }

    if (isset($_GET['reset'])) {
        $payload['metrics'] = hls_metric_snapshot();
        hls_metric_reset();
    } else {
        $payload['metrics'] = hls_metric_snapshot();
    }

    sendJson($payload);
}

/* ═════════════════════════ القوائم ═════════════════════════ */

function handlePlaylistRequest(): void
{
    $channelsValue = validateChannelsValue((string) ($_GET['channels'] ?? ''));
    $viewerId = validateViewerValue((string) ($_GET['viewer'] ?? ''), false);
    $rootChannel = validateRootChannel(
        $channelsValue,
        (string) ($_GET['root'] ?? '')
    );
    $expires = validateStreamAccess($channelsValue, $viewerId, $rootChannel);

    $channelIds = array_values(array_unique(
        array_map('intval', explode('-', $channelsValue))
    ));

    if ($channelIds === [] || count($channelIds) > 3) {
        failResponse('Maximum 3 qualities allowed', 400);
    }

    foreach ($channelIds as $channelId) {
        if ($channelId < 1) {
            failResponse('Invalid channel ID', 400);
        }
    }

    /*
     * تجديد الجلسة يحدث هنا فقط — على قائمة التشغيل التي يطلبها المشغل،
     * وليس على أي مقطع فيديو. الكتابة نفسها محدودة بمرة كل عدة ثوانٍ.
     */
    touchViewerSafely($viewerId, $rootChannel);

    /*
     * تبديل القناة (اختياري): إن كان لأي قناة مطلوبة تبديل مفعّل نعرض
     * القالب وننهي. عند غياب الملف أو أي خطأ نستمر بالبث الطبيعي.
     */
    try {
        $overrideLibrary = __DIR__ . '/override.php';

        if (is_file($overrideLibrary)) {
            require_once $overrideLibrary;

            if (function_exists('override_maybe_serve')) {
                foreach ($channelIds as $requestedChannel) {
                    override_maybe_serve($requestedChannel);
                }
            }
        }
    } catch (Throwable $overrideError) {
        error_log('[ToFi Override] ' . $overrideError->getMessage());
    }

    if (count($channelIds) > 1) {
        outputMasterPlaylist($channelIds, $expires, $viewerId, $rootChannel);
        return;
    }

    outputMediaPlaylist($channelIds[0], $expires, $viewerId, $rootChannel);
}

function outputMasterPlaylist(
    array $channelIds,
    int $expires,
    string $viewerId,
    int $rootChannel
): void {
    $profiles = [
        [
            'name' => '720p',
            'resolution' => '1280x720',
            'bandwidth' => 2500000,
            'average' => 1800000,
        ],
        [
            'name' => '360p',
            'resolution' => '640x360',
            'bandwidth' => 1000000,
            'average' => 750000,
        ],
        [
            'name' => '240p',
            'resolution' => '426x240',
            'bandwidth' => 600000,
            'average' => 400000,
        ],
    ];

    $playlist = ['#EXTM3U', '#EXT-X-VERSION:3'];
    $shared = (bool) hls_config('shared_media_playlist', false);

    foreach ($channelIds as $index => $channelId) {
        $profile = $profiles[$index] ?? $profiles[count($profiles) - 1];

        $playlist[] = '#EXT-X-STREAM-INF:'
            . 'BANDWIDTH=' . $profile['bandwidth']
            . ',AVERAGE-BANDWIDTH=' . $profile['average']
            . ',RESOLUTION=' . $profile['resolution']
            . ',NAME="' . $profile['name'] . '"';

        if ($shared) {
            $playlist[] = hls_public_asset_url(
                hls_channel_source_url((int) $channelId),
                'm3u8'
            );
            continue;
        }

        $childChannels = (string) $channelId;

        $playlist[] = PUBLIC_BASE_URL
            . '/stream/' . $childChannels
            . '/index.m3u8?expires=' . $expires
            . '&viewer=' . rawurlencode($viewerId)
            . '&root=' . $rootChannel
            . '&token=' . createStreamToken(
                $childChannels,
                $expires,
                $viewerId,
                $rootChannel
            );
    }

    sendProtectedPlaylistHeaders();

    if (!isHeadRequest()) {
        echo implode("\n", $playlist) . "\n";
    }

    exit;
}

function outputMediaPlaylist(
    int $channelId,
    int $expires,
    string $viewerId,
    int $rootChannel
): void {
    if ((bool) hls_config('shared_media_playlist', false)) {
        /*
         * وضع اختياري لأحمال ضخمة: قائمة وسيطة تشير إلى قائمة مشتركة
         * واحدة لجميع المشاهدين، فيتقاسمها CDN. مطفأ افتراضيًا.
         */
        $playlist = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-STREAM-INF:BANDWIDTH=2500000,'
                . 'AVERAGE-BANDWIDTH=1800000,NAME="main"',
            hls_public_asset_url(
                hls_channel_source_url($channelId),
                'm3u8'
            ),
        ];

        sendProtectedPlaylistHeaders();

        if (!isHeadRequest()) {
            echo implode("\n", $playlist) . "\n";
        }

        exit;
    }

    $entry = hls_playlist_get(hls_channel_source_url($channelId));

    maybeCleanupCache();
    sendProtectedPlaylistHeaders();
    header('X-Tofi-Age: ' . (int) $entry['age_ms']);

    if (!isHeadRequest()) {
        echo $entry['body'];
    }

    exit;
}

function sendProtectedPlaylistHeaders(): void
{
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header(
        'Cache-Control: private, no-store, no-cache, '
        . 'must-revalidate, max-age=0'
    );
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * القوائم المشتركة (‎/hls-cache/xxx.m3u8‎) لا تحمل أي بيانات مشاهد، فيمكن
 * لـ CDN تخزينها لثانية واحدة ومشاركتها بين كل المشاهدين.
 */
function sendSharedPlaylistHeaders(int $ageMs): void
{
    header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
    header(
        'Cache-Control: public, max-age=1, s-maxage=1, '
        . 'stale-while-revalidate=2, stale-if-error=4'
    );
    header('X-Tofi-Age: ' . $ageMs);
}

function maybeCleanupCache(): void
{
    if ((string) hls_config('mode') === 'worker') {
        /* الـ worker يتولى التنظيف في هذا الوضع. */
        return;
    }

    try {
        hls_cleanup();
    } catch (Throwable $error) {
        /* التنظيف لا يعطّل البث. */
    }
}

/* ═════════════════════════ المقاطع والمفاتيح ═════════════════════════ */

function handleAssetRequest(string $name, string $extension): void
{
    if (preg_match('/^[a-z0-9]{2,5}$/', $extension) !== 1) {
        failResponse('Invalid asset extension', 400);
    }

    if (preg_match('/^[a-f0-9]{40}$/', $name) === 1) {
        $sourceUrl = hls_asset_map_lookup($name);

        if ($sourceUrl === null) {
            /*
             * انتهت صلاحية الخريطة (مقطع قديم جدًا) أو اسم غير معروف.
             * لا نكشف أي تفاصيل عن المصدر.
             */
            failResponse('Asset not available', 404);
        }
    } elseif (preg_match('/^[A-Za-z0-9_-]{20,4096}$/', $name) === 1) {
        /*
         * توافق رجعي: روابط النسخة السابقة كانت تحمل الرابط مشفّرًا داخل
         * الاسم. نحوّلها مرة واحدة إلى الشكل القصير الجديد.
         */
        try {
            $sourceUrl = legacyDecryptAssetToken($name);
        } catch (Throwable $error) {
            failResponse('Invalid asset token', 400);
        }

        $canonical = hls_public_asset_url($sourceUrl, $extension);
        header('Cache-Control: public, max-age=3600');
        header('Location: ' . $canonical, true, 301);
        exit;
    } else {
        failResponse('Invalid asset token', 400);
    }

    $realExtension = hls_detect_extension($sourceUrl, $extension);

    if ($realExtension !== $extension) {
        failResponse('Invalid asset extension', 400);
    }

    if ($extension === 'm3u8') {
        outputSharedPlaylist($sourceUrl);
    }

    if ($extension === 'key') {
        outputEncryptionKey($name, $sourceUrl);
    }

    outputSegment($name, $extension, $sourceUrl);
}

function outputSharedPlaylist(string $sourceUrl): void
{
    $entry = hls_playlist_get($sourceUrl);

    maybeCleanupCache();
    sendSharedPlaylistHeaders((int) $entry['age_ms']);

    if (!isHeadRequest()) {
        echo $entry['body'];
    }

    exit;
}

function outputEncryptionKey(string $name, string $sourceUrl): void
{
    /*
     * لا تُحفظ المفاتيح كملفات ثابتة: قد يعيد المصدر استخدام الرابط نفسه
     * بمفتاح جديد، فلا يجوز منحها صلاحية يوم كامل.
     */
    $key = hls_key_get($name, $sourceUrl);

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($key));
    header('Cache-Control: public, max-age=15, s-maxage=15');

    if (!isHeadRequest()) {
        echo $key;
    }

    exit;
}

function outputSegment(
    string $name,
    string $extension,
    string $sourceUrl
): void {
    $result = hls_segment_acquire($name, $extension, $sourceUrl);

    if ($result['state'] === 'busy') {
        header('Retry-After: 1');
        failResponse('Segment is being prepared', 503);
    }

    $staticUrl = hls_config('public_base_url')
        . '/hls-cache/' . $name . '.' . $extension;

    $alreadyRedirected = isset($_GET['r']);
    $wantsRange = ($_SERVER['HTTP_RANGE'] ?? '') !== '';

    if ($result['state'] === 'exists' || $wantsRange) {
        if (!$alreadyRedirected) {
            /*
             * الملف صار موجودًا فعلًا: نحوّل إليه ليُرسله Apache/Nginx
             * مباشرة (بدعم Range كامل) بدل تمريره عبر PHP.
             * التحويل نفسه لا يُخزَّن في أي كاش.
             */
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('CDN-Cache-Control: no-store');
            header('Cloudflare-CDN-Cache-Control: no-store');
            header('Location: ' . $staticUrl . '?r=1', true, 302);
            exit;
        }

        /*
         * وصلنا هنا بعد تحويل سابق: معناه أن قاعدة الملفات الثابتة في
         * ‎.htaccess‎ غير فعّالة. نرسل الملف مرة واحدة حتى لا يتعطّل البث،
         * ونسجّل تحذيرًا لأن الإعداد يحتاج مراجعة.
         */
        hls_log(
            'static delivery is not active for /hls-cache — '
            . 'check .htaccess rewrite rules'
        );

        emitCachedSegmentFile($name, $extension);
    }

    /* أول طلب فقط: نرسل ما جلبناه للتو، والباقي يخدمه الخادم مباشرة. */
    header('Content-Type: ' . segmentContentType($extension));
    header('Content-Length: ' . strlen($result['body']));
    header(
        'Cache-Control: public, max-age=86400, s-maxage=86400, immutable'
    );
    header('X-Tofi-Cache: MISS');

    if (!isHeadRequest()) {
        echo $result['body'];
    }

    exit;
}

function emitCachedSegmentFile(string $name, string $extension): void
{
    $path = hls_segment_path($name, $extension);

    if (!is_file($path)) {
        failResponse('Asset not available', 404);
    }

    header('Content-Type: ' . segmentContentType($extension));
    header('Content-Length: ' . (string) filesize($path));
    header(
        'Cache-Control: public, max-age=86400, s-maxage=86400, immutable'
    );
    header('X-Tofi-Cache: FALLBACK');

    if (!isHeadRequest()) {
        readfile($path);
    }

    exit;
}

function segmentContentType(string $extension): string
{
    $types = [
        'ts' => 'video/mp2t',
        'm4s' => 'video/iso.segment',
        'mp4' => 'video/mp4',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'vtt' => 'text/vtt; charset=utf-8',
        'key' => 'application/octet-stream',
    ];

    return $types[$extension] ?? 'application/octet-stream';
}

/* ═════════════════════════ توافق الروابط القديمة ═════════════════════════ */

/**
 * فك تشفير التوكن الطويل المستخدم في النسخة السابقة (AES-256-CBC + HMAC).
 * موجود فقط حتى تنتهي صلاحية الروابط التي أصدرها الإصدار القديم.
 */
function legacyDecryptAssetToken(string $token): string
{
    $decoded = legacyBase64UrlDecode($token);

    if ($decoded === false || strlen($decoded) <= 16) {
        throw new RuntimeException('Invalid asset token');
    }

    $encrypted = substr($decoded, 0, -16);
    $providedSignature = substr($decoded, -16);
    $key = hash('sha256', TOKEN_SECRET, true);
    $expectedSignature = substr(
        hash_hmac('sha256', $encrypted, $key, true),
        0,
        16
    );

    if (!hash_equals($expectedSignature, $providedSignature)) {
        throw new RuntimeException('Invalid asset signature');
    }

    $iv = substr(hash('sha256', 'ToFi-IV|' . TOKEN_SECRET, true), 0, 16);

    $sourceUrl = openssl_decrypt(
        $encrypted,
        'aes-256-cbc',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );

    if (!is_string($sourceUrl) || !hls_source_allowed($sourceUrl)) {
        throw new RuntimeException('Invalid asset source');
    }

    return $sourceUrl;
}

function legacyBase64UrlDecode(string $data)
{
    $remainder = strlen($data) % 4;

    if ($remainder !== 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'), true);
}

/* ═════════════════════════ أدوات الاستجابة ═════════════════════════ */

function isHeadRequest(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD';
}

function failResponse(string $message, int $status): void
{
    http_response_code($status);
    header('Cache-Control: no-store');

    /*
     * صفحة الخطأ الاحترافية بالرمز الصحيح لكل حالة. لا تُذكر أي تفاصيل عن
     * المصدر أو الروابط الداخلية في أي رسالة.
     */
    $errorRenderer = __DIR__ . '/error.php';

    if (is_file($errorRenderer)) {
        header('Content-Type: text/html; charset=utf-8');

        if (!isHeadRequest()) {
            $__errorStatus = $status;
            include $errorRenderer;
        }
    } else {
        header('Content-Type: text/plain; charset=utf-8');

        if (!isHeadRequest()) {
            echo $message;
        }
    }

    exit;
}
