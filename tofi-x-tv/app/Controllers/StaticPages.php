<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\Seo;
use TofiXTv\Core\View;

final class StaticPages
{
    public static function page(string $name): void
    {
        $titles = [
            'about'   => t('page.about.title'),
            'privacy' => t('page.privacy.title'),
            'terms'   => t('page.terms.title'),
            'contact' => t('page.contact.title'),
        ];
        $seo = (new Seo())
            ->title($titles[$name] ?? $name)
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [$titles[$name] ?? $name, path($name)],
            ]);
        View::page('static-' . $name, ['title' => $titles[$name] ?? $name], $seo);
    }

    public static function favorites(): void
    {
        $seo = (new Seo())->title(t('fav.title'));
        View::page('favorites', [], $seo);
    }

    public static function offline(): void
    {
        $seo = (new Seo())->title(t('misc.offline'));
        View::page('offline', [], $seo);
    }
}
