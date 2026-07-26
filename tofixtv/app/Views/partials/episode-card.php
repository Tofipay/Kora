<?php
$number = max(1, (int)($episode['episode_number'] ?? 1));
$title = trim((string)($episode['name'] ?? ''));
$runtime = (int)($episode['runtime'] ?? 0);
$still = trim((string)($episode['still_path'] ?? ''));
if ($still !== '') $image = tmdb_backdrop($still, 'w300');
elseif (!empty($tv['backdrop_path'])) $image = tmdb_backdrop((string)$tv['backdrop_path'], 'w780');
elseif (!empty($tv['poster_path'])) $image = tmdb_poster((string)$tv['poster_path'], 'w342');
else $image = '/assets/img/episode.svg';
// Intent playback mode: the card opens the server-picker dialog instead of the
// in-page iframe switcher (so the app-launch attributes replace data-ep-*).
$epIntentServers = $servers ?? null;
?>
<a class="episode-card<?= $number === $current ? ' active' : '' ?>"
   <?php if (!empty($epIntentServers)): ?>
   data-cinema-play data-servers="<?= e(json_encode($epIntentServers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
   <?php else: ?>
   data-ep-series="<?= (int)$tv['id'] ?>" data-ep-season="<?= $season ?>" data-ep-episode="<?= $number ?>"
   <?php endif; ?>
   href="<?= e($selfPath) ?>?season=<?= $season ?>&amp;episode=<?= $number ?>#player"
   <?= $number === $current ? 'aria-current="true"' : '' ?>>
  <span class="episode-thumb">
    <img src="<?= e($image) ?>" alt="<?= e($title ?: t('cinema.episode') . ' ' . $number) ?>" width="300" height="169" loading="lazy" decoding="async">
    <span class="episode-play"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
    <span class="episode-number"><?= e(t('cinema.episode')) ?> <?= $number ?></span>
    <span class="episode-watched" aria-label="<?= e(t('cinema.watched')) ?>">✓</span>
  </span>
  <span class="episode-info">
    <b><?= e($title ?: t('cinema.episode') . ' ' . $number) ?></b>
    <?php if ($runtime > 0): ?><small><?= $runtime ?> <?= e(t('cinema.minutes')) ?></small><?php endif; ?>
  </span>
</a>
