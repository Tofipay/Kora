<?php require __DIR__ . '/_shell.php'; admin_top('Homepage Builder', 'homepage'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="card">
  <p style="color:#9FB0C8;font-size:13px;margin-bottom:12px">Reorder sections with the arrows and toggle visibility. Changes apply instantly to the public homepage.</p>
  <form method="post" id="hb-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="sections" id="hb-json">
    <div id="hb-list">
      <?php foreach ($sections as $s): ?>
      <div class="sec-item" data-id="<?= e($s['id']) ?>">
        <span class="mv" data-dir="-1">▲</span><span class="mv" data-dir="1">▼</span>
        <b style="flex:1;text-transform:capitalize"><?= e($s['id']) ?></b>
        <label style="margin:0;display:flex;gap:6px;align-items:center"><input type="checkbox" <?= $s['on'] ? 'checked' : '' ?>> visible</label>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn" type="submit">Save layout</button>
  </form>
</div>
<script>
const list = document.getElementById('hb-list');
list.addEventListener('click', e => {
  const mv = e.target.closest('.mv'); if (!mv) return;
  const item = mv.closest('.sec-item'), dir = +mv.dataset.dir;
  if (dir < 0 && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
  if (dir > 0 && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
});
document.getElementById('hb-form').addEventListener('submit', () => {
  const out = [...list.querySelectorAll('.sec-item')].map(el => ({ id: el.dataset.id, on: el.querySelector('input').checked }));
  document.getElementById('hb-json').value = JSON.stringify(out);
});
</script>
<?php admin_bottom();
