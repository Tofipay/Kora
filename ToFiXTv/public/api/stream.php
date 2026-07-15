<?php
declare(strict_types=1);

/**
 * Stream proxy — direct-execution entry point (/api/stream.php).
 * Also available as the front-controller route /stream (works on any config).
 * All logic lives in TofiXTv\Core\StreamProxy so both paths behave identically.
 */

require dirname(__DIR__, 2) . '/app/config.php';
require APP_DIR . '/Core/StreamProxy.php';

\TofiXTv\Core\StreamProxy::serve();
