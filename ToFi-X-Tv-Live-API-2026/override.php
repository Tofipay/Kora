<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  override.php — نظام تبديل القناة بصورة/فيديو بدل البث المباشر
 * ───────────────────────────────────────────────────────────────────────────
 *  يُستخدم بطريقتين:
 *    1) داخل index.php  → override_maybe_serve($channel) قبل البث الطبيعي.
 *    2) داخل panel.php   → دوال الإدارة (حفظ/حذف/تحويل الوسائط).
 *
 *  مبدأ السلامة: لو لم توجد أي تهيئة أو حدث خطأ، لا نلمس البث الطبيعي إطلاقًا.
 *  التخزين في ملف PHP يعيد مصفوفة (آمن من التسريب لو فُتح من المتصفح).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!defined('OVR_BASE_DIR')) {
    define('OVR_BASE_DIR', __DIR__ . '/overrides');
    // ملف مخفي (يمنعه .htaccess) وبصيغة JSON حتى لا يتأثر بذاكرة opcache،
    // فتظهر تغييرات اللوحة فورًا على البث.
    define('OVR_DATA_FILE', OVR_BASE_DIR . '/.data.json');
    define('OVR_MEDIA_DIR', OVR_BASE_DIR . '/media');
    define('OVR_UPLOAD_DIR', OVR_BASE_DIR . '/.uploads');   // مخفي عبر .htaccess
    define('OVR_DEFAULT_KEY', '_default');
    define('OVR_SEG_WINDOW', 4);   // عدد المقاطع في نافذة البث الحي
}

/* ───────────────────────── التخزين ───────────────────────── */

function ovr_load(): array
{
    if (is_file(OVR_DATA_FILE)) {
        $raw = @file_get_contents(OVR_DATA_FILE);

        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);

            if (is_array($data)) {
                $data += [
                    'admin' => null,
                    'channels' => [],
                    'templates' => [],
                ];

                // مفاتيح JSON نصية → نُعيدها أرقامًا صحيحة للقنوات.
                $channels = [];
                if (is_array($data['channels'])) {
                    foreach ($data['channels'] as $key => $value) {
                        $channels[(int) $key] = $value;
                    }
                }
                $data['channels'] = $channels;

                if (!is_array($data['templates'])) {
                    $data['templates'] = [];
                }

                return $data;
            }
        }
    }

    return [
        'admin' => null,
        'channels' => [],
        'templates' => [],
    ];
}

function ovr_save(array $data): void
{
    ovr_ensure_dir(OVR_BASE_DIR);

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if ($json === false) {
        throw new RuntimeException('تعذّر ترميز الإعدادات');
    }

    $temporary = OVR_DATA_FILE . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $json, LOCK_EX) === false) {
        throw new RuntimeException('تعذّر حفظ الإعدادات');
    }

    @chmod($temporary, 0640);

    if (!@rename($temporary, OVR_DATA_FILE)) {
        @unlink($temporary);
        throw new RuntimeException('تعذّر نشر الإعدادات');
    }
}

/* ───────────────────────── منطق التبديل ───────────────────────── */

/**
 * ترجع بيانات التبديل الفعّال للقناة، أو null إذا لا يوجد تبديل نشط.
 * إذا مرّ وقت العودة (until) تُعاد null تلقائيًا → يعود البث الأصلي وحده.
 */
function ovr_active_channel(int $channel): ?array
{
    $data = ovr_load();
    $entry = $data['channels'][$channel] ?? null;

    if (!is_array($entry) || empty($entry['enabled'])) {
        return null;
    }

    if (!empty($entry['until']) && time() >= (int) $entry['until']) {
        return null; // انتهى الموعد المؤقت → عودة تلقائية للبث
    }

    return $entry;
}

/**
 * القوالب الجاهزة المتوفّرة (فقط التي لها وسائط فعلية).
 */
function ovr_builtin_presets(): array
{
    return [
        '_default' => 'سيعود البث قريبا - بصوت',
        '_silent' => 'سيعود البث قريبا - بدون صوت',
        '_scene' => 'انطلاق المباراة - بدون صوت',
        '_tofi_music' => 'قالب ToFi X Tv',
    ];
}

function ovr_user_templates(): array
{
    $data = ovr_load();
    $templates = [];

    foreach ($data['templates'] ?? [] as $key => $template) {
        if (
            !is_string($key)
            || preg_match('/^tpl_[a-f0-9]{16}$/', $key) !== 1
            || !is_array($template)
        ) {
            continue;
        }

        $name = trim((string) ($template['name'] ?? ''));
        if (
            $name === ''
            || !is_file(ovr_media_dir_for($key) . '/meta.json')
        ) {
            continue;
        }

        $templates[$key] = [
            'name' => $name,
            'kind' => (string) ($template['kind'] ?? 'hls'),
            'created' => (int) ($template['created'] ?? 0),
        ];
    }

    return $templates;
}

function ovr_presets(): array
{
    $available = [];

    foreach (ovr_builtin_presets() as $key => $label) {
        if (is_file(ovr_media_dir_for($key) . '/meta.json')) {
            $available[$key] = $label;
        }
    }

    foreach (ovr_user_templates() as $key => $template) {
        $available[$key] = (string) $template['name'];
    }

    return $available ?: [OVR_DEFAULT_KEY => 'القالب الافتراضي'];
}

function ovr_new_template_key(): string
{
    do {
        $key = 'tpl_' . bin2hex(random_bytes(8));
    } while (is_dir(ovr_media_dir_for($key)));

    return $key;
}

function ovr_template_name_exists(string $name): bool
{
    $needle = mb_strtolower(trim($name), 'UTF-8');

    foreach (ovr_user_templates() as $template) {
        if (
            mb_strtolower((string) $template['name'], 'UTF-8')
            === $needle
        ) {
            return true;
        }
    }

    return false;
}

function ovr_media_key(int $channel, array $entry): string
{
    if (($entry['source'] ?? '') === 'custom') {
        return (string) $channel;
    }

    $preset = (string) ($entry['preset'] ?? OVR_DEFAULT_KEY);

    return array_key_exists($preset, ovr_presets()) ? $preset : OVR_DEFAULT_KEY;
}

function ovr_media_dir_for(string $mediaKey): string
{
    if (
        preg_match(
            '/^(?:[1-9][0-9]{0,5}|_[a-z0-9_]{1,40}|tpl_[a-f0-9]{16})$/',
            $mediaKey
        ) !== 1
    ) {
        throw new InvalidArgumentException('مفتاح الوسائط غير صالح');
    }

    return OVR_MEDIA_DIR . '/' . $mediaKey;
}

/**
 * نقطة الدخول من index.php. إن كان هناك تبديل نشط للقناة، تبثّ القالب
 * (صورة/فيديو) بصيغة HLS وتُنهي التنفيذ. غير ذلك تعود بدون أي أثر.
 */
function override_maybe_serve(int $channel): void
{
    $entry = ovr_active_channel($channel);
    if ($entry === null) {
        return;
    }

    $mediaKey = ovr_media_key($channel, $entry);
    $metaFile = ovr_media_dir_for($mediaKey) . '/meta.json';

    if (!is_file($metaFile)) {
        return; // الوسائط غير جاهزة → لا نعطّل البث
    }

    $meta = json_decode((string) @file_get_contents($metaFile), true);
    if (!is_array($meta) || empty($meta['segments'])) {
        return;
    }

    ovr_output_loop_playlist($mediaKey, $meta);
    exit;
}

/**
 * يبني قائمة تشغيل حية تدور على المقاطع بلا نهاية (loop) بسلاسة على كل
 * المشغّلات، باستخدام نافذة منزلقة محسوبة من الوقت الحالي.
 */
function ovr_output_loop_playlist(string $mediaKey, array $meta): void
{
    $segments = array_values($meta['segments']);
    $count    = count($segments);
    $segTime  = max(1.0, (float) ($meta['seg'] ?? 2));
    $window   = (int) min(OVR_SEG_WINDOW, $count);

    $globalIndex = (int) floor(time() / $segTime);
    $base = ovr_public_base() . '/overrides/media/' . rawurlencode($mediaKey) . '/';

    $lines = [
        '#EXTM3U',
        '#EXT-X-VERSION:3',
        '#EXT-X-TARGETDURATION:' . (int) ceil($segTime),
        '#EXT-X-MEDIA-SEQUENCE:' . $globalIndex,
    ];

    for ($k = 0; $k < $window; $k++) {
        $segment  = $segments[($globalIndex + $k) % $count];
        $duration = number_format((float) ($segment[1] ?? $segTime), 3, '.', '');
        $lines[]  = '#EXTINF:' . $duration . ',';
        $lines[]  = $base . rawurlencode((string) $segment[0]);
    }
    // قائمة حية (بدون EXT-X-ENDLIST) → تكرار مستمر.

    if (!headers_sent()) {
        header('Content-Type: application/vnd.apple.mpegurl; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Access-Control-Allow-Origin: *');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo implode("\n", $lines) . "\n";
    }
}

function ovr_public_base(): string
{
    if (defined('PUBLIC_BASE_URL')) {
        return rtrim(PUBLIC_BASE_URL, '/');
    }

    $https = (($_SERVER['HTTPS'] ?? '') === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return ($https ? 'https' : 'http') . '://' . $host;
}

/* ───────────────────────── تحويل الوسائط ───────────────────────── */

function ovr_ffmpeg_bin(): ?string
{
    static $cached = false;
    static $bin = null;

    if ($cached) {
        return $bin;
    }
    $cached = true;

    if (!function_exists('shell_exec')) {
        return $bin = null;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (in_array('shell_exec', $disabled, true)) {
        return $bin = null;
    }

    foreach (['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/bin/ffmpeg'] as $candidate) {
        $out = @shell_exec(escapeshellarg($candidate) . ' -version 2>/dev/null');
        if (is_string($out) && stripos($out, 'ffmpeg version') !== false) {
            return $bin = $candidate;
        }
    }

    return $bin = null;
}

/**
 * يحوّل صورة أو فيديو مرفوعًا إلى مقاطع HLS داخل مجلد قناة أو قالب.
 * يتطلب ffmpeg على الخادم. يرمي استثناءً برسالة عربية عند الفشل.
 */
function ovr_ingest_media(
    string $mediaKey,
    string $sourcePath,
    string $kind
): array
{
    $bin = ovr_ffmpeg_bin();
    if ($bin === null) {
        throw new RuntimeException(
            'ffmpeg غير متوفّر على الخادم. استخدم القالب الافتراضي، أو ارفع حزمة HLS جاهزة (‎.zip‎).'
        );
    }

    $dir = ovr_media_dir_for($mediaKey);
    ovr_rrmdir($dir);
    ovr_ensure_dir($dir);

    $segTime = 2;
    $scale   = "scale=1280:720:force_original_aspect_ratio=decrease,"
             . "pad=1280:720:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1";

    $hls = "-force_key_frames " . escapeshellarg("expr:gte(t,n_forced*$segTime)")
         . " -g 48 -keyint_min 48 -sc_threshold 0"
         . " -f hls -hls_time $segTime -hls_list_size 0 -hls_flags independent_segments"
         . " -hls_segment_filename " . escapeshellarg($dir . '/seg%03d.ts')
         . ' ' . escapeshellarg($dir . '/index.m3u8');

    if ($kind === 'image') {
        $command = escapeshellarg($bin) . ' -y -loop 1 -i ' . escapeshellarg($sourcePath)
            . ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -t 10 -r 24'
            . ' -vf ' . escapeshellarg($scale)
            . ' -c:v libx264 -preset veryfast -tune stillimage -pix_fmt yuv420p'
            . ' -c:a aac -b:a 64k -ac 2 -shortest ' . $hls . ' 2>&1';
    } else {
        $command = escapeshellarg($bin) . ' -y -i ' . escapeshellarg($sourcePath)
            . ' -vf ' . escapeshellarg($scale) . ' -r 24'
            . ' -c:v libx264 -preset veryfast -pix_fmt yuv420p'
            . ' -c:a aac -b:a 96k -ac 2 ' . $hls . ' 2>&1';
    }

    $log = (string) @shell_exec($command);

    $segments = ovr_parse_hls($dir . '/index.m3u8');
    if (empty($segments)) {
        ovr_rrmdir($dir);
        throw new RuntimeException('فشل تحويل الوسائط. تفاصيل: ' . mb_substr(trim($log), -280));
    }

    return ovr_write_meta($dir, $segments, $segTime, $kind);
}

/**
 * يستورد حزمة HLS جاهزة (‎.zip‎ تحوي index.m3u8 ومقاطع ‎.ts‎) بدون ffmpeg.
 */
function ovr_import_hls_zip(string $mediaKey, string $zipPath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('إضافة Zip غير متوفّرة على الخادم');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('تعذّر فتح ملف ZIP');
    }

    $dir = ovr_media_dir_for($mediaKey);
    ovr_rrmdir($dir);
    ovr_ensure_dir($dir);

    $allowed  = ['m3u8', 'ts', 'm4s', 'mp4', 'aac', 'vtt', 'key'];
    $playlist = null;
    $segments = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $base = basename($name);

        if ($base === '' || $base[0] === '.' || substr($name, -1) === '/') {
            continue;
        }

        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }

        $data = $zip->getFromIndex($i);
        if ($data === false) {
            continue;
        }

        if (file_put_contents($dir . '/' . $base, $data, LOCK_EX) !== false) {
            if ($ext === 'm3u8') {
                if (
                    $playlist === null
                    || strtolower($base) === 'index.m3u8'
                ) {
                    $playlist = $dir . '/' . $base;
                }
            } elseif (in_array($ext, ['ts', 'm4s', 'mp4'], true)) {
                $segments++;
            }
        }
    }

    $zip->close();

    if ($playlist === null || $segments === 0) {
        ovr_rrmdir($dir);
        throw new RuntimeException('حزمة HLS غير صالحة (يجب أن تحتوي على index.m3u8 ومقاطع ‎.ts‎).');
    }

    if (basename($playlist) !== 'index.m3u8') {
        @copy($playlist, $dir . '/index.m3u8');
    }

    $parsed = ovr_parse_hls($dir . '/index.m3u8');
    if (empty($parsed)) {
        ovr_rrmdir($dir);
        throw new RuntimeException('تعذّر قراءة مقاطع الحزمة');
    }

    // نتأكد أن كل مقطع مذكور موجود فعلًا كملف داخل المجلد.
    $existing = [];
    foreach ($parsed as $segment) {
        if (is_file($dir . '/' . $segment[0])) {
            $existing[] = $segment;
        }
    }

    if (empty($existing)) {
        ovr_rrmdir($dir);
        throw new RuntimeException('مقاطع الحزمة غير متطابقة مع القائمة');
    }

    return ovr_write_meta(
        $dir,
        $existing,
        ovr_detect_segment_time($existing),
        'hls'
    );
}

function ovr_detect_segment_time(array $segments): float
{
    $durations = [];

    foreach ($segments as $segment) {
        $duration = (float) ($segment[1] ?? 0);
        if ($duration > 0) {
            $durations[] = $duration;
        }
    }

    if ($durations === []) {
        return 2.0;
    }

    sort($durations, SORT_NUMERIC);
    $middle = (int) floor((count($durations) - 1) / 2);

    return max(0.25, min(60.0, (float) $durations[$middle]));
}

function ovr_write_meta(
    string $dir,
    array $segments,
    float $segTime,
    string $kind
): array
{
    $meta = [
        'segments' => array_values($segments),
        'seg'      => $segTime,
        'kind'     => $kind,
        'created'  => time(),
    ];

    file_put_contents(
        $dir . '/meta.json',
        json_encode($meta, JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    return $meta;
}

/**
 * يقرأ أسماء المقاطع ومددها من قائمة m3u8 (الأسماء تُختصر إلى basename).
 */
function ovr_parse_hls(string $playlistPath): array
{
    if (!is_file($playlistPath)) {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($playlistPath)) ?: [];
    $segments = [];
    $duration = 2.0;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (strpos($line, '#EXTINF:') === 0) {
            $duration = (float) substr($line, 8);
        } elseif ($line[0] !== '#') {
            $segments[] = [basename($line), $duration > 0 ? $duration : 2.0];
        }
    }

    return $segments;
}

/* ───────────────────────── أدوات مساعدة ───────────────────────── */

function ovr_ensure_dir(string $directory): void
{
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('تعذّر إنشاء المجلد: ' . $directory);
    }
}

function ovr_rrmdir(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory . '/' . $item;
        is_dir($path) ? ovr_rrmdir($path) : @unlink($path);
    }

    @rmdir($directory);
}

function ovr_delete_channel_media(int $channel): void
{
    ovr_rrmdir(ovr_media_dir_for((string) $channel));
}
