<?php
/** GET /news-details.php?id=N[&lang=ar|en] — a single news article. */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$id = api_int('id');
if ($id <= 0) api_error('Missing news id', [], 400);

api_serve(function () use ($id) {
    $detail = Api::newsDetail($id);
    if (!$detail) {
        // Detail blocked/unavailable → rebuild from the list metadata we have.
        $item = Api::findNewsItem($id);
        if ($item) {
            $detail = [
                'id'        => $id,
                'title'     => $item['title'] ?? '',
                'news_desc' => $item['news_desc'] ?? '',
                'full_news' => $item['full_news'] ?? $item['news_desc'] ?? '',
                'image'     => $item['image'] ?? null,
                'created_at'=> $item['created_at'] ?? $item['date'] ?? '',
                'partial'   => true,
            ];
        }
    }
    return $detail ?: [];
}, 'news_detail_' . Lang::current() . '_' . $id, CACHE_TTL_NEWS, api_fail_text());
