<?php
/** Series detail — مسلسل. Expects: $tv, $seasons, $curSeason, $curEpisode, $cast, $similar, $recommended, $trailer, $embed. */
use TofiXTv\Core\Tmdb;
use TofiXTv\Core\View;

$title = (string)($tv['name'] ?? '');
$year  = substr((string)($tv['first_air_date'] ?? ''), 0, 4);
$vote  = (float)($tv['vote_average'] ?? 0);
$nSeasons  = (int)($tv['number_of_seasons'] ?? 0);
$nEpisodes = (int)($tv['number_of_episodes'] ?? 0);
$selfPath  = series_url($tv);

// Episode count of the selected season (from the seasons list — no extra API call)
$curSeasonEpisodes = 0;
foreach ($seasons as $s) {
    if ((int)($s['season_number'] ?? 0) === $curSeason) {
        $curSeasonEpisodes = (int)($s['episode_count'] ?? 0);
        break;
    }
}
$curSeasonEpisodes = max(1, min(60, $curSeasonEpisodes ?: 20));
?>
<article class="cinema-detail">
  <div class="cd-hero">
    <img class="cd-backdrop" src="<?= e(tmdb_backdrop($tv['backdrop_path'] ?? null)) ?>" alt="" aria-hidden="true" fetchpriority="high" decoding="async">
    <div class="cd-overlay" aria-hidden="true"></div>
    <div class="container cd-head">
      <div class="cd-poster">
        <img src="<?= e(tmdb_poster($tv['poster_path'] ?? null, 'w342')) ?>" alt="<?= e($title) ?>" width="342" height="513" decoding="async">
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
          <?php if ($nSeasons): ?><span class="badge"><?= $nSeasons ?> <?= e(t('cinema.seasons')) ?></span><?php endif; ?>
          <?php if ($nEpisodes): ?><span class="badge"><?= $nEpisodes ?> <?= e(t('cinema.episodes')) ?></span><?php endif; ?>
          <?php foreach (array_slice($tv['genres'] ?? [], 0, 3) as $g): ?>
          <a class="badge badge-link" href="<?= e(path('series/genre/' . slugify((string)$g['name'], 'genre') . '-' . (int)$g['id'])) ?>"><?= e($g['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($tv['overview'])): ?>
        <p class="cd-overview"><?= e($tv['overview']) ?></p>
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
        </div>
      </div>
    </div>
  </div>

  <section class="section container" id="player">
    <div class="section-head">
      <h2><?= e($title) ?> — <?= e(t('cinema.season')) ?> <?= $curSeason ?> <?= e(t('cinema.episode')) ?> <?= $curEpisode ?></h2>
    </div>

    <!-- Season / episode picker: plain GET links = crawlable + zero JS required -->
    <?php if (!empty($seasons)): ?>
    <div class="hscroll season-chips">
      <?php foreach ($seasons as $s): $sn = (int)($s['season_number'] ?? 0); ?>
      <a class="chip<?= $sn === $curSeason ? ' active' : '' ?>"
         href="<?= e($selfPath) ?>?season=<?= $sn ?>#player"><?= e(t('cinema.season')) ?> <?= $sn ?></a>
      <?php endforeach; ?>
    </div>
    <div class="hscroll episode-chips">
      <?php for ($ep = 1; $ep <= $curSeasonEpisodes; $ep++): ?>
      <a class="chip chip-sm<?= $ep === $curEpisode ? ' active' : '' ?>"
         href="<?= e($selfPath) ?>?season=<?= $curSeason ?>&amp;episode=<?= $ep ?>#player"><?= $ep ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div class="player-frame glass" data-player>
      <button class="player-poster" data-embed-src="<?= e($embed['vidsrc']) ?>" data-embed-target="player" aria-label="<?= e(t('cinema.watch_now')) ?>">
        <img src="<?= e(tmdb_backdrop($tv['backdrop_path'] ?? null, 'w780')) ?>" alt="" loading="lazy" decoding="async">
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

  <?= View::partial('poster-row', ['title' => t('cinema.similar'),     'items' => $similar,     'type' => 'tv']) ?>
  <?= View::partial('poster-row', ['title' => t('cinema.recommended'), 'items' => $recommended, 'type' => 'tv']) ?>
</article>
