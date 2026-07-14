<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * i18n. Arabic is the default language served at "/", English lives under "/en".
 */
final class Lang
{
    private static string $current = 'ar';
    private static array $dict = [];
    /** @var array<string,array> per-language dictionary memo — boot() is called
     *  inside loops (sitemaps, ping engine), so the dict file must load once. */
    private static array $dicts = [];

    public static function boot(string $lang): void
    {
        self::$current = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
        if (!isset(self::$dicts[self::$current])) {
            $file = APP_DIR . '/Lang/' . self::$current . '.php';
            self::$dicts[self::$current] = is_file($file) ? (require $file) : [];
        }
        self::$dict = self::$dicts[self::$current];
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function isRtl(): bool
    {
        return self::$current === 'ar';
    }

    public static function dir(): string
    {
        return self::isRtl() ? 'rtl' : 'ltr';
    }

    /** Translate a key; falls back to the key itself. */
    public static function t(string $key, array $vars = []): string
    {
        $s = self::$dict[$key] ?? $key;
        foreach ($vars as $k => $v) $s = str_replace('{' . $k . '}', (string)$v, $s);
        return $s;
    }

    public static function siteName(): string
    {
        return self::$current === 'ar' ? SITE_NAME_AR : SITE_NAME_EN;
    }

    public static function siteSlogan(): string
    {
        return self::$current === 'ar' ? SITE_SLOGAN_AR : SITE_SLOGAN_EN;
    }

    /** Path prefix for the active language ('' for ar, '/en' for en). */
    public static function prefix(): string
    {
        return self::$current === 'en' ? '/en' : '';
    }

    /**
     * Same page in the other language (header switcher / hreflang).
     *
     * Entity slugs are language-specific (Arabic titles vs English titles),
     * so carrying them across languages would force a cross-language
     * canonical redirect chain. Instead, link the bare-id form
     * (/league/894789) — the target language 301s once to its own
     * correctly-encoded canonical slug.
     */
    public static function alternatePath(string $currentPath): string
    {
        $bare = strtok($currentPath, '?');
        $bare = preg_replace('#^/en(/|$)#', '/', (string)$bare);
        $bare = '/' . ltrim((string)$bare, '/');
        if (preg_match('#^/(match|league|team|player|news)/[^/]*?(\d+)$#u', $bare, $m)) {
            $bare = '/' . $m[1] . '/' . $m[2];
        }
        return self::$current === 'en' ? $bare : ('/en' . ($bare === '/' ? '' : $bare));
    }
}
