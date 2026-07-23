<?php
/**
 * Football pitch with both formations.
 * Expects: $lineups (teamId => ['lineup'=>[], 'substitutions'=>[]]),
 *          $homeId, $awayId, $home, $away (team arrays).
 *
 * Line letters from the API: G goalkeeper · D defence · M midfield ·
 * S second line of attack · F forwards. formation_position orders players
 * inside a line.
 */

$buildLines = function (array $side): array {
    $order = ['G' => 0, 'D' => 1, 'M' => 2, 'S' => 3, 'F' => 4];
    $lines = [];
    foreach (($side['lineup'] ?? []) as $lp) {
        $letter = strtoupper((string)($lp['position'] ?? ''));
        if (!isset($order[$letter])) $letter = 'M';
        $lines[$letter][] = $lp;
    }
    foreach ($lines as &$line) {
        usort($line, fn($a, $b) => (int)($a['formation_position'] ?? 0) <=> (int)($b['formation_position'] ?? 0));
    }
    unset($line);
    uksort($lines, fn($a, $b) => $order[$a] <=> $order[$b]);
    return $lines;
};

$formationLabel = function (array $lines): string {
    $parts = [];
    foreach ($lines as $letter => $players) {
        if ($letter === 'G') continue;
        $parts[] = count($players);
    }
    return implode('-', $parts);
};

$ratingClass = function ($rating): string {
    $r = (float)$rating;
    if ($r >= 8)   return 'r-elite';
    if ($r >= 7)   return 'r-good';
    if ($r >= 6)   return 'r-ok';
    return 'r-low';
};

$renderChip = function (array $lp) use ($ratingClass): string {
    $pl = $lp['player'] ?? [];
    $name = player_label($pl);
    $short = mb_strlen($name) > 14 ? mb_substr($name, 0, 13) . '…' : $name;
    ob_start(); ?>
  <a class="fp-chip" href="<?= e(player_url(['id' => (int)($pl['row_id'] ?? 0), 'title' => $name])) ?>" title="<?= e($name) ?>">
    <span class="fp-photo">
      <img src="<?= e(player_img($pl, '64')) ?>" alt="<?= e($name) ?>" width="46" height="46" loading="lazy" decoding="async">
      <b class="fp-num"><?= (int)($pl['player_number'] ?? 0) ?></b>
      <?php if (!empty($lp['rating']) && (float)$lp['rating'] > 0): ?>
        <b class="fp-rating <?= $ratingClass($lp['rating']) ?>"><?= e((string)$lp['rating']) ?></b>
      <?php endif; ?>
      <?php if (!empty($lp['captain'])): ?><i class="fp-cap">C</i><?php endif; ?>
      <span class="fp-marks">
        <?php $goals = (int)($lp['goal'] ?? 0); if ($goals > 0): ?>
          <i class="fp-goal" title="<?= e(t('event.goal')) ?>">⚽<?= $goals > 1 ? '×' . $goals : '' ?></i>
        <?php endif; ?>
        <?php if (!empty($lp['own_goal'])): ?><i class="fp-goal og" title="<?= e(t('event.owngoal')) ?>">⚽</i><?php endif; ?>
        <?php if (!empty($lp['red'])): ?><i class="fp-card red"></i>
        <?php elseif (!empty($lp['yellow'])): ?><i class="fp-card yellow"></i><?php endif; ?>
        <?php if (!empty($lp['substitute'])): ?>
          <i class="fp-sub" title="<?= e(t('event.sub_out')) ?><?= !empty($lp['substitute_time']) ? ' ' . (int)$lp['substitute_time'] . '′' : '' ?>">
            <svg viewBox="0 0 12 12" width="10" height="10"><path d="M6 2v8M6 10 3 7m3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </i>
        <?php endif; ?>
      </span>
    </span>
    <span class="fp-name"><?= e($short) ?></span>
  </a>
    <?php return (string)ob_get_clean();
};

$homeSide = $lineups[$homeId] ?? null;
$awaySide = $lineups[$awayId] ?? null;
$homeLines = is_array($homeSide) ? $buildLines($homeSide) : [];
$awayLines = is_array($awaySide) ? $buildLines($awaySide) : [];
?>
<div class="pitch-card card">
  <div class="pitch-header">
    <span class="pitch-team">
      <img src="<?= e(team_img($home)) ?>" alt="" width="26" height="26">
      <b><?= e(team_name($home)) ?></b>
      <?php if ($homeLines): ?><em class="pitch-formation"><?= e($formationLabel($homeLines)) ?></em><?php endif; ?>
    </span>
    <span class="pitch-team away">
      <?php if ($awayLines): ?><em class="pitch-formation"><?= e($formationLabel($awayLines)) ?></em><?php endif; ?>
      <b><?= e(team_name($away)) ?></b>
      <img src="<?= e(team_img($away)) ?>" alt="" width="26" height="26">
    </span>
  </div>

  <div class="pitch" role="img" aria-label="<?= e(t('match.lineups')) ?>">
    <i class="pl pl-outline"></i><i class="pl pl-halfway"></i><i class="pl pl-circle"></i><i class="pl pl-spot-c"></i>
    <i class="pl pl-box-top"></i><i class="pl pl-box6-top"></i><i class="pl pl-box-bottom"></i><i class="pl pl-box6-bottom"></i>
    <i class="pl pl-arc-top"></i><i class="pl pl-arc-bottom"></i>

    <div class="pitch-side side-home">
      <?php foreach ($homeLines as $line): ?>
        <div class="pitch-line"><?php foreach ($line as $lp) echo $renderChip($lp); ?></div>
      <?php endforeach; ?>
    </div>
    <div class="pitch-side side-away">
      <?php foreach (array_reverse($awayLines, true) as $line): ?>
        <div class="pitch-line"><?php foreach ($line as $lp) echo $renderChip($lp); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
