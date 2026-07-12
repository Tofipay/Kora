import type { Metadata } from 'next';
import VideosList from '@/components/VideosList';

export const metadata: Metadata = {
  title: 'الفيديوهات',
  description: 'ملخصات وأهداف أحدث المباريات، مقسّمة حسب البطولة — تُحدَّث على مدار الساعة.',
  alternates: { canonical: '/videos' },
};

export default function Page() {
  return <VideosList />;
}
