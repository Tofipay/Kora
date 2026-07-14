<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * IndexNow client — instantly notifies Bing, Yandex, Seznam, Naver (and the
 * shared IndexNow network) when a URL's content changes. Great for live sports:
 * scores and statuses change constantly, so match/live pages are re-crawled fast.
 *
 * Spec: a key file must be reachable at https://host/{key}.txt containing the
 * key verbatim. We generate the key once and write that static file into public/.
 */
final class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** Get (or lazily create) the IndexNow key and its public key-file. */
    public static function key(): string
    {
        $seo = Settings::get('indexnow', []);
        if (!is_array($seo)) $seo = [];
        $key = (string)($seo['key'] ?? '');
        if ($key === '' || !ctype_xdigit($key)) {
            $key = bin2hex(random_bytes(16));
            Settings::set('indexnow', ['key' => $key]);
        }
        // Ensure the verification file exists at public/{key}.txt.
        $file = PUBLIC_DIR . '/' . $key . '.txt';
        if (!is_file($file)) @file_put_contents($file, $key);
        return $key;
    }

    /**
     * Submit one or many URLs. Silent, best-effort, non-blocking-ish.
     * @param string|string[] $urls absolute URLs on this host
     * @return bool true when the endpoint accepted the batch
     */
    public static function submit($urls): bool
    {
        $urls = array_values(array_unique(array_filter(
            array_map('strval', (array)$urls),
            fn($u) => $u !== '' && str_starts_with($u, 'http')
        )));
        if (!$urls) return false;

        $host = (string)parse_url(SITE_URL, PHP_URL_HOST);
        if ($host === '') return false;
        $key = self::key();

        $payload = (string)json_encode([
            'host'    => $host,
            'key'     => $key,
            'keyLocation' => SITE_URL . '/' . $key . '.txt',
            'urlList' => array_slice($urls, 0, 10000),
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // 200 = accepted, 202 = accepted/pending validation.
        return in_array($code, [200, 202], true);
    }
}
