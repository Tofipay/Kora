/**
 * Site-wide constants. Kept identical to the PHP app's config so canonical
 * URLs, brand strings and asset paths never change.
 */
export const SITE_URL = 'https://www.qamhad.com';
export const SITE_NAME = 'قمهد لايف';
export const SITE_NAME_FULL = 'قمهد لايف — Qamhad Live';
export const BRAND_PRIMARY = '#16C784';
export const TELEGRAM = 'https://t.me/liveqamhad';

/**
 * Base URL of the first-party PHP API. On production it is same-origin
 * (`/api`), so uploading `out/` next to the existing `api/` folder just works.
 * Override for local development with NEXT_PUBLIC_API_BASE=https://www.qamhad.com/api
 */
export const API_BASE =
  process.env.NEXT_PUBLIC_API_BASE?.replace(/\/$/, '') || '/api';

/** Featured leagues (mirrors FAVORITE_LEAGUES in app/config.php). */
export const FAVORITE_LEAGUES: { url_id: number; ar: string; en: string }[] = [
  { url_id: 894789, ar: 'كأس العالم', en: 'World Cup' },
  { url_id: 900326, ar: 'الدوري الإنجليزي', en: 'Premier League' },
  { url_id: 901074, ar: 'الدوري الإسباني', en: 'LaLiga' },
  { url_id: 899984, ar: 'الدوري الإيطالي', en: 'Serie A' },
  { url_id: 899867, ar: 'الدوري الألماني', en: 'Bundesliga' },
  { url_id: 900705, ar: 'الدوري الفرنسي', en: 'Ligue 1' },
  { url_id: 903294, ar: 'دوري روشن السعودي', en: 'Saudi Pro League' },
  { url_id: 900620, ar: 'الدوري البرتغالي', en: 'Primeira Liga' },
  { url_id: 916145, ar: 'الدوري البرازيلي', en: 'Brasileirão' },
];

/** Absolute URL on the canonical domain (percent-encodes Arabic slugs once). */
export function absoluteUrl(path: string): string {
  const p = path.startsWith('/') ? path : `/${path}`;
  return SITE_URL + p.split('/').map(encodeURIComponent).join('/').replace(/%2F/g, '/');
}
