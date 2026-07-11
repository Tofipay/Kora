<?php
use Qamhad\Core\Lang;
$altPath = Lang::alternatePath($_SERVER['REQUEST_URI'] ?? '/');
$cur = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$bare = preg_replace('#^/en(/|$)#', '/$1', $cur);
$isActive = function (string $p) use ($bare): string {
    $b = '/' . ltrim((string)$bare, '/');
    if ($p === '/') return $b === '/' ? ' active' : '';
    return str_starts_with($b, $p) ? ' active' : '';
};
?>
<header class="site-header glass">
  <div class="container header-inner">
    <a class="brand" href="<?= e(path('/')) ?>" aria-label="<?= e(Lang::siteName()) ?>">
      <img src="<?= e(site_logo()) ?>" alt="<?= e(Lang::siteName()) ?>" width="150" height="36" class="brand-logo light-only">
      <img src="<?= e(site_logo(true)) ?>" alt="<?= e(Lang::siteName()) ?>" width="150" height="36" class="brand-logo dark-only">
    </a>

    <nav class="main-nav" aria-label="main">
      <a class="nav-link<?= $isActive('/matches') ?: $isActive('/') ?>" href="<?= e(path('matches')) ?>"><?= e(t('nav.matches')) ?></a>
      <a class="nav-link nav-live<?= $isActive('/live') ?>" href="<?= e(path('live')) ?>"><span class="live-dot"></span><?= e(t('nav.live')) ?></a>
      <a class="nav-link<?= $isActive('/news') ?>" href="<?= e(path('news')) ?>"><?= e(t('nav.news')) ?></a>
      <a class="nav-link<?= $isActive('/videos') ?: $isActive('/video') ?>" href="<?= e(path('videos')) ?>"><?= e(t('nav.videos')) ?></a>
      <a class="nav-link<?= $isActive('/standings') ?>" href="<?= e(path('standings')) ?>"><?= e(t('nav.standings')) ?></a>
      <a class="nav-link<?= $isActive('/top-scorers') ?>" href="<?= e(path('top-scorers')) ?>"><?= e(t('nav.scorers')) ?></a>
      <a class="nav-link<?= $isActive('/leagues') ?>" href="<?= e(path('leagues')) ?>"><?= e(t('nav.leagues')) ?></a>
    </nav>

    <div class="header-actions">
      <a class="icon-btn" href="<?= e(path('search')) ?>" aria-label="<?= e(t('nav.search')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      </a>
      <a class="icon-btn" href="<?= e(path('favorites')) ?>" aria-label="<?= e(t('nav.favorites')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
      </a>
      <button class="icon-btn" id="theme-toggle" aria-label="<?= e(t('misc.theme')) ?>">
        <svg class="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
        <svg class="ic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <a class="lang-switch" href="<?= e($altPath) ?>" rel="alternate" hreflang="<?= Lang::current() === 'ar' ? 'en' : 'ar' ?>">
        <?= Lang::current() === 'ar' ? 'EN' : 'عربي' ?>
      </a>
    </div>
  </div>
</header>
