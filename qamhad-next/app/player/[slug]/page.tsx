import type { Metadata } from 'next';
import PlayerDetail from '@/components/PlayerDetail';
export function generateStaticParams() { return [{ slug: 'player' }]; }
export const metadata: Metadata = { title: 'اللاعب', description: 'ملف اللاعب وإحصاءاته.' };
export default function Page() { return <PlayerDetail />; }
