/* Qamhad Live — service worker
 * ---------------------------------------------------------------
 * Strategy:
 *   HTML pages         → network-first (never serve a stale page) → offline
 *   /assets/* (CSS/JS) → cache-first, but URLs are filemtime-versioned so a
 *                        new release is a new URL = automatic refresh
 *   /media/*           → stale-while-revalidate (fast images, fresh in bg)
 *   /api/*, /admin     → always network (bypass cache)
 *
 * Auto-update: the SW is registered as /sw.js?v={build}. When the site build
 * changes, the browser sees a byte-different SW, installs it, and the page is
 * notified so it can show an "update available" prompt. Clicking "update now"
 * posts SKIP_WAITING → the new SW activates and the page reloads. Users never
 * need to clear their browser cache.
 * --------------------------------------------------------------- */

// The build token is passed in the registration URL (?v=...); it changes on
// every release / "تحديث الموقع" click so each release is its own cache scope.
const BUILD = new URL(self.location).searchParams.get('v') || 'base';
const STATIC_CACHE = 'q-' + BUILD + '-static';
const PAGE_CACHE   = 'q-' + BUILD + '-pages';
const MEDIA_CACHE  = 'q-' + BUILD + '-media';
const OFFLINE_URL  = '/offline';

// Only version-stable brand assets are precached. CSS/JS are intentionally
// NOT precached here — they carry filemtime query strings and are cached on
// first use, so we never pin an outdated ?v= build.
const PRECACHE = [
  OFFLINE_URL,
  '/assets/brand/logo.svg',
  '/assets/brand/logo-dark.svg',
  '/assets/brand/favicon.svg',
  '/assets/brand/icon.svg',
  '/assets/brand/icon-192.png',
  '/assets/img/team.svg',
  '/assets/img/league.svg',
  '/assets/img/news.svg',
  '/assets/img/player.svg',
  '/assets/img/placeholder.svg'
];

self.addEventListener('install', e => {
  // Do NOT skipWaiting automatically — wait for the user's "update now" so we
  // never reload the page out from under them mid-interaction.
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then(c => c.addAll(PRECACHE.map(u => new Request(u, { cache: 'reload' }))))
      .catch(() => {})
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => !k.includes(BUILD)).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// The page can ask the waiting SW to take over immediately.
self.addEventListener('message', e => {
  if (e.data && e.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin')) return;

  // Media proxy → stale-while-revalidate
  if (url.pathname.startsWith('/media/')) {
    e.respondWith(caches.open(MEDIA_CACHE).then(async cache => {
      const cached = await cache.match(req);
      const fetched = fetch(req).then(res => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => cached);
      return cached || fetched;
    }));
    return;
  }

  // Static assets (filemtime-versioned) → cache-first
  if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest' || url.pathname === '/favicon.ico') {
    e.respondWith(caches.match(req).then(cached => cached || fetch(req).then(res => {
      if (res.ok) caches.open(STATIC_CACHE).then(c => c.put(req, res.clone()));
      return res;
    })));
    return;
  }

  // HTML pages → network-first, fallback to cache, then the offline page
  if (req.headers.get('accept') && req.headers.get('accept').includes('text/html')) {
    e.respondWith(
      fetch(req).then(res => {
        if (res.ok) caches.open(PAGE_CACHE).then(c => c.put(req, res.clone()));
        return res;
      }).catch(async () => (await caches.match(req)) || caches.match(OFFLINE_URL))
    );
  }
});

/* Push (FCM data or webpush payloads) */
self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch (err) { /* plain text */ }
  const n = data.notification || data;
  e.waitUntil(self.registration.showNotification(n.title || 'قمهد لايف', {
    body: n.body || '',
    icon: n.icon || '/assets/brand/icon-192.png',
    badge: '/assets/brand/icon-192.png',
    dir: 'rtl',
    data: { url: (data.data && data.data.url) || n.url || '/' }
  }));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
    return clients.openWindow(url);
  }));
});
