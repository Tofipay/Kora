import type { Metadata, Viewport } from 'next';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import BottomNav from '@/components/BottomNav';
import Scripts from '@/components/Scripts';
import { SITE_NAME, SITE_NAME_FULL, SITE_URL, BRAND_PRIMARY } from '@/lib/site';
import { t } from '@/lib/i18n';

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: SITE_NAME_FULL,
    template: `%s — ${SITE_NAME}`,
  },
  description: 'نتائج مباشرة، مركز مباريات متكامل وأخبار كرة القدم لحظة بلحظة',
  applicationName: SITE_NAME,
  manifest: '/manifest.webmanifest',
  alternates: {
    canonical: '/',
    languages: { ar: '/', en: '/en', 'x-default': '/' },
  },
  robots: { index: true, follow: true, 'max-image-preview': 'large', 'max-snippet': -1, 'max-video-preview': -1 } as any,
  icons: {
    icon: [
      { url: '/assets/brand/favicon.svg', type: 'image/svg+xml' },
      { url: '/favicon.ico', sizes: '32x32' },
    ],
    apple: '/assets/brand/icon-192.png',
  },
  openGraph: {
    type: 'website', siteName: SITE_NAME, locale: 'ar_SA', alternateLocale: 'en_US',
    url: SITE_URL, title: SITE_NAME_FULL,
    description: 'نتائج مباشرة، مركز مباريات متكامل وأخبار كرة القدم لحظة بلحظة',
    images: [{ url: '/assets/brand/social-cover.png', width: 1200, height: 630 }],
  },
  twitter: {
    card: 'summary_large_image', title: SITE_NAME_FULL,
    description: 'نتائج مباشرة، مركز مباريات متكامل وأخبار كرة القدم لحظة بلحظة',
    images: ['/assets/brand/social-cover.png'],
  },
  appleWebApp: { capable: true, title: SITE_NAME },
};

export const viewport: Viewport = {
  themeColor: BRAND_PRIMARY,
  colorScheme: 'light dark',
  width: 'device-width',
  initialScale: 1,
  viewportFit: 'cover',
};

const FONTS =
  'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl" data-theme="auto">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        {/* Async font load (never blocks first paint), matching the PHP head. */}
        <link rel="stylesheet" href={FONTS} media="print" data-onload-all="1" />
        <noscript><link rel="stylesheet" href={FONTS} /></noscript>
        <link rel="stylesheet" href="/assets/css/app.css" />
        <link rel="stylesheet" href="/assets/css/next-extra.css" />
      </head>
      <body>
        <a className="skip-link" href="#main">{t('nav.home')}</a>
        <Header />
        <main id="main" className="page">{children}</main>
        <Footer />
        <BottomNav />
        <div id="toast" className="toast" role="status" aria-live="polite" />
        <Scripts />
        {/* Swap the async font stylesheet to all media once loaded. */}
        <script dangerouslySetInnerHTML={{ __html: `document.querySelectorAll('link[data-onload-all]').forEach(function(l){l.media='all'});` }} />
      </body>
    </html>
  );
}
