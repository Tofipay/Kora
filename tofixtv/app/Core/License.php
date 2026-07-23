<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * ALOKA Live — remote license / activation gate.
 * ---------------------------------------------------------------
 * A one-time activation code is entered ONCE in the admin panel. The code and
 * the current domain are verified against a remote JSON source (a Blogspot page
 * that embeds <script id="aloka-license-data" type="application/json">…</script>).
 * On success the activation is stored server-side (storage/settings/license.json,
 * outside the web root and denied by storage/.htaccess) and the activation UI
 * disappears for good.
 *
 * A lightweight background re-check runs at most once per interval (10 min while
 * active, 2 min while locked) using the LOCAL cached decision the rest of the
 * time — so normal visits never trigger an external request and the site stays
 * fast. If the license is removed, set to "disabled", or the domain no longer
 * matches, the ENTIRE site (pages, admin, player, APIs) is blocked and only a
 * professional deactivation page is shown. Restoring the status to "active"
 * brings the site back automatically on the next check.
 *
 * The activation code is NEVER emitted to HTML/JS and NEVER stored in the
 * browser — every check happens in PHP.
 */
final class License
{
    /** Remote license source. Overridable via env (vendor may self-host). */
    private const REMOTE_URL_DEFAULT = 'https://admin-cod.blogspot.com/p/admin-cod.html';
    /** The ONLY element read from the remote page. */
    private const ELEMENT_ID   = 'aloka-license-data';

    /** Support / re-activation contact shown on the deactivation page. */
    public  const CONTACT_URL  = 'https://t.me/Abu5Turkish';
    public  const CONTACT_TEXT = 't.me/Abu5Turkish';

    /** Re-check cadence (seconds): slower while healthy, faster while locked. */
    private const INTERVAL_ACTIVE = 600;             // 10 minutes
    private const INTERVAL_LOCKED = 120;             // 2 minutes
    /**
     * Max time the site may keep running on the LOCAL cache without a fresh
     * remote confirmation. After this window a successful remote check is
     * mandatory — so blocking/removing the license host cannot keep a site
     * alive indefinitely. Also bounds any local-cache tampering.
     */
    private const GRACE_SECONDS   = 12 * 3600;       // 12 hours

    private static ?array $data = null;

    private static function remoteUrl(): string
    {
        $env = getenv('ALOKA_LICENSE_URL');
        return ($env && $env !== '') ? $env : self::REMOTE_URL_DEFAULT;
    }

    /* ---------------- local state (storage/settings/license.json) ---------------- */

    private static function load(): array
    {
        if (self::$data === null) {
            $d = Settings::get('license', []);
            self::$data = is_array($d) ? $d : [];
        }
        return self::$data;
    }

    private static function store(array $d): void
    {
        self::$data = $d;
        Settings::set('license', $d);
    }

    public static function isActivated(): bool
    {
        return !empty(self::load()['activated']);
    }

    /** 'active' | 'locked' | 'unactivated' */
    public static function state(): string
    {
        $d = self::load();
        if (empty($d['activated'])) return 'unactivated';
        if (($d['state'] ?? 'active') !== 'active') return 'locked';
        // Defense in depth: never report "active" off a cache that was never
        // remotely confirmed, is confirmed in the (impossible) future, or is
        // older than the grace window — the remote must confirm within GRACE.
        $now    = time();
        $lastOk = (int)($d['last_ok'] ?? 0);
        if ($lastOk <= 0 || $lastOk > $now || ($now - $lastOk) >= self::GRACE_SECONDS) {
            return 'locked';
        }
        return 'active';
    }

    /* ---------------- domain helpers ---------------- */

    /** Current request host, normalized (lowercase, no port, no leading www). */
    public static function currentDomain(): string
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') $host = strtolower((string)PRIMARY_HOST);
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        return trim($host);
    }

    private static function normalizeDomain(string $d): string
    {
        $d = strtolower(trim($d));
        $d = preg_replace('#^https?://#', '', $d) ?? $d;
        $d = preg_replace('#/.*$#', '', $d) ?? $d;   // drop any path
        $d = preg_replace('/:\d+$/', '', $d) ?? $d;  // drop port
        $d = preg_replace('/^www\./', '', $d) ?? $d; // drop www.
        return trim($d);
    }

    /** True when the licensed domain equals, or is the apex of, the current host. */
    private static function domainMatch(string $licensed, string $current): bool
    {
        $licensed = self::normalizeDomain($licensed);
        $current  = self::normalizeDomain($current);
        if ($licensed === '' || $current === '') return false;
        if ($licensed === $current) return true;
        return str_ends_with($current, '.' . $licensed); // allow subdomains of the licensed apex
    }

    /* ---------------- remote fetch + parse ---------------- */

    /**
     * Fetch the remote page and return the parsed licenses, or null on any
     * network / parse failure (caller keeps the last known decision).
     * @return array<int,array{code:string,domain:string,status:string}>|null
     */
    private static function fetchRemote(): ?array
    {
        try {
            $html = self::httpGet(self::remoteUrl());
        } catch (\Throwable $e) {
            return null;
        }
        if ($html === null) return null;

        // Read ONLY the JSON inside <script id="aloka-license-data" …>…</script>.
        $pat = '#<script[^>]*id=["\']' . preg_quote(self::ELEMENT_ID, '#') . '["\'][^>]*>(.*?)</script>#is';
        if (!preg_match($pat, $html, $m)) return null;

        $json = trim($m[1]);
        // Blogspot HTML-escapes quotes and may inject <br>/&nbsp; — undo that.
        $json = html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $json = preg_replace('#<br\s*/?>#i', '', $json) ?? $json;
        $json = str_replace("\xC2\xA0", ' ', $json); // non-breaking space

        $parsed = json_decode($json, true);
        if (!is_array($parsed) || !isset($parsed['licenses']) || !is_array($parsed['licenses'])) {
            return null;
        }
        $out = [];
        foreach ($parsed['licenses'] as $l) {
            if (!is_array($l)) continue;
            $out[] = [
                'code'   => (string)($l['code'] ?? ''),
                'domain' => (string)($l['domain'] ?? ''),
                'status' => strtolower(trim((string)($l['status'] ?? ''))),
            ];
        }
        return $out;
    }

    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'ALOKA-License/1.0',
                CURLOPT_HTTPHEADER     => ['Accept: text/html'],
            ]);
            $res  = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return (is_string($res) && $res !== '' && $code >= 200 && $code < 400) ? $res : null;
        }
        $ctx = stream_context_create([
            'http' => ['timeout' => 5, 'header' => "Accept: text/html\r\nUser-Agent: ALOKA-License/1.0\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return (is_string($res) && $res !== '') ? $res : null;
    }

    /**
     * Decide whether a (code, domain) pair is authorized against a license list.
     * @param array<int,array{code:string,domain:string,status:string}> $licenses
     * @return array{ok:bool,reason:string} reason ∈ active|disabled|domain_mismatch|not_found
     */
    private static function evaluate(array $licenses, string $code, string $domain): array
    {
        $codeFound = false;
        $activeCodeExists = false;
        foreach ($licenses as $l) {
            if ($l['code'] === '' || !hash_equals($l['code'], $code)) continue;
            $codeFound = true;
            if ($l['status'] !== 'active') continue;      // disabled record for this code
            $activeCodeExists = true;
            if (self::domainMatch($l['domain'], $domain)) {
                return ['ok' => true, 'reason' => 'active'];
            }
        }
        if ($activeCodeExists) return ['ok' => false, 'reason' => 'domain_mismatch'];
        if ($codeFound)        return ['ok' => false, 'reason' => 'disabled'];
        return ['ok' => false, 'reason' => 'not_found'];
    }

    /* ---------------- one-time activation (admin) ---------------- */

    /**
     * Verify a freshly entered code against the current domain and persist it.
     * @return array{ok:bool,msg:string}
     */
    public static function activate(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'msg' => 'الرجاء إدخال كود التفعيل.'];
        }
        $licenses = self::fetchRemote();
        if ($licenses === null) {
            return ['ok' => false, 'msg' => 'تعذّر الاتصال بخادم التفعيل. تأكد من اتصال الخادم بالإنترنت ثم حاول مرة أخرى.'];
        }
        $domain = self::currentDomain();
        $r = self::evaluate($licenses, $code, $domain);
        if (!$r['ok']) {
            $msg = match ($r['reason']) {
                'domain_mismatch' => 'هذا الكود غير مصرّح له بهذا الدومين (' . $domain . '). تواصل مع الدعم.',
                'disabled'        => 'هذا الكود موقوف حالياً. تواصل مع الدعم لإعادة التفعيل.',
                default           => 'كود التفعيل غير صحيح.',
            };
            return ['ok' => false, 'msg' => $msg];
        }
        $now = time();
        self::store([
            'activated'    => true,
            'code'         => $code,   // server-side ONLY — never emitted or sent to the browser
            'domain'       => $domain,
            'activated_at' => date('c'),
            'last_check'   => $now,
            'last_ok'      => $now,
            'state'        => 'active',
            'reason'       => 'active',
        ]);
        return ['ok' => true, 'msg' => 'تم تفعيل الموقع بنجاح.'];
    }

    /* ---------------- periodic background re-check ---------------- */

    /**
     * Re-verify against the REMOTE authority at most once per interval; between
     * checks the local cache is used only for speed — it is never trusted as the
     * source of on/off. The Blogspot page is always authoritative:
     *   - Timestamps in the future are rejected (a tampered license.json cannot
     *     push the next check away to keep a disabled site alive).
     *   - A successful remote "active" confirmation is required at least once per
     *     GRACE window, so blocking/removing the license host, or editing the
     *     local state to "active", can keep the site up for at most that window
     *     — after which a real remote check is forced.
     */
    public static function refresh(bool $force = false): void
    {
        if (PHP_SAPI === 'cli') return;             // no reliable request host on CLI
        $d = self::load();
        if (empty($d['activated'])) return;         // nothing to recheck before first activation

        $now   = time();
        $state = ($d['state'] ?? 'active') === 'active' ? 'active' : 'locked';

        // Anti-tamper: a future timestamp is impossible on an honest server; treat
        // any future last_check / last_ok as "never happened".
        $lastCheck = (int)($d['last_check'] ?? 0);
        $lastOk    = (int)($d['last_ok'] ?? 0);
        if ($lastCheck > $now) $lastCheck = 0;
        if ($lastOk    > $now) $lastOk    = 0;

        $interval = $state === 'active' ? self::INTERVAL_ACTIVE : self::INTERVAL_LOCKED;
        $due = $force
            || ($now - $lastCheck) >= $interval          // normal cadence
            || $lastOk <= 0                               // never really confirmed
            || ($now - $lastOk) >= self::GRACE_SECONDS;   // must reconfirm within grace
        if (!$due) return;                                // still inside the trusted window

        $licenses = self::fetchRemote();
        $d['last_check'] = $now;
        $d['last_ok']    = $lastOk;                       // persist the sanitized value

        if ($licenses === null) {
            // Transient failure: keep serving ONLY while a real confirmation is
            // still within the grace window; otherwise lock (can't be verified).
            if ($state === 'active' && $lastOk > 0 && ($now - $lastOk) < self::GRACE_SECONDS) {
                $d['state']  = 'active';
                $d['reason'] = 'cached';
            } else {
                $d['state']  = 'locked';
                $d['reason'] = $lastOk > 0 ? 'unreachable' : 'unverified';
            }
            self::store($d);
            return;
        }

        // Remote reachable → its verdict is final for this cycle.
        $r = self::evaluate($licenses, (string)($d['code'] ?? ''), self::currentDomain());
        $d['last_ok'] = $now;
        $d['state']   = $r['ok'] ? 'active' : 'locked';
        $d['reason']  = $r['reason'];
        if ($r['ok']) $d['domain'] = self::currentDomain();
        self::store($d);
    }

    /* ---------------- request gate ---------------- */

    /**
     * Enforce the license for the current request.
     * @param string $context 'web' | 'api' | 'admin'
     *   - active       → always allowed
     *   - unactivated  → 'admin' allowed (to show the activation form); web/api blocked
     *   - locked       → everything blocked
     */
    public static function gate(string $context): void
    {
        self::refresh();
        $st = self::state();
        if ($st === 'active') return;
        if ($st === 'unactivated' && $context === 'admin') return;

        if ($context === 'api') self::apiBlock();
        self::renderLockedPage();
    }

    /** JSON 403 for API endpoints when the site is not active. */
    private static function apiBlock(): void
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo json_encode([
            'ok'      => false,
            'error'   => 'license_inactive',
            'message' => 'الموقع غير مُفعّل حالياً. للتواصل وإعادة التفعيل: ' . self::CONTACT_TEXT,
            'contact' => self::CONTACT_URL,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Standalone, self-contained deactivation page (no site assets required). */
    public static function renderLockedPage(): void
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex, nofollow');
        }
        $url  = self::CONTACT_URL;
        $text = self::CONTACT_TEXT;
        echo <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>تم إيقاف تفعيل الموقع</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{min-height:100vh;display:grid;place-items:center;padding:24px;
    font-family:'Segoe UI',Tahoma,system-ui,-apple-system,sans-serif;
    background:radial-gradient(1100px 620px at 50% -10%,#2A0A83 0%,#1B0761 45%,#10033D 100%);
    color:#EDE9FF}
  .card{width:min(520px,100%);text-align:center;
    background:rgba(27,7,97,.55);border:1px solid rgba(124,77,255,.35);
    border-radius:26px;padding:44px 30px 36px;
    box-shadow:0 30px 80px rgba(0,0,0,.45),0 0 0 1px rgba(124,77,255,.12) inset;
    -webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px)}
  .logo{width:96px;height:96px;margin:0 auto 22px;display:grid;place-items:center;
    border-radius:26px;background:linear-gradient(150deg,#5B12E7,#340996 55%,#1B0761);
    box-shadow:0 14px 40px rgba(76,14,205,.5)}
  .logo svg{width:60px;height:60px}
  h1{font-size:23px;font-weight:800;margin-bottom:12px;letter-spacing:.2px}
  p{color:#C6B8FF;font-size:15px;line-height:1.9;margin-bottom:4px}
  .handle{display:inline-block;margin:6px 0 24px;color:#B39DFF;font-weight:700;
    direction:ltr;font-size:15px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;
    width:100%;padding:15px 22px;border-radius:999px;text-decoration:none;
    font-size:16px;font-weight:800;color:#fff;
    background:linear-gradient(135deg,#7C4DFF,#4C0ECD);
    box-shadow:0 12px 30px rgba(76,14,205,.5);transition:transform .15s ease,box-shadow .15s ease}
  .btn:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(76,14,205,.6)}
  .btn svg{width:22px;height:22px;fill:currentColor}
  .sep{height:1px;background:rgba(124,77,255,.25);margin:26px 0 0}
  .foot{margin-top:18px;color:#9A86D8;font-size:12.5px}
</style>
</head>
<body>
  <main class="card">
    <div class="logo" aria-hidden="true">
      <svg viewBox="0 0 1024 1024" role="img" aria-label="ALOKA Live">
        <path fill="#FFFFFF" fill-rule="evenodd" d="M512 96c-45 0-81 28-100 72L118 866c-13 31 10 62 43 62h143c19 0 36-11 43-29l71-179h188l71 179c7 18 24 29 43 29h143c33 0 56-31 43-62L612 168c-19-44-55-72-100-72Zm-67 296c0-21 23-34 41-23l165 105c17 11 17 37 0 48L486 627c-18 11-41-2-41-23V392Zm10 328h114l-57 145-57-145Z"/>
      </svg>
    </div>
    <h1>تم إيقاف تفعيل الموقع</h1>
    <p>للتواصل وإعادة التفعيل:</p>
    <span class="handle">{$text}</span>
    <a class="btn" href="{$url}" target="_blank" rel="noopener">
      <svg viewBox="0 0 24 24"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.2 13.4l-4.8-1.5c-1-.3-1.1-1 .2-1.5l18.7-7.2c.9-.3 1.7.2 1.4 1.1z"/></svg>
      تواصل عبر تيليجرام
    </a>
    <div class="sep"></div>
    <div class="foot">ALOKA Live</div>
  </main>
</body>
</html>
HTML;
        exit;
    }
}
