/**
 * Client-side API layer — the browser talks ONLY to the first-party PHP
 * endpoints under /api (same contract as public/assets/js/api-service.js).
 * The external scores API, its secret headers, the stream proxy and HMAC
 * signing all stay server-side in PHP and are never touched here.
 *
 * Every endpoint returns the envelope { ok, stale, data, ... }; helpers below
 * unwrap `data` and throw on transport errors so callers can show a friendly
 * message and fall back to cached content.
 */
import { API_BASE } from './site';
import { ymd } from './helpers';

export interface Envelope<T = any> {
  ok: boolean; stale?: boolean; lang?: string; count?: number | null;
  data: T; error?: string;
}

const LANG = 'ar'; // Arabic front-end; English (/en) is served by the PHP app.

async function get<T = any>(path: string, init?: RequestInit): Promise<Envelope<T>> {
  const sep = path.indexOf('?') === -1 ? '?' : '&';
  const url = `${API_BASE}${path}${sep}lang=${LANG}`;
  const r = await fetch(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    ...init,
  });
  if (!r.ok && r.status >= 500) throw new Error(`upstream ${r.status}`);
  return (await r.json()) as Envelope<T>;
}

/** Unwrap `data`, returning a fallback on any failure. */
async function data<T>(path: string, fallback: T): Promise<{ data: T; stale: boolean }> {
  try {
    const env = await get<T>(path);
    return { data: (env.data ?? fallback) as T, stale: !!env.stale };
  } catch {
    return { data: fallback, stale: true };
  }
}

/* ---------------- Matches ---------------- */
export const matchesApi = {
  byDate: (date?: string) => data<any[]>(`/matches.php${date ? `?date=${encodeURIComponent(date)}` : ''}`, []),
  today: () => data<any[]>('/matches.php', []),
  live: () => data<any[]>('/live.php', []),
  yesterday: () => { const d = new Date(); d.setDate(d.getDate() - 1); return matchesApi.byDate(ymd(d)); },
  tomorrow: () => { const d = new Date(); d.setDate(d.getDate() + 1); return matchesApi.byDate(ymd(d)); },
  detail: (id: number | string) => data<any>(`/match/${encodeURIComponent(String(id))}`, null),
};

/* ---------------- News ---------------- */
export const newsApi = {
  page: (page = 1) => data<any>(`/news.php?page=${page}`, { items: [], has_next: false }),
  article: (id: number | string) => data<any>(`/news.php?id=${encodeURIComponent(String(id))}`, null),
};

/* ---------------- Videos (front-controller /api/videos) ---------------- */
export const videosApi = {
  page: (page = 1, champ = 'all') =>
    data<any>(`/videos?page=${page}&champ=${encodeURIComponent(champ)}`, { items: [], has_next: false }),
};

/* ---------------- Standings / scorers ---------------- */
export const standingsApi = {
  league: (leagueId: number | string) =>
    data<any>(`/standings.php?league=${encodeURIComponent(String(leagueId))}`, { standings: [], scorers: [] }),
};

/* ---------------- Team / player ---------------- */
export const teamApi = { get: (id: number | string) => data<any>(`/team.php?id=${encodeURIComponent(String(id))}`, null) };
export const playerApi = { get: (id: number | string) => data<any>(`/player.php?id=${encodeURIComponent(String(id))}`, null) };
