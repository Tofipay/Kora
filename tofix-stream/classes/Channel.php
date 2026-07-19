<?php

/**
 * classes/Channel.php
 * -----------------------------------------------------------------------------
 * كيان (Entity) يمثّل قناة بثّ واحدة. مسؤول عن:
 *   - تعريف الحقول والقيم الافتراضية.
 *   - التحقّق من صحّة المدخلات (Validation).
 *   - التحويل من/إلى مصفوفة للتخزين في JSON.
 *
 * @package ToFiXStream\Model
 */

declare(strict_types=1);

namespace ToFiXStream;

final class Channel
{
    /** أنواع مصادر البثّ المدعومة. */
    public const SOURCE_TYPES = ['m3u8', 'mpd', 'mp4', 'rtmp', 'udp', 'http', 'https'];

    /** حالات القناة. */
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_OFFLINE  = 'offline';

    /** أوضاع إعادة البث. */
    public const MODE_PROXY  = 'proxy';   // إعادة كتابة المانيفست فقط (خفيف)
    public const MODE_FFMPEG = 'ffmpeg';  // إعادة ترميز/نسخ عبر FFmpeg

    /** مواضع العلامة المائية داخل الفيديو. */
    public const WM_POSITIONS = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(private array $attributes = [])
    {
    }

    /**
     * بناء كيان قناة من مصفوفة قادمة من طلب/تخزين مع تطبيق القيم الافتراضية.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = [
            'id'            => $data['id'] ?? null,
            'name'          => trim((string) ($data['name'] ?? '')),
            'logo'          => trim((string) ($data['logo'] ?? '')),
            'category'      => trim((string) ($data['category'] ?? 'General')),
            'description'   => trim((string) ($data['description'] ?? '')),
            'status'        => $data['status'] ?? self::STATUS_ACTIVE,
            'country'       => strtoupper(trim((string) ($data['country'] ?? ''))),
            'quality'       => $data['quality'] ?? 'source',
            'audio_lang'    => trim((string) ($data['audio_lang'] ?? 'ar')),
            // مصدر البثّ
            'source_type'   => strtolower((string) ($data['source_type'] ?? 'm3u8')),
            'source_url'    => trim((string) ($data['source_url'] ?? '')),
            // وضع إعادة البثّ
            'mode'          => $data['mode'] ?? self::MODE_PROXY,
            // مقاييس المراقبة الحيّة (تُحدَّث من FFprobe/FFmpeg)
            'metrics'       => $data['metrics'] ?? [],
            // عدّاد المشاهدين التقريبي
            'viewers'       => (int) ($data['viewers'] ?? 0),
            // العلامة المائية المدموجة داخل البثّ (شعار/نصّ يظهر داخل الفيديو).
            'watermark'     => self::buildWatermark($data),
        ];

        // الحفاظ على أختام الوقت إن وُجدت.
        foreach (['created_at', 'updated_at'] as $ts) {
            if (isset($data[$ts])) {
                $defaults[$ts] = $data[$ts];
            }
        }

        return new self($defaults);
    }

    /**
     * بناء إعدادات العلامة المائية من مصفوفة قادمة (تدعم الشكل المتداخل
     * `watermark` أو الحقول المسطّحة `watermark_*` القادمة من نموذج HTML).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function buildWatermark(array $data): array
    {
        // إن أُرسلت مصفوفة متداخلة جاهزة نعتمدها كأساس.
        $wm = is_array($data['watermark'] ?? null) ? $data['watermark'] : [];

        // دعم الحقول المسطّحة من النموذج (watermark_enabled, watermark_text ...).
        $flat = static fn (string $k, $def = null) => $data['watermark_' . $k] ?? ($wm[$k] ?? $def);

        $enabled = $flat('enabled', false);
        $enabled = in_array((string) $enabled, ['1', 'true', 'on', 'yes'], true) || $enabled === true;

        $position = (string) $flat('position', 'top-right');
        if (!in_array($position, self::WM_POSITIONS, true)) {
            $position = 'top-right';
        }

        return [
            'enabled'  => $enabled,
            'type'     => in_array($flat('type', 'image'), ['image', 'text'], true) ? $flat('type', 'image') : 'image',
            'image'    => trim((string) $flat('image', '')),   // رابط/مسار صورة الشعار
            'text'     => (string) $flat('text', ''),           // نصّ العلامة المائية
            'position' => $position,
            // الشفافية 0..1 (1 = معتم تمامًا).
            'opacity'  => max(0.05, min(1.0, (float) $flat('opacity', 0.85))),
            // حجم الشعار بالبكسل (العرض) أو حجم خطّ النصّ.
            'size'     => max(8, (int) $flat('size', 120)),
            // لون النصّ (Hex).
            'color'    => preg_match('/^#?[0-9a-fA-F]{6}$/', (string) $flat('color', 'ffffff'))
                ? ltrim((string) $flat('color', 'ffffff'), '#') : 'ffffff',
            // الهامش عن الحواف بالبكسل.
            'margin'   => max(0, (int) $flat('margin', 24)),
        ];
    }

    /**
     * التحقّق من صحّة البيانات وإرجاع قائمة الأخطاء (فارغة = صحيح).
     *
     * @return array<int,string>
     */
    public function validate(): array
    {
        $errors = [];
        $a = $this->attributes;

        if ($a['name'] === '') {
            $errors[] = 'اسم القناة مطلوب.';
        }
        if (mb_strlen((string) $a['name']) > 120) {
            $errors[] = 'اسم القناة طويل جدًا (الحد 120 حرفًا).';
        }
        if ($a['source_url'] === '') {
            $errors[] = 'رابط مصدر البثّ مطلوب.';
        }
        if (!in_array($a['source_type'], self::SOURCE_TYPES, true)) {
            $errors[] = 'نوع المصدر غير مدعوم. المدعوم: ' . implode(', ', self::SOURCE_TYPES);
        }
        if (!in_array($a['status'], [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_OFFLINE], true)) {
            $errors[] = 'حالة القناة غير صحيحة.';
        }
        if (!in_array($a['mode'], [self::MODE_PROXY, self::MODE_FFMPEG], true)) {
            $errors[] = 'وضع إعادة البثّ غير صحيح.';
        }
        // التحقّق السطحي من صيغة الرابط لأنواع http.
        if (in_array($a['source_type'], ['m3u8', 'mpd', 'mp4', 'http', 'https'], true)
            && !preg_match('#^https?://#i', $a['source_url'])) {
            $errors[] = 'رابط المصدر يجب أن يبدأ بـ http:// أو https://';
        }

        return $errors;
    }

    /**
     * قراءة قيمة حقل.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * إرجاع المعرّف.
     */
    public function id(): ?string
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * تحويل الكيان إلى مصفوفة للتخزين.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
