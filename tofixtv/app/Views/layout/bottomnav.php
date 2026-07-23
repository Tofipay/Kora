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
  <a class="bn-item<?= $on('/channels') ?>" href="<?= e(path('channels')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="2.5" y="6" width="19" height="14" rx="3"/><path d="m8 2 4 4 4-4M7 11h7M7 15h5M18 11v4"/></svg>
    <span><?= e(t('nav.channels')) ?></span>
  </a>
  <a class="bn-item<?= $on('/movies') ?: $on('/movie') ?>" href="<?= e(path('movies')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M7 4l2.5 5M12 4l2.5 5M17 4l2.5 5"/></svg>
    <span><?= e(t('nav.movies')) ?></span>
  </a>
  <a class="bn-item<?= $on('/series') ?>" href="<?= e(path('series')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="2" y="7" width="20" height="13" rx="3"/><path d="m8 2 4 4 4-4"/><path d="m10.5 11 4.5 3-4.5 3z" fill="currentColor" stroke="none"/></svg>
    <span><?= e(t('nav.series')) ?></span>
  </a>
  <a class="bn-item<?= $on('/more') ?>" href="<?= e(path('more')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
    <span><?= e(t('nav.more')) ?></span>
  </a>
</nav>
