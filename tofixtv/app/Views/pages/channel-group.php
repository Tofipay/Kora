<?php
use TofiXTv\Core\ChannelCatalog;
use TofiXTv\Core\View;
?>
<div class="channel-group-topbar">
  <div class="container">
    <a class="channel-back-icon" href="<?= e(path('channels')) ?>" aria-label="<?= e(t('channels.all')) ?>">
      <svg viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 6-6 6 6 6"/></svg>
    </a>
    <h1><?= e(ChannelCatalog::label($group)) ?></h1>
    <button class="channel-refresh" type="button" onclick="location.reload()" aria-label="<?= e(t('misc.update_now')) ?>">
      <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6v5h-5M4 18v-5h5M18.5 9A7 7 0 0 0 6 7M5.5 15A7 7 0 0 0 18 17"/></svg>
    </button>
  </div>
</div>

<section class="section container channel-listing-section">
  <?php if (empty($channels)): ?>
    <div class="empty-state glass-soft"><p><?= e(t('channels.empty_group')) ?></p></div>
  <?php else: ?>
    <div class="poster-grid channel-poster-grid">
      <?php foreach ($channels as $channel): ?><?= View::partial('channel-card', ['channel' => $channel]) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
