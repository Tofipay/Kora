import type { Metadata } from 'next';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'غير متصل', robots: { index: false } };
export default function Offline() {
  return (
    <div className="container">
      <div className="empty-state glass-soft">
        <svg viewBox="0 0 24 24" width={44} height={44} fill="none" stroke="currentColor" strokeWidth={1.6}><path d="M1 1l22 22M9 9a10 10 0 0 0-4.6 2.6M5.5 12.9a7 7 0 0 1 3-1.9m3.9 2.2a4.5 4.5 0 0 1 2.8 1.3M12 19h.01M16.7 8.6A10 10 0 0 1 19.6 11" /></svg>
        <h1>{t('misc.offline')}</h1>
        <a className="btn btn-primary" href="/">{t('misc.gohome')}</a>
      </div>
    </div>
  );
}
