<?php
/** Standings table. Expects: $rows, optional $compact, optional $highlightTeam */
$compact = $compact ?? false;
$highlightTeam = $highlightTeam ?? 0;
?>
<div class="table-wrap">
<table class="standings<?= $compact ? ' compact' : '' ?>">
  <thead>
    <tr>
      <th class="col-pos">#</th>
      <th class="col-team"><?= e(t('standings.team')) ?></th>
      <th><?= e(t('standings.played')) ?></th>
      <?php if (!$compact): ?>
      <th><?= e(t('standings.win')) ?></th>
      <th><?= e(t('standings.draw')) ?></th>
      <th><?= e(t('standings.lose')) ?></th>
      <th><?= e(t('standings.gf')) ?></th>
      <th><?= e(t('standings.ga')) ?></th>
      <?php endif; ?>
      <th><?= e(t('standings.gd')) ?></th>
      <th class="col-pts"><?= e(t('standings.points')) ?></th>
    </tr>
  </thead>
  <tbody>
  <?php $pos = 0; foreach ($rows as $r): $pos++;
      $team = $r['team_name'] ?? [];
      $color = (string)($r['color'] ?? '');
      $isMe = $highlightTeam && (int)($r['team_id'] ?? 0) === $highlightTeam;
  ?>
    <tr<?= $isMe ? ' class="row-highlight"' : '' ?>>
      <td class="col-pos"><span class="pos-pill" <?= $color ? 'style="--pos-color:' . e($color) . '"' : '' ?>><?= $pos ?></span></td>
      <td class="col-team">
        <a href="<?= e(team_url($team)) ?>">
          <img src="<?= e(team_img($team)) ?>" alt="" width="22" height="22" loading="lazy" decoding="async">
          <span><?= e(team_name($team)) ?></span>
        </a>
      </td>
      <td><?= (int)($r['play'] ?? 0) ?></td>
      <?php if (!$compact): ?>
      <td><?= (int)($r['wins'] ?? 0) ?></td>
      <td><?= (int)($r['draw'] ?? 0) ?></td>
      <td><?= (int)($r['lose'] ?? 0) ?></td>
      <td><?= (int)($r['for'] ?? 0) ?></td>
      <td><?= (int)($r['against'] ?? 0) ?></td>
      <?php endif; ?>
      <td><?= (int)($r['diff'] ?? 0) ?></td>
      <td class="col-pts"><strong><?= (int)($r['points'] ?? 0) ?></strong></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
