<?php
/** Series detail — مسلسل. Expects: $tv, $seasons, $episodes, $curSeason, $curEpisode, $cast, $similar, $recommended, $trailer, $embed, $policy. */
use TofiXTv\Core\CinemaPolicy;
use TofiXTv\Core\Tmdb;
use TofiXTv\Core\View;

$policy = is_array($policy ?? null) ? $policy : ['visible' => true, 'playable' => true, 'locked' => false, 'rating' => 'g', 'reason' => ''];
$ageRating = (string)($policy['rating'] ?? 'g');
$title = (string)($tv['name'] ?? '');
$year  = substr((string)($tv['first_air_date'] ?? ''), 0, 4);
$vote  = (float)($tv['vote_average'] ?? 0);
$nSeasons  = (int)($tv['number_of_seasons'] ?? 0);
$nEpisodes = (int)($tv['number_of_episodes'] ?? 0);
$selfPath  = series_url($tv);
$downloadUrl = (string)(CinemaPolicy::itemFor('tv', (int)($tv['id'] ?? 0))['download_url'] ?? '');

// Episode count fallback when the selected season API has no detailed rows.
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
          <?php if ($ageRating !== 'g'): ?><span class="badge age-badge age-<?= e($ageRating) ?>"><?= e(CinemaPolicy::ratingLabel($ageRating)) ?></span><?php endif; ?>
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
          <?php if ($downloadUrl !== '' && !empty($policy['playable']) && !empty($policy['visible'])): ?>
          <a class="btn btn-ghost cinema-download" href="<?= e($downloadUrl) ?>" target="_blank" rel="noopener noreferrer external" download>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14"/></svg>
            <?= e(t('cinema.download')) ?>
          </a>
          <?php endif; ?>
          <button class="btn btn-ghost fav-btn" data-fav="series" data-id="<?= (int)$tv['id'] ?>"
                  data-title="<?= e($title) ?>" data-url="<?= e($selfPath) ?>"
                  data-img="<?= e(tmdb_poster($tv['poster_path'] ?? null, 'w185')) ?>"
                  aria-label="<?= e(t('nav.favorites')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
            <?= e(t('nav.favorites')) ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <section class="section container" id="player">
    <div class="section-head">
      <h2><?= e($title) ?> — <?= e(t('cinema.season')) ?> <span data-ep-label-season><?= $curSeason ?></span> <?= e(t('cinema.episode')) ?> <span data-ep-label-episode><?= $curEpisode ?></span></h2>
    </div>
    <?php if (!$policy['playable'] || !$policy['visible']): ?>
      <?= View::partial('cinema-locked', ['policy' => $policy, 'title' => $title]) ?>
    <?php else: ?>
    <?php
    // Embed URL templates for the Netflix-style client-side episode switcher
    // ({s}/{e} placeholders). The chips below keep REAL hrefs, so crawlers and
    // no-JS visitors still get fully server-rendered episode pages.
    $tvId = (int)$tv['id'];
    $epConfig = [
        'id'      => $tvId,
        'season'  => $curSeason,
        'episode' => $curEpisode,
        'sources' => [
            'vidsrc'   => PLAYER_VIDSRC_TO . "/tv/{$tvId}/{s}/{e}",
            'vidsrccc' => PLAYER_VIDSRC_CC . "/tv/{$tvId}/{s}/{e}",
            'videasy'  => PLAYER_VIDEASY . "/tv/{$tvId}/{s}/{e}",
        ],
    ];
    ?>
    <script type="application/json" data-ep-config><?= json_encode($epConfig, JSON_UNESCAPED_SLASHES) ?></script>

    <!-- Season / episode picker: plain GET links = crawlable + zero JS required.
         JS upgrades EPISODE clicks to an in-place player swap (no reload). -->
    <?php if (!empty($seasons)): ?>
    <div class="hscroll season-chips">
      <?php foreach ($seasons as $s): $sn = (int)($s['season_number'] ?? 0); ?>
      <a class="chip<?= $sn === $curSeason ? ' active' : '' ?>"
         href="<?= e($selfPath) ?>?season=<?= $sn ?>#player"><?= e(t('cinema.season')) ?> <?= $sn ?></a>
      <?php endforeach; ?>
    </div>
    <div class="episode-grid">
      <?php if (!empty($episodes)): ?>
        <?php foreach ($episodes as $episode): ?>
          <?= View::partial('episode-card', [
              'episode' => $episode, 'tv' => $tv, 'selfPath' => $selfPath,
              'season' => $curSeason, 'current' => $curEpisode,
          ]) ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($ep = 1; $ep <= $curSeasonEpisodes; $ep++): ?>
          <?= View::partial('episode-card', [
              'episode' => ['episode_number' => $ep], 'tv' => $tv, 'selfPath' => $selfPath,
              'season' => $curSeason, 'current' => $curEpisode,
          ]) ?>
        <?php endfor; ?>
      <?php endif; ?>
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
      <button class="chip" data-ep-source="vidsrc" data-embed-src="<?= e($embed['vidsrc']) ?>" data-embed-target="player">VidSrc</button>
      <button class="chip" data-ep-source="vidsrccc" data-embed-src="<?= e($embed['vidsrccc']) ?>" data-embed-target="player">VidSrc CC</button>
      <button class="chip" data-ep-source="videasy" data-embed-src="<?= e($embed['videasy']) ?>" data-embed-target="player">Videasy</button>
      <small class="player-hint"><?= e(t('cinema.player_hint')) ?></small>
    </div>
    <?php endif; ?>
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
