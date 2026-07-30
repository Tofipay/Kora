<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  worker.php — خدمة السحب المستمر (VPS فقط — اختيارية بالكامل)
 * ───────────────────────────────────────────────────────────────────────────
 *  ⚠ هذا الملف ليس جزءًا من التشغيل الأساسي.
 *
 *  على الاستضافة المشتركة (Hostinger Business مثلًا) لا تشغّله ولا تحتاجه:
 *  النظام يعمل بالكامل بوضع request_driven داخل الطلبات نفسها. وجود هذا
 *  الملف على السيرفر بلا تشغيل لا يؤثر على شيء إطلاقًا، و‎.htaccess‎ يمنع
 *  فتحه من المتصفح.
 *
 *  استخدمه فقط بعد الانتقال إلى VPS، حيث يضيف تحميل المقاطع مسبقًا.
 *
 *  ماذا تفعل:
 *    • تسحب قائمة كل قناة باستمرار حسب TARGETDURATION.
 *    • تكتشف المقاطع الجديدة وتحمّلها مسبقًا قبل أن يطلبها المشاهدون.
 *    • تكتب القوائم المحلية الجاهزة في الكاش نفسه الذي يقرأه index.php.
 *    • تنظّف الكاش دوريًا.
 *
 *  مهم: هذه الخدمة تحسين اختياري فقط. إذا توقّفت، يستمر البث بوضع
 *  request_driven تلقائيًا لأن index.php يستخدم نفس الكاش ونفس القفل.
 *
 *  التشغيل:
 *      php worker.php --channels=10,11,20
 *      php worker.php --once            (جولة واحدة، مفيدة في cron)
 *      php worker.php --verbose
 *
 *  عبر systemd:    systemd/tofi-hls-worker.service
 *  عبر Supervisor: supervisor/tofi-hls-worker.conf
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("worker.php works from the command line only\n");
}

require_once __DIR__ . '/hls-core.php';

$options = worker_parse_arguments($argv ?? []);
$channels = $options['channels'] !== []
    ? $options['channels']
    : array_values(array_filter(array_map(
        'intval',
        (array) hls_config('worker_channels')
    )));

if ($channels === []) {
    fwrite(
        STDERR,
        "لا توجد قنوات. استخدم --channels=10,11 أو اضبط worker_channels "
        . "في config.php\n"
    );
    exit(1);
}

$running = true;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    $stop = static function (int $signal) use (&$running): void {
        $running = false;
    };

    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

worker_log(
    'started | mode=' . (string) hls_config('mode')
    . ' | channels=' . implode(',', $channels)
    . ' | prefetch=' . (hls_config('worker_prefetch') ? 'on' : 'off'),
    $options
);

$lastHeartbeat = 0;
$lastCleanup = 0;

do {
    $roundStarted = hls_now_ms();
    $nextDelayMs = 1000;

    foreach ($channels as $channel) {
        try {
            $delay = worker_process_channel($channel, $options);
            $nextDelayMs = min($nextDelayMs, $delay);
        } catch (Throwable $error) {
            worker_log(
                'channel ' . $channel . ' failed: ' . $error->getMessage(),
                $options,
                true
            );
        }
    }

    $now = time();

    if ($now - $lastHeartbeat >= (int) hls_config('worker_heartbeat_seconds')) {
        worker_heartbeat($channels);
        $lastHeartbeat = $now;
    }

    if ($now - $lastCleanup >= 30) {
        try {
            hls_cleanup(true);
        } catch (Throwable $error) {
            worker_log('cleanup failed: ' . $error->getMessage(), $options, true);
        }

        $lastCleanup = $now;
    }

    if ($options['once']) {
        break;
    }

    /*
     * نحدّث عند 75% من عمر القائمة، فتبقى دائمًا "حديثة" عند وصول أي مشاهد،
     * ولا يضطر أي طلب في وضع request_driven لفتح اتصال بالمصدر.
     */
    $elapsed = hls_now_ms() - $roundStarted;
    $sleepMs = max(120, (int) ($nextDelayMs * 0.75) - $elapsed);

    usleep($sleepMs * 1000);
} while ($running);

worker_log('stopped', $options);
exit(0);

/* ═════════════════════════ الدوال ═════════════════════════ */

function worker_parse_arguments(array $argv): array
{
    $options = [
        'channels' => [],
        'once' => false,
        'verbose' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--channels=')) {
            $options['channels'] = array_values(array_filter(array_map(
                'intval',
                explode(',', substr($argument, 11))
            )));
            continue;
        }

        if ($argument === '--once') {
            $options['once'] = true;
            continue;
        }

        if ($argument === '--verbose' || $argument === '-v') {
            $options['verbose'] = true;
        }
    }

    return $options;
}

function worker_log(string $message, array $options, bool $isError = false): void
{
    if (!$isError && !$options['verbose']) {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [tofi-worker] ' . $message . "\n";
    fwrite($isError ? STDERR : STDOUT, $line);
}

/**
 * جولة واحدة على قناة: تحديث القائمة، ثم تحميل المقاطع الجديدة مسبقًا.
 * تُعيد المدة المقترحة (مللي ثانية) قبل الجولة التالية.
 */
function worker_process_channel(int $channel, array $options): int
{
    $sourceUrl = hls_channel_source_url($channel);
    $entry = hls_playlist_get($sourceUrl);
    $kind = (string) ($entry['meta']['kind'] ?? 'live');

    if ($kind === 'master') {
        /* قائمة رئيسية: نتابع القوائم الداخلية أيضًا. */
        $delay = (int) $entry['meta']['refresh_ms'];

        foreach (worker_child_playlists($entry['body']) as $childUrl) {
            try {
                $child = hls_playlist_get($childUrl);
                $delay = min($delay, (int) $child['meta']['refresh_ms']);

                worker_prefetch($childUrl, $options);
            } catch (Throwable $error) {
                worker_log(
                    'variant failed: ' . $error->getMessage(),
                    $options,
                    true
                );
            }
        }

        return max(200, $delay);
    }

    worker_prefetch($sourceUrl, $options);

    return max(200, (int) $entry['meta']['refresh_ms']);
}

/**
 * يستخرج روابط القوائم الداخلية من قائمة رئيسية محلية.
 */
function worker_child_playlists(string $body): array
{
    $urls = [];

    if (
        preg_match_all(
            '#/hls-cache/([a-f0-9]{40})\.m3u8#i',
            $body,
            $matches
        ) === false
    ) {
        return $urls;
    }

    foreach (array_unique($matches[1] ?? []) as $name) {
        $sourceUrl = hls_asset_map_lookup((string) $name);

        if ($sourceUrl !== null) {
            $urls[] = $sourceUrl;
        }
    }

    return $urls;
}

/**
 * يحمّل المقاطع الجديدة في النافذة الحية قبل أن يطلبها أول مشاهد.
 */
function worker_prefetch(string $sourceUrl, array $options): void
{
    if (!hls_config('worker_prefetch')) {
        return;
    }

    $paths = hls_playlist_paths(hls_playlist_key($sourceUrl));

    if (!is_file($paths['window'])) {
        return;
    }

    $window = json_decode((string) @file_get_contents($paths['window']), true);

    if (!is_array($window) || empty($window['segments'])) {
        return;
    }

    $segments = array_slice(
        $window['segments'],
        -1 * max(1, (int) hls_config('worker_prefetch_max'))
    );

    foreach ($segments as $segment) {
        $fileName = (string) ($segment['name'] ?? '');

        if (preg_match('/^([a-f0-9]{40})\.([a-z0-9]{2,5})$/', $fileName, $parts) !== 1) {
            continue;
        }

        [$full, $name, $extension] = $parts;

        if ($extension === 'key' || $extension === 'm3u8') {
            continue;
        }

        $path = hls_segment_path($name, $extension);

        if (is_file($path) && filesize($path) > 0) {
            continue;
        }

        $segmentSource = hls_asset_map_lookup($name);

        if ($segmentSource === null) {
            continue;
        }

        try {
            $result = hls_segment_acquire($name, $extension, $segmentSource);

            if ($result['state'] === 'fetched') {
                worker_log('prefetched ' . $fileName, $options);
            }
        } catch (Throwable $error) {
            worker_log(
                'prefetch failed for ' . substr($fileName, 0, 12)
                . '…: ' . $error->getMessage(),
                $options,
                true
            );
        }
    }
}

function worker_heartbeat(array $channels): void
{
    try {
        $directory = (string) hls_config('cache_root');
        hls_ensure_directory($directory, 0700);

        hls_atomic_write(
            $directory . '/worker.heartbeat',
            (string) json_encode([
                'pid' => getmypid(),
                'updated_at' => time(),
                'channels' => array_values($channels),
                'mode' => (string) hls_config('mode'),
            ], JSON_UNESCAPED_SLASHES),
            0600
        );
    } catch (Throwable $error) {
        /* نبضة الحياة ليست حرجة. */
    }
}
