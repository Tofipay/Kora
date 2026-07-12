import type { Metadata } from 'next';
import MatchesDay from '@/components/MatchesDay';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'المباريات المباشرة', description: 'المباريات الجارية الآن مع النتائج المباشرة لحظة بلحظة.', alternates: { canonical: '/live' } };
export default function Page() { return <MatchesDay day="live" title={t('home.live')} />; }
