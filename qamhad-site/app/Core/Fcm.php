<?php
declare(strict_types=1);

namespace Qamhad\Core;

/**
 * Firebase Cloud Messaging — HTTP v1 API sender (modern).
 *
 * Replaces the deprecated legacy endpoint (fcm/send + "Authorization: key=…").
 * This is a self-contained, dependency-free equivalent of the Firebase Admin
 * SDK for PHP: it mints an OAuth2 access token from a Service Account JSON
 * (RS256-signed JWT → https://oauth2.googleapis.com/token) and posts messages
 * to https://fcm.googleapis.com/v1/projects/{projectId}/messages:send with
 * "Authorization: Bearer {access_token}".
 *
 * Security model
 *  - The Service Account JSON (the private key) lives ONLY on the server,
 *    under storage/ (outside the web root, also denied by storage/.htaccess).
 *  - It is never exposed to the frontend. The browser only ever receives the
 *    PUBLIC web config (apiKey/appId/vapidKey…) needed for getToken().
 *  - The short-lived OAuth token is cached server-side until ~5 min pre-expiry.
 */
final class Fcm
{
    private const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** Server-only path of the uploaded Service Account JSON. */
    public static function saPath(): string
    {
        return SETTINGS_DIR . '/service-account.json';
    }

    private static function tokenCachePath(): string
    {
        return SETTINGS_DIR . '/fcm-token.json';
    }

    private static function logPath(): string
    {
        return SETTINGS_DIR . '/fcm.log';
    }

    /* ---------------- Service Account ---------------- */

    /** Parsed Service Account, or null when not configured / invalid. */
    public static function serviceAccount(): ?array
    {
        $f = self::saPath();
        if (!is_file($f)) return null;
        $d = json_decode((string)file_get_contents($f), true);
        if (!is_array($d) || empty($d['private_key']) || empty($d['client_email']) || empty($d['project_id'])) {
            return null;
        }
        return $d;
    }

    public static function isConfigured(): bool
    {
        return self::serviceAccount() !== null;
    }

    /** Non-secret metadata for the admin panel (never the private key). */
    public static function saMeta(): ?array
    {
        $sa = self::serviceAccount();
        if (!$sa) return null;
        return [
            'project_id'   => (string)$sa['project_id'],
            'client_email' => (string)$sa['client_email'],
            'key_id'       => (string)($sa['private_key_id'] ?? ''),
        ];
    }

    /**
     * Validate and persist an uploaded Service Account JSON (0600, server-only).
     * @return array{ok:bool,msg:string}
     */
    public static function saveServiceAccount(string $json): array
    {
        $d = json_decode($json, true);
        if (!is_array($d)) return ['ok' => false, 'msg' => 'Invalid JSON file'];
        foreach (['type', 'project_id', 'private_key', 'client_email'] as $k) {
            if (empty($d[$k])) return ['ok' => false, 'msg' => "Not a Service Account JSON (missing “{$k}”)"];
        }
        if (($d['type'] ?? '') !== 'service_account') {
            return ['ok' => false, 'msg' => 'JSON is not of type service_account'];
        }
        // Sanity: the private key must be a usable RSA key.
        if (openssl_pkey_get_private($d['private_key']) === false) {
            return ['ok' => false, 'msg' => 'The private_key in the JSON is not a valid key'];
        }
        if (!is_dir(SETTINGS_DIR)) @mkdir(SETTINGS_DIR, 0755, true);
        $ok = (bool)@file_put_contents(self::saPath(), $json, LOCK_EX);
        if ($ok) {
            @chmod(self::saPath(), 0600);
            @unlink(self::tokenCachePath()); // force a fresh token on next send
        }
        return $ok
            ? ['ok' => true, 'msg' => 'Service Account saved for project ' . $d['project_id']]
            : ['ok' => false, 'msg' => 'Could not write the Service Account file (check storage permissions)'];
    }

    /** Remove the Service Account and any cached token. */
    public static function deleteServiceAccount(): void
    {
        @unlink(self::saPath());
        @unlink(self::tokenCachePath());
    }

    /** Effective FCM project id: Service Account wins, else saved web config. */
    public static function projectId(): string
    {
        $sa = self::serviceAccount();
        if ($sa) return (string)$sa['project_id'];
        $fcm = Settings::get('fcm', []);
        return trim((string)($fcm['projectId'] ?? ''));
    }

    /* ---------------- OAuth2 access token ---------------- */

    /**
     * Cached OAuth2 access token for the messaging scope.
     * @return array{token?:string,error?:string}
     */
    public static function accessToken(): array
    {
        $cache = self::tokenCachePath();
        if (is_file($cache)) {
            $c = json_decode((string)file_get_contents($cache), true);
            if (is_array($c) && !empty($c['access_token']) && (int)($c['exp'] ?? 0) > time() + 300) {
                return ['token' => (string)$c['access_token']];
            }
        }

        $sa = self::serviceAccount();
        if (!$sa) return ['error' => 'Service Account not configured'];

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim  = [
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $sa['token_uri'] ?? self::TOKEN_URI,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];
        $unsigned = self::b64url((string)json_encode($header)) . '.' . self::b64url((string)json_encode($claim));

        $sig = '';
        if (!openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            return ['error' => 'Failed to sign JWT (invalid private key)'];
        }
        $assertion = $unsigned . '.' . self::b64url($sig);

        [$code, $res] = self::http(
            (string)($sa['token_uri'] ?? self::TOKEN_URI),
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $j = json_decode((string)$res, true) ?: [];

        if ($code === 200 && !empty($j['access_token'])) {
            @file_put_contents($cache, (string)json_encode([
                'access_token' => $j['access_token'],
                'exp'          => $now + (int)($j['expires_in'] ?? 3600),
            ]), LOCK_EX);
            @chmod($cache, 0600);
            return ['token' => (string)$j['access_token']];
        }

        $err = 'OAuth token error HTTP ' . $code . ' — '
             . ($j['error_description'] ?? $j['error'] ?? substr((string)$res, 0, 160));
        self::log('auth', $err);
        return ['error' => $err];
    }

    /* ---------------- Sending ---------------- */

    /**
     * Send one message to a single registration token via HTTP v1.
     * @return array{ok:bool,code:int,error?:string,unregistered?:bool}
     */
    public static function sendToToken(string $token, array $message, string $accessToken, string $projectId): array
    {
        $url  = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
        $body = (string)json_encode(
            ['message' => array_merge(['token' => $token], $message)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        [$code, $res] = self::http($url, $body, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        if ($code === 200) return ['ok' => true, 'code' => 200];

        $j = json_decode((string)$res, true) ?: [];
        $status = (string)($j['error']['status'] ?? '');
        // Stale/removed tokens → safe to prune from the subscriber list.
        $unregistered = $code === 404
            || in_array($status, ['NOT_FOUND', 'UNREGISTERED'], true);
        return [
            'ok'           => false,
            'code'         => $code,
            'error'        => (string)($j['error']['message'] ?? substr((string)$res, 0, 160)),
            'unregistered' => $unregistered,
        ];
    }

    /**
     * Broadcast to stored push subscribers. When $topic is given (a per-league
     * slug like "lg_339"), only tokens subscribed to that topic — or to 'all',
     * or saved before per-league topics existed (legacy slugs) — receive it;
     * admin broadcasts pass no topic and reach everyone. Prunes tokens FCM
     * reports as unregistered so the subscriber list self-heals.
     *
     * @return array{ok:bool,summary:string,sent:int,failed:int,pruned:int}
     */
    public static function broadcast(string $title, string $body, ?string $url = null, ?string $topic = null): array
    {
        $fail = fn(string $m): array => ['ok' => false, 'summary' => $m, 'sent' => 0, 'failed' => 0, 'pruned' => 0];

        if (trim($title) === '') return $fail('Title is required');
        if (!self::isConfigured()) return $fail('Upload the Service Account JSON first');

        $projectId = self::projectId();
        if ($projectId === '') return $fail('Firebase Project ID is missing');

        $at = self::accessToken();
        if (empty($at['token'])) return $fail($at['error'] ?? 'Could not obtain an access token');

        $tokens = Settings::get('push_tokens', []);
        if (!is_array($tokens) || !$tokens) return $fail('No subscribers yet');

        $link    = $url ?: SITE_URL;
        $message = self::buildMessage($title, $body, $link);

        $sent = 0; $failed = 0; $prune = [];
        foreach ($tokens as $row) {
            $tk = is_array($row) ? (string)($row['token'] ?? '') : (string)$row;
            if ($tk === '') continue;
            if ($topic !== null && !self::wantsTopic($row, $topic)) continue;
            $r = self::sendToToken($tk, $message, (string)$at['token'], $projectId);
            if ($r['ok']) {
                $sent++;
            } else {
                $failed++;
                self::log('send', 'HTTP ' . $r['code'] . ' ' . ($r['error'] ?? ''));
                if (!empty($r['unregistered'])) $prune[$tk] = true;
            }
        }

        if ($prune) {
            $kept = array_values(array_filter($tokens, static function ($row) use ($prune) {
                $tk = is_array($row) ? (string)($row['token'] ?? '') : (string)$row;
                return empty($prune[$tk]);
            }));
            Settings::set('push_tokens', $kept);
        }

        $summary = "Sent {$sent}, failed {$failed}" . ($prune ? ', pruned ' . count($prune) . ' stale token(s)' : '');
        return ['ok' => $sent > 0, 'summary' => $summary, 'sent' => $sent, 'failed' => $failed, 'pruned' => count($prune)];
    }

    /**
     * Does this stored token row want pushes for the given per-league topic?
     * Rules: 'all' → yes; the exact topic → yes; a row whose topics predate
     * the per-league system (no "lg_" slug at all) → yes (legacy opt-in was
     * effectively "everything"); otherwise the visitor toggled the league OFF.
     */
    private static function wantsTopic($row, string $topic): bool
    {
        if (!is_array($row)) return true;                        // bare legacy token
        $topics = $row['topics'] ?? null;
        if (!is_array($topics) || !$topics) return true;         // no preference stored
        if (in_array('all', $topics, true)) return true;
        if (in_array($topic, $topics, true)) return true;
        foreach ($topics as $t) {
            if (is_string($t) && str_starts_with($t, 'lg_')) return false; // chose leagues, not this one
        }
        return true;                                             // legacy slugs only
    }

    /**
     * HTTP v1 message body. Carries a `notification` (title/body) plus a
     * `webpush` block so BOTH the FCM default SW (fcm_options.link) and our own
     * /sw.js push handler (data.url) render correctly on Android Chrome, WebAPK
     * and installed PWAs. All data values must be strings.
     */
    private static function buildMessage(string $title, string $body, string $url): array
    {
        $icon = SITE_URL . '/assets/brand/icon-192.png';
        return [
            'notification' => ['title' => $title, 'body' => $body],
            'webpush' => [
                'notification' => ['icon' => $icon, 'badge' => $icon],
                'fcm_options'  => ['link' => $url],
            ],
            'data' => [
                'url'   => $url,
                'title' => $title,
                'body'  => $body,
                'icon'  => $icon,
            ],
        ];
    }

    /* ---------------- Low-level helpers ---------------- */

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** @return array{0:int,1:string} [httpCode, responseBody] */
    private static function http(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return [0, (string)json_encode(['error' => ['message' => 'cURL: ' . $err]])];
        }
        curl_close($ch);
        return [$code, (string)$res];
    }

    /** Append a timestamped failure reason to storage/settings/fcm.log. */
    private static function log(string $kind, string $msg): void
    {
        @file_put_contents(
            self::logPath(),
            '[' . date('c') . '] ' . $kind . ': ' . $msg . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /** Last N log lines for the admin panel (most recent first). */
    public static function tailLog(int $lines = 10): array
    {
        $f = self::logPath();
        if (!is_file($f)) return [];
        $all = array_values(array_filter(explode("\n", (string)file_get_contents($f)), 'strlen'));
        return array_slice(array_reverse($all), 0, $lines);
    }
}
