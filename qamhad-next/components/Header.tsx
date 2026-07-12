'use client';
import { usePathname } from 'next/navigation';
import { t } from '@/lib/i18n';
import { SITE_NAME } from '@/lib/site';

/** Site header — faithful port of app/Views/layout/header.php. */
export default function Header() {
  const path = usePathname() || '/';
  const active = (p: string): string => {
    const b = '/' + path.replace(/^\/+/, '').replace(/\/$/, '');
    const bb = b === '/' ? '/' : b;
    if (p === '/') return bb === '/' ? ' active' : '';
    return bb.startsWith(p) ? ' active' : '';
  };

  return (
    <header className="site-header glass">
      <div className="container header-inner">
        <a className="brand" href="/" aria-label={SITE_NAME}>
          <img src="/assets/brand/logo.svg" alt={SITE_NAME} width={150} height={36} className="brand-logo light-only" />
          <img src="/assets/brand/logo-dark.svg" alt={SITE_NAME} width={150} height={36} className="brand-logo dark-only" />
        </a>

        <nav className="main-nav" aria-label="main">
          <a className={`nav-link${active('/matches') || active('/')}`} href="/matches">{t('nav.matches')}</a>
          <a className={`nav-link nav-live${active('/live')}`} href="/live"><span className="live-dot" />{t('nav.live')}</a>
          <a className={`nav-link${active('/news')}`} href="/news">{t('nav.news')}</a>
          <a className={`nav-link${active('/videos') || active('/video')}`} href="/videos">{t('nav.videos')}</a>
          <a className={`nav-link${active('/standings')}`} href="/standings">{t('nav.standings')}</a>
          <a className={`nav-link${active('/top-scorers')}`} href="/top-scorers">{t('nav.scorers')}</a>
          <a className={`nav-link${active('/leagues')}`} href="/leagues">{t('nav.leagues')}</a>
        </nav>

        <div className="header-actions">
          <a className="icon-btn" href="/search" aria-label={t('nav.search')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} width={20} height={20}><circle cx={11} cy={11} r={7} /><path d="m21 21-4.3-4.3" /></svg>
          </a>
          <a className="icon-btn" href="/favorites" aria-label={t('nav.favorites')}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} width={20} height={20}><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z" /></svg>
          </a>
          <button className="icon-btn" id="theme-toggle" aria-label={t('misc.theme')}>
            <svg className="ic-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} width={20} height={20}><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
            <svg className="ic-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} width={20} height={20}><circle cx={12} cy={12} r={4} /><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
          </button>
          <a className="lang-switch" href="/en" rel="alternate" hrefLang="en">EN</a>
        </div>
      </div>
    </header>
  );
}
