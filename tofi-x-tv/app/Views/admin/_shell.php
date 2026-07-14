<?php
/**
 * Admin shell — modern RTL Arabic dashboard chrome.
 * Call admin_top($title, $active) then admin_bottom().
 * Design language: glassy dark surface, brand-green accents, responsive
 * (fixed sidebar on desktop, horizontal scroll nav on mobile).
 */
function admin_top(string $title, string $active = ''): void
{
    $nav = [
        'dashboard'     => ['',              'لوحة التحكم',      'M3 12l9-9 9 9M5 10v10h14V10'],
        'branding'      => ['branding',      'الشعار والهوية',   'M4 4h16v6H4zM4 14h10v6H4'],
        'seo'           => ['seo',           'تحسين الظهور SEO', 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.3-4.3'],
        'homepage'      => ['homepage',      'بناء الصفحة',      'M4 5h16M4 12h16M4 19h10'],
        'theme'         => ['theme',         'المظهر',           'M12 3a9 9 0 1 0 9 9c0-.5-4-.5-4-3s3-2.5 3-3a9 9 0 0 0-8-3z'],
        'cache'         => ['cache',         'الكاش',            'M4 7c0-2 4-3 8-3s8 1 8 3-4 3-8 3-8-1-8-3zM4 7v10c0 2 4 3 8 3s8-1 8-3V7'],
        'notifications' => ['notifications', 'الإشعارات',        'M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9'],
        'streaming'     => ['streaming',     'البث المباشر',     'M2 5h20v14H2zM10 9l5 3-5 3z'],
        'channels'      => ['channels',      'مكتبة القنوات',    'M4 5h16v14H4zM8 5v14M16 5v14'],
        'newsletter'    => ['newsletter',    'النشرة البريدية',  'M3 5h18v14H3zM3 7l9 6 9-6'],
        'security'      => ['security',      'الأمان',           'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z'],
    ];
    ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="dark">
<title><?= e($title) ?> · لوحة تحكم توفي إكس تيفي</title>
<link rel="icon" href="/assets/brand/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap">
<style>
:root{
  --p:#16C784;--p2:#22D3EE;--ink:#080E1A;--bg:#080E1A;--card:#101A2D;--card2:#16223A;
  --line:rgba(233,239,248,.09);--tx:#E9EFF8;--tx2:#A3B3CA;--tx3:#6B7C96;
  --shadow:0 8px 30px rgba(0,0,0,.4);--r:16px;
  --danger:#ef4444;--warn:#f59e0b;
}
*{box-sizing:border-box;margin:0}
body{background:
  radial-gradient(700px 380px at 90% -5%,rgba(34,211,238,.10),transparent 70%),
  radial-gradient(760px 420px at 5% 0%,rgba(22,199,132,.12),transparent 70%),
  var(--bg);
  color:var(--tx);font:14px/1.7 'Cairo',system-ui,sans-serif;min-height:100vh;
  display:grid;grid-template-columns:250px 1fr;
  -webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}

/* Sidebar */
aside{
  position:sticky;top:0;align-self:start;height:100vh;overflow-y:auto;
  background:rgba(16,26,45,.72);-webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px);
  border-inline-start:1px solid var(--line);padding:20px 14px}
aside .brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:16px;padding:0 8px 20px}
aside .brand .dot{width:30px;height:30px;border-radius:9px;flex:none;display:grid;place-items:center;
  background:linear-gradient(135deg,var(--p),var(--p2));color:#06281b}
aside a.nav{display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:12px;color:var(--tx2);
  font-weight:700;margin-bottom:3px;transition:.18s}
aside a.nav svg{width:18px;height:18px;flex:none;opacity:.85}
aside a.nav:hover{background:rgba(255,255,255,.05);color:var(--tx)}
aside a.nav.on{background:linear-gradient(135deg,rgba(22,199,132,.18),rgba(34,211,238,.10));color:var(--p)}
aside .sep{height:1px;background:var(--line);margin:12px 6px}
aside a.nav.out{color:#f87171}

/* Main */
main{padding:26px clamp(16px,3vw,34px);max-width:1180px;width:100%;min-width:0;overflow-x:hidden}
/* Grid/flex children default to min-width:auto which lets fixed-width inner
   columns overflow the track — pin them to 0 so bars shrink correctly. */
.cols>*,.stat-grid>*,.grid>*{min-width:0}
.top{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.top h1{font-size:22px;font-weight:900}
.top .muted{color:var(--tx3);font-size:12.5px;font-weight:700}

/* Cards & primitives */
.card{background:rgba(16,26,45,.7);border:1px solid var(--line);border-radius:var(--r);padding:20px;margin-bottom:16px;box-shadow:var(--shadow)}
.card>b{font-size:15px;font-weight:800;display:block;margin-bottom:4px}
label{display:block;font-weight:700;color:var(--tx2);font-size:12.5px;margin:12px 0 5px}
input[type=text],input[type=password],input[type=email],input[type=url],textarea,select{
  width:100%;background:#0b1424;border:1px solid var(--line);border-radius:11px;padding:10px 13px;color:var(--tx);font:inherit}
input:focus,textarea:focus,select:focus{outline:2px solid rgba(22,199,132,.5);outline-offset:1px;border-color:transparent}
textarea{min-height:84px}
.btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--p),color-mix(in srgb,var(--p) 70%,var(--p2)));
  color:#06281b;font-weight:800;padding:10px 22px;border-radius:999px;border:0;cursor:pointer;font:inherit;margin-top:14px;transition:.15s}
.btn:hover{transform:translateY(-1px)}
.btn:active{transform:scale(.97)}
.btn.ghost{background:transparent;border:1.5px solid var(--line);color:var(--tx)}
.btn.danger{background:var(--danger);color:#fff}
.msg{background:rgba(22,199,132,.12);border:1px solid rgba(22,199,132,.4);color:var(--p);padding:11px 16px;border-radius:12px;margin-bottom:16px;font-weight:700}

/* Stat cards */
.stat-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:16px}
.stat{background:rgba(16,26,45,.7);border:1px solid var(--line);border-radius:var(--r);padding:16px 18px;position:relative;overflow:hidden}
.stat::before{content:"";position:absolute;inset-block:0;inset-inline-start:0;width:3px;background:linear-gradient(180deg,var(--p),var(--p2))}
.stat .lbl{color:var(--tx3);font-size:12px;font-weight:700;display:flex;align-items:center;gap:7px}
.stat .lbl svg{width:15px;height:15px}
.stat b{font-size:27px;font-weight:900;display:block;margin-top:6px;font-variant-numeric:tabular-nums}
.stat.live b{color:var(--p)}
.stat .pulse{width:8px;height:8px;border-radius:50%;background:var(--p);box-shadow:0 0 0 0 rgba(22,199,132,.5);animation:pl 1.6s infinite}
@keyframes pl{0%{box-shadow:0 0 0 0 rgba(22,199,132,.45)}70%{box-shadow:0 0 0 8px rgba(22,199,132,0)}100%{box-shadow:0 0 0 0 rgba(22,199,132,0)}}

/* Two-column layout */
.cols{display:grid;gap:16px;grid-template-columns:1fr}
@media(min-width:900px){.cols.c2{grid-template-columns:1fr 1fr}}

/* Bar chart (14-day) */
.bar{display:flex;align-items:flex-end;gap:5px;height:130px;margin-top:16px}
.bar div{flex:1;background:linear-gradient(180deg,var(--p),#0d9488);border-radius:5px 5px 0 0;min-height:4px;position:relative;transition:height .5s cubic-bezier(.2,.7,.3,1)}
.bar div:hover{filter:brightness(1.15)}
.bar div span{position:absolute;bottom:-20px;inset-inline-start:50%;transform:translateX(50%);font-size:9px;color:var(--tx3);white-space:nowrap}

/* Horizontal metric bars (sources/devices/browsers) */
.metric{display:flex;flex-direction:column;gap:12px;margin-top:8px}
.metric .mrow{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:700;flex-wrap:nowrap}
.metric .mrow .name{flex:0 0 100px;min-width:0;color:var(--tx2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.metric .mrow .track{flex:1 1 auto;min-width:0;height:10px;border-radius:999px;background:#0b1424;overflow:hidden}
.metric .mrow .fill{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--p2),var(--p));box-shadow:0 0 10px rgba(22,199,132,.35);transition:width .8s cubic-bezier(.2,.7,.3,1)}
.metric .mrow .val{flex:0 0 50px;min-width:0;color:var(--tx);text-align:end;font-variant-numeric:tabular-nums}

/* Ranked lists (top matches/news/videos) */
.rank{list-style:none;margin:8px 0 0;padding:0}
.rank li{display:flex;align-items:center;gap:11px;padding:9px 2px;border-bottom:1px dashed var(--line)}
.rank li:last-child{border-bottom:0}
.rank .n{width:24px;height:24px;flex:none;border-radius:8px;background:#0b1424;display:grid;place-items:center;font-weight:800;font-size:12px;color:var(--tx2)}
.rank li:first-child .n{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#451a03}
.rank .t{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:700}
.rank .c{color:var(--p);font-weight:800;font-variant-numeric:tabular-nums}
.empty{color:var(--tx3);font-size:13px;padding:14px 0;text-align:center}

/* Update-site hero button */
.update-hero{display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:space-between;
  background:linear-gradient(120deg,rgba(22,199,132,.14),rgba(34,211,238,.08));border:1px solid rgba(22,199,132,.3)}
.update-hero .u-txt b{font-size:16px}
.update-hero .u-txt p{color:var(--tx2);font-size:12.5px;margin-top:2px}
.update-hero .btn{margin-top:0;font-size:15px;padding:13px 28px}

table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:start}
th{color:var(--tx3);font-size:11.5px;font-weight:800}
.row{display:flex;gap:12px;flex-wrap:wrap}.row>*{flex:1;min-width:200px}
.sec-item{display:flex;align-items:center;gap:10px;background:#0b1424;border:1px solid var(--line);border-radius:11px;padding:10px 14px;margin-bottom:6px;cursor:grab}
.sec-item .mv{color:var(--tx2);cursor:pointer;padding:2px 7px;border-radius:6px}
.sec-item .mv:hover{background:rgba(255,255,255,.08)}
small.hint{color:var(--tx3);display:block;margin-top:4px}
.kpi{background:rgba(16,26,45,.7);border:1px solid var(--line);border-radius:var(--r);padding:18px;text-align:center}
.kpi b{font-size:26px;color:var(--p);display:block}.kpi span{color:var(--tx2);font-size:12px}
.grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}

/* Responsive: collapse sidebar to a top nav on tablet/phone */
@media(max-width:860px){
  body{grid-template-columns:1fr}
  aside{position:sticky;top:0;height:auto;display:flex;gap:6px;overflow-x:auto;padding:12px;
    border-inline-start:0;border-bottom:1px solid var(--line);z-index:10}
  aside .brand{display:none}
  aside .sep{display:none}
  aside a.nav{flex:none;white-space:nowrap;padding:9px 14px}
  aside a.nav span{font-size:13px}
}
</style>
</head>
<body>
<aside>
  <div class="brand"><span class="dot"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M4 13l4-4 4 4 8-8"/></svg></span> توفي إكس تيفي</div>
  <?php foreach ($nav as $key => [$p, $label, $d]): ?>
    <a class="nav<?= $key === $active ? ' on' : '' ?>" href="/<?= ADMIN_PATH ?><?= $p ? '/' . $p : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="<?= $d ?>"/></svg>
      <span><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
  <div class="sep"></div>
  <a class="nav" href="/" target="_blank">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M7 17 17 7M8 7h9v9"/></svg><span>زيارة الموقع</span>
  </a>
  <a class="nav out" href="/<?= ADMIN_PATH ?>/logout">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M16 17l5-5-5-5M21 12H9M9 3H5v18h4"/></svg><span>تسجيل الخروج</span>
  </a>
</aside>
<main>
<div class="top"><h1><?= e($title) ?></h1></div>
<?php
}

function admin_bottom(): void
{
    echo '</main></body></html>';
}
