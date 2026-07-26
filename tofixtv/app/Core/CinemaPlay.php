<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * ALOKA Live — cinema (movies & series) playback method.
 * ---------------------------------------------------------------
 * Admin → إدارة الأفلام/المسلسلات chooses how the video plays:
 *   - 'embed'  : the normal in-page iframe player (default).
 *   - 'intent' : clicking watch / a server / an episode opens a server-picker
 *                dialog; each server launches the official app via an xmtv
 *                deep link. The embed URL gets a "#aloka=web" tag, is AES
 *                encrypted with the SAME key as the channels, and wrapped as:
 *                intent://<encrypted>#Intent;scheme=xmtv;package=com.aloka.live.app;end
 */
final class CinemaPlay
{
    private const SCHEME  = 'xmtv';
    private const PACKAGE = 'com.aloka.live.app';
    private const WEB_TAG = '#aloka=web';

    /** 'embed' (default) | 'intent' */
    public static function mode(): string
    {
        $s = Settings::get('cinema_playback', []);
        $m = is_array($s) ? (string)($s['mode'] ?? 'embed') : 'embed';
        return $m === 'intent' ? 'intent' : 'embed';
    }

    public static function isIntent(): bool
    {
        return self::mode() === 'intent';
    }

    public static function setMode(string $mode): void
    {
        Settings::merge('cinema_playback', [
            'mode'       => $mode === 'intent' ? 'intent' : 'embed',
            'updated_at' => date('c'),
        ]);
    }

    /**
     * Append the #aloka=web tag, encrypt with the channels AES key, and wrap
     * the ciphertext as an xmtv intent deep link. Returns '' on failure.
     */
    public static function intentFor(string $embedUrl): string
    {
        $embedUrl = trim($embedUrl);
        if ($embedUrl === '') return '';
        $enc = ChannelCatalog::encryptPlayValue($embedUrl . self::WEB_TAG);
        if ($enc === '') return '';
        return 'intent://' . $enc . '#Intent;scheme=' . self::SCHEME . ';package=' . self::PACKAGE . ';end';
    }

    /**
     * Ordered watch-server list for a set of embed URLs (alternative sources):
     * Videasy, VidSrc CC, VidSrc.
     * @param array{videasy?:string,vidsrccc?:string,vidsrc?:string} $embed
     * @return array<int,array{name:string,intent:string}>
     */
    public static function servers(array $embed): array
    {
        $order = [
            ['name' => 'Videasy',   'key' => 'videasy'],
            ['name' => 'VidSrc CC', 'key' => 'vidsrccc'],
            ['name' => 'VidSrc',    'key' => 'vidsrc'],
        ];
        $out = [];
        foreach ($order as $s) {
            $url    = (string)($embed[$s['key']] ?? '');
            $intent = $url !== '' ? self::intentFor($url) : '';
            if ($intent !== '') $out[] = ['name' => $s['name'], 'intent' => $intent];
        }
        return $out;
    }
}
