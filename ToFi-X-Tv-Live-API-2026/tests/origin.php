<?php
declare(strict_types=1);

/**
 * origin.php — مصدر HLS وهمي للاختبار المحلي فقط.
 *
 * يُشغّل هكذا:
 *      php -S 127.0.0.1:8801 -t tests tests/origin.php
 *
 * يحاكي مصدرًا حيًّا حقيقيًا:
 *   القناة 10 / 20 / 30 : بث TS عادي (نافذة 6 مقاطع، مدة المقطع ثانيتان).
 *   القناة 66           : روابط مقاطع بتوكن مصدر طويل جدًا.
 *   القناة 77           : Master Playlist بقوائم داخلية.
 *   القناة 88           : fMP4 مع ‎#EXT-X-MAP‎ ومقاطع ‎.m4s‎.
 *   القناة 99           : مشفّرة بـ ‎#EXT-X-KEY‎.
 *
 * ويعدّ كل طلب فعلي وصل إليه، حتى نثبت أن 50 مشاهدًا لا يسببون 50 طلبًا.
 */

const SEGMENT_DURATION = 2.0;
const WINDOW_SIZE = 6;

$stateFile = __DIR__ . '/.origin-state.json';
$countFile = __DIR__ . '/.origin-counters.json';

$path = (string) parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);

/* ───────────── لوحة التحكم بالاختبار ───────────── */

if (str_starts_with($path, '/control')) {
    $state = origin_state($stateFile);

    if (isset($_GET['fail'])) {
        $state['fail_until'] = time() + max(0, (int) $_GET['fail']);
    }

    if (isset($_GET['slow'])) {
        /* مصدر بطيء جدًا (لا يرد بخطأ سريع) — يختبر إعادة حساب عمر الكاش. */
        $state['slow_until'] = time() + max(0, (int) $_GET['slow']);
    }

    if (isset($_GET['variant'])) {
        $variant = (string) $_GET['variant'] === 'b' ? 'b' : 'a';

        if ($variant !== $state['variant']) {
            $state['variant'] = $variant;
            $state['switched_at'] = time();
        }
    }

    if (isset($_GET['reset'])) {
        $state = [
            'fail_until' => 0,
            'slow_until' => 0,
            'variant' => 'a',
            'switched_at' => 0,
        ];
        origin_write($countFile, []);
    }

    origin_write($stateFile, $state);

    header('Content-Type: application/json');
    echo json_encode([
        'state' => $state,
        'counters' => origin_state($countFile, []),
        'now' => time(),
    ], JSON_PRETTY_PRINT);

    return;
}

/* ───────────── المصدر ───────────── */

if (
    preg_match(
        '#^/live/([A-Za-z0-9_]+)/([A-Za-z0-9_]+)/(.+)$#',
        $path,
        $matches
    ) !== 1
) {
    http_response_code(404);
    echo 'not found';
    return;
}

$resource = (string) $matches[3];
$state = origin_state($stateFile);
$isPlaylist = str_ends_with($resource, '.m3u8');

origin_count($countFile, $isPlaylist ? 'playlists' : 'assets');
origin_count($countFile, 'resource:' . $resource);

if ($isPlaylist && (int) $state['fail_until'] > time()) {
    origin_count($countFile, 'failed');
    http_response_code(502);
    echo 'upstream down';
    return;
}

if ($isPlaylist && (int) ($state['slow_until'] ?? 0) > time()) {
    origin_count($countFile, 'slow');
    sleep(9);
}

$prefix = $state['variant'] === 'b' ? 'b-' : '';
$sequence = (int) floor(time() / SEGMENT_DURATION);

if ($state['variant'] === 'b') {
    /* محاكاة تبديل مصدر القناة: التسلسل يعود لرقم أقل بكثير. */
    $sequence -= 5000;
}

$first = $sequence - (WINDOW_SIZE - 1);

/* ───────────── القوائم ───────────── */

if ($resource === '77.m3u8') {
    header('Content-Type: application/vnd.apple.mpegurl');
    echo "#EXTM3U\n#EXT-X-VERSION:3\n"
        . "#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION=640x360\n"
        . "77-lo.m3u8\n"
        . "#EXT-X-STREAM-INF:BANDWIDTH=2500000,RESOLUTION=1280x720\n"
        . "77-hi.m3u8\n";
    return;
}

if (preg_match('/^(77-lo|77-hi)\.m3u8$/', $resource, $variant) === 1) {
    echo_media_playlist(
        $variant[1] . '-' . $prefix,
        'ts',
        $first,
        $sequence,
        null,
        null
    );
    return;
}

if ($resource === '88.m3u8') {
    echo_media_playlist(
        '88-' . $prefix,
        'm4s',
        $first,
        $sequence,
        '#EXT-X-MAP:URI="init88.mp4"',
        null
    );
    return;
}

if ($resource === '99.m3u8') {
    echo_media_playlist(
        '99-' . $prefix,
        'ts',
        $first,
        $sequence,
        null,
        '#EXT-X-KEY:METHOD=AES-128,URI="key99.bin",'
        . 'IV=0x00000000000000000000000000000001'
    );
    return;
}

if ($resource === '66.m3u8') {
    echo_media_playlist(
        '66-' . $prefix,
        'ts',
        $first,
        $sequence,
        null,
        null,
        '?token=' . str_repeat('T0k3n', 130)
    );
    return;
}

if (preg_match('/^([0-9]{1,6})\.m3u8$/', $resource, $channel) === 1) {
    echo_media_playlist(
        $channel[1] . '-' . $prefix,
        'ts',
        $first,
        $sequence,
        null,
        null
    );
    return;
}

/* ───────────── المقاطع والمفاتيح ───────────── */

if ($resource === 'key99.bin') {
    header('Content-Type: application/octet-stream');
    echo str_repeat("\x2a", 16);
    return;
}

if ($resource === 'init88.mp4') {
    header('Content-Type: video/mp4');
    echo "\x00\x00\x00\x18ftypiso5" . str_repeat("\x00", 200);
    return;
}

if (preg_match('/\.(ts|m4s)$/', $resource, $extension) === 1) {
    if ($extension[1] === 'ts') {
        header('Content-Type: video/mp2t');
        echo origin_transport_stream($resource);
        return;
    }

    header('Content-Type: video/iso.segment');
    echo "\x00\x00\x00\x18styp" . str_repeat("\x11", 4096);
    return;
}

http_response_code(404);
echo 'not found';

/* ───────────── أدوات ───────────── */

function echo_media_playlist(
    string $prefix,
    string $extension,
    int $first,
    int $last,
    ?string $mapLine,
    ?string $keyLine,
    string $query = ''
): void {
    header('Content-Type: application/vnd.apple.mpegurl');

    $lines = [
        '#EXTM3U',
        '#EXT-X-VERSION:' . ($extension === 'm4s' ? 7 : 3),
        '#EXT-X-TARGETDURATION:' . (int) ceil(SEGMENT_DURATION),
        '#EXT-X-MEDIA-SEQUENCE:' . $first,
    ];

    if ($keyLine !== null) {
        $lines[] = $keyLine;
    }

    if ($mapLine !== null) {
        $lines[] = $mapLine;
    }

    for ($index = $first; $index <= $last; $index++) {
        if ($index === $first) {
            $lines[] = '#EXT-X-PROGRAM-DATE-TIME:'
                . gmdate('Y-m-d\TH:i:s\Z', (int) ($index * SEGMENT_DURATION));
        }

        $lines[] = '#EXTINF:'
            . number_format(SEGMENT_DURATION, 3, '.', '') . ',';
        $lines[] = $prefix . $index . '.' . $extension . $query;
    }

    echo implode("\n", $lines) . "\n";
}

/**
 * مقطع MPEG-TS صالح فعلًا (حزم 188 بايت تبدأ بـ 0x47) حتى يمر فحص المحتوى.
 */
function origin_transport_stream(string $seed): string
{
    $packets = 40;
    $body = '';

    for ($index = 0; $index < $packets; $index++) {
        $packet = "\x47" . str_repeat(
            chr((crc32($seed . $index) % 251) + 1),
            187
        );
        $body .= $packet;
    }

    return $body;
}

function origin_state(string $file, array $default = [
    'fail_until' => 0,
    'slow_until' => 0,
    'variant' => 'a',
    'switched_at' => 0,
]): array {
    if (!is_file($file)) {
        return $default;
    }

    $data = json_decode((string) @file_get_contents($file), true);

    return is_array($data) ? $data + $default : $default;
}

function origin_write(string $file, array $data): void
{
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * عدّاد ذرّي: يثبت كم طلبًا فعليًا وصل المصدر مهما كان عدد المشاهدين.
 */
function origin_count(string $file, string $key): void
{
    $handle = @fopen($file, 'c+');

    if ($handle === false) {
        return;
    }

    if (flock($handle, LOCK_EX)) {
        $raw = stream_get_contents($handle);
        $data = json_decode((string) $raw, true);

        if (!is_array($data)) {
            $data = [];
        }

        $data[$key] = (int) ($data[$key] ?? 0) + 1;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
    }

    fclose($handle);
}
