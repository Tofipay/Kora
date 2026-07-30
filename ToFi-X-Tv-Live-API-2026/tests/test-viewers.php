<?php
declare(strict_types=1);

/**
 * test-viewers.php — اختبار مخازن إحصاء المتصلين الأربعة.
 *
 *      php tests/test-viewers.php redis
 *      php tests/test-viewers.php sqlite
 *      php tests/test-viewers.php file
 *      php tests/test-viewers.php apcu
 *
 * يتحقق من: التسجيل، العدّ، تحديد معدّل الكتابة، الحذف الفوري (leave).
 */

$backend = (string) ($argv[1] ?? 'file');
putenv('TOFI_VIEWER_BACKEND=' . $backend);

require_once dirname(__DIR__) . '/viewers.php';

$passed = 0;
$failed = 0;

function ok(string $label, bool $condition, string $note = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo '  ✓ ' . $label . ($note !== '' ? '  (' . $note . ')' : '') . "\n";
        return;
    }

    $failed++;
    echo '  ✗ ' . $label . ($note !== '' ? '  (' . $note . ')' : '') . "\n";
}

$active = viewer_backend_name();
echo "\n== مخزن الإحصاء: " . $active . " (المطلوب: " . $backend . ") ==\n";

if ($active !== $backend && $backend !== 'auto') {
    echo "  ⓘ المخزن المطلوب غير متوفّر على هذا الخادم، تم التدرّج إلى "
        . $active . "\n";
}

$channel = 90000 + random_int(1, 900);
$viewers = [];

for ($index = 0; $index < 25; $index++) {
    $viewers[] = viewer_new_id();
}

foreach ($viewers as $viewerId) {
    viewer_touch($viewerId, $channel);
}

$stats = viewer_stats($channel);
ok('تسجيل 25 مشاهدًا', (int) $stats['selected_count'] === 25,
    'العدد: ' . (int) $stats['selected_count']);
ok('الإجمالي يشمل القناة', (int) $stats['total'] >= 25);
ok('القناة تظهر في التوزيع', $stats['channels'] !== []);

/* تحديد معدّل الكتابة: تكرار التجديد فورًا يجب ألا يكتب مرة أخرى. */
$sample = $viewers[0];
$writesBefore = viewer_write_probe($channel, $sample);

for ($index = 0; $index < 20; $index++) {
    viewer_touch($sample, $channel);
}

$writesAfter = viewer_write_probe($channel, $sample);
ok(
    '20 تجديدًا متتاليًا لا تُنتج كتابة جديدة (rate limit يعمل)',
    $writesBefore === $writesAfter,
    'قبل=' . $writesBefore . ' بعد=' . $writesAfter
);

viewer_forget($sample, $channel);
$stats = viewer_stats($channel);
ok('leave يُنقص العدد فورًا', (int) $stats['selected_count'] === 24,
    'العدد: ' . (int) $stats['selected_count']);

foreach ($viewers as $viewerId) {
    viewer_forget($viewerId, $channel);
}

$stats = viewer_stats($channel);
ok('تنظيف الجلسات يعمل', (int) $stats['selected_count'] === 0);

/*
 * مهم للاستضافة المشتركة: الوضع التلقائي يجب ألا يجرّب Redis إطلاقًا،
 * حتى لو كان يعمل على الجهاز. تجربته تعني اتصال TCP في كل طلب.
 */
if ($backend === 'auto') {
    ok(
        'الوضع التلقائي لا يجرّب Redis (يتجنّب اتصال TCP في كل طلب)',
        $active !== 'redis',
        'اختار: ' . $active
    );
}

ok('معرّف غير صالح يُتجاهل بهدوء', (static function () use ($channel): bool {
    try {
        viewer_touch('not-a-valid-id', $channel);
        viewer_forget('not-a-valid-id', $channel);

        return true;
    } catch (Throwable $error) {
        return false;
    }
})());

echo sprintf("\nناجح: %d   فاشل: %d\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);

/**
 * بصمة تُظهر ما إذا كانت الكتابة قد تمت فعلًا (وقت انتهاء الجلسة المخزّن).
 */
function viewer_write_probe(int $channel, string $viewerId): string
{
    switch (viewer_backend_name()) {
        case 'redis':
            $redis = viewer_redis();

            return (string) ($redis !== null
                ? $redis->zScore(viewer_redis_key($channel), $viewerId)
                : '');

        case 'apcu':
            return (string) apcu_fetch('tofi_v_' . $channel . '_' . $viewerId);

        case 'sqlite':
            $pdo = viewer_sqlite();

            if ($pdo === null) {
                return '';
            }

            $statement = $pdo->prepare(
                'SELECT expires_at FROM viewers WHERE viewer = :viewer '
                . 'AND channel = :channel'
            );
            $statement->execute([
                ':viewer' => $viewerId,
                ':channel' => $channel,
            ]);

            return (string) $statement->fetchColumn();

        default:
            $file = viewer_channel_directory($channel) . '/' . $viewerId . '.live';
            clearstatcache(true, $file);

            return (string) @filemtime($file);
    }
}
