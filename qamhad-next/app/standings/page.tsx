import type { Metadata } from 'next';
import StandingsView from '@/components/StandingsView';
export const metadata: Metadata = { title: 'جدول الترتيب', description: 'ترتيب أبرز الدوريات: الإنجليزي، الإسباني، الإيطالي، الألماني، الفرنسي والسعودي.', alternates: { canonical: '/standings' } };
export default function Page() { return <StandingsView mode="standings" />; }
