<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Lang;
use TofiXTv\Core\Seo;
use TofiXTv\Core\View;
use TofiXTv\Core\Yacine;

/**
 * Yacine channel browsing:
 *   /yacine/{id}          → decrypt the channel and list its servers (HD/SD/…)
 *   /yacine/{id}/{index}  → open the player on the chosen server
 *
 * Mirrors the original Yacine site flow (channel → servers → watch).
 */
final class YacineWatch
{
    /** Servers list for one channel. */
    public static function channel(int $id): void
    {
        // /yacine/{id}?debug=1 → server-side diagnostics (why won't it play?)
        if (!empty($_GET['debug'])) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(Yacine::diagnose($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $sources = Yacine::resolveById($id);

        header('X-Robots-Tag: noindex, nofollow');
        $seo = (new Seo())
            ->title(t('player.watch'))
            ->canonical(path('yacine/' . $id));

        View::page('yacine-servers', [
            'id'      => $id,
            'sources' => $sources,
        ], $seo);
    }

    /**
     * Fresh source URLs as JSON (new stream token). The player calls this to
     * auto-reconnect the SAME server when a live link expires — no page reload,
     * no switching servers.
     */
    public static function source(int $id): void
    {
        $sources = Yacine::resolveById($id, true); // fresh = bypass cache
        View::json([
            'ok'      => !empty($sources),
            'ttl'     => Yacine::lastTtl(),
            'sources' => array_map(static fn($s) => [
                'name' => $s['label'],
                'url'  => $s['url'],
                'type' => $s['type'],
                'drm'  => $s['drm'],
            ], $sources),
        ]);
    }

    /** Player, starting on the chosen server (all servers stay switchable). */
    public static function play(int $id, int $index): void
    {
        $sources = Yacine::resolveById($id);
        if (empty($sources)) {
            View::notFound();
        }

        // Put the chosen server first so the player starts on it; keep the rest
        // for in-player switching.
        if ($index > 0 && $index < count($sources)) {
            $chosen = array_splice($sources, $index, 1);
            array_unshift($sources, ...$chosen);
        }

        header('X-Robots-Tag: noindex, nofollow');
        $seo = (new Seo())
            ->title(t('player.watch'))
            ->canonical(path('yacine/' . $id . '/' . $index));

        View::page('yacine-play', [
            'id'      => $id,
            'sources' => $sources,
            'ttl'     => Yacine::lastTtl(),
        ], $seo);
    }
}
