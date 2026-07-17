<?php
/**
 * Head-to-head stat bars. Expects: $hStats, $aStats, $labels (stat keys), $home, $away.
 * xG is shown as a placeholder row when absent upstream.
 */
?>
<div class="stat-bars">
<?php foreach ($labels as $key):
    if ($key === 'expected_goals') {
        // Upstream reports expected goals as `xg` (string, e.g. "0.96")
        $hRaw = $hStats['expected_goals'] ?? $hStats['xg'] ?? null;
        $aRaw = $aStats['expected_goals'] ?? $aStats['xg'] ?? null;
        if ($hRaw === null && $aRaw === null) continue; // xG not provided upstream yet
        $hv = (float)$hRaw;
        $av = (float)$aRaw;
    } else {
        $hv = (float)($hStats[$key] ?? 0);
        $av = (float)($aStats[$key] ?? 0);
    }
    if ($hv == 0 && $av == 0 && !in_array($key, ['ball_possession'], true)) continue;
    $total = $hv + $av;
    $hp = $total > 0 ? round($hv / $total * 100) : 50;
?>
  <div class="stat-row">
    <b class="sv sv-h"><?= $key === 'ball_possession' || $key === 'passes_percentage' ? $hv . '%' : e(rtrim(rtrim(number_format($hv, 2, '.', ''), '0'), '.')) ?></b>
    <div class="stat-mid">
      <span class="stat-label"><?= e(t('stat.' . $key)) ?></span>
      <div class="stat-track" role="img" aria-label="<?= e(t('stat.' . $key)) ?>">
        <span class="fill-h" style="width:<?= $hp ?>%"></span>
        <span class="fill-a" style="width:<?= 100 - $hp ?>%"></span>
      </div>
    </div>
    <b class="sv sv-a"><?= $key === 'ball_possession' || $key === 'passes_percentage' ? $av . '%' : e(rtrim(rtrim(number_format($av, 2, '.', ''), '0'), '.')) ?></b>
  </div>
<?php endforeach; ?>
</div>
