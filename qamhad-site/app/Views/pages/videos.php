<?php use Qamhad\Core\View; ?>
<div class="container page-head">
  <div>
    <h1><?= e(t('videos.title')) ?></h1>
    <p class="page-sub"><?= e(t('videos.subtitle')) ?></p>
  </div>
</div>

<div class="container">
  <!-- Search within videos (client-side, instant) -->
  <div class="search-bar glass-soft videos-search">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" id="videos-search" placeholder="<?= e(t('videos.search')) ?>" aria-label="<?= e(t('videos.search')) ?>" autocomplete="off">
  </div>

  <!-- Championship tabs -->
  <nav class="day-nav videos-tabs glass-soft" aria-label="<?= e(t('videos.by_champ')) ?>">
    <?php foreach ($categories as $c): $active = (string)$c['id'] === (string)$champ; ?>
      <a class="day-tab<?= $active ? ' active' : '' ?>"
         href="<?= e(path('videos') . ($c['id'] === 'all' ? '' : '?champ=' . rawurlencode((string)$c['id']))) ?>">
        <?= e((string)$c['title']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="container">
  <?php if (empty($items)): ?>
    <div class="empty-state glass-soft" data-videos-empty>
      <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/></svg>
      <p><?= e(t('videos.none')) ?></p>
    </div>
  <?php else: ?>
    <div class="videos-grid"
         data-videos-grid
         data-champ="<?= e((string)$champ) ?>"
         data-next-skip="<?= $hasMore ? (int)$nextSkip : '' ?>">
      <?php foreach ($items as $v): ?><?= View::partial('video-card', ['v' => $v]) ?><?php endforeach; ?>
    </div>

    <!-- Skeleton row shown while the next page loads -->
    <div class="videos-grid videos-skeleton" data-videos-skeleton hidden aria-hidden="true">
      <?php for ($i = 0; $i < 4; $i++): ?>
      <div class="vcard vcard-skel">
        <span class="vc-thumb skeleton"></span>
        <span class="vc-body"><span class="skeleton skel-line"></span><span class="skeleton skel-line short"></span></span>
      </div>
      <?php endfor; ?>
    </div>

    <!-- No-search result note (client-side) -->
    <div class="empty-state glass-soft" data-videos-noresult hidden><p><?= e(t('videos.no_match')) ?></p></div>

    <?php if ($hasMore): ?>
    <div class="show-more-wrap" data-videos-more>
      <button class="btn btn-ghost" type="button" data-load-more>
        <?= e(t('misc.show_more')) ?>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
      </button>
    </div>
    <span class="videos-sentinel" data-videos-sentinel aria-hidden="true"></span>
    <?php endif; ?>
  <?php endif; ?>
</div>
