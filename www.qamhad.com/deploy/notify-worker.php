<?php
/**
 * Qamhad Live — notification worker (run from cron every minute):
 *   * * * * * php /path/to/site/deploy/notify-worker.php >/dev/null 2>&1
 *
 * Detects match events (kick-off, goals, half time, full time) by diffing
 * today's fixtures against the previous run, then broadcasts through the FCM
 * HTTP v1 API using the Service Account uploaded in the admin panel (OAuth2
 * Bearer token — no legacy server key).
 *
 * On shared hosting where PHP-CLI cron is awkward, use the URL trigger instead:
 *   wget -q -O /dev/null "https://live.qamhad.com/cron/notify?key=THE_KEY"
 * (the key is shown in Admin → Notifications). Both paths run the same scan.
 */
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'live.qamhad.com';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
require dirname(__DIR__) . '/app/config.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Qamhad\\')) {
        $rel = str_replace(['Qamhad\\', '\\'], ['', '/'], $class);
        $file = APP_DIR . '/' . $rel . '.php';
        if (is_file($file)) require $file;
    }
});

use Qamhad\Core\Lang;
use Qamhad\Core\Notifier;

Lang::boot('ar');
require APP_DIR . '/helpers.php';

$r = Notifier::scanAndBroadcast();

// Brief, cron-log-friendly line.
fwrite(STDOUT, '[' . date('c') . '] '
    . ($r['ok'] ? "events={$r['events']} sent={$r['sent']}" : ('skipped: ' . ($r['skipped'] ?? '')))
    . "\n");
