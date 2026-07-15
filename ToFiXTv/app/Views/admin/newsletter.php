<?php require __DIR__ . '/_shell.php'; admin_top('Newsletter', 'newsletter'); ?>
<div class="card">
  <b><?= count($list) ?> subscriber(s)</b>
  <a class="btn" style="float:right" href="/<?= ADMIN_PATH ?>/newsletter?export=1">Export CSV</a>
  <table style="margin-top:14px">
    <tr><th>Email</th><th>Subscribed at</th></tr>
    <?php foreach (array_slice(array_reverse($list), 0, 100) as $row): ?>
    <tr><td><?= e($row['email']) ?></td><td><?= e($row['at']) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php admin_bottom();
