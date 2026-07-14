<?php
/**
 * Poster card for a movie / series item.
 * Expects: $item (TMDB payload), optional $type ('movie'|'tv' — auto-detected).
 */
use TofiXTv\Core\Tmdb;

$type  = $type ?? Tmdb::typeOf($item);
$title = Tmdb::titleOf($item);
$year  = Tmdb::yearOf($item);
$vote  = (float)($item['vote_average'] ?? 0);
$url   = $type === 'tv' ? series_url($item) : movie_url($item);
?>
<a class="poster-card card-hover" href="<?= e($url) ?>">
  <div class="poster-thumb">
    <img src="<?= e(tmdb_poster($item['poster_path'] ?? null)) ?>" alt="<?= e($title) ?>"
         loading="lazy" decoding="async" width="342" height="513">
    <?php if ($vote > 0): ?>
    <span class="poster-rating" aria-label="<?= e(t('cinema.rating')) ?>">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
      <?= e(Tmdb::rating($vote)) ?>
    </span>
    <?php endif; ?>
    <span class="poster-type"><?= e($type === 'tv' ? t('cinema.series_one') : t('cinema.movie')) ?></span>
    <span class="poster-play" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
    </span>
  </div>
  <div class="poster-body">
    <h3 class="poster-title"><?= e($title) ?></h3>
    <?php if ($year): ?><span class="poster-year"><?= e($year) ?></span><?php endif; ?>
  </div>
</a>
