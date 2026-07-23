<?php require __DIR__ . '/_shell.php'; admin_top('Cache Manager', 'cache'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<div class="grid">
  <div class="kpi"><b><?= number_format($stats['api_files']) ?></b><span>API cache files (<?= round($stats['api_bytes'] / 1048576, 1) ?> MB)</span></div>
  <div class="kpi"><b><?= number_format($stats['media_files']) ?></b><span>Cached media (<?= round($stats['media_bytes'] / 1048576, 1) ?> MB)</span></div>
</div>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn" type="submit" name="flush_api" value="1">Flush API cache</button>
    <button class="btn ghost" type="submit" name="flush_media" value="1">Flush media cache</button>
  </form>
  <small class="hint">API data refreshes automatically (live: 60s · fixtures: 5min · news: 15min · leagues: 60min). Media is cached 7 days and converted to WebP on the fly.</small>
</div>

<div class="card">
  <b>Deployment / PHP OPcache</b>
  <p style="margin:6px 0;color:#9FB0C8;font-size:13px">
    Running SEO build: <code><?= e($seoBuild ?? '') ?></code>
    &nbsp;·&nbsp; OPcache: <?= !empty($opcacheOn) ? '<span style="color:#4C0ECD">enabled</span>' : 'disabled' ?>
  </p>
  <p style="margin:6px 0;color:#9FB0C8;font-size:13px">
    Uploaded new PHP files but the site looks unchanged? Some hosts keep the old
    compiled code in OPcache. Click below to force PHP to load your latest code.
  </p>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn" type="submit" name="flush_opcache" value="1">Clear OPcache (apply new code now)</button>
  </form>
  <small class="hint">To verify the live code: open your site’s page source and search for
    <code>qseo-build</code> — it must show <code><?= e($seoBuild ?? '') ?></code>.</small>
</div>
<?php admin_bottom();
