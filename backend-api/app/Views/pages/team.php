<?php use Qamhad\Core\View; ?>
<section class="entity-hero">
  <div class="mh-bg" aria-hidden="true"></div>
  <div class="container entity-inner">
    <img class="entity-logo" src="<?= e(team_img($team, '128')) ?>" alt="<?= e(team_name($team)) ?>" width="76" height="76">
    <div>
      <h1><?= e(team_name($team)) ?></h1>
      <p class="page-sub">
        <?php if ($league): ?><a href="<?= e(league_url($league)) ?>"><?= e($league['title'] ?? '') ?></a><?php endif; ?>
        <?php if (!empty($team['world_ranking'])): ?> · <?= e(t('team.world_ranking')) ?> #<?= (int)$team['world_ranking'] ?><?php endif; ?>
      </p>
      <?php if (!empty($summary['form'])): ?>
      <div class="form-badges">
        <?php foreach (array_reverse(array_slice($summary['form'], 0, 5)) as $f): ?>
          <i class="form-badge form-<?= strtolower($f) ?>"><?= $f === 'W' ? '✓' : ($f === 'L' ? '✗' : '=') ?></i>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <button class="fav-btn standalone" data-fav="team" data-id="<?= (int)$id ?>" data-title="<?= e(team_name($team)) ?>" data-url="<?= e(team_url($team)) ?>" data-img="<?= e(team_img($team)) ?>" onclick="QF.toggle(this)" aria-label="<?= e(t('fav.add')) ?>">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
    </button>
  </div>
  <div class="container">
    <nav class="tabs glass-soft" role="tablist">
      <button class="tab active" data-tab="overview"><?= e(t('match.overview')) ?></button>
      <button class="tab" data-tab="squad"><?= e(t('team.squad')) ?></button>
      <button class="tab" data-tab="fixtures"><?= e(t('team.fixtures')) ?></button>
      <button class="tab" data-tab="results"><?= e(t('team.results')) ?></button>
      <button class="tab" data-tab="standings"><?= e(t('team.standing')) ?></button>
      <button class="tab" data-tab="news"><?= e(t('team.news')) ?></button>
    </nav>
  </div>
</section>

<div class="container match-body">
  <section class="tab-panel active" data-panel="overview">
    <div class="two-col">
      <div>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('team.stats')) ?></h3>
          <div class="team-kpis">
            <div class="kpi"><b><?= (int)$summary['won'] ?></b><span><?= e(t('standings.win')) ?></span></div>
            <div class="kpi"><b><?= (int)$summary['drawn'] ?></b><span><?= e(t('standings.draw')) ?></span></div>
            <div class="kpi"><b><?= (int)$summary['lost'] ?></b><span><?= e(t('standings.lose')) ?></span></div>
            <div class="kpi"><b><?= (int)$summary['gf'] ?></b><span><?= e(t('standings.gf')) ?></span></div>
            <div class="kpi"><b><?= (int)$summary['ga'] ?></b><span><?= e(t('standings.ga')) ?></span></div>
          </div>
          <?php if ($teamRow): ?>
          <p class="muted" style="margin:10px 0 0">
            <?= e(t('team.standing')) ?>: <b>#<?= (int)array_search($teamRow, $standingRows, true) + 1 ?></b> ·
            <?= e(t('standings.points')) ?>: <b><?= (int)($teamRow['points'] ?? 0) ?></b>
          </p>
          <?php endif; ?>
        </div>

        <?php if (!empty($fixtures)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('team.fixtures')) ?></h3>
          <div class="league-matches">
            <?php foreach (array_slice($fixtures, 0, 5) as $fm): ?><?= View::partial('match-card', ['m' => $fm]) ?><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <?php if (!empty($topPlayers)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('team.top_players')) ?></h3>
          <?= View::partial('scorers-table', ['scorers' => $topPlayers, 'leagueId' => (int)($league['url_id'] ?? 0)]) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($results)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('team.results')) ?></h3>
          <div class="league-matches">
            <?php foreach (array_slice($results, 0, 5) as $fm): ?><?= View::partial('match-card', ['m' => $fm]) ?><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="tab-panel" data-panel="fixtures">
    <?php if (empty($fixtures)): ?><div class="empty-state glass-soft"><p><?= e(t('matches.none')) ?></p></div>
    <?php else: ?>
      <div class="league-matches">
        <?php $lastDate = ''; foreach ($fixtures as $fm): $d = (string)($fm['match_date'] ?? ''); ?>
          <?php if ($d !== $lastDate): $lastDate = $d; ?><h4 class="date-sep"><?= e(format_date_long($d)) ?></h4><?php endif; ?>
          <?= View::partial('match-card', ['m' => $fm]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="results">
    <?php if (empty($results)): ?><div class="empty-state glass-soft"><p><?= e(t('matches.none')) ?></p></div>
    <?php else: ?>
      <div class="league-matches">
        <?php $lastDate = ''; foreach ($results as $fm): $d = (string)($fm['match_date'] ?? ''); ?>
          <?php if ($d !== $lastDate): $lastDate = $d; ?><h4 class="date-sep"><?= e(format_date_long($d)) ?></h4><?php endif; ?>
          <?= View::partial('match-card', ['m' => $fm]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="squad">
    <?php if (!empty($coach)): ?>
    <div class="card glass-soft coach-card">
      <img src="<?= e(media_url('coach', '48', \Qamhad\Core\Www::cdnFile((string)($coach['image'] ?? '')), '/assets/img/player.svg')) ?>" alt="<?= e($coach['title']) ?>" width="44" height="44" loading="lazy">
      <div><b><?= e($coach['title']) ?></b><small><?= e(t('match.coach')) ?></small></div>
    </div>
    <?php endif; ?>
    <?php if (empty($squadGroups)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('match.no_lineup')) ?></p></div>
    <?php else: ?>
      <?php foreach ($squadGroups as $grp): ?>
      <div class="card glass-soft squad-group">
        <h3 class="card-title squad-line"><?= e($grp['label']) ?> <span class="squad-count"><?= count($grp['players']) ?></span></h3>
        <div class="squad-grid">
          <?php foreach ($grp['players'] as $pl):
              $pid = (int)($pl['row_id'] ?? $pl['id'] ?? 0);
              $posLabel = (string)($pl['position_name'] ?? $pl['center'] ?? $pl['position'] ?? '');
          ?>
          <a class="squad-player card-hover" href="<?= e(player_url(['id' => $pid, 'title' => player_label($pl)])) ?>">
            <span class="sp-photo">
              <img src="<?= e(player_img($pl, '64')) ?>" alt="<?= e(player_label($pl)) ?>" width="52" height="52" loading="lazy" decoding="async">
              <?php if (!empty($pl['player_number']) || !empty($pl['pn'])): ?><b class="sp-num"><?= (int)($pl['player_number'] ?? $pl['pn']) ?></b><?php endif; ?>
            </span>
            <b class="sp-name"><?= e(player_label($pl)) ?></b>
            <?php if ($posLabel !== ''): ?><small class="sp-pos"><?= e($posLabel) ?></small><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="standings">
    <?php if (empty($standingRows)): ?><div class="empty-state glass-soft"><p><?= e(t('standings.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft"><?= View::partial('standings-table', ['rows' => $standingRows, 'highlightTeam' => $id]) ?></div>
    <?php endif; ?>
  </section>

  <section class="tab-panel" data-panel="news">
    <?php if (empty($teamNews)): ?><div class="empty-state glass-soft"><p><?= e(t('news.none')) ?></p></div>
    <?php else: ?>
      <div class="news-list">
        <?php foreach ($teamNews as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
