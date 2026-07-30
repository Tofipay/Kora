<?php
declare(strict_types=1);

/**
 * ToFi X Tv — Channels Control Center
 *
 * The panel manages channel overrides, reusable HLS templates and live viewer
 * presence. Playback, token issuance and the upstream proxy remain independent.
 */

require __DIR__ . '/override.php';

$viewerLibrary = __DIR__ . '/viewers.php';
if (is_file($viewerLibrary)) {
    require_once $viewerLibrary;
}

$secureCookie = (($_SERVER['HTTPS'] ?? '') === 'on')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Strict',
]);
session_start();

const PANEL_MAX_UPLOAD = 200 * 1024 * 1024;

/* ───────────────────────── Common helpers ───────────────────────── */

function panel_csrf(): string
{
    if (empty($_SESSION['ovr_csrf'])) {
        $_SESSION['ovr_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['ovr_csrf'];
}

function panel_check_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');

    if (
        $token === ''
        || !hash_equals(
            (string) ($_SESSION['ovr_csrf'] ?? ''),
            $token
        )
    ) {
        panel_redirect(
            panel_message_url(
                'انتهت الجلسة، حاول مرة أخرى',
                'err'
            )
        );
    }
}

function panel_redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function panel_message_url(
    string $message,
    string $type = 'ok',
    string $tab = 'channels'
): string {
    return 'panel.php?tab=' . rawurlencode($tab)
        . '&msg=' . rawurlencode($message)
        . '&type=' . ($type === 'ok' ? 'ok' : 'err');
}

function panel_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );
    header('Pragma: no-cache');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function panel_is_auth(): bool
{
    return !empty($_SESSION['ovr_auth']);
}

function panel_receive_upload(string $field): array
{
    $file = $_FILES[$field] ?? null;

    if (!is_array($file)) {
        throw new RuntimeException('اختر ملفًا للرفع');
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'فشل الرفع (رمز ' . $error
            . '). تحقق من حد الرفع في الاستضافة.'
        );
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > PANEL_MAX_UPLOAD) {
        throw new RuntimeException(
            'حجم الملف غير صالح أو يتجاوز 200 ميجابايت'
        );
    }

    $extension = strtolower(
        pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)
    );
    $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

    if ($extension === '') {
        throw new RuntimeException('امتداد الملف غير معروف');
    }

    ovr_ensure_dir(OVR_UPLOAD_DIR);
    $temporary = OVR_UPLOAD_DIR
        . '/up_' . bin2hex(random_bytes(10))
        . '.' . $extension;

    if (
        !move_uploaded_file(
            (string) ($file['tmp_name'] ?? ''),
            $temporary
        )
    ) {
        throw new RuntimeException('تعذّر حفظ الملف المرفوع');
    }

    return [$temporary, $extension];
}

function panel_valid_template_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $length = mb_strlen($name, 'UTF-8');

    if ($length < 2 || $length > 60) {
        throw new RuntimeException(
            'اسم القالب يجب أن يكون بين حرفين و60 حرفًا'
        );
    }

    if (ovr_template_name_exists($name)) {
        throw new RuntimeException(
            'يوجد قالب محفوظ بهذا الاسم، اختر اسمًا مختلفًا'
        );
    }

    return $name;
}

function panel_ingest_uploaded_media(
    string $mediaKey,
    string $temporary,
    string $extension
): array {
    $images = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'];
    $videos = ['mp4', 'mov', 'mkv', 'webm', 'm4v', 'avi', 'ts'];

    if ($extension === 'zip') {
        return ovr_import_hls_zip(
            $mediaKey,
            $temporary
        );
    }

    if (in_array($extension, $images, true)) {
        return ovr_ingest_media(
            $mediaKey,
            $temporary,
            'image'
        );
    }

    if (in_array($extension, $videos, true)) {
        return ovr_ingest_media(
            $mediaKey,
            $temporary,
            'video'
        );
    }

    throw new RuntimeException(
        'النوع غير مدعوم. المسموح: صورة أو فيديو أو حزمة HLS بصيغة ZIP.'
    );
}

function panel_template_usage(string $key, array $channels): int
{
    $usage = 0;

    foreach ($channels as $channel) {
        if (
            is_array($channel)
            && ($channel['source'] ?? '') === 'preset'
            && ($channel['preset'] ?? '') === $key
        ) {
            $usage++;
        }
    }

    return $usage;
}

function panel_media_summary(string $key): array
{
    try {
        $directory = ovr_media_dir_for($key);
    } catch (Throwable $error) {
        return ['segments' => 0, 'duration' => 0, 'size' => 0];
    }

    $meta = [];
    $metaFile = $directory . '/meta.json';

    if (is_file($metaFile)) {
        $decoded = json_decode(
            (string) @file_get_contents($metaFile),
            true
        );
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $segments = is_array($meta['segments'] ?? null)
        ? count($meta['segments'])
        : 0;
    $duration = 0.0;

    foreach ($meta['segments'] ?? [] as $segment) {
        $duration += (float) ($segment[1] ?? 0);
    }

    $size = 0;
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            $size += (int) @filesize($file);
        }
    }

    return [
        'segments' => $segments,
        'duration' => (int) round($duration),
        'size' => $size,
    ];
}

function panel_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

/* ───────────────────────── Request actions ───────────────────────── */

$data = ovr_load();
$hasPassword = is_string($data['admin'] ?? null)
    && $data['admin'] !== '';
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'viewer_stats') {
    if (!panel_is_auth()) {
        panel_json(
            ['success' => false, 'message' => 'انتهت جلسة الدخول'],
            401
        );
    }

    $selected = null;
    $selectedRaw = trim((string) ($_GET['channel'] ?? ''));

    if ($selectedRaw !== '') {
        if (
            preg_match('/^[1-9][0-9]{0,5}$/', $selectedRaw) !== 1
        ) {
            panel_json(
                ['success' => false, 'message' => 'رقم القناة غير صالح'],
                422
            );
        }
        $selected = (int) $selectedRaw;
    }

    try {
        $stats = function_exists('viewer_stats')
            ? viewer_stats($selected)
            : [
                'total' => 0,
                'active_channels' => 0,
                'channels' => [],
                'selected_channel' => $selected,
                'selected_count' => $selected !== null ? 0 : null,
                'lease_seconds' => 0,
                'updated_at' => time(),
            ];

        panel_json(['success' => true] + $stats);
    } catch (Throwable $error) {
        error_log('[ToFi Viewer Dashboard] ' . $error->getMessage());
        panel_json(
            ['success' => false, 'message' => 'تعذّر قراءة الإحصاء الآن'],
            500
        );
    }
}

if (
    $action === 'setup'
    && !$hasPassword
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($password, 'UTF-8') < 8) {
        panel_redirect(
            panel_message_url(
                'كلمة المرور يجب ألا تقل عن 8 أحرف',
                'err'
            )
        );
    }

    if ($password !== $confirmation) {
        panel_redirect(
            panel_message_url(
                'كلمتا المرور غير متطابقتين',
                'err'
            )
        );
    }

    $data['admin'] = password_hash($password, PASSWORD_DEFAULT);
    ovr_save($data);
    session_regenerate_id(true);
    $_SESSION['ovr_auth'] = true;

    panel_redirect(
        panel_message_url('تم تجهيز لوحة التحكم بنجاح')
    );
}

if (
    $action === 'login'
    && $hasPassword
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $password = (string) ($_POST['password'] ?? '');

    if (password_verify($password, (string) $data['admin'])) {
        session_regenerate_id(true);
        $_SESSION['ovr_auth'] = true;
        panel_redirect(panel_message_url('مرحبًا بك'));
    }

    usleep(600000);
    panel_redirect(
        panel_message_url('كلمة المرور غير صحيحة', 'err')
    );
}

if (
    $action === 'logout'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $_SESSION = [];
    session_destroy();
    panel_redirect('panel.php');
}

if (
    $action === 'passwd'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $current = (string) ($_POST['current'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password2'] ?? '');

    if (!password_verify($current, (string) $data['admin'])) {
        panel_redirect(
            panel_message_url(
                'كلمة المرور الحالية غير صحيحة',
                'err',
                'settings'
            )
        );
    }

    if (
        mb_strlen($password, 'UTF-8') < 8
        || $password !== $confirmation
    ) {
        panel_redirect(
            panel_message_url(
                'كلمة المرور الجديدة غير صالحة أو غير متطابقة',
                'err',
                'settings'
            )
        );
    }

    $data['admin'] = password_hash($password, PASSWORD_DEFAULT);
    ovr_save($data);
    panel_redirect(
        panel_message_url(
            'تم تغيير كلمة المرور',
            'ok',
            'settings'
        )
    );
}

if (
    $action === 'save'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();

    $channel = (int) ($_POST['channel'] ?? 0);
    $note = mb_substr(
        trim((string) ($_POST['note'] ?? '')),
        0,
        80,
        'UTF-8'
    );
    $enabled = !empty($_POST['enabled']);
    $templateChoice = (string) ($_POST['tmpl'] ?? '_default');
    $saveAsTemplate = !empty($_POST['save_template']);
    $createdTemplateKey = null;
    $temporary = null;

    if ($channel < 1 || $channel > 999999) {
        panel_redirect(
            panel_message_url('رقم القناة غير صالح', 'err')
        );
    }

    $until = 0;
    $untilRaw = trim((string) ($_POST['until'] ?? ''));
    if ($untilRaw !== '') {
        $timestamp = strtotime($untilRaw);
        if ($timestamp === false) {
            panel_redirect(
                panel_message_url('موعد العودة غير صالح', 'err')
            );
        }
        $until = $timestamp;
    }

    $presets = ovr_presets();
    $source = $templateChoice === 'custom' ? 'custom' : 'preset';
    $preset = null;

    if ($source === 'preset') {
        $preset = array_key_exists($templateChoice, $presets)
            ? $templateChoice
            : OVR_DEFAULT_KEY;
    }

    try {
        $workingData = ovr_load();
        $uploadName = trim((string) (
            $_FILES['media']['name'] ?? ''
        ));
        $hasUpload = $uploadName !== '';

        if ($saveAsTemplate && $source !== 'custom') {
            throw new RuntimeException(
                'اختر رفع وسائط مخصّصة أولًا لحفظها كقالب'
            );
        }

        if ($saveAsTemplate && !$hasUpload) {
            throw new RuntimeException(
                'ارفع حزمة HLS أو وسائط ثم فعّل حفظها كقالب'
            );
        }

        if ($source === 'custom' && $hasUpload) {
            $templateName = '';
            $targetKey = (string) $channel;

            if ($saveAsTemplate) {
                $templateName = panel_valid_template_name(
                    (string) ($_POST['template_name'] ?? '')
                );
                $createdTemplateKey = ovr_new_template_key();
                $targetKey = $createdTemplateKey;
            }

            [$temporary, $extension] = panel_receive_upload('media');
            $meta = panel_ingest_uploaded_media(
                $targetKey,
                $temporary,
                $extension
            );

            @unlink($temporary);
            $temporary = null;

            if ($saveAsTemplate && $createdTemplateKey !== null) {
                $workingData['templates'][$createdTemplateKey] = [
                    'name' => $templateName,
                    'kind' => (string) ($meta['kind'] ?? 'hls'),
                    'created' => time(),
                ];
                $source = 'preset';
                $preset = $createdTemplateKey;
            }
        }

        if (
            $source === 'custom'
            && !is_file(
                ovr_media_dir_for((string) $channel)
                . '/meta.json'
            )
        ) {
            throw new RuntimeException(
                'لا توجد وسائط لهذه القناة. ارفع صورة أو فيديو أو حزمة HLS.'
            );
        }

        $workingData['channels'][$channel] = [
            'enabled' => $enabled,
            'source' => $source,
            'preset' => $preset,
            'until' => $until,
            'note' => $note,
            'updated' => time(),
        ];

        ovr_save($workingData);

        $message = $createdTemplateKey !== null
            ? 'تم حفظ القناة والقالب الجديد بنجاح'
            : 'تم حفظ إعداد القناة ' . $channel;

        panel_redirect(panel_message_url($message));
    } catch (Throwable $error) {
        if (is_string($temporary)) {
            @unlink($temporary);
        }

        if ($createdTemplateKey !== null) {
            try {
                ovr_rrmdir(ovr_media_dir_for($createdTemplateKey));
            } catch (Throwable $cleanupError) {
                error_log(
                    '[ToFi Template Cleanup] '
                    . $cleanupError->getMessage()
                );
            }
        }

        panel_redirect(
            panel_message_url($error->getMessage(), 'err')
        );
    }
}

if (
    $action === 'create_template'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $createdTemplateKey = null;
    $temporary = null;

    try {
        $name = panel_valid_template_name(
            (string) ($_POST['template_name'] ?? '')
        );
        [$temporary, $extension] = panel_receive_upload('template_zip');

        if ($extension !== 'zip') {
            throw new RuntimeException(
                'مكتبة القوالب تقبل حزمة HLS بصيغة ZIP فقط'
            );
        }

        $createdTemplateKey = ovr_new_template_key();
        $meta = ovr_import_hls_zip(
            $createdTemplateKey,
            $temporary
        );

        @unlink($temporary);
        $temporary = null;

        $workingData = ovr_load();
        $workingData['templates'][$createdTemplateKey] = [
            'name' => $name,
            'kind' => (string) ($meta['kind'] ?? 'hls'),
            'created' => time(),
        ];
        ovr_save($workingData);

        panel_redirect(
            panel_message_url(
                'تم حفظ القالب «' . $name . '»',
                'ok',
                'templates'
            )
        );
    } catch (Throwable $error) {
        if (is_string($temporary)) {
            @unlink($temporary);
        }

        if ($createdTemplateKey !== null) {
            try {
                ovr_rrmdir(ovr_media_dir_for($createdTemplateKey));
            } catch (Throwable $cleanupError) {
                error_log(
                    '[ToFi Template Cleanup] '
                    . $cleanupError->getMessage()
                );
            }
        }

        panel_redirect(
            panel_message_url(
                $error->getMessage(),
                'err',
                'templates'
            )
        );
    }
}

if (
    $action === 'delete_template'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $key = (string) ($_POST['template_key'] ?? '');
    $workingData = ovr_load();
    $template = $workingData['templates'][$key] ?? null;

    if (!is_array($template)) {
        panel_redirect(
            panel_message_url(
                'القالب غير موجود أو افتراضي ولا يمكن حذفه',
                'err',
                'templates'
            )
        );
    }

    $usage = panel_template_usage(
        $key,
        $workingData['channels'] ?? []
    );

    if ($usage > 0) {
        panel_redirect(
            panel_message_url(
                'القالب مستخدم في ' . $usage
                . ' قناة. غيّر قالب هذه القنوات قبل حذفه.',
                'err',
                'templates'
            )
        );
    }

    unset($workingData['templates'][$key]);
    ovr_save($workingData);
    ovr_rrmdir(ovr_media_dir_for($key));

    panel_redirect(
        panel_message_url(
            'تم حذف القالب المحفوظ',
            'ok',
            'templates'
        )
    );
}

if (
    $action === 'toggle'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $channel = (int) ($_POST['channel'] ?? 0);
    $workingData = ovr_load();

    if (isset($workingData['channels'][$channel])) {
        $workingData['channels'][$channel]['enabled'] = empty(
            $workingData['channels'][$channel]['enabled']
        );
        $workingData['channels'][$channel]['updated'] = time();
        ovr_save($workingData);
    }

    panel_redirect(
        panel_message_url('تم تحديث حالة القناة ' . $channel)
    );
}

if (
    $action === 'delete'
    && panel_is_auth()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    panel_check_csrf();
    $channel = (int) ($_POST['channel'] ?? 0);
    $workingData = ovr_load();
    unset($workingData['channels'][$channel]);
    ovr_save($workingData);
    ovr_delete_channel_media($channel);

    panel_redirect(
        panel_message_url('تم حذف إعداد القناة ' . $channel)
    );
}

/* ───────────────────────── View state ───────────────────────── */

$view = 'dashboard';
if (!$hasPassword) {
    $view = 'setup';
} elseif (!panel_is_auth()) {
    $view = 'login';
}

$notice = null;
if (isset($_GET['msg'])) {
    $notice = [
        'text' => (string) $_GET['msg'],
        'type' => (($_GET['type'] ?? '') === 'ok') ? 'ok' : 'err',
    ];
}

$allowedTabs = ['channels', 'templates', 'viewers', 'settings'];
$initialTab = (string) ($_GET['tab'] ?? 'channels');
if (!in_array($initialTab, $allowedTabs, true)) {
    $initialTab = 'channels';
}

$csrf = panel_csrf();
$ffmpegReady = ovr_ffmpeg_bin() !== null;
$data = ovr_load();
$channels = is_array($data['channels'] ?? null)
    ? $data['channels']
    : [];
ksort($channels, SORT_NUMERIC);

$presets = ovr_presets();
$builtins = ovr_builtin_presets();
$userTemplates = ovr_user_templates();
$activeOverrides = 0;

foreach ($channels as $channel) {
    if (
        !empty($channel['enabled'])
        && (
            empty($channel['until'])
            || time() < (int) $channel['until']
        )
    ) {
        $activeOverrides++;
    }
}
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark">
<title>مركز تحكم ToFi X Tv</title>
<style>
  :root{
    --bg:#05070d;
    --surface:#0b101a;
    --surface-2:#101725;
    --surface-3:#151e2e;
    --line:rgba(151,174,211,.14);
    --line-strong:rgba(151,174,211,.24);
    --text:#f4f7fb;
    --muted:#8f9db2;
    --muted-2:#65738a;
    --primary:#29b6f6;
    --primary-2:#2368ff;
    --primary-soft:rgba(41,182,246,.12);
    --success:#2dd4a2;
    --success-soft:rgba(45,212,162,.11);
    --danger:#fb5772;
    --danger-soft:rgba(251,87,114,.1);
    --warning:#f9c74f;
    --shadow:0 24px 70px rgba(0,0,0,.28);
    --radius:18px;
  }
  *{box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{
    margin:0;
    min-height:100vh;
    color:var(--text);
    background:
      radial-gradient(900px 520px at 82% -10%,rgba(35,104,255,.15),transparent 66%),
      radial-gradient(700px 480px at -10% 35%,rgba(41,182,246,.08),transparent 68%),
      var(--bg);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans Arabic","Noto Kufi Arabic",Tahoma,Arial,sans-serif;
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }
  button,input,select{font:inherit}
  button{color:inherit}
  a{color:inherit;text-decoration:none}
  .app{
    width:min(1440px,100%);
    margin:0 auto;
    padding:20px;
    display:grid;
    grid-template-columns:252px minmax(0,1fr);
    gap:20px;
  }
  .sidebar{
    position:sticky;
    top:20px;
    height:calc(100vh - 40px);
    padding:18px 14px;
    border:1px solid var(--line);
    border-radius:24px;
    background:rgba(9,14,23,.82);
    box-shadow:var(--shadow);
    backdrop-filter:blur(18px);
    display:flex;
    flex-direction:column;
  }
  .brand{
    display:flex;
    align-items:center;
    gap:12px;
    padding:5px 8px 22px;
    border-bottom:1px solid var(--line);
  }
  .brand-mark{
    width:44px;height:44px;border-radius:14px;
    display:grid;place-items:center;
    color:white;font-size:13px;font-weight:900;letter-spacing:-.5px;
    background:linear-gradient(145deg,var(--primary),var(--primary-2));
    box-shadow:0 12px 28px rgba(35,104,255,.28);
  }
  .brand-name{font-weight:850;font-size:16px;line-height:1.35}
  .brand-name small{display:block;color:var(--muted);font-size:11px;font-weight:600}
  .nav-label{
    color:var(--muted-2);
    font-size:10px;
    font-weight:800;
    letter-spacing:.08em;
    padding:22px 12px 8px;
  }
  .nav{display:grid;gap:6px}
  .nav-btn{
    width:100%;border:1px solid transparent;background:transparent;
    border-radius:13px;padding:11px 12px;cursor:pointer;
    display:flex;align-items:center;gap:11px;text-align:right;
    color:var(--muted);font-size:13.5px;font-weight:750;
    transition:.18s ease;
  }
  .nav-btn:hover{background:rgba(255,255,255,.035);color:var(--text)}
  .nav-btn.active{
    color:#dff6ff;border-color:rgba(41,182,246,.18);
    background:linear-gradient(90deg,rgba(41,182,246,.13),rgba(35,104,255,.08));
  }
  .nav-icon{
    width:29px;height:29px;border-radius:9px;display:grid;place-items:center;
    background:rgba(255,255,255,.05);font-size:13px;flex:none;
  }
  .nav-btn.active .nav-icon{background:rgba(41,182,246,.16)}
  .side-foot{margin-top:auto;padding:14px 8px 2px}
  .system-state{
    border:1px solid var(--line);border-radius:13px;padding:11px;
    background:rgba(255,255,255,.025);font-size:11.5px;color:var(--muted)
  }
  .state-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
  .state-dot,.live-dot{width:8px;height:8px;border-radius:50%;background:var(--success);box-shadow:0 0 0 5px rgba(45,212,162,.09)}
  .main{min-width:0}
  .topbar{
    min-height:74px;
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    margin-bottom:18px;padding:6px 4px;
  }
  .page-title h1{margin:0;font-size:clamp(21px,2.2vw,29px);letter-spacing:-.02em}
  .page-title p{margin:3px 0 0;color:var(--muted);font-size:12.5px}
  .top-actions{display:flex;align-items:center;gap:9px}
  .live-chip{
    display:inline-flex;align-items:center;gap:9px;padding:9px 12px;
    border:1px solid rgba(45,212,162,.2);border-radius:12px;
    background:var(--success-soft);color:#9af0d5;font-size:11.5px;font-weight:800;
  }
  .live-dot{width:7px;height:7px;animation:pulse 1.7s infinite}
  @keyframes pulse{50%{opacity:.45;box-shadow:0 0 0 8px transparent}}
  .panel-section{display:none}
  .panel-section.active{display:block;animation:sectionIn .22s ease}
  @keyframes sectionIn{from{opacity:.4;transform:translateY(5px)}}
  .metrics{
    display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;
    margin-bottom:16px;
  }
  .metric{
    min-height:112px;border:1px solid var(--line);border-radius:var(--radius);
    background:linear-gradient(145deg,rgba(16,23,37,.94),rgba(10,15,25,.9));
    padding:17px;position:relative;overflow:hidden;
  }
  .metric::after{
    content:"";position:absolute;width:90px;height:90px;border-radius:50%;
    background:var(--primary-soft);filter:blur(3px);inset-inline-end:-35px;top:-35px
  }
  .metric-label{color:var(--muted);font-size:11.5px;font-weight:700}
  .metric-value{font-size:28px;font-weight:900;margin-top:7px;line-height:1.1}
  .metric-meta{color:var(--muted-2);font-size:10.5px;margin-top:7px}
  .grid-2{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(290px,.65fr);gap:16px}
  .card{
    border:1px solid var(--line);border-radius:var(--radius);
    background:linear-gradient(145deg,rgba(16,23,37,.94),rgba(10,15,25,.92));
    box-shadow:0 16px 48px rgba(0,0,0,.14);
    padding:20px;margin-bottom:16px;min-width:0;
  }
  .card-head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:14px;
    margin-bottom:18px;
  }
  .card-title{font-size:15px;font-weight:850;margin:0}
  .card-sub{font-size:11.5px;color:var(--muted);margin:3px 0 0}
  .eyebrow{font-size:10px;color:var(--primary);font-weight:850;letter-spacing:.05em;margin-bottom:4px}
  .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}
  .field{min-width:0}
  .field.full{grid-column:1/-1}
  label.field-label{
    display:flex;align-items:center;justify-content:space-between;gap:8px;
    font-size:11.5px;color:#b6c0cf;font-weight:700;margin:0 2px 6px
  }
  .optional{color:var(--muted-2);font-size:10px;font-weight:600}
  input[type=text],input[type=number],input[type=password],input[type=datetime-local],select,input[type=file]{
    width:100%;min-height:45px;border:1px solid var(--line);
    border-radius:11px;background:rgba(4,8,14,.52);color:var(--text);
    padding:10px 12px;outline:none;transition:.16s ease;
  }
  select{cursor:pointer}
  select option,select optgroup{background:#101725;color:var(--text)}
  input::placeholder{color:#536176}
  input:focus,select:focus{
    border-color:rgba(41,182,246,.62);
    box-shadow:0 0 0 3px rgba(41,182,246,.08);
  }
  input[type=file]{padding:8px}
  input[type=file]::file-selector-button{
    border:0;border-radius:8px;padding:7px 12px;margin-inline-end:9px;
    background:var(--primary-soft);color:#bfeeff;font-weight:750;cursor:pointer;
  }
  .upload-box{
    grid-column:1/-1;border:1px dashed rgba(41,182,246,.3);
    border-radius:14px;padding:14px;background:rgba(41,182,246,.035)
  }
  .hint{font-size:10.5px;color:var(--muted);margin-top:7px}
  .warning{color:#f7d47e}
  .save-template-box{
    margin-top:12px;padding-top:12px;border-top:1px solid var(--line);
    display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,.8fr);gap:12px;align-items:end
  }
  .switch-line{display:flex;align-items:center;gap:9px;cursor:pointer;user-select:none}
  .switch-line input{display:none}
  .switch-track{width:40px;height:23px;border-radius:99px;background:#273043;position:relative;transition:.18s;flex:none}
  .switch-track::after{content:"";width:17px;height:17px;border-radius:50%;background:white;position:absolute;top:3px;inset-inline-start:3px;transition:.18s}
  .switch-line input:checked + .switch-track{background:linear-gradient(135deg,var(--primary),var(--primary-2))}
  .switch-line input:checked + .switch-track::after{inset-inline-start:20px}
  .form-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}
  .button{
    border:1px solid var(--line);border-radius:10px;background:rgba(255,255,255,.035);
    padding:9px 14px;min-height:40px;cursor:pointer;font-weight:800;font-size:12px;
    display:inline-flex;align-items:center;justify-content:center;gap:7px;transition:.16s;
  }
  .button:hover{border-color:var(--line-strong);background:rgba(255,255,255,.06)}
  .button.primary{border:0;background:linear-gradient(135deg,var(--primary),var(--primary-2));color:white;box-shadow:0 10px 25px rgba(35,104,255,.2)}
  .button.primary:hover{filter:brightness(1.08)}
  .button.danger:hover{border-color:rgba(251,87,114,.45);color:#ffafbd;background:var(--danger-soft)}
  .button.small{min-height:32px;padding:6px 10px;font-size:10.5px;border-radius:8px}
  .button.icon{width:40px;padding:0}
  .status-stack{display:grid;gap:9px}
  .status-item{
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:11px 12px;border:1px solid var(--line);border-radius:11px;
    background:rgba(255,255,255,.02);font-size:11.5px
  }
  .status-item span:first-child{color:var(--muted)}
  .badge{
    display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);
    border-radius:999px;padding:4px 8px;font-size:9.5px;font-weight:850;white-space:nowrap;
  }
  .badge.on{color:#9af0d5;border-color:rgba(45,212,162,.25);background:var(--success-soft)}
  .badge.off{color:#a7b1c0;background:rgba(255,255,255,.025)}
  .badge.blue{color:#bcecff;border-color:rgba(41,182,246,.24);background:var(--primary-soft)}
  .badge.warn{color:#f7d47e;border-color:rgba(249,199,79,.2);background:rgba(249,199,79,.07)}
  .dot{width:6px;height:6px;border-radius:50%;background:currentColor}
  .toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
  .search{position:relative;max-width:270px;width:100%}
  .search input{padding-inline-start:36px;min-height:39px}
  .search::before{content:"⌕";position:absolute;inset-inline-start:13px;top:7px;color:var(--muted);font-size:17px;z-index:2}
  .table-wrap{overflow:auto;border:1px solid var(--line);border-radius:13px}
  table{width:100%;border-collapse:collapse;min-width:760px;font-size:11.5px}
  th{text-align:right;color:var(--muted);font-size:10px;font-weight:800;padding:11px 13px;background:rgba(255,255,255,.025);border-bottom:1px solid var(--line);white-space:nowrap}
  td{padding:12px 13px;border-bottom:1px solid rgba(151,174,211,.08);vertical-align:middle}
  tbody tr:last-child td{border-bottom:0}
  tbody tr{transition:.14s}
  tbody tr:hover{background:rgba(255,255,255,.018)}
  .channel-id{font-size:17px;font-weight:900;color:#cfefff}
  .channel-note{color:var(--muted);font-size:9.5px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .empty{
    min-height:150px;display:grid;place-items:center;text-align:center;
    color:var(--muted);font-size:12px;padding:30px;
  }
  .empty strong{display:block;color:#c5cedb;font-size:13px;margin-bottom:3px}
  .notice{
    position:relative;border-radius:13px;padding:12px 15px;margin-bottom:14px;
    border:1px solid;font-size:11.5px;font-weight:750;
  }
  .notice.ok{color:#a3efd7;border-color:rgba(45,212,162,.3);background:var(--success-soft)}
  .notice.err{color:#ffbac5;border-color:rgba(251,87,114,.3);background:var(--danger-soft)}
  .template-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
  .template-card{
    border:1px solid var(--line);border-radius:15px;padding:15px;
    background:rgba(255,255,255,.022);display:flex;flex-direction:column;min-height:168px;
  }
  .template-icon{
    width:39px;height:39px;border-radius:12px;display:grid;place-items:center;
    background:var(--primary-soft);color:#bcecff;font-size:16px;margin-bottom:13px
  }
  .template-name{font-weight:850;font-size:12.5px;line-height:1.45}
  .template-meta{display:flex;gap:7px;flex-wrap:wrap;color:var(--muted);font-size:9.5px;margin-top:7px}
  .template-foot{margin-top:auto;padding-top:13px;display:flex;align-items:center;justify-content:space-between;gap:8px}
  .stats-hero{
    border:1px solid rgba(41,182,246,.18);border-radius:21px;padding:22px;
    background:
      radial-gradient(450px 180px at 90% 0,rgba(41,182,246,.13),transparent 70%),
      linear-gradient(145deg,rgba(16,23,37,.98),rgba(9,14,23,.96));
    margin-bottom:16px;
  }
  .stats-hero-head{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:20px}
  .stats-number{font-size:clamp(40px,7vw,72px);font-weight:950;line-height:1;letter-spacing:-.04em}
  .stats-caption{color:var(--muted);font-size:12px;margin-top:8px}
  .viewer-layout{display:grid;grid-template-columns:minmax(290px,.72fr) minmax(0,1.28fr);gap:16px}
  .selected-count{
    font-size:44px;font-weight:950;line-height:1;color:#d8f4ff;margin:18px 0 5px
  }
  .selected-label{font-size:11px;color:var(--muted)}
  .viewer-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
  .viewer-bar{height:5px;background:#202a3a;border-radius:99px;overflow:hidden;margin-top:7px}
  .viewer-bar span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--primary),var(--primary-2));min-width:4px}
  .privacy-note{
    border:1px solid var(--line);border-radius:13px;padding:13px 14px;
    background:rgba(255,255,255,.018);color:var(--muted);font-size:10.5px;margin-top:13px
  }
  details.security summary{cursor:pointer;font-size:12px;font-weight:800;list-style:none}
  details.security summary::-webkit-details-marker{display:none}
  .auth-shell{width:min(440px,calc(100% - 32px));margin:9vh auto 0}
  .auth-brand{justify-content:center;border:0;padding-bottom:18px}
  .auth-card{border:1px solid var(--line);border-radius:22px;padding:26px;background:rgba(10,15,25,.92);box-shadow:var(--shadow)}
  .auth-card h1{font-size:20px;margin:0 0 5px}
  .auth-card p{font-size:11.5px;color:var(--muted);margin:0 0 20px}
  .auth-card .field{margin-bottom:12px}
  .auth-card .button{width:100%;margin-top:5px}
  .mobile-nav{display:none}
  @media(max-width:1100px){
    .metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
    .template-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .grid-2,.viewer-layout{grid-template-columns:1fr}
  }
  @media(max-width:780px){
    .app{display:block;padding:12px}
    .sidebar{display:none}
    .mobile-nav{
      display:flex;position:sticky;top:8px;z-index:20;overflow:auto;gap:5px;
      padding:7px;margin-bottom:12px;border:1px solid var(--line);border-radius:15px;
      background:rgba(9,14,23,.9);backdrop-filter:blur(16px)
    }
    .mobile-nav .nav-btn{width:auto;flex:0 0 auto;padding:8px 10px}
    .mobile-nav .nav-icon{width:25px;height:25px}
    .topbar{min-height:58px;padding:2px 3px}
    .page-title p,.live-chip span:last-child{display:none}
    .metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    .metric{min-height:100px;padding:14px}
    .metric-value{font-size:24px}
    .form-grid,.save-template-box{grid-template-columns:1fr}
    .template-grid{grid-template-columns:1fr}
    .card,.stats-hero{padding:16px;border-radius:16px}
  }
  @media(max-width:430px){
    .metrics{grid-template-columns:1fr 1fr}
    .metric-label{font-size:10.5px}
    .metric-value{font-size:22px}
    .top-actions .button{display:none}
  }
</style>
</head>
<body>

<?php if ($view === 'setup' || $view === 'login'): ?>
  <div class="auth-shell">
    <div class="brand auth-brand">
      <div class="brand-mark">ToFi</div>
      <div class="brand-name">ToFi X Tv<small>Channels Control Center</small></div>
    </div>

    <?php if ($notice): ?>
      <div class="notice <?= e($notice['type']) ?>"><?= e($notice['text']) ?></div>
    <?php endif; ?>

    <div class="auth-card">
      <?php if ($view === 'setup'): ?>
        <div class="eyebrow">التشغيل الأول</div>
        <h1>جهّز لوحة التحكم</h1>
        <p>أنشئ كلمة مرور خاصة بك لحماية إدارة القنوات والإحصاءات.</p>
        <form method="post" action="?action=setup">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <div class="field">
            <label class="field-label">كلمة المرور</label>
            <input type="password" name="password" minlength="8" required autofocus>
          </div>
          <div class="field">
            <label class="field-label">تأكيد كلمة المرور</label>
            <input type="password" name="password2" minlength="8" required>
          </div>
          <button class="button primary" type="submit">حفظ والدخول</button>
        </form>
      <?php else: ?>
        <div class="eyebrow">دخول آمن</div>
        <h1>مرحبًا بعودتك</h1>
        <p>أدخل كلمة مرور مركز تحكم ToFi X Tv للمتابعة.</p>
        <form method="post" action="?action=login">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <div class="field">
            <label class="field-label">كلمة المرور</label>
            <input type="password" name="password" required autofocus>
          </div>
          <button class="button primary" type="submit">دخول إلى اللوحة</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">ToFi</div>
      <div class="brand-name">ToFi X Tv<small>Control Center 2026</small></div>
    </div>

    <div class="nav-label">الإدارة</div>
    <nav class="nav">
      <button class="nav-btn" type="button" data-section-target="channels">
        <span class="nav-icon">◫</span><span>إدارة القنوات</span>
      </button>
      <button class="nav-btn" type="button" data-section-target="templates">
        <span class="nav-icon">◇</span><span>مكتبة القوالب</span>
      </button>
      <button class="nav-btn" type="button" data-section-target="viewers">
        <span class="nav-icon">◉</span><span>المتصلون الآن</span>
      </button>
    </nav>

    <div class="nav-label">النظام</div>
    <nav class="nav">
      <button class="nav-btn" type="button" data-section-target="settings">
        <span class="nav-icon">⚙</span><span>الأمان والإعدادات</span>
      </button>
    </nav>

    <div class="side-foot">
      <div class="system-state">
        <div class="state-row">
          <span>حالة الخدمة</span>
          <span class="state-dot" aria-hidden="true"></span>
        </div>
        <div style="margin-top:6px;color:#c7d0dc;font-weight:750">البث يعمل بشكل مستقل</div>
      </div>
    </div>
  </aside>

  <main class="main">
    <nav class="mobile-nav">
      <button class="nav-btn" type="button" data-section-target="channels"><span class="nav-icon">◫</span>القنوات</button>
      <button class="nav-btn" type="button" data-section-target="templates"><span class="nav-icon">◇</span>القوالب</button>
      <button class="nav-btn" type="button" data-section-target="viewers"><span class="nav-icon">◉</span>المتصلون</button>
      <button class="nav-btn" type="button" data-section-target="settings"><span class="nav-icon">⚙</span>الأمان</button>
    </nav>

    <header class="topbar">
      <div class="page-title">
        <h1 id="pageHeading">إدارة القنوات</h1>
        <p id="pageSubtitle">تبديل البث وإدارة القوالب دون التأثير على مسار التشغيل</p>
      </div>
      <div class="top-actions">
        <div class="live-chip"><span class="live-dot"></span><span>النظام متصل</span></div>
        <form method="post" action="?action=logout">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <button class="button" type="submit">تسجيل الخروج</button>
        </form>
      </div>
    </header>

    <?php if ($notice): ?>
      <div class="notice <?= e($notice['type']) ?>" id="pageNotice"><?= e($notice['text']) ?></div>
    <?php endif; ?>

    <section class="panel-section" data-section="channels">
      <div class="metrics">
        <article class="metric">
          <div class="metric-label">القنوات المسجلة</div>
          <div class="metric-value"><?= count($channels) ?></div>
          <div class="metric-meta">كل إعدادات التبديل المحفوظة</div>
        </article>
        <article class="metric">
          <div class="metric-label">تبديل نشط الآن</div>
          <div class="metric-value"><?= $activeOverrides ?></div>
          <div class="metric-meta">يعرض قالبًا بدل البث الحالي</div>
        </article>
        <article class="metric">
          <div class="metric-label">القوالب المتاحة</div>
          <div class="metric-value"><?= count($presets) ?></div>
          <div class="metric-meta">افتراضية ومحفوظة باسمك</div>
        </article>
        <article class="metric">
          <div class="metric-label">محرك التحويل</div>
          <div class="metric-value" style="font-size:18px;margin-top:13px"><?= $ffmpegReady ? 'جاهز' : 'HLS فقط' ?></div>
          <div class="metric-meta"><?= $ffmpegReady ? 'يدعم الصور والفيديو' : 'ارفع حزمة ZIP جاهزة' ?></div>
        </article>
      </div>

      <div class="grid-2">
        <article class="card" id="channelEditor">
          <div class="card-head">
            <div>
              <div class="eyebrow">CHANNEL OVERRIDE</div>
              <h2 class="card-title">إضافة أو تعديل قناة</h2>
              <p class="card-sub">اختر القالب وسيظهر فورًا بعد حفظ الإعداد</p>
            </div>
            <span class="badge blue">تطبيق فوري</span>
          </div>

          <form method="post" action="?action=save" enctype="multipart/form-data" id="saveForm">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="form-grid">
              <div class="field">
                <label class="field-label" for="channelInput">رقم القناة</label>
                <input id="channelInput" type="number" name="channel" min="1" max="999999" placeholder="مثال: 15" required>
              </div>

              <div class="field">
                <label class="field-label" for="templateSelect">القالب المعروض</label>
                <select id="templateSelect" name="tmpl">
                  <optgroup label="القوالب الافتراضية">
                    <?php foreach ($builtins as $key => $label): ?>
                      <?php if (array_key_exists($key, $presets)): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php if ($userTemplates !== []): ?>
                    <optgroup label="قوالبي المحفوظة">
                      <?php foreach ($userTemplates as $key => $template): ?>
                        <option value="<?= e($key) ?>"><?= e((string) $template['name']) ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endif; ?>
                  <optgroup label="وسائط جديدة">
                    <option value="custom">رفع صورة / فيديو / حزمة HLS</option>
                  </optgroup>
                </select>
              </div>

              <div class="upload-box" id="uploadBox" hidden>
                <div class="field">
                  <label class="field-label" for="mediaInput">
                    الملف المرفوع
                    <span class="optional">ZIP / صورة / فيديو</span>
                  </label>
                  <input id="mediaInput" type="file" name="media" accept=".zip,image/*,video/*">
                  <div class="hint">
                    حزمة HLS الجاهزة يجب أن تحتوي على <b>index.m3u8</b> ومقاطع البث.
                    <?php if (!$ffmpegReady): ?>
                      <span class="warning">هذه الاستضافة لا تدعم تحويل الصور والفيديو؛ استخدم ZIP جاهزة.</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="save-template-box">
                  <label class="switch-line">
                    <input type="checkbox" name="save_template" id="saveTemplateCheck">
                    <span class="switch-track"></span>
                    <span>
                      <b style="display:block;font-size:11.5px">حفظ في مكتبة القوالب</b>
                      <small style="color:var(--muted);font-size:9.5px">استخدمه لاحقًا على أي قناة</small>
                    </span>
                  </label>
                  <div class="field" id="templateNameField" hidden>
                    <label class="field-label" for="templateNameInput">اسم القالب</label>
                    <input id="templateNameInput" type="text" name="template_name" maxlength="60" placeholder="مثال: انتظار المباريات">
                  </div>
                </div>
              </div>

              <div class="field">
                <label class="field-label" for="untilInput">موعد عودة البث <span class="optional">اختياري</span></label>
                <input id="untilInput" type="datetime-local" name="until">
              </div>

              <div class="field">
                <label class="field-label" for="noteInput">ملاحظة داخلية <span class="optional">اختياري</span></label>
                <input id="noteInput" type="text" name="note" maxlength="80" placeholder="مثال: مباراة الهلال">
              </div>
            </div>

            <div class="form-actions">
              <label class="switch-line">
                <input type="checkbox" name="enabled" id="enabledInput" checked>
                <span class="switch-track"></span>
                <span style="font-size:11.5px;font-weight:750">تفعيل التبديل الآن</span>
              </label>
              <button class="button primary" type="submit">حفظ إعداد القناة</button>
            </div>
          </form>
        </article>

        <aside class="card">
          <div class="card-head">
            <div>
              <div class="eyebrow">SYSTEM STATUS</div>
              <h2 class="card-title">جاهزية النظام</h2>
              <p class="card-sub">فحص سريع لمكونات لوحة التبديل</p>
            </div>
          </div>
          <div class="status-stack">
            <div class="status-item">
              <span>القوالب الافتراضية</span>
              <span class="badge on"><span class="dot"></span><?= count(array_intersect_key($presets, $builtins)) ?> جاهزة</span>
            </div>
            <div class="status-item">
              <span>قالب ToFi X Tv</span>
              <span class="badge <?= isset($presets['_tofi_music']) ? 'on' : 'off' ?>"><span class="dot"></span><?= isset($presets['_tofi_music']) ? 'مدمج' : 'غير متوفر' ?></span>
            </div>
            <div class="status-item">
              <span>تحويل الوسائط</span>
              <span class="badge <?= $ffmpegReady ? 'on' : 'warn' ?>"><?= $ffmpegReady ? 'FFmpeg جاهز' : 'ZIP جاهزة فقط' ?></span>
            </div>
            <div class="status-item">
              <span>وقت الخادم</span>
              <b style="font-size:10.5px"><?= e(date('Y-m-d H:i:s')) ?></b>
            </div>
          </div>
          <div class="privacy-note">
            أي خطأ في اللوحة أو الإحصاءات لا يوقف البث؛ مسار التشغيل والتخزين المؤقت يعملان بشكل مستقل.
          </div>
        </aside>
      </div>

      <article class="card">
        <div class="toolbar">
          <div>
            <h2 class="card-title">القنوات المُبدّلة</h2>
            <p class="card-sub">القنوات غير الموجودة هنا تعمل بالبث الأصلي</p>
          </div>
          <div class="search">
            <input id="channelSearch" type="text" inputmode="numeric" placeholder="ابحث برقم القناة">
          </div>
        </div>

        <?php if ($channels === []): ?>
          <div class="empty"><div><strong>لا توجد قنوات مضافة</strong>ابدأ بإضافة رقم قناة من النموذج أعلاه.</div></div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>القناة</th>
                  <th>القالب</th>
                  <th>الحالة</th>
                  <th>العودة التلقائية</th>
                  <th>آخر تعديل</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody id="channelsTable">
              <?php foreach ($channels as $number => $channel):
                  $isActive = !empty($channel['enabled'])
                      && (
                          empty($channel['until'])
                          || time() < (int) $channel['until']
                      );
                  $isExpired = !empty($channel['enabled'])
                      && !empty($channel['until'])
                      && time() >= (int) $channel['until'];
                  $source = (string) ($channel['source'] ?? 'preset');
                  $presetKey = (string) ($channel['preset'] ?? OVR_DEFAULT_KEY);
                  $templateLabel = $source === 'custom'
                      ? 'وسائط خاصة بالقناة'
                      : (string) ($presets[$presetKey] ?? 'قالب غير متوفر');
                  $untilValue = !empty($channel['until'])
                      ? date('Y-m-d\TH:i', (int) $channel['until'])
                      : '';
              ?>
                <tr data-channel-row="<?= (int) $number ?>">
                  <td>
                    <div class="channel-id">#<?= (int) $number ?></div>
                    <?php if (!empty($channel['note'])): ?>
                      <div class="channel-note" title="<?= e((string) $channel['note']) ?>"><?= e((string) $channel['note']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge blue"><?= e($templateLabel) ?></span></td>
                  <td>
                    <?php if ($isActive): ?>
                      <span class="badge on"><span class="dot"></span>يعرض القالب</span>
                    <?php elseif ($isExpired): ?>
                      <span class="badge warn"><span class="dot"></span>انتهى الموعد</span>
                    <?php else: ?>
                      <span class="badge off"><span class="dot"></span>متوقف</span>
                    <?php endif; ?>
                  </td>
                  <td style="color:var(--muted)"><?= !empty($channel['until']) ? e(date('Y-m-d H:i', (int) $channel['until'])) : 'بدون موعد' ?></td>
                  <td style="color:var(--muted)"><?= !empty($channel['updated']) ? e(date('Y-m-d H:i', (int) $channel['updated'])) : '—' ?></td>
                  <td>
                    <div class="actions">
                      <button
                        type="button"
                        class="button small edit-channel"
                        data-channel="<?= (int) $number ?>"
                        data-template="<?= e($source === 'custom' ? 'custom' : $presetKey) ?>"
                        data-until="<?= e($untilValue) ?>"
                        data-note="<?= e((string) ($channel['note'] ?? '')) ?>"
                        data-enabled="<?= !empty($channel['enabled']) ? '1' : '0' ?>"
                      >تعديل</button>
                      <form method="post" action="?action=toggle">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="channel" value="<?= (int) $number ?>">
                        <button class="button small" type="submit"><?= !empty($channel['enabled']) ? 'إيقاف' : 'تشغيل' ?></button>
                      </form>
                      <form method="post" action="?action=delete" onsubmit="return confirm('حذف إعداد القناة <?= (int) $number ?>؟');">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="channel" value="<?= (int) $number ?>">
                        <button class="button small danger" type="submit">حذف</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <section class="panel-section" data-section="templates">
      <div class="grid-2">
        <article class="card">
          <div class="card-head">
            <div>
              <div class="eyebrow">HLS TEMPLATE LIBRARY</div>
              <h2 class="card-title">حفظ حزمة HLS كقالب</h2>
              <p class="card-sub">ارفعها مرة واحدة ثم اخترها لأي قناة لاحقًا</p>
            </div>
            <span class="badge blue">ZIP</span>
          </div>
          <form method="post" action="?action=create_template" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="form-grid">
              <div class="field">
                <label class="field-label">اسم القالب المخصص</label>
                <input type="text" name="template_name" maxlength="60" placeholder="مثال: استراحة المباريات" required>
              </div>
              <div class="field">
                <label class="field-label">حزمة HLS الجاهزة</label>
                <input type="file" name="template_zip" accept=".zip,application/zip" required>
              </div>
            </div>
            <div class="form-actions">
              <span class="hint">يجب أن تحتوي الحزمة على index.m3u8 ومقاطع متطابقة.</span>
              <button class="button primary" type="submit">رفع وحفظ القالب</button>
            </div>
          </form>
        </article>

        <aside class="card">
          <div class="card-head">
            <div>
              <div class="eyebrow">HOW IT WORKS</div>
              <h2 class="card-title">استخدام مرن وآمن</h2>
            </div>
          </div>
          <div class="status-stack">
            <div class="status-item"><span>1. ارفع ZIP</span><b>مرة واحدة</b></div>
            <div class="status-item"><span>2. اختر اسمًا</span><b>يظهر في القائمة</b></div>
            <div class="status-item"><span>3. طبّقه</span><b>على أي قناة</b></div>
          </div>
          <div class="privacy-note">يمكنك أيضًا حفظ الملف كقالب مباشرة أثناء إعداد قناة من قسم إدارة القنوات.</div>
        </aside>
      </div>

      <article class="card">
        <div class="card-head">
          <div>
            <h2 class="card-title">كل القوالب المتاحة</h2>
            <p class="card-sub"><?= count($presets) ?> قالبًا جاهزًا للاستخدام الآن</p>
          </div>
        </div>
        <div class="template-grid">
          <?php foreach ($presets as $key => $label):
              $isBuiltin = array_key_exists($key, $builtins);
              $summary = panel_media_summary($key);
              $usage = panel_template_usage($key, $channels);
          ?>
            <div class="template-card">
              <div class="template-icon"><?= $key === '_tofi_music' ? 'TV' : ($isBuiltin ? '◆' : '◇') ?></div>
              <div class="template-name"><?= e((string) $label) ?></div>
              <div class="template-meta">
                <span><?= (int) $summary['segments'] ?> مقطع</span>
                <span>•</span>
                <span><?= (int) $summary['duration'] ?> ثانية</span>
                <span>•</span>
                <span><?= e(panel_format_bytes((int) $summary['size'])) ?></span>
              </div>
              <div class="template-foot">
                <span class="badge <?= $isBuiltin ? 'blue' : 'on' ?>"><?= $isBuiltin ? 'افتراضي' : 'محفوظ' ?></span>
                <div class="actions">
                  <span style="font-size:9.5px;color:var(--muted)">مستخدم: <?= $usage ?></span>
                  <?php if (!$isBuiltin): ?>
                    <form method="post" action="?action=delete_template" onsubmit="return confirm('حذف القالب «<?= e((string) $label) ?>»؟');">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="template_key" value="<?= e($key) ?>">
                      <button class="button small danger" type="submit" <?= $usage > 0 ? 'disabled title="القالب مستخدم حاليًا"' : '' ?>>حذف</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    </section>

    <section class="panel-section" data-section="viewers">
      <article class="stats-hero">
        <div class="stats-hero-head">
          <div>
            <div class="eyebrow">LIVE CONCURRENCY</div>
            <h2 class="card-title">المتصلون بجميع القنوات الآن</h2>
            <p class="card-sub">قراءة فعلية من جلسات تشغيل HLS النشطة</p>
          </div>
          <span class="badge on" id="statsConnection"><span class="dot"></span>تحديث مباشر</span>
        </div>
        <div class="stats-number" id="totalViewers">0</div>
        <div class="stats-caption">مشاهد متصل في اللحظة الحالية عبر <b id="activeChannelCount">0</b> قناة</div>
      </article>

      <div class="viewer-layout">
        <article class="card">
          <div class="card-head">
            <div>
              <div class="eyebrow">CHANNEL LOOKUP</div>
              <h2 class="card-title">فحص قناة محددة</h2>
              <p class="card-sub">اكتب ID القناة فقط مثل 15</p>
            </div>
          </div>
          <div class="field">
            <label class="field-label" for="viewerChannelInput">رقم القناة</label>
            <div style="display:flex;gap:8px">
              <input id="viewerChannelInput" type="number" min="1" max="999999" placeholder="15">
              <button class="button primary" type="button" id="lookupViewers">فحص</button>
            </div>
          </div>
          <div class="selected-count" id="selectedViewerCount">—</div>
          <div class="selected-label" id="selectedViewerLabel">اكتب رقم قناة لعرض المتصلين بها</div>
          <div class="privacy-note">
            لا توجد أرقام تجريبية أو تقديرية: لا تُحتسب الجلسة إلا بعد طلب تشغيل موقّع فعلي، وتُزال تلقائيًا عند توقف طلبات المشغل.
            مهلة التأكيد 18 ثانية، أو فورًا عند استدعاء leave_url من التطبيق.
          </div>
        </article>

        <article class="card">
          <div class="card-head">
            <div>
              <div class="eyebrow">CHANNEL BREAKDOWN</div>
              <h2 class="card-title">التوزيع المباشر حسب القناة</h2>
              <p class="card-sub" id="statsUpdatedAt">بانتظار أول تحديث…</p>
            </div>
          </div>
          <div id="viewerChannelsList">
            <div class="empty"><div><strong>لا توجد جلسات نشطة الآن</strong>ستظهر القنوات تلقائيًا عند بدء المشاهدة.</div></div>
          </div>
        </article>
      </div>
    </section>

    <section class="panel-section" data-section="settings">
      <article class="card" style="max-width:760px">
        <div class="card-head">
          <div>
            <div class="eyebrow">SECURITY</div>
            <h2 class="card-title">أمان لوحة التحكم</h2>
            <p class="card-sub">تحديث كلمة المرور الخاصة بالإدارة</p>
          </div>
          <span class="badge on"><span class="dot"></span>جلسة محمية</span>
        </div>
        <details class="security" open>
          <summary>تغيير كلمة المرور</summary>
          <form method="post" action="?action=passwd" style="margin-top:16px">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="form-grid">
              <div class="field full">
                <label class="field-label">كلمة المرور الحالية</label>
                <input type="password" name="current" required>
              </div>
              <div class="field">
                <label class="field-label">كلمة المرور الجديدة</label>
                <input type="password" name="password" minlength="8" required>
              </div>
              <div class="field">
                <label class="field-label">تأكيد كلمة المرور</label>
                <input type="password" name="password2" minlength="8" required>
              </div>
            </div>
            <div class="form-actions">
              <span class="hint">استخدم 8 أحرف على الأقل وكلمة غير مستخدمة في خدمات أخرى.</span>
              <button class="button primary" type="submit">تحديث كلمة المرور</button>
            </div>
          </form>
        </details>
      </article>
    </section>
  </main>
</div>

<script>
(function(){
  'use strict';

  var initialSection = <?= json_encode($initialTab, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var titles = {
    channels: ['إدارة القنوات','تبديل البث وإدارة القوالب دون التأثير على مسار التشغيل'],
    templates: ['مكتبة القوالب','احفظ حزم HLS واستخدمها على أي قناة'],
    viewers: ['المتصلون الآن','إحصاء مباشر لجميع جلسات المشاهدة النشطة'],
    settings: ['الأمان والإعدادات','إدارة حماية لوحة التحكم']
  };

  function showSection(name, updateUrl) {
    if (!titles[name]) name = 'channels';

    document.querySelectorAll('[data-section]').forEach(function(section){
      section.classList.toggle('active', section.getAttribute('data-section') === name);
    });
    document.querySelectorAll('[data-section-target]').forEach(function(button){
      button.classList.toggle('active', button.getAttribute('data-section-target') === name);
    });

    document.getElementById('pageHeading').textContent = titles[name][0];
    document.getElementById('pageSubtitle').textContent = titles[name][1];

    if (updateUrl && window.history && window.history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', name);
      url.searchParams.delete('msg');
      url.searchParams.delete('type');
      window.history.replaceState({}, '', url.toString());
    }

    if (name === 'viewers') loadViewerStats();
  }

  document.querySelectorAll('[data-section-target]').forEach(function(button){
    button.addEventListener('click', function(){
      showSection(button.getAttribute('data-section-target'), true);
    });
  });
  showSection(initialSection, false);

  var templateSelect = document.getElementById('templateSelect');
  var uploadBox = document.getElementById('uploadBox');
  var saveTemplateCheck = document.getElementById('saveTemplateCheck');
  var templateNameField = document.getElementById('templateNameField');
  var templateNameInput = document.getElementById('templateNameInput');

  function syncUploadFields(){
    if (!templateSelect) return;
    var custom = templateSelect.value === 'custom';
    uploadBox.hidden = !custom;
    if (!custom) {
      saveTemplateCheck.checked = false;
      templateNameField.hidden = true;
      templateNameInput.required = false;
    }
  }
  function syncTemplateName(){
    var enabled = saveTemplateCheck && saveTemplateCheck.checked;
    templateNameField.hidden = !enabled;
    templateNameInput.required = enabled;
  }
  if (templateSelect) templateSelect.addEventListener('change', syncUploadFields);
  if (saveTemplateCheck) saveTemplateCheck.addEventListener('change', syncTemplateName);
  syncUploadFields();
  syncTemplateName();

  document.querySelectorAll('.edit-channel').forEach(function(button){
    button.addEventListener('click', function(){
      showSection('channels', true);
      document.getElementById('channelInput').value = button.dataset.channel || '';
      document.getElementById('templateSelect').value = button.dataset.template || '_default';
      document.getElementById('untilInput').value = button.dataset.until || '';
      document.getElementById('noteInput').value = button.dataset.note || '';
      document.getElementById('enabledInput').checked = button.dataset.enabled === '1';
      syncUploadFields();
      syncTemplateName();
      document.getElementById('channelEditor').scrollIntoView({behavior:'smooth',block:'start'});
      window.setTimeout(function(){ document.getElementById('channelInput').focus(); }, 350);
    });
  });

  var channelSearch = document.getElementById('channelSearch');
  if (channelSearch) {
    channelSearch.addEventListener('input', function(){
      var needle = channelSearch.value.trim();
      document.querySelectorAll('[data-channel-row]').forEach(function(row){
        row.hidden = needle !== '' && row.getAttribute('data-channel-row').indexOf(needle) === -1;
      });
    });
  }

  var viewerInput = document.getElementById('viewerChannelInput');
  var lookupButton = document.getElementById('lookupViewers');
  var statsBusy = false;
  var numberFormat = typeof Intl !== 'undefined'
    ? new Intl.NumberFormat('ar-SA')
    : {format:function(value){return String(value);}};

  function escapeHtml(value){
    return String(value).replace(/[&<>"']/g,function(char){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
    });
  }

  function renderViewerChannels(channels, total){
    var list = document.getElementById('viewerChannelsList');
    if (!list) return;

    if (!channels || channels.length === 0) {
      list.innerHTML = '<div class="empty"><div><strong>لا توجد جلسات نشطة الآن</strong>ستظهر القنوات تلقائيًا عند بدء المشاهدة.</div></div>';
      return;
    }

    var maximum = Math.max.apply(null, channels.map(function(item){return Number(item.count) || 0;}));
    list.innerHTML = channels.map(function(item){
      var count = Number(item.count) || 0;
      var percentage = maximum > 0 ? Math.max(4, Math.round((count / maximum) * 100)) : 4;
      return '<div style="padding:10px 0;border-bottom:1px solid var(--line)">'
        + '<div class="viewer-row"><div><b style="font-size:12px">القناة #' + escapeHtml(item.id) + '</b>'
        + '<div style="font-size:9.5px;color:var(--muted)">' + (total > 0 ? Math.round((count / total) * 100) : 0) + '% من الإجمالي</div></div>'
        + '<b style="font-size:16px;color:#d8f4ff">' + numberFormat.format(count) + '</b></div>'
        + '<div class="viewer-bar"><span style="width:' + percentage + '%"></span></div></div>';
    }).join('');
  }

  async function loadViewerStats(){
    if (statsBusy || document.hidden) return;
    var totalElement = document.getElementById('totalViewers');
    if (!totalElement) return;

    statsBusy = true;
    var selected = viewerInput ? viewerInput.value.trim() : '';
    var endpoint = 'panel.php?action=viewer_stats';
    if (/^[1-9][0-9]{0,5}$/.test(selected)) {
      endpoint += '&channel=' + encodeURIComponent(selected);
    }

    try {
      var response = await fetch(endpoint, {
        method:'GET',
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'application/json'}
      });
      var data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'stats');

      document.getElementById('totalViewers').textContent = numberFormat.format(data.total || 0);
      document.getElementById('activeChannelCount').textContent = numberFormat.format(data.active_channels || 0);

      var connection = document.getElementById('statsConnection');
      connection.className = 'badge on';
      connection.innerHTML = '<span class="dot"></span>تحديث مباشر';

      var date = new Date((Number(data.updated_at) || Math.floor(Date.now()/1000)) * 1000);
      document.getElementById('statsUpdatedAt').textContent = 'آخر تحديث: ' + date.toLocaleTimeString('ar-SA');

      if (data.selected_channel !== null && data.selected_channel !== undefined) {
        document.getElementById('selectedViewerCount').textContent = numberFormat.format(data.selected_count || 0);
        document.getElementById('selectedViewerLabel').textContent = 'متصل الآن على القناة #' + data.selected_channel;
      } else {
        document.getElementById('selectedViewerCount').textContent = '—';
        document.getElementById('selectedViewerLabel').textContent = 'اكتب رقم قناة لعرض المتصلين بها';
      }

      renderViewerChannels(data.channels || [], Number(data.total) || 0);
    } catch (error) {
      var failed = document.getElementById('statsConnection');
      if (failed) {
        failed.className = 'badge warn';
        failed.innerHTML = '<span class="dot"></span>إعادة الاتصال…';
      }
    } finally {
      statsBusy = false;
    }
  }

  if (lookupButton) lookupButton.addEventListener('click', loadViewerStats);
  if (viewerInput) viewerInput.addEventListener('keydown', function(event){
    if (event.key === 'Enter') {
      event.preventDefault();
      loadViewerStats();
    }
  });
  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) loadViewerStats();
  });
  window.setInterval(loadViewerStats, 2500);
  loadViewerStats();

  var notice = document.getElementById('pageNotice');
  if (notice) {
    window.setTimeout(function(){
      notice.style.transition = 'opacity .25s ease';
      notice.style.opacity = '0';
      window.setTimeout(function(){ notice.remove(); }, 280);
    }, 6500);
  }
})();
</script>
<?php endif; ?>
</body>
</html>
