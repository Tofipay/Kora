import type { Metadata } from 'next';
import NewsArticle from '@/components/NewsArticle';

/**
 * News article — client-rendered by trailing id. Under static export a single
 * shell is emitted (the placeholder param below); real slugs resolve to it via
 * the deep-link rewrite in public/.htaccess, and the id is read from the URL.
 * NOTE: for full server-side article SEO (per-article title/JSON-LD) keep the
 * PHP /news/{slug} route — see the deployment README.
 */
export function generateStaticParams() {
  return [{ slug: 'article' }];
}

export const metadata: Metadata = {
  title: 'الأخبار',
  description: 'تفاصيل الخبر من قمهد لايف.',
};

export default function Page() {
  return <NewsArticle />;
}
