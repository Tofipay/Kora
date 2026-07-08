/* Qamhad Live — service worker
 * static: cache-first · pages: network-first with offline fallback
 * media proxy: stale-while-revalidate
 */
const VERSION = 'q-v5';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const MEDIA_CACHE = VERSION + '-media';
const OFFLINE_URL = '/offline';

const PRECACHE = [
  '/', OFFLINE_URL,
  '/assets/css/app.css?v=5',
  '/assets/js/app.js?v=3',
  '/assets/brand/logo.svg',
  '/assets/brand/logo-dark.svg',
  '/assets/brand/favicon.svg',
  '/assets/brand/icon-192.png',
  '/assets/img/team.svg',
  '/assets/img/league.svg',
  '/assets/img/news.svg',
  '/assets/img/player.svg',
  '/assets/img/placeholder.svg'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(STATIC_CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin')) return;

  // Media proxy → stale-while-revalidate
  if (url.pathname.startsWith('/media/')) {
    e.respondWith(
      caches.open(MEDIA_CACHE).then(async cache => {
        const cached = await cache.match(req);
        const fetched = fetch(req).then(res => {
          if (res.ok) cache.put(req, res.clone());
          return res;
        }).catch(() => cached);
        return cached || fetched;
      })
    );
    return;
  }

  // Static assets → cache-first
  if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.webmanifest' || url.pathname === '/favicon.ico') {
    e.respondWith(
      caches.match(req).then(cached => cached || fetch(req).then(res => {
        if (res.ok) caches.open(STATIC_CACHE).then(c => c.put(req, res.clone()));
        return res;
      }))
    );
    return;
  }

  // HTML pages → network-first, fallback to cache, then offline page
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
