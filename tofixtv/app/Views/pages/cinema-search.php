<?php
/** Cinema search page. Expects: $q, $items, $page, $pages. */
use TofiXTv\Core\View;
?>
<section class="section container">
  <div class="section-head">
    <h1 class="page-title"><?= e($q !== '' ? t('cinema.search_for', ['q' => $q]) : t('cinema.search')) ?></h1>
  </div>

  <form class="cinema-search cinema-search-page" action="<?= e(path('cinema/search')) ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('cinema.search_ph')) ?>" aria-label="<?= e(t('cinema.search')) ?>" autofocus>
    <button class="btn btn-primary" type="submit"><?= e(t('nav.search')) ?></button>
  </form>

  <?php if ($q !== '' && empty($items)): ?>
    <p class="empty-note"><?= e(t('cinema.no_results')) ?></p>
  <?php elseif (!empty($items)): ?>
  <div class="poster-grid">
    <?php foreach ($items as $item): ?>
      <?= View::partial('poster-card', ['item' => $item]) ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="pagination">
    <?php $qs = 'q=' . rawurlencode($q); ?>
    <?php if ($page > 1): ?>
    <a class="btn btn-ghost" href="<?= e(path('cinema/search')) ?>?<?= $qs ?>&amp;page=<?= $page - 1 ?>">‹ <?= e(t('misc.back')) ?></a>
    <?php endif; ?>
    <span class="pager-info"><?= e(t('misc.page')) ?> <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?>
    <a class="btn btn-ghost" href="<?= e(path('cinema/search')) ?>?<?= $qs ?>&amp;page=<?= $page + 1 ?>"><?= e(t('misc.show_more')) ?> ›</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
