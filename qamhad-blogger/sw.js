/*!
 * Qamhad Live — Service Worker (optional PWA offline shell).
 * Host this file at the ROOT of your site (same origin as the template) so its
 * scope covers the whole app. On plain blogspot.com you cannot upload root
 * files; use a custom domain behind Cloudflare (a Worker/route can serve it),
 * or host the template on your own PHP host. Registration fails silently if the
 * file is absent, so the site keeps working either way.
 */
'use strict';

var VERSION = 'qamhad-v1';
var SHELL = ['/', '/?view=home'];

self.addEventListener('install', function (e) {
  self.skipWaiting();
  e.waitUntil(caches.open(VERSION).then(function (c) { return c.addAll(SHELL).catch(function () {}); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);

  // Never cache the live API or streams — always fresh.
  if (/\/(matches|live|match|events|statistics|standings)\.php/.test(url.pathname) ||
      url.pathname.indexOf('/stream') === 0) {
    return;
  }

  // Images (media proxy, fonts, icons): cache-first.
  if (/\.(png|jpe?g|webp|gif|svg|woff2?)$/.test(url.pathname) || url.pathname.indexOf('/media') === 0) {
    e.respondWith(
      caches.open(VERSION).then(function (c) {
        return c.match(req).then(function (hit) {
          return hit || fetch(req).then(function (res) { c.put(req, res.clone()); return res; }).catch(function () { return hit; });
        });
      })
    );
    return;
  }

  // Navigations: network-first, fall back to the cached shell when offline.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(function () { return caches.match('/') || caches.match('/?view=home'); })
    );
  }
});
