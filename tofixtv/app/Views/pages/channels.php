<?php
use TofiXTv\Core\ChannelCatalog;

$slides = is_array($slides ?? null) ? $slides : [];
?>

<?php if ($slides): ?>
<section class="container channel-feature-slider" data-channel-slider aria-roledescription="carousel" aria-label="<?= e(t('channels.slider_label')) ?>">
  <div class="channel-feature-track">
    <?php foreach ($slides as $index => $slide):
      $slideUrl = ChannelCatalog::slideUrl($slide);
      $slideTitle = ChannelCatalog::label($slide, 'title');
      $slideAlt = $slideTitle !== '' ? $slideTitle : t('channels.slider_image');
      $slideDescription = ChannelCatalog::label($slide, 'description');
      $slideButton = ChannelCatalog::label($slide, 'button');
      $hasContent = $slideTitle !== '' || $slideDescription !== '' || ($slideButton !== '' && $slideUrl !== '');
      $tag = $slideUrl !== '' ? 'a' : 'div';
      $external = $slideUrl !== '' && preg_match('#^https?://#i', $slideUrl) === 1;
    ?>
      <<?= $tag ?> class="channel-feature-slide<?= $index === 0 ? ' is-active' : '' ?>"
        <?= $slideUrl !== '' ? 'href="' . e($slideUrl) . '"' : '' ?>
        <?= $external ? 'target="_blank" rel="noopener"' : '' ?>
        data-channel-slide aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>">
        <img src="<?= e(catalog_image($slide['image'] ?? null)) ?>" alt="<?= e($slideAlt) ?>"
             width="1600" height="700" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
        <?php if ($hasContent): ?>
          <span class="channel-feature-shade" aria-hidden="true"></span>
          <span class="channel-feature-content">
            <?php if ($slideTitle !== ''): ?><strong><?= e($slideTitle) ?></strong><?php endif; ?>
            <?php if ($slideDescription !== ''): ?><span class="channel-feature-description"><?= e($slideDescription) ?></span><?php endif; ?>
            <?php if ($slideButton !== '' && $slideUrl !== ''): ?>
              <span class="channel-feature-cta"><?= e($slideButton) ?>
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 18 6-6-6-6"/></svg>
              </span>
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </<?= $tag ?>>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?>
    <button class="channel-feature-arrow is-prev" type="button" data-channel-prev aria-label="<?= e(t('channels.slider_previous')) ?>">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 6-6 6 6 6"/></svg>
    </button>
    <button class="channel-feature-arrow is-next" type="button" data-channel-next aria-label="<?= e(t('channels.slider_next')) ?>">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 6 6 6-6 6"/></svg>
    </button>
    <div class="channel-feature-dots" role="tablist" aria-label="<?= e(t('channels.slider_navigation')) ?>">
      <?php foreach ($slides as $index => $_): ?>
        <button class="channel-feature-dot<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-channel-goto="<?= $index ?>" aria-label="<?= e(t('channels.slider_go', ['number' => $index + 1])) ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="container channels-page channels-catalog-page">
<?php if (empty($categories)): ?>
  <div class="empty-state glass-soft"><p><?= e(t('channels.empty')) ?></p></div>
<?php else: ?>
  <?php foreach ($categories as $category): $categoryGroups = $groups[(int)$category['id']] ?? []; ?>
    <?php if (!empty($categoryGroups)): ?>
    <section class="section channel-category" id="<?= e((string)$category['slug']) ?>">
      <div class="section-head"><h2><?= e(ChannelCatalog::label($category)) ?></h2></div>
      <div class="hscroll poster-rail channel-group-rail" role="list">
        <?php foreach ($categoryGroups as $group): ?>
        <a class="poster-card channel-group-poster" href="<?= e(channel_group_url($category, $group)) ?>" role="listitem" aria-label="<?= e(ChannelCatalog::label($group)) ?>">
          <span class="poster-thumb channel-poster-thumb">
            <img src="<?= e(catalog_image($group['image'] ?? null)) ?>" alt="<?= e(ChannelCatalog::label($group)) ?>" width="342" height="513" loading="lazy" decoding="async">
            <span class="poster-caption"><span class="poster-title"><?= e(ChannelCatalog::label($group)) ?></span></span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
</div>
