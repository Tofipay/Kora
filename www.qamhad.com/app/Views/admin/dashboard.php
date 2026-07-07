<?php require __DIR__ . '/_shell.php'; admin_top('Dashboard', 'dashboard'); ?>
<div class="grid">
  <div class="kpi"><b><?= number_format($total) ?></b><span>Total page views</span></div>
  <div class="kpi"><b><?= number_format($cache['api_files']) ?></b><span>API cache files</span></div>
  <div class="kpi"><b><?= round(($cache['api_bytes'] + $cache['media_bytes']) / 1048576, 1) ?> MB</b><span>Cache size</span></div>
  <div class="kpi"><b><?= number_format($tokens) ?></b><span>Push subscribers</span></div>
  <div class="kpi"><b><?= number_format($emails) ?></b><span>Newsletter emails</span></div>
</div>
<div class="card">
  <b>Views — last 14 days</b>
  <?php $max = 1; foreach ($days as $d) $max = max($max, (int)($d['total'] ?? 0)); ?>
  <div class="bar">
    <?php foreach ($days as $day => $d): ?>
      <div style="height:<?= max(3, round(((int)($d['total'] ?? 0)) / $max * 100)) ?>%" title="<?= e($day) ?>: <?= (int)($d['total'] ?? 0) ?>"><span><?= e(substr($day, 5)) ?></span></div>
    <?php endforeach; ?>
    <?php if (empty($days)): ?><small class="hint">No data yet — analytics start counting as pages get visited.</small><?php endif; ?>
  </div>
</div>
<?php admin_bottom();
