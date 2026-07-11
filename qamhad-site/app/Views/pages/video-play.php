<?php
use Qamhad\Core\View;
/**
 * In-site Btolat player page. Expects: $v (VideoFeed::find result), $related.
 * Renders whichever provider the source exposes:
 *   - youtube      → poster → youtube-nocookie iframe (existing JS)
 *   - direct/mp4   → native <video> with poster (no JS needed)
 *   - x / unknown  → poster + open buttons (post/source page)
 * Same design language as the legacy watch page (vp-* components).
 */
$title    = (string)$v['title'];
$poster   = (string)$v['thumbnail'];
$champ    = (string)$v['champ_title'];
$shareUrl = SITE_URL . path('video/' . (int)$v['id']);
$provider = (string)($v['provider'] ?? '');
$media    = (string)($v['media_url'] ?? '');
$external = (string)($v['external_url'] ?? ($v['page_url'] ?? ''));
?>
<section class="video-page">
  <div class="container">
    <a class="wt-back" href="<?= e(path('videos')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      <?= e(t('videos.title')) ?>
    </a>

    <?php if ($provider === 'youtube' && !empty($v['youtube_id'])): ?>
    <!-- YouTube: poster first, iframe injected on play (existing JS) -->
    <div class="vp-stage" data-yt="<?= e((string)$v['youtube_id']) ?>" data-yt-player>
      <button class="vp-poster" type="button" data-yt-play aria-label="<?= e(t('videos.play')) ?>">
        <?php if ($poster !== ''): ?>
        <img src="<?= e($poster) ?>" alt="<?= e($title) ?>" width="1280" height="720" fetchpriority="high" onerror="this.style.display='none'">
        <?php endif; ?>
        <span class="vp-big-play" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </button>
    </div>
    <?php elseif ($media !== ''): ?>
    <!-- Direct stream from the source: native player, zero extra JS -->
    <div class="vp-stage">
      <video class="vp-iframe" controls playsinline preload="metadata"
             <?= $poster !== '' ? 'poster="' . e($poster) . '"' : '' ?>>
        <source src="<?= e($media) ?>">
      </video>
    </div>
    <?php else: ?>
    <!-- X post / provider without a public stream: poster + open actions -->
    <div class="vp-stage">
      <a class="vp-poster" href="<?= e($external) ?>" target="_blank" rel="noopener nofollow" aria-label="<?= e(t('videos.play')) ?>">
        <?php if ($poster !== ''): ?>
        <img src="<?= e($poster) ?>" alt="<?= e($title) ?>" width="1280" height="720" fetchpriority="high" onerror="this.style.display='none'">
        <?php endif; ?>
        <span class="vp-big-play" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </a>
    </div>
    <?php endif; ?>

    <div class="vp-info card glass-soft">
      <h1 class="vp-title"><?= e($title) ?></h1>
      <div class="vp-meta">
        <?php if ($champ !== ''): ?>
        <span class="vp-chip vp-champ">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 4h10v3a5 5 0 0 1-10 0zM5 5H3v2a3 3 0 0 0 3 3M19 5h2v2a3 3 0 0 1-3 3M9 15h6v5H9z"/></svg>
          <?= e($champ) ?>
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
        <?php if ($provider === 'x' && $external !== ''): ?>
        <a class="btn btn-ghost vp-act" href="<?= e($external) ?>" target="_blank" rel="noopener nofollow">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L1.6 2h6.4l4.4 5.9L18.9 2z"/></svg>
          X
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($related)): ?>
    <section class="section vp-related">
      <div class="section-head"><h2><?= e(t('videos.related')) ?></h2></div>
      <div class="videos-grid">
        <?php foreach (array_slice($related, 0, 8) as $rv): ?><?= View::partial('video-card', ['v' => $rv]) ?><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</section>
