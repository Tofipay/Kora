<?php

declare(strict_types=1);

namespace BtolatApi;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class BtolatScraper
{
    private bool $usedCache = false;

    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @return array{videos: array<int, array<string, mixed>>, pages_fetched: int, has_more: bool, cached: bool, source_url: string}
     */
    public function videos(string $categoryKey, int $page, int $pages, bool $refresh = false, bool $enrich = false): array
    {
        $categories = Config::categories();
        if (!isset($categories[$categoryKey])) {
            throw new \InvalidArgumentException('التصنيف غير موجود. استخدم endpoint=categories لعرض القيم المتاحة.');
        }

        $this->usedCache = false;
        $category = $categories[$categoryKey];

        $result = $category['type'] === 'all'
            ? $this->fetchAll($page, $pages, $refresh)
            : $this->fetchPaginatedCategory($category, $page, $pages, $refresh);

        $result['videos'] = $this->deduplicate($result['videos']);

        if ($enrich) {
            $result['videos'] = $this->enrich($result['videos'], $refresh);
        }

        $result['cached'] = $this->usedCache;
        return $result;
    }

    /** @return array<string, mixed> */
    public function video(int $id, bool $refresh = false): array
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('معرّف الفيديو غير صالح.');
        }

        $response = $this->http->get(Config::BASE_URL . '/video/' . $id, $refresh);
        $this->usedCache = $this->usedCache || $response['cached'];
        $detail = $this->parseVideoDetail($response['body'], $id);
        $detail['cached'] = $response['cached'];
        return $detail;
    }

    /**
     * @return array{videos: array<int, array<string, mixed>>, pages_fetched: int, has_more: bool, source_url: string}
     */
    private function fetchAll(int $page, int $pages, bool $refresh): array
    {
        $endPage = $page + $pages - 1;
        $initial = $this->http->get(Config::BASE_URL . '/videos', $refresh);
        $this->usedCache = $this->usedCache || $initial['cached'];

        $currentItems = $this->parseCards($initial['body'], "//div[@id='news-list']");
        $collected = [];
        $fetched = 0;
        $hasMore = $currentItems !== [];

        for ($currentPage = 1; $currentPage <= $endPage; $currentPage++) {
            if ($currentPage > 1) {
                $cursor = $this->lastCursor($currentItems);
                if ($cursor === null) {
                    $hasMore = false;
                    break;
                }

                $response = $this->http->post(
                    Config::BASE_URL . '/api/video/LoadMore/0',
                    ['lastRowId' => $cursor['id'], 'lasRowDate' => $cursor['date']],
                    $refresh
                );
                $this->usedCache = $this->usedCache || $response['cached'];

                $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload) || ($payload['success'] ?? false) !== true || !is_string($payload['html'] ?? null)) {
                    throw new \RuntimeException('استجابة التحميل المتتابع من بطولات غير متوقعة.');
                }

                $currentItems = $this->parseCards($payload['html']);
                if ($currentItems === []) {
                    $hasMore = false;
                    break;
                }
            }

            if ($currentPage >= $page) {
                array_push($collected, ...$currentItems);
                $fetched++;
            }
        }

        return [
            'videos' => $this->removeInternalFields($collected),
            'pages_fetched' => $fetched,
            'has_more' => $hasMore && $currentItems !== [],
            'source_url' => Config::BASE_URL . '/videos',
        ];
    }

    /**
     * @param array{name: string, type: string, id: int|null, slug: string|null, path: string} $category
     * @return array{videos: array<int, array<string, mixed>>, pages_fetched: int, has_more: bool, source_url: string}
     */
    private function fetchPaginatedCategory(array $category, int $page, int $pages, bool $refresh): array
    {
        $collected = [];
        $fetched = 0;
        $hasMore = false;
        $lastUrl = Config::BASE_URL . $category['path'];

        for ($currentPage = $page; $currentPage < $page + $pages; $currentPage++) {
            $url = Config::BASE_URL . $category['path'] . '?p=' . $currentPage;
            $response = $this->http->get($url, $refresh);
            $this->usedCache = $this->usedCache || $response['cached'];
            $items = $this->parseCards($response['body']);

            if ($items === []) {
                $hasMore = false;
                break;
            }

            array_push($collected, ...$items);
            $fetched++;
            $lastUrl = $url;
            $hasMore = $this->containsNextPage($response['body'], $currentPage + 1);

            if (!$hasMore) {
                break;
            }
        }

        return [
            'videos' => $this->removeInternalFields($collected),
            'pages_fetched' => $fetched,
            'has_more' => $hasMore,
            'source_url' => $lastUrl,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function parseCards(string $html, ?string $scopeXPath = null): array
    {
        [$document, $xpath] = $this->document($html);
        $cardExpression = ".//div[contains(concat(' ', normalize-space(@class), ' '), ' card ') and contains(concat(' ', normalize-space(@class), ' '), ' video ')]"
            . " | .//div[contains(concat(' ', normalize-space(@class), ' '), ' categoryNewsCard ')]";

        if ($scopeXPath !== null) {
            $scopes = $xpath->query($scopeXPath);
            if ($scopes === false || $scopes->length === 0) {
                return [];
            }
            $nodes = $xpath->query($cardExpression, $scopes->item(0));
        } else {
            $nodes = $xpath->query(substr($cardExpression, 1));
        }

        if ($nodes === false) {
            return [];
        }

        $items = [];
        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $videoLink = $this->firstNode($xpath, ".//a[contains(@href, '/video/') or starts-with(@href, 'video/')]", $node);
            $titleNode = $this->firstNode($xpath, './/h3', $node);
            if (!$videoLink instanceof DOMElement || !$titleNode instanceof DOMNode) {
                continue;
            }

            $href = $videoLink->getAttribute('href');
            $id = (int) $node->getAttribute('data-val');
            if ($id < 1 && preg_match('~/video/(\d+)~', '/' . ltrim($href, '/'), $matches) === 1) {
                $id = (int) $matches[1];
            }
            if ($id < 1) {
                continue;
            }

            $imageNode = $this->firstNode($xpath, ".//img[not(ancestor::span[contains(concat(' ', normalize-space(@class), ' '), ' player ')])]", $node);
            $thumbnail = $imageNode instanceof DOMElement
                ? $this->bestImageUrl($imageNode)
                : null;

            $categoryNode = $this->firstNode(
                $xpath,
                ".//a[contains(concat(' ', normalize-space(@class), ' '), ' category ') or contains(concat(' ', normalize-space(@class), ' '), ' categoryTag ')]",
                $node
            );
            $category = $categoryNode instanceof DOMElement
                ? $this->categoryFromAnchor($categoryNode)
                : null;

            $dateRaw = trim($node->getAttribute('data-date'));
            $items[] = [
                'id' => $id,
                'title' => $this->cleanText($titleNode->textContent),
                'page_url' => Config::BASE_URL . '/video/' . $id,
                'thumbnail' => $thumbnail,
                'published_at' => $this->parseDate($dateRaw),
                'published_date' => $this->dateFromThumbnail($thumbnail),
                'category' => $category,
                '_cursor_id' => $id,
                '_cursor_date' => $dateRaw,
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function parseVideoDetail(string $html, int $id): array
    {
        [$document, $xpath] = $this->document($html);
        $titleNode = $this->firstNode($xpath, '//h1');
        $title = $titleNode ? $this->cleanText($titleNode->textContent) : null;

        $ogTitle = $this->metaContent($xpath, 'property', 'og:title');
        $thumbnail = $this->metaContent($xpath, 'property', 'og:image');
        $canonical = $this->firstNode($xpath, "//link[@rel='canonical']");
        $pageUrl = $canonical instanceof DOMElement && $canonical->getAttribute('href') !== ''
            ? $canonical->getAttribute('href')
            : Config::BASE_URL . '/video/' . $id;

        $mediaUrl = null;
        $mediaNode = $this->firstNode($xpath, '//video/source[@src] | //video[@src]');
        if ($mediaNode instanceof DOMElement) {
            $mediaUrl = $mediaNode->getAttribute('src');
        }
        $mediaUrl ??= $this->metaContent($xpath, 'property', 'og:video');

        $provider = null;
        $embedUrl = null;
        $externalUrl = null;

        $youtube = $this->firstNode($xpath, "//iframe[contains(@src, 'youtube.com') or contains(@src, 'youtu.be')]");
        if ($youtube instanceof DOMElement) {
            $provider = 'youtube';
            $embedUrl = $youtube->getAttribute('src');
        }

        $xStatus = $this->firstNode($xpath, "//blockquote[contains(@class, 'twitter-tweet')]//a[contains(@href, 'x.com/') and contains(@href, '/status/')]");
        if ($xStatus instanceof DOMElement) {
            $provider = 'x';
            $externalUrl = preg_replace('/\?.*$/', '', $xStatus->getAttribute('href'));
            $embedUrl = $externalUrl;
        }

        if ($mediaUrl !== null && $provider === null) {
            $provider = 'direct';
        }

        $categoryNode = $this->firstNode($xpath, "//a[starts-with(@href, '/league/') and not(contains(@href, '/news/')) and not(contains(@href, '/videos/'))][1]");
        $category = $categoryNode instanceof DOMElement ? $this->categoryFromAnchor($categoryNode) : null;

        return [
            'id' => $id,
            'title' => $title ?? ($ogTitle !== null ? preg_replace('/\s*-\s*بطولات\s*$/u', '', $ogTitle) : null),
            'page_url' => $pageUrl,
            'thumbnail' => $thumbnail,
            'category' => $category,
            'provider' => $provider,
            'embed_url' => $embedUrl,
            'external_url' => $externalUrl,
            'media_url' => $mediaUrl,
        ];
    }

    /** @param array<int, array<string, mixed>> $videos
     *  @return array<int, array<string, mixed>>
     */
    private function enrich(array $videos, bool $refresh): array
    {
        $limit = min(count($videos), Config::MAX_ENRICH_ITEMS);
        for ($index = 0; $index < $limit; $index++) {
            $detail = $this->video((int) $videos[$index]['id'], $refresh);
            foreach (['provider', 'embed_url', 'external_url', 'media_url'] as $field) {
                $videos[$index][$field] = $detail[$field] ?? null;
            }
        }
        return $videos;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array{id: int, date: string}|null
     */
    private function lastCursor(array $items): ?array
    {
        $last = end($items);
        if (!is_array($last)) {
            return null;
        }

        $id = (int) ($last['_cursor_id'] ?? 0);
        $date = (string) ($last['_cursor_date'] ?? '');
        return $id > 0 && $date !== '' ? ['id' => $id, 'date' => $date] : null;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<int, array<string, mixed>>
     */
    private function removeInternalFields(array $items): array
    {
        foreach ($items as &$item) {
            unset($item['_cursor_id'], $item['_cursor_date']);
        }
        unset($item);
        return $items;
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<int, array<string, mixed>>
     */
    private function deduplicate(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[(int) $item['id']] = $item;
        }
        return array_values($unique);
    }

    private function containsNextPage(string $html, int $nextPage): bool
    {
        return preg_match('/[?&]p=' . preg_quote((string) $nextPage, '/') . '(?:["&]|$)/', $html) === 1;
    }

    /** @return array{0: DOMDocument, 1: DOMXPath} */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return [$document, new DOMXPath($document)];
    }

    private function firstNode(DOMXPath $xpath, string $expression, ?DOMNode $context = null): ?DOMNode
    {
        $nodes = $xpath->query($expression, $context);
        return $nodes !== false && $nodes->length > 0 ? $nodes->item(0) : null;
    }

    private function bestImageUrl(DOMElement $image): ?string
    {
        foreach (['data-src', 'data-original', 'src'] as $attribute) {
            $value = trim($image->getAttribute($attribute));
            if ($value !== '' && !str_contains($value, 'preload.gif') && !str_contains($value, 'play-arrow')) {
                return $this->absoluteUrl($value);
            }
        }
        return null;
    }

    /** @return array{name: string, url: string, type: string|null, id: int|null, slug: string|null} */
    private function categoryFromAnchor(DOMElement $anchor): array
    {
        $href = $anchor->getAttribute('href');
        $type = null;
        $id = null;
        $slug = null;

        if (preg_match('~/(league|team)/(?:videos/)?(\d+)/([^/?#]+)~', $href, $matches) === 1) {
            $type = $matches[1];
            $id = (int) $matches[2];
            $slug = $matches[3];
        }

        return [
            'name' => $this->cleanText($anchor->textContent),
            'url' => $this->absoluteUrl($href),
            'type' => $type,
            'id' => $id,
            'slug' => $slug,
        ];
    }

    private function parseDate(string $raw): ?string
    {
        if ($raw === '' || str_starts_with($raw, '1/1/0001')) {
            return null;
        }

        $timezone = new DateTimeZone('Africa/Cairo');
        $date = DateTimeImmutable::createFromFormat('n/j/Y g:i:s A', $raw, $timezone);
        return $date instanceof DateTimeImmutable ? $date->format(DATE_ATOM) : null;
    }

    private function dateFromThumbnail(?string $url): ?string
    {
        if ($url !== null && preg_match('~/((?:19|20)\d{2})/(\d{1,2})/(\d{1,2})/video/~', $url, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }
        return null;
    }

    private function metaContent(DOMXPath $xpath, string $attribute, string $value): ?string
    {
        $node = $this->firstNode($xpath, "//meta[@{$attribute}='{$value}']");
        return $node instanceof DOMElement && $node->getAttribute('content') !== ''
            ? $node->getAttribute('content')
            : null;
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('~^https?://~i', $url) === 1) {
            return $url;
        }
        return Config::BASE_URL . '/' . ltrim($url, '/');
    }

    private function cleanText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
