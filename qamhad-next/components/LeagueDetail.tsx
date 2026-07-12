'use client';
import { usePathname } from 'next/navigation';
import { useFetch } from '@/lib/useFetch';
import { standingsApi } from '@/lib/api';
import { idFromSlug, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import StandingsTable from '@/components/StandingsTable';
import { Loading, Empty } from '@/components/State';

/** League page — standings table for the league id in the URL. */
export default function LeagueDetail() {
  const path = usePathname() || '';
  const id = idFromSlug(path.split('/').filter(Boolean).pop() || '');
  const { data, loading } = useFetch<Dict>(() => standingsApi.league(id), [id], { standings: [], scorers: [] });

  const rows = (data?.standings as Dict[]) || [];

  return (
    <>
      <div className="container page-head"><h1>{t('standings.title')}</h1></div>
      <div className="container">
        {loading ? <Loading rows={8} />
          : rows.length === 0 ? <Empty text={t('standings.none')} />
          : <div className="card glass-soft"><StandingsTable rows={rows} /></div>}
      </div>
    </>
  );
}
