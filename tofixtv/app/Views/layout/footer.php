<?php use TofiXTv\Core\Lang; ?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <img src="<?= e(site_logo(true)) ?>" alt="<?= e(Lang::siteName()) ?>" width="160" height="38" loading="lazy">
      <p><?= e(t('footer.desc')) ?></p>
    </div>
    <nav aria-label="<?= e(t('footer.sections')) ?>">
      <h4><?= e(t('footer.sections')) ?></h4>
      <a href="<?= e(path('matches')) ?>"><?= e(t('nav.matches')) ?></a>
      <a href="<?= e(path('live')) ?>"><?= e(t('nav.live')) ?></a>
      <a href="<?= e(path('news')) ?>"><?= e(t('nav.news')) ?></a>
      <a href="<?= e(path('standings')) ?>"><?= e(t('nav.standings')) ?></a>
      <a href="<?= e(path('top-scorers')) ?>"><?= e(t('nav.scorers')) ?></a>
      <a href="<?= e(path('leagues')) ?>"><?= e(t('nav.leagues')) ?></a>
      <a href="<?= e(path('channels')) ?>"><?= e(t('nav.channels')) ?></a>
      <a href="<?= e(path('movies')) ?>"><?= e(t('nav.movies')) ?></a>
      <a href="<?= e(path('series')) ?>"><?= e(t('nav.series')) ?></a>
    </nav>
    <nav aria-label="<?= e(t('footer.legal')) ?>">
      <h4><?= e(t('footer.legal')) ?></h4>
      <a href="<?= e(path('about')) ?>"><?= e(t('footer.about')) ?></a>
      <a href="<?= e(path('privacy')) ?>"><?= e(t('footer.privacy')) ?></a>
      <a href="<?= e(path('terms')) ?>"><?= e(t('footer.terms')) ?></a>
      <a href="<?= e(path('cookies')) ?>"><?= e(t('page.cookies.title')) ?></a>
      <a href="<?= e(path('cookie-settings')) ?>"><?= e(t('cookies.settings_title')) ?></a>
      <a href="<?= e(path('contact')) ?>"><?= e(t('footer.contact')) ?></a>
    </nav>
    <div>
      <h4><?= e(t('footer.follow')) ?></h4>
      <a class="tg-follow" href="https://t.me/alokalive" target="_blank" rel="noopener">
        <span class="tg-ic" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.2 13.4l-4.8-1.5c-1-.3-1.1-1 .2-1.5l18.7-7.2c.9-.3 1.7.2 1.4 1.1z"/></svg>
        </span>
        <span class="tg-txt">
          <b>Telegram</b>
          <small>@alokalive</small>
        </span>
      </a>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <a class="rights-link" href="<?= e(SITE_URL) ?>/" title="<?= e(Lang::siteName()) ?>"><?= e(Lang::siteName()) ?></a> — <?= e(t('footer.rights')) ?></span>
  </div>
</footer>
