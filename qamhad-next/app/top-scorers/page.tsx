import type { Metadata } from 'next';
import StandingsView from '@/components/StandingsView';
export const metadata: Metadata = { title: 'ترتيب الهدافين', description: 'قائمة هدافي أبرز الدوريات الأوروبية والعربية محدثة أولاً بأول.', alternates: { canonical: '/top-scorers' } };
export default function Page() { return <StandingsView mode="scorers" />; }
