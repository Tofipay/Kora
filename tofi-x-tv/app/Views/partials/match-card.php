<?php
/** Match row card. Expects: $m (match array) */
$home = team_of($m, 'home');
$away = team_of($m, 'away');
$state = match_state($m);
$mid = (int)($m['match_id'] ?? 0);
$hs = (int)($m['home_scores'] ?? 0);
$as = (int)($m['away_scores'] ?? 0);
?>
<a class="match-card card-hover" href="<?= e(match_url($m)) ?>" data-match="<?= $mid ?>" data-state="<?= e($state['key']) ?>">
  <div class="mc-team mc-home">
    <img src="<?= e(team_img($home)) ?>" alt="<?= e(team_name($home)) ?>" width="34" height="34" loading="lazy" decoding="async">
    <span class="mc-name"><?= e(team_name($home)) ?></span>
  </div>
  <div class="mc-center">
    <?php if ($state['started']): ?>
      <span class="mc-score" data-score><span data-hs><?= $hs ?></span><i>-</i><span data-as><?= $as ?></span></span>
      <span class="mc-status <?= $state['live'] ? 'is-live' : 'is-ft' ?>" data-status<?= live_clock_attrs($state) ?>><?= e($state['label']) ?></span>
    <?php else: ?>
      <span class="mc-time" data-ts="<?= (int)($m['match_timestamp'] ?? 0) ?>"><?= e($state['label']) ?></span>
      <span class="mc-status is-soon" data-status><?= e(t('status.notstarted')) ?></span>
    <?php endif; ?>
  </div>
  <div class="mc-team mc-away">
    <img src="<?= e(team_img($away)) ?>" alt="<?= e(team_name($away)) ?>" width="34" height="34" loading="lazy" decoding="async">
    <span class="mc-name"><?= e(team_name($away)) ?></span>
  </div>
  <button class="fav-btn" data-fav="match" data-id="<?= $mid ?>" data-title="<?= e(team_name($home) . ' - ' . team_name($away)) ?>" data-url="<?= e(match_url($m)) ?>" aria-label="<?= e(t('fav.add')) ?>" onclick="event.preventDefault();event.stopPropagation();QF.toggle(this)">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
  </button>
</a>
