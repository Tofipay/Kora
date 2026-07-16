<?php require __DIR__ . '/_shell.php'; admin_top('Theme Settings', 'theme'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div><label>Primary color</label><input type="text" name="primary" value="<?= e($t['primary'] ?? '#16C784') ?>" placeholder="#16C784"></div>
      <div><label>Accent color</label><input type="text" name="accent" value="<?= e($t['accent'] ?? '#22D3EE') ?>" placeholder="#22D3EE"></div>
      <div><label>Default mode</label>
        <select name="default_mode">
          <?php foreach (['auto', 'light', 'dark'] as $mo): ?>
          <option value="<?= $mo ?>" <?= ($t['default_mode'] ?? 'auto') === $mo ? 'selected' : '' ?>><?= ucfirst($mo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn" type="submit">Save</button>
  </form>
</div>
<?php admin_bottom();
