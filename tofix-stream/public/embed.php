<?php

/**
 * public/embed.php
 * -----------------------------------------------------------------------------
 * مشغّل مبسّط للتضمين عبر <iframe> في المواقع الأخرى. يستخدم Hls.js فقط
 * لخفّة الوزن وسرعة التحميل، ويعرض الفيديو ملء الإطار بلا واجهة إضافية.
 *
 * @package ToFiXStream\Player
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\ChannelManager;

$channelId = (string) ($_GET['channel'] ?? '');
$manager = new ChannelManager();
$channel = $channelId !== '' ? $manager->get($channelId) : null;

if (!$channel) {
    http_response_code(404);
    exit('Channel not found');
}
$streamUrl = $channel['playback']['hls'];
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string) $channel['name']) ?></title>
  <style>html,body{margin:0;height:100%;background:#000}video{width:100%;height:100%;object-fit:contain}</style>
</head>
<body>
  <video id="v" controls autoplay playsinline crossorigin="anonymous"></video>
  <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
  <script>
    const src = <?= json_encode($streamUrl) ?>;
    const v = document.getElementById('v');
    if (window.Hls && Hls.isSupported()) {
      const hls = new Hls({ lowLatencyMode: true });
      hls.loadSource(src); hls.attachMedia(v);
      hls.on(Hls.Events.MANIFEST_PARSED, () => v.play().catch(() => {}));
    } else if (v.canPlayType('application/vnd.apple.mpegurl')) {
      v.src = src;
    }
  </script>
</body>
</html>
