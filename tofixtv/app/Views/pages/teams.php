<div class="container page-head">
  <h1><?= e(t('home.popular_teams')) ?></h1>
</div>
<div class="container">
  <div class="leagues-grid">
    <?php foreach ($teams as $tm): ?>
    <a class="league-card glass-soft card-hover" href="<?= e(team_url($tm)) ?>">
      <img src="<?= e(team_img($tm, '128')) ?>" alt="<?= e(team_name($tm)) ?>" width="42" height="42" loading="lazy" decoding="async">
      <b><?= e(team_name($tm)) ?></b>
      <?php if (!empty($tm['world_ranking'])): ?><small class="muted">FIFA #<?= (int)$tm['world_ranking'] ?></small><?php endif; ?>
      <button class="fav-btn" data-fav="team" data-id="<?= (int)($tm['row_id'] ?? 0) ?>" data-title="<?= e(team_name($tm)) ?>" data-url="<?= e(team_url($tm)) ?>" data-img="<?= e(team_img($tm)) ?>" onclick="event.preventDefault();QF.toggle(this)" aria-label="<?= e(t('fav.add')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
      </button>
    </a>
    <?php endforeach; ?>
  </div>
</div>
