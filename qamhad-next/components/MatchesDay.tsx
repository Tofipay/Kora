'use client';
import { usePathname } from 'next/navigation';
import { useFetch } from '@/lib/useFetch';
import { matchesApi } from '@/lib/api';
import { groupByLeague, Dict } from '@/lib/helpers';
import { t } from '@/lib/i18n';
import { LeagueGroup } from '@/components/cards';
import { Loading, Empty } from '@/components/State';

type Day = 'today' | 'tomorrow' | 'yesterday' | 'live';

const fetcher = (day: Day) =>
  day === 'live' ? matchesApi.live()
  : day === 'tomorrow' ? matchesApi.tomorrow()
  : day === 'yesterday' ? matchesApi.yesterday()
  : matchesApi.today();

/** Matches list for a day tab (today/tomorrow/yesterday) or the live view. */
export default function MatchesDay({ day, title }: { day: Day; title: string }) {
  const path = usePathname() || '/matches';
  const { data, loading } = useFetch<Dict[]>(() => fetcher(day), [day], []);
  const groups = groupByLeague(data || []);

  const tab = (href: string, label: string) => (
    <a className={`day-tab${path.replace(/\/$/, '') === href ? ' active' : ''}`} href={href}>{label}</a>
  );

  return (
    <section className="section container">
      <div className="section-head"><h1>{title}</h1></div>
      <div className="day-tabs hscroll">
        {tab('/yesterday', t('day.yesterday'))}
        {tab('/matches', t('day.today'))}
        {tab('/tomorrow', t('day.tomorrow'))}
        {tab('/live', t('matches.live_now'))}
      </div>
      {loading ? <Loading rows={8} />
        : groups.length === 0 ? <Empty text={t('matches.none')} />
        : groups.map((g, i) => <LeagueGroup key={i} group={g} />)}
    </section>
  );
}
