'use client';
import { useFetch } from '@/lib/useFetch';
import { matchesApi, newsApi } from '@/lib/api';
import { groupByLeague, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { LiveCard, LeagueGroup, NewsCard } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

/**
 * Home — hero + live now + today's matches (grouped by league) + latest news.
 * Each section fetches the existing PHP API client-side, then app.js ticks the
 * live clocks. Mirrors the section structure of app/Views/pages/home.php.
 */
export default function HomePage() {
  const today = useFetch<Dict[]>(() => matchesApi.today(), [], []);
  const live = useFetch<Dict[]>(() => matchesApi.live(), [], []);
  const news = useFetch<Dict>(() => newsApi.page(1), [], { items: [] });

  const liveList = live.data || [];
  const grouped = groupByLeague(today.data || []).slice(0, 10);
  const newsItems: Dict[] = (news.data?.items as Dict[]) || [];

  return (
    <>
      <section className="hero">
        <div className="hero-bg" aria-hidden="true" />
        <div className="container hero-inner">
          <h1 className="hero-title">{t('home.hero.title')}</h1>
          <p className="hero-sub">{t('home.hero.sub')}</p>
          <div className="hero-cta">
            <a className="btn btn-primary" href="/today">{t('home.hero.cta')}</a>
            <a className="btn btn-ghost" href="/live"><span className="live-dot" /> {t('home.hero.cta2')}</a>
          </div>
        </div>
      </section>

      {liveList.length > 0 && (
        <section className="section container reveal">
          <div className="section-head">
            <h2><span className="live-dot" /> {t('home.live')}</h2>
            <a className="view-all" href="/live">{t('home.view_all')}</a>
          </div>
          <div className="hscroll live-slider">
            {liveList.map((m, i) => <LiveCard key={m.match_id ?? i} m={m} />)}
          </div>
        </section>
      )}

      <section className="section container reveal" id="today">
        <div className="section-head">
          <h2>{t('home.today')}</h2>
          <a className="view-all" href="/matches">{t('home.view_all')}</a>
        </div>
        {today.loading ? <Loading rows={6} />
          : grouped.length === 0 ? <Empty text={t('matches.none')} />
          : grouped.map((g, i) => <LeagueGroup key={i} group={g} />)}
      </section>

      <section className="section container reveal">
        <div className="section-head">
          <h2>{t('home.trending_news')}</h2>
          <a className="view-all" href="/news">{t('home.view_all')}</a>
        </div>
        {news.loading ? <Loading rows={3} />
          : newsItems.length === 0 ? <Empty text={t('news.none')} />
          : newsItems.length >= 4 ? (
            <div className="news-grid">
              {newsItems.slice(0, 1).map((n) => <NewsCard key={n.id} n={n} big />)}
              <div className="news-side">
                {newsItems.slice(1, 5).map((n) => <NewsCard key={n.id} n={n} />)}
              </div>
            </div>
          ) : (
            <div className="news-list">
              {newsItems.map((n) => <NewsCard key={n.id} n={n} />)}
            </div>
          )}
      </section>

      <section className="section container reveal">
        <div className="app-banner">
          <div className="ab-copy">
            <h2>{t('home.app_banner.title')}</h2>
            <p>{t('home.app_banner.sub')}</p>
            <button className="btn btn-light" id="install-btn" hidden>{t('home.app_banner.cta')}</button>
            <button className="btn btn-light" id="notify-btn">{t('misc.enable_notifications')}</button>
          </div>
          <div className="ab-art" aria-hidden="true">
            <img src="/assets/brand/icon-192.png" alt="" width={120} height={120} loading="lazy" />
          </div>
        </div>
      </section>
    </>
  );
}
