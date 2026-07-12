import type { Metadata } from 'next';
import { FAVORITE_LEAGUES } from '@/lib/site';
import { leagueUrl } from '@/lib/helpers';
import { t } from '@/lib/i18n';

export const metadata: Metadata = {
  title: 'البطولات والدوريات',
  description: 'أبرز بطولات ودوريات كرة القدم: كأس العالم، دوري أبطال أوروبا والدوريات الأوروبية والعربية.',
  alternates: { canonical: '/leagues' },
};

export default function LeaguesPage() {
  return (
    <>
      <div className="container page-head"><h1>{t('leagues.title')}</h1></div>
      <div className="container">
        <div className="hscroll league-pills" style={{ flexWrap: 'wrap' }}>
          {FAVORITE_LEAGUES.map((lg) => (
            <a className="league-pill glass-soft card-hover" key={lg.url_id} href={leagueUrl({ url_id: lg.url_id, title: lg.ar })}>
              <img src="/assets/brand/icon.svg" alt="" width={30} height={30} loading="lazy" />
              <span>{lg.ar}</span>
            </a>
          ))}
        </div>
      </div>
    </>
  );
}
