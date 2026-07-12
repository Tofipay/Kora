'use client';
import { usePathname } from 'next/navigation';
import { useFetch } from '@/lib/useFetch';
import { newsApi } from '@/lib/api';
import { idFromSlug, newsImg, formatDateLong, isoDate, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { NewsCard } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

/** Keep only safe formatting tags from upstream article HTML. */
function sanitize(html: string): string {
  return (html || '')
    .replace(/<\s*(script|style|iframe|object|embed)[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/gi, '')
    .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    .replace(/(href)\s*=\s*(["']?)\s*javascript:[^"'>\s]*\2?/gi, '');
}

export default function NewsArticle() {
  const path = usePathname() || '';
  const slug = path.split('/').filter(Boolean).pop() || '';
  const id = idFromSlug(slug);
  const { data, loading } = useFetch<Dict | null>(() => newsApi.article(id), [id], null);

  if (loading) return <div className="container section"><Loading rows={5} /></div>;
  if (!data || !(data.title || data.article)) return <div className="container section"><Empty text={t('news.none')} /></div>;

  const n: Dict = data.article ?? data;
  const related: Dict[] = (data.related as Dict[]) || [];
  const body = sanitize(String(n.full_news ?? ''));
  const src = ['link', 'url', 'source_url', 'source'].map((k) => n[k]).find((u) => typeof u === 'string' && /^https?:\/\//i.test(u));

  return (
    <>
      <article className="container article">
        <header className="article-head">
          <h1>{n.title ?? ''}</h1>
          <div className="article-meta">
            <time dateTime={isoDate(n.created_at)}>{t('news.published')}: {formatDateLong(n.created_at)}</time>
            <button className="icon-btn" onClick={() => (window as any).QShare?.()} aria-label={t('misc.share')}>
              <svg viewBox="0 0 24 24" width={18} height={18} fill="none" stroke="currentColor" strokeWidth={2}><circle cx={6} cy={12} r={2.5} /><circle cx={18} cy={6} r={2.5} /><circle cx={18} cy={18} r={2.5} /><path d="m8.2 10.8 7.6-3.6m-7.6 6 7.6 3.6" /></svg>
            </button>
          </div>
        </header>

        <figure className="article-cover">
          <img src={newsImg(n, '1200')} srcSet={`${newsImg(n, '640')} 640w, ${newsImg(n, '1200')} 1200w`}
               sizes="(max-width: 720px) 100vw, 960px" alt={n.title ?? ''} width={1200} height={675} />
        </figure>

        <div className="article-body">
          {body.replace(/<[^>]+>/g, '').trim() === ''
            ? <p>{String(n.news_desc ?? n.description ?? '')}</p>
            : <div dangerouslySetInnerHTML={{ __html: body }} />}
          {n.partial ? (
            <p className="article-partial muted">
              {t('news.partial')}{' '}
              {src ? <a href={src} target="_blank" rel="noopener nofollow">{t('news.read_source')}</a> : null}
            </p>
          ) : null}
        </div>
      </article>

      {related.length > 0 && (
        <section className="section container">
          <div className="section-head"><h2>{t('news.related')}</h2></div>
          <div className="news-list">{related.map((r, i) => <NewsCard key={r.id ?? i} n={r} />)}</div>
        </section>
      )}
    </>
  );
}
