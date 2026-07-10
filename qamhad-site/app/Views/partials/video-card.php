<?php
/**
 * Video highlight card. Expects: $v (normalized VideoFeed item).
 * YouTube videos open the in-site player (/video/{id}); other providers
 * open externally. Thumbnail lazy-loads over a branded gradient fallback.
 */
$yt    = (string)($v['youtube_id'] ?? '');
$thumb = (string)($v['thumbnail'] ?? '');
$title = (string)($v['title'] ?? '');
$champ = (string)($v['champ_title'] ?? '');
$date  = (string)($v['created_at'] ?? '');
$dTs   = $date !== '' ? to_ts($date) : 0;
$dLabel = $dTs ? format_date_short($dTs) : '';

$isInternal = $yt !== '';
$href   = $isInternal ? path('video/' . $yt) : (string)($v['video_url'] ?? '#');
$target = $isInternal ? '' : ' target="_blank" rel="noopener nofollow"';
$prov   = $v['video_type'] === 'youtube' ? 'YouTube' : ($v['video_type'] === 'fifa' ? 'FIFA+' : '');
?>
<a class="vcard card-hover" href="<?= e($href) ?>"<?= $target ?> data-video-card>
  <span class="vc-thumb">
    <span class="vc-thumb-ph" aria-hidden="true">
      <img src="/assets/brand/icon.svg" alt="" width="46" height="46" loading="lazy">
    </span>
    <?php if ($thumb !== ''): ?>
    <img class="vc-img" src="<?= e($thumb) ?>" alt="" loading="lazy" width="480" height="270"
         onerror="this.remove()">
    <?php endif; ?>
    <span class="vc-play" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
    </span>
    <?php if ($prov !== ''): ?><span class="vc-provider" dir="ltr"><?= e($prov) ?></span><?php endif; ?>
  </span>
  <span class="vc-body">
    <b class="vc-title"><?= e($title) ?></b>
    <span class="vc-meta">
      <?php if ($champ !== ''): ?><span class="vc-champ"><?= e($champ) ?></span><?php endif; ?>
      <?php if ($dLabel !== ''): ?><span class="vc-date"><?= e($dLabel) ?></span><?php endif; ?>
    </span>
  </span>
</a>
