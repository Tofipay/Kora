<?php use Qamhad\Core\View; ?>
<div class="container page-head">
  <h1><?= e(t('standings.title')) ?></h1>
</div>
<div class="container">
<?php if (empty($tables)): ?>
  <div class="empty-state glass-soft"><p><?= e(t('standings.none')) ?></p></div>
<?php else: ?>
  <?php foreach ($tables as $tb): ?>
  <section class="section reveal">
    <div class="section-head">
      <h2><?= e($tb['title']) ?></h2>
      <a class="view-all" href="<?= e(league_url(['url_id' => $tb['url_id'], 'title' => $tb['title']])) ?>"><?= e(t('home.view_all')) ?></a>
    </div>
    <div class="card glass-soft"><?= View::partial('standings-table', ['rows' => $tb['rows']]) ?></div>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
</div>
