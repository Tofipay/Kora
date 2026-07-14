<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Cache;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\View;

/**
 * Admin panel: dashboard, branding/logo, SEO, homepage builder, theme,
 * cache manager, notifications (FCM), newsletter export, language defaults.
 *
 * Single-password auth (hashed at rest). Change it in Settings → Security.
 */
final class Admin
{
    public static function dispatch(string $action): void
    {
        tofixtv_session_start();
        header('X-Robots-Tag: noindex, nofollow');

        if ($action === 'login') { self::login(); return; }
        if ($action === 'logout') {
            unset($_SESSION['qadmin']);
            View::redirect('/' . ADMIN_PATH . '/login', 302);
        }
        if (empty($_SESSION['qadmin'])) View::redirect('/' . ADMIN_PATH . '/login', 302);

        // CSRF for all POSTs
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $tok = (string)($_POST['_csrf'] ?? '');
            if (!hash_equals(self::csrf(), $tok)) { http_response_code(403); exit('Bad CSRF token'); }
        }

        match (true) {
            $action === '' || $action === 'dashboard' => self::render('dashboard', self::dashboardData()),
            $action === 'update'        => self::updateSite(),
            $action === 'branding'      => self::branding(),
            $action === 'seo'           => self::seo(),
            $action === 'homepage'      => self::homepage(),
            $action === 'theme'         => self::theme(),
            $action === 'cache'         => self::cache(),
            $action === 'notifications' => self::notifications(),
            $action === 'newsletter'    => self::newsletter(),
            $action === 'streaming'     => self::streaming(),
            $action === 'channels'      => self::channels(),
            $action === 'security'      => self::security(),
            default => View::notFound(),
        };
    }

    /* ---------------- Channels Library ---------------- */

    private static function channels(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $names   = (array)($_POST['c_name'] ?? []);
            $urlsRaw = (array)($_POST['c_urls'] ?? []);   // one textarea per channel (URLs by line)
            $items = [];
            foreach ($names as $i => $nm) {
                $urls = preg_split('/\r\n|\r|\n/', (string)($urlsRaw[$i] ?? '')) ?: [];
                $items[] = ['name' => (string)$nm, 'urls' => $urls];
            }
            \TofiXTv\Core\ChannelLib::save($items);
            $msg = 'Saved ' . count(\TofiXTv\Core\ChannelLib::all()) . ' channel(s).';
        }
        self::render('channels', [
            'items' => \TofiXTv\Core\ChannelLib::all(),
            'msg'   => $msg,
        ]);
    }

    /* ---------------- Streaming ---------------- */

    private static function streaming(): void
    {
        $msg = null;
        $editId = (int)($_GET['match'] ?? $_POST['match_id'] ?? 0);

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $editId > 0) {
            $servers = [];
            $names  = (array)($_POST['s_name'] ?? []);
            $urls   = (array)($_POST['s_url'] ?? []);
            $types  = (array)($_POST['s_type'] ?? []);
            $orders = (array)($_POST['s_order'] ?? []);
            $active = (array)($_POST['s_active'] ?? []);
            foreach ($urls as $i => $u) {
                if (trim((string)$u) === '' && trim((string)($names[$i] ?? '')) === '') continue;
                $servers[] = [
                    'name'   => (string)($names[$i] ?? ''),
                    'url'    => (string)$u,
                    'type'   => (string)($types[$i] ?? 'auto'),
                    'order'  => (int)($orders[$i] ?? ($i + 1)),
                    'active' => isset($active[$i]) && $active[$i] !== '0',
                ];
            }
            \TofiXTv\Core\Streams::save($editId, [
                'mode'         => ($_POST['mode'] ?? 'internal'),
                'external_url' => (string)($_POST['external_url'] ?? ''),
                'servers'      => $servers,
            ]);
            $msg = 'Saved streams for match ' . $editId;
        }

        // Build the list: today's matches + already-configured matches
        $today = [];
        foreach (\TofiXTv\Core\Api::matchesByDate() as $m) {
            $today[] = [
                'id'    => (int)($m['match_id'] ?? 0),
                'title' => team_name(team_of($m, 'home')) . ' × ' . team_name(team_of($m, 'away')),
                'league'=> (string)($m['championship']['title'] ?? ''),
                'has'   => \TofiXTv\Core\Streams::isWatchable((int)($m['match_id'] ?? 0)),
            ];
        }
        $configured = [];
        foreach (\TofiXTv\Core\Streams::configuredIds() as $cid) {
            $mi = \TofiXTv\Core\Api::matchInfo($cid);
            $configured[] = [
                'id'    => $cid,
                'title' => $mi ? (team_name(team_of($mi, 'home')) . ' × ' . team_name(team_of($mi, 'away'))) : ('Match ' . $cid),
                'league'=> (string)($mi['championship']['title'] ?? ''),
            ];
        }

        $edit = null;
        if ($editId > 0) {
            $mi = \TofiXTv\Core\Api::matchInfo($editId);
            $edit = [
                'id'     => $editId,
                'title'  => $mi ? (team_name(team_of($mi, 'home')) . ' × ' . team_name(team_of($mi, 'away'))) : ('Match ' . $editId),
                'cfg'    => \TofiXTv\Core\Streams::forMatch($editId),
            ];
        }

        self::render('streaming', [
            'msg' => $msg, 'today' => $today, 'configured' => $configured,
            'edit' => $edit, 'types' => \TofiXTv\Core\Streams::TYPES,
        ]);
    }

    /* ---------------- Auth ---------------- */

    private static function login(): void
    {
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $pass = (string)($_POST['password'] ?? '');
            $rl = $_SESSION['qadmin_rl'] ?? ['n' => 0, 't' => time()];
            if (time() - $rl['t'] > 600) $rl = ['n' => 0, 't' => time()];
            $rl['n']++;
            $_SESSION['qadmin_rl'] = $rl;
            if ($rl['n'] > 10) {
                $error = 'Too many attempts. Try again later.';
            } else {
                $sec = Settings::get('security', []);
                $hash = $sec['password_hash'] ?? password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT);
                if (password_verify($pass, $hash)) {
                    session_regenerate_id(true);
                    $_SESSION['qadmin'] = true;
                    View::redirect('/' . ADMIN_PATH, 302);
                }
                $error = 'Wrong password';
            }
        }
        self::render('login', ['error' => $error], false);
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['qadmin_csrf'])) $_SESSION['qadmin_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['qadmin_csrf'];
    }

    /* ---------------- Sections ---------------- */

    /**
     * "تحديث الموقع" — publish a new release to every client at once.
     * Bumps the global build token (so each visitor's service worker sees a
     * new /sw.js?v= and prompts "update now"), clears the API cache and resets
     * OPcache. No one has to clear their browser cache.
     */
    private static function updateSite(): void
    {
        Settings::merge('system', ['build' => date('Ymd-His'), 'updated_at' => date('c')]);
        $removed = Cache::flush();
        if (function_exists('opcache_reset')) @opcache_reset();
        $_SESSION['qflash'] = 'تم نشر إصدار جديد وتحديث الكاش (' . $removed . ' ملف). سيصل التحديث لكل المستخدمين تلقائياً.';
        View::redirect('/' . ADMIN_PATH, 302);
    }

    private static function dashboardData(): array
    {
        $a = Settings::get('analytics', []);
        if (!is_array($a)) $a = [];
        $days = is_array($a['days'] ?? null) ? $a['days'] : [];
        ksort($days);

        // Truthful roll-ups from the daily buckets.
        $today = date('Y-m-d');
        $sum = function (int $back) use ($days): int {
            $from = date('Y-m-d', strtotime("-{$back} days"));
            $t = 0;
            foreach ($days as $d => $row) { if ($d >= $from) $t += (int)($row['total'] ?? 0); }
            return $t;
        };
        // Prune the online ring to the last 5 minutes for an accurate count.
        $online = is_array($a['online'] ?? null) ? $a['online'] : [];
        $now = time();
        $onlineNow = 0;
        foreach ($online as $ts) { if ($now - (int)$ts <= 300) $onlineNow++; }

        $topOf = function (string $type, int $n = 6) use ($a): array {
            $rows = is_array($a['top'][$type] ?? null) ? $a['top'][$type] : [];
            arsort($rows);
            return array_slice($rows, 0, $n, true);
        };

        $flash = $_SESSION['qflash'] ?? null;
        unset($_SESSION['qflash']);

        return [
            'total'    => (int)($a['total'] ?? 0),
            'today'    => (int)($days[$today]['total'] ?? 0),
            'week'     => $sum(7),
            'month'    => $sum(30),
            'onlineNow'=> $onlineNow,
            'days'     => array_slice($days, -14, null, true),
            'sources'  => is_array($a['sources'] ?? null) ? $a['sources'] : [],
            'devices'  => is_array($a['devices'] ?? null) ? $a['devices'] : [],
            'browsers' => is_array($a['browsers'] ?? null) ? $a['browsers'] : [],
            'topMatches' => $topOf('match'),
            'topNews'    => $topOf('article'),
            'topVideos'  => $topOf('video'),
            'topChamps'  => $topOf('videos'),
            'cache'    => Cache::stats(),
            'tokens'   => count(Settings::get('push_tokens', []) ?: []),
            'emails'   => count(Settings::get('newsletter', []) ?: []),
            'server'   => self::serverMetrics(),
            'build'    => build_token(),
            'flash'    => $flash,
        ];
    }

    /** Real server metrics readable from PHP (RAM/CPU/disk/cache/PHP). */
    private static function serverMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
        $cores = 0;
        if (is_readable('/proc/cpuinfo')) $cores = substr_count((string)@file_get_contents('/proc/cpuinfo'), "\nprocessor");
        $memTotal = $memAvail = 0;
        if (is_readable('/proc/meminfo')) {
            $mi = (string)@file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $mi, $m)) $memTotal = (int)$m[1] * 1024;
            if (preg_match('/MemAvailable:\s+(\d+)/', $mi, $m)) $memAvail = (int)$m[1] * 1024;
        }
        $diskTotal = @disk_total_space(STORAGE_DIR) ?: 0;
        $diskFree  = @disk_free_space(STORAGE_DIR) ?: 0;
        return [
            'php'        => PHP_VERSION,
            'load'       => is_array($load) ? round((float)$load[0], 2) : null,
            'cores'      => $cores ?: null,
            'mem_total'  => $memTotal,
            'mem_used'   => $memTotal && $memAvail ? $memTotal - $memAvail : 0,
            'php_mem'    => memory_get_peak_usage(true),
            'disk_total' => $diskTotal,
            'disk_used'  => $diskTotal && $diskFree ? $diskTotal - $diskFree : 0,
            'opcache'    => function_exists('opcache_get_status') && @opcache_get_status(false)['opcache_enabled'],
        ];
    }

    private static function branding(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $patch = [];
            foreach (['logo', 'logo_dark'] as $field) {
                if (!empty($_FILES[$field]['tmp_name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
                    $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['svg', 'png', 'webp', 'jpg', 'jpeg'], true) && (int)$_FILES[$field]['size'] < 2_000_000) {
                        $dir = PUBLIC_DIR . '/assets/uploads';
                        if (!is_dir($dir)) @mkdir($dir, 0755, true);
                        $name = $field . '-' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
                            $patch[$field] = $name;
                        }
                    } else {
                        $msg = 'Rejected file (svg/png/webp/jpg, max 2MB)';
                    }
                }
            }
            if (isset($_POST['reset'])) {
                Settings::set('branding', []);
                $msg = 'Reset to default brand assets';
            } elseif ($patch) {
                Settings::merge('branding', $patch);
                $msg = 'Saved';
            }
        }
        self::render('branding', ['b' => Settings::get('branding', []), 'msg' => $msg]);
    }

    private static function seo(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            Settings::set('seo', [
                'title_ar' => trim((string)($_POST['title_ar'] ?? '')),
                'title_en' => trim((string)($_POST['title_en'] ?? '')),
                'desc_ar'  => trim((string)($_POST['desc_ar'] ?? '')),
                'desc_en'  => trim((string)($_POST['desc_en'] ?? '')),
                'gsc'      => trim((string)($_POST['gsc'] ?? '')),   // Search Console token
                'ga4'      => trim((string)($_POST['ga4'] ?? '')),   // GA4 measurement id
            ]);
            $msg = 'Saved';
        }
        self::render('seo', ['s' => Settings::get('seo', []), 'msg' => $msg]);
    }

    private static function homepage(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $order = json_decode((string)($_POST['sections'] ?? '[]'), true);
            if (is_array($order)) {
                Settings::set('homepage', ['sections' => array_map(fn($s) => [
                    'id' => (string)($s['id'] ?? ''), 'on' => (bool)($s['on'] ?? true),
                ], $order)]);
                $msg = 'Saved';
            }
        }
        self::render('homepage', ['sections' => Settings::homeSections(), 'msg' => $msg]);
    }

    private static function theme(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $clean = fn($v) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$v) ? (string)$v : '';
            Settings::set('theme', array_filter([
                'primary' => $clean($_POST['primary'] ?? ''),
                'accent'  => $clean($_POST['accent'] ?? ''),
                'default_mode' => in_array($_POST['default_mode'] ?? '', ['light', 'dark', 'auto'], true)
                    ? $_POST['default_mode'] : 'auto',
            ]));
            $msg = 'Saved';
        }
        self::render('theme', ['t' => Settings::get('theme', []), 'msg' => $msg]);
    }

    private static function cache(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (isset($_POST['flush_api']))   $msg = Cache::flush() . ' API cache files removed';
            if (isset($_POST['flush_media'])) $msg = Cache::flushMedia() . ' media files removed';
            // Force PHP to recompile updated .php files (fixes "I uploaded new
            // code but nothing changed" on hosts with opcache.validate_timestamps=0).
            if (isset($_POST['flush_opcache'])) {
                if (function_exists('opcache_reset') && @opcache_reset()) {
                    $msg = 'OPcache cleared — new PHP code is now live.';
                } else {
                    $msg = 'OPcache is not enabled (or reset is disabled) on this server.';
                }
            }
        }
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        self::render('cache', [
            'stats'      => Cache::stats(),
            'msg'        => $msg,
            'opcacheOn'  => is_array($opcache) && !empty($opcache['opcache_enabled']),
            'seoBuild'   => \TofiXTv\Core\Seo::BUILD,
        ]);
    }

    private static function notifications(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            // ---- Public web-app config (safe for the browser) + auto events ----
            if (isset($_POST['save_config'])) {
                $cur = Settings::get('fcm', []);
                if (!is_array($cur)) $cur = [];
                Settings::set('fcm', array_merge($cur, [
                    'apiKey'            => trim((string)($_POST['apiKey'] ?? '')),
                    'authDomain'        => trim((string)($_POST['authDomain'] ?? '')),
                    'projectId'         => trim((string)($_POST['projectId'] ?? '')),
                    'messagingSenderId' => trim((string)($_POST['messagingSenderId'] ?? '')),
                    'appId'             => trim((string)($_POST['appId'] ?? '')),
                    'vapidKey'          => trim((string)($_POST['vapidKey'] ?? '')),
                    'events'            => [
                        'match_start' => isset($_POST['ev_match_start']),
                        'goal'        => isset($_POST['ev_goal']),
                        'red_card'    => isset($_POST['ev_red_card']),
                        'half_time'   => isset($_POST['ev_half_time']),
                        'full_time'   => isset($_POST['ev_full_time']),
                        'news'        => isset($_POST['ev_news']),
                    ],
                ]));
                $msg = 'Saved';
            }

            // ---- Service Account JSON upload (server-only, HTTP v1 auth) ----
            if (isset($_POST['upload_sa']) && !empty($_FILES['service_account']['tmp_name'])
                && is_uploaded_file($_FILES['service_account']['tmp_name'])) {
                if ((int)$_FILES['service_account']['size'] > 32_000) {
                    $msg = 'Rejected: file too large to be a Service Account JSON';
                } else {
                    $json = (string)file_get_contents($_FILES['service_account']['tmp_name']);
                    $r = \TofiXTv\Core\Fcm::saveServiceAccount($json);
                    $msg = $r['msg'];
                    // Keep the public projectId in sync with the Service Account.
                    if ($r['ok'] && ($sa = \TofiXTv\Core\Fcm::saMeta())) {
                        Settings::merge('fcm', ['projectId' => $sa['project_id']]);
                    }
                }
            }

            // ---- Remove the Service Account ----
            if (isset($_POST['delete_sa'])) {
                \TofiXTv\Core\Fcm::deleteServiceAccount();
                $msg = 'Service Account removed';
            }

            // ---- Test / broadcast via HTTP v1 ----
            if (isset($_POST['send_test'])) {
                $res = \TofiXTv\Core\Fcm::broadcast(
                    trim((string)($_POST['title'] ?? '')),
                    trim((string)($_POST['body'] ?? '')),
                    SITE_URL
                );
                $msg = $res['summary'];
            }
        }

        self::render('notifications', [
            'f'       => Settings::get('fcm', []),
            'msg'     => $msg,
            'tokens'  => count(Settings::get('push_tokens', []) ?: []),
            'sa'      => \TofiXTv\Core\Fcm::saMeta(),      // null when not configured
            'saLog'   => \TofiXTv\Core\Fcm::tailLog(8),
            'cronUrl' => SITE_URL . '/cron/notify?key=' . \TofiXTv\Core\Notifier::cronKey(),
        ]);
    }

    private static function newsletter(): void
    {
        $list = Settings::get('newsletter', []) ?: [];
        if (isset($_GET['export'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=newsletter.csv');
            echo "email,subscribed_at\n";
            foreach ($list as $row) echo $row['email'] . ',' . $row['at'] . "\n";
            exit;
        }
        self::render('newsletter', ['list' => $list]);
    }

    private static function security(): void
    {
        $msg = null;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $new = (string)($_POST['new_password'] ?? '');
            if (strlen($new) >= 10) {
                Settings::merge('security', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
                $msg = 'Password updated';
            } else {
                $msg = 'Password must be at least 10 characters';
            }
        }
        self::render('security', ['msg' => $msg]);
    }

    /* ---------------- Rendering ---------------- */

    private static function render(string $view, array $data = [], bool $chrome = true): void
    {
        extract($data, EXTR_SKIP);
        $csrf = !empty($_SESSION['qadmin']) || $view === 'login' ? self::csrf() : '';
        require APP_DIR . '/Views/admin/' . $view . '.php';
        exit;
    }
}
