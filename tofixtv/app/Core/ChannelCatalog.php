<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * Standalone, admin-managed channel catalogue.
 *
 * This is intentionally separate from ChannelLib, AppChannels and Streams.
 * Those classes continue to power match playback exactly as before; this
 * catalogue only powers the new /channels section.
 */
final class ChannelCatalog
{
    private static ?array $data = null;

    /** AES-192 key used by the official Android player. */
    private const PLAY_CRYPTO_KEY = '6.o4XM7s~I|qZF+p9yZ0eOYi';

    private const DEFAULTS = [
        'config' => [
            'scheme'       => 'xmtv',
            'package'      => 'com.aloka.live.app',
            'download_url' => 'https://t.me/alokalive',
        ],
        'slides'     => [],
        'categories' => [],
        'groups'     => [],
        'channels'   => [],
    ];

    public static function data(): array
    {
        if (self::$data !== null) return self::$data;
        $saved = Settings::get('channel_catalog', []);
        if (!is_array($saved)) $saved = [];
        $data = array_replace_recursive(self::DEFAULTS, $saved);
        foreach (['slides', 'categories', 'groups', 'channels'] as $key) {
            if (!is_array($data[$key] ?? null)) $data[$key] = [];
        }
        return self::$data = $data;
    }

    public static function config(): array
    {
        return self::data()['config'];
    }

    /** @return array<int,array> */
    public static function slides(bool $visibleOnly = true): array
    {
        $items = array_values(array_filter(self::data()['slides'], static fn($row) =>
            is_array($row) && (!$visibleOnly || !empty($row['visible']))
        ));
        usort($items, self::sorter(...));
        return $items;
    }

    /** @return array<int,array> */
    public static function categories(bool $visibleOnly = true): array
    {
        $items = array_values(array_filter(self::data()['categories'], static fn($row) =>
            is_array($row) && (!$visibleOnly || !empty($row['visible']))
        ));
        usort($items, self::sorter(...));
        return $items;
    }

    /** @return array<int,array> */
    public static function groups(bool $visibleOnly = true): array
    {
        $items = array_values(array_filter(self::data()['groups'], static fn($row) =>
            is_array($row) && (!$visibleOnly || !empty($row['visible']))
        ));
        usort($items, self::sorter(...));
        return $items;
    }

    /** @return array<int,array> */
    public static function channels(bool $visibleOnly = true): array
    {
        $items = array_values(array_filter(self::data()['channels'], static fn($row) =>
            is_array($row) && (!$visibleOnly || !empty($row['visible']))
        ));
        usort($items, self::sorter(...));
        return $items;
    }

    public static function categoryBySlug(string $slug): ?array
    {
        foreach (self::categories(false) as $row) {
            if ((string)($row['slug'] ?? '') === $slug) return $row;
        }
        return null;
    }

    public static function categoryById(int $id): ?array
    {
        foreach (self::categories(false) as $row) {
            if ((int)($row['id'] ?? 0) === $id) return $row;
        }
        return null;
    }

    public static function groupById(int $id): ?array
    {
        foreach (self::groups(false) as $row) {
            if ((int)($row['id'] ?? 0) === $id) return $row;
        }
        return null;
    }

    public static function groupBySlug(string $categorySlug, string $groupSlug): ?array
    {
        $category = self::categoryBySlug($categorySlug);
        if (!$category) return null;
        foreach (self::groups(false) as $row) {
            if ((int)($row['category_id'] ?? 0) === (int)$category['id']
                && (string)($row['slug'] ?? '') === $groupSlug) return $row;
        }
        return null;
    }

    public static function channelById(int $id, bool $visibleOnly = true): ?array
    {
        foreach (self::channels(false) as $row) {
            if ((int)($row['id'] ?? 0) === $id && (!$visibleOnly || !empty($row['visible']))) return $row;
        }
        return null;
    }

    /** @return array<int,array> */
    public static function groupsForCategory(int $categoryId, bool $visibleOnly = true): array
    {
        return array_values(array_filter(self::groups($visibleOnly), static fn($row) =>
            (int)($row['category_id'] ?? 0) === $categoryId
        ));
    }

    /** @return array<int,array> */
    public static function channelsForGroup(int $groupId, bool $visibleOnly = true): array
    {
        return array_values(array_filter(self::channels($visibleOnly), static fn($row) =>
            (int)($row['group_id'] ?? 0) === $groupId
        ));
    }

    public static function categoryForGroup(array $group): ?array
    {
        $id = (int)($group['category_id'] ?? 0);
        foreach (self::categories(false) as $row) if ((int)($row['id'] ?? 0) === $id) return $row;
        return null;
    }

    public static function groupForChannel(array $channel): ?array
    {
        $id = (int)($channel['group_id'] ?? 0);
        foreach (self::groups(false) as $row) if ((int)($row['id'] ?? 0) === $id) return $row;
        return null;
    }

    public static function label(array $row, string $field = 'name'): string
    {
        $lang = Lang::current();
        $value = trim((string)($row[$field . '_' . $lang] ?? ''));
        if ($value !== '') return $value;
        $other = $lang === 'ar' ? 'en' : 'ar';
        return trim((string)($row[$field . '_' . $other] ?? $row[$field] ?? ''));
    }

    public static function save(array $config, array $categories, array $groups, array $channels, ?array $slides = null): bool
    {
        $cleanConfig = [
            // These defaults deliberately preserve the existing official app
            // identity. They are fixed even if a form is manually tampered with.
            'scheme'       => 'xmtv',
            'package'      => 'com.aloka.live.app',
            'download_url' => self::safeHttp((string)($config['download_url'] ?? '')) ?: 'https://t.me/alokalive',
        ];

        $cats = self::cleanRows($categories, 'category');
        $catIds = array_fill_keys(array_map(static fn($r) => (int)$r['id'], $cats), true);

        $grps = [];
        foreach (self::cleanRows($groups, 'group') as $row) {
            if (!isset($catIds[(int)($row['category_id'] ?? 0)])) continue;
            $grps[] = $row;
        }
        $groupIds = array_fill_keys(array_map(static fn($r) => (int)$r['id'], $grps), true);

        $chs = [];
        foreach (self::cleanRows($channels, 'channel') as $row) {
            if (!isset($groupIds[(int)($row['group_id'] ?? 0)])) continue;
            $chs[] = $row;
        }

        $savedSlides = $slides ?? self::data()['slides'];
        self::$data = null;
        return Settings::set('channel_catalog', [
            'config'     => $cleanConfig,
            'slides'     => $savedSlides,
            'categories' => $cats,
            'groups'     => $grps,
            'channels'   => $chs,
        ]);
    }

    /** Add or edit one banner in the channels-page slider. */
    public static function saveSlide(array $input): int
    {
        $data = self::data();
        $id = (int)($input['id'] ?? 0);
        $index = self::rowIndex($data['slides'], $id);
        $old = $index === null ? [] : $data['slides'][$index];
        if ($index === null) $id = self::nextId($data['slides']);

        $image = self::safeImage((string)($input['image'] ?? $old['image'] ?? ''));
        if ($image === '') return 0;

        $targetType = (string)($input['target_type'] ?? $old['target_type'] ?? 'none');
        if (!in_array($targetType, ['none', 'group', 'url'], true)) $targetType = 'none';
        $groupId = (int)($input['group_id'] ?? $old['group_id'] ?? 0);
        $targetUrl = self::safeHttp((string)($input['target_url'] ?? $old['target_url'] ?? ''));
        if ($targetType === 'group' && !self::groupById($groupId)) $targetType = 'none';
        if ($targetType === 'url' && $targetUrl === '') $targetType = 'none';

        $row = array_replace($old, [
            'id' => $id,
            'image' => $image,
            'title_ar' => mb_substr(trim((string)($input['title_ar'] ?? '')), 0, 140),
            'title_en' => mb_substr(trim((string)($input['title_en'] ?? '')), 0, 140),
            'description_ar' => mb_substr(trim((string)($input['description_ar'] ?? '')), 0, 280),
            'description_en' => mb_substr(trim((string)($input['description_en'] ?? '')), 0, 280),
            'button_ar' => mb_substr(trim((string)($input['button_ar'] ?? '')), 0, 50),
            'button_en' => mb_substr(trim((string)($input['button_en'] ?? '')), 0, 50),
            'target_type' => $targetType,
            'group_id' => $targetType === 'group' ? $groupId : 0,
            'target_url' => $targetType === 'url' ? $targetUrl : '',
            'visible' => array_key_exists('visible', $old) ? !empty($old['visible']) : true,
            'order' => (int)($input['order'] ?? $old['order'] ?? self::nextOrder($data['slides'])),
        ]);
        if ($index === null) $data['slides'][] = $row; else $data['slides'][$index] = $row;
        self::persist($data);
        return $id;
    }

    public static function deleteSlide(int $id): bool
    {
        $data = self::data();
        if (self::rowIndex($data['slides'], $id) === null) return false;
        $data['slides'] = array_values(array_filter(
            $data['slides'],
            static fn($row) => (int)($row['id'] ?? 0) !== $id
        ));
        return self::persist($data);
    }

    /** Empty means the slide is visual-only and intentionally not clickable. */
    public static function slideUrl(array $slide): string
    {
        $type = (string)($slide['target_type'] ?? 'none');
        if ($type === 'url') return self::safeHttp((string)($slide['target_url'] ?? ''));
        if ($type !== 'group') return '';
        $group = self::groupById((int)($slide['group_id'] ?? 0));
        $category = $group ? self::categoryForGroup($group) : null;
        return ($group && $category) ? channel_group_url($category, $group) : '';
    }

    /** Add or edit one category without exposing the rest of the catalogue. */
    public static function saveCategory(array $input): int
    {
        $data = self::data();
        $id = (int)($input['id'] ?? 0);
        $index = self::rowIndex($data['categories'], $id);
        $old = $index === null ? [] : $data['categories'][$index];
        $nameAr = mb_substr(trim((string)($input['name_ar'] ?? '')), 0, 120);
        $nameEn = mb_substr(trim((string)($input['name_en'] ?? '')), 0, 120);
        if ($nameAr === '' && $nameEn === '') return 0;
        if ($index === null) $id = self::nextId($data['categories']);
        $row = array_replace($old, [
            'id' => $id,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'slug' => (string)($old['slug'] ?? slugify($nameEn ?: $nameAr, 'category-' . $id)),
            // Categories intentionally have no image in the new manager.
            'visible' => array_key_exists('visible', $old) ? !empty($old['visible']) : true,
            'order' => (int)($old['order'] ?? self::nextOrder($data['categories'])),
        ]);
        if ($index === null) $data['categories'][] = $row; else $data['categories'][$index] = $row;
        self::persist($data);
        return $id;
    }

    /** Delete a category and its descendants after the admin confirmation. */
    public static function deleteCategory(int $id): bool
    {
        $data = self::data();
        if (self::rowIndex($data['categories'], $id) === null) return false;
        $groupIds = [];
        foreach ($data['groups'] as $row) {
            if ((int)($row['category_id'] ?? 0) === $id) $groupIds[(int)$row['id']] = true;
        }
        $data['categories'] = array_values(array_filter($data['categories'], static fn($row) => (int)($row['id'] ?? 0) !== $id));
        $data['groups'] = array_values(array_filter($data['groups'], static fn($row) => (int)($row['category_id'] ?? 0) !== $id));
        $data['channels'] = array_values(array_filter($data['channels'], static fn($row) => !isset($groupIds[(int)($row['group_id'] ?? 0)])));
        return self::persist($data);
    }

    /** Add or edit a group inside one category. */
    public static function saveGroup(int $categoryId, array $input): int
    {
        if (!self::categoryById($categoryId)) return 0;
        $data = self::data();
        $id = (int)($input['id'] ?? 0);
        $index = self::rowIndex($data['groups'], $id);
        $old = $index === null ? [] : $data['groups'][$index];
        if ($index !== null && (int)($old['category_id'] ?? 0) !== $categoryId) return 0;
        $nameAr = mb_substr(trim((string)($input['name_ar'] ?? '')), 0, 120);
        $nameEn = mb_substr(trim((string)($input['name_en'] ?? '')), 0, 120);
        if ($nameAr === '' && $nameEn === '') return 0;
        if ($index === null) $id = self::nextId($data['groups']);
        $image = self::safeImage((string)($input['image'] ?? $old['image'] ?? ''));
        $row = array_replace($old, [
            'id' => $id,
            'category_id' => $categoryId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'slug' => (string)($old['slug'] ?? slugify($nameEn ?: $nameAr, 'group-' . $id)),
            'image' => $image,
            'visible' => array_key_exists('visible', $old) ? !empty($old['visible']) : true,
            'order' => (int)($input['order'] ?? $old['order'] ?? self::nextOrder($data['groups'])),
        ]);
        if ($index === null) $data['groups'][] = $row; else $data['groups'][$index] = $row;
        self::persist($data);
        return $id;
    }

    /** Delete a group and all channels inside it after confirmation. */
    public static function deleteGroup(int $id): bool
    {
        $data = self::data();
        if (self::rowIndex($data['groups'], $id) === null) return false;
        $data['groups'] = array_values(array_filter($data['groups'], static fn($row) => (int)($row['id'] ?? 0) !== $id));
        $data['channels'] = array_values(array_filter($data['channels'], static fn($row) => (int)($row['group_id'] ?? 0) !== $id));
        return self::persist($data);
    }

    /** Add or edit one app channel. Every catalogue channel is favourite-ready. */
    public static function saveChannel(int $groupId, array $input): int
    {
        if (!self::groupById($groupId)) return 0;
        $data = self::data();
        $id = (int)($input['id'] ?? 0);
        $index = self::rowIndex($data['channels'], $id);
        $old = $index === null ? [] : $data['channels'][$index];
        if ($index !== null && (int)($old['group_id'] ?? 0) !== $groupId) return 0;
        $nameAr = mb_substr(trim((string)($input['name_ar'] ?? '')), 0, 120);
        $nameEn = mb_substr(trim((string)($input['name_en'] ?? '')), 0, 120);
        $playValue = trim((string)($input['play_value'] ?? $old['play_value'] ?? ''));
        if (($nameAr === '' && $nameEn === '') || $playValue === '') return 0;
        if ($index === null) $id = self::nextId($data['channels']);
        $logo = self::safeImage((string)($input['logo'] ?? $old['logo'] ?? ''));
        $playEncrypted = !empty($input['play_encrypted']) || self::isEncryptedPlayValue($playValue);
        $row = array_replace($old, [
            'id' => $id,
            'group_id' => $groupId,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'logo' => $logo,
            'play_value' => $playValue,
            'play_encrypted' => $playEncrypted,
            // This catalogue is opened by the official Android application.
            'play_type' => 'intent',
            'status' => 'live',
            'featured' => false,
            'visible' => array_key_exists('visible', $old) ? !empty($old['visible']) : true,
            'order' => (int)($input['order'] ?? $old['order'] ?? self::nextOrder($data['channels'])),
        ]);
        if ($index === null) $data['channels'][] = $row; else $data['channels'][$index] = $row;
        self::persist($data);
        return $id;
    }

    /**
     * Match CryptoJS AES.encrypt(text, Utf8.parse(key), ECB + PKCS7), returning
     * only the raw ciphertext encoded as Base64.
     */
    public static function encryptPlayValue(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '' || !function_exists('openssl_encrypt')) return '';
        $raw = openssl_encrypt($plain, 'aes-192-ecb', self::PLAY_CRYPTO_KEY, OPENSSL_RAW_DATA);
        return is_string($raw) && $raw !== '' ? base64_encode($raw) : '';
    }

    /** Return null when the value is not valid ciphertext for the fixed key. */
    public static function decryptPlayValue(string $ciphertext): ?string
    {
        $ciphertext = trim($ciphertext);
        if ($ciphertext === '' || !function_exists('openssl_decrypt')) return null;
        $raw = base64_decode($ciphertext, true);
        if (!is_string($raw) || $raw === '' || strlen($raw) % 16 !== 0) return null;
        $plain = openssl_decrypt($raw, 'aes-192-ecb', self::PLAY_CRYPTO_KEY, OPENSSL_RAW_DATA);
        if (!is_string($plain) || $plain === '' || preg_match('//u', $plain) !== 1) return null;
        return $plain;
    }

    public static function isEncryptedPlayValue(string $value): bool
    {
        return self::decryptPlayValue($value) !== null;
    }

    public static function deleteChannel(int $id): bool
    {
        $data = self::data();
        if (self::rowIndex($data['channels'], $id) === null) return false;
        $data['channels'] = array_values(array_filter($data['channels'], static fn($row) => (int)($row['id'] ?? 0) !== $id));
        return self::persist($data);
    }

    private static function cleanRows(array $rows, string $type): array
    {
        $out = []; $seen = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) continue;
            $id = max(1, (int)($row['id'] ?? ($i + 1)));
            while (isset($seen[$id])) $id++;
            $seen[$id] = true;
            $nameAr = trim((string)($row['name_ar'] ?? ''));
            $nameEn = trim((string)($row['name_en'] ?? ''));
            if ($nameAr === '' && $nameEn === '') continue;
            $slugSource = trim((string)($row['slug'] ?? '')) ?: ($nameEn ?: $nameAr);
            $base = [
                'id'      => $id,
                'name_ar' => mb_substr($nameAr, 0, 120),
                'name_en' => mb_substr($nameEn, 0, 120),
                'slug'    => slugify($slugSource, $type . '-' . $id),
                'image'   => self::safeImage((string)($row['image'] ?? $row['logo'] ?? '')),
                'visible' => !empty($row['visible']),
                'order'   => (int)($row['order'] ?? ($i + 1)),
            ];
            if ($type === 'group') {
                $base['category_id'] = (int)($row['category_id'] ?? 0);
                $base['description_ar'] = mb_substr(trim((string)($row['description_ar'] ?? '')), 0, 500);
                $base['description_en'] = mb_substr(trim((string)($row['description_en'] ?? '')), 0, 500);
            } elseif ($type === 'channel') {
                $base['group_id'] = (int)($row['group_id'] ?? 0);
                $base['logo'] = $base['image'];
                unset($base['image']);
                $base['description_ar'] = mb_substr(trim((string)($row['description_ar'] ?? '')), 0, 500);
                $base['description_en'] = mb_substr(trim((string)($row['description_en'] ?? '')), 0, 500);
                $base['play_value'] = trim((string)($row['play_value'] ?? ''));
                $base['play_encrypted'] = !empty($row['play_encrypted'])
                    || self::isEncryptedPlayValue($base['play_value']);
                $base['play_type'] = in_array(($row['play_type'] ?? ''), ['intent', 'hls', 'external'], true)
                    ? (string)$row['play_type'] : 'intent';
                $base['quality'] = in_array(($row['quality'] ?? ''), ['', 'HD', 'FHD', '4K'], true)
                    ? (string)$row['quality'] : '';
                $base['status'] = in_array(($row['status'] ?? ''), ['live', 'offline', 'soon'], true)
                    ? (string)$row['status'] : 'live';
                $base['access'] = in_array(($row['access'] ?? ''), ['free', 'encrypted'], true)
                    ? (string)$row['access'] : 'free';
                $base['featured'] = !empty($row['featured']);
            }
            $out[] = $base;
        }
        return $out;
    }

    private static function safeHttp(string $url): string
    {
        $url = trim($url);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    private static function safeImage(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('#^https?://#i', $value)) return $value;
        return str_starts_with($value, '/assets/uploads/') ? $value : '';
    }

    private static function sorter(array $a, array $b): int
    {
        return ((int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0))
            ?: ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
    }

    private static function rowIndex(array $rows, int $id): ?int
    {
        if ($id < 1) return null;
        foreach ($rows as $i => $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) === $id) return (int)$i;
        }
        return null;
    }

    private static function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) $max = max($max, (int)($row['id'] ?? 0));
        return $max + 1;
    }

    private static function nextOrder(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) $max = max($max, (int)($row['order'] ?? 0));
        return $max + 1;
    }

    private static function persist(array $data): bool
    {
        self::$data = $data;
        return Settings::set('channel_catalog', $data);
    }
}
