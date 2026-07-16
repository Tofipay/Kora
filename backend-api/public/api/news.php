<?php
/** GET /api/news.php[?id=N | ?page=N][&lang=ar|en] — news detail or list. */
require __DIR__ . '/_boot.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $id = (int)$_GET['id'];
    api_serve(fn() => Api::newsDetail($id), 'news_detail_' . Lang::current() . '_' . $id, CACHE_TTL_NEWS, api_fail_text());
}

$page = max(1, (int)($_GET['page'] ?? 1));
api_serve(fn() => Api::newsPage($page), 'news_page_' . Lang::current() . '_' . $page, CACHE_TTL_NEWS, api_fail_text());
