<?php require __DIR__ . '/_shell.php'; admin_top('Security', 'security'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>New admin password (min 10 characters)</label>
    <input type="password" name="new_password" minlength="10" required>
    <button class="btn" type="submit">Update password</button>
  </form>
  <small class="hint">Default password on first install: <code><?= e(ADMIN_DEFAULT_PASSWORD) ?></code> — change it immediately.</small>
</div>
<?php admin_bottom();
