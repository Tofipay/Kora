'use client';
import { useState } from 'react';
import { useFetch } from '@/lib/useFetch';
import { videosApi } from '@/lib/api';
import { Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { VideoCard } from '@/components/cards';
import { Loading } from '@/components/State';

/** Videos listing — championship-agnostic client fetch with "load more". */
export default function VideosList() {
  const [page, setPage] = useState(1);
  const [items, setItems] = useState<Dict[]>([]);
  const { data, loading } = useFetch<Dict>(() => videosApi.page(page), [page], { items: [], has_next: false });

  const pageItems: Dict[] = (data?.items as Dict[]) || [];
  if (pageItems.length && items[items.length - 1]?._page !== page) {
    const merged = [...items];
    const seen = new Set(items.map((i) => i.id));
    for (const it of pageItems) if (!seen.has(it.id)) merged.push({ ...it, _page: page });
    if (merged.length !== items.length) setItems(merged);
  }
  const list = items.length ? items : pageItems;

  return (
    <>
      <div className="container page-head">
        <div>
          <h1>{t('videos.title')}</h1>
          <p className="page-sub">{t('videos.subtitle')}</p>
        </div>
      </div>

      <div className="container">
        {loading && list.length === 0 ? <Loading rows={6} />
          : list.length === 0 ? (
            <div className="empty-state glass-soft">
              <svg viewBox="0 0 24 24" width={44} height={44} fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden="true"><rect x={2} y={5} width={20} height={14} rx={3} /><path d="m10 9 5 3-5 3z" /></svg>
              <p>{t('videos.none')}</p>
            </div>
          ) : (
            <>
              <div className="videos-grid" data-videos-grid>
                {list.map((v, i) => <VideoCard key={v.id ?? i} v={v} />)}
              </div>
              {data?.has_next && (
                <div className="load-more-wrap" style={{ textAlign: 'center', marginTop: 20 }}>
                  <button className="btn btn-ghost" disabled={loading} onClick={() => setPage((p) => p + 1)}>
                    {loading ? t('misc.loading') : t('misc.show_more')}
                  </button>
                </div>
              )}
            </>
          )}

        <section className="videos-seo card glass-soft">
          <h2>{t('videos.seo_h')}</h2>
          <p>{t('videos.seo_p1')}</p>
          <p>{t('videos.seo_p2')}</p>
        </section>
      </div>
    </>
  );
}
