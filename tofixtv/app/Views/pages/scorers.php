<?php use TofiXTv\Core\View; ?>
<div class="container page-head">
  <h1><?= e(t('scorers.title')) ?></h1>
</div>
<div class="container">
<?php if (empty($boards)): ?>
  <div class="empty-state glass-soft"><p><?= e(t('scorers.none')) ?></p></div>
<?php else: ?>
  <div class="boards-grid">
  <?php foreach ($boards as $b): ?>
    <section class="card glass-soft reveal">
      <div class="section-head">
        <h2><?= e($b['title']) ?></h2>
        <a class="view-all" href="<?= e(league_url(['url_id' => $b['url_id'], 'title' => $b['title']])) ?>"><?= e(t('home.view_all')) ?></a>
      </div>
      <?= View::partial('scorers-table', ['scorers' => $b['scorers'], 'leagueId' => $b['url_id']]) ?>
    </section>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
