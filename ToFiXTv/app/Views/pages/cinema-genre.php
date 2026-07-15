<?php
/** Genre browse page. Expects: $type, $name, $items, $page, $pages, $base, $genres. */
use TofiXTv\Core\View;

$section = $type === 'tv' ? 'series' : 'movies';
?>
<section class="section container">
  <div class="section-head">
    <h1 class="page-title"><?= e($name) ?> — <?= e(t($type === 'tv' ? 'nav.series' : 'nav.movies')) ?></h1>
  </div>

  <?php if (!empty($genres)): ?>
  <div class="hscroll genre-chips">
    <?php foreach ($genres as $g): $gp = path($section . '/genre/' . slugify((string)$g['name'], 'genre') . '-' . (int)$g['id']); ?>
    <a class="chip<?= $gp === $base ? ' active' : '' ?>" href="<?= e($gp) ?>"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <p class="empty-note"><?= e(t('cinema.no_results')) ?></p>
  <?php else: ?>
  <div class="poster-grid">
    <?php foreach ($items as $item): ?>
      <?= View::partial('poster-card', ['item' => $item, 'type' => $type]) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="pagination">
    <?php if ($page > 1): ?>
    <a class="btn btn-ghost" href="<?= e($base . ($page - 1 > 1 ? '/page/' . ($page - 1) : '')) ?>">‹ <?= e(t('misc.back')) ?></a>
    <?php endif; ?>
    <span class="pager-info"><?= e(t('misc.page')) ?> <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?>
    <a class="btn btn-ghost" href="<?= e($base . '/page/' . ($page + 1)) ?>"><?= e(t('misc.show_more')) ?> ›</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
