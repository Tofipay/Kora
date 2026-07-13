<?php
use Qamhad\Core\View;
/**
 * Videos listing — 5 per page, News-style numbered pagination (prev/next +
 * page numbers). Server-rendered; no infinite scroll, no "show more".
 * Expects: $categories, $champ, $items, $page, $hasNext, $hasPrev, $pagePath.
 */
?>
<div class="container page-head">
  <div>
    <h1><?= e(t('videos.title')) ?></h1>
    <p class="page-sub"><?= e(t('videos.subtitle')) ?></p>
  </div>
</div>

<div class="container">
  <!-- Search — server-side (API-backed), like the News section -->
  <form class="search-bar glass-soft videos-search" action="<?= e(path('videos')) ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="<?= e(t('videos.search')) ?>" aria-label="<?= e(t('videos.search')) ?>" autocomplete="off">
    <?php if (($champ ?? 'all') !== 'all'): ?><input type="hidden" name="champ" value="<?= e((string)$champ) ?>"><?php endif; ?>
    <button class="btn btn-primary vs-go" type="submit"><?= e(t('nav.search')) ?></button>
  </form>

  <?php if (($q ?? '') !== ''): ?>
  <p class="videos-search-note">
    <?= e(t('videos.results_for', ['q' => $q])) ?><?php if (isset($total)): ?> · <?= (int)$total ?><?php endif; ?>
    — <a href="<?= e(path('videos')) ?>"><?= e(t('videos.clear_search')) ?></a>
  </p>
  <?php endif; ?>

  <!-- Championship tabs -->
  <nav class="day-nav videos-tabs glass-soft" aria-label="<?= e(t('videos.by_champ')) ?>">
    <?php foreach ($categories as $c): $active = (string)$c['id'] === (string)$champ && ($q ?? '') === ''; ?>
      <a class="day-tab<?= $active ? ' active' : '' ?>"
         href="<?= e(path('videos') . ($c['id'] === 'all' ? '' : '?champ=' . rawurlencode((string)$c['id']))) ?>">
        <?= e((string)$c['title']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="container">
  <?php if (empty($items)): ?>
    <div class="empty-state glass-soft">
      <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/></svg>
      <p><?= e(t('videos.none')) ?></p>
    </div>
  <?php else: ?>
    <div class="videos-grid" data-videos-grid>
      <?php foreach ($items as $v): ?><?= View::partial('video-card', ['v' => $v]) ?><?php endforeach; ?>
    </div>

    <?php
      // Numbered window: previous two pages, current, and next page when it exists.
      $numStart = max(1, $page - 2);
      $numEnd   = $hasNext ? $page + 1 : $page;
    ?>
    <nav class="pagination" aria-label="pagination">
      <?php if ($hasPrev): ?>
        <a class="btn btn-ghost" href="<?= e($pagePath($page - 1)) ?>" rel="prev"><?= e(t('news.prev')) ?></a>
      <?php endif; ?>
      <span class="page-numbers">
        <?php for ($n = $numStart; $n <= $numEnd; $n++): ?>
          <?php if ($n === $page): ?>
            <span class="page-num is-current" aria-current="page"><?= $n ?></span>
          <?php else: ?>
            <a class="page-num" href="<?= e($pagePath($n)) ?>"><?= $n ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </span>
      <?php if ($hasNext): ?>
        <a class="btn btn-ghost" href="<?= e($pagePath($page + 1)) ?>" rel="next"><?= e(t('news.next')) ?></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <?php if (($q ?? '') === '' && $page === 1): ?>
  <!-- SEO copy — server-rendered descriptive content for the section -->
  <section class="videos-seo card glass-soft">
    <h2><?= e(t('videos.seo_h')) ?><?= ($champ ?? 'all') !== 'all' ? ' — ' . e($activeTitle) : '' ?></h2>
    <p><?= e(t('videos.seo_p1')) ?></p>
    <p><?= e(t('videos.seo_p2')) ?></p>
  </section>
  <?php endif; ?>
</div>
