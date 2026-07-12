import type { Metadata } from 'next';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'المفضلة', robots: { index: false } };
export default function Favorites() {
  return (
    <>
      <div className="container page-head"><h1>{t('fav.title')}</h1></div>
      <div className="container" id="favorites-root"
        data-l10n-teams={t('fav.teams')} data-l10n-leagues={t('fav.leagues')}
        data-l10n-matches={t('fav.matches')} data-l10n-empty={t('fav.empty')}>
        <noscript><p className="empty-note">{t('fav.empty')}</p></noscript>
      </div>
    </>
  );
}
