<?php
/**
 * ToFi X Tv — عدّاد الزيارات والتحميلات
 * يخزّن الأعداد في ملف counts.json بجانب هذا الملف.
 *
 * الاستخدام:
 *   counter.php?action=visit     → يزيد عدّاد الزيارات ويرجع الأعداد
 *   counter.php?action=download  → يزيد عدّاد التحميلات ويرجع الأعداد
 *   counter.php?action=get       → يرجع الأعداد فقط دون زيادة
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$file   = __DIR__ . '/counts.json';
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

$fp = @fopen($file, 'c+');
if ($fp === false) {
    http_response_code(500);
    echo json_encode(['error' => 'cannot open counts.json']);
    exit;
}

flock($fp, LOCK_EX);
$raw  = stream_get_contents($fp);
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = ['visits' => 0, 'downloads' => 0];
}
$data['visits']    = isset($data['visits'])    ? (int) $data['visits']    : 0;
$data['downloads'] = isset($data['downloads']) ? (int) $data['downloads'] : 0;

if ($action === 'visit') {
    $data['visits']++;
} elseif ($action === 'download') {
    $data['downloads']++;
}

if ($action === 'visit' || $action === 'download') {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
}

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode($data);
