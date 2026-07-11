# Qamhad Live — Blogger XML Template + PHP API Server

A production‑ready **Blogger** football platform (live scores, match center, standings,
scorers, teams, players, news, videos and TV channels) that talks to **one backend only**:

```
https://api.qamhad.com/
```

This is a **1:1 conversion** of the original PHP site — not a re‑skin. The Blogger theme is a
native XML **shell** (header, nav, footer, router). Each page's body is the project's *own*
markup — the exact same views, cards, tables, timelines, formations and `app.css` — rendered
by the API server (`render.php`) and composed into the shell with instant, History‑API
navigation. Clicks open pages normally (no long‑press, no preview); media loads from
`api.qamhad.com`; the upstream data sources stay hidden server‑side.

```
Browser (Blogger shell)                api.qamhad.com
  click /match/real-madrid-123  ──►  render.php  ──►  original views + app.css
        ▲                                │                     │
        └──── <main> fragment + SEO ◄────┘   ◄── upstream data (hidden, cached)
  (History API — instant, no reload)
```

The theme loads the real design system once from `https://api.qamhad.com/assets/app.css`,
so colours, spacing, typography and every component are identical to the PHP site. JSON data
endpoints (`/matches.php`, `/live.php`, …) remain available for any custom widgets.

---

## What's in this package

```
qamhad-blogger/
├─ template.xml          ← upload to Blogger (Theme → Edit HTML)
├─ sw.js                 ← optional PWA service worker (host at site root)
├─ README.md             ← this guide
└─ api-server/           ← upload the CONTENTS to https://api.qamhad.com/
   ├─ index.php  router.php  proxy.php   (front controller + dispatcher)
   ├─ config.php  _bootstrap.php  helpers.php  cache.php
   ├─ matches.php match.php live.php events.php lineups.php formations.php
   ├─ statistics.php standings.php topscorers.php league.php team.php player.php
   ├─ news.php news-details.php search.php videos.php channels.php channel.php
   ├─ comments.php settings.php media.php stream.php
   ├─ render.php         ← renders the real pages as <main> fragments (the theme)
   ├─ sitemap.php robots.php health.php status.php
   ├─ .htaccess
   ├─ assets/            (app.css — the REAL design system — + logos, placeholders)
   ├─ engine/            (the original app: Core + Controllers + Views + config)
   └─ storage/           (writable: cache + settings + rate‑limit state)
```

**Requirements:** PHP **8.1+** (built/tested on 8.3), with `curl`, `mbstring`, `json`
and (optional) `gd` for WebP image conversion. No database. No framework.

---

## 1) Upload the API server to `api.qamhad.com`

1. Point the sub‑domain `api.qamhad.com` at a PHP host (its document root = the
   **contents** of `api-server/`, so `https://api.qamhad.com/matches.php` works).
2. Upload everything inside `api-server/` (including `.htaccess`, `engine/`,
   `storage/`, `assets/`).
3. Make `storage/` writable by PHP:
   ```bash
   chmod -R 775 storage
   ```
4. Test it:
   ```
   https://api.qamhad.com/health.php     → {"ok":true,...}
   https://api.qamhad.com/status.php     → cache + upstream probe
   https://api.qamhad.com/matches.php    → today's matches (JSON)
   https://api.qamhad.com/settings.php   → front‑end config the template reads
   ```

Every endpoint also works through the single dispatcher and pretty URLs:

```
/proxy.php?endpoint=match&id=123
/matches            (pretty, needs the bundled .htaccess or nginx rules below)
/media/teams/64/x.png
```

## 2) Upload the template to Blogger

1. Blogger dashboard → **Theme** → ⋮ → **Edit HTML**.
2. Delete everything and paste the full contents of **`template.xml`**.
3. **Save**. Open your blog — the app loads and fetches data from the API.

> The template ships pointing at `https://api.qamhad.com`. To use a different host,
> edit the one line near the top of `template.xml`:
> ```html
> <meta content='https://api.qamhad.com' name='qamhad:api'/>
> ```

## 3) Configure the API URL (already done)

There is nothing else to wire: the template reads `qamhad:api`, then calls
`/settings.php` for **all** branding, colours, menus, social links, ads and analytics.
Change JSON on the server — never the XML — after install.

## 4) Configure cache

Caching is automatic (disk cache with stale‑fallback). TTLs live in
`api-server/engine/config.php`:

| Data          | TTL      |
|---------------|----------|
| Live matches  | 60 s     |
| Day fixtures  | 10 min   |
| News          | 15 min   |
| Leagues/tables| 60 min   |
| Media images  | 7 days   |

Inspect / clear:
```
/cache.php                          → stats
/cache.php?action=flush&key=KEY     → clear API cache
/cache.php?action=flush-media&key=KEY
/cache.php?action=warm&key=KEY      → pre‑warm today's feeds
```
`KEY` = env `QAMHAD_CACHE_KEY` (defaults to the public API key).

## 5) Configure Cron (optional, keeps the site instant)

Warm the cache every few minutes so visitors never wait on an upstream call:
```cron
*/5 * * * * curl -s "https://api.qamhad.com/cache.php?action=warm&key=YOUR_CACHE_KEY" >/dev/null
```

## 6) Configure Cloudflare (recommended)

* Proxy `api.qamhad.com` through Cloudflare (orange cloud).
* SSL/TLS mode: **Full (strict)**.
* Enable **Brotli**, **HTTP/2** and **HTTP/3 (QUIC)** under *Speed → Optimization*.
* Cache rule: cache `*.php` responses respecting origin `Cache-Control` (the API sends
  correct `max-age`/`s-maxage`/`ETag`), and **bypass cache** for `/stream*`.
* The API reads `CF-Connecting-IP` for correct rate‑limiting behind Cloudflare.

## 7) Configure HTTPS

The bundled `.htaccess` forces HTTPS (Cloudflare‑aware via `X-Forwarded-Proto`).
On nginx use the snippet below. Always serve both the blog and API over HTTPS.

## 8) Configure `robots.txt` + Sitemaps

The API generates them dynamically:
```
https://api.qamhad.com/robots.php      → robots.txt (points to the sitemap)
https://api.qamhad.com/sitemap.php     → sitemap index
   ?type=main | news | video | image
```
Submit `https://api.qamhad.com/sitemap.php` in Google Search Console. Sitemap URLs
point at your Blogger site using canonical deep links (`?view=…&id=…`) that the
template routes to real pages.

## 9) Configure FCM / Push (optional)

Set `fcm_enabled` and `vapid_key` in `storage/settings/analytics_config.json`.
The template exposes them to the front‑end; wire your Firebase project's messaging SW
if you want web push. (Push requires hosting a service worker at the site root — see PWA below.)

## 10) Configure Google Search Console / Bing / Yandex / Facebook

Add verification tokens in `api-server/config.php` → `API_VERIFY`:
```php
const API_VERIFY = [
    'google'   => 'xxxxxxxx',
    'bing'     => 'xxxxxxxx',
    'yandex'   => 'xxxxxxxx',
    'facebook' => 'xxxxxxxx',
];
```
The template injects the matching `<meta>` verification tags automatically.

## 11) Configure AdSense

Edit `storage/settings/analytics_config.json`:
```json
{
  "adsense_client": "ca-pub-XXXXXXXXXXXXXXXX",
  "auto_ads": true,
  "slot_header": "1234567890",
  "slot_infeed": "1234567891",
  "slot_article": "1234567892"
}
```
The template loads AdSense (Auto Ads and/or manual slots) with lazy, CLS‑safe placements.
Also set GA4 / GTM here (`"ga4"`, `"gtm"`).

---

## PWA / Offline

`template.xml` registers a service worker at `/sw.js` and installs a web‑app manifest.
Full offline needs `sw.js` served from your **site root** (same origin as the pages):

* **Custom domain + Cloudflare:** add a Worker/route that returns the bundled `sw.js`
  for `/sw.js`, or host the template on your own PHP server and drop `sw.js` at the root.
* **Plain `*.blogspot.com`:** root files can't be uploaded, so offline caching is skipped —
  everything else (installable manifest, theme colour, fast loading) still works.

---

## nginx (if you don't use Apache/.htaccess)

```nginx
server {
    server_name api.qamhad.com;
    root /var/www/qamhad-api;      # = contents of api-server/
    index index.php;

    location ~* ^/(engine|storage)/ { deny all; }

    # pretty URLs → front controller
    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    gzip on;
    gzip_types application/json application/xml text/plain application/javascript image/svg+xml;
}
```

---

## Endpoint reference

| Endpoint | Params | Returns |
|---|---|---|
| `render.php` | `path`, `lang` | **the theme's engine** — a page's `<main>` fragment + SEO (title, description, JSON‑LD) as JSON |
| `settings.php` | `lang` | front‑end config (brand, menu, ads, analytics) |
| `matches.php` | `date=YYYY-MM-DD`, `lang` | matches for a day |
| `live.php` | `lang` | matches in play now |
| `match.php` | `id`, `lang` | full match center (info, events, lineups, stats, channels) |
| `events.php` / `lineups.php` / `formations.php` / `statistics.php` | `id`, `lang` | per‑match parts |
| `standings.php` | `league`, `lang` | table + scorers |
| `topscorers.php` | `league`, `type=goals\|assists` | ranking |
| `league.php` | `id` (omit for list) | league hub / active leagues |
| `team.php` | `id`, `lang` | fixtures, results, squad, news |
| `player.php` | `id`, `slug`, `lang` | vitals + stats + transfers |
| `news.php` | `page`, `lang` | news list + featured hub |
| `news-details.php` | `id`, `lang` | one article |
| `search.php` | `q`, `lang` | players + teams |
| `videos.php` | `champ`, `skip`, `lang` | highlights feed + categories |
| `channels.php` / `channel.php` | `slug`/`name`/`id` | TV channels + playable sources |
| `comments.php` | `path` (GET) / JSON (POST) | first‑party comments |
| `media.php` | `p=kind/size/file` | image proxy (WebP, ETag) |
| `stream.php` | signed `url,h,sig` | HLS proxy (no open‑proxy) |
| `sitemap.php` / `robots.php` | `type` | SEO |
| `health.php` / `status.php` | — | monitoring |
| `proxy.php` | `endpoint=<name>&…` | single‑entry dispatcher |

Every JSON endpoint returns:
```json
{ "ok": true, "stale": false, "lang": "ar", "count": 12, "ts": 1730000000, "data": … }
```

---

## Environment variables (all optional)

| Var | Default | Purpose |
|---|---|---|
| `QAMHAD_API_URL` | `https://api.qamhad.com` | canonical API host |
| `QAMHAD_BLOG_URL` | `https://www.qamhad.com` | public Blogger site (sitemaps) |
| `QAMHAD_ALLOWED_ORIGINS` | built‑in list | comma‑separated CORS allow‑list |
| `QAMHAD_API_KEY` | `qamhad-public-2026` | public API key |
| `QAMHAD_REQUIRE_KEY` | `0` | set `1` to enforce the key |
| `QAMHAD_RATE_LIMIT` / `QAMHAD_RATE_WINDOW` | `120` / `60` | rate limiting |
| `QAMHAD_CACHE_KEY` | = API key | admin key for `cache.php` |

---

## Security notes

* Upstream provider hosts are **never** printed to the browser — only `api.qamhad.com`.
* Input is validated per endpoint; SSRF‑guarded stream proxy with HMAC‑signed URLs.
* `engine/` and `storage/` are denied by `.htaccess` / nginx rules.
* Comments reject HTML/links and are stored HTML‑escaped.
* CORS is allow‑listed; rate limiting is per‑IP (Cloudflare‑aware).
