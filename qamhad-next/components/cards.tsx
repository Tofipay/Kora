'use client';
/**
 * Card components — 1:1 React ports of the PHP partials (match-card,
 * league-group, news-card, video-card, live-card, featured-card). Class names,
 * data-* attributes and SVGs are identical so the reused app.css and app.js
 * enhance them exactly as before.
 */
import { t } from '@/lib/i18n';
import {
  Dict, teamOf, teamName, teamImg, leagueImg, newsImg, matchUrl, leagueUrl, newsUrl,
  matchState, liveClockAttrs, formatDateLong, formatDateShort, excerpt, timeAgo, isoDate,
} from '@/lib/helpers';

export function MatchCard({ m }: { m: Dict }) {
  const home = teamOf(m, 'home'), away = teamOf(m, 'away');
  const state = matchState(m);
  const mid = Number(m.match_id ?? 0);
  const hs = Number(m.home_scores ?? 0), as = Number(m.away_scores ?? 0);
  return (
    <a className="match-card card-hover" href={matchUrl(m)} data-match={mid} data-state={state.key}>
      <div className="mc-team mc-home">
        <img src={teamImg(home)} alt={teamName(home)} width={34} height={34} loading="lazy" decoding="async" />
        <span className="mc-name">{teamName(home)}</span>
      </div>
      <div className="mc-center">
        {state.started ? (
          <>
            <span className="mc-score" data-score><span data-hs>{hs}</span><i>-</i><span data-as>{as}</span></span>
            <span className={`mc-status ${state.live ? 'is-live' : 'is-ft'}`} data-status {...liveClockAttrs(state)}>{state.label}</span>
          </>
        ) : (
          <>
            <span className="mc-time" data-ts={Number(m.match_timestamp ?? 0)}>{state.label}</span>
            <span className="mc-status is-soon" data-status>{t('status.notstarted')}</span>
          </>
        )}
      </div>
      <div className="mc-team mc-away">
        <img src={teamImg(away)} alt={teamName(away)} width={34} height={34} loading="lazy" decoding="async" />
        <span className="mc-name">{teamName(away)}</span>
      </div>
    </a>
  );
}

export function LeagueGroup({ group }: { group: { league: Dict; matches: Dict[] } }) {
  const lg = group.league ?? {};
  return (
    <section className="league-block reveal">
      <a className="league-head glass-soft" href={leagueUrl(lg)}>
        <img src={leagueImg(lg)} alt="" width={26} height={26} loading="lazy" decoding="async" />
        <h3>{lg.title ?? ''}</h3>
        <svg className="chev" viewBox="0 0 24 24" width={18} height={18} fill="none" stroke="currentColor" strokeWidth={2}><path d="m9 6 6 6-6 6" /></svg>
      </a>
      <div className="league-matches">
        {group.matches.map((m, i) => <MatchCard key={m.match_id ?? i} m={m} />)}
      </div>
    </section>
  );
}

export function LiveCard({ m }: { m: Dict }) {
  const home = teamOf(m, 'home'), away = teamOf(m, 'away');
  const st = matchState(m);
  return (
    <a className="live-card glass" href={matchUrl(m)} data-match={Number(m.match_id ?? 0)}>
      <div className="lc-league"><img src={leagueImg(m.championship ?? {})} alt="" width={16} height={16} loading="lazy" /><span>{m.championship?.title ?? ''}</span></div>
      <div className="lc-row">
        <span className="lc-team"><img src={teamImg(home)} alt="" width={28} height={28} loading="lazy" />{teamName(home)}</span>
        <span className="lc-score" data-hs>{Number(m.home_scores ?? 0)}</span>
      </div>
      <div className="lc-row">
        <span className="lc-team"><img src={teamImg(away)} alt="" width={28} height={28} loading="lazy" />{teamName(away)}</span>
        <span className="lc-score" data-as>{Number(m.away_scores ?? 0)}</span>
      </div>
      <span className="lc-minute" data-status {...liveClockAttrs(st)}>{st.label}</span>
    </a>
  );
}

export function FeaturedCard({ m }: { m: Dict }) {
  const home = teamOf(m, 'home'), away = teamOf(m, 'away');
  const st = matchState(m);
  return (
    <a className="featured-card glass card-hover" href={matchUrl(m)} data-match={Number(m.match_id ?? 0)}>
      <div className="fc-league"><img src={leagueImg(m.championship ?? {})} alt="" width={18} height={18} loading="lazy" /><span>{m.championship?.title ?? ''}</span></div>
      <div className="fc-teams">
        <span className="fc-team"><img src={teamImg(home, '128')} alt={teamName(home)} width={52} height={52} loading="lazy" /><b>{teamName(home)}</b></span>
        <span className="fc-mid">
          {st.started ? (
            <>
              <b className="fc-score"><span data-hs>{Number(m.home_scores ?? 0)}</span> - <span data-as>{Number(m.away_scores ?? 0)}</span></b>
              <small className={st.live ? 'is-live' : ''} data-status {...liveClockAttrs(st)}>{st.label}</small>
            </>
          ) : (
            <>
              <b className="fc-time" data-ts={Number(m.match_timestamp ?? 0)}>{st.label}</b>
              <small className="fc-date">{formatDateLong(m.match_date ?? '')}</small>
            </>
          )}
        </span>
        <span className="fc-team"><img src={teamImg(away, '128')} alt={teamName(away)} width={52} height={52} loading="lazy" /><b>{teamName(away)}</b></span>
      </div>
    </a>
  );
}

export function NewsCard({ n, big = false }: { n: Dict; big?: boolean }) {
  return (
    <a className={`news-card card-hover${big ? ' news-big' : ''}`} href={newsUrl(n)}>
      <div className="news-thumb">
        <img src={newsImg(n, big ? '640' : '150')} alt={n.title ?? ''} loading="lazy" decoding="async" width={big ? 640 : 150} height={big ? 360 : 100} />
      </div>
      <div className="news-body">
        <h3 className="news-title">{n.title ?? ''}</h3>
        {big && n.news_desc ? <p className="news-desc">{excerpt(String(n.news_desc), 120)}</p> : null}
        <time className="news-time" dateTime={isoDate(n.created_at)}>{timeAgo(n.created_at)}</time>
      </div>
    </a>
  );
}

export function VideoCard({ v }: { v: Dict }) {
  const yt = String(v.youtube_id ?? '');
  const vid = Number(v.id ?? 0);
  const thumb = String(v.thumbnail ?? '');
  const title = String(v.title ?? '').trim();
  const champ = String(v.champ_title ?? '');
  const dLabel = v.created_at ? formatDateShort(v.created_at) : '';
  if (title === '' || (String(v.video_url ?? '').trim() === '' && vid < 1 && yt === '')) return null;

  let href = '', ext = false;
  if (vid > 0) href = `/video/${vid}`;
  else if (yt !== '') href = `/video/${yt}`;
  else { href = String(v.video_url); ext = true; }
  const prov = v.video_type === 'youtube' ? 'YouTube' : v.video_type === 'fifa' ? 'FIFA+' : '';

  const inner = (
    <>
      <span className="vc-thumb">
        <span className="vc-thumb-ph" aria-hidden="true"><img src="/assets/brand/icon.svg" alt="" width={46} height={46} loading="lazy" /></span>
        {thumb !== '' ? <img className="vc-img" src={thumb} alt="" loading="lazy" width={480} height={270} /> : null}
        <span className="vc-play" aria-hidden="true"><svg viewBox="0 0 24 24" width={20} height={20} fill="currentColor"><path d="M8 5v14l11-7z" /></svg></span>
        {prov !== '' ? <span className="vc-provider" dir="ltr">{prov}</span> : null}
      </span>
      <span className="vc-body">
        <b className="vc-title">{title}</b>
        <span className="vc-meta">
          {champ !== '' ? <span className="vc-champ">{champ}</span> : null}
          {dLabel !== '' ? <span className="vc-date">{dLabel}</span> : null}
        </span>
      </span>
    </>
  );

  return ext
    ? <a className="vcard card-hover" href={href} target="_blank" rel="noopener nofollow" data-video-card>{inner}</a>
    : <a className="vcard card-hover" href={href} data-video-card>{inner}</a>;
}
