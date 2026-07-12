import type { Metadata } from 'next';
import NewsList from '@/components/NewsList';

export const metadata: Metadata = {
  title: 'أخبار كرة القدم',
  description: 'آخر أخبار كرة القدم العربية والعالمية، انتقالات ومباريات لحظة بلحظة.',
  alternates: { canonical: '/news' },
};

export default function Page() {
  return <NewsList />;
}
