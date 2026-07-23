<?php require __DIR__ . '/_shell.php'; admin_top('Theme Settings', 'theme'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div><label>Primary color</label><input type="text" name="primary" value="<?= e($t['primary'] ?? '#4C0ECD') ?>" placeholder="#4C0ECD"></div>
      <div><label>Accent color</label><input type="text" name="accent" value="<?= e($t['accent'] ?? '#7C4DFF') ?>" placeholder="#7C4DFF"></div>
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
