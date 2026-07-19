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
     * هل دوال تنفيذ الأوامر (shell_exec/exec) مفعّلة على الخادم؟
     * كثير من الاستضافات المشتركة (CloudLinux/LiteSpeed) تعطّلها افتراضيًا،
     * وبدونها لا يمكن تشغيل FFmpeg إطلاقًا.
     */
    public static function execEnabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        foreach (['shell_exec', 'exec'] as $fn) {
            if (!function_exists($fn) || in_array($fn, $disabled, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * هل نظام FFmpeg متاح على الخادم؟ (يتطلّب تفعيل exec ووجود الملفّ التنفيذي).
     */
    public function isAvailable(): bool
    {
        if (!self::execEnabled()) {
            return false;
        }
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

        // تشخيص دقيق لسبب تعذّر التشغيل.
        if (!self::execEnabled()) {
            return ['ok' => false, 'message' =>
                'دوال تشغيل الأوامر (exec/shell_exec) معطّلة في استضافتك، '
                . 'لذا لا يمكن تشغيل FFmpeg. فعّلها من إعدادات PHP (disable_functions) '
                . 'أو استخدم خادمًا/VPS يسمح بها. يمكنك استخدام وضع Proxy بدون إعادة ترميز.'];
        }
        if (!$this->isAvailable()) {
            return ['ok' => false, 'message' =>
                'FFmpeg غير مثبّت أو غير موجود في المسار المحدّد ('
                . $this->binary . '). ثبّته على الخادم أو اضبط FFMPEG_BIN.'];
        }

        $channelDir = $this->outputDir . '/' . $id;
        if (!is_dir($channelDir)) {
            @mkdir($channelDir, 0775, true);
        }
        if (!is_writable($channelDir)) {
            return ['ok' => false, 'message' => 'مجلّد الإخراج streams/ غير قابل للكتابة. اضبط صلاحياته (chmod 775).'];
        }
        $playlist = $channelDir . '/index.m3u8';

        $cmd = $this->buildCommand($channel, $playlist);
        $logFile = Config::get('paths.logs') . "/ffmpeg-{$id}.log";

        // تشغيل في الخلفية مع فصل العملية (setsid) وكتابة السجل.
        // setsid يفصل العملية عن جلسة الويب حتى لا تُقتل عند انتهاء الطلب.
        $setsid = $this->hasSetsid() ? 'setsid ' : '';
        $fullCmd = sprintf('%s%s > %s 2>&1 & echo $!', $setsid, $cmd, escapeshellarg($logFile));
        $pid = (int) trim((string) shell_exec($fullCmd));

        if ($pid <= 0) {
            return ['ok' => false, 'message' => 'تعذّر تشغيل FFmpeg (لم تُرجَع عملية).'];
        }

        // تحقّق قصير: هل العملية حيّة بعد لحظة أم ماتت فورًا (خطأ في الأمر)؟
        usleep(600_000);
        if (!$this->processExists($pid)) {
            $tail = $this->logTail($logFile);
            Logger::warning('FFmpeg مات فور التشغيل', ['channel' => $id, 'log' => $tail]);
            return ['ok' => false, 'message' => 'بدأ FFmpeg ثم توقّف فورًا. آخر سطور السجل: ' . $tail];
        }

        $this->savePid($id, $pid);
        Logger::info('تم تشغيل بثّ FFmpeg', ['channel' => $id, 'pid' => $pid]);

        return [
            'ok'      => true,
            'message' => 'تم تشغيل البثّ بنجاح.' . (!empty($channel['watermark']['enabled']) ? ' (مع الشعار)' : ''),
            'pid'     => $pid,
            'output'  => Config::baseUrl() . "/stream/{$id}/index.m3u8",
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
     * بناء أمر FFmpeg كاملًا حسب إعدادات القناة (copy أو transcode + علامة مائية).
     */
    private function buildCommand(array $channel, string $playlist): string
    {
        $bin = escapeshellarg($this->binary);
        $source = (string) $channel['source_url'];
        $quality = (string) ($channel['quality'] ?? 'source');

        // هل توجد علامة مائية مفعّلة؟ (تفرض إعادة ترميز الفيديو).
        $wm = is_array($channel['watermark'] ?? null) ? $channel['watermark'] : [];
        $wmEnabled = !empty($wm['enabled']);
        $wmType = $wm['type'] ?? 'image';
        $logoPath = null;

        $input = [];

        // إعادة الاتصال التلقائي لمصادر HTTP.
        if (Config::get('ffmpeg.reconnect', true) && preg_match('#^https?://#i', $source)) {
            $input[] = '-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max '
                . (int) Config::get('ffmpeg.reconnect_delay_max', 5);
        }
        $input[] = '-i ' . escapeshellarg($source);

        // إدخال ثانٍ لصورة الشعار (بحلقة لتبقى ظاهرة طوال البثّ).
        if ($wmEnabled && $wmType === 'image') {
            $logoPath = $this->resolveLogo($channel);
            if ($logoPath !== null) {
                $input[] = '-i ' . escapeshellarg($logoPath);
            } else {
                $wmEnabled = false; // تعذّر تجهيز الشعار — نتجاهل العلامة.
            }
        }

        // بناء سلسلة الفلاتر للعلامة المائية.
        $filter = $wmEnabled ? $this->buildWatermarkFilter($wm, $logoPath !== null) : '';

        // إعدادات الترميز.
        $codec = [];
        $qualities = (array) Config::get('ffmpeg.qualities', []);
        $needsTranscode = $wmEnabled
            || !($quality === 'source' || !isset($qualities[$quality]) || !empty($qualities[$quality]['copy']));

        if (!$needsTranscode) {
            // نسخ مباشر بلا إعادة ترميز (أخف وأسرع) — لا علامة مائية.
            $codec[] = '-c copy';
        } else {
            $vf = [];
            // مقياس الجودة عند اختيار جودة محدّدة.
            if (isset($qualities[$quality]) && empty($qualities[$quality]['copy'])) {
                $q = $qualities[$quality];
                $vf[] = "scale={$q['width']}:{$q['height']}";
                $codec[] = '-b:v ' . escapeshellarg((string) $q['v_bitrate']);
                $codec[] = '-c:a aac -b:a ' . escapeshellarg((string) $q['a_bitrate']);
            } else {
                // علامة مائية دون تغيير الجودة: نبقي الصوت كما هو.
                $codec[] = '-c:a copy';
            }

            if ($wmType === 'image' && $logoPath !== null) {
                // دمج الفيديو مع الشعار عبر filter_complex.
                $codec[] = '-filter_complex ' . escapeshellarg($filter);
            } elseif ($filter !== '') {
                // نصّ أو مقياس فقط عبر -vf.
                $vfCombined = $vf ? implode(',', $vf) . ',' . $filter : $filter;
                $codec[] = '-vf ' . escapeshellarg($vfCombined);
            } elseif ($vf) {
                $codec[] = '-vf ' . escapeshellarg(implode(',', $vf));
            }

            array_unshift($codec, '-c:v libx264 -preset veryfast -profile:v main -pix_fmt yuv420p');
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

    /**
     * بناء سلسلة فلتر العلامة المائية (overlay للصورة أو drawtext للنصّ).
     *
     * @param array<string,mixed> $wm      إعدادات العلامة.
     * @param bool                $hasLogo هل توجد صورة شعار كمُدخل ثانٍ؟
     */
    private function buildWatermarkFilter(array $wm, bool $hasLogo): string
    {
        $margin = (int) ($wm['margin'] ?? 24);
        $opacity = (float) ($wm['opacity'] ?? 0.85);
        $position = (string) ($wm['position'] ?? 'top-right');

        if (($wm['type'] ?? 'image') === 'image' && $hasLogo) {
            // موضع overlay بدلالة أبعاد الفيديو (W,H) والشعار (w,h).
            $pos = match ($position) {
                'top-left'     => "{$margin}:{$margin}",
                'bottom-left'  => "{$margin}:H-h-{$margin}",
                'bottom-right' => "W-w-{$margin}:H-h-{$margin}",
                'center'       => '(W-w)/2:(H-h)/2',
                default        => "W-w-{$margin}:{$margin}", // top-right
            };
            $w = (int) ($wm['size'] ?? 120);
            // نقيس الشعار، نطبّق الشفافية، ثم ندمجه.
            return "[1:v]scale={$w}:-1,format=rgba,colorchannelmixer=aa={$opacity}[wm];[0:v][wm]overlay={$pos}";
        }

        // نصّ عبر drawtext.
        $text = str_replace([':', "'", '\\'], ['\\:', "\u{2019}", ''], (string) ($wm['text'] ?? ''));
        $size = (int) ($wm['size'] ?? 28);
        $color = (string) ($wm['color'] ?? 'ffffff');
        $font = $this->findFont();
        $fontOpt = $font ? "fontfile='{$font}':" : '';
        $pos = match ($position) {
            'top-left'     => "x={$margin}:y={$margin}",
            'bottom-left'  => "x={$margin}:y=h-th-{$margin}",
            'bottom-right' => "x=w-tw-{$margin}:y=h-th-{$margin}",
            'center'       => 'x=(w-tw)/2:y=(h-th)/2',
            default        => "x=w-tw-{$margin}:y={$margin}", // top-right
        };
        return "drawtext={$fontOpt}text='{$text}':fontcolor=0x{$color}@{$opacity}:fontsize={$size}"
            . ":box=1:boxcolor=black@0.4:boxborderw=8:{$pos}";
    }

    /**
     * تجهيز مسار محلّي لصورة الشعار: يعتمد الملفّ المحلّي إن وُجد، أو يُنزّل
     * الصورة من رابط خارجي إلى storage/watermarks/.
     */
    private function resolveLogo(array $channel): ?string
    {
        $wm = $channel['watermark'] ?? [];
        $image = trim((string) ($wm['image'] ?? ''));
        if ($image === '') {
            return null;
        }

        $cid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $channel['id']);

        // 1) رابط يخصّ أصولنا المحلّية -> نحوّله لمسار في نظام الملفّات.
        $base = Config::baseUrl();
        if (str_starts_with($image, $base . '/')) {
            $rel = ltrim(substr($image, strlen($base)), '/');
            $local = Config::get('paths.root') . '/' . $rel;
            if (is_file($local)) {
                return $this->rasterizeIfSvg($local, $cid);
            }
        }

        // 2) مسار محلّي مباشر موجود.
        if (is_file($image)) {
            return $this->rasterizeIfSvg($image, $cid);
        }

        // 3) رابط خارجي -> تنزيل إلى storage/watermarks/{channelId}.
        if (preg_match('#^https?://#i', $image)) {
            $dir = Config::get('paths.storage') . '/watermarks';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ext = strtolower(pathinfo((string) parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png');
            $dest = $dir . '/' . $cid . '.' . $ext;
            $data = @file_get_contents($image);
            if ($data !== false && $data !== '') {
                @file_put_contents($dest, $data);
                return $this->rasterizeIfSvg($dest, $cid);
            }
        }

        return null;
    }

    /**
     * FFmpeg لا يقرأ SVG في أغلب البُنى. إن كان الشعار SVG نحوّله إلى PNG:
     *   - عبر إضافة Imagick إن كانت متوفّرة (لا تحتاج exec).
     *   - أو عبر rsvg-convert / ImageMagick convert إن كان exec مفعّلًا.
     * وإلا نُعيد الملفّ كما هو (قد ينجح إن كان FFmpeg مبنيًّا مع librsvg).
     *
     * @return string المسار الجاهز للاستخدام مع FFmpeg.
     */
    private function rasterizeIfSvg(string $path, string $cid): string
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
            return $path;
        }

        $dir = Config::get('paths.storage') . '/watermarks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $png = $dir . '/' . $cid . '_raster.png';

        // (أ) Imagick (لا يتطلّب صلاحيات تنفيذ أوامر).
        if (extension_loaded('imagick')) {
            try {
                $im = new \Imagick();
                $im->setBackgroundColor(new \ImagickPixel('transparent'));
                $im->readImageBlob((string) @file_get_contents($path));
                $im->setImageFormat('png32');
                $im->writeImage($png);
                $im->clear();
                if (is_file($png)) {
                    return $png;
                }
            } catch (\Throwable $e) {
                Logger::warning('فشل تحويل SVG عبر Imagick', ['err' => $e->getMessage()]);
            }
        }

        // (ب) أدوات سطر الأوامر إن كان exec مفعّلًا.
        if (self::execEnabled()) {
            $src = escapeshellarg($path);
            $out = escapeshellarg($png);
            @shell_exec("rsvg-convert -w 400 {$src} -o {$out} 2>/dev/null");
            if (!is_file($png)) {
                @shell_exec("convert -background none {$src} {$out} 2>/dev/null");
            }
            if (is_file($png)) {
                return $png;
            }
        }

        // (ج) تعذّر التحويل — نُعيد الأصل (قد ينجح مع librsvg).
        Logger::warning('تعذّر تحويل SVG إلى PNG؛ يُفضّل رفع شعار PNG.', ['path' => $path]);
        return $path;
    }

    /**
     * إيجاد ملفّ خطّ متاح على النظام لاستخدامه مع drawtext.
     */
    private function findFont(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
        ] as $f) {
            if (is_file($f)) {
                return $f;
            }
        }
        return null;
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
     * هل الأمر setsid متاح؟ (لفصل عملية FFmpeg عن جلسة الويب).
     */
    private function hasSetsid(): bool
    {
        $out = @shell_exec('command -v setsid 2>/dev/null');
        return is_string($out) && trim($out) !== '';
    }

    /**
     * قراءة آخر أسطر من ملفّ سجلّ FFmpeg (لعرض سبب الفشل للمستخدم).
     */
    private function logTail(string $file, int $lines = 4): string
    {
        if (!is_file($file)) {
            return '(لا يوجد سجل)';
        }
        $content = trim((string) @file_get_contents($file));
        if ($content === '') {
            return '(السجل فارغ)';
        }
        $arr = array_slice(explode("\n", $content), -$lines);
        return trim(implode(' | ', $arr));
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
