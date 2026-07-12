import type { Metadata } from 'next';
import MatchesDay from '@/components/MatchesDay';
import { t } from '@/lib/i18n';
export const metadata: Metadata = { title: 'مباريات الغد', description: 'جدول مباريات الغد ومواعيدها.', alternates: { canonical: '/tomorrow' } };
export default function Page() { return <MatchesDay day="tomorrow" title={t('day.tomorrow')} />; }
