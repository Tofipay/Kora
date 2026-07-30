<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  hls-loadtest.php — نفس اختبار k6 لكن بلا أي أدوات خارجية
 * ───────────────────────────────────────────────────────────────────────────
 *  يحاكي مشغّلات HLS حقيقية بالتوازي (curl_multi) ويطبع p50/p95/p99
 *  وعدد الأخطاء وعدد اتصالات المصدر الفعلية.
 *
 *      php loadtest/hls-loadtest.php --viewers=50 --duration=30 \
 *          --base=https://live-api-tofixtv.tofi-xtv.com --channels=10
 *
 *  الخيارات:
 *      --base=URL          العنوان العام للبروكسي
 *      --channels=10       أو 10-20-30 للجودات المتعددة
 *      --viewers=50        عدد المشاهدين المتزامنين
 *      --duration=30       مدة المشاهدة بالثواني
 *      --metrics-key=KEY   لعرض عدّادات المصدر قبل/بعد
 *      --origin=URL        عنوان مصدر الاختبار (لقراءة عدّاداته)
 *
 *  تنبيه: الأرقام تعتمد على السيرفر والشبكة. الاختبار يقيس ولا يَعِد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$options = [
    'base' => 'http://127.0.0.1:8802',
    'channels' => '10',
    'viewers' => 50,
    'duration' => 30,
    'metrics-key' => '',
    'origin' => '',
];

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $parts) === 1) {
        $options[$parts[1]] = is_numeric($parts[2])
            ? (int) $parts[2]
            : $parts[2];
    }
}

$base = rtrim((string) $options['base'], '/');
$viewers = max(1, (int) $options['viewers']);
$duration = max(5, (int) $options['duration']);

fwrite(STDOUT, sprintf(
    "\n== اختبار حمل HLS ==\nالعنوان: %s | القناة: %s | المشاهدون: %d | المدة: %ds\n\n",
    $base,
    (string) $options['channels'],
    $viewers,
    $duration
));

$metricsBefore = load_metrics($base, (string) $options['metrics-key']);
$originBefore = load_origin_counters((string) $options['origin']);

/* ═════════════ 1) الحصول على التوكنات ═════════════ */

$sessions = [];
$tokenSamples = [];
$multi = curl_multi_init();
$handles = [];

for ($index = 0; $index < $viewers; $index++) {
    $curl = curl_init($base . '/api/token/' . $options['channels']);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'MTX Player',
    ]);
    curl_multi_add_handle($multi, $curl);
    $handles[] = $curl;
}

run_multi($multi);

foreach ($handles as $curl) {
    $tokenSamples[] = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000;
    $data = json_decode((string) curl_multi_getcontent($curl), true);

    if (is_array($data) && isset($data['url'])) {
        $sessions[] = $data;
    }

    curl_multi_remove_handle($multi, $curl);
    curl_close($curl);
}

curl_multi_close($multi);

if ($sessions === []) {
    fwrite(STDERR, "تعذّر الحصول على أي توكن — تحقق من العنوان.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "تم إصدار %d توكن (p95 = %.0f ms)\n",
    count($sessions),
    percentile($tokenSamples, 95)
));

/* ═════════════ 2) حلقة المشاهدة ═════════════ */

$state = [];

foreach ($sessions as $index => $session) {
    $state[$index] = [
        'url' => (string) $session['url'],
        'leave' => (string) ($session['leave_url'] ?? ''),
        'kind' => 'playlist',
        'next_at' => microtime(true) + (mt_rand(0, 400) / 1000),
        'seen' => [],
        'queue' => [],
        'sequence' => -1,
        'master_resolved' => false,
    ];
}

$samples = ['playlist' => [], 'segment' => []];
$statusCounts = [];
$errors = 0;
$sequenceViolations = 0;
$segmentBytes = 0;
$polls = 0;

$multi = curl_multi_init();
$inflight = [];
$deadline = microtime(true) + $duration;

while (microtime(true) < $deadline || $inflight !== []) {
    $now = microtime(true);

    if ($now < $deadline) {
        foreach ($state as $index => &$viewer) {
            if (isset($viewer['busy']) && $viewer['busy']) {
                continue;
            }

            if ($viewer['next_at'] > $now) {
                continue;
            }

            if ($viewer['queue'] !== []) {
                $url = (string) array_shift($viewer['queue']);
                $kind = 'segment';
            } else {
                $url = $viewer['url'];
                $kind = 'playlist';
            }

            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'tofi-loadtest',
            ]);

            curl_multi_add_handle($multi, $curl);
            $inflight[spl_object_id($curl)] = [
                'handle' => $curl,
                'viewer' => $index,
                'kind' => $kind,
            ];

            $viewer['busy'] = true;
        }

        unset($viewer);
    }

    curl_multi_exec($multi, $active);

    if ($inflight !== []) {
        curl_multi_select($multi, 0.05);
    } else {
        usleep(20000);
    }

    while (($info = curl_multi_info_read($multi)) !== false) {
        $curl = $info['handle'];
        $entry = $inflight[spl_object_id($curl)] ?? null;

        if ($entry === null) {
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
            continue;
        }

        unset($inflight[spl_object_id($curl)]);

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $elapsed = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000;
        $body = (string) curl_multi_getcontent($curl);

        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        $samples[$entry['kind']][] = $elapsed;

        $index = $entry['viewer'];
        $viewer = &$state[$index];
        $viewer['busy'] = false;

        if ($status !== 200) {
            $errors++;
            $viewer['next_at'] = microtime(true) + 1.0;
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
            unset($viewer);
            continue;
        }

        if ($entry['kind'] === 'segment') {
            $segmentBytes += strlen($body);
            $viewer['next_at'] = microtime(true);
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
            unset($viewer);
            continue;
        }

        $polls++;

        /* Master Playlist → ننتقل مرة واحدة إلى أول جودة. */
        if (
            !$viewer['master_resolved']
            && str_contains($body, '#EXT-X-STREAM-INF')
        ) {
            $variant = null;

            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '' && $line[0] !== '#') {
                    $variant = $line;
                    break;
                }
            }

            if ($variant !== null) {
                $viewer['url'] = absolute_url($variant, $viewer['url']);
                $viewer['master_resolved'] = true;
                $viewer['next_at'] = microtime(true);
                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
                unset($viewer);
                continue;
            }
        }

        $parsed = parse_playlist($body, $viewer['url']);

        if ($parsed['sequence'] < $viewer['sequence']) {
            $sequenceViolations++;
        }

        $viewer['sequence'] = max($viewer['sequence'], $parsed['sequence']);

        $new = 0;

        foreach ($parsed['segments'] as $segment) {
            if (isset($viewer['seen'][$segment])) {
                continue;
            }

            $viewer['seen'][$segment] = true;

            /* لا نعيد تحميل ما شوهد، ولا نحمّل أكثر من 3 مقاطع في الدورة. */
            if ($new < 3) {
                $viewer['queue'][] = $segment;
                $new++;
            }
        }

        if (count($viewer['seen']) > 400) {
            $viewer['seen'] = array_slice($viewer['seen'], -200, null, true);
        }

        $target = $parsed['target'] > 0 ? $parsed['target'] : 6.0;
        $viewer['next_at'] = microtime(true)
            + ($viewer['queue'] !== [] ? 0.0 : max(0.5, $target / 2));

        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
        unset($viewer);
    }
}

curl_multi_close($multi);

/* ═════════════ 3) الخروج النظيف ═════════════ */

$multi = curl_multi_init();
$handles = [];

foreach ($state as $viewer) {
    if ($viewer['leave'] === '') {
        continue;
    }

    $curl = curl_init($viewer['leave']);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_multi_add_handle($multi, $curl);
    $handles[] = $curl;
}

run_multi($multi);

foreach ($handles as $curl) {
    curl_multi_remove_handle($multi, $curl);
    curl_close($curl);
}

curl_multi_close($multi);

/* ═════════════ 4) التقرير ═════════════ */

$metricsAfter = load_metrics($base, (string) $options['metrics-key']);
$originAfter = load_origin_counters((string) $options['origin']);

fwrite(STDOUT, "\n" . str_repeat('─', 62) . "\n");

foreach (['playlist' => 'قوائم التشغيل', 'segment' => 'المقاطع'] as $key => $label) {
    if ($samples[$key] === []) {
        continue;
    }

    sort($samples[$key]);

    fwrite(STDOUT, sprintf(
        "%-14s طلبات=%-6d p50=%6.0fms  p95=%6.0fms  p99=%6.0fms  max=%6.0fms\n",
        $label,
        count($samples[$key]),
        percentile($samples[$key], 50),
        percentile($samples[$key], 95),
        percentile($samples[$key], 99),
        end($samples[$key])
    ));
}

ksort($statusCounts);

fwrite(STDOUT, sprintf(
    "\nرموز الحالة: %s\nأخطاء: %d | تحديثات القائمة: %d | تراجع التسلسل: %d | حجم المقاطع: %.1f MB\n",
    json_encode($statusCounts),
    $errors,
    $polls,
    $sequenceViolations,
    $segmentBytes / 1048576
));

if ($metricsBefore !== null && $metricsAfter !== null) {
    fwrite(STDOUT, "\nعدّادات البروكسي خلال الاختبار:\n");

    foreach ($metricsAfter as $name => $value) {
        $delta = $value - (int) ($metricsBefore[$name] ?? 0);

        if ($delta !== 0) {
            fwrite(STDOUT, sprintf("    %-28s %d\n", $name, $delta));
        }
    }
}

if ($originBefore !== [] || $originAfter !== []) {
    fwrite(STDOUT, "\nاتصالات المصدر الفعلية:\n");

    foreach ($originAfter as $name => $value) {
        $delta = $value - (int) ($originBefore[$name] ?? 0);

        if ($delta !== 0 && !str_starts_with($name, 'resource:')) {
            fwrite(STDOUT, sprintf("    %-28s %d\n", $name, $delta));
        }
    }
}

fwrite(STDOUT, "\nملاحظة: النتيجة تعتمد على مواصفات السيرفر والشبكة وCDN.\n");

exit($errors === 0 ? 0 : 2);

/* ═════════════ أدوات ═════════════ */

function run_multi($multi): void
{
    do {
        $status = curl_multi_exec($multi, $active);

        if ($active) {
            curl_multi_select($multi, 0.1);
        }
    } while ($active && $status === CURLM_OK);
}

function percentile(array $values, int $percentile): float
{
    if ($values === []) {
        return 0.0;
    }

    sort($values);
    $index = (int) ceil(($percentile / 100) * count($values)) - 1;

    return (float) $values[max(0, min(count($values) - 1, $index))];
}

function parse_playlist(string $body, string $baseUrl): array
{
    $segments = [];
    $sequence = 0;
    $target = 0.0;

    foreach (preg_split('/\r?\n/', $body) ?: [] as $raw) {
        $line = trim($raw);

        if ($line === '') {
            continue;
        }

        if (str_starts_with($line, '#EXT-X-MEDIA-SEQUENCE:')) {
            $sequence = (int) substr($line, 22);
            continue;
        }

        if (str_starts_with($line, '#EXT-X-TARGETDURATION:')) {
            $target = (float) substr($line, 22);
            continue;
        }

        if ($line[0] === '#') {
            continue;
        }

        $segments[] = absolute_url($line, $baseUrl);
    }

    return [
        'segments' => $segments,
        'sequence' => $sequence,
        'target' => $target,
    ];
}

function absolute_url(string $uri, string $baseUrl): string
{
    if (preg_match('#^https?://#i', $uri) === 1) {
        return $uri;
    }

    $base = explode('?', $baseUrl)[0];

    if (str_starts_with($uri, '/')) {
        preg_match('#^(https?://[^/]+)#i', $base, $matches);

        return ($matches[1] ?? '') . $uri;
    }

    return substr($base, 0, (int) strrpos($base, '/') + 1) . $uri;
}

function load_metrics(string $base, string $key): ?array
{
    if ($key === '') {
        return null;
    }

    $raw = @file_get_contents(
        $base . '/api/metrics?key=' . rawurlencode($key)
    );

    if (!is_string($raw)) {
        return null;
    }

    $data = json_decode($raw, true);

    return is_array($data['metrics'] ?? null) ? $data['metrics'] : null;
}

function load_origin_counters(string $originBase): array
{
    if ($originBase === '') {
        return [];
    }

    $raw = @file_get_contents(rtrim($originBase, '/') . '/control');

    if (!is_string($raw)) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data['counters'] ?? null) ? $data['counters'] : [];
}
