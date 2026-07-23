<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\ChannelCatalog;
use TofiXTv\Core\Seo;
use TofiXTv\Core\Settings;
use TofiXTv\Core\View;

final class Channels
{
    public static function index(): void
    {
        Settings::trackHit('channels');
        $slides = ChannelCatalog::slides();
        $categories = ChannelCatalog::categories();
        $groups = [];
        foreach ($categories as $category) {
            $groups[(int)$category['id']] = ChannelCatalog::groupsForCategory((int)$category['id']);
        }
        $seo = (new Seo())
            ->title(t('channels.title'))
            ->description(t('channels.description'))
            ->canonical(path('channels'));
        $list = [];
        $position = 1;
        foreach ($categories as $category) {
            foreach ($groups[(int)$category['id']] ?? [] as $group) {
                $list[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => ChannelCatalog::label($group),
                    'url' => absolute_url(channel_group_url($category, $group)),
                ];
            }
        }
        if ($list) {
            $seo->addJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => t('channels.title'),
                'itemListElement' => $list,
            ]);
        }
        View::page('channels', ['slides' => $slides, 'categories' => $categories, 'groups' => $groups], $seo);
    }

    public static function group(string $categorySlug, string $groupSlug): void
    {
        $category = ChannelCatalog::categoryBySlug($categorySlug);
        $group = ChannelCatalog::groupBySlug($categorySlug, $groupSlug);
        if (!$category || !$group || empty($category['visible']) || empty($group['visible'])) View::notFound();
        $channels = ChannelCatalog::channelsForGroup((int)$group['id']);
        Settings::trackHit('channel_group', ChannelCatalog::label($group));
        $canonical = channel_group_url($category, $group);
        $seo = (new Seo())
            ->title(ChannelCatalog::label($group))
            ->description(ChannelCatalog::label($group, 'description') ?: t('channels.group_description', ['name' => ChannelCatalog::label($group)]))
            ->canonical($canonical)
            ->breadcrumbs([
                [t('nav.home'), path('/')],
                [t('nav.channels'), path('channels')],
                [ChannelCatalog::label($category), path('channels') . '#' . (string)$category['slug']],
                [ChannelCatalog::label($group), $canonical],
            ]);
        if ($channels) {
            $seo->addJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => ChannelCatalog::label($group),
                'itemListElement' => array_map(
                    static fn(array $channel, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => ChannelCatalog::label($channel),
                        'url' => absolute_url($canonical . '#channel-' . (int)$channel['id']),
                    ],
                    $channels,
                    array_keys($channels)
                ),
            ]);
        }
        View::page('channel-group', compact('category', 'group', 'channels'), $seo);
    }

    public static function play(int $id): void
    {
        $channel = ChannelCatalog::channelById($id);
        if (!$channel || ($channel['play_type'] ?? '') !== 'hls' || !preg_match('#^https?://#i', (string)($channel['play_value'] ?? ''))) {
            View::notFound();
        }
        $group = ChannelCatalog::groupForChannel($channel);
        $category = $group ? ChannelCatalog::categoryForGroup($group) : null;
        if (!$group || !$category || empty($group['visible']) || empty($category['visible'])) View::notFound();
        header('X-Robots-Tag: noindex, follow');
        $seo = (new Seo())
            ->title(t('channels.watch', ['name' => ChannelCatalog::label($channel)]))
            ->description(ChannelCatalog::label($channel, 'description'))
            ->canonical(channel_play_url($channel))
            ->breadcrumbs([
                [t('nav.channels'), path('channels')],
                [ChannelCatalog::label($group), channel_group_url($category, $group)],
                [ChannelCatalog::label($channel), channel_play_url($channel)],
            ]);
        View::page('channel-play', compact('channel', 'group', 'category'), $seo);
    }
}
