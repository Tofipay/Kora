<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/** Local editorial news and non-destructive overrides for upstream articles. */
final class EditorialNews
{
    private static ?array $data = null;

    private static function data(): array
    {
        if (self::$data !== null) return self::$data;
        $data = Settings::get('editorial_news', []);
        if (!is_array($data)) $data = [];
        $data += [
            'next_id' => 900000001,
            'default_author_ar' => 'فريق ALOKA Live',
            'default_author_en' => 'ALOKA Live Team',
            'items' => [],
            'overrides' => [],
        ];
        if (!is_array($data['items'])) $data['items'] = [];
        if (!is_array($data['overrides'])) $data['overrides'] = [];
        return self::$data = $data;
    }

    public static function defaultAuthor(?string $lang = null): string
    {
        $lang = $lang ?: Lang::current();
        $data = self::data();
        return trim((string)($data['default_author_' . $lang] ?? ''))
            ?: ($lang === 'ar' ? 'فريق ALOKA Live' : 'ALOKA Live Team');
    }

    public static function settings(): array
    {
        $d = self::data();
        return [
            'default_author_ar' => $d['default_author_ar'],
            'default_author_en' => $d['default_author_en'],
        ];
    }

    public static function saveSettings(string $ar, string $en): bool
    {
        $d = self::data();
        $d['default_author_ar'] = mb_substr(trim($ar), 0, 120) ?: 'فريق ALOKA Live';
        $d['default_author_en'] = mb_substr(trim($en), 0, 120) ?: 'ALOKA Live Team';
        return self::persist($d);
    }

    /** @return array<int,array> */
    public static function adminItems(): array
    {
        $items = array_values(array_filter(self::data()['items'], 'is_array'));
        usort($items, static fn($a, $b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? '')));
        return $items;
    }

    public static function findLocal(int $id, bool $publishedOnly = true): ?array
    {
        foreach (self::data()['items'] as $row) {
            if (!is_array($row) || (int)($row['id'] ?? 0) !== $id) continue;
            if ($publishedOnly && !self::isPublished($row)) return null;
            return self::toApi($row);
        }
        return null;
    }

    public static function adminLocal(int $id): ?array
    {
        foreach (self::data()['items'] as $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) === $id) return $row;
        }
        return null;
    }

    public static function saveLocal(array $input): int
    {
        $d = self::data();
        $id = (int)($input['id'] ?? 0);
        $index = null;
        foreach ($d['items'] as $i => $row) {
            if ((int)($row['id'] ?? 0) === $id) { $index = $i; break; }
        }
        if ($id < 900000001 || $index === null) {
            $id = max(900000001, (int)$d['next_id']);
            $d['next_id'] = $id + 1;
            $index = null;
        }
        $old = $index !== null ? $d['items'][$index] : [];
        $row = self::cleanInput($input, $old);
        $row['id'] = $id;
        $row['created_at'] = (string)($old['created_at'] ?? date('c'));
        $row['updated_at'] = date('c');
        if ($index === null) $d['items'][] = $row; else $d['items'][$index] = $row;
        self::persist($d);
        return $id;
    }

    public static function deleteLocal(int $id): bool
    {
        $d = self::data();
        $before = count($d['items']);
        $d['items'] = array_values(array_filter($d['items'], static fn($row) => (int)($row['id'] ?? 0) !== $id));
        return count($d['items']) !== $before && self::persist($d);
    }

    public static function saveOverride(int $id, array $input): bool
    {
        if ($id < 1 || $id >= 900000001) return false;
        $d = self::data();
        $old = is_array($d['overrides'][(string)$id] ?? null) ? $d['overrides'][(string)$id] : [];
        $row = self::cleanInput($input, $old);
        $row['hidden'] = (($input['status'] ?? '') === 'hidden') || !empty($input['hidden']);
        $row['updated_at'] = date('c');
        $d['overrides'][(string)$id] = $row;
        return self::persist($d);
    }

    public static function setUpstreamHidden(int $id, bool $hidden): bool
    {
        $d = self::data();
        $row = is_array($d['overrides'][(string)$id] ?? null) ? $d['overrides'][(string)$id] : [];
        $row['hidden'] = $hidden;
        $row['updated_at'] = date('c');
        $d['overrides'][(string)$id] = $row;
        return self::persist($d);
    }

    public static function overrideFor(int $id): array
    {
        $row = self::data()['overrides'][(string)$id] ?? [];
        return is_array($row) ? $row : [];
    }

    public static function applyUpstreamItem(array $item): array
    {
        $id = (int)($item['id'] ?? 0);
        if ($id < 1) return $item;
        $ov = self::overrideFor($id);
        if (!empty($ov['hidden'])) return [];
        $lang = Lang::current();
        $map = [
            'title'       => 'title_' . $lang,
            'news_desc'   => 'description_' . $lang,
            'full_news'   => 'content_' . $lang,
            'author_name' => 'author_' . $lang,
            'category'    => 'category_' . $lang,
        ];
        foreach ($map as $dest => $src) {
            if (trim((string)($ov[$src] ?? '')) !== '') $item[$dest] = $ov[$src];
        }
        if (trim((string)($ov['image'] ?? '')) !== '') $item['editorial_image'] = $ov['image'];
        if (trim((string)($ov['published_at'] ?? '')) !== '') $item['created_at'] = $ov['published_at'];
        if (!empty($ov['updated_at'])) $item['updated_at'] = $ov['updated_at'];
        return $item;
    }

    /** @return array<int,array> */
    public static function applyUpstreamList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $item = self::applyUpstreamItem($item);
            if ($item) $out[] = $item;
        }
        return $out;
    }

    /** Add local published items to page one without altering upstream fetching. */
    public static function mergePage(array $items, int $page): array
    {
        $items = self::applyUpstreamList($items);
        if ($page !== 1) return $items;
        foreach (self::data()['items'] as $row) {
            if (is_array($row) && self::isPublished($row)) $items[] = self::toApi($row);
        }
        $dedupe = [];
        foreach ($items as $item) $dedupe[(string)($item['id'] ?? spl_object_id((object)$item))] = $item;
        $items = array_values($dedupe);
        usort($items, static fn($a, $b) => (to_ts($b['created_at'] ?? null) ?: 0) <=> (to_ts($a['created_at'] ?? null) ?: 0));
        return $items;
    }

    private static function isPublished(array $row): bool
    {
        if (($row['status'] ?? 'draft') !== 'published') return false;
        $ts = to_ts($row['published_at'] ?? null);
        return !$ts || $ts <= time();
    }

    private static function toApi(array $row): array
    {
        $lang = Lang::current(); $other = $lang === 'ar' ? 'en' : 'ar';
        $pick = static function (string $base) use ($row, $lang, $other): string {
            return trim((string)($row[$base . '_' . $lang] ?? '')) ?: trim((string)($row[$base . '_' . $other] ?? ''));
        };
        $title = $pick('title');
        return [
            'id'              => (int)$row['id'],
            'title'           => $title,
            'slug'            => slugify($title, 'news-' . (int)$row['id']),
            'news_desc'       => $pick('description'),
            'full_news'       => $pick('content'),
            'author_name'     => $pick('author') ?: self::defaultAuthor($lang),
            'category'        => $pick('category'),
            'created_at'      => (string)($row['published_at'] ?? $row['created_at'] ?? date('c')),
            'updated_at'      => (string)($row['updated_at'] ?? $row['published_at'] ?? date('c')),
            'editorial_image' => (string)($row['image'] ?? ''),
            '_editorial'      => true,
        ];
    }

    private static function cleanInput(array $input, array $old = []): array
    {
        $out = [];
        foreach (['title', 'description', 'content', 'author', 'category'] as $field) {
            foreach (['ar', 'en'] as $lang) {
                $key = $field . '_' . $lang;
                $value = trim((string)($input[$key] ?? $old[$key] ?? ''));
                $out[$key] = $field === 'content' ? $value : mb_substr($value, 0, $field === 'description' ? 1000 : 255);
            }
        }
        $image = trim((string)($input['image'] ?? $old['image'] ?? ''));
        $out['image'] = (preg_match('#^https?://#i', $image) || str_starts_with($image, '/assets/uploads/')) ? $image : '';
        $out['status'] = in_array(($input['status'] ?? ''), ['draft', 'published', 'hidden'], true)
            ? (string)$input['status'] : (string)($old['status'] ?? 'draft');
        $pub = trim((string)($input['published_at'] ?? $old['published_at'] ?? ''));
        $out['published_at'] = $pub !== '' ? date('c', strtotime($pub) ?: time()) : date('c');
        return $out;
    }

    private static function persist(array $data): bool
    {
        self::$data = $data;
        return Settings::set('editorial_news', $data);
    }
}
