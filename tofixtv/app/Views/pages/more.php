<?php
/** Settings & More — the mobile bottom-nav hub (works on desktop too). */
use TofiXTv\Core\Lang;

$isAr = Lang::isRtl();
$altPath = Lang::alternatePath($_SERVER['REQUEST_URI'] ?? '/');
$arHref = Lang::current() === 'ar' ? path('more') : $altPath;
$enHref = Lang::current() === 'en' ? path('more') : $altPath;

/** [icon-path, label, href] rows for the sections group. */
$sections = [
    ['M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9zM12 3v18M3.5 9h17M3.5 15h17', t('nav.live'),      path('live'),        true],
    ['M8 21h8M12 17v4M5 3h14v6a7 7 0 0 1-14 0zM5 5H3v2a4 4 0 0 0 2 3.5M19 5h2v2a4 4 0 0 1-2 3.5', t('nav.leagues'), path('leagues'), false],
    ['M4 20V10M10 20V4M16 20v-7M21 20H3',                            t('nav.standings'), path('standings'),   false],
    ['M12 15a5 5 0 1 0-5-5 5 5 0 0 0 5 5zM9 14l-1.5 7L12 18.5 16.5 21 15 14', t('nav.scorers'), path('top-scorers'), false],
    ['M2 5h20v14H2zM10 9l5 3-5 3z',                                  t('nav.videos'),    path('videos'),      false],
    ['M2 7h20v13H2zM8 2l4 4 4-4',                                    t('more.tv_channels'), path('live'),     false],
    ['M2 4h20v16H2zM2 9h20M7 4l2.5 5M12 4l2.5 5M17 4l2.5 5',         t('nav.movies'),    path('movies'),      false],
    ['M2 7h20v13H2zM8 2l4 4 4-4M9.5 11l5 3-5 3z',                    t('nav.series'),    path('series'),      false],
];
$info = [
    [t('footer.about'),   path('about')],
    [t('footer.privacy'), path('privacy')],
    [t('footer.terms'),   path('terms')],
    [t('page.cookies.title'), path('cookies')],
    [t('cookies.settings_title'), path('cookie-settings')],
    [t('footer.contact'), path('contact')],
];
?>
<div class="container page-head">
  <h1><?= e(t('more.title')) ?></h1>
  <p class="page-sub"><?= e(t('more.desc')) ?></p>
</div>

<div class="container more-page">

  <!-- General: language + appearance -->
  <section class="more-group glass-soft">
    <h2 class="mg-title"><?= e(t('more.general')) ?></h2>

    <div class="mg-row">
      <span class="mg-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
      </span>
      <span class="mg-label"><?= e(t('more.language')) ?></span>
      <span class="mg-ctl seg" role="group" aria-label="<?= e(t('more.language')) ?>">
        <a class="seg-btn<?= $isAr ? ' active' : '' ?>" href="<?= e($arHref) ?>" data-set-lang="ar" rel="alternate" hreflang="ar">العربية</a>
        <a class="seg-btn<?= !$isAr ? ' active' : '' ?>" href="<?= e($enHref) ?>" data-set-lang="en" rel="alternate" hreflang="en">English</a>
      </span>
    </div>

    <div class="mg-row">
      <span class="mg-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      </span>
      <span class="mg-label"><?= e(t('more.appearance')) ?></span>
      <span class="mg-ctl seg" role="group" aria-label="<?= e(t('more.appearance')) ?>">
        <button class="seg-btn" type="button" data-set-theme="dark"><?= e(t('more.dark')) ?></button>
        <button class="seg-btn" type="button" data-set-theme="light"><?= e(t('more.light')) ?></button>
      </span>
    </div>

    <div class="mg-row">
      <span class="mg-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></svg>
      </span>
      <span class="mg-label"><?= e(t('misc.notifications')) ?></span>
      <span class="mg-ctl">
        <button class="btn btn-ghost btn-sm" type="button" onclick="var b=document.getElementById('notify-btn'); if(b) b.click();"><?= e(t('misc.enable_notifications')) ?></button>
      </span>
    </div>

    <div class="mg-row">
      <span class="mg-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
      </span>
      <span class="mg-label"><?= e(t('nav.favorites')) ?></span>
      <span class="mg-ctl">
        <a class="btn btn-ghost btn-sm" href="<?= e(path('favorites')) ?>"><?= e(t('fav.title')) ?></a>
      </span>
    </div>
  </section>

  <!-- Sections -->
  <section class="more-group glass-soft">
    <h2 class="mg-title"><?= e(t('more.sections')) ?></h2>
    <div class="mg-grid">
      <?php foreach ($sections as [$d, $label, $href, $liveDot]): ?>
      <a class="mg-tile card-hover" href="<?= e($href) ?>">
        <span class="mg-tile-ic<?= $liveDot ? ' is-live' : '' ?>" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7"><path d="<?= e($d) ?>"/></svg>
          <?php if ($liveDot): ?><i class="live-dot"></i><?php endif; ?>
        </span>
        <span><?= e($label) ?></span>
        <svg class="mg-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="<?= $isAr ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6' ?>"/></svg>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Information -->
  <section class="more-group glass-soft">
    <h2 class="mg-title"><?= e(t('more.information')) ?></h2>
    <div class="mg-list">
      <?php foreach ($info as [$label, $href]): ?>
      <a class="mg-item" href="<?= e($href) ?>">
        <span><?= e($label) ?></span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="<?= $isAr ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6' ?>"/></svg>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <p class="more-version"><?= e(Lang::siteName()) ?> · <?= e(build_token()) ?></p>
</div>
