<?php

/**
 * public/player.php
 * -----------------------------------------------------------------------------
 * صفحة المشغّل الاحترافية لقناة واحدة.
 * تدعم ثلاثة محرّكات: Video.js (افتراضي) + Hls.js + Shaka Player.
 * الميزات: Picture-in-Picture, Fullscreen, AirPlay, Chromecast, سرعات التشغيل,
 * الترجمات، وتعدّد مسارات الصوت (حسب توفّرها في المصدر).
 *
 * @package ToFiXStream\Player
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\Config;
use ToFiXStream\ChannelManager;

$channelId = (string) ($_GET['channel'] ?? '');
// hls.js هو الافتراضي لأنه الأكثر موثوقية مع بثّ IPTV الحيّ (TS).
$engine    = in_array($_GET['engine'] ?? '', ['videojs', 'hlsjs', 'shaka'], true) ? $_GET['engine'] : 'hlsjs';

$manager = new ChannelManager();
$channel = $channelId !== '' ? $manager->get($channelId) : null;

if (!$channel) {
    http_response_code(404);
    echo '<h2 style="font-family:sans-serif;text-align:center;margin-top:60px">القناة غير موجودة</h2>';
    exit;
}

// الرابط المُعاد بثّه عبر البروكسي (يُخفي المصدر الأصلي تمامًا).
$streamUrl = $channel['playback']['hls'];
$name = htmlspecialchars((string) $channel['name']);
$logo = htmlspecialchars((string) ($channel['logo'] ?? ''));

// العلامة المائية: في وضع FFmpeg تُحرق داخل الفيديو، أمّا في وضع Proxy فنعرضها
// كطبقة فوق المشغّل (تعمل بدون FFmpeg، وتدعم SVG والعربية بشكل مثالي).
$wm = is_array($channel['watermark'] ?? null) ? $channel['watermark'] : [];
$showOverlay = !empty($wm['enabled']) && ($channel['mode'] ?? 'proxy') !== 'ffmpeg';

/**
 * ترجمة موضع العلامة إلى أنماط CSS للطبقة فوق المشغّل.
 */
$wmStyle = static function (array $wm): string {
    $m = (int) ($wm['margin'] ?? 24) . 'px';
    return match ($wm['position'] ?? 'top-right') {
        'top-left'     => "top:$m;inset-inline-start:$m",
        'bottom-left'  => "bottom:$m;inset-inline-start:$m",
        'bottom-right' => "bottom:$m;inset-inline-end:$m",
        'center'       => 'top:50%;left:50%;transform:translate(-50%,-50%)',
        default        => "top:$m;inset-inline-end:$m", // top-right
    };
};
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $name ?> — ToFi X Stream Player</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://vjs.zencdn.net/8.16.1/video-js.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/player.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/css/player.css') ?>">
</head>
<body>
  <div class="player-shell">
    <header class="player-head">
      <div class="ch-id">
        <?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php else: ?><span class="ph"><?= mb_substr($name, 0, 1) ?></span><?php endif; ?>
        <div>
          <h1><?= $name ?></h1>
          <span class="live-dot"><span></span> LIVE · <?= htmlspecialchars((string) $channel['category']) ?></span>
        </div>
      </div>
      <div class="engine-switch">
        <?php foreach (['videojs' => 'Video.js', 'hlsjs' => 'Hls.js', 'shaka' => 'Shaka'] as $key => $label): ?>
          <a href="?channel=<?= urlencode($channelId) ?>&engine=<?= $key ?>"
             class="<?= $engine === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </header>

    <div class="video-frame">
      <?php if ($engine === 'videojs'): ?>
        <video id="player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto"
               playsinline crossorigin="anonymous" x-webkit-airplay="allow"></video>
      <?php else: ?>
        <video id="player" controls playsinline crossorigin="anonymous"
               x-webkit-airplay="allow" style="width:100%;height:100%;background:#000"></video>
      <?php endif; ?>

      <?php if ($showOverlay): ?>
        <?php $op = (float) ($wm['opacity'] ?? 0.9); ?>
        <?php if (($wm['type'] ?? 'image') === 'image' && !empty($wm['image'])): ?>
          <img class="wm-overlay" alt="logo"
               src="<?= htmlspecialchars((string) $wm['image']) ?>"
               style="<?= $wmStyle($wm) ?>;width:<?= (int) ($wm['size'] ?? 140) ?>px;opacity:<?= $op ?>">
        <?php elseif (($wm['type'] ?? '') === 'text' && !empty($wm['text'])): ?>
          <div class="wm-overlay wm-text"
               style="<?= $wmStyle($wm) ?>;color:#<?= htmlspecialchars((string) ($wm['color'] ?? 'ffffff')) ?>;opacity:<?= $op ?>;font-size:<?= (int) ($wm['size'] ?? 28) ?>px">
            <?= htmlspecialchars((string) $wm['text']) ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="player-bar">
      <div class="left">
        <button class="pbtn" id="pipBtn" title="Picture in Picture"><i class="bi bi-pip"></i></button>
        <button class="pbtn" id="castBtn" title="Chromecast"><i class="bi bi-cast"></i></button>
        <button class="pbtn" id="fsBtn" title="ملء الشاشة"><i class="bi bi-fullscreen"></i></button>
        <span class="speed">
          السرعة:
          <select id="speedSel">
            <option value="0.5">0.5x</option><option value="1" selected>1x</option>
            <option value="1.25">1.25x</option><option value="1.5">1.5x</option><option value="2">2x</option>
          </select>
        </span>
      </div>
      <div class="right muted">المحرّك: <b><?= ucfirst($engine) ?></b> · إعادة بثّ ToFi X Stream</div>
    </div>

    <footer class="player-foot muted">
      رابط البثّ (مُخفي المصدر): <code><?= htmlspecialchars($streamUrl) ?></code>
    </footer>
  </div>

  <!-- محرّكات التشغيل -->
  <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@videojs/http-streaming@3.13.2/dist/videojs-http-streaming.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.15/dist/hls.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/shaka-player@4.11.6/dist/shaka-player.compiled.js"></script>
  <script>
    window.PLAYER = {
      engine: <?= json_encode($engine) ?>,
      src: <?= json_encode($streamUrl) ?>,
      isDash: false,
    };
  </script>
  <script src="../assets/js/player.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/player.js') ?>"></script>
</body>
</html>
