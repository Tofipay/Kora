<?php
use Qamhad\Core\View;
$dTs    = $date !== '' ? to_ts($date) : 0;
$dLabel = $dTs ? format_date_long(date('Y-m-d', $dTs)) : '';
$poster = "https://i.ytimg.com/vi/{$ytId}/hqdefault.jpg";
$shareUrl = SITE_URL . path('video/' . $ytId);
?>
<section class="video-page">
  <div class="container">
    <a class="wt-back" href="<?= e(path('videos')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      <?= e(t('videos.title')) ?>
    </a>

    <!-- Player: click the poster to load the iframe (fast first paint, no
         third-party JS until the user hits play) -->
    <div class="vp-stage" data-yt="<?= e($ytId) ?>" data-yt-player>
      <button class="vp-poster" type="button" data-yt-play aria-label="<?= e(t('videos.play')) ?>">
        <img src="<?= e($poster) ?>" alt="<?= e($title) ?>" width="1280" height="720" fetchpriority="high"
             onerror="this.style.display='none'">
        <span class="vp-big-play" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </button>
    </div>

    <div class="vp-info card glass-soft">
      <h1 class="vp-title"><?= e($title) ?></h1>
      <div class="vp-meta">
        <?php if ($champ !== ''): ?>
        <span class="vp-chip vp-champ">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 4h10v3a5 5 0 0 1-10 0zM5 5H3v2a3 3 0 0 0 3 3M19 5h2v2a3 3 0 0 1-3 3M9 15h6v5H9z"/></svg>
          <?= e($champ) ?>
        </span>
        <?php endif; ?>
        <?php if ($dLabel !== ''): ?>
        <span class="vp-chip vp-date">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
          <?= e($dLabel) ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="vp-actions">
        <button class="btn btn-ghost vp-act" type="button" onclick="QShare()">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6m-7.6 6 7.6 3.6"/></svg>
          <?= e(t('misc.share')) ?>
        </button>
        <button class="btn btn-ghost vp-act" type="button" data-copy-link="<?= e($shareUrl) ?>">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>
          <?= e(t('videos.copy_link')) ?>
        </button>
        <a class="btn btn-ghost vp-act" href="https://www.youtube.com/watch?v=<?= e($ytId) ?>" target="_blank" rel="noopener nofollow">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M8 7h9v9"/></svg>
          YouTube
        </a>
      </div>
    </div>

    <?php if (!empty($related)): ?>
    <section class="section vp-related">
      <div class="section-head"><h2><?= e(t('videos.related')) ?></h2></div>
      <div class="videos-grid">
        <?php foreach (array_slice($related, 0, 8) as $v): ?><?= View::partial('video-card', ['v' => $v]) ?><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</section>
