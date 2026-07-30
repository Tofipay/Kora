<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  selfcheck.php — فحص بيئة التشغيل بعد الرفع (للاستضافة المشتركة)
 * ───────────────────────────────────────────────────────────────────────────
 *  يجيب على السؤال الأهم على Hostinger: هل قواعد ‎.htaccess‎ فعّالة فعلًا،
 *  وهل يرسل الخادم المقاطع المحفوظة مباشرة بلا PHP؟
 *
 *  الفتح:
 *      https://موقعك/selfcheck.php?key=<metrics_token>
 *      https://موقعك/selfcheck.php?key=<metrics_token>&format=json
 *
 *  أو بعد تسجيل الدخول في panel.php يعمل بلا مفتاح.
 *
 *  اضبط في config.php:   'metrics_token' => 'مفتاح-عشوائي-طويل'
 *  احذف هذا الملف بعد انتهاء الفحص إن أردت — لا شيء يعتمد عليه.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/hls-core.php';

if (is_file(__DIR__ . '/viewers.php')) {
    require_once __DIR__ . '/viewers.php';
}

/* ───────────── الحماية ───────────── */

$authorised = false;
$configuredKey = (string) hls_config('metrics_token');
$providedKey = (string) ($_GET['key'] ?? '');

if ($configuredKey !== '' && hash_equals($configuredKey, $providedKey)) {
    $authorised = true;
} else {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $authorised = !empty($_SESSION['ovr_auth']);
}

if (!$authorised) {
    http_response_code(404);
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=utf-8');

    $errorPage = __DIR__ . '/error.php';

    if (is_file($errorPage)) {
        $__errorStatus = 404;
        include $errorPage;
    }

    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

$results = [];

function check_add(
    string $group,
    string $label,
    string $state,
    string $detail = '',
    string $fix = ''
): void {
    global $results;

    $results[] = [
        'group' => $group,
        'label' => $label,
        'state' => $state,      /* ok | warn | fail | info */
        'detail' => $detail,
        'fix' => $fix,
    ];
}

/* ───────────── 1) PHP والإضافات ───────────── */

check_add(
    'PHP',
    'إصدار PHP',
    version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'fail',
    PHP_VERSION,
    'المشروع يتطلب PHP 8.0 أو أحدث. غيّره من hPanel ← PHP Configuration.'
);

foreach (['curl' => true, 'openssl' => true, 'json' => true, 'zip' => false] as $extension => $required) {
    $loaded = extension_loaded($extension);

    check_add(
        'PHP',
        'إضافة ' . $extension,
        $loaded ? 'ok' : ($required ? 'fail' : 'warn'),
        $loaded ? 'متوفرة' : 'غير متوفرة',
        $required
            ? 'ضرورية للتشغيل. فعّلها من hPanel ← PHP Configuration.'
            : 'اختيارية (تُستخدم لرفع حزم HLS في لوحة التحكم).'
    );
}

check_add(
    'PHP',
    'مهلة تنفيذ السكربت',
    ((int) ini_get('max_execution_time') === 0
        || (int) ini_get('max_execution_time') >= 30) ? 'ok' : 'warn',
    (string) ini_get('max_execution_time') . ' ثانية',
    'يُفضّل 30 ثانية فأكثر حتى يكتمل جلب المقطع الأول.'
);

/* ───────────── 2) المجلدات والصلاحيات ───────────── */

foreach (
    [
        'مجلد المقاطع (يُخدم مباشرة)' => (string) hls_config('segment_cache_root'),
        'مجلد الحالة الداخلي' => (string) hls_config('cache_root'),
        'مجلد إحصاء المتصلين' => __DIR__ . '/.tofi-viewers',
    ] as $label => $directory
) {
    $exists = is_dir($directory);

    if (!$exists) {
        $exists = @mkdir($directory, 0755, true) || is_dir($directory);
    }

    $writable = $exists && is_writable($directory);

    check_add(
        'المجلدات',
        $label,
        $writable ? 'ok' : 'fail',
        $writable
            ? 'قابل للكتابة'
            : ($exists ? 'موجود لكنه غير قابل للكتابة' : 'تعذّر إنشاؤه'),
        'اضبط الصلاحية 755 على المجلد من File Manager، وتأكد أن مالكه هو '
        . 'مستخدم الاستضافة نفسه.'
    );
}

/* ───────────── 3) الإعدادات والأسرار ───────────── */

$hasConfig = is_file(__DIR__ . '/config.php');

check_add(
    'الإعدادات',
    'ملف config.php',
    $hasConfig ? 'ok' : 'warn',
    $hasConfig ? 'موجود' : 'غير موجود (تُستخدم القيم الافتراضية)',
    'انسخ config.example.php إلى config.php لضبط الأسرار والقنوات.'
);

$defaultToken = 'ToFi-X-TV-3Loka-Proxy-Key-2026-7f4a91c8e2b65d03';
$defaultSigning = 'ToFi-Stream-Signing-2026-b7e43a1f9062cd85d8417a3e';
$usingDefaults = hash_equals($defaultToken, (string) hls_config('token_secret'))
    || hash_equals($defaultSigning, (string) hls_config('stream_signing_secret'));

check_add(
    'الإعدادات',
    'الأسرار',
    $usingDefaults ? 'warn' : 'ok',
    $usingDefaults ? 'ما زالت المفاتيح الافتراضية مستخدمة' : 'مفاتيح مخصّصة',
    'غيّرها في config.php بعد أن يحدّث المستخدمون التطبيق. تغييرها الآن '
    . 'يُبطل الروابط الموقّعة الجارية فورًا.'
);

check_add(
    'الإعدادات',
    'وضع التشغيل',
    (string) hls_config('mode') === 'request_driven' ? 'ok' : 'info',
    (string) hls_config('mode'),
    (string) hls_config('mode') === 'worker'
        ? 'وضع worker يتطلب خدمة دائمة (VPS). على الاستضافة المشتركة اجعله '
        . 'request_driven.'
        : 'الوضع الصحيح للاستضافة المشتركة.'
);

check_add(
    'الإعدادات',
    'القائمة المشتركة (shared_media_playlist)',
    hls_config('shared_media_playlist') ? 'ok' : 'info',
    hls_config('shared_media_playlist') ? 'مفعّلة' : 'مطفأة',
    hls_config('shared_media_playlist')
        ? 'تأكد أن التطبيق يستدعي ping_url كل 10–15 ثانية ليبقى الإحصاء دقيقًا.'
        : 'تفعيلها يسمح لـ Cloudflare بمشاركة قائمة واحدة بين كل المشاهدين، '
        . 'وهو المطلوب للأعداد الكبيرة على الاستضافة المشتركة.'
);

$backend = function_exists('viewer_backend_name') ? viewer_backend_name() : '—';

check_add(
    'الإعدادات',
    'مخزن إحصاء المتصلين',
    'info',
    $backend,
    $backend === 'file'
        ? 'يعمل. إن توفّر pdo_sqlite سيُستخدم تلقائيًا وهو أخف.'
        : 'جيد.'
);

/* ───────────── 4) الاتصال بالمصدر ───────────── */

$channel = (int) ($_GET['channel'] ?? 0);

if ($channel > 0) {
    $started = microtime(true);

    try {
        $entry = hls_playlist_get(hls_channel_source_url($channel));
        $elapsed = (microtime(true) - $started) * 1000;
        $segments = substr_count($entry['body'], '#EXTINF:');

        check_add(
            'المصدر',
            'جلب قناة ' . $channel,
            $segments > 0 ? 'ok' : 'fail',
            sprintf(
                '%d مقطعًا في النافذة، عمر النسخة %d ms، الزمن %.0f ms',
                $segments,
                (int) $entry['age_ms'],
                $elapsed
            ),
            ''
        );
    } catch (Throwable $error) {
        check_add(
            'المصدر',
            'جلب قناة ' . $channel,
            'fail',
            'فشل الاتصال',
            'تأكد أن الاستضافة تسمح بالاتصالات الصادرة، وأن رابط المصدر '
            . 'وallowed_source_hosts صحيحان.'
        );
    }
} else {
    check_add(
        'المصدر',
        'اختبار قناة',
        'info',
        'لم يُطلب',
        'أضف ‎&channel=10‎ إلى الرابط لاختبار الجلب من المصدر فعليًا.'
    );
}

/* ───────────── 5) الفحص الحاسم: هل يرسل الخادم الملفات مباشرة؟ ───────────── */

$probeName = str_repeat('0', 8) . bin2hex(random_bytes(16));
$probePath = (string) hls_config('segment_cache_root') . '/' . $probeName . '.ts';
$probeUrl = rtrim((string) hls_config('public_base_url'), '/')
    . '/hls-cache/' . $probeName . '.ts';
$probeBody = '';

for ($index = 0; $index < 8; $index++) {
    $probeBody .= "\x47" . str_repeat("\x1f", 187);
}

$probeWritten = @file_put_contents($probePath, $probeBody) !== false;

if (!$probeWritten) {
    check_add(
        'التسليم المباشر',
        'كتابة ملف اختبار',
        'fail',
        'تعذّرت الكتابة في hls-cache',
        'راجع صلاحيات المجلد.'
    );
} else {
    $probe = selfcheck_fetch($probeUrl);
    @unlink($probePath);

    if ($probe['status'] === 0) {
        check_add(
            'التسليم المباشر',
            'طلب الملف من الخادم',
            'warn',
            'تعذّر الوصول إلى ' . parse_url($probeUrl, PHP_URL_HOST),
            'قد تكون الاستضافة تمنع الاتصال بنفسها (loopback). افتح الرابط '
            . 'يدويًا من المتصفح للتأكد، أو تحقق أن public_base_url صحيح.'
        );
    } else {
        $headers = strtolower($probe['headers']);
        $servedByPhp = str_contains($headers, 'x-tofi-cache:');
        $immutable = str_contains($headers, 'immutable');
        $cors = str_contains($headers, 'access-control-allow-origin');
        $ranges = str_contains($headers, 'accept-ranges');

        check_add(
            'التسليم المباشر',
            'الملف المحفوظ يرسله الخادم بلا PHP',
            ($probe['status'] === 200 && !$servedByPhp) ? 'ok' : 'fail',
            'الحالة ' . $probe['status']
            . ($servedByPhp ? ' — مرّ عبر PHP!' : ' — مباشر من الخادم'),
            'إن فشل: قاعدة ‎.htaccess‎ للملفات الثابتة غير فعّالة. تأكد أن '
            . 'mod_rewrite مفعّل وأن الملف ‎.htaccess‎ مرفوع فعلًا في جذر '
            . 'الدومين (وليس داخل مجلد فرعي).'
        );

        check_add(
            'التسليم المباشر',
            'ترويسة التخزين الطويل للمقاطع',
            $immutable ? 'ok' : 'warn',
            $immutable ? 'immutable موجودة' : 'immutable غير موجودة',
            'تحتاج mod_headers. بدونها سيعمل البث لكن CDN سيعيد التحقق كثيرًا.'
        );

        check_add(
            'التسليم المباشر',
            'ترويسة CORS على المقاطع',
            $cors ? 'ok' : 'warn',
            $cors ? 'موجودة' : 'غير موجودة',
            'تحتاج mod_headers في ‎.htaccess‎.'
        );

        check_add(
            'التسليم المباشر',
            'دعم Range من الخادم',
            $ranges ? 'ok' : 'warn',
            $ranges ? 'Accept-Ranges موجودة' : 'غير معلنة',
            'يتولاها الخادم للملفات الثابتة. غيابها غالبًا لا يمنع التشغيل.'
        );
    }
}

/* ───────────── 6) التوجيه والحماية ───────────── */

$publicBase = rtrim((string) hls_config('public_base_url'), '/');

$routeChecks = [
    ['api/token/10', 'MTX Player', [200], 'مسار إصدار التوكن'],
    ['api/token/10', 'curl/8', [401], 'رفض User-Agent غير المسموح'],
    ['index.php', 'MTX Player', [403], 'منع فتح المعالج مباشرة'],
    ['hls-core.php', 'MTX Player', [403], 'منع فتح ملف المحرّك'],
    ['config.php', 'MTX Player', [403, 404], 'منع فتح ملف الإعدادات'],
];

foreach ($routeChecks as [$path, $agent, $expected, $label]) {
    $response = selfcheck_fetch($publicBase . '/' . $path, $agent);

    if ($response['status'] === 0) {
        check_add('التوجيه', $label, 'warn', 'تعذّر الفحص (loopback)', '');
        continue;
    }

    check_add(
        'التوجيه',
        $label,
        in_array($response['status'], $expected, true) ? 'ok' : 'fail',
        'الحالة ' . $response['status']
        . ' (المتوقع ' . implode('/', $expected) . ')',
        'إن كانت النتيجة غير متوقعة فراجع ترتيب قواعد ‎.htaccess‎.'
    );
}

/* ───────────── 7) حجم الكاش ───────────── */

$segmentRoot = (string) hls_config('segment_cache_root');
$files = 0;
$bytes = 0;

if (is_dir($segmentRoot)) {
    foreach ((array) @scandir($segmentRoot) as $item) {
        if ($item === '.' || $item === '..' || $item === false) {
            continue;
        }

        $path = $segmentRoot . '/' . $item;

        if (is_file($path)) {
            $files++;
            $bytes += (int) @filesize($path);
        }
    }
}

check_add(
    'الكاش',
    'المقاطع المحفوظة الآن',
    'info',
    $files . ' ملفًا، ' . number_format($bytes / 1048576, 1) . ' ميجابايت',
    'تُحذف تلقائيًا بعد ' . (int) hls_config('segment_keep_seconds')
    . ' ثانية. خفّض segment_keep_seconds إن اقتربت من حد الملفات (inodes).'
);

/* ───────────── الإخراج ───────────── */

$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];

foreach ($results as $result) {
    $counts[$result['state']]++;
}

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'summary' => $counts,
        'healthy' => $counts['fail'] === 0,
        'checks' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    exit;
}

function selfcheck_fetch(string $url, string $userAgent = 'ToFi-SelfCheck'): array
{
    $curl = curl_init($url);

    if ($curl === false) {
        return ['status' => 0, 'headers' => '', 'body' => ''];
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
    ]);

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    if (!is_string($raw)) {
        return ['status' => 0, 'headers' => '', 'body' => ''];
    }

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function e_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>فحص بيئة التشغيل — ToFi X Tv</title>
<style>
  :root{
    --bg:#05070d; --surface:#0b101a; --line:rgba(151,174,211,.14);
    --text:#f4f7fb; --muted:#8f9db2;
    --ok:#2dd4a2; --warn:#f9c74f; --fail:#fb5772; --info:#29b6f6;
  }
  *{box-sizing:border-box}
  body{
    margin:0;padding:24px;background:var(--bg);color:var(--text);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Kufi Arabic",Tahoma,Arial,sans-serif;
    line-height:1.7;
  }
  .wrap{max-width:980px;margin:0 auto}
  h1{font-size:22px;margin:0 0 4px}
  .sub{color:var(--muted);font-size:13px;margin-bottom:20px}
  .summary{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
  .pill{
    border:1px solid var(--line);border-radius:12px;padding:10px 16px;
    background:var(--surface);font-size:13px;font-weight:700;
  }
  .pill b{font-size:18px;display:block}
  .group{margin-bottom:22px}
  .group h2{
    font-size:12px;letter-spacing:.08em;color:var(--muted);
    text-transform:uppercase;margin:0 0 10px;font-weight:800;
  }
  .item{
    border:1px solid var(--line);border-radius:12px;background:var(--surface);
    padding:13px 15px;margin-bottom:8px;
  }
  .row{display:flex;align-items:flex-start;gap:11px}
  .dot{
    width:9px;height:9px;border-radius:50%;flex:none;margin-top:8px;
  }
  .ok .dot{background:var(--ok)} .warn .dot{background:var(--warn)}
  .fail .dot{background:var(--fail)} .info .dot{background:var(--info)}
  .label{font-weight:750;font-size:14px}
  .detail{color:var(--muted);font-size:12.5px}
  .fix{
    color:#c7d3e4;font-size:12px;margin-top:7px;padding-top:7px;
    border-top:1px dashed var(--line);
  }
  .ok .fix{display:none}
  .banner{
    border-radius:14px;padding:16px 18px;margin-bottom:22px;font-weight:750;
    border:1px solid var(--line);
  }
  .banner.good{background:rgba(45,212,162,.1);color:#9af0d5}
  .banner.bad{background:rgba(251,87,114,.1);color:#ffc2cc}
  code{background:rgba(255,255,255,.06);padding:1px 6px;border-radius:5px;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
  <h1>فحص بيئة التشغيل</h1>
  <div class="sub">
    ToFi X Tv Live API 2026 — <?= e_html(date('Y-m-d H:i:s')) ?>
  </div>

  <div class="banner <?= $counts['fail'] === 0 ? 'good' : 'bad' ?>">
    <?php if ($counts['fail'] === 0): ?>
      لا توجد مشاكل حرجة. البيئة جاهزة للبث.
    <?php else: ?>
      يوجد <?= (int) $counts['fail'] ?> فحصًا فاشلًا يجب معالجته قبل الاعتماد
      على النظام. اقرأ الحل تحت كل بند.
    <?php endif; ?>
  </div>

  <div class="summary">
    <div class="pill" style="color:var(--ok)"><b><?= (int) $counts['ok'] ?></b>ناجح</div>
    <div class="pill" style="color:var(--warn)"><b><?= (int) $counts['warn'] ?></b>تنبيه</div>
    <div class="pill" style="color:var(--fail)"><b><?= (int) $counts['fail'] ?></b>فاشل</div>
    <div class="pill" style="color:var(--info)"><b><?= (int) $counts['info'] ?></b>معلومة</div>
  </div>

<?php
$groups = [];

foreach ($results as $result) {
    $groups[$result['group']][] = $result;
}

foreach ($groups as $groupName => $items):
?>
  <div class="group">
    <h2><?= e_html($groupName) ?></h2>
<?php foreach ($items as $item): ?>
    <div class="item <?= e_html($item['state']) ?>">
      <div class="row">
        <span class="dot"></span>
        <div>
          <div class="label"><?= e_html($item['label']) ?></div>
          <div class="detail"><?= e_html($item['detail']) ?></div>
<?php if ($item['fix'] !== '' && $item['state'] !== 'ok'): ?>
          <div class="fix"><?= e_html($item['fix']) ?></div>
<?php endif; ?>
        </div>
      </div>
    </div>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>

  <div class="sub">
    أضف <code>&amp;channel=10</code> لاختبار الجلب من المصدر،
    أو <code>&amp;format=json</code> للحصول على النتيجة كـ JSON.
  </div>
</div>
</body>
</html>
