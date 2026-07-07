<?php
/**
 * Admin shell — call admin_top($title, $active) then admin_bottom().
 */
function admin_top(string $title, string $active = ''): void
{
    $nav = [
        'dashboard'     => ['', 'Dashboard'],
        'branding'      => ['branding', 'Logo & Branding'],
        'seo'           => ['seo', 'SEO Manager'],
        'homepage'      => ['homepage', 'Homepage Builder'],
        'theme'         => ['theme', 'Theme'],
        'cache'         => ['cache', 'Cache Manager'],
        'notifications' => ['notifications', 'Notifications'],
        'streaming'     => ['streaming', 'Streaming'],
        'channels'      => ['channels', 'Channels Library'],
        'newsletter'    => ['newsletter', 'Newsletter'],
        'security'      => ['security', 'Security'],
    ];
    ?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Qamhad Live Admin</title>
<link rel="icon" href="/assets/brand/favicon.svg">
<style>
:root{--p:#16C784;--ink:#0F172A;--bg:#0B1220;--card:#111B2E;--line:rgba(230,237,247,.09);--tx:#E6EDF7;--tx2:#9FB0C8}
*{box-sizing:border-box;margin:0}
body{background:var(--bg);color:var(--tx);font:14px/1.6 'Inter',system-ui,sans-serif;display:flex;min-height:100vh}
a{color:inherit;text-decoration:none}
aside{width:230px;background:var(--card);border-right:1px solid var(--line);padding:20px 12px;flex-shrink:0}
aside .logo{display:flex;align-items:center;gap:8px;font-weight:800;padding:0 10px 18px;font-size:15px}
aside .logo i{width:12px;height:12px;border-radius:4px;background:var(--p);display:inline-block}
aside a.nav{display:block;padding:9px 12px;border-radius:9px;color:var(--tx2);font-weight:600;margin-bottom:2px}
aside a.nav:hover{background:rgba(255,255,255,.05);color:var(--tx)}
aside a.nav.on{background:rgba(22,199,132,.14);color:var(--p)}
main{flex:1;padding:28px;max-width:1000px}
h1{font-size:20px;margin-bottom:18px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px}
label{display:block;font-weight:600;color:var(--tx2);font-size:12.5px;margin:12px 0 5px}
input[type=text],input[type=password],input[type=email],textarea,select{width:100%;background:#0d1626;border:1px solid var(--line);border-radius:9px;padding:9px 12px;color:var(--tx);font:inherit}
textarea{min-height:80px}
.btn{display:inline-block;background:var(--p);color:#06281b;font-weight:800;padding:9px 20px;border-radius:999px;border:0;cursor:pointer;font:inherit;margin-top:14px}
.btn.ghost{background:transparent;border:1.5px solid var(--line);color:var(--tx)}
.btn.danger{background:#ef4444;color:#fff}
.msg{background:rgba(22,199,132,.12);border:1px solid rgba(22,199,132,.4);color:var(--p);padding:10px 16px;border-radius:10px;margin-bottom:16px;font-weight:600}
.grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;text-align:center}
.kpi b{font-size:26px;color:var(--p);display:block}
.kpi span{color:var(--tx2);font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left}
th{color:var(--tx2);font-size:11.5px}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row>*{flex:1;min-width:200px}
.sec-item{display:flex;align-items:center;gap:10px;background:#0d1626;border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-bottom:6px;cursor:grab}
.sec-item .mv{color:var(--tx2);cursor:pointer;padding:2px 7px;border-radius:6px}
.sec-item .mv:hover{background:rgba(255,255,255,.08)}
small.hint{color:var(--tx2);display:block;margin-top:4px}
.bar{display:flex;align-items:flex-end;gap:4px;height:120px;margin-top:10px}
.bar div{flex:1;background:linear-gradient(180deg,var(--p),#0d9488);border-radius:4px 4px 0 0;min-height:3px;position:relative}
.bar div span{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:9px;color:var(--tx2);white-space:nowrap}
</style>
</head>
<body>
<aside>
  <div class="logo"><i></i> Qamhad Live</div>
  <?php foreach ($nav as $key => [$p, $label]): ?>
    <a class="nav<?= $key === $active ? ' on' : '' ?>" href="/<?= ADMIN_PATH ?><?= $p ? '/' . $p : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="nav" href="/" target="_blank">↗ View site</a>
  <a class="nav" href="/<?= ADMIN_PATH ?>/logout" style="color:#ef4444">Logout</a>
</aside>
<main>
<h1><?= e($title) ?></h1>
<?php
}

function admin_bottom(): void
{
    echo '</main></body></html>';
}
