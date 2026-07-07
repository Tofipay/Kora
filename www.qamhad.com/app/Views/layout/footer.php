<?php use Qamhad\Core\Lang; ?>
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
    </nav>
    <nav aria-label="<?= e(t('footer.legal')) ?>">
      <h4><?= e(t('footer.legal')) ?></h4>
      <a href="<?= e(path('about')) ?>"><?= e(t('footer.about')) ?></a>
      <a href="<?= e(path('privacy')) ?>"><?= e(t('footer.privacy')) ?></a>
      <a href="<?= e(path('terms')) ?>"><?= e(t('footer.terms')) ?></a>
      <a href="<?= e(path('contact')) ?>"><?= e(t('footer.contact')) ?></a>
    </nav>
    <div>
      <h4><?= e(t('footer.follow')) ?></h4>
      <div class="social-row">
        <a class="icon-btn" href="#" aria-label="X"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3l-4.9-6.4L6.4 22H3.3l7.3-8.3L1.6 2h6.4l4.4 5.9L18.9 2zm-1.1 18h1.7L7.1 3.9H5.3L17.8 20z"/></svg></a>
        <a class="icon-btn" href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.7c0-.9.3-1.6 1.6-1.6h1.7V4.2c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.4V14h2.7v8h3.4z"/></svg></a>
        <a class="icon-btn" href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8" fill="currentColor"/></svg></a>
        <a class="icon-btn" href="#" aria-label="YouTube"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M23 8s-.2-1.6-.9-2.3c-.9-.9-1.8-.9-2.3-1C16.6 4.5 12 4.5 12 4.5s-4.6 0-7.8.2c-.4.1-1.4.1-2.3 1C1.2 6.4 1 8 1 8S.8 9.9.8 11.8v1.7c0 1.9.2 3.8.2 3.8s.2 1.6.9 2.3c.9.9 2 .9 2.5 1 1.8.2 7.6.2 7.6.2s4.6 0 7.8-.3c.4-.1 1.4-.1 2.3-1 .7-.7.9-2.3.9-2.3s.2-1.9.2-3.8v-1.7C23.2 9.9 23 8 23 8zM9.8 15.3V8.6l6.1 3.4-6.1 3.3z"/></svg></a>
      </div>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e(Lang::siteName()) ?> — <?= e(t('footer.rights')) ?></span>
  </div>
</footer>
