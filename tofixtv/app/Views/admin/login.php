<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Login · ALOKA Live Admin</title>
<link rel="icon" href="/assets/brand/favicon.svg">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0B1220;color:#E6EDF7;font:14px/1.6 'Inter',system-ui,sans-serif}
.box{width:min(360px,92vw);background:#111B2E;border:1px solid rgba(230,237,247,.09);border-radius:18px;padding:30px}
h1{font-size:18px;margin:0 0 6px;display:flex;gap:8px;align-items:center}
h1 i{width:12px;height:12px;border-radius:4px;background:#4C0ECD}
p{color:#9FB0C8;font-size:12.5px;margin:0 0 18px}
input{width:100%;box-sizing:border-box;background:#0d1626;border:1px solid rgba(230,237,247,.09);border-radius:10px;padding:11px 14px;color:#E6EDF7;font:inherit}
button{width:100%;margin-top:14px;background:#4C0ECD;border:0;color:#ffffff;font-weight:800;padding:11px;border-radius:999px;cursor:pointer;font:inherit}
.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);color:#fca5a5;border-radius:10px;padding:9px 14px;margin-bottom:14px;font-size:13px}
</style></head><body>
<form class="box" method="post">
  <h1><i></i> ALOKA Live</h1>
  <p>Admin panel — sign in to continue</p>
  <?php if (!empty($error)): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="password" name="password" placeholder="Password" autofocus required>
  <button type="submit">Sign in</button>
</form>
</body></html>
