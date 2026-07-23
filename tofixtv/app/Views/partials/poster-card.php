<?php
/**
 * Poster card for a movie / series item.
 * Title sits INSIDE the artwork (bottom-center, translucent brand overlay);
 * hovering reveals the overview + rating + genres panel.
 * Expects: $item (TMDB payload), optional $type ('movie'|'tv' — auto-detected).
 */
use TofiXTv\Core\CinemaPolicy;
use TofiXTv\Core\Tmdb;

$type  = $type ?? Tmdb::typeOf($item);
$title = Tmdb::titleOf($item);
$cpol  = CinemaPolicy::itemFor($type, (int)($item['id'] ?? 0));
$isAppOnly = $cpol['access'] === 'app' && !is_tofix_app();
$year  = Tmdb::yearOf($item);
$vote  = (float)($item['vote_average'] ?? 0);
$url   = $type === 'tv' ? series_url($item) : movie_url($item);
$overview = trim((string)($item['overview'] ?? ''));

// Genre chips from genre_ids via the disk-cached map (one lookup per request).
$genreNames = [];
if (!empty($item['genre_ids']) && is_array($item['genre_ids'])) {
    $gmap = Tmdb::genreMap($type);
    foreach (array_slice($item['genre_ids'], 0, 2) as $gid) {
        if (!empty($gmap[(int)$gid])) $genreNames[] = $gmap[(int)$gid];
    }
} elseif (!empty($item['genres'])) {
    foreach (array_slice((array)$item['genres'], 0, 2) as $g) {
        if (!empty($g['name'])) $genreNames[] = (string)$g['name'];
    }
}
?>
<a class="poster-card card-hover" href="<?= e($url) ?>" title="<?= e($title) ?>">
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
    <?php if ($cpol['rating'] !== 'g'): ?>
    <span class="poster-age age-badge age-<?= e($cpol['rating']) ?>"><?= e(CinemaPolicy::ratingLabel($cpol['rating'])) ?></span>
    <?php endif; ?>
    <?php if ($isAppOnly): ?>
    <span class="poster-lock" title="<?= e(t('cinema.app_only_badge')) ?>">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/></svg>
      <?= e(t('cinema.app_only_badge')) ?>
    </span>
    <?php endif; ?>
    <button class="poster-fav" data-fav="<?= $type === 'tv' ? 'series' : 'movie' ?>" data-id="<?= (int)($item['id'] ?? 0) ?>"
            data-title="<?= e($title) ?>" data-url="<?= e($url) ?>"
            data-img="<?= e(tmdb_poster($item['poster_path'] ?? null, 'w185')) ?>"
            aria-label="<?= e(t('nav.favorites')) ?>">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
    </button>

    <!-- Always-visible caption INSIDE the artwork -->
    <div class="poster-caption">
      <h3 class="poster-title"><?= e($title) ?></h3>
      <?php if ($year): ?><span class="poster-year"><?= e($year) ?></span><?php endif; ?>
    </div>

    <!-- Hover panel: play + overview + genres -->
    <div class="poster-info" aria-hidden="true">
      <span class="pi-play">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </span>
      <?php if ($overview !== ''): ?>
      <p class="pi-overview"><?= e(excerpt($overview, 110)) ?></p>
      <?php endif; ?>
      <?php if ($genreNames): ?>
      <div class="pi-genres">
        <?php foreach ($genreNames as $gn): ?><span class="pi-genre"><?= e($gn) ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</a>
