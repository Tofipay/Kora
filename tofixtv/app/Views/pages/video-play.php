<?php
use TofiXTv\Core\View;
/**
 * In-site Btolat player page. Expects: $v (VideoFeed::find result), $related.
 *
 * Playback priority — ALWAYS inside the site, never a hop to the source:
 *   1. media_url        → native <video>; .m3u8 attaches hls.js (site vendor)
 *   2. youtube_id       → poster → youtube-nocookie iframe with embed-block
 *                         detection (errors 101/150, e.g. beIN SPORTS MENA);
 *                         on block it AUTO-falls back to the X embed when one
 *                         exists, else shows the blocked note + platform button
 *   3. tweet_id         → X post embedded IN-SITE (platform.twitter.com)
 *   4. embed_iframe     → generic external player (vortexvision & friends,
 *                         from the source's ld+json embedURL) in an iframe
 *   5. nothing exposed  → poster + note (no source-site link, ever)
 *
 * Platform buttons (YouTube / X) are always offered when known — the user
 * chooses between in-site playback and the platform.
 */
$title    = (string)$v['title'];
$poster   = (string)$v['thumbnail'];
$champ    = (string)$v['champ_title'];
$shareUrl = SITE_URL . path('video/' . (int)$v['id']);
$media    = (string)($v['media_url'] ?? '');
$isHls    = !empty($v['is_hls']);
$ytId     = (string)($v['youtube_id'] ?? '');
$tweetId  = (string)($v['tweet_id'] ?? '');
$xUrl     = (string)($v['x_url'] ?? '');
$isAr     = \TofiXTv\Core\Lang::current() === 'ar';

/* X embed URL — official tweet embed frame, no widgets.js needed. */
$xEmbed = $tweetId !== ''
    ? 'https://platform.twitter.com/embed/Tweet.html?id=' . rawurlencode($tweetId)
      . '&theme=light&hideCard=false&hideThread=true&lang=' . ($isAr ? 'ar' : 'en')
    : '';
?>
<section class="video-page">
  <div class="container">
    <a class="wt-back" href="<?= e(path('videos')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      <?= e(t('videos.title')) ?>
    </a>

    <?php if ($media !== ''): ?>
    <!-- 1) Direct stream: native player; m3u8 goes through hls.js -->
    <div class="vp-stage">
      <video class="vp-iframe" controls playsinline preload="metadata"
             <?= $isHls ? 'data-hls="' . e($media) . '"' : '' ?>
             <?= $poster !== '' ? 'poster="' . e($poster) . '"' : '' ?>>
        <?php if (!$isHls): ?><source src="<?= e($media) ?>"><?php endif; ?>
      </video>
    </div>
    <?php elseif ($ytId !== ''): ?>
    <!-- 2) YouTube with embed-block detection; X embed as automatic fallback -->
    <div class="vp-stage" data-yt="<?= e($ytId) ?>" data-yt-player data-yt-guard
         <?= $xEmbed !== '' ? 'data-x-fallback="' . e($xEmbed) . '"' : '' ?>>
      <button class="vp-poster" type="button" data-yt-play aria-label="<?= e(t('videos.play')) ?>">
        <?php if ($poster !== ''): ?>
        <img src="<?= e($poster) ?>" alt="<?= e($title) ?>" width="1280" height="720" fetchpriority="high" onerror="this.style.display='none'">
        <?php endif; ?>
        <span class="vp-big-play" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </button>
      <!-- Shown by JS only when YouTube reports embedding disabled (101/150) -->
      <template data-yt-blocked>
        <div class="vp-blocked">
          <?php if ($poster !== ''): ?><img class="vp-blocked-bg" src="<?= e($poster) ?>" alt="" aria-hidden="true"><?php endif; ?>
          <div class="vp-blocked-note">
            <p><?= e(t('videos.blocked')) ?></p>
            <a class="btn btn-primary" href="https://www.youtube.com/watch?v=<?= e($ytId) ?>" target="_blank" rel="noopener nofollow">
              <?= e(t('videos.watch_on')) ?> YouTube
            </a>
          </div>
        </div>
      </template>
    </div>
    <?php elseif ($xEmbed !== ''): ?>
    <!-- 3) X post embedded in-site -->
    <div class="vp-stage vp-x">
      <iframe class="vp-iframe vp-x-frame" src="<?= e($xEmbed) ?>" title="X"
              allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
    </div>
    <?php elseif (!empty($v['embed_iframe'])): ?>
    <!-- 4) Generic external player (ld+json embedURL) — in-site iframe -->
    <div class="vp-stage">
      <iframe class="vp-iframe" src="<?= e((string)$v['embed_iframe']) ?>" title="<?= e($title) ?>"
              allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen
              referrerpolicy="no-referrer" loading="eager" frameborder="0"></iframe>
    </div>
    <?php else: ?>
    <!-- 5) No public player exposed: poster + honest note (never a source link) -->
    <div class="vp-stage">
      <div class="vp-poster" aria-hidden="true">
        <?php if ($poster !== ''): ?>
        <img src="<?= e($poster) ?>" alt="<?= e($title) ?>" width="1280" height="720" fetchpriority="high" onerror="this.style.display='none'">
        <?php endif; ?>
      </div>
      <div class="vp-blocked-note vp-static-note"><p><?= e(t('videos.unavailable')) ?></p></div>
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
        <?php if ($ytId !== ''): ?>
        <a class="btn btn-ghost vp-act" href="https://www.youtube.com/watch?v=<?= e($ytId) ?>" target="_blank" rel="noopener nofollow">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M23 8s-.2-1.6-.9-2.3c-.9-.9-1.8-.9-2.3-1C16.6 4.5 12 4.5 12 4.5s-4.6 0-7.8.2c-.4.1-1.4.1-2.3 1C1.2 6.4 1 8 1 8S.8 9.9.8 11.8v1.7c0 1.9.2 3.8.2 3.8s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.2 7.6.2s4.6 0 7.8-.3c.4-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.9.2-3.8v-1.7C23.2 9.9 23 8 23 8zM9.8 15.3V8.6l6.1 3.4-6.1 3.3z"/></svg>
          <?= e(t('videos.watch_on')) ?> YouTube
        </a>
        <?php endif; ?>
        <?php if ($xUrl !== ''): ?>
        <a class="btn btn-ghost vp-act" href="<?= e($xUrl) ?>" target="_blank" rel="noopener nofollow">
          <svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L1.6 2h6.4l4.4 5.9L18.9 2z"/></svg>
          <?= e(t('videos.watch_on')) ?> X
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
