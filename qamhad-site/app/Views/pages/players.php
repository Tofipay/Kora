<div class="container page-head">
  <h1><?= e(t('home.top_scorers')) ?></h1>
</div>
<div class="container">
  <div class="players-grid">
    <?php foreach ($players as $s): $pi = $s['player_info'] ?? []; ?>
    <a class="player-card glass-soft card-hover" href="<?= e(player_url(['id' => (int)($pi['id'] ?? $s['player_id'] ?? 0), 'title' => player_label($pi)]) . (!empty($s['league']['url_id']) ? '?lg=' . (int)$s['league']['url_id'] : '')) ?>">
      <img src="<?= e(player_img($pi, '64')) ?>" alt="<?= e(player_label($pi)) ?>" width="64" height="64" loading="lazy" decoding="async">
      <b><?= e(player_label($pi)) ?></b>
      <?php if (!empty($pi['team_name'])): ?><small class="muted"><?= e($pi['team_name']) ?></small><?php endif; ?>
      <span class="pc-goals"><?= (int)($s['goals'] ?? 0) ?> <?= e(t('scorers.goals')) ?></span>
      <?php if (!empty($s['league']['title'])): ?><small class="pc-league"><?= e($s['league']['title']) ?></small><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
