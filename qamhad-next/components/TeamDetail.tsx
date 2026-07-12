'use client';
import { usePathname } from 'next/navigation';
import { useFetch } from '@/lib/useFetch';
import { teamApi } from '@/lib/api';
import { idFromSlug, teamName, teamImg, playerUrl, playerImg, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { MatchCard } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

/** Team page — header, upcoming fixtures, recent results and squad. */
export default function TeamDetail() {
  const path = usePathname() || '';
  const id = idFromSlug(path.split('/').filter(Boolean).pop() || '');
  const { data, loading } = useFetch<Dict | null>(() => teamApi.get(id), [id], null);

  if (loading) return <div className="container section"><Loading rows={6} /></div>;
  if (!data || !data.team) return <div className="container section"><Empty text={t('misc.error')} /></div>;

  const team = data.team as Dict;
  const fixtures = (data.fixtures as Dict[]) || [];
  const results = (data.results as Dict[]) || [];
  const squad = (data.squad as Dict[]) || [];

  return (
    <>
      <div className="container page-head team-head-row" style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
        <img src={teamImg(team, '128')} alt={teamName(team)} width={56} height={56} />
        <h1>{teamName(team)}</h1>
      </div>
      <div className="container">
        {fixtures.length > 0 && (
          <section className="section reveal">
            <div className="section-head"><h2>{t('team.fixtures')}</h2></div>
            <div className="league-matches">{fixtures.map((m, i) => <MatchCard key={m.match_id ?? i} m={m} />)}</div>
          </section>
        )}
        {results.length > 0 && (
          <section className="section reveal">
            <div className="section-head"><h2>{t('team.results')}</h2></div>
            <div className="league-matches">{results.map((m, i) => <MatchCard key={m.match_id ?? i} m={m} />)}</div>
          </section>
        )}
        {squad.length > 0 && (
          <section className="section reveal">
            <div className="section-head"><h2>{t('team.squad')}</h2></div>
            <div className="hscroll team-pills" style={{ flexWrap: 'wrap' }}>
              {squad.map((p, i) => {
                const label = String(p.title ?? p.full_title ?? p.name ?? '—');
                return (
                  <a className="team-pill glass-soft card-hover" key={i} href={playerUrl({ id: Number(p.row_id ?? p.id ?? 0), title: label })}>
                    <img src={playerImg(p, '64')} alt="" width={44} height={44} loading="lazy" /><span>{label}</span>
                  </a>
                );
              })}
            </div>
          </section>
        )}
        {fixtures.length === 0 && results.length === 0 && squad.length === 0 && <Empty text={t('misc.error')} />}
      </div>
    </>
  );
}
