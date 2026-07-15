<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Api;
use TofiXTv\Core\ChannelLib;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\Streams;
use TofiXTv\Core\View;
use TofiXTv\Core\Yacine;

final class Watch
{
    public static function show(int $id): void
    {
        // External-only configs never render the internal player.
        if (Streams::mode($id) === 'external') {
            $url = Streams::externalUrl($id);
            if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
                View::redirect($url, 302);
            }
            View::notFound();
        }

        // Servers = admin per-match config + channels auto-pulled from the
        // library by the match's broadcasting channels. Yacine API links are
        // decrypted + proxied; plain .m3u8 play directly.
        $servers = self::buildServers($id);
        if (empty($servers)) View::notFound();

        Settings::trackHit('watch');
        $info = Api::matchInfo($id);
        $home = team_of($info, 'home');
        $away = team_of($info, 'away');
        $title = $info ? (team_name($home) . ' ' . t('match.vs') . ' ' . team_name($away)) : t('player.watch');

        $seo = (new Seo())
            ->title($title . ' — ' . t('player.watch'))
            ->description($title)
            ->canonical(path('watch/' . $id));
        // Watch pages should not be indexed (ephemeral live streams).
        header('X-Robots-Tag: noindex, nofollow');

        View::page('watch', [
            'id'         => $id,
            'm'          => $info,
            'home'       => $home,
            'away'       => $away,
            'title'      => $title,
            'servers'    => $servers,
            'refreshUrl' => path('watch/' . $id . '/src'),
            'ttl'        => Yacine::lastTtl(),
        ], $seo);
    }

    /**
     * Fresh servers as JSON — the player calls this to auto-reconnect a live
     * link (new Yacine token) on the SAME server without a page reload.
     */
    public static function source(int $id): void
    {
        $servers = self::buildServers($id);
        View::json([
            'ok'      => !empty($servers),
            'ttl'     => Yacine::lastTtl(),
            'sources' => array_map(static fn($s) => [
                'name' => $s['name'],
                'url'  => $s['url'],
                'type' => $s['type'],
                'drm'  => $s['drm'] ?? null,
            ], $servers),
        ]);
    }

    /** Combined, ready-to-play servers for a match. */
    private static function buildServers(int $id): array
    {
        $raw = array_merge(Streams::servers($id), ChannelLib::serversForMatch($id));
        // primaryOnly: one clean entry per channel (label = channel — commentator).
        return Yacine::expandServers($raw, true);
    }
}
