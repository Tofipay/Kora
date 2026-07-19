<?php

/**
 * cron/supervisor.php
 * -----------------------------------------------------------------------------
 * سكربت CLI يُشغَّل عبر Cron لمراقبة البثوث وإعادة تشغيلها تلقائيًا.
 *
 * التثبيت في crontab (كل دقيقة):
 *   * * * * * /usr/bin/php /path/to/tofix-stream/cron/supervisor.php >> /path/to/tofix-stream/logs/cron.log 2>&1
 *
 * @package ToFiXStream\Cron
 */

declare(strict_types=1);

// يُسمح بالتشغيل من سطر الأوامر فقط.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\StreamSupervisor;

$supervisor = new StreamSupervisor();
$report = $supervisor->tick();

echo '[' . date('Y-m-d H:i:s') . "] Supervisor tick — " . count($report) . " channel(s)\n";
foreach ($report as $line) {
    printf("  - %-28s status=%-8s action=%s\n",
        $line['name'] ?? $line['id'],
        $line['status'] ?? '-',
        $line['action'] ?? 'none'
    );
}
