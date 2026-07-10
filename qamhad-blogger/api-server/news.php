<?php
/**
 * GET /news.php[?page=N][&lang=ar|en] — latest news list (+ featured hub on page 1).
 * For a single article use /news-details.php?id=N.
 */
require_once __DIR__ . '/_bootstrap.php';

use Qamhad\Core\Api;
use Qamhad\Core\Lang;

api_require_method(['GET']);

$page = max(1, api_int('page', 1));

api_serve(function () use ($page) {
    $list = Api::newsPage($page);
    if ($page === 1) {
        $hub = Api::allNewsPage();
        $list['featured']      = $hub['main'];
        $list['important']     = $hub['important'];
        $list['popular_teams'] = $hub['last_teams'];
    }
    return $list;
}, 'news_page_' . Lang::current() . '_' . $page, CACHE_TTL_NEWS, api_fail_text());
