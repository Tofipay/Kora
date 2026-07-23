<?php use TofiXTv\Core\License; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>تفعيل الموقع · ALOKA Live</title>
<link rel="icon" href="/assets/brand/favicon.svg">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;
  background:radial-gradient(900px 520px at 50% -10%,#2A0A83,#1B0761 50%,#10033D);
  color:#EDE9FF;font:14px/1.7 'Cairo','Segoe UI',system-ui,sans-serif}
.box{width:min(400px,94vw);background:rgba(27,7,97,.55);border:1px solid rgba(124,77,255,.3);
  border-radius:20px;padding:30px;box-shadow:0 24px 64px rgba(0,0,0,.4);
  -webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px)}
.brand{display:flex;align-items:center;gap:10px;font-weight:900;font-size:17px;margin-bottom:6px}
.brand .dot{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;
  background:linear-gradient(150deg,#5B12E7,#340996 55%,#1B0761)}
.brand .dot svg{width:22px;height:22px}
p.sub{color:#C6B8FF;font-size:13px;margin:0 0 18px}
label{display:block;font-size:12.5px;color:#B39DFF;font-weight:700;margin:0 0 7px}
input{width:100%;box-sizing:border-box;background:#10033d;border:1px solid rgba(124,77,255,.28);
  border-radius:11px;padding:12px 14px;color:#EDE9FF;font:inherit;letter-spacing:1px;direction:ltr;text-align:center}
input:focus{outline:none;border-color:#7C4DFF;box-shadow:0 0 0 3px rgba(124,77,255,.25)}
button{width:100%;margin-top:16px;background:linear-gradient(135deg,#7C4DFF,#4C0ECD);border:0;color:#fff;
  font-weight:800;padding:13px;border-radius:999px;cursor:pointer;font:inherit;font-size:15px;
  box-shadow:0 12px 30px rgba(76,14,205,.45)}
button:hover{filter:brightness(1.06)}
.msg{border-radius:11px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600}
.msg.err{background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.4);color:#fca5a5}
.msg.ok{background:rgba(76,14,205,.16);border:1px solid rgba(124,77,255,.45);color:#C6B8FF}
.dom{margin-top:14px;font-size:12px;color:#9A86D8;text-align:center;direction:ltr}
.logout{display:block;text-align:center;margin-top:16px;color:#9A86D8;font-size:12px;text-decoration:none}
</style></head><body>
<form class="box" method="post" action="/<?= ADMIN_PATH ?>/activate">
  <div class="brand">
    <span class="dot"><svg viewBox="0 0 1024 1024"><path fill="#fff" fill-rule="evenodd" d="M512 96c-45 0-81 28-100 72L118 866c-13 31 10 62 43 62h143c19 0 36-11 43-29l71-179h188l71 179c7 18 24 29 43 29h143c33 0 56-31 43-62L612 168c-19-44-55-72-100-72Zm-67 296c0-21 23-34 41-23l165 105c17 11 17 37 0 48L486 627c-18 11-41-2-41-23V392Zm10 328h114l-57 145-57-145Z"/></svg></span>
    ALOKA Live
  </div>
  <p class="sub">تفعيل الموقع — أدخل كود التفعيل مرة واحدة لبدء التشغيل.</p>
  <?php if (!empty($msg)): ?><div class="msg <?= !empty($err) ? 'err' : 'ok' ?>"><?= e($msg) ?></div><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <label for="license_code">كود التفعيل</label>
  <input id="license_code" type="text" name="license_code" placeholder="XXXXXXXXXXXXXXX" autocomplete="off" autofocus required>
  <button type="submit">تفعيل الموقع</button>
  <div class="dom">Domain: <?= e(License::currentDomain()) ?></div>
  <a class="logout" href="/<?= ADMIN_PATH ?>/logout">تسجيل الخروج</a>
</form>
</body></html>
