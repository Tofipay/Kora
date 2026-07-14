<?php
/** @var int $id @var array $sources */
use TofiXTv\Core\Lang;
$ar = Lang::current() === 'ar';
?>
<div class="container page-head">
  <h1><?= $ar ? 'اختر السيرفر' : 'Choose a server' ?></h1>
</div>

<div class="container">
  <?php if (empty($sources)): ?>
    <div class="yac-empty glass">
      <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      <p><?= $ar
        ? 'تعذّر جلب هذه القناة. تأكّد أن الخادم يصل إلى مصدر Yacine وأن المعرّف صحيح.'
        : 'Could not load this channel. Make sure the server can reach the Yacine source.' ?></p>
    </div>
  <?php else: ?>
  <div class="yac-grid">
    <?php foreach ($sources as $n => $s):
        $isDash = ($s['type'] ?? '') === 'mpd';
    ?>
    <a class="yac-card glass card-hover" href="<?= e(path('yacine/' . $id . '/' . $n)) ?>">
      <span class="yac-num"><?= (int)$n + 1 ?></span>
      <span class="yac-body">
        <b><?= e($s['label'] ?? ('S' . ($n + 1))) ?></b>
        <span class="yac-tags">
          <span class="yac-tag <?= $isDash ? 'dash' : 'hls' ?>"><?= $isDash ? 'DASH' : 'HLS' ?></span>
          <?php if (!empty($s['drm'])): ?>
            <span class="yac-tag drm"><?= $ar ? 'مشفّر' : 'DRM' ?></span>
          <?php endif; ?>
        </span>
      </span>
      <svg class="yac-play" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
