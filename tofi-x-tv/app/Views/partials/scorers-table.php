<?php
/** Scorers list. Expects: $scorers, optional $leagueId (link hint), $metric ('goals'|'assist') */
$metric = $metric ?? 'goals';
$leagueId = $leagueId ?? 0;
?>
<ol class="scorers-list">
<?php $i = 0; foreach ($scorers as $s): $i++;
    $pi = $s['player_info'] ?? [];
    $val = $metric === 'goals' ? (int)($s['goals'] ?? 0) : (int)($s['assist'] ?? $s['assists'] ?? 0);
    $pen = (int)($s['score_penalty'] ?? 0);
?>
  <li class="scorer-row">
    <span class="sc-rank"><?= $i ?></span>
    <a class="sc-player" href="<?= e(player_url(['id' => (int)($pi['id'] ?? $s['player_id'] ?? 0), 'title' => player_label($pi)]) . ($leagueId ? '?lg=' . (int)$leagueId : '')) ?>">
      <img src="<?= e(player_img($pi, '64')) ?>" alt="" width="34" height="34" loading="lazy" decoding="async">
      <span class="sc-meta">
        <strong><?= e(player_label($pi)) ?></strong>
        <?php if (!empty($pi['team_name'])): ?><small><?= e($pi['team_name']) ?></small><?php endif; ?>
      </span>
    </a>
    <span class="sc-val">
      <strong><?= $val ?></strong>
      <?php if ($metric === 'goals' && $pen > 0): ?><small>(<?= $pen ?> <?= e(t('scorers.pens')) ?>)</small><?php endif; ?>
    </span>
  </li>
<?php endforeach; ?>
</ol>
