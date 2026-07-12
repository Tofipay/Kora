import type { Metadata } from 'next';
import LeagueDetail from '@/components/LeagueDetail';
export function generateStaticParams() { return [{ slug: 'league' }]; }
export const metadata: Metadata = { title: 'البطولة', description: 'ترتيب البطولة ومبارياتها.' };
export default function Page() { return <LeagueDetail />; }
