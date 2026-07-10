<?php
/**
 * ============================================================================
 *  Router — maps a clean endpoint name to its file (single source of truth).
 * ============================================================================
 *  Used by index.php (front controller / pretty URLs) and proxy.php (single
 *  entry dispatcher). Only whitelisted names are ever included, so a crafted
 *  path can never reach an arbitrary file (directory-traversal safe).
 * ----------------------------------------------------------------------------
 */
declare(strict_types=1);

/** name => filename map. Aliases point several paths at one handler. */
function api_routes(): array
{
    return [
        'matches'      => 'matches.php',
        'match'        => 'match.php',
        'live'         => 'live.php',
        'news'         => 'news.php',
        'news-details' => 'news-details.php',
        'news_details' => 'news-details.php',
        'article'      => 'news-details.php',
        'search'       => 'search.php',
        'channels'     => 'channels.php',
        'channel'      => 'channel.php',
        'player'       => 'player.php',
        'team'         => 'team.php',
        'league'       => 'league.php',
        'leagues'      => 'league.php',
        'standings'    => 'standings.php',
        'topscorers'   => 'topscorers.php',
        'scorers'      => 'topscorers.php',
        'statistics'   => 'statistics.php',
        'stats'        => 'statistics.php',
        'videos'       => 'videos.php',
        'comments'     => 'comments.php',
        'formations'   => 'formations.php',
        'lineups'      => 'lineups.php',
        'events'       => 'events.php',
        'settings'     => 'settings.php',
        'media'        => 'media.php',
        'stream'       => 'stream.php',
        'sitemap'      => 'sitemap.php',
        'robots'       => 'robots.php',
        'health'       => 'health.php',
        'status'       => 'status.php',
        'cache'        => 'cache.php',
    ];
}

/** Resolve a name to a safe, existing endpoint file, or null. */
function api_resolve(string $name): ?string
{
    $name = strtolower(trim($name));
    $name = preg_replace('#\.php$#', '', $name) ?? '';
    $map  = api_routes();
    if (!isset($map[$name])) return null;
    $file = __DIR__ . '/' . $map[$name];
    return is_file($file) ? $file : null;
}

/** Include the endpoint for $name, or emit a JSON 404. */
function api_dispatch(string $name): void
{
    $file = api_resolve($name);
    if ($file === null) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'unknown_endpoint', 'endpoint' => $name], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require $file;
}
