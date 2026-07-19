<?php

/**
 * classes/FFmpegManager.php
 * -----------------------------------------------------------------------------
 * مدير عمليات FFmpeg لإعادة البثّ إلى HLS محلّي على السيرفر.
 *
 * المسؤوليات:
 *   - بناء أمر FFmpeg الصحيح حسب وضع النسخ (copy) أو إعادة الترميز (transcode).
 *   - تشغيل العملية في الخلفية وتخزين رقم العملية (PID) للتحكّم بها.
 *   - إيقاف/إعادة تشغيل/كشف الحالة (online/offline).
 *   - إعادة الاتصال التلقائي (reconnect) للمصادر عبر HTTP.
 *
 * ملاحظة أمان: كل الوسائط تُمرّر عبر escapeshellarg لمنع حقن الأوامر.
 *
 * @package ToFiXStream\FFmpeg
 */

declare(strict_types=1);

namespace ToFiXStream;

final class FFmpegManager
{
    private string $binary;
    private string $outputDir;
    private string $pidDir;

    public function __construct()
    {
        $this->binary   = (string) Config::get('ffmpeg.binary', 'ffmpeg');
        $this->outputDir = (string) Config::get('ffmpeg.output_dir');
        $this->pidDir   = (string) Config::get('ffmpeg.pid_dir');

        foreach ([$this->outputDir, $this->pidDir] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }

    /**
     * هل نظام FFmpeg متاح على الخادم؟
     */
    public function isAvailable(): bool
    {
        $out = @shell_exec(escapeshellarg($this->binary) . ' -version 2>&1');
        return is_string($out) && str_contains($out, 'ffmpeg version');
    }

    /**
     * تشغيل إعادة بثّ لقناة إلى HLS محلّي.
     *
     * @param array<string,mixed> $channel سجلّ القناة.
     * @return array{ok:bool,message:string,pid?:int,output?:string}
     */
    public function start(array $channel): array
    {
        $id = (string) ($channel['id'] ?? '');
        if ($id === '' || empty($channel['source_url'])) {
            return ['ok' => false, 'message' => 'بيانات القناة غير مكتملة.'];
        }

        if ($this->isRunning($id)) {
            return ['ok' => false, 'message' => 'البثّ يعمل بالفعل.', 'pid' => $this->pid($id) ?? 0];
        }

        if (!$this->isAvailable()) {
            return ['ok' => false, 'message' => 'FFmpeg غير مثبّت على الخادم.'];
        }

        $channelDir = $this->outputDir . '/' . $id;
        if (!is_dir($channelDir)) {
            @mkdir($channelDir, 0775, true);
        }
        $playlist = $channelDir . '/index.m3u8';

        $cmd = $this->buildCommand($channel, $playlist);
        $logFile = Config::get('paths.logs') . "/ffmpeg-{$id}.log";

        // تشغيل في الخلفية مع فصل العملية وكتابة السجل.
        $fullCmd = sprintf('%s > %s 2>&1 & echo $!', $cmd, escapeshellarg($logFile));
        $pid = (int) trim((string) shell_exec($fullCmd));

        if ($pid <= 0) {
            return ['ok' => false, 'message' => 'تعذّر تشغيل FFmpeg.'];
        }

        $this->savePid($id, $pid);
        Logger::info('تم تشغيل بثّ FFmpeg', ['channel' => $id, 'pid' => $pid]);

        return [
            'ok'      => true,
            'message' => 'تم تشغيل البثّ.',
            'pid'     => $pid,
            'output'  => Config::get('app.base_url') . "/streams/{$id}/index.m3u8",
        ];
    }

    /**
     * إيقاف بثّ قناة عبر رقم العملية.
     *
     * @return array{ok:bool,message:string}
     */
    public function stop(string $channelId): array
    {
        $pid = $this->pid($channelId);
        if ($pid === null) {
            return ['ok' => false, 'message' => 'لا يوجد بثّ نشط لهذه القناة.'];
        }

        // إنهاء لطيف ثم قسري إن لزم.
        @exec('kill ' . (int) $pid . ' 2>/dev/null');
        usleep(300_000);
        if ($this->processExists($pid)) {
            @exec('kill -9 ' . (int) $pid . ' 2>/dev/null');
        }

        $this->removePid($channelId);
        Logger::info('تم إيقاف بثّ FFmpeg', ['channel' => $channelId, 'pid' => $pid]);
        return ['ok' => true, 'message' => 'تم إيقاف البثّ.'];
    }

    /**
     * إعادة تشغيل بثّ قناة.
     *
     * @param array<string,mixed> $channel
     * @return array{ok:bool,message:string,pid?:int,output?:string}
     */
    public function restart(array $channel): array
    {
        $this->stop((string) $channel['id']);
        usleep(400_000);
        return $this->start($channel);
    }

    /**
     * هل البثّ يعمل حاليًا؟ (يتحقّق من وجود العملية فعليًا لا من الملف فقط).
     */
    public function isRunning(string $channelId): bool
    {
        $pid = $this->pid($channelId);
        if ($pid === null) {
            return false;
        }
        if (!$this->processExists($pid)) {
            // العملية ماتت لكن ملف الـ PID باقٍ => ننظّفه.
            $this->removePid($channelId);
            return false;
        }
        return true;
    }

    /**
     * حالة البثّ التفصيلية لقناة.
     *
     * @return array{running:bool,pid:?int,online:bool,output:?string}
     */
    public function status(string $channelId): array
    {
        $running = $this->isRunning($channelId);
        $playlist = $this->outputDir . '/' . $channelId . '/index.m3u8';
        // نعتبر البثّ "online" إذا كان يعمل والبلاي-ليست تحدّثت مؤخّرًا.
        $online = $running && is_file($playlist) && (time() - filemtime($playlist)) < 30;

        return [
            'running' => $running,
            'pid'     => $this->pid($channelId),
            'online'  => $online,
            'output'  => is_file($playlist)
                ? Config::get('app.base_url') . "/streams/{$channelId}/index.m3u8"
                : null,
        ];
    }

    // -------------------------------------------------------------------------
    // بناء الأمر
    // -------------------------------------------------------------------------

    /**
     * بناء أمر FFmpeg كاملًا حسب إعدادات القناة (copy أو transcode).
     */
    private function buildCommand(array $channel, string $playlist): string
    {
        $bin = escapeshellarg($this->binary);
        $source = (string) $channel['source_url'];
        $quality = (string) ($channel['quality'] ?? 'source');

        $input = [];

        // إعادة الاتصال التلقائي لمصادر HTTP.
        if (Config::get('ffmpeg.reconnect', true) && preg_match('#^https?://#i', $source)) {
            $input[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max '
                . (int) Config::get('ffmpeg.reconnect_delay_max', 5);
        }
        $input[] = '-i ' . escapeshellarg($source);

        // إعدادات الترميز.
        $codec = [];
        $qualities = (array) Config::get('ffmpeg.qualities', []);
        if ($quality === 'source' || !isset($qualities[$quality]) || !empty($qualities[$quality]['copy'])) {
            // نسخ مباشر بلا إعادة ترميز (أخف وأسرع).
            $codec[] = '-c copy';
        } else {
            $q = $qualities[$quality];
            $codec[] = '-c:v libx264 -preset veryfast -profile:v main';
            $codec[] = '-b:v ' . escapeshellarg((string) $q['v_bitrate']);
            $codec[] = '-vf ' . escapeshellarg("scale={$q['width']}:{$q['height']}");
            $codec[] = '-c:a aac -b:a ' . escapeshellarg((string) $q['a_bitrate']);
        }

        // إعدادات HLS.
        $hls = [
            '-f hls',
            '-hls_time ' . (int) Config::get('ffmpeg.hls_time', 4),
            '-hls_list_size ' . (int) Config::get('ffmpeg.hls_list_size', 6),
            '-hls_flags delete_segments+append_list+omit_endlist',
            '-hls_segment_filename ' . escapeshellarg(dirname($playlist) . '/seg_%05d.ts'),
        ];

        return implode(' ', array_merge(
            [$bin, '-hide_banner -loglevel warning -y'],
            $input,
            $codec,
            $hls,
            [escapeshellarg($playlist)]
        ));
    }

    // -------------------------------------------------------------------------
    // إدارة ملفات PID
    // -------------------------------------------------------------------------

    private function pidFile(string $channelId): string
    {
        return $this->pidDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $channelId) . '.pid';
    }

    private function savePid(string $channelId, int $pid): void
    {
        @file_put_contents($this->pidFile($channelId), (string) $pid, LOCK_EX);
    }

    private function pid(string $channelId): ?int
    {
        $file = $this->pidFile($channelId);
        if (!is_file($file)) {
            return null;
        }
        $pid = (int) trim((string) @file_get_contents($file));
        return $pid > 0 ? $pid : null;
    }

    private function removePid(string $channelId): void
    {
        @unlink($this->pidFile($channelId));
    }

    /**
     * التحقّق من وجود عملية بنظام POSIX.
     */
    private function processExists(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        return is_dir("/proc/{$pid}");
    }
}
