<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * ALOKA Live — site access mode ("app only" vs "app + browser").
 * ---------------------------------------------------------------
 * Admin → "وضع التطبيق" chooses one of:
 *   - 'both' : the site works normally in the browser AND inside the app.
 *   - 'app'  : the site works ONLY inside the official Android app
 *              (User-Agent com.aloka.live.app). Every browser visitor instead
 *              sees a professional "download the app" landing page whose button
 *              downloads aloka-live.apk. The app WebView keeps working normally.
 *
 * The admin panel, the APK download and static assets are always reachable in
 * the browser so the owner can manage the site and users can get the app.
 */
final class AppMode
{
    /** Where the distributable APK lives (outside the web root; served via PHP). */
    public static function apkPath(): string
    {
        return STORAGE_DIR . '/app/aloka-live.apk';
    }

    public static function apkExists(): bool
    {
        return is_file(self::apkPath());
    }

    /** 'both' (default) | 'app' */
    public static function mode(): string
    {
        $s = Settings::get('access', []);
        $m = is_array($s) ? (string)($s['mode'] ?? 'both') : 'both';
        return $m === 'app' ? 'app' : 'both';
    }

    public static function setMode(string $mode): void
    {
        Settings::merge('access', ['mode' => $mode === 'app' ? 'app' : 'both', 'updated_at' => date('c')]);
    }

    /**
     * Enforce the access mode for the current request.
     * @param string $context 'web' | 'api' | 'admin' | 'download' | 'asset'
     */
    public static function gate(string $context): void
    {
        if (self::mode() !== 'app') return;                 // 'both' → nothing to do
        if (function_exists('is_tofix_app') && is_tofix_app()) return; // inside the app → normal

        // Always reachable from a browser even in app-only mode:
        if ($context === 'admin' || $context === 'download' || $context === 'asset') return;

        if ($context === 'api') {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
            }
            echo json_encode([
                'ok'      => false,
                'error'   => 'app_only',
                'message' => 'هذا المحتوى متاح داخل تطبيق ALOKA Live فقط.',
                'download'=> '/download/app',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        self::renderLanding();
    }

    /** Stream the APK to the browser, or fall back to Telegram if not uploaded. */
    public static function serveApk(): void
    {
        $p = self::apkPath();
        if (!is_file($p)) {
            // The vendor hasn't uploaded the APK yet — keep the button working.
            if (!headers_sent()) header('Location: https://t.me/alokalive', true, 302);
            exit;
        }
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="aloka-live.apk"');
            header('Content-Length: ' . (string)filesize($p));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: public, max-age=300');
        }
        @set_time_limit(0);
        readfile($p);
        exit;
    }

    /** Self-contained, ALOKA-themed "download the app" landing page. */
    public static function renderLanding(): void
    {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, must-revalidate');
        }
        $dl   = '/download/app';
        $tg   = 'https://t.me/alokalive';
        $name = defined('SITE_NAME_AR') ? SITE_NAME_AR : 'ALOKA Live';
        echo <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>حمّل تطبيق {$name}</title>
<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:26px;font-family:'Cairo','Segoe UI',Tahoma,system-ui,-apple-system,sans-serif;
    background:radial-gradient(1200px 680px at 50% -12%,#2A0A83 0%,#1B0761 46%,#10033D 100%);
    color:#EDE9FF;text-align:center}
  .wrap{width:min(560px,100%)}
  .logo{width:112px;height:112px;margin:0 auto 26px;display:grid;place-items:center;border-radius:28px;
    background:linear-gradient(150deg,#5B12E7,#340996 55%,#1B0761);
    box-shadow:0 18px 48px rgba(76,14,205,.55)}
  .logo svg{width:68px;height:68px}
  h1{font-size:26px;font-weight:800;margin-bottom:12px;line-height:1.4}
  h1 span{background:linear-gradient(90deg,#B39DFF,#7C4DFF);-webkit-background-clip:text;background-clip:text;color:transparent}
  p.lead{color:#C6B8FF;font-size:15.5px;line-height:1.95;margin-bottom:26px}
  .features{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:30px}
  .chip{display:inline-flex;align-items:center;gap:7px;background:rgba(124,77,255,.14);
    border:1px solid rgba(124,77,255,.28);color:#D9CCFF;border-radius:999px;padding:8px 14px;font-size:13px;font-weight:700}
  .chip svg{width:15px;height:15px;fill:none;stroke:#B39DFF;stroke-width:2}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:11px;width:100%;
    padding:17px 22px;border-radius:16px;text-decoration:none;font-size:17px;font-weight:800;color:#fff;
    background:linear-gradient(135deg,#7C4DFF,#4C0ECD);box-shadow:0 16px 38px rgba(76,14,205,.5);
    transition:transform .15s ease,box-shadow .15s ease}
  .btn:hover{transform:translateY(-2px);box-shadow:0 22px 48px rgba(76,14,205,.62)}
  .btn svg{width:26px;height:26px;fill:currentColor}
  .btn.tg{margin-top:14px;background:linear-gradient(135deg,#2AABEE,#229ED9);box-shadow:0 12px 30px rgba(34,158,217,.4)}
  .apk{margin-top:16px;color:#9A86D8;font-size:12.5px;direction:ltr}
  .foot{margin-top:30px;color:#9A86D8;font-size:12px}
</style>
</head>
<body>
  <main class="wrap">
    <div class="logo" aria-hidden="true">
      <svg viewBox="0 0 1024 1024" role="img" aria-label="{$name}">
        <path fill="#FFFFFF" fill-rule="evenodd" d="M512 96c-45 0-81 28-100 72L118 866c-13 31 10 62 43 62h143c19 0 36-11 43-29l71-179h188l71 179c7 18 24 29 43 29h143c33 0 56-31 43-62L612 168c-19-44-55-72-100-72Zm-67 296c0-21 23-34 41-23l165 105c17 11 17 37 0 48L486 627c-18 11-41-2-41-23V392Zm10 328h114l-57 145-57-145Z"/>
      </svg>
    </div>
    <h1>حمّل تطبيق <span>{$name}</span></h1>
    <p class="lead">لمشاهدة البث المباشر، القنوات الرياضية، مباريات اليوم، الأفلام، المسلسلات والأخبار —
       حمّل التطبيق الرسمي واستمتع بتجربة أسرع وأكثر استقراراً.</p>
    <div class="features">
      <span class="chip"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> بث مباشر عالي الجودة</span>
      <span class="chip"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> بدون تقطيع</span>
      <span class="chip"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> أفلام ومسلسلات</span>
      <span class="chip"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> تحديثات مستمرة</span>
    </div>
    <a class="btn" href="{$dl}" download="aloka-live.apk" rel="nofollow">
      <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>
      تحميل التطبيق للأندرويد
    </a>
    <div class="apk">aloka-live.apk</div>
    <a class="btn tg" href="{$tg}" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.2 13.4l-4.8-1.5c-1-.3-1.1-1 .2-1.5l18.7-7.2c.9-.3 1.7.2 1.4 1.1z"/></svg>
      قناة تيليجرام
    </a>
    <div class="foot">{$name}</div>
  </main>
</body>
</html>
HTML;
        exit;
    }
}
