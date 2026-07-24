<?php
declare(strict_types=1);

namespace TofiXTv\Controllers;

use TofiXTv\Core\AppMode;

/**
 * Serves the official Android app package (aloka-live.apk). The file lives
 * outside the web root (storage/app/) and is streamed by PHP so the download
 * always uses the correct filename and content type; if it isn't uploaded yet
 * the request falls back to the Telegram channel.
 */
final class Download
{
    public static function apk(): void
    {
        AppMode::serveApk();
    }
}
