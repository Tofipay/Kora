<?php

/**
 * classes/StreamSupervisor.php
 * -----------------------------------------------------------------------------
 * المشرف (Supervisor) على بثوث FFmpeg. يُشغّل دوريًا عبر Cron ليضمن:
 *   - Auto Restart: إعادة تشغيل أي قناة وضعها ffmpeg وتوقّفت عمليتها.
 *   - Detect Offline: تحديث حالة القناة إلى offline عند انقطاع المصدر.
 *   - تحديث مقاييس المراقبة تلقائيًا.
 *
 * الاستخدام (كل دقيقة عبر crontab):
 *   * * * * * php /path/to/tofix-stream/cron/supervisor.php
 *
 * @package ToFiXStream\Monitor
 */

declare(strict_types=1);

namespace ToFiXStream;

final class StreamSupervisor
{
    private ChannelManager $channels;
    private FFmpegManager $ffmpeg;
    private StreamMonitor $monitor;

    public function __construct()
    {
        $this->channels = new ChannelManager();
        $this->ffmpeg   = new FFmpegManager();
        $this->monitor  = new StreamMonitor();
    }

    /**
     * دورة إشراف واحدة على كل القنوات.
     *
     * @return array<int,array<string,mixed>> تقرير عن كل قناة.
     */
    public function tick(): array
    {
        $report = [];
        foreach ($this->channels->list() as $channel) {
            $id = (string) $channel['id'];
            $line = ['id' => $id, 'name' => $channel['name'], 'action' => 'none'];

            // نُدير فقط القنوات النشطة التي تعمل بوضع FFmpeg.
            if (($channel['status'] ?? '') === 'active' && ($channel['mode'] ?? '') === Channel::MODE_FFMPEG) {
                if (!$this->ffmpeg->isRunning($id)) {
                    $result = $this->ffmpeg->start($channel);
                    $line['action'] = $result['ok'] ? 'restarted' : 'restart_failed';
                    Logger::warning('Supervisor أعاد تشغيل بثّ', ['id' => $id, 'ok' => $result['ok']]);
                }
            }

            // تحديث مقاييس المراقبة (متاح لكل الأوضاع).
            if (($channel['status'] ?? '') === 'active') {
                $metrics = $this->monitor->probe((string) $channel['source_url']);
                $this->channels->updateMetrics($id, $metrics);
                $line['status'] = $metrics['status'];

                // كشف الانقطاع: إن كان المصدر offline نُحدّث حالة القناة.
                if ($metrics['status'] === 'offline') {
                    $this->channels->update($id, ['status' => Channel::STATUS_OFFLINE]);
                    $line['action'] = 'marked_offline';
                }
            }

            $report[] = $line;
        }
        return $report;
    }
}
