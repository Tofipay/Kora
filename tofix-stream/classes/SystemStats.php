<?php

/**
 * classes/SystemStats.php
 * -----------------------------------------------------------------------------
 * جمع مؤشّرات صحّة الخادم لعرضها في اللوحة: CPU, RAM, Storage, Uptime.
 * يعتمد على واجهات لينكس (/proc) عند توفّرها ويتدرّج بلطف إلى قيم "غير معروف".
 *
 * @package ToFiXStream\Monitor
 */

declare(strict_types=1);

namespace ToFiXStream;

final class SystemStats
{
    /**
     * إرجاع لقطة كاملة عن حالة الخادم.
     *
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        return [
            'cpu'     => $this->cpu(),
            'ram'     => $this->ram(),
            'storage' => $this->storage(),
            'uptime'  => $this->uptime(),
            'php'     => PHP_VERSION,
            'os'      => php_uname('s') . ' ' . php_uname('r'),
            'time'    => date('c'),
        ];
    }

    /**
     * نسبة استخدام المعالج التقريبية من متوسّط الحِمل (load average).
     *
     * @return array<string,mixed>
     */
    private function cpu(): array
    {
        $cores = $this->cpuCores();
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $one = $load[0] ?? 0;
        $percent = $cores > 0 ? min(100, round(($one / $cores) * 100, 1)) : 0;

        return [
            'cores'   => $cores,
            'load'    => round((float) $one, 2),
            'percent' => $percent,
        ];
    }

    /**
     * عدد أنوية المعالج.
     */
    private function cpuCores(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            $count = substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');
            if ($count > 0) {
                return $count;
            }
        }
        return 1;
    }

    /**
     * استخدام الذاكرة من /proc/meminfo.
     *
     * @return array<string,mixed>
     */
    private function ram(): array
    {
        if (is_readable('/proc/meminfo')) {
            $info = (string) @file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $info, $t);
            preg_match('/MemAvailable:\s+(\d+)/', $info, $a);
            $total = isset($t[1]) ? (int) $t[1] * 1024 : 0;
            $avail = isset($a[1]) ? (int) $a[1] * 1024 : 0;
            $used = $total - $avail;
            return [
                'total'   => $this->human($total),
                'used'    => $this->human($used),
                'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            ];
        }
        // بديل: استهلاك عملية PHP فقط.
        return [
            'total'   => '—',
            'used'    => $this->human(memory_get_usage(true)),
            'percent' => 0,
        ];
    }

    /**
     * استخدام مساحة التخزين للقرص الحاوي للمشروع.
     *
     * @return array<string,mixed>
     */
    private function storage(): array
    {
        $root = Config::get('paths.root');
        $total = @disk_total_space($root) ?: 0;
        $free = @disk_free_space($root) ?: 0;
        $used = $total - $free;
        return [
            'total'   => $this->human((int) $total),
            'used'    => $this->human((int) $used),
            'free'    => $this->human((int) $free),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    /**
     * مدّة تشغيل الخادم.
     */
    private function uptime(): string
    {
        if (is_readable('/proc/uptime')) {
            $seconds = (int) explode(' ', (string) @file_get_contents('/proc/uptime'))[0];
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $mins = intdiv($seconds % 3600, 60);
            return "{$days}d {$hours}h {$mins}m";
        }
        return '—';
    }

    /**
     * تحويل بايت إلى وحدة مقروءة.
     */
    private function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
