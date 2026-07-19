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

// طبقة الشعار فوق الفيديو (وضع Proxy، بدون FFmpeg).
$wm = is_array($channel['watermark'] ?? null) ? $channel['watermark'] : [];
$showOverlay = !empty($wm['enabled']) && ($channel['mode'] ?? 'proxy') !== 'ffmpeg';
$m = (int) ($wm['margin'] ?? 24) . 'px';
$wmPos = match ($wm['position'] ?? 'top-right') {
    'top-left'     => "top:$m;left:$m",
    'bottom-left'  => "bottom:$m;left:$m",
    'bottom-right' => "bottom:$m;right:$m",
    'center'       => 'top:50%;left:50%;transform:translate(-50%,-50%)',
    default        => "top:$m;right:$m",
};
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string) $channel['name']) ?></title>
  <style>
    html,body{margin:0;height:100%;background:#000}
    .wrap{position:relative;width:100%;height:100%}
    video{width:100%;height:100%;object-fit:contain}
    .wm{position:absolute;z-index:5;pointer-events:none;filter:drop-shadow(0 2px 6px rgba(0,0,0,.6))}
    .wm.text{font-family:sans-serif;font-weight:800;padding:4px 12px;border-radius:8px;background:rgba(0,0,0,.35);text-shadow:0 2px 6px rgba(0,0,0,.7)}
  </style>
</head>
<body>
  <div class="wrap">
    <video id="v" controls autoplay playsinline crossorigin="anonymous"></video>
    <?php if ($showOverlay): ?>
      <?php $op = (float) ($wm['opacity'] ?? 0.9); ?>
      <?php if (($wm['type'] ?? 'image') === 'image' && !empty($wm['image'])): ?>
        <img class="wm" src="<?= htmlspecialchars((string) $wm['image']) ?>" alt="logo"
             style="<?= $wmPos ?>;width:<?= (int) ($wm['size'] ?? 140) ?>px;opacity:<?= $op ?>">
      <?php elseif (($wm['type'] ?? '') === 'text' && !empty($wm['text'])): ?>
        <div class="wm text" style="<?= $wmPos ?>;color:#<?= htmlspecialchars((string) ($wm['color'] ?? 'ffffff')) ?>;opacity:<?= $op ?>;font-size:<?= (int) ($wm['size'] ?? 28) ?>px"><?= htmlspecialchars((string) $wm['text']) ?></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
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
