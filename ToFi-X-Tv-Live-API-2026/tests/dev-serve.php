<?php
declare(strict_types=1);

/**
 * dev-serve.php — تشغيل/إيقاف خوادم الاختبار المحلية يدويًا.
 *
 *      php tests/dev-serve.php start
 *      php tests/dev-serve.php stop
 */

$root = dirname(__DIR__);
$command = (string) ($argv[1] ?? 'start');
$pidFile = __DIR__ . '/.dev-serve.pids';

if ($command === 'stop') {
    foreach (explode("\n", (string) @file_get_contents($pidFile)) as $pid) {
        $pid = (int) trim($pid);

        if ($pid > 0) {
            @exec('kill ' . $pid . ' 2>/dev/null');
        }
    }

    @unlink($pidFile);
    echo "stopped\n";
    exit(0);
}

$environment = [
    'PHP_CLI_SERVER_WORKERS=16',
    'TOFI_SOURCE_BASE_URL=http://127.0.0.1:8801/live/Abuturki/Abuturki/',
    'TOFI_PUBLIC_BASE_URL=http://127.0.0.1:8802',
    'TOFI_ALLOWED_SOURCE_HOSTS=127.0.0.1',
    'TOFI_ALLOW_INSECURE_SOURCE=1',
    'TOFI_METRICS_ENABLED=1',
    'TOFI_METRICS_TOKEN=test-metrics-key',
    'TOFI_VIEWER_BACKEND=' . (string) ($argv[2] ?? 'file'),
];

$pids = [];

foreach (
    [
        [8801, $root . '/tests', $root . '/tests/origin.php', '/tmp/tofi-origin.log'],
        [8802, $root, $root . '/tests/router.php', '/tmp/tofi-proxy.log'],
    ] as [$port, $docroot, $router, $log]
) {
    $shell = 'env ' . implode(' ', array_map('escapeshellarg', $environment))
        . ' php -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($docroot)
        . ' ' . escapeshellarg($router)
        . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!';

    $pids[] = (int) trim((string) shell_exec($shell));
}

file_put_contents($pidFile, implode("\n", $pids));

for ($attempt = 0; $attempt < 50; $attempt++) {
    $handle = @fsockopen('127.0.0.1', 8802, $code, $message, 0.2);

    if (is_resource($handle)) {
        fclose($handle);
        echo "servers up: origin 8801, proxy 8802 (pids " . implode(',', $pids) . ")\n";
        exit(0);
    }

    usleep(200000);
}

echo "servers failed to start\n";
exit(1);
