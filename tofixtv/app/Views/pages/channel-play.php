<?php use TofiXTv\Core\ChannelCatalog; ?>
<section class="section container channel-watch-page">
  <div class="section-head">
    <h1><?= e(ChannelCatalog::label($channel)) ?></h1>
    <a class="btn btn-ghost btn-sm" href="<?= e(channel_group_url($category, $group)) ?>"><?= e(t('channels.back_group')) ?></a>
  </div>
  <div class="channel-video glass">
    <video controls autoplay playsinline data-hls="<?= e((string)$channel['play_value']) ?>" poster="<?= e(catalog_image($channel['logo'] ?? null)) ?>"></video>
  </div>
  <div class="channel-watch-meta">
    <?php if (!empty($channel['quality'])): ?><span class="channel-badge quality"><?= e((string)$channel['quality']) ?></span><?php endif; ?>
    <span class="channel-badge status-<?= e((string)$channel['status']) ?>"><?= e(t('channels.status_' . (string)$channel['status'])) ?></span>
  </div>
</section>
