<?php

/**
 * classes/JsonStore.php
 * -----------------------------------------------------------------------------
 * طبقة تخزين خفيفة تعتمد ملفات JSON بدلاً من قاعدة بيانات، مع قفل ملفات
 * (flock) لضمان سلامة البيانات أثناء الطلبات المتزامنة.
 *
 * تُستخدم كـ "Repository" عام لأي مجموعة سجلّات (قنوات، إعدادات، إحصائيات...).
 * كل سجلّ عبارة عن مصفوفة ترابطية تحتوي حقل "id" فريد.
 *
 * @package ToFiXStream\Storage
 */

declare(strict_types=1);

namespace ToFiXStream;

final class JsonStore
{
    /** @var string المسار الكامل لملف JSON. */
    private string $file;

    /** @var bool تفعيل قفل الملفات. */
    private bool $useLock;

    /**
     * @param string $file    مسار ملف JSON.
     * @param bool   $useLock هل نستخدم قفل الكتابة؟
     */
    public function __construct(string $file, bool $useLock = true)
    {
        $this->file = $file;
        $this->useLock = $useLock;
        $this->ensureFile();
    }

    /**
     * التأكّد من وجود ملف التخزين ومجلّده، وإنشاؤه فارغًا عند اللزوم.
     */
    private function ensureFile(): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!file_exists($this->file)) {
            @file_put_contents($this->file, "[]", LOCK_EX);
        }
    }

    /**
     * قراءة كامل السجلّات من الملف.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $raw = @file_get_contents($this->file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * إيجاد سجلّ واحد عبر مُعرّفه.
     *
     * @param string $id مُعرّف السجل.
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * إدراج سجلّ جديد (يولّد id تلقائيًا إن لم يوجد).
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed> السجل بعد الإدراج.
     */
    public function insert(array $row): array
    {
        $row['id'] ??= $this->generateId();
        $row['created_at'] ??= date('c');
        $row['updated_at'] = date('c');

        $this->transaction(function (array $rows) use (&$row): array {
            $rows[] = $row;
            return $rows;
        });

        return $row;
    }

    /**
     * تحديث سجلّ موجود بدمج الحقول الجديدة.
     *
     * @param string $id
     * @param array<string,mixed> $changes
     * @return array<string,mixed>|null السجل بعد التحديث أو null إن لم يوجد.
     */
    public function update(string $id, array $changes): ?array
    {
        $updated = null;
        $this->transaction(function (array $rows) use ($id, $changes, &$updated): array {
            foreach ($rows as $i => $row) {
                if (($row['id'] ?? null) === $id) {
                    unset($changes['id'], $changes['created_at']);
                    $rows[$i] = array_merge($row, $changes, ['updated_at' => date('c')]);
                    $updated = $rows[$i];
                    break;
                }
            }
            return $rows;
        });
        return $updated;
    }

    /**
     * حذف سجلّ عبر مُعرّفه.
     *
     * @param string $id
     * @return bool هل تمّ الحذف فعليًا؟
     */
    public function delete(string $id): bool
    {
        $deleted = false;
        $this->transaction(function (array $rows) use ($id, &$deleted): array {
            $filtered = array_values(array_filter(
                $rows,
                static fn (array $row): bool => ($row['id'] ?? null) !== $id
            ));
            $deleted = count($filtered) !== count($rows);
            return $filtered;
        });
        return $deleted;
    }

    /**
     * كتابة مجموعة سجلّات كاملة (استبدال شامل).
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function replaceAll(array $rows): void
    {
        $this->write($rows);
    }

    /**
     * تنفيذ عملية قراءة/تعديل/كتابة ذرّية (transaction) داخل قفل واحد.
     *
     * @param callable(array):array $mutator دالة تستقبل السجلّات وتُعيدها معدّلة.
     */
    private function transaction(callable $mutator): void
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("تعذّر فتح ملف التخزين: {$this->file}");
        }

        try {
            if ($this->useLock) {
                flock($handle, LOCK_EX);
            }
            $raw = stream_get_contents($handle) ?: '[]';
            $rows = json_decode($raw, true);
            $rows = is_array($rows) ? $rows : [];

            $rows = $mutator($rows);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $this->encode($rows));
            fflush($handle);
        } finally {
            if ($this->useLock) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    /**
     * كتابة مباشرة للملف (بقفل).
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private function write(array $rows): void
    {
        $flags = $this->useLock ? LOCK_EX : 0;
        file_put_contents($this->file, $this->encode($rows), $flags);
    }

    /**
     * ترميز المصفوفة إلى JSON مقروء.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private function encode(array $rows): string
    {
        return json_encode(
            array_values($rows),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '[]';
    }

    /**
     * توليد مُعرّف فريد قصير وآمن.
     */
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
