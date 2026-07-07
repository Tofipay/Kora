<?php
/**
 * 301 redirects for every legacy .php URL (old Gamhed Sport site).
 * Keeps existing search rankings while moving to the clean URL scheme.
 */
declare(strict_types=1);

function qamhad_legacy_redirect(string $path): void
{
    if (!str_contains($path, '.php') && $path !== '/index') return;

    $id   = (int)($_GET['id'] ?? 0);
    $slug = trim((string)($_GET['slug'] ?? ''));
    $page = (int)($_GET['page'] ?? 0);
    $date = trim((string)($_GET['date'] ?? ''));
    $q    = trim((string)($_GET['q'] ?? ($_GET['query'] ?? '')));

    $map = null;
    switch (true) {
        case $path === '/index.php' || $path === '/index':
            $map = '/';
            break;
        case $path === '/match.php':
            $map = $id ? "/match/{$id}" : '/matches';
            break;
        case $path === '/matches.php':
            $map = $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? "/matches/{$date}" : '/matches';
            break;
        case $path === '/news.php':
            if ($slug !== '')      $map = '/news/' . rawurlencode($slug);
            elseif ($id)           $map = "/news/{$id}";
            elseif ($page > 1)     $map = "/news/page/{$page}";
            else                   $map = '/news';
            break;
        case $path === '/news-details.php':
            $map = $id ? "/news/{$id}" : '/news';
            break;
        case $path === '/league.php':
            $map = $id ? "/league/{$id}" : '/leagues';
            break;
        case $path === '/team.php':
            $map = $id ? "/team/{$id}" : '/teams';
            break;
        case $path === '/player.php':
            $map = $id ? "/player/{$id}" : '/players';
            break;
        case $path === '/search.php':
            $map = '/search' . ($q !== '' ? '?q=' . rawurlencode($q) : '');
            break;
        case $path === '/about.php':   $map = '/about';   break;
        case $path === '/contact.php': $map = '/contact'; break;
        case $path === '/privacy.php': $map = '/privacy'; break;
        case $path === '/terms.php':   $map = '/terms';   break;
        case $path === '/sitemap.php': $map = '/sitemap.xml'; break;
        default:
            // Any other *.php → strip the extension
            $map = preg_replace('/\.php$/', '', $path);
            break;
    }

    if ($map !== null && $map !== $path) {
        header('Location: ' . $map, true, 301);
        exit;
    }
}
