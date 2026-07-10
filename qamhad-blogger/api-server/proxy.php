<?php
/**
 * ============================================================================
 *  Generic proxy dispatcher.
 * ============================================================================
 *  A single entry point that routes to any whitelisted endpoint by name:
 *      /proxy.php?endpoint=matches&date=2026-07-10&lang=ar
 *      /proxy.php?endpoint=match&id=123
 *  Handy where the host only allows one PHP file to be public, or to keep the
 *  template's base path constant. Directory-traversal safe (whitelist only).
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

require_once __DIR__ . '/router.php';

$name = (string)($_GET['endpoint'] ?? $_GET['e'] ?? '');
if ($name === '') {
    require_once __DIR__ . '/config.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'ok'    => true,
        'usage' => '/proxy.php?endpoint=<name>&<params>',
        'names' => array_keys(api_routes()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

api_dispatch($name);
