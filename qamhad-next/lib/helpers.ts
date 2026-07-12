/**
 * View helpers — a TypeScript port of the parts of app/helpers.php the UI
 * needs: clean-URL builders, first-party media URLs, team/league name
 * resolution, the live match clock and Arabic date/time formatting. Behaviour
 * matches the PHP one-for-one so links, images and labels are identical.
 */
import { t } from './i18n';

/* ---------------- Types ---------------- */
export type Dict = Record<string, any>;

/* ---------------- Slug / URL builders ---------------- */
export function slugify(text?: string | null, fallback = ''): string {
  let s = (text ?? '').toString().trim();
  if (s === '') return fallback;
  s = s.replace(/[\s_]+/gu, '-').toLowerCase();
  // Keep Arabic letters, latin letters, digits and dashes.
  s = s.replace(/[^؀-ۿa-z0-9-]+/gu, '');
  s = s.replace(/-+/g, '-').replace(/^-|-$/g, '');
  return s === '' ? fallback : s.slice(0, 90);
}

export function matchUrl(m: Dict): string {
  const id = Number(m.match_id ?? m.id ?? 0);
  const home = teamOf(m, 'home').title ?? '';
  const away = teamOf(m, 'away').title ?? '';
  const slug = slugify(`${home}-${away}`);
  return `/match/${slug ? slug + '-' : ''}${id}`;
}

export function leagueUrl(lg: Dict | number, title?: string): string {
  const id = typeof lg === 'number' ? lg : Number(lg.url_id ?? lg.id ?? 0);
  const slug = slugify(title ?? (typeof lg === 'object' ? String(lg.title ?? '') : ''));
  return `/league/${slug ? slug + '-' : ''}${id}`;
}

export function teamUrl(tm: Dict | number, title?: string): string {
  const id = typeof tm === 'number' ? tm : Number(tm.row_id ?? tm.team_id ?? tm.id ?? 0);
  const slug = slugify(title ?? (typeof tm === 'object' ? String(tm.title ?? tm.full_title ?? '') : ''));
  return `/team/${slug ? slug + '-' : ''}${id}`;
}

export function playerUrl(p: Dict | number, title?: string): string {
  const id = typeof p === 'number' ? p : Number(p.row_id ?? p.player_id ?? p.id ?? 0);
  const slug = slugify(title ?? (typeof p === 'object' ? String(p.title ?? p.full_title ?? '') : ''));
  return `/player/${slug ? slug + '-' : ''}${id}`;
}

export function newsUrl(n: Dict): string {
  const id = Number(n.id ?? 0);
  const slug = slugify(String(n.slug ?? n.title ?? ''));
  return `/news/${slug ? slug + '-' : ''}${id}`;
}

/** Extract the trailing numeric id from "some-slug-123". */
export function idFromSlug(slug: string): number {
  const m = /(\d+)$/.exec(slug || '');
  return m ? Number(m[1]) : 0;
}

/* ---------------- First-party media URLs ---------------- */
const MEDIA_KINDS: Record<string, string[]> = {
  teams: ['64', '128'], championship: ['64', '128'], news: ['320', '640'],
  player: ['64', '128'], country: ['64'],
};

export function mediaUrl(kind: string, size: string, file: string | null | undefined, fallback: string): string {
  if (!file) return fallback;
  if (/(^|\/)[a-z0-9_]*default\.(png|jpe?g|gif|webp)$/i.test(file)) return fallback;
  if (/^https?:\/\//i.test(file)) {
    try {
      const u = new URL(file);
      if (u.host.startsWith('imgs.')) {
        const mm = /^\/([a-z]+)\/\d{2,4}\/([A-Za-z0-9._-]+)$/i.exec(u.pathname);
        if (mm && MEDIA_KINDS[mm[1]] && MEDIA_KINDS[mm[1]].includes(size)) {
          return `/media/${mm[1]}/${size}/${mm[2]}`;
        }
        return '/media' + u.pathname;
      }
    } catch { /* fall through */ }
    return file;
  }
  const f = file.replace(/^\/+/, '');
  if (!/^[A-Za-z0-9._-]+$/.test(f)) return fallback;
  return `/media/${kind}/${size}/${f}`;
}

const pick = (o: Dict | string, ...keys: string[]): string | null => {
  if (typeof o === 'string') return o;
  for (const k of keys) if (o && o[k]) return o[k];
  return null;
};

export const teamImg = (t: Dict | string, size = '64') =>
  mediaUrl('teams', size, pick(t, 'image', 'logo'), '/assets/brand/icon.svg');
export const leagueImg = (l: Dict | string, size = '128') =>
  mediaUrl('championship', size, pick(l, 'image'), '/assets/brand/icon.svg');
export const newsImg = (n: Dict | string, size = '640') =>
  mediaUrl('news', size, pick(n, 'image'), '/assets/img/news.svg');
export const playerImg = (p: Dict | string, size = '64') =>
  mediaUrl('player', size, pick(p, 'image'), '/assets/img/player.svg');

/* ---------------- Match helpers ---------------- */
export function teamOf(m: Dict, side: 'home' | 'away'): Dict {
  const t = m[`${side}_team_info`] ?? m[`${side}_team`] ?? {};
  return typeof t === 'object' && t ? t : {};
}

export function teamName(team: Dict | string, def = '—'): string {
  if (typeof team === 'string') return team !== '' ? team : def;
  if (!team || typeof team !== 'object') return def;
  for (const k of ['title', 'full_title', 'name', 'short_title', 'team_name']) {
    if (team[k] && typeof team[k] === 'string') return team[k];
  }
  return def;
}

export interface MatchState {
  key: 'finished' | 'live' | 'upcoming';
  label: string; live: boolean; started: boolean;
  status?: number; clock?: LiveClock | null;
}
export interface LiveClock { label: string; minute: number; phase: string; start: number; base: number; cap: number; }

/** Live match clock, derived from ht_time (period kickoff unix ts). */
export function liveClock(m: Dict): LiveClock {
  const status = Number(m.status ?? 0);
  if (status === 2) return { label: t('status.halftime'), minute: 45, phase: 'ht', start: 0, base: 45, cap: 45 };
  if ([7, 8, 13].includes(status)) return { label: t('match.penalties') || 'ركلات الترجيح', minute: 120, phase: 'pens', start: 0, base: 120, cap: 120 };

  const table: Record<number, [number, number]> = { 1: [0, 45], 3: [45, 90], 5: [90, 105], 6: [105, 120] };
  const [base, cap] = table[status] ?? [0, 45];

  const raw = m.ht_time;
  const minutes = m.minutes;
  const start = (Number.isFinite(Number(raw)) && Number(raw) > 1_000_000_000) ? Number(raw) : 0;
  const now = Math.floor(Date.now() / 1000);

  let minute: number;
  if (start > 0) minute = base + Math.floor(Math.max(0, now - start) / 60) + 1;
  else if (Number(minutes) > 0 && Number(minutes) <= 130) minute = Number(minutes);
  else if (Number(raw) > 0 && Number(raw) <= 130) minute = Number(raw);
  else if (![1, 3, 5, 6].includes(status)) return { label: t('status.live'), minute: 0, phase: 'live', start: 0, base: 0, cap: 45 };
  else minute = base + 1;

  const label = minute > cap ? `${cap}+${minute - cap}′` : `${minute}′`;
  return { label, minute, phase: status === 1 ? '1h' : status === 3 ? '2h' : 'et', start, base, cap };
}

export function matchState(m: Dict): MatchState {
  const status = Number(m.status ?? 0);
  const live = Number(m.live ?? 0);
  if (status === 4) return { key: 'finished', label: t('status.finished'), live: false, started: true, clock: null };
  if (live === 1 || [1, 2, 3, 5, 6, 7, 8, 13].includes(status)) {
    const clock = liveClock(m);
    return { key: 'live', label: clock.label, live: true, started: true, clock, status };
  }
  return { key: 'upcoming', label: formatTime12h(m.match_time ?? ''), live: false, started: false, clock: null };
}

/** data-* attributes the reused app.js uses to tick the clock between polls. */
export function liveClockAttrs(state: MatchState): Record<string, string | number> {
  if (!state.live || !state.clock) return {};
  return { 'data-ls': state.status ?? 0, 'data-lt': state.clock.start };
}

/* ---------------- Time & date (12-hour, Arabic) ---------------- */
export function formatTime12h(time?: string): string {
  const s = (time ?? '').trim();
  if (s === '') return '';
  const parts = s.split(':');
  let h = Number(parts[0] ?? 0);
  const min = (parts[1] ?? '00').padStart(2, '0');
  const pm = h >= 12;
  h = h % 12 || 12;
  return `${String(h).padStart(2, '0')}:${min} ${pm ? t('misc.pm') : t('misc.am')}`;
}

function toTs(value: string | number | undefined): number {
  if (!value) return 0;
  if (typeof value === 'number') return value > 1e11 ? Math.floor(value / 1000) : value;
  const n = Number(value);
  if (Number.isFinite(n) && n > 1_000_000_000) return n;
  const ts = Date.parse(String(value).replace(' ', 'T'));
  return Number.isNaN(ts) ? 0 : Math.floor(ts / 1000);
}

export function formatDateLong(value: string | number | undefined): string {
  const ts = toTs(value);
  if (!ts) return '';
  const d = new Date(ts * 1000);
  return `${t('wd.' + d.getDay())} ${d.getDate()} ${t('mo.' + (d.getMonth() + 1))} ${d.getFullYear()}`;
}

export function ymd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export function formatDateShort(value: string | number | undefined): string {
  const ts = toTs(value);
  return ts ? ymd(new Date(ts * 1000)) : '';
}

export function isoDate(value: string | number | undefined): string {
  const ts = toTs(value) || Math.floor(Date.now() / 1000);
  return new Date(ts * 1000).toISOString();
}

/** Trim to a length on a word boundary, appending an ellipsis. */
export function excerpt(text: string, len = 120): string {
  const s = (text ?? '').replace(/\s+/g, ' ').trim();
  if (s.length <= len) return s;
  return s.slice(0, len).replace(/\s+\S*$/, '') + '…';
}

/** Relative "منذ ٥ دقائق"-style label (Arabic). */
export function timeAgo(value: string | number | undefined): string {
  const ts = toTs(value);
  if (!ts) return '';
  const diff = Math.max(0, Math.floor(Date.now() / 1000) - ts);
  const mins = Math.floor(diff / 60);
  if (mins < 1) return 'الآن';
  if (mins < 60) return `قبل ${mins} ${mins === 1 ? 'دقيقة' : 'دقيقة'}`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `قبل ${hrs} ${hrs === 1 ? 'ساعة' : 'ساعة'}`;
  const days = Math.floor(hrs / 24);
  if (days < 30) return `قبل ${days} ${days === 1 ? 'يوم' : 'يوم'}`;
  return formatDateLong(ts);
}

/** Group a flat match list by championship, preserving first-seen order. */
export function groupByLeague(matches: Dict[]): { league: Dict; matches: Dict[] }[] {
  const groups = new Map<string, { league: Dict; matches: Dict[] }>();
  for (const m of matches) {
    const champ = (m.championship ?? {}) as Dict;
    const key = String(champ.url_id ?? champ.id ?? champ.title ?? 'other');
    if (!groups.has(key)) groups.set(key, { league: champ, matches: [] });
    groups.get(key)!.matches.push(m);
  }
  return Array.from(groups.values());
}
