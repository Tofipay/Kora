<?php
/**
 * Match timeline. Expects: $events (upstream order = newest first), $homeId, $awayId.
 * Period markers (type 100) render as separators.
 */
$icons = [
    'goal'      => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="m12 7.5 4.3 3.1-1.7 5H9.4l-1.7-5z" fill="currentColor" stroke="none"/></svg>',
    'owngoal'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path d="m12 7.5 4.3 3.1-1.7 5H9.4l-1.7-5z" fill="currentColor" stroke="none"/></svg>',
    'yellow'    => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="7" y="4" width="10" height="16" rx="2" fill="#facc15"/></svg>',
    'red'       => '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="7" y="4" width="10" height="16" rx="2" fill="#ef4444"/></svg>',
    'sub'       => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke-width="2"><path d="M7 4v12m0 0-3-3m3 3 3-3" stroke="#22c55e"/><path d="M17 20V8m0 0-3 3m3-3 3 3" stroke="#ef4444"/></svg>',
    'missed'    => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ef4444" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 8 8 8M16 8l-8 8"/></svg>',
    'cancelled' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ef4444" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 8 8 8M16 8l-8 8"/></svg>',
    'whistle'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="14" r="5"/><path d="M13.5 11 21 7l-1.5-3-7 4.5M14 14a5 5 0 0 0-5-5"/></svg>',
    'dot'       => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="12" cy="12" r="3"/></svg>',
];
?>
<ol class="timeline">
<?php foreach ($events as $ev):
    $et = event_type($ev);
    $minute = (int)($ev['time_minute'] ?? 0);
    $plus = (int)($ev['time_plus'] ?? 0);
    $minStr = $minute > 0 ? $minute . ($plus > 0 && $plus !== $minute ? '+' . $plus : '') . '′' : '';
    $isHome = (int)($ev['team_id'] ?? 0) === (int)$homeId;

    if ($et['key'] === 'period'): ?>
  <li class="tl-period">
    <span class="tl-period-label"><?= $icons['whistle'] ?> <?= e(period_label($ev)) ?></span>
  </li>
    <?php continue; endif; ?>
  <?php
    /* Substitution reason rides on `status` / `status_name`:
       status 9 = injury sub ("الإصابة"), status 8 = tactical ("تبديل"). */
    $isInjury = $et['key'] === 'sub'
        && ((int)($ev['status'] ?? 0) === 9
            || (is_string($ev['status_name'] ?? null) && str_contains((string)$ev['status_name'], 'صاب')));
  ?>
  <li class="tl-event <?= $isHome ? 'side-home' : 'side-away' ?> ev-<?= e($et['key']) ?><?= $isInjury ? ' ev-injury' : '' ?>">
    <span class="tl-min"><?= e($minStr) ?></span>
    <span class="tl-icon"><?= $icons[$et['icon']] ?? $icons['dot'] ?></span>
    <span class="tl-body">
      <b><?= e(player_label($ev['player_name'] ?? null, $et['label'])) ?></b>
      <small><?php if ($et['key'] === 'sub'): ?><?= e(t('event.sub_out')) ?><?php else: ?><?= e($et['label']) ?><?php endif; ?><?php
        $assist = player_label($ev['assist_player_name'] ?? null);
        if ($assist !== ''): ?> · <?= e($et['key'] === 'sub' ? t('event.sub_in') : t('event.assist')) ?>: <?= e($assist) ?><?php endif; ?></small>
    </span>
    <?php if ($isInjury): ?>
      <span class="tl-chip tl-injury" title="<?= e((string)($ev['status_name'] ?? t('event.injury'))) ?>">
        <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <?= e(t('event.injury')) ?>
      </span>
    <?php endif; ?>
    <?php if (!empty($ev['event_video'])): ?>
      <a class="tl-video" href="<?= e((string)$ev['event_video']) ?>" target="_blank" rel="noopener nofollow" aria-label="video">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </a>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ol>
