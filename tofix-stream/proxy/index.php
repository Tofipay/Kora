<?php

/**
 * proxy/index.php
 * -----------------------------------------------------------------------------
 * نقطة دخول البروكسي الذكي — الرابط الجديد الذي يُعطى للمشاهدين.
 *
 * نمطان للاستدعاء:
 *   1) ?channel=CHANNEL_ID
 *      نقطة البداية: تجلب رابط القناة الأصلي من التخزين وتبدأ إعادة الكتابة.
 *      (اختياريًا مع &token=... إذا فُعّلت الروابط الموقّعة.)
 *
 *   2) ?u=<base64url>&s=<signature>
 *      روابط داخلية موقّعة تولّدها إعادة الكتابة (مقاطع/بلاي-ليست فرعية/مفاتيح).
 *
 * في الحالتين لا يُكشف الرابط الأصلي إطلاقًا للمشاهد.
 *
 * @package ToFiXStream\Proxy
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\Config;
use ToFiXStream\ChannelManager;
use ToFiXStream\HlsProxy;
use ToFiXStream\Security;

// طلب CORS المسبق.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    http_response_code(204);
    exit;
}

// طبقات الحماية: IP allowlist, Hotlink, Rate limit.
if (!Security::checkIp()) {
    http_response_code(403);
    exit('Forbidden: IP not allowed.');
}
if (!Security::checkReferer()) {
    http_response_code(403);
    exit('Forbidden: hotlink protection.');
}
if (!Security::rateLimit()) {
    http_response_code(429);
    exit('Too Many Requests.');
}

$proxy = new HlsProxy();

// --- الحالة 2: رابط داخلي موقّع (مقطع / بلاي-ليست فرعية) ---
if (isset($_GET['u'], $_GET['s'])) {
    $original = $proxy->resolveUrl((string) $_GET['u'], (string) $_GET['s']);
    if ($original === null) {
        http_response_code(403);
        exit('Invalid signature.');
    }
    $proxy->handle($original);
    exit;
}

// --- الحالة 1: نقطة بداية قناة ---
$channelId = (string) ($_GET['channel'] ?? '');
if ($channelId === '') {
    http_response_code(400);
    exit('Missing channel parameter.');
}

$manager = new ChannelManager();
$channel = $manager->get($channelId);
if (!$channel) {
    http_response_code(404);
    exit('Channel not found.');
}

// إن كانت الروابط الموقّعة مفعّلة، تحقّق من التوكن.
if (Config::get('security.hotlink_protection', false) && !empty($_GET['token'])) {
    if (!Security::verifyToken($channelId, (string) $_GET['token'])) {
        http_response_code(403);
        exit('Invalid or expired token.');
    }
}

// إن كانت القناة تعمل عبر FFmpeg محليًا، أعِد توجيه البثّ من الملف المحلّي؛
// وإلا استخدم البروكسي المباشر على الرابط الأصلي.
$sourceUrl = (string) $channel['source_url'];
if (($channel['mode'] ?? 'proxy') === 'ffmpeg') {
    $localPlaylist = Config::get('ffmpeg.output_dir') . '/' . $channelId . '/index.m3u8';
    if (is_file($localPlaylist)) {
        // نمرّر البلاي-ليست المحلّي عبر نفس منطق إعادة الكتابة ليصبح المسار مطلقًا.
        $sourceUrl = Config::baseUrl() . '/streams/' . $channelId . '/index.m3u8';
    }
}

$proxy->handle($sourceUrl);
