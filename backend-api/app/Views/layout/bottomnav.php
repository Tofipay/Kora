<?php
$cur = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$bare = '/' . ltrim((string)preg_replace('#^/en(/|$)#', '/$1', $cur), '/');
$on = fn(string $p) => ($p === '/' ? $bare === '/' : str_starts_with($bare, $p)) ? ' active' : '';
?>
<nav class="bottom-nav glass" aria-label="mobile">
  <a class="bn-item<?= $on('/matches') ?: $on('/') ?>" href="<?= e(path('matches')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3.5 9h17M3.5 15h17" opacity=".4"/><circle cx="12" cy="12" r="3.2"/></svg>
    <span><?= e(t('nav.matches')) ?></span>
  </a>
  <a class="bn-item<?= $on('/news') ?>" href="<?= e(path('news')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h6"/></svg>
    <span><?= e(t('nav.news')) ?></span>
  </a>
  <a class="bn-item<?= $on('/videos') ?: $on('/video') ?>" href="<?= e(path('videos')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none"/></svg>
    <span><?= e(t('nav.videos')) ?></span>
  </a>
  <a class="bn-item<?= $on('/standings') ?>" href="<?= e(path('standings')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
    <span><?= e(t('nav.standings')) ?></span>
  </a>
  <a class="bn-item<?= $on('/leagues') ?>" href="<?= e(path('leagues')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><circle cx="5" cy="6" r="1.6"/><circle cx="5" cy="12" r="1.6"/><circle cx="5" cy="18" r="1.6"/><path d="M10 6h9M10 12h9M10 18h9"/></svg>
    <span><?= e(t('nav.more')) ?></span>
  </a>
</nav>
