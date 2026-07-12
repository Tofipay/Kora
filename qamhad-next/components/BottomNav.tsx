'use client';
import { usePathname } from 'next/navigation';
import { t } from '@/lib/i18n';

/** Mobile bottom navigation — port of app/Views/layout/bottomnav.php. */
export default function BottomNav() {
  const path = usePathname() || '/';
  const bare = '/' + path.replace(/^\/+/, '').replace(/\/$/, '');
  const on = (p: string) => ((p === '/' ? bare === '/' : bare.startsWith(p)) ? ' active' : '');

  return (
    <nav className="bottom-nav glass" aria-label="mobile">
      <a className={`bn-item${on('/matches') || on('/')}`} href="/matches">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} width={22} height={22}><circle cx={12} cy={12} r={9} /><path d="M12 3v18M3.5 9h17M3.5 15h17" opacity=".4" /><circle cx={12} cy={12} r={3.2} /></svg>
        <span>{t('nav.matches')}</span>
      </a>
      <a className={`bn-item${on('/news')}`} href="/news">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} width={22} height={22}><rect x={3} y={4} width={18} height={16} rx={3} /><path d="M7 9h10M7 13h6" /></svg>
        <span>{t('nav.news')}</span>
      </a>
      <a className={`bn-item${on('/videos') || on('/video')}`} href="/videos">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} width={22} height={22}><rect x={2} y={5} width={20} height={14} rx={4} /><path d="m10 9 5 3-5 3z" fill="currentColor" stroke="none" /></svg>
        <span>{t('nav.videos')}</span>
      </a>
      <a className={`bn-item${on('/standings')}`} href="/standings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} width={22} height={22}><path d="M5 20V10M12 20V4M19 20v-7" /></svg>
        <span>{t('nav.standings')}</span>
      </a>
      <a className={`bn-item${on('/leagues')}`} href="/leagues">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} width={22} height={22}><circle cx={5} cy={6} r={1.6} /><circle cx={5} cy={12} r={1.6} /><circle cx={5} cy={18} r={1.6} /><path d="M10 6h9M10 12h9M10 18h9" /></svg>
        <span>{t('nav.more')}</span>
      </a>
    </nav>
  );
}
