<?php
use TofiXTv\Core\ChannelCatalog;

$value = trim((string)($channel['play_value'] ?? ''));
$disabled = $value === '' || ($channel['status'] ?? 'live') === 'offline';
if (str_starts_with($value, 'intent://')) {
    $value = substr($value, strlen('intent://'));
    $intentAt = strpos($value, '#Intent;');
    if ($intentAt !== false) $value = substr($value, 0, $intentAt);
}
$href = $disabled ? '#' : app_intent_url($value);
$name = ChannelCatalog::label($channel);
$image = catalog_image($channel['logo'] ?? null);
?>
<article class="poster-card channel-poster-card<?= $disabled ? ' is-disabled' : '' ?>" id="channel-<?= (int)$channel['id'] ?>">
  <div class="poster-thumb channel-poster-thumb">
    <?php if ($disabled): ?>
      <div class="channel-poster-link" aria-disabled="true">
    <?php else: ?>
      <a class="channel-poster-link" href="<?= e($href) ?>" data-channel-intent data-app-download="https://t.me/alokalive" aria-label="<?= e(t('channels.watch', ['name' => $name])) ?>">
    <?php endif; ?>
        <img src="<?= e($image) ?>" alt="<?= e($name) ?>" width="342" height="513" loading="lazy" decoding="async">
        <span class="poster-caption"><span class="poster-title"><?= e($name) ?></span></span>
    <?php if ($disabled): ?></div><?php else: ?></a><?php endif; ?>

    <button class="poster-fav channel-card-favorite" type="button" data-fav="channel" data-id="<?= (int)$channel['id'] ?>"
            data-title="<?= e($name) ?>" data-url="<?= e($href) ?>" data-img="<?= e($image) ?>"
            data-play-type="intent" aria-label="<?= e(t('nav.favorites')) ?>">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
    </button>
  </div>
</article>
