<?php
/**
 * صفحة أخطاء احترافية موحّدة تدعم جميع الرموز (403 / 404 / 502 ...).
 *
 * تُستخدَم بطريقتين:
 *   1) عبر ErrorDocument في ‎.htaccess‎  → تقرأ الرمز من REDIRECT_STATUS.
 *   2) عبر include داخل failResponse() في index.php → تُمرَّر $__errorStatus.
 *
 * الهدف: عدم إظهار خطأ مؤقت (مثل 502) على أنه "ممنوع الوصول". كل رمز
 * يظهر بعنوانه الصحيح. لا تلمس هذه الصفحة أي منطق للبث أو التخزين.
 */

$__included = isset($__errorStatus);
$__status   = $__included
    ? (int) $__errorStatus
    : (int) ($_SERVER['REDIRECT_STATUS'] ?? 403);

if ($__status < 400 || $__status > 599) {
    $__status = 403;
}

// عند الاستدعاء المباشر (ErrorDocument) نضبط الترويسات هنا؛ وعند التضمين
// من failResponse تكون الترويسات مضبوطة مسبقًا فلا نكررها.
if (!$__included && !headers_sent()) {
    http_response_code($__status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
}

// طلبات HEAD لا تحتاج جسمًا.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    return;
}

$__map = [
    400 => ['Bad Request',         'طلب غير صالح'],
    401 => ['Unauthorized',        'غير مُصرَّح'],
    403 => ['Forbidden',           'ممنوع الوصول'],
    404 => ['Not Found',           'الصفحة غير موجودة'],
    405 => ['Method Not Allowed',  'الطلب غير مسموح'],
    408 => ['Request Timeout',     'انتهت مهلة الطلب'],
    429 => ['Too Many Requests',   'طلبات كثيرة جدًا'],
    500 => ['Server Error',        'خطأ في الخادم'],
    502 => ['Bad Gateway',         'الخدمة غير متاحة مؤقتًا'],
    503 => ['Service Unavailable', 'الخدمة غير متاحة مؤقتًا'],
    504 => ['Gateway Timeout',     'انتهت مهلة الاتصال'],
];

[$__en, $__ar] = $__map[$__status] ?? ['Error', 'حدث خطأ'];
$__isServer = $__status >= 500;

$__badge = $__isServer ? 'Service Unavailable' : 'Access Denied';
$__msgAr = $__isServer
    ? 'الخدمة غير متاحة مؤقتًا، يُرجى المحاولة بعد قليل.'
    : 'عذراً، لا تملك صلاحية الوصول إلى هذه الصفحة، أو أنها غير موجودة على هذا الخادم.';
$__msgEn = $__isServer
    ? 'The service is temporarily unavailable. Please try again in a moment.'
    : 'You don&rsquo;t have permission to access this resource on this server.';

$__code = (string) $__status;
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#05070d">
<title><?= $__code ?> <?= $__en ?></title>
<style>
  :root{
    --bg:#05070d;
    --card:rgba(255,255,255,.035);
    --border:rgba(255,255,255,.09);
    --text:#e8edf7;
    --muted:#8a97ad;
    --a1:#38bdf8;
    --a2:#6366f1;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,
      "Noto Kufi Arabic","Segoe UI Arabic",Tahoma,"Helvetica Neue",Arial,sans-serif;
    background:
      radial-gradient(1200px 620px at 50% -12%, rgba(99,102,241,.20), transparent 60%),
      radial-gradient(900px 520px at 50% 118%, rgba(56,189,248,.12), transparent 60%),
      var(--bg);
    color:var(--text);
    min-height:100%;
    display:flex;align-items:center;justify-content:center;
    padding:24px;
    position:relative;
    overflow:hidden;
    -webkit-font-smoothing:antialiased;
  }
  body::before{
    content:"";position:fixed;inset:0;z-index:0;
    background-image:
      linear-gradient(rgba(255,255,255,.022) 1px,transparent 1px),
      linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px);
    background-size:46px 46px;
    -webkit-mask-image:radial-gradient(circle at 50% 42%,#000,transparent 72%);
    mask-image:radial-gradient(circle at 50% 42%,#000,transparent 72%);
    pointer-events:none;
  }
  .card{
    position:relative;z-index:1;
    width:min(560px,100%);
    background:var(--card);
    border:1px solid var(--border);
    border-radius:24px;
    padding:clamp(30px,6vw,54px);
    text-align:center;
    backdrop-filter:blur(14px);
    -webkit-backdrop-filter:blur(14px);
    box-shadow:0 34px 90px -24px rgba(0,0,0,.75), inset 0 1px 0 rgba(255,255,255,.05);
  }
  .badge{
    display:inline-flex;align-items:center;gap:8px;
    font-size:12px;letter-spacing:.18em;text-transform:uppercase;
    color:var(--muted);
    border:1px solid var(--border);border-radius:999px;
    padding:7px 15px;margin-bottom:26px;
    direction:ltr;
  }
  .badge .dot{
    width:7px;height:7px;border-radius:50%;background:var(--a1);
    box-shadow:0 0 12px var(--a1);
    animation:pulse 2.4s ease-in-out infinite;
  }
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.45;transform:scale(.82)}}
  .shield{
    width:clamp(80px,20vw,104px);height:auto;margin:0 auto 22px;display:block;
    filter:drop-shadow(0 10px 30px rgba(56,189,248,.35));
    animation:float 5.5s ease-in-out infinite;
  }
  @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
  .code{
    font-size:clamp(70px,18vw,124px);
    font-weight:800;line-height:1;letter-spacing:-.03em;
    background:linear-gradient(135deg,var(--a1),var(--a2));
    -webkit-background-clip:text;background-clip:text;color:transparent;
    margin-bottom:8px;
  }
  h1{
    font-size:clamp(19px,4.5vw,25px);font-weight:700;letter-spacing:.01em;
  }
  h1 .en{
    display:block;font-size:.6em;letter-spacing:.34em;text-transform:uppercase;
    color:var(--muted);font-weight:600;margin-top:9px;direction:ltr;
  }
  .divider{
    height:1px;width:66px;margin:22px auto;
    background:linear-gradient(90deg,transparent,var(--border),transparent);
  }
  p{
    color:var(--muted);font-size:clamp(14px,3.4vw,15.5px);line-height:1.9;
    max-width:44ch;margin:0 auto;
  }
  p .en{display:block;direction:ltr;margin-top:9px;font-size:.9em;opacity:.82}
  .foot{
    margin-top:30px;font-size:11.5px;letter-spacing:.16em;text-transform:uppercase;
    color:rgba(138,151,173,.55);direction:ltr;
  }
  @media (prefers-reduced-motion:reduce){
    .shield,.badge .dot{animation:none}
  }
</style>
</head>
<body>
  <main class="card" role="alert">
    <span class="badge"><span class="dot"></span> <?= $__badge ?></span>
    <svg class="shield" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">
          <stop stop-color="#38bdf8"/><stop offset="1" stop-color="#6366f1"/>
        </linearGradient>
      </defs>
      <path d="M12 2.5 4.5 5.6v5.2c0 4.6 3.2 8.9 7.5 10.2 4.3-1.3 7.5-5.6 7.5-10.2V5.6L12 2.5Z"
            stroke="url(#g)" stroke-width="1.4" stroke-linejoin="round"/>
      <rect x="9" y="10.6" width="6" height="5" rx="1.2" stroke="url(#g)" stroke-width="1.4"/>
      <path d="M10.3 10.6V9.3a1.7 1.7 0 0 1 3.4 0v1.3" stroke="url(#g)" stroke-width="1.4" stroke-linecap="round"/>
    </svg>
    <div class="code"><?= $__code ?></div>
    <h1><?= $__ar ?><span class="en"><?= $__en ?></span></h1>
    <div class="divider"></div>
    <p>
      <?= $__msgAr ?>
      <span class="en"><?= $__msgEn ?></span>
    </p>
    <div class="foot">Secure Streaming Gateway</div>
  </main>
</body>
</html>
