<?php
declare(strict_types=1);

/**
 * test-worker.php — يثبت أن وضع worker يحمّل المقاطع قبل طلب المشاهدين.
 *
 * الخطوات:
 *   1) يشغّل worker.php على القناة 10 لعدة ثوانٍ.
 *   2) يصفّر عدّادات المصدر.
 *   3) يدخل مشاهد جديد ويطلب القائمة وكل مقاطعها.
 *   4) يتحقق أن المصدر لم يُطلب منه أي مقطع (كلها جاهزة مسبقًا).
 *
 * يتطلب تشغيل خوادم الاختبار أولًا:  php tests/dev-serve.php start
 */

$root = dirname(__DIR__);
$proxy = 'http://127.0.0.1:8802';
$origin = 'http://127.0.0.1:8801';

$environment = 'env '
    . 'TOFI_SOURCE_BASE_URL=' . escapeshellarg($origin . '/live/Abuturki/Abuturki/') . ' '
    . 'TOFI_PUBLIC_BASE_URL=' . escapeshellarg($proxy) . ' '
    . 'TOFI_ALLOWED_SOURCE_HOSTS=127.0.0.1 '
    . 'TOFI_ALLOW_INSECURE_SOURCE=1 '
    . 'TOFI_MODE=worker ';

$pid = (int) trim((string) shell_exec(
    $environment . 'php ' . escapeshellarg($root . '/worker.php')
    . ' --channels=10 > /tmp/tofi-worker.log 2>&1 & echo $!'
));

echo "== اختبار وضع worker ==\n";
echo "  تشغيل worker (pid " . $pid . ") لمدة 8 ثوانٍ…\n";

sleep(8);

/* بعد أن ملأ الـ worker النافذة، يدخل مشاهد جديد. */
file_get_contents($origin . '/control?reset=1');

$token = json_decode((string) file_get_contents_ua(
    $proxy . '/api/token/10',
    'MTX Player'
), true);

$playlist = (string) file_get_contents((string) $token['url']);

preg_match_all(
    '#https?://\S+/hls-cache/[a-f0-9]{40}\.ts#',
    $playlist,
    $matches
);

$statuses = [];

foreach ($matches[0] as $segment) {
    $context = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 10],
    ]);
    file_get_contents($segment, false, $context);
    $statuses[] = (string) ($http_response_header[0] ?? '');
}

$counters = json_decode(
    (string) file_get_contents($origin . '/control'),
    true
)['counters'] ?? [];

if ($pid > 0) {
    exec('kill ' . $pid . ' 2>/dev/null');
}

$segmentFetches = (int) ($counters['assets'] ?? 0);
$playlistFetches = (int) ($counters['playlists'] ?? 0);
$failed = 0;

echo "  عدد المقاطع في القائمة: " . count($matches[0]) . "\n";
echo "  طلبات المصدر للمقاطع بعد دخول المشاهد: " . $segmentFetches . "\n";
echo "  طلبات المصدر للقوائم بعد دخول المشاهد: " . $playlistFetches . "\n";

if (count($matches[0]) < 6) {
    echo "  ✗ النافذة المحلية لم تمتلئ\n";
    $failed++;
} else {
    echo "  ✓ النافذة المحلية جاهزة (" . count($matches[0]) . " مقاطع)\n";
}

/*
 * المقاطع القديمة في النافذة يجب أن تكون كلها جاهزة. يُسمح بمقطع واحد فقط:
 * المقطع الذي وُلد عند المصدر في اللحظة نفسها، وأول من يجلبه (worker أو
 * الطلب) يجلبه مرة واحدة للجميع.
 */
$preloaded = count($matches[0]) - $segmentFetches;

if ($segmentFetches <= 1) {
    echo "  ✓ " . $preloaded . " من " . count($matches[0])
        . " مقاطع كانت محمّلة مسبقًا قبل دخول المشاهد\n";
} else {
    echo "  ✗ المصدر طُلب منه " . $segmentFetches . " مقاطع\n";
    $failed++;
}

foreach ($statuses as $status) {
    if (!str_contains($status, ' 200') && !str_contains($status, ' 302')) {
        echo "  ✗ استجابة غير متوقعة: " . $status . "\n";
        $failed++;
        break;
    }
}

if ($failed === 0) {
    echo "  ✓ كل المقاطع سُلّمت بنجاح\n";
}

echo $failed === 0 ? "\nناجح\n" : "\nفاشل\n";
exit($failed === 0 ? 0 : 1);

function file_get_contents_ua(string $url, string $userAgent): string
{
    $context = stream_context_create([
        'http' => [
            'header' => 'User-Agent: ' . $userAgent . "\r\n",
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    return (string) file_get_contents($url, false, $context);
}
