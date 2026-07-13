<?php

declare(strict_types=1);

namespace BtolatApi;

final class HttpClient
{
    /** @var string[] */
    private array $allowedHosts = ['mobile.btolat.com', 'www.btolat.com'];

    public function __construct(private readonly Cache $cache)
    {
    }

    /** @return array{body: string, cached: bool, status: int} */
    public function get(string $url, bool $refresh = false): array
    {
        return $this->request('GET', $url, [], $refresh);
    }

    /** @param array<string, scalar> $fields
     *  @return array{body: string, cached: bool, status: int}
     */
    public function post(string $url, array $fields, bool $refresh = false): array
    {
        return $this->request('POST', $url, $fields, $refresh);
    }

    /** @param array<string, scalar> $fields
     *  @return array{body: string, cached: bool, status: int}
     */
    private function request(string $method, string $url, array $fields, bool $refresh): array
    {
        $this->assertAllowedUrl($url);
        $cacheKey = $method . ':' . $url . ':' . http_build_query($fields);
        $lastStatus = 0;

        $result = $this->cache->remember($cacheKey, function () use ($method, $url, $fields, &$lastStatus): string {
            $lastError = 'خطأ شبكة غير معروف';

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                $handle = curl_init($url);
                if ($handle === false) {
                    throw new \RuntimeException('تعذر تهيئة cURL.');
                }

                $headers = [
                    'Accept: text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                    'Accept-Language: ar,en-US;q=0.8,en;q=0.6',
                    'Cache-Control: no-cache',
                    'Referer: ' . Config::BASE_URL . '/videos',
                ];

                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => Config::CONNECT_TIMEOUT,
                    CURLOPT_TIMEOUT => Config::REQUEST_TIMEOUT,
                    CURLOPT_ENCODING => '',
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BtolatJsonApi/1.0; +https://example.com)',
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);

                if ($method === 'POST') {
                    curl_setopt($handle, CURLOPT_POST, true);
                    curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
                    $headers[] = 'X-Requested-With: XMLHttpRequest';
                    curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
                }

                $body = curl_exec($handle);
                $lastStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $curlError = curl_error($handle);
                curl_close($handle);

                if (is_string($body) && $lastStatus >= 200 && $lastStatus < 300) {
                    return $body;
                }

                $lastError = $curlError !== '' ? $curlError : 'HTTP ' . $lastStatus;
                if ($attempt < 2) {
                    usleep(250000);
                }
            }

            throw new \RuntimeException('فشل الاتصال بمصدر بطولات: ' . $lastError);
        }, $refresh);

        return [
            'body' => $result['value'],
            'cached' => $result['cached'],
            'status' => $result['cached'] ? 200 : $lastStatus,
        ];
    }

    private function assertAllowedUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($scheme !== 'https' || !in_array($host, $this->allowedHosts, true)) {
            throw new \InvalidArgumentException('رابط المصدر غير مسموح.');
        }
    }
}
