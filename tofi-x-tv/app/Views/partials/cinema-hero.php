<?php
/**
 * Cinema hero slider (movies / series hubs). Expects: $hero (list), $h1, $sub.
 */
use TofiXTv\Core\Tmdb;

if (empty($hero)) return;
$first = $hero[0];
?>
<section class="cinema-hero" id="cinemaHero">
  <?php foreach ($hero as $i => $item):
      $type = Tmdb::typeOf($item);
      $title = Tmdb::titleOf($item);
      $url = $type === 'tv' ? series_url($item) : movie_url($item);
  ?>
  <div class="ch-slide<?= $i === 0 ? ' active' : '' ?>" data-slide="<?= $i ?>">
    <img class="ch-backdrop" src="<?= e(tmdb_backdrop($item['backdrop_path'] ?? null, 'w1280')) ?>"
         alt="" aria-hidden="true" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
    <div class="ch-overlay" aria-hidden="true"></div>
    <div class="container ch-content">
      <?php if ($i === 0): ?>
      <h1 class="ch-eyebrow"><?= e($h1) ?></h1>
      <?php endif; ?>
      <p class="ch-badge"><?= e($type === 'tv' ? t('cinema.series_one') : t('cinema.movie')) ?></p>
      <p class="ch-title"><?= e($title) ?></p>
      <div class="ch-meta">
        <?php if (!empty($item['vote_average'])): ?>
        <span class="ch-rating">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
          <?= e(Tmdb::rating($item['vote_average'])) ?>
        </span>
        <?php endif; ?>
        <?php if (Tmdb::yearOf($item)): ?><span><?= e(Tmdb::yearOf($item)) ?></span><?php endif; ?>
      </div>
      <?php if (!empty($item['overview'])): ?>
      <p class="ch-overview"><?= e(excerpt((string)$item['overview'], 180)) ?></p>
      <?php endif; ?>
      <div class="ch-actions">
        <a class="btn btn-primary" href="<?= e($url) ?>">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
          <?= e(t('cinema.watch_now')) ?>
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (count($hero) > 1): ?>
  <div class="ch-dots" role="tablist" aria-label="slides">
    <?php foreach ($hero as $i => $_): ?>
    <button class="ch-dot<?= $i === 0 ? ' active' : '' ?>" data-goto="<?= $i ?>" aria-label="slide <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
