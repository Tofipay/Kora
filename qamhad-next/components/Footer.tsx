import { t } from '@/lib/i18n';
import { SITE_NAME, SITE_URL, TELEGRAM } from '@/lib/site';

/** Site footer — faithful port of app/Views/layout/footer.php. */
export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="container footer-grid">
        <div className="footer-brand">
          <img src="/assets/brand/logo-dark.svg" alt={SITE_NAME} width={160} height={38} loading="lazy" />
          <p>{t('footer.desc')}</p>
        </div>
        <nav aria-label={t('footer.sections')}>
          <h4>{t('footer.sections')}</h4>
          <a href="/matches">{t('nav.matches')}</a>
          <a href="/live">{t('nav.live')}</a>
          <a href="/news">{t('nav.news')}</a>
          <a href="/standings">{t('nav.standings')}</a>
          <a href="/top-scorers">{t('nav.scorers')}</a>
          <a href="/leagues">{t('nav.leagues')}</a>
        </nav>
        <nav aria-label={t('footer.legal')}>
          <h4>{t('footer.legal')}</h4>
          <a href="/about">{t('footer.about')}</a>
          <a href="/privacy">{t('footer.privacy')}</a>
          <a href="/terms">{t('footer.terms')}</a>
          <a href="/contact">{t('footer.contact')}</a>
        </nav>
        <div>
          <h4>{t('footer.follow')}</h4>
          <a className="tg-follow" href={TELEGRAM} target="_blank" rel="noopener">
            <span className="tg-ic" aria-hidden="true">
              <svg viewBox="0 0 24 24" width={26} height={26} fill="currentColor"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.2 13.4l-4.8-1.5c-1-.3-1.1-1 .2-1.5l18.7-7.2c.9-.3 1.7.2 1.4 1.1z" /></svg>
            </span>
            <span className="tg-txt"><b>Telegram</b><small>@liveqamhad</small></span>
          </a>
        </div>
      </div>
      <div className="container footer-bottom">
        <span>© {new Date().getFullYear()} <a className="rights-link" href={`${SITE_URL}/`} title={SITE_NAME}>{SITE_NAME}</a> — {t('footer.rights')}</span>
      </div>
    </footer>
  );
}
