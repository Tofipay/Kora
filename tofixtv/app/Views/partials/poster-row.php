<?php
/**
 * Horizontal poster rail. Expects: $title, $items, optional $type, $moreUrl.
 */
use TofiXTv\Core\View;

if (empty($items)) return;
?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e($title) ?></h2>
    <?php if (!empty($moreUrl)): ?>
    <a class="view-all" href="<?= e($moreUrl) ?>"><?= e(t('cinema.view_all')) ?></a>
    <?php endif; ?>
  </div>
  <div class="hscroll poster-rail">
    <?php foreach ($items as $item): ?>
      <?= View::partial('poster-card', ['item' => $item, 'type' => $type ?? null]) ?>
    <?php endforeach; ?>
  </div>
</section>
