<?php
/** Movies hub — الأفلام. */
use TofiXTv\Core\View;
?>
<?= View::partial('cinema-hero', [
    'hero' => $hero,
    'h1'   => t('cinema.movies_title'),
]) ?>

<div class="container cinema-toolbar reveal">
  <form class="cinema-search" action="<?= e(path('cinema/search')) ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" placeholder="<?= e(t('cinema.search_ph')) ?>" aria-label="<?= e(t('cinema.search')) ?>">
  </form>
  <?php if (!empty($genres)): ?>
  <div class="hscroll genre-chips" aria-label="<?= e(t('cinema.genres')) ?>">
    <?php foreach ($genres as $g): ?>
    <a class="chip" href="<?= e(path('movies/genre/' . slugify((string)$g['name'], 'genre') . '-' . (int)$g['id'])) ?>"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?= View::partial('poster-row', ['title' => t('cinema.popular_movies'), 'items' => $popular,    'type' => 'movie']) ?>
<?= View::partial('poster-row', ['title' => t('cinema.now_playing'),    'items' => $nowPlaying, 'type' => 'movie']) ?>
<?= View::partial('poster-row', ['title' => t('cinema.top_rated'),      'items' => $topRated,   'type' => 'movie']) ?>
<?= View::partial('poster-row', ['title' => t('cinema.upcoming'),       'items' => $upcoming,   'type' => 'movie']) ?>

<?php if (empty($popular) && empty($topRated)): ?>
<section class="section container">
  <div class="skeleton-grid" aria-hidden="true">
    <?php for ($i = 0; $i < 12; $i++): ?><div class="skeleton skeleton-poster"></div><?php endfor; ?>
  </div>
  <p class="empty-note"><?= e(t('misc.loading')) ?></p>
</section>
<?php endif; ?>
