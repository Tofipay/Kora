<?php

/**
 * classes/StreamMonitor.php
 * -----------------------------------------------------------------------------
 * مراقبة البثّ الحيّة عبر ffprobe: استخراج المعلومات التقنية للمصدر أو
 * للبثّ المُعاد (bitrate, fps, resolution, codecs...) لعرضها في اللوحة.
 *
 * إن لم يتوفّر ffprobe يُعيد قيمًا افتراضية "غير معروف" دون كسر الواجهة.
 *
 * @package ToFiXStream\Monitor
 */

declare(strict_types=1);

namespace ToFiXStream;

final class StreamMonitor
{
    private string $ffprobe;

    public function __construct()
    {
        $this->ffprobe = (string) Config::get('ffmpeg.ffprobe', 'ffprobe');
    }

    /**
     * هل ffprobe متاح؟
     */
    public function isAvailable(): bool
    {
        $out = @shell_exec(escapeshellarg($this->ffprobe) . ' -version 2>&1');
        return is_string($out) && str_contains($out, 'ffprobe version');
    }

    /**
     * فحص مصدر بثّ واستخراج المقاييس التقنية.
     *
     * @param string $url رابط المصدر (m3u8/mp4/...).
     * @return array<string,mixed> مقاييس المراقبة.
     */
    public function probe(string $url): array
    {
        $default = [
            'status'        => 'unknown',
            'resolution'    => '—',
            'fps'           => '—',
            'video_codec'   => '—',
            'audio_codec'   => '—',
            'bitrate'       => '—',
            'checked_at'    => date('c'),
        ];

        if (!$this->isAvailable()) {
            $default['status'] = 'no_ffprobe';
            return $default;
        }

        $cmd = sprintf(
            '%s -v quiet -print_format json -show_streams -show_format -timeout 8000000 %s 2>&1',
            escapeshellarg($this->ffprobe),
            escapeshellarg($url)
        );

        $raw = @shell_exec($cmd);
        if (!is_string($raw) || trim($raw) === '') {
            $default['status'] = 'offline';
            return $default;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['streams'])) {
            $default['status'] = 'offline';
            return $default;
        }

        $video = null;
        $audio = null;
        foreach ($data['streams'] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video' && $video === null) {
                $video = $stream;
            }
            if (($stream['codec_type'] ?? '') === 'audio' && $audio === null) {
                $audio = $stream;
            }
        }

        $fps = '—';
        if ($video && !empty($video['r_frame_rate']) && str_contains($video['r_frame_rate'], '/')) {
            [$num, $den] = array_map('intval', explode('/', $video['r_frame_rate']));
            $fps = $den > 0 ? round($num / $den, 2) : '—';
        }

        return [
            'status'      => 'online',
            'resolution'  => $video ? ($video['width'] ?? '?') . 'x' . ($video['height'] ?? '?') : '—',
            'fps'         => $fps,
            'video_codec' => $video['codec_name'] ?? '—',
            'audio_codec' => $audio['codec_name'] ?? '—',
            'bitrate'     => isset($data['format']['bit_rate'])
                ? round(((int) $data['format']['bit_rate']) / 1000) . ' kbps'
                : '—',
            'checked_at'  => date('c'),
        ];
    }
}
