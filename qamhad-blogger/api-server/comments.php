<?php
/**
 * Comments — first-party, file-backed, no database.
 *   GET  /comments.php?path=/match/123           → list approved comments
 *   POST /comments.php  {path, name, body}        → add a comment (sanitized)
 * All input is validated and HTML-escaped; only http(s)-safe text is stored.
 */
require_once __DIR__ . '/_bootstrap.php';

api_require_method(['GET', 'POST']);

$dir = SETTINGS_DIR . '/comments';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

/** Normalise a page key so it maps 1:1 to a storage file. */
function comments_key(string $path): string
{
    $path = '/' . ltrim(trim($path), '/');
    $path = preg_replace('#[^A-Za-z0-9/_\-]#', '', $path) ?? '';
    return mb_substr($path, 0, 120, 'UTF-8');
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'POST') {
    $raw  = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($raw)) $raw = $_POST;

    $path = comments_key((string)($raw['path'] ?? ''));
    $name = trim((string)($raw['name'] ?? ''));
    $body = trim((string)($raw['body'] ?? $raw['comment'] ?? ''));

    if ($path === '/' || $path === '') api_error('Missing path', [], 422);
    $name = mb_substr($name !== '' ? $name : 'زائر', 0, 40, 'UTF-8');
    $body = mb_substr($body, 0, 1000, 'UTF-8');
    if (mb_strlen($body) < 2) api_error('Comment too short', [], 422);
    // Reject anything with markup or links to blunt XSS/spam.
    if (preg_match('#<|>|https?://#i', $body)) api_error('Invalid content', [], 422);

    $file = $dir . '/' . md5($path) . '.json';
    $fh = @fopen($file, 'c+');
    if (!$fh) api_error('Storage error', [], 500);
    @flock($fh, LOCK_EX);
    $list = json_decode(stream_get_contents($fh) ?: '[]', true);
    if (!is_array($list)) $list = [];

    $list[] = [
        'id'   => bin2hex(random_bytes(6)),
        'name' => e($name),
        'body' => e($body),
        'at'   => date('c'),
    ];
    if (count($list) > 500) $list = array_slice($list, -500);

    @ftruncate($fh, 0);
    @rewind($fh);
    @fwrite($fh, json_encode($list, JSON_UNESCAPED_UNICODE));
    @flock($fh, LOCK_UN);
    @fclose($fh);

    api_out(['saved' => true, 'total' => count($list)], 0);
}

/* GET */
$path = comments_key((string)($_GET['path'] ?? ''));
if ($path === '/' || $path === '') api_error('Missing path', [], 422);
$file = $dir . '/' . md5($path) . '.json';
$list = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
if (!is_array($list)) $list = [];
$list = array_reverse($list); // newest first
api_out(['path' => $path, 'comments' => $list], 30);
