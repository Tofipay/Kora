<?php
use Qamhad\Core\View;

$mid = (int)$m['match_id'];
$hs = (int)($m['home_scores'] ?? 0);
$as = (int)($m['away_scores'] ?? 0);
$homeId = (int)($home['row_id'] ?? 0);
$awayId = (int)($away['row_id'] ?? 0);
$ts = (int)($m['match_timestamp'] ?? 0);
$videoLinks = is_array($m['video_links'] ?? null) ? $m['video_links'] : [];

/* Extra time & penalty shootout (see match_periods) */
$periods  = is_array($periods ?? null) ? $periods : match_periods($m);
$pens     = $periods['pens'] ?? null;                 // [home, away] shootout
$penWinner = $pens ? ($pens[0] > $pens[1] ? 'home' : ($pens[1] > $pens[0] ? 'away' : null)) : null;
$penWinnerName = $penWinner === 'home' ? team_name($home) : ($penWinner === 'away' ? team_name($away) : '');

/* Key events = everything except period markers & subs & yellows */
$keyEvents = array_values(array_filter($events, function ($ev) {
    $k = event_type($ev)['key'];
    return !in_array($k, ['sub', 'yellow', 'other'], true);
}));
$mainReferee = null;
foreach ($referees as $r) { if ((int)($r['referee_type'] ?? 0) === 1 || $mainReferee === null) { $mainReferee = $r; if ((int)($r['referee_type'] ?? 0) === 1) break; } }

$statLabels = [
    'ball_possession','expected_goals','total_shots','shots_on_goal','shots_off_goal','blocked_shots',
    'shots_insidebox','shots_outsidebox','corner_kicks','offsides','fouls','yellow_cards','red_cards',
    'goalkeeper_saves','total_passes','passes_percentage','freekick','cross_ball','throwin','goalkick','goal_attempt',
];
$statsByTeam = [];
foreach ($stats as $srow) { if (isset($srow['team_id'])) $statsByTeam[(int)$srow['team_id']] = $srow; }
$hStats = $statsByTeam[$homeId] ?? [];
$aStats = $statsByTeam[$awayId] ?? [];
?>
<section class="match-hero" data-match-page="<?= $mid ?>">
  <div class="mh-bg" aria-hidden="true"></div>
  <div class="container mh-inner">
    <?php
      // Visually-hidden semantic headings for search engines. They carry the
      // same match facts already shown on the page — no layout change.
      $seoH1 = $seoH1 ?? '';
      $seoH2 = is_array($seoH2 ?? null) ? $seoH2 : [];
    ?>
    <?php if ($seoH1 !== ''): ?><h1 class="seo-only"><?= e($seoH1) ?></h1><?php endif; ?>
    <?php foreach ($seoH2 as $seoHead): ?><h2 class="seo-only"><?= e($seoHead) ?></h2><?php endforeach; ?>
    <a class="mh-league" href="<?= e(league_url($m['championship'] ?? [])) ?>">
      <img src="<?= e(league_img($m['championship'] ?? [])) ?>" alt="" width="20" height="20">
      <span><?= e($m['championship']['title'] ?? '') ?></span>
    </a>
    <div class="mh-teams">
      <a class="mh-team" href="<?= e(team_url($home)) ?>">
        <img src="<?= e(team_img($home, '128')) ?>" alt="<?= e(team_name($home)) ?>" width="76" height="76">
        <b><?= e(team_name($home)) ?></b>
      </a>
      <div class="mh-center">
        <?php if ($state['started']): ?>
          <div class="mh-score"><span data-hs><?= $hs ?></span><i>:</i><span data-as><?= $as ?></span></div>
          <?php if ($state['live'] && !empty($state['clock'])): $clk = $state['clock'];
              $R = 30; $CIRC = round(2 * M_PI * $R, 2); ?>
          <div class="live-ring" role="timer" aria-label="<?= e($clk['label']) ?>">
            <svg viewBox="0 0 72 72" width="72" height="72" aria-hidden="true">
              <circle class="ring-bg" cx="36" cy="36" r="<?= $R ?>"/>
              <circle class="ring-fg" cx="36" cy="36" r="<?= $R ?>"
                      stroke-dasharray="<?= $CIRC ?>"
                      stroke-dashoffset="<?= round($CIRC * (1 - $clk['progress']), 2) ?>"
                      data-ring="<?= $CIRC ?>"/>
            </svg>
            <b class="ring-min" data-status<?= live_clock_attrs($state) ?>><?= e($clk['label']) ?></b>
            <i class="ring-pulse" aria-hidden="true"></i>
          </div>
          <span class="mh-status is-live"><?= e(t('status.live')) ?></span>
          <?php else: ?>
          <span class="mh-status is-ft" data-status><?= e($state['label']) ?></span>
          <?php endif; ?>
          <?php if ($pens): ?>
          <div class="mh-pens" aria-label="<?= e(t('match.penalties')) ?>">
            <span class="mh-pens-label"><?= e(t('match.penalties')) ?></span>
            <b class="<?= $penWinner === 'home' ? 'is-win' : '' ?>"><?= (int)$pens[0] ?></b>
            <i>-</i>
            <b class="<?= $penWinner === 'away' ? 'is-win' : '' ?>"><?= (int)$pens[1] ?></b>
          </div>
          <?php if ($penWinnerName !== ''): ?><small class="mh-pens-note"><?= e(t('match.won_pens', ['team' => $penWinnerName])) ?></small><?php endif; ?>
          <?php elseif (!empty($periods['has_et'])): ?>
          <small class="mh-pens-note"><?= e(t('match.after_et')) ?></small>
          <?php endif; ?>
        <?php else: ?>
          <div class="mh-time" data-ts="<?= $ts ?>"><?= e(format_ts_time($ts)) ?></div>
          <div class="countdown" data-countdown="<?= $ts ?>" aria-label="<?= e(t('match.kickoff_in')) ?>">
            <span data-cd="d">--</span><i>:</i><span data-cd="h">--</span><i>:</i><span data-cd="m">--</span><i>:</i><span data-cd="s">--</span>
          </div>
        <?php endif; ?>
      </div>
      <a class="mh-team" href="<?= e(team_url($away)) ?>">
        <img src="<?= e(team_img($away, '128')) ?>" alt="<?= e(team_name($away)) ?>" width="76" height="76">
        <b><?= e(team_name($away)) ?></b>
      </a>
    </div>
    <?php /* "Watch now" appears ONLY while the match is live — hidden before
             kickoff and after it ends. */ ?>
    <?php if (!empty($watchable) && !empty($state['live'])): ?>
    <div class="mh-watch">
      <a class="watch-cta" href="<?= e($watchUrl) ?>" target="<?= e($watchTarget) ?>"<?= $watchTarget === '_blank' ? ' rel="noopener"' : '' ?>>
        <span class="live-dot"></span><?= e(\Qamhad\Core\Lang::current() === 'ar' ? 'شاهد المباراة الآن' : t('player.watch')) ?>
      </a>
    </div>
    <?php endif; ?>
    <div class="mh-actions">
      <button class="icon-btn" onclick="QShare()" aria-label="<?= e(t('misc.share')) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6m-7.6 6 7.6 3.6"/></svg>
      </button>
      <button class="fav-btn standalone" data-fav="match" data-id="<?= $mid ?>" data-title="<?= e(team_name($home) . ' - ' . team_name($away)) ?>" data-url="<?= e(match_url($m)) ?>" onclick="QF.toggle(this)" aria-label="<?= e(t('fav.add')) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
      </button>
    </div>
  </div>

  <div class="container">
    <nav class="tabs glass-soft" role="tablist">
      <button class="tab active" data-tab="overview"><?= e(t('match.overview')) ?></button>
      <button class="tab" data-tab="events"><?= e(t('match.events')) ?></button>
      <button class="tab" data-tab="lineups"><?= e(t('match.lineups')) ?></button>
      <button class="tab" data-tab="stats"><?= e(t('match.stats')) ?></button>
      <button class="tab" data-tab="news"><?= e(t('match.news')) ?></button>
      <button class="tab" data-tab="standings"><?= e(t('match.standings')) ?></button>
      <button class="tab" data-tab="scorers"><?= e(t('match.scorers')) ?></button>
    </nav>
  </div>
</section>

<div class="container match-body">

  <!-- ============ OVERVIEW ============ -->
  <section class="tab-panel active" data-panel="overview">
    <div class="two-col">
      <div>
        <?php
        $breakdown = [];
        if (!empty($periods['fh']))  $breakdown[] = [t('match.first_half'),  $periods['fh']];
        if (!empty($periods['sh']))  $breakdown[] = [t('match.second_half'), $periods['sh']];
        if (!empty($periods['fe']))  $breakdown[] = [t('match.et_first'),    $periods['fe']];
        if (!empty($periods['se']))  $breakdown[] = [t('match.et_second'),   $periods['se']];
        if ($pens)                   $breakdown[] = [t('match.penalties'),   $pens];
        if ($state['started'] && $breakdown):
        ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.score_breakdown')) ?></h3>
          <div class="score-breakdown">
            <div class="sb-head">
              <span class="sb-team"><img src="<?= e(team_img($home)) ?>" alt="" width="22" height="22"><?= e(team_name($home)) ?></span>
              <span class="sb-total"><b><?= $hs ?></b><i>-</i><b><?= $as ?></b></span>
              <span class="sb-team sb-team-a"><?= e(team_name($away)) ?><img src="<?= e(team_img($away)) ?>" alt="" width="22" height="22"></span>
            </div>
            <ul class="sb-rows">
              <?php foreach ($breakdown as $bd): [$lbl, $pr] = $bd; ?>
              <li><span><?= (int)$pr[0] ?></span><em><?= e($lbl) ?></em><span><?= (int)$pr[1] ?></span></li>
              <?php endforeach; ?>
            </ul>
            <?php if ($penWinnerName !== ''): ?>
            <p class="sb-note"><?= e(t('match.won_pens', ['team' => $penWinnerName])) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($videoLinks) && $state['started']): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.highlights')) ?></h3>
          <div class="video-links">
            <?php foreach ($videoLinks as $v): if (empty($v['video_link'])) continue; ?>
              <a class="video-link card-hover" href="<?= e($v['video_link']) ?>" target="_blank" rel="noopener nofollow">
                <span class="hl-play"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
                <span><?= e(t('match.highlights')) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($keyEvents)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.key_events')) ?></h3>
          <?= View::partial('event-list', ['events' => $keyEvents, 'homeId' => $homeId, 'awayId' => $awayId]) ?>
        </div>
        <?php endif; ?>

        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.info')) ?></h3>
          <dl class="info-list">
            <div><dt><?= e(t('match.championship')) ?></dt><dd><a href="<?= e(league_url($m['championship'] ?? [])) ?>"><?= e($m['championship']['title'] ?? '—') ?></a></dd></div>
            <?php $roundText = !empty($roundLabel) ? $roundLabel : (string)($m['rank'] ?? ''); ?>
            <?php if ($roundText !== '' && $roundText !== '0'): ?><div><dt><?= e(t('match.round')) ?></dt><dd><?= e($roundText) ?></dd></div><?php endif; ?>
            <?php if (!empty($stadium)): ?><div><dt><?= e(t('match.stadium')) ?></dt><dd><?= e($stadium) ?></dd></div><?php endif; ?>
            <?php if ($mainReferee): ?><div><dt><?= e(t('match.referee')) ?></dt><dd><?= e((string)($mainReferee['title'] ?? '')) ?></dd></div><?php endif; ?>
            <div><dt><?= e(t('match.time')) ?></dt><dd data-ts-inline="<?= $ts ?>"><?= e(format_ts_time($ts)) ?></dd></div>
            <div><dt><?= e(t('match.date')) ?></dt><dd><?= e(format_date_long($m['match_date'] ?? '')) ?></dd></div>
          </dl>
        </div>

        <?php if (!empty($channels)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.channels')) ?></h3>
          <ul class="channel-list">
            <?php foreach ($channels as $c): ?>
              <li>
                <b><?= e((string)($c['channel_name'] ?? '')) ?></b>
                <?php if (!empty($c['commentator_name'])): ?><span><?= e((string)$c['commentator_name']) ?></span><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>

      <div>
        <?php if (!empty($playedResult['home']) || !empty($playedResult['away'])): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.form')) ?></h3>
          <div class="form-compare">
            <?php foreach ([['side' => 'home', 'team' => $home], ['side' => 'away', 'team' => $away]] as $fc):
                $list = array_values((array)($playedResult[$fc['side']] ?? [])); if (!$list) continue; ?>
            <div class="form-row">
              <span class="form-team"><img src="<?= e(team_img($fc['team'])) ?>" alt="" width="22" height="22"><?= e(team_name($fc['team'])) ?></span>
              <span class="form-dots">
                <?php foreach (array_slice($list, 0, 5) as $fm):
                    $wt = (string)($fm['win_type'] ?? '');
                    $cls = $wt === 'win' ? 'form-w' : ($wt === 'lose' ? 'form-l' : 'form-d');
                    $tip = team_name($fm['home'] ?? []) . ' ' . (int)($fm['home_scores'] ?? 0) . '-' . (int)($fm['away_scores'] ?? 0) . ' ' . team_name($fm['away'] ?? []); ?>
                  <i class="form-badge <?= $cls ?>" title="<?= e($tip) ?>"><?= $wt === 'win' ? '✓' : ($wt === 'lose' ? '✗' : '=') ?></i>
                <?php endforeach; ?>
              </span>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($teamWins) && (($teamWins['equal'] ?? null) !== null)):
                $hw = 0; $aw = 0; $eq = (int)($teamWins['equal'] ?? 0);
                foreach ($teamWins as $k => $v) {
                    if ($k === 'equal') continue;
                    if ((int)$k === (int)($home['row_id'] ?? -1)) $hw = (int)$v; else $aw = (int)$v;
                } ?>
            <div class="h2h-strip">
              <span class="h2h-cell"><b><?= $hw ?></b><small><?= e(team_name($home)) ?></small></span>
              <span class="h2h-cell"><b><?= $eq ?></b><small><?= e(t('standings.draw')) ?></small></span>
              <span class="h2h-cell"><b><?= $aw ?></b><small><?= e(team_name($away)) ?></small></span>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($hStats) || !empty($aStats)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('home.stats')) ?></h3>
          <?= View::partial('stat-bars', ['hStats' => $hStats, 'aStats' => $aStats, 'labels' => ['ball_possession', 'total_shots', 'shots_on_goal', 'corner_kicks', 'fouls'], 'home' => $home, 'away' => $away]) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($standingRows)): ?>
        <div class="card glass-soft">
          <h3 class="card-title"><?= e(t('match.standings')) ?></h3>
          <?= View::partial('standings-table', ['rows' => array_slice($standingRows, 0, 8), 'compact' => true, 'highlightTeam' => 0]) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ============ EVENTS ============ -->
  <section class="tab-panel" data-panel="events">
    <?php if (empty($events)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('match.no_events')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft">
        <div class="seg-control" role="tablist">
          <button class="seg active" data-events-filter="key"><?= e(t('match.key_events')) ?></button>
          <button class="seg" data-events-filter="all"><?= e(t('match.all_events')) ?></button>
        </div>
        <div data-events-view="key"><?= View::partial('event-list', ['events' => $keyEvents, 'homeId' => $homeId, 'awayId' => $awayId]) ?></div>
        <div data-events-view="all" hidden><?= View::partial('event-list', ['events' => $events, 'homeId' => $homeId, 'awayId' => $awayId]) ?></div>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============ LINEUPS ============ -->
  <section class="tab-panel" data-panel="lineups">
    <?php if (empty($lineups)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('match.no_lineup')) ?></p></div>
    <?php else: ?>
      <?= View::partial('formation-pitch', [
          'lineups' => $lineups, 'homeId' => $homeId, 'awayId' => $awayId,
          'home' => $home, 'away' => $away,
      ]) ?>
      <div class="two-col">
        <?php foreach ([$homeId => $home, $awayId => $away] as $tid => $teamObj):
            $side = $lineups[$tid] ?? null; if (!is_array($side)) continue;
            $starters = is_array($side['lineup'] ?? null) ? $side['lineup'] : [];
            $benchers = is_array($side['substitutions'] ?? null) ? $side['substitutions'] : [];
        ?>
        <div class="card glass-soft">
          <h3 class="card-title lineup-team">
            <img src="<?= e(team_img($teamObj)) ?>" alt="" width="24" height="24"><?= e(team_name($teamObj)) ?>
          </h3>
          <ul class="lineup-list">
            <?php foreach ($starters as $lp): $pl = $lp['player'] ?? []; ?>
            <li>
              <span class="lp-num"><?= (int)($pl['player_number'] ?? 0) ?></span>
              <img src="<?= e(player_img($pl, '64')) ?>" alt="" width="30" height="30" loading="lazy">
              <span class="lp-name">
                <?= e(player_label($pl)) ?>
                <?php if (!empty($lp['captain'])): ?><em class="badge-cap">C</em><?php endif; ?>
                <?php if (!empty($pl['position'])): ?><small><?= e((string)$pl['position']) ?></small><?php endif; ?>
              </span>
              <span class="lp-marks">
                <?php if (!empty($lp['goal'])): ?><i class="mark-goal" title="<?= e(t('event.goal')) ?>">⚽<?= (int)$lp['goal'] > 1 ? '×' . (int)$lp['goal'] : '' ?></i><?php endif; ?>
                <?php if (!empty($lp['yellow'])): ?><i class="mark-card yellow"></i><?php endif; ?>
                <?php if (!empty($lp['red'])): ?><i class="mark-card red"></i><?php endif; ?>
                <?php if (!empty($lp['rating']) && $lp['rating'] !== '0'): ?><b class="lp-rating"><?= e((string)$lp['rating']) ?></b><?php endif; ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php if (!empty($benchers)): ?>
          <h4 class="card-subtitle"><?= e(t('match.substitutes')) ?></h4>
          <ul class="lineup-list bench">
            <?php foreach ($benchers as $lp): $pl = $lp['player'] ?? []; ?>
            <li>
              <span class="lp-num"><?= (int)($pl['player_number'] ?? 0) ?></span>
              <img src="<?= e(player_img($pl, '64')) ?>" alt="" width="30" height="30" loading="lazy">
              <span class="lp-name"><?= e(player_label($pl)) ?><?php if (!empty($pl['position'])): ?><small><?= e((string)$pl['position']) ?></small><?php endif; ?></span>
              <span class="lp-marks">
                <?php if (!empty($lp['rating']) && $lp['rating'] !== '0'): ?><b class="lp-rating"><?= e((string)$lp['rating']) ?></b><?php endif; ?>
              </span>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============ STATS ============ -->
  <section class="tab-panel" data-panel="stats">
    <?php if (empty($hStats) && empty($aStats)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('match.no_stats')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft">
        <div class="stats-teams">
          <span><img src="<?= e(team_img($home)) ?>" alt="" width="28" height="28"><?= e(team_name($home)) ?></span>
          <b><?= e(t('home.stats')) ?></b>
          <span><?= e(team_name($away)) ?><img src="<?= e(team_img($away)) ?>" alt="" width="28" height="28"></span>
        </div>
        <?= View::partial('stat-bars', ['hStats' => $hStats, 'aStats' => $aStats, 'labels' => $statLabels, 'home' => $home, 'away' => $away]) ?>
        <div class="heatmap-placeholder">
          <svg viewBox="0 0 100 60" width="100%" aria-hidden="true">
            <rect x="1" y="1" width="98" height="58" rx="2" fill="none" stroke="currentColor" stroke-width=".8" opacity=".35"/>
            <line x1="50" y1="1" x2="50" y2="59" stroke="currentColor" stroke-width=".8" opacity=".35"/>
            <circle cx="50" cy="30" r="8" fill="none" stroke="currentColor" stroke-width=".8" opacity=".35"/>
            <rect x="1" y="17" width="14" height="26" fill="none" stroke="currentColor" stroke-width=".8" opacity=".35"/>
            <rect x="85" y="17" width="14" height="26" fill="none" stroke="currentColor" stroke-width=".8" opacity=".35"/>
          </svg>
          <p><?= e(t('stat.heatmap_soon')) ?></p>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============ NEWS ============ -->
  <section class="tab-panel" data-panel="news">
    <?php if (empty($leagueNews)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('news.none')) ?></p></div>
    <?php else: ?>
      <div class="news-list">
        <?php foreach ($leagueNews as $n): ?>
          <?= View::partial('news-card', ['n' => $n]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============ STANDINGS ============ -->
  <section class="tab-panel" data-panel="standings">
    <?php if (empty($standingRows)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('standings.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft">
        <?= View::partial('standings-table', ['rows' => $standingRows, 'compact' => false]) ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ============ SCORERS ============ -->
  <section class="tab-panel" data-panel="scorers">
    <?php if (empty($scorers)): ?>
      <div class="empty-state glass-soft"><p><?= e(t('scorers.none')) ?></p></div>
    <?php else: ?>
      <div class="card glass-soft">
        <?= View::partial('scorers-table', ['scorers' => $scorers, 'leagueId' => (int)($m['championship']['url_id'] ?? 0)]) ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php
/* ============ SEO Content Engine — server-rendered article ============ */
$__article = match_article($m, $state, $channels, [
    'referee' => (string)($mainReferee['title'] ?? ''),
    'stadium' => (string)($stadium ?? ''),
    'round'   => (string)($roundLabel ?? ''),
]);
?>
<div class="container">
  <?= View::partial('match-article', [
      'article' => $__article,
      'home'    => team_name($home),
      'away'    => team_name($away),
  ]) ?>
</div>
