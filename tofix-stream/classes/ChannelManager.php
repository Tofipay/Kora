<?php

/**
 * classes/ChannelManager.php
 * -----------------------------------------------------------------------------
 * خدمة (Service) تدير دورة حياة القنوات فوق طبقة JsonStore:
 *   - CRUD كامل (إنشاء/قراءة/تحديث/حذف).
 *   - Duplicate (تكرار قناة).
 *   - بناء رابط إعادة البثّ الجديد الخاص بالسيرفر.
 *
 * تطبّق مبدأ المسؤولية الواحدة (SRP): كل ما يخصّ القنوات في مكان واحد.
 *
 * @package ToFiXStream\Service
 */

declare(strict_types=1);

namespace ToFiXStream;

final class ChannelManager
{
    private JsonStore $store;

    public function __construct(?JsonStore $store = null)
    {
        $this->store = $store ?? new JsonStore(
            Config::get('storage.channels_file'),
            (bool) Config::get('storage.use_file_lock', true)
        );
    }

    /**
     * إرجاع كل القنوات مع إثراء كل قناة برابط إعادة البثّ.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(): array
    {
        return array_map(
            fn (array $row): array => $this->decorate($row),
            $this->store->all()
        );
    }

    /**
     * إيجاد قناة واحدة مع الإثراء.
     *
     * @return array<string,mixed>|null
     */
    public function get(string $id): ?array
    {
        $row = $this->store->find($id);
        return $row ? $this->decorate($row) : null;
    }

    /**
     * إنشاء قناة جديدة بعد التحقّق من الصحّة.
     *
     * @param array<string,mixed> $data
     * @return array{ok:bool,errors?:array<int,string>,channel?:array<string,mixed>}
     */
    public function create(array $data): array
    {
        $channel = Channel::fromArray($data);
        $errors = $channel->validate();
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $saved = $this->store->insert($channel->toArray());
        Logger::info('تم إنشاء قناة جديدة', ['id' => $saved['id'], 'name' => $saved['name']]);
        return ['ok' => true, 'channel' => $this->decorate($saved)];
    }

    /**
     * تحديث قناة.
     *
     * @param array<string,mixed> $data
     * @return array{ok:bool,errors?:array<int,string>,channel?:array<string,mixed>}
     */
    public function update(string $id, array $data): array
    {
        $existing = $this->store->find($id);
        if (!$existing) {
            return ['ok' => false, 'errors' => ['القناة غير موجودة.']];
        }

        // دمج الحقول الجديدة فوق القديمة ثم التحقّق.
        $merged = Channel::fromArray(array_merge($existing, $data, ['id' => $id]));
        $errors = $merged->validate();
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $updated = $this->store->update($id, $merged->toArray());
        Logger::info('تم تحديث قناة', ['id' => $id]);
        return ['ok' => true, 'channel' => $this->decorate($updated ?? $existing)];
    }

    /**
     * حذف قناة.
     */
    public function delete(string $id): bool
    {
        $ok = $this->store->delete($id);
        if ($ok) {
            Logger::info('تم حذف قناة', ['id' => $id]);
        }
        return $ok;
    }

    /**
     * تكرار قناة موجودة باسم جديد.
     *
     * @return array<string,mixed>|null القناة المكرّرة أو null.
     */
    public function duplicate(string $id): ?array
    {
        $existing = $this->store->find($id);
        if (!$existing) {
            return null;
        }
        unset($existing['id'], $existing['created_at'], $existing['updated_at']);
        $existing['name'] = ($existing['name'] ?? 'Channel') . ' (Copy)';
        $saved = $this->store->insert($existing);
        return $this->decorate($saved);
    }

    /**
     * تحديث مقاييس المراقبة الحيّة لقناة (يُستدعى من StreamMonitor).
     *
     * @param array<string,mixed> $metrics
     */
    public function updateMetrics(string $id, array $metrics): void
    {
        $this->store->update($id, ['metrics' => $metrics]);
    }

    /**
     * إثراء سجلّ القناة بروابط إعادة البثّ وروابط المشغّل.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorate(array $row): array
    {
        $base = Config::baseUrl();
        $id = $row['id'] ?? '';

        // الرابط الجديد الذي يظهر للمستخدم بدل الرابط الأصلي.
        $row['playback'] = [
            // ★ الرابط النظيف الجاهز — ينتهي بـ .m3u8 ويعمل في أي مشغّل/تطبيق IPTV.
            //   مثال: https://test.tofi-xtv.com/stream/ID/index.m3u8
            'hls'     => "{$base}/stream/{$id}/index.m3u8",
            // الرابط المباشر للبروكسي (احتياطي إن لم يكن إعادة الكتابة مفعّلة).
            'hls_raw' => "{$base}/proxy/index.php?channel={$id}",
            // رابط صفحة المشغّل الجاهزة.
            'player'  => "{$base}/public/player.php?channel={$id}",
            // رابط embed للتضمين في المواقع الأخرى.
            'embed'   => "{$base}/public/embed.php?channel={$id}",
        ];

        return $row;
    }
}
