<?php
/** @var array $servers @var array $m */
use TofiXTv\Core\Lang;

$hasHome = !empty($home);
$serversJson = array_map(fn($s) => [
    'name' => (string)$s['name'],
    'url'  => (string)$s['url'],
    'type' => (string)$s['type'],
    'drm'  => $s['drm'] ?? null,   // ClearKey for DASH sources (dash.js)
], $servers);
?>
<section class="watch-page" dir="<?= Lang::dir() ?>">
  <div class="container watch-top">
    <a class="wt-back" href="<?= e($m ? match_url($m) : path('live')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      <?= e(t('misc.back')) ?>
    </a>
    <?php if ($m): ?>
    <div class="wt-teams">
      <span><img src="<?= e(team_img($home)) ?>" alt="" width="24" height="24"><b><?= e(team_name($home)) ?></b></span>
      <?php $st = match_state($m); if ($st['started']): ?>
        <em class="wt-score"><span><?= (int)($m['home_scores'] ?? 0) ?></span><i class="wt-vs">-</i><span><?= (int)($m['away_scores'] ?? 0) ?></span></em>
      <?php else: ?><em class="wt-vs"><?= e(t('match.vs')) ?></em><?php endif; ?>
      <span><b><?= e(team_name($away)) ?></b><img src="<?= e(team_img($away)) ?>" alt="" width="24" height="24"></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="container">
    <div class="player-shell glass" id="qplayer"
         data-servers='<?= e(json_encode($serversJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
         <?php if (!empty($refreshUrl)): ?>data-refresh="<?= e($refreshUrl) ?>" data-ttl="<?= (int)($ttl ?? 0) ?>"<?php endif; ?>
         data-hls="/assets/vendor/hls.min.js" data-dash="/assets/vendor/dash.all.min.js">
      <div class="pl-stage">
        <video id="qvideo" class="pl-video" playsinline crossorigin="anonymous" poster="/assets/brand/splash.png"></video>

        <div class="pl-loading" data-loading><span class="pl-spinner"></span></div>
        <div class="pl-arhint" data-arhint hidden></div>

        <div class="pl-error" data-error hidden>
          <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
          <p data-error-msg><?= e(t('misc.error')) ?></p>
          <button class="btn btn-primary" data-retry><?= e(t('misc.retry')) ?></button>
        </div>

        <div class="pl-controls" data-controls>
          <button class="pl-big-play" data-bigplay aria-label="play">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
          </button>
          <div class="pl-bar">
            <button class="pl-btn" data-toggle aria-label="play/pause">
              <svg class="ic-play" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
              <svg class="ic-pause" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" hidden><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
            </button>
            <div class="pl-vol">
              <button class="pl-btn" data-mute aria-label="mute">
                <svg class="ic-vol" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 10v4h4l5 5V5L7 10H3z"/><path d="M16 8a5 5 0 0 1 0 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <svg class="ic-mute" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" hidden><path d="M3 10v4h4l5 5V5L7 10H3z"/><path d="m16 9 5 6M21 9l-5 6" stroke="currentColor" stroke-width="2" fill="none"/></svg>
              </button>
              <input type="range" class="pl-volbar" data-volbar min="0" max="1" step="0.05" value="1" aria-label="volume">
            </div>
            <span class="pl-live" data-live><span class="live-dot"></span>LIVE</span>
            <span class="pl-time" data-time hidden>0:00</span>
            <div class="pl-spacer"></div>

            <div class="pl-menu" data-menu="quality" hidden>
              <button class="pl-btn pl-txt" data-menu-btn>HD</button>
              <div class="pl-menu-list" data-menu-list></div>
            </div>
            <div class="pl-menu" data-menu="speed">
              <button class="pl-btn pl-txt" data-menu-btn>1x</button>
              <div class="pl-menu-list">
                <?php foreach (['0.5','0.75','1','1.25','1.5','2'] as $sp): ?>
                  <button data-speed="<?= $sp ?>"<?= $sp === '1' ? ' class="on"' : '' ?>><?= $sp ?>x</button>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="pl-btn" data-cast hidden aria-label="cast">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 16a5 5 0 0 1 5 5M2 12a9 9 0 0 1 9 9M2 20h.01"/><rect x="2" y="4" width="20" height="14" rx="2"/></svg>
            </button>
            <button class="pl-btn" data-airplay hidden aria-label="airplay">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-1"/><path d="m12 15 5 6H7z" fill="currentColor"/></svg>
            </button>
            <button class="pl-btn" data-pip aria-label="picture in picture">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><rect x="12" y="11" width="7" height="5" rx="1" fill="currentColor"/></svg>
            </button>
            <button class="pl-btn" data-aspect aria-label="aspect ratio" title="<?= e(Lang::current() === 'ar' ? 'نسبة العرض' : 'Aspect ratio') ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3"/></svg>
            </button>
            <button class="pl-btn" data-fs aria-label="fullscreen">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
            </button>
          </div>
        </div>
      </div>

      <?php if (count($servers) > 1): ?>
      <div class="pl-servers" data-servers-bar>
        <span class="pls-label"><?= e(Lang::current() === 'ar' ? 'السيرفرات' : 'Servers') ?></span>
        <?php foreach ($servers as $i => $s): ?>
          <button class="pls-btn<?= $i === 0 ? ' on' : '' ?>" data-server="<?= (int)$i ?>">
            <span class="pls-dot"></span><?= e($s['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <p class="watch-note"><?= e(Lang::current() === 'ar'
        ? 'إذا لم يعمل السيرفر الحالي، جرّب سيرفراً آخر. البث من مصادر خارجية.'
        : 'If the current server does not work, try another one. Streams are provided by third parties.') ?></p>
  </div>
</section>
<script src="<?= e(asset_url('/assets/js/watch.js')) ?>" defer></script>
