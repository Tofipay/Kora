<?php
use TofiXTv\Core\View;

$days = [];
for ($i = -3; $i <= 3; $i++) {
    $d = date('Y-m-d', strtotime("{$i} days"));
    $days[] = [
        'date'  => $d,
        'label' => $i === 0 ? t('day.today') : ($i === 1 ? t('day.tomorrow') : ($i === -1 ? t('day.yesterday') : t('wd.' . date('w', strtotime($d))))),
        'num'   => date('d/m', strtotime($d)),
        'href'  => $i === 0 ? path('matches') : ($i === 1 ? path('tomorrow') : ($i === -1 ? path('yesterday') : path('matches/' . $d))),
    ];
}
?>
<div class="container page-head">
  <h1><?= e($label) ?></h1>
  <p class="page-sub"><?= e(format_date_long($date)) ?></p>
</div>

<div class="container day-nav glass-soft" role="tablist" aria-label="<?= e(t('matches.pick_day')) ?>">
  <a class="day-tab<?= $dayKey === 'live' ? ' active is-live' : '' ?>" href="<?= e(path('live')) ?>"><span class="live-dot"></span><?= e(t('nav.live')) ?></a>
  <?php foreach ($days as $d): ?>
    <a class="day-tab<?= ($d['date'] === $date && $dayKey !== 'live') ? ' active' : '' ?>" href="<?= e($d['href']) ?>">
      <b><?= e($d['label']) ?></b><small><?= e($d['num']) ?></small>
    </a>
  <?php endforeach; ?>
  <label class="day-picker">
    <input type="date" id="date-jump" value="<?= e($date) ?>" aria-label="<?= e(t('matches.pick_day')) ?>">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
  </label>
</div>

<div class="container">
<?php if (empty($grouped)): ?>
  <div class="empty-state glass-soft">
    <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M9 15c.8.7 1.8 1 3 1s2.2-.3 3-1"/></svg>
    <p><?= e($liveOnly ? t('matches.none') : t('matches.none')) ?></p>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $group): ?>
    <?= View::partial('league-group', ['group' => $group]) ?>
  <?php endforeach; ?>
<?php endif; ?>
</div>
