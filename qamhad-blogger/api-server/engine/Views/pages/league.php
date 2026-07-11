<?php use Qamhad\Core\View; ?>
<section class="entity-hero">
  <div class="mh-bg" aria-hidden="true"></div>
  <div class="container entity-inner">
    <img class="entity-logo" src="<?= e(league_img($image, '128')) ?>" alt="<?= e($title) ?>" width="72" height="72">
    <div>
      <h1><?= e($title) ?></h1>
      <p class="page-sub"><?= e(t('leagues.title')) ?></p>
    </div>
    <button class="fav-btn standalone" data-fav="league" data-id="<?= (int)$id ?>" data-title="<?= e($title) ?>" data-url="<?= e(league_url(['url_id' => $id, 'title' => $title])) ?>" data-img="<?= e(league_img($image, '128')) ?>" onclick="QF.toggle(this)" aria-label="<?= e(t('fav.add')) ?>">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
    </button>
  </div>
  <div class="container">
    <nav class="tabs glass-soft" role="tablist">
      <button class="tab active" data-tab="standings"><?= e(t('standings.title')) ?></button>
      <button class="tab" data-tab="fixtures"><?= e(t('league.fixtures')) ?></button>
      <button class="tab" data-tab="results"><?= e(t('league.results')) ?></button>
      <button class="tab" data-tab="scorers"><?= e(t('scorers.title')) ?></button>
      <button class="tab" data-tab="assists"><?= e(t('assists.title')) ?></button>
      <button class="tab" data-tab="news"><?= e(t('league.news')) ?></button>
    </nav>
  </div>
</section>

<div class="container match-body">
  <section class="tab-panel active" data-panel="standings">
    <?php if (empty($rows)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('standings.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft"><?= View::partial('standings-table', ['rows' => $rows]) ?></div>
      <?php if (!empty($rules)): ?>
      <details class="rules-box glass-soft">
        <summary><?= e(t('standings.rules')) ?></summary>
        <ol><?php foreach ($rules as $rule): ?><li><?= e((string)$rule) ?></li><?php endforeach; ?></ol>
      </details>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="fixtures">
    <?php if (empty($fixtures)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('matches.none')) ?></p></div>
    <?php else: ?>
      <div class="league-matches">
        <?php $lastDate = ''; foreach ($fixtures as $fm): $d = (string)($fm['match_date'] ?? ''); ?>
          <?php if ($d !== $lastDate): $lastDate = $d; ?><h4 class="date-sep"><?= e(format_date_long($d)) ?></h4><?php endif; ?>
          <?= View::partial('match-card', ['m' => $fm]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="results">
    <?php if (empty($results)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('matches.none')) ?></p></div>
    <?php else: ?>
      <div class="league-matches">
        <?php $lastDate = ''; foreach ($results as $fm): $d = (string)($fm['match_date'] ?? ''); ?>
          <?php if ($d !== $lastDate): $lastDate = $d; ?><h4 class="date-sep"><?= e(format_date_long($d)) ?></h4><?php endif; ?>
          <?= View::partial('match-card', ['m' => $fm]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="scorers">
    <?php if (empty($scorers)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('scorers.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft"><?= View::partial('scorers-table', ['scorers' => $scorers, 'leagueId' => $id]) ?></div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="assists">
    <?php if (empty($assists)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('scorers.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft"><?= View::partial('scorers-table', ['scorers' => $assists, 'leagueId' => $id, 'metric' => 'assist']) ?></div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="news">
    <?php if (empty($news)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('news.none')) ?></p></div>
    <?php else: ?>
      <div class="news-list">
        <?php foreach ($news as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
