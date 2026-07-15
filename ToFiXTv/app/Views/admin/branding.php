<?php require __DIR__ . '/_shell.php'; admin_top('Logo & Branding', 'branding'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div>
        <label>Logo (light backgrounds)</label>
        <input type="file" name="logo" accept=".svg,.png,.webp,.jpg,.jpeg">
        <small class="hint">Current: <?= e($b['logo'] ?? 'default /assets/brand/logo.svg') ?></small>
      </div>
      <div>
        <label>Logo (dark backgrounds)</label>
        <input type="file" name="logo_dark" accept=".svg,.png,.webp,.jpg,.jpeg">
        <small class="hint">Current: <?= e($b['logo_dark'] ?? 'default /assets/brand/logo-dark.svg') ?></small>
      </div>
    </div>
    <button class="btn" type="submit">Upload</button>
    <button class="btn danger" type="submit" name="reset" value="1">Reset to defaults</button>
  </form>
</div>
<div class="card">
  <b>Default brand kit</b>
  <p style="color:#9FB0C8;font-size:12.5px;margin:6px 0 12px">SVG sources live in <code>public/assets/brand/</code> — logo, favicon, app icons, OG image and splash screen.</p>
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
    <img src="/assets/brand/logo-dark.svg" alt="logo" height="34">
    <img src="/assets/brand/icon-192.png" alt="icon" height="48" style="border-radius:12px">
    <img src="/assets/brand/og-default.png" alt="og" height="60" style="border-radius:8px">
  </div>
</div>
<?php admin_bottom();
