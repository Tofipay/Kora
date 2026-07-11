<?php use Qamhad\Core\View; ?>
<div class="container page-head">
  <h1><?= e(t('news.title')) ?></h1>
  <form class="inline-search" action="<?= e(path('search')) ?>" method="get">
    <input type="search" name="q" placeholder="<?= e(t('news.search')) ?>" aria-label="<?= e(t('news.search')) ?>">
  </form>
</div>

<div class="container">
<?php if (empty($items)): ?>
  <div class="empty-state glass-soft"><p><?= e(t('news.none')) ?></p></div>
<?php else: ?>
  <?php if ($page === 1): ?>
  <div class="news-grid">
    <?php foreach (array_slice($items, 0, 1) as $n): ?><?= View::partial('news-card', ['n' => $n, 'big' => true]) ?><?php endforeach; ?>
    <div class="news-side">
      <?php foreach (array_slice($items, 1, 4) as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
    </div>
  </div>
  <div class="news-list" style="margin-top:16px">
    <?php foreach (array_slice($items, 5) as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="news-list">
    <?php foreach ($items as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <nav class="pagination" aria-label="pagination">
    <?php if ($page > 1): ?>
      <a class="btn btn-ghost" href="<?= e($page === 2 ? path('news') : path('news/page/' . ($page - 1))) ?>" rel="prev"><?= e(t('news.prev')) ?></a>
    <?php endif; ?>
    <span class="page-indicator"><?= e(t('news.page', ['n' => $page])) ?></span>
    <?php if ($page < $last): ?>
      <a class="btn btn-ghost" href="<?= e(path('news/page/' . ($page + 1))) ?>" rel="next"><?= e(t('news.next')) ?></a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
</div>
