<?php use Qamhad\Core\View; ?>
<div class="container page-head">
  <h1><?= e(t('search.title')) ?></h1>
</div>
<div class="container">
  <form class="search-bar glass-soft" action="<?= e(path('search')) ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search.placeholder')) ?>" autofocus autocomplete="off">
    <button class="btn btn-primary" type="submit"><?= e(t('nav.search')) ?></button>
  </form>

<?php if ($q === ''): ?>
  <p class="empty-note"><?= e(t('search.hint')) ?></p>
<?php elseif (empty($teams) && empty($players) && empty($leagues) && empty($matches) && empty($news)): ?>
  <div class="empty-state glass-soft"><p><?= e(t('search.none')) ?></p></div>
<?php else: ?>

  <?php if (!empty($players)): ?>
  <section class="section">
    <div class="section-head"><h2><?= e(t('search.players')) ?></h2></div>
    <div class="hscroll team-pills">
      <?php foreach ($players as $pl): ?>
        <a class="team-pill glass-soft card-hover" href="<?= e(player_url(['id' => (int)($pl['row_id'] ?? 0), 'title' => player_label($pl)])) ?>">
          <img src="<?= e(player_img($pl, '64')) ?>" alt="" width="44" height="44" loading="lazy"><span><?= e(player_label($pl)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($teams)): ?>
  <section class="section">
    <div class="section-head"><h2><?= e(t('search.teams')) ?></h2></div>
    <div class="hscroll team-pills">
      <?php foreach ($teams as $tm): ?>
        <a class="team-pill glass-soft card-hover" href="<?= e(team_url($tm)) ?>">
          <img src="<?= e(team_img($tm, '128')) ?>" alt="" width="44" height="44" loading="lazy"><span><?= e(team_name($tm)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($leagues)): ?>
  <section class="section">
    <div class="section-head"><h2><?= e(t('search.leagues')) ?></h2></div>
    <div class="hscroll league-pills">
      <?php foreach ($leagues as $lg): ?>
        <a class="league-pill glass-soft card-hover" href="<?= e(league_url($lg)) ?>">
          <img src="<?= e(league_img($lg)) ?>" alt="" width="30" height="30" loading="lazy"><span><?= e($lg['title']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($matches)): ?>
  <section class="section">
    <div class="section-head"><h2><?= e(t('search.matches')) ?></h2></div>
    <div class="league-matches">
      <?php foreach ($matches as $m): ?><?= View::partial('match-card', ['m' => $m]) ?><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($news)): ?>
  <section class="section">
    <div class="section-head"><h2><?= e(t('search.news')) ?></h2></div>
    <div class="news-list">
      <?php foreach ($news as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php endif; ?>
</div>
