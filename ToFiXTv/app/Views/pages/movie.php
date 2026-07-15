<?php
/** Movie detail — فيلم. Expects: $movie, $cast, $similar, $recommended, $trailer, $embed. */
use TofiXTv\Core\Tmdb;
use TofiXTv\Core\View;

$title   = (string)($movie['title'] ?? '');
$year    = substr((string)($movie['release_date'] ?? ''), 0, 4);
$runtime = (int)($movie['runtime'] ?? 0);
$vote    = (float)($movie['vote_average'] ?? 0);
?>
<article class="cinema-detail">
  <div class="cd-hero">
    <img class="cd-backdrop" src="<?= e(tmdb_backdrop($movie['backdrop_path'] ?? null)) ?>" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
    <div class="cd-overlay" aria-hidden="true"></div>
    <div class="container cd-head">
      <div class="cd-poster">
        <img src="<?= e(tmdb_poster($movie['poster_path'] ?? null, 'w342')) ?>" alt="<?= e($title) ?>" width="342" height="513" decoding="async">
      </div>
      <div class="cd-info">
        <h1 class="cd-title"><?= e($title) ?></h1>
        <div class="cd-meta">
          <?php if ($vote > 0): ?>
          <span class="badge badge-rating">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            <?= e(Tmdb::rating($vote)) ?>/10
          </span>
          <?php endif; ?>
          <?php if ($year): ?><span class="badge"><?= e($year) ?></span><?php endif; ?>
          <?php if ($runtime > 0): ?><span class="badge"><?= $runtime ?> <?= e(t('cinema.minutes')) ?></span><?php endif; ?>
          <?php foreach (array_slice($movie['genres'] ?? [], 0, 3) as $g): ?>
          <a class="badge badge-link" href="<?= e(path('movies/genre/' . slugify((string)$g['name'], 'genre') . '-' . (int)$g['id'])) ?>"><?= e($g['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($movie['overview'])): ?>
        <p class="cd-overview"><?= e($movie['overview']) ?></p>
        <?php endif; ?>
        <div class="cd-actions">
          <a class="btn btn-primary" href="#player">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
            <?= e(t('cinema.watch_now')) ?>
          </a>
          <?php if ($trailer): ?>
          <button class="btn btn-ghost" data-embed-src="https://www.youtube.com/embed/<?= e($trailer['key']) ?>" data-embed-target="player">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <?= e(t('cinema.trailer')) ?>
          </button>
          <?php endif; ?>
          <button class="btn btn-ghost fav-btn" data-fav="movie" data-id="<?= (int)$movie['id'] ?>"
                  data-title="<?= e($title) ?>" data-url="<?= e(movie_url($movie)) ?>"
                  data-img="<?= e(tmdb_poster($movie['poster_path'] ?? null, 'w185')) ?>"
                  aria-label="<?= e(t('nav.favorites')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            <?= e(t('nav.favorites')) ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <section class="section container" id="player">
    <div class="section-head"><h2><?= e(t('cinema.watch_now')) ?> — <?= e($title) ?></h2></div>
    <div class="player-frame glass" data-player>
      <!-- Embed loads on demand (click) — keeps LCP/INP clean, no third-party JS at load. -->
      <button class="player-poster" data-embed-src="<?= e($embed['vidsrc']) ?>" data-embed-target="player" aria-label="<?= e(t('cinema.watch_now')) ?>">
        <img src="<?= e(tmdb_backdrop($movie['backdrop_path'] ?? null, 'w780')) ?>" alt="" loading="lazy" decoding="async">
        <span class="pp-btn"><svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span>
      </button>
    </div>
    <div class="player-sources">
      <span><?= e(t('cinema.sources')) ?>:</span>
      <button class="chip" data-embed-src="<?= e($embed['vidsrc']) ?>" data-embed-target="player">VidSrc</button>
      <button class="chip" data-embed-src="<?= e($embed['vidsrccc']) ?>" data-embed-target="player">VidSrc CC</button>
      <button class="chip" data-embed-src="<?= e($embed['videasy']) ?>" data-embed-target="player">Videasy</button>
      <small class="player-hint"><?= e(t('cinema.player_hint')) ?></small>
    </div>
  </section>

  <?php if (!empty($cast)): ?>
  <section class="section container reveal">
    <div class="section-head"><h2><?= e(t('cinema.cast')) ?></h2></div>
    <div class="hscroll cast-rail">
      <?php foreach ($cast as $c): ?>
      <div class="cast-card">
        <img src="<?= e(tmdb_profile($c['profile_path'] ?? null)) ?>" alt="<?= e($c['name'] ?? '') ?>" loading="lazy" decoding="async" width="120" height="120">
        <b><?= e($c['name'] ?? '') ?></b>
        <small><?= e($c['character'] ?? '') ?></small>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?= View::partial('poster-row', ['title' => t('cinema.similar'),     'items' => $similar,     'type' => 'movie']) ?>
  <?= View::partial('poster-row', ['title' => t('cinema.recommended'), 'items' => $recommended, 'type' => 'movie']) ?>
</article>
