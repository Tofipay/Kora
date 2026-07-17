# ToFi X Tv — Deployment Guide

Requirements: **PHP 8.1+** with `curl`, `mbstring`, `gd` (for WebP), `intl` (optional, better slugs).
No database and no Composer packages required.

---

## 1. Shared hosting (cPanel / Apache) — easiest

1. Upload the whole project to your account (e.g. `~/tofixtv-live/`).
2. **Preferred:** point the domain's document root to `tofixtv-live/public`.
   - Can't change the docroot? Upload the project INTO `public_html` — the
     included root `.htaccess` transparently routes everything into `public/`
     and blocks direct access to `app/`, `storage/`, etc.
3. Make the storage folder writable:
   ```bash
   chmod -R 775 storage storage/cache storage/settings
   chmod -R 775 public/assets/uploads   # created automatically on first logo upload
   ```
4. Visit `https://tofi-xtv.com/` — done. Old `*.php?id=…` URLs now 301 to
   the clean URLs automatically.
5. Open `https://tofi-xtv.com/admin` — default password is in
   `app/config.php` (`ADMIN_DEFAULT_PASSWORD`). **Change it immediately**
   in Admin → Security.
6. In `public/.htaccess`, un-comment the *Force HTTPS* block once SSL is active.

## 2. VPS (Nginx + PHP-FPM)

```bash
sudo mkdir -p /var/www/tofixtv-live
sudo rsync -a ./ /var/www/tofixtv-live/
sudo chown -R www-data:www-data /var/www/tofixtv-live/storage /var/www/tofixtv-live/public/assets
sudo cp /var/www/tofixtv-live/deploy/nginx.conf /etc/nginx/sites-available/tofixtv-live
sudo ln -s /etc/nginx/sites-available/tofixtv-live /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Adjust the `fastcgi_pass` socket to your PHP-FPM version, then issue SSL:
`sudo certbot --nginx -d tofi-xtv.com`.

## 3. Local development

```bash
php -S 0.0.0.0:8080 -t public deploy/dev-router.php
```

## 4. Cron jobs

```cron
# Push notifications for match events (start/goal/HT/FT) — requires the FCM
# Service Account JSON uploaded in Admin → Notifications (HTTP v1 API)
* * * * *  php /path/to/tofixtv-live/deploy/notify-worker.php >/dev/null 2>&1

# Optional: pre-warm today's fixtures cache
*/5 * * * *  curl -s https://tofi-xtv.com/api/live-scores >/dev/null
```

## 5. Firebase Cloud Messaging setup (HTTP v1)

1. Create a project at console.firebase.google.com → add a **Web app**.
2. Copy the web config (apiKey, authDomain, projectId, messagingSenderId, appId)
   into **Admin → Notifications → step 2** and save. These are public and ship
   to the browser for `getToken()`.
3. Project settings → Cloud Messaging → **Web Push certificates** → generate a
   key pair → paste as *VAPID key*.
4. Project settings → **Service accounts** → *Generate new private key* →
   upload the downloaded JSON in **Admin → Notifications → step 1**. It is
   stored server-side only (`storage/settings/service-account.json`, chmod 600,
   outside the web root) and is **never** exposed to the frontend. The panel
   and cron mint a short-lived OAuth2 Bearer token from it and send through
   `https://fcm.googleapis.com/v1/projects/{projectId}/messages:send`.
5. Users press **“تفعيل الإشعارات”** on the site; tokens are stored via
   `/api/push-subscribe` and every broadcast is delivered per-token over HTTP v1
   (stale/unregistered tokens are pruned automatically).

> The legacy **Server key** and `fcm/send` endpoint are no longer used. Rotate
> any old server key you previously exposed.

## 6. CDN (optional but recommended)

Everything is CDN-ready out of the box:

- `/assets/*` → `Cache-Control: public, max-age=31536000, immutable`
- `/media/*`  → `Cache-Control: public, max-age=604800, s-maxage=2592000, immutable` + `Vary: Accept`

Put Cloudflare (or any CDN) in front and cache those paths at the edge.
`s-maxage` lets the edge hold media for 30 days while browsers revalidate weekly.

## 7. Go-live SEO checklist

- [ ] `public/robots.txt` — confirm the sitemap host matches your domain.
- [ ] Admin → SEO — set titles/descriptions, GSC verification token, GA4 id.
- [ ] Search Console: submit `/sitemap.xml` and `/sitemap-news.xml`.
- [ ] Verify a legacy URL 301s: `curl -I https://tofi-xtv.com/news.php?id=15`.
- [ ] Run PageSpeed Insights — server-rendered pages + WebP + immutable caching
      target 95+ desktop / 90+ mobile.

## 8. Updating

The app is stateless outside `storage/` and `public/assets/uploads/`.
To update: rsync new code, keep those two directories. Cache clears itself by
TTL, or use Admin → Cache Manager.
