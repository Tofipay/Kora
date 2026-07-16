<?php

declare(strict_types=1);

use BtolatApi\BtolatScraper;
use BtolatApi\Cache;
use BtolatApi\Config;
use BtolatApi\HttpClient;

require dirname(__DIR__) . '/src/Config.php';
require dirname(__DIR__) . '/src/Cache.php';
require dirname(__DIR__) . '/src/HttpClient.php';
require dirname(__DIR__) . '/src/BtolatScraper.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60, stale-while-revalidate=240');

$allowedOrigin = getenv('CORS_ALLOW_ORIGIN') ?: '*';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(['success' => false, 'error' => ['code' => 'method_not_allowed', 'message' => 'المسموح هو GET فقط.']], 405);
}

$cacheDirectory = getenv('CACHE_DIR') ?: dirname(__DIR__) . '/storage/cache';
$cacheTtl = positiveIntFromEnvironment('CACHE_TTL', Config::CACHE_TTL);
$scraper = new BtolatScraper(new HttpClient(new Cache($cacheDirectory, $cacheTtl)));

try {
    $endpoint = strtolower(trim((string) ($_GET['endpoint'] ?? 'videos')));
    $refresh = boolParam('refresh', false);

    switch ($endpoint) {
        case 'health':
            respond([
                'success' => true,
                'data' => [
                    'service' => 'Btolat Videos JSON API',
                    'version' => '1.0.0',
                    'status' => 'ok',
                    'php' => PHP_VERSION,
                    'time' => date(DATE_ATOM),
                ],
            ]);

        case 'categories':
            $categories = [];
            foreach (Config::categories() as $key => $category) {
                $categories[] = [
                    'key' => $key,
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'id' => $category['id'],
                    'slug' => $category['slug'],
                    'source_url' => Config::BASE_URL . $category['path'],
                    'api_url' => currentApiUrl(['endpoint' => 'videos', 'category' => $key, 'page' => 1]),
                ];
            }
            respond([
                'success' => true,
                'meta' => ['count' => count($categories), 'generated_at' => date(DATE_ATOM)],
                'data' => $categories,
            ]);

        case 'video':
            $id = intParam('id', 0, 1, PHP_INT_MAX);
            $video = $scraper->video($id, $refresh);
            respond([
                'success' => true,
                'meta' => ['cached' => $video['cached'] ?? false, 'generated_at' => date(DATE_ATOM)],
                'data' => $video,
            ]);

        case 'videos':
            $category = strtolower(trim((string) ($_GET['category'] ?? 'all')));
            $page = intParam('page', 1, 1, Config::MAX_PAGE);
            $pages = intParam('pages', 1, 1, Config::MAX_PAGES_PER_REQUEST);
            $enrich = boolParam('enrich', false);

            if ($enrich && $pages > 1) {
                throw new InvalidArgumentException('عند enrich=1 يجب أن تكون pages=1 لتقليل عدد الطلبات الخارجية.');
            }

            $result = $scraper->videos($category, $page, $pages, $refresh, $enrich);
            $nextPage = $result['has_more'] ? $page + $result['pages_fetched'] : null;

            respond([
                'success' => true,
                'meta' => [
                    'category' => $category,
                    'page' => $page,
                    'pages_requested' => $pages,
                    'pages_fetched' => $result['pages_fetched'],
                    'count' => count($result['videos']),
                    'has_more' => $result['has_more'],
                    'next_page' => $nextPage,
                    'cached' => $result['cached'],
                    'enriched' => $enrich,
                    'source_url' => $result['source_url'],
                    'generated_at' => date(DATE_ATOM),
                ],
                'data' => $result['videos'],
            ]);

        default:
            throw new InvalidArgumentException('endpoint غير معروف. القيم المتاحة: videos, video, categories, health.');
    }
} catch (InvalidArgumentException | JsonException $exception) {
    respond([
        'success' => false,
        'error' => ['code' => 'invalid_request', 'message' => $exception->getMessage()],
    ], 400);
} catch (Throwable $exception) {
    error_log('[btolat-api] ' . $exception::class . ': ' . $exception->getMessage());
    respond([
        'success' => false,
        'error' => [
            'code' => 'upstream_error',
            'message' => 'تعذر جلب البيانات من المصدر حاليًا. حاول مرة أخرى لاحقًا.',
        ],
    ], 502);
}

/** @param array<string, mixed> $payload */
function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    exit;
}

function intParam(string $name, int $default, int $minimum, int $maximum): int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }

    $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
    if ($value === false || $value < $minimum || $value > $maximum) {
        throw new InvalidArgumentException("المعلمة {$name} يجب أن تكون عددًا صحيحًا بين {$minimum} و{$maximum}.");
    }
    return (int) $value;
}

function boolParam(string $name, bool $default): bool
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }

    $value = filter_var($_GET[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        throw new InvalidArgumentException("المعلمة {$name} يجب أن تكون 0 أو 1.");
    }
    return $value;
}

function positiveIntFromEnvironment(string $name, int $default): int
{
    $value = getenv($name);
    return $value !== false && ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
}

/** @param array<string, scalar> $query */
function currentApiUrl(array $query): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?') ?: '/index.php';
    return $scheme . '://' . $host . $path . '?' . http_build_query($query);
}
