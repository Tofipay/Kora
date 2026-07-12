'use client';
import { usePathname } from 'next/navigation';
import Script from 'next/script';
import { useFetch } from '@/lib/useFetch';
import { API_BASE, SITE_URL } from '@/lib/site';
import { idFromSlug, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { VideoCard } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

/** Fetch a single Btolat video's playable details from the PHP front-controller. */
async function fetchVideo(id: number): Promise<{ data: any; stale: boolean }> {
  try {
    const r = await fetch(`${API_BASE}/video/${id}?lang=ar`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const j = await r.json();
    return { data: j?.data ?? j, stale: !!j?.stale };
  } catch { return { data: null, stale: true }; }
}

/**
 * In-site video player — client-rendered by trailing id. Reproduces the exact
 * data-* attributes from video-play.php so the reused app.js drives YouTube
 * embed-block detection, HLS via hls.js, the X embed and copy-link.
 */
export default function VideoPlay() {
  const path = usePathname() || '';
  const slug = path.split('/').filter(Boolean).pop() || '';
  const id = idFromSlug(slug) || Number(slug) || 0;
  const { data: v, loading } = useFetch<Dict | null>(() => fetchVideo(id), [id], null);

  if (loading) return <div className="container section"><Loading rows={4} /></div>;
  if (!v || !v.title) return <div className="container section"><Empty text={t('videos.unavailable')} /></div>;

  const title = String(v.title);
  const poster = String(v.thumbnail ?? '');
  const champ = String(v.champ_title ?? '');
  const media = String(v.media_url ?? '');
  const isHls = !!v.is_hls;
  const ytId = String(v.youtube_id ?? '');
  const tweetId = String(v.tweet_id ?? '');
  const xUrl = String(v.x_url ?? '');
  const embedIframe = String(v.embed_iframe ?? '');
  const shareUrl = `${SITE_URL}/video/${Number(v.id ?? id)}`;
  const xEmbed = tweetId !== '' ? `https://platform.twitter.com/embed/Tweet.html?id=${encodeURIComponent(tweetId)}&theme=light&hideCard=false&hideThread=true&lang=ar` : '';
  const related: Dict[] = (v.related as Dict[]) || [];

  return (
    <section className="video-page">
      {media && isHls ? <Script src="/assets/vendor/hls.min.js" strategy="afterInteractive" /> : null}
      <div className="container">
        <a className="wt-back" href="/videos">
          <svg viewBox="0 0 24 24" width={20} height={20} fill="none" stroke="currentColor" strokeWidth={2}><path d="M15 18l-6-6 6-6" /></svg>
          {t('videos.title')}
        </a>

        {media !== '' ? (
          <div className="vp-stage">
            <video className="vp-iframe" controls playsInline preload="metadata"
                   {...(isHls ? { 'data-hls': media } : {})} {...(poster ? { poster } : {})}>
              {!isHls ? <source src={media} /> : null}
            </video>
          </div>
        ) : ytId !== '' ? (
          <div className="vp-stage" data-yt={ytId} data-yt-player data-yt-guard {...(xEmbed ? { 'data-x-fallback': xEmbed } : {})}>
            <button className="vp-poster" type="button" data-yt-play aria-label={t('videos.play')}>
              {poster ? <img src={poster} alt={title} width={1280} height={720} /> : null}
              <span className="vp-big-play" aria-hidden="true"><svg viewBox="0 0 24 24" width={34} height={34} fill="currentColor"><path d="M8 5v14l11-7z" /></svg></span>
            </button>
          </div>
        ) : xEmbed !== '' ? (
          <div className="vp-stage vp-x">
            <iframe className="vp-iframe vp-x-frame" src={xEmbed} title="X" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen loading="lazy" />
          </div>
        ) : embedIframe !== '' ? (
          <div className="vp-stage">
            <iframe className="vp-iframe" src={embedIframe} title={title} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen referrerPolicy="no-referrer" loading="eager" frameBorder={0} />
          </div>
        ) : (
          <div className="vp-stage">
            <div className="vp-poster" aria-hidden="true">{poster ? <img src={poster} alt={title} width={1280} height={720} /> : null}</div>
            <div className="vp-blocked-note vp-static-note"><p>{t('videos.unavailable')}</p></div>
          </div>
        )}

        <div className="vp-info card glass-soft">
          <h1 className="vp-title">{title}</h1>
          <div className="vp-meta">
            {champ !== '' ? (
              <span className="vp-chip vp-champ">
                <svg viewBox="0 0 24 24" width={14} height={14} fill="none" stroke="currentColor" strokeWidth={2}><path d="M7 4h10v3a5 5 0 0 1-10 0zM5 5H3v2a3 3 0 0 0 3 3M19 5h2v2a3 3 0 0 1-3 3M9 15h6v5H9z" /></svg>
                {champ}
              </span>
            ) : null}
          </div>
          <div className="vp-actions">
            <button className="btn btn-ghost vp-act" type="button" onClick={() => (window as any).QShare?.()}>
              <svg viewBox="0 0 24 24" width={17} height={17} fill="none" stroke="currentColor" strokeWidth={2}><circle cx={6} cy={12} r={2.5} /><circle cx={18} cy={6} r={2.5} /><circle cx={18} cy={18} r={2.5} /><path d="m8.2 10.8 7.6-3.6m-7.6 6 7.6 3.6" /></svg>
              {t('misc.share')}
            </button>
            <button className="btn btn-ghost vp-act" type="button" data-copy-link={shareUrl}>
              <svg viewBox="0 0 24 24" width={17} height={17} fill="none" stroke="currentColor" strokeWidth={2}><rect x={9} y={9} width={12} height={12} rx={2} /><path d="M6 15H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1" /></svg>
              {t('videos.copy_link')}
            </button>
            {ytId !== '' ? (
              <a className="btn btn-ghost vp-act" href={`https://www.youtube.com/watch?v=${ytId}`} target="_blank" rel="noopener nofollow">
                {t('videos.watch_on')} YouTube
              </a>
            ) : null}
            {xUrl !== '' ? (
              <a className="btn btn-ghost vp-act" href={xUrl} target="_blank" rel="noopener nofollow">
                {t('videos.watch_on')} X
              </a>
            ) : null}
          </div>
        </div>

        {related.length > 0 && (
          <section className="section vp-related">
            <div className="section-head"><h2>{t('videos.related')}</h2></div>
            <div className="videos-grid">{related.slice(0, 8).map((rv, i) => <VideoCard key={rv.id ?? i} v={rv} />)}</div>
          </section>
        )}
      </div>
    </section>
  );
}
