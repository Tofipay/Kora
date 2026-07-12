import type { Metadata } from 'next';
import MatchesDay from '@/components/MatchesDay';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'مباريات أمس', description: 'نتائج مباريات أمس كاملة.', alternates: { canonical: '/yesterday' } };
export default function Page() { return <MatchesDay day="yesterday" title={t('day.yesterday')} />; }
