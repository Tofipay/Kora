import type { Metadata } from 'next';
import MatchesDay from '@/components/MatchesDay';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'مباريات اليوم', description: 'كل مباريات اليوم بث مباشر ونتائج لحظة بلحظة.', alternates: { canonical: '/today' } };
export default function Page() { return <MatchesDay day="today" title={t('day.today')} />; }
