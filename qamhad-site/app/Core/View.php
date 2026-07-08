<?php
declare(strict_types=1);

namespace Qamhad\Core;

final class View
{
    /** Render a page view inside the base layout. */
    public static function page(string $view, array $data = [], ?Seo $seo = null): void
    {
        $seo = $seo ?? new Seo();
        $data['seo'] = $seo;
        extract($data, EXTR_SKIP);
        $contentView = APP_DIR . '/Views/pages/' . $view . '.php';
        require APP_DIR . '/Views/layout/base.php';
    }

    /** Render a partial and return the HTML. */
    public static function partial(string $name, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_DIR . '/Views/partials/' . $name . '.php';
        return (string)ob_get_clean();
    }

    public static function notFound(): void
    {
        http_response_code(404);
        $seo = (new Seo())->title(t('misc.notfound'));
        self::page('404', [], $seo);
        exit;
    }

    public static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect with an RFC-compliant Location header. Raw UTF-8 in
     * Location is undefined behaviour and gets mangled by proxies/CDNs
     * into double-encoded mojibake URLs — always emit ASCII.
     */
    public static function redirect(string $to, int $code = 301): void
    {
        if (preg_match('/[^\x20-\x7E]/', $to)) {
            $to = str_starts_with($to, 'http')
                ? absolute_url($to)
                : encode_path($to);
        }
        header('Location: ' . $to, true, $code);
        exit;
    }
}
