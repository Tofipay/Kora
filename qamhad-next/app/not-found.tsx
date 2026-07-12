import type { Metadata } from 'next';
import { t } from '@/lib/i18n';

export const metadata: Metadata = { title: 'الصفحة غير موجودة' };

export default function NotFound() {
  return (
    <div className="container">
      <div className="empty-state glass-soft error-404">
        <b className="err-code">404</b>
        <h1>{t('misc.notfound')}</h1>
        <p>{t('misc.notfound_sub')}</p>
        <a className="btn btn-primary" href="/">{t('misc.gohome')}</a>
      </div>
    </div>
  );
}
