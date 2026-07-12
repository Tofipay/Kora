'use client';
import { useState } from 'react';
import { useFetch } from '@/lib/useFetch';
import { newsApi } from '@/lib/api';
import { Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { NewsCard } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

/** News listing with progressive "load more" over the PHP /api/news.php pages. */
export default function NewsList() {
  const [page, setPage] = useState(1);
  const [items, setItems] = useState<Dict[]>([]);
  const { data, loading } = useFetch<Dict>(() => newsApi.page(page), [page], { items: [], has_next: false });

  const pageItems: Dict[] = (data?.items as Dict[]) || [];
  // Merge new page into the accumulated list (dedupe by id).
  if (pageItems.length && items[items.length - 1]?._page !== page) {
    const merged = [...items];
    const seen = new Set(items.map((i) => i.id));
    for (const it of pageItems) if (!seen.has(it.id)) merged.push({ ...it, _page: page });
    if (merged.length !== items.length) setItems(merged);
  }

  const list = items.length ? items : pageItems;

  return (
    <section className="section container">
      <div className="section-head"><h1>{t('news.title')}</h1></div>
      {loading && list.length === 0 ? <Loading rows={6} />
        : list.length === 0 ? <Empty text={t('news.none')} />
        : (
          <>
            <div className="news-list">
              {list.map((n, i) => <NewsCard key={n.id ?? i} n={n} />)}
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
    </section>
  );
}
