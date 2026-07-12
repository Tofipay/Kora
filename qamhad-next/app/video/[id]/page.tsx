import type { Metadata } from 'next';
import VideoPlay from '@/components/VideoPlay';

/**
 * In-site video player — client-rendered by trailing id. Single shell under
 * static export; real ids resolve via the deep-link rewrite in public/.htaccess.
 * NOTE: keep the PHP /video/{id} route for full VideoObject SEO (see README).
 */
export function generateStaticParams() {
  return [{ id: 'watch' }];
}

export const metadata: Metadata = {
  title: 'الفيديوهات',
  description: 'شاهد ملخص المباراة داخل الموقع.',
};

export default function Page() {
  return <VideoPlay />;
}
