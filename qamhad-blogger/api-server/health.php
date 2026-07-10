<?php
/** GET /health.php — lightweight liveness probe (no upstream calls). */
require_once __DIR__ . '/_bootstrap.php';

$writable = is_writable(STORAGE_DIR) || @mkdir(STORAGE_DIR, 0755, true);

api_out([
    'service'   => 'qamhad-api',
    'status'    => $writable ? 'ok' : 'degraded',
    'php'       => PHP_VERSION,
    'time'      => date('c'),
    'writable'  => (bool)$writable,
], 0);
