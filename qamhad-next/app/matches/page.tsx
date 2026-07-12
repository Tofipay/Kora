import type { Metadata } from 'next';
import MatchesDay from '@/components/MatchesDay';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'جدول المباريات', description: 'جدول مباريات اليوم ونتائجها المباشرة لحظة بلحظة.', alternates: { canonical: '/matches' } };
export default function Page() { return <MatchesDay day="today" title={t('matches.title')} />; }
