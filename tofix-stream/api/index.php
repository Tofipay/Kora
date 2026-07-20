<?php

/**
 * api/index.php
 * -----------------------------------------------------------------------------
 * موجّه REST API لمنصّة ToFi X Stream.
 *
 * كل النقاط تُرجع JSON بصيغة موحّدة (انظر Response).
 * التوجيه يعتمد باراميتر ?resource=&action= لسهولة العمل بدون rewrite،
 * وتُدعم أيضًا أفعال HTTP (GET/POST/PUT/DELETE).
 *
 * نقاط النهاية:
 *   GET    ?resource=channels                 قائمة القنوات
 *   GET    ?resource=channels&id=XXX          قناة واحدة
 *   POST   ?resource=channels                 إنشاء قناة   (body JSON)
 *   PUT    ?resource=channels&id=XXX          تحديث قناة   (body JSON)
 *   DELETE ?resource=channels&id=XXX          حذف قناة
 *   POST   ?resource=channels&action=duplicate&id=XXX   تكرار
 *
 *   POST   ?resource=stream&action=start&id=XXX    تشغيل FFmpeg
 *   POST   ?resource=stream&action=stop&id=XXX     إيقاف
 *   POST   ?resource=stream&action=restart&id=XXX  إعادة تشغيل
 *   GET    ?resource=stream&action=status&id=XXX   حالة البثّ
 *   GET    ?resource=stream&action=monitor&id=XXX  مقاييس ffprobe
 *
 *   GET    ?resource=stats                     إحصائيات اللوحة
 *   GET    ?resource=system                    مؤشّرات الخادم
 *   GET    ?resource=token&id=XXX              توليد توكن موقّع للقناة
 *
 * @package ToFiXStream\Api
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\Config;
use ToFiXStream\ChannelManager;
use ToFiXStream\FFmpegManager;
use ToFiXStream\StreamMonitor;
use ToFiXStream\SystemStats;
use ToFiXStream\Security;
use ToFiXStream\Response;

// معالجة طلب التحقّق المسبق (CORS preflight).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    Response::json(null, 204);
}

// تحديد معدّل الطلبات.
if (!Security::rateLimit()) {
    Response::error('تجاوزت الحد المسموح من الطلبات. حاول لاحقًا.', 429);
}

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = $_GET['resource'] ?? 'channels';
$action   = $_GET['action'] ?? '';
$id       = $_GET['id'] ?? '';

// الأفعال التي تعدّل البيانات تتطلّب مفتاح API إداري.
$writeMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
$adminResources = ['channels', 'stream'];
if (in_array($method, $writeMethods, true) && in_array($resource, $adminResources, true)) {
    if (!Security::checkApiKey()) {
        Response::error('مفتاح API مطلوب أو غير صحيح.', 401);
    }
}

/**
 * قراءة جسم الطلب كـ JSON أو form-data.
 *
 * @return array<string,mixed>
 */
$readBody = static function (): array {
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
};

try {
    switch ($resource) {

        // ---------------------------------------------------------------
        // القنوات (CRUD)
        // ---------------------------------------------------------------
        case 'channels':
            $manager = new ChannelManager();

            if ($action === 'duplicate' && $method === 'POST') {
                $dup = $manager->duplicate($id);
                $dup ? Response::json($dup, 201) : Response::error('القناة غير موجودة.', 404);
            }

            switch ($method) {
                case 'GET':
                    if ($id !== '') {
                        $ch = $manager->get($id);
                        $ch ? Response::json($ch) : Response::error('القناة غير موجودة.', 404);
                    }
                    $list = $manager->list();
                    Response::json($list, 200, ['count' => count($list)]);
                    // no break (Response ينهي التنفيذ)

                case 'POST':
                    $result = $manager->create($readBody());
                    $result['ok']
                        ? Response::json($result['channel'], 201)
                        : Response::error($result['errors'] ?? ['خطأ غير معروف'], 422);
                    // no break

                case 'PUT':
                case 'PATCH':
                    if ($id === '') {
                        Response::error('مُعرّف القناة مطلوب.', 400);
                    }
                    $result = $manager->update($id, $readBody());
                    $result['ok']
                        ? Response::json($result['channel'])
                        : Response::error($result['errors'] ?? ['خطأ'], 422);
                    // no break

                case 'DELETE':
                    if ($id === '') {
                        Response::error('مُعرّف القناة مطلوب.', 400);
                    }
                    $manager->delete($id)
                        ? Response::json(['deleted' => $id])
                        : Response::error('القناة غير موجودة.', 404);
                    // no break

                default:
                    Response::error('طريقة غير مدعومة.', 405);
            }
            break;

        // ---------------------------------------------------------------
        // التحكّم بالبثّ (FFmpeg)
        // ---------------------------------------------------------------
        case 'stream':
            $manager = new ChannelManager();
            $ff = new FFmpegManager();

            if ($id === '') {
                Response::error('مُعرّف القناة مطلوب.', 400);
            }
            $channel = $manager->get($id);
            if (!$channel && !in_array($action, ['status'], true)) {
                Response::error('القناة غير موجودة.', 404);
            }

            switch ($action) {
                case 'start':
                    Response::json($ff->start($channel));
                case 'stop':
                    Response::json($ff->stop($id));
                case 'restart':
                    Response::json($ff->restart($channel));
                case 'status':
                    Response::json($ff->status($id));
                case 'monitor':
                    $monitor = new StreamMonitor();
                    $metrics = $monitor->probe((string) $channel['source_url']);
                    $manager->updateMetrics($id, $metrics);
                    Response::json($metrics);
                case 'test':
                    // اختبار المصدر من الخادم مباشرة (يعمل حتى لو exec معطّلة).
                    $proxy = new \ToFiXStream\HlsProxy();
                    Response::json($proxy->testSource((string) $channel['source_url']));
                default:
                    Response::error('إجراء غير معروف للبثّ.', 400);
            }
            break;

        // ---------------------------------------------------------------
        // إحصائيات اللوحة
        // ---------------------------------------------------------------
        case 'stats':
            $manager = new ChannelManager();
            $channels = $manager->list();
            $ff = new FFmpegManager();

            $active = 0;
            $viewers = 0;
            $live = 0;
            foreach ($channels as $c) {
                if (($c['status'] ?? '') === 'active') {
                    $active++;
                }
                $viewers += (int) ($c['viewers'] ?? 0);
                if ($ff->isRunning((string) $c['id'])) {
                    $live++; // القنوات التي تعمل عبر FFmpeg تُعدّ بثوثًا نشطة.
                }
            }

            Response::json([
                'total_channels'  => count($channels),
                'active_channels' => $active,
                'live_streams'    => $live,
                'total_viewers'   => $viewers,
            ]);
            break;

        // ---------------------------------------------------------------
        // مؤشّرات الخادم
        // ---------------------------------------------------------------
        case 'system':
            Response::json((new SystemStats())->snapshot());
            break;

        // ---------------------------------------------------------------
        // تشخيص القدرات (لماذا لا يعمل إعادة البثّ/الشعار؟)
        // ---------------------------------------------------------------
        case 'diagnostics':
            $ff = new FFmpegManager();
            $streamsDir = Config::get('paths.streams');
            Response::json([
                'exec_enabled'     => FFmpegManager::execEnabled(),
                'ffmpeg'           => $ff->isAvailable(),
                'ffprobe'          => (new StreamMonitor())->isAvailable(),
                'imagick'          => extension_loaded('imagick'),
                'streams_writable' => is_dir($streamsDir) && is_writable($streamsDir),
                'notes'            => [
                    'exec' => 'مطلوب لتشغيل FFmpeg وإعادة البثّ الحقيقي. إن كان معطّلًا استخدم وضع Proxy.',
                    'svg'  => 'شعار SVG يُحوَّل تلقائيًا إن توفّر Imagick أو exec؛ الأفضل رفع PNG.',
                ],
            ]);
            break;

        // ---------------------------------------------------------------
        // توليد توكن موقّع لقناة
        // ---------------------------------------------------------------
        case 'token':
            if ($id === '') {
                Response::error('مُعرّف القناة مطلوب.', 400);
            }
            Response::json(Security::signToken($id));
            break;

        // ---------------------------------------------------------------
        // رفع صورة (شعار العلامة المائية)
        // ---------------------------------------------------------------
        case 'upload':
            if ($method !== 'POST') {
                Response::error('استخدم POST لرفع الصورة.', 405);
            }
            if (!Security::checkApiKey()) {
                Response::error('مفتاح API مطلوب أو غير صحيح.', 401);
            }
            if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
                Response::error('لم يتمّ استلام أي ملفّ.', 400);
            }

            $tmp = $_FILES['file']['tmp_name'];
            $info = @getimagesize($tmp);
            $allowed = [
                IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp',
            ];
            if ($info === false || !isset($allowed[$info[2]])) {
                Response::error('صيغة الصورة غير مدعومة (المسموح: PNG, JPG, GIF, WEBP).', 422);
            }
            if (($_FILES['file']['size'] ?? 0) > 3 * 1024 * 1024) {
                Response::error('حجم الصورة يتجاوز 3 ميغابايت.', 422);
            }

            $dir = Config::get('paths.root') . '/assets/watermarks';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fname = 'wm_' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
            if (!@move_uploaded_file($tmp, $dir . '/' . $fname)) {
                Response::error('تعذّر حفظ الصورة على الخادم.', 500);
            }

            Response::json([
                'url'  => Config::baseUrl() . '/assets/watermarks/' . $fname,
                'name' => $fname,
            ], 201);
            break;

        default:
            Response::error('المورد المطلوب غير موجود.', 404);
    }
} catch (\Throwable $e) {
    \ToFiXStream\Logger::error('خطأ في الـ API', ['msg' => $e->getMessage()]);
    Response::error(
        Config::get('app.debug') ? $e->getMessage() : 'خطأ داخلي في الخادم.',
        500
    );
}
