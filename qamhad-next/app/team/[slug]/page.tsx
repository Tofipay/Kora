import type { Metadata } from 'next';
import TeamDetail from '@/components/TeamDetail';
export function generateStaticParams() { return [{ slug: 'team' }]; }
export const metadata: Metadata = { title: 'الفريق', description: 'مباريات الفريق وتشكيلته.' };
export default function Page() { return <TeamDetail />; }
