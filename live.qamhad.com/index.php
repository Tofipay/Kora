<?php
/**
 * live.qamhad.com — PHP fallback redirect (safety net).
 *
 * Normally .htaccess answers every request with a one-hop 301 before PHP
 * runs. On hosts where mod_rewrite is unavailable or .htaccess is ignored,
 * this front controller performs the same permanent, path-preserving
 * redirect to the canonical domain, with an ASCII-safe Location header
 * (Arabic slugs are percent-encoded per RFC 3986).
 */
declare(strict_types=1);

$target = 'https://www.qamhad.com';

$uri   = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path  = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
$query = (string)(parse_url($uri, PHP_URL_QUERY) ?? '');

// Percent-encode each already-decoded segment exactly once.
$segments = array_map(
    static fn(string $s): string => rawurlencode(rawurldecode($s)),
    explode('/', $path)
);
$location = $target . implode('/', $segments) . ($query !== '' ? '?' . $query : '');

header('Location: ' . $location, true, 301);
header('Cache-Control: public, max-age=86400');
exit;
