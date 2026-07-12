'use client';
import { useEffect, useState } from 'react';
import { standingsApi } from '@/lib/api';
import { FAVORITE_LEAGUES } from '@/lib/site';
import { Dict, leagueUrl, playerUrl, playerImg } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import StandingsTable from '@/components/StandingsTable';
import { Loading, Empty } from '@/components/State';

interface Table { url_id: number; title: string; rows: Dict[]; scorers: Dict[]; }

/** Standings + top scorers for all featured leagues (mirrors both PHP pages). */
export default function StandingsView({ mode }: { mode: 'standings' | 'scorers' }) {
  const [tables, setTables] = useState<Table[] | null>(null);

  useEffect(() => {
    let alive = true;
    Promise.all(FAVORITE_LEAGUES.map(async (f) => {
      const { data } = await standingsApi.league(f.url_id);
      return { url_id: f.url_id, title: f.ar, rows: (data?.standings as Dict[]) || [], scorers: (data?.scorers as Dict[]) || [] };
    })).then((res) => { if (alive) setTables(res); });
    return () => { alive = false; };
  }, []);

  if (tables === null) return <div className="container section"><Loading rows={8} /></div>;

  const usable = tables.filter((tb) => (mode === 'standings' ? tb.rows.length >= 3 : tb.scorers.length > 0));

  return (
    <>
      <div className="container page-head"><h1>{mode === 'standings' ? t('standings.title') : t('scorers.title')}</h1></div>
      <div className="container">
        {usable.length === 0 ? <Empty text={mode === 'standings' ? t('standings.none') : t('scorers.none')} />
          : usable.map((tb) => (
            <section className="section reveal" key={tb.url_id}>
              <div className="section-head">
                <h2>{tb.title}</h2>
                <a className="view-all" href={leagueUrl({ url_id: tb.url_id, title: tb.title })}>{t('home.view_all')}</a>
              </div>
              <div className="card glass-soft">
                {mode === 'standings'
                  ? <StandingsTable rows={tb.rows} />
                  : <ScorersList scorers={tb.scorers} leagueId={tb.url_id} />}
              </div>
            </section>
          ))}
      </div>
    </>
  );
}

/** Scorers list — port of partials/scorers-table.php. */
function ScorersList({ scorers, leagueId }: { scorers: Dict[]; leagueId: number }) {
  const label = (pi: Dict) => String(pi.title ?? pi.full_title ?? pi.name ?? '—');
  return (
    <ol className="scorers-list">
      {scorers.map((s, i) => {
        const pi = (s.player_info ?? {}) as Dict;
        const val = Number(s.goals ?? 0);
        const pen = Number(s.score_penalty ?? 0);
        return (
          <li className="scorer-row" key={i}>
            <span className="sc-rank">{i + 1}</span>
            <a className="sc-player" href={playerUrl({ id: Number(pi.id ?? s.player_id ?? 0), title: label(pi) }) + (leagueId ? `?lg=${leagueId}` : '')}>
              <img src={playerImg(pi, '64')} alt="" width={34} height={34} loading="lazy" decoding="async" />
              <span className="sc-meta">
                <strong>{label(pi)}</strong>
                {pi.team_name ? <small>{String(pi.team_name)}</small> : null}
              </span>
            </a>
            <span className="sc-val">
              <strong>{val}</strong>
              {pen > 0 ? <small>({pen} {t('scorers.pens')})</small> : null}
            </span>
          </li>
        );
      })}
    </ol>
  );
}
