<?php
/** @var array $player  normalized view model @var array $comps @var array $totals */
use TofiXTv\Core\View;

$flag = flag_img($player);
$transfers = is_array($transfers ?? null) ? $transfers : [];
$description = (string)($description ?? '');
$sum = ['appearances' => 0, 'started' => 0, 'sub' => 0, 'assists' => 0, 'yellow' => 0, 'red' => 0,
        'penalties' => 0, 'miss_pen' => 0, 'disallowed' => 0, 'motm' => 0, 'minutes' => 0,
        'shots' => 0, 'passes' => 0, 'own_goal' => 0, 'goals' => 0];
foreach ($comps as $c) {
    foreach ($sum as $k => $_) $sum[$k] += (int)($c['stats'][$k] ?? 0);
}
$apps = $sum['appearances']; $minutes = $sum['minutes']; $yellow = $sum['yellow']; $red = $sum['red'];
$shots = $sum['shots']; $passes = $sum['passes']; $own = $sum['own_goal']; $missPen = $sum['miss_pen'];
$goals = (int)$totals['goals']; $assists = (int)$totals['assists']; $pens = (int)$totals['penalties'];
// Prefer the appearances the stats actually report; fall back to totals.
if ($apps === 0 && $goals === 0 && $assists === 0) { /* nothing scraped */ }
// Ring maxes scale to the player's own output so the arcs read well.
$ringMax = max(10, $goals, $assists, $apps);
?>
<section class="player-hero">
  <div class="ph-aurora" aria-hidden="true"></div>
  <div class="container ph-inner">
    <div class="ph-photo-wrap">
      <div class="ph-photo-glow" aria-hidden="true"></div>
      <img class="ph-photo" src="<?= e(player_img($p, '128')) ?>" alt="<?= e($name) ?>" width="150" height="150"
           onerror="this.onerror=null;this.src='<?= e(player_img($p, '64')) ?>'">
      <?php if ($player['number'] > 0): ?><span class="ph-number"><?= (int)$player['number'] ?></span><?php endif; ?>
    </div>

    <div class="ph-id">
      <h1 class="ph-name"><?= e($name) ?></h1>
      <?php if ($player['full'] && $player['full'] !== $name): ?><p class="ph-full"><?= e($player['full']) ?></p><?php endif; ?>
      <div class="ph-tags">
        <?php if ($flag): ?><span class="ph-tag"><img src="<?= e($flag) ?>" alt="" width="20" height="14" loading="lazy"><?= e($player['nationality'] ?: '') ?></span><?php endif; ?>
        <?php if ($player['team']): ?>
          <span class="ph-tag"><?php if ($player['team_id']): ?><a href="<?= e(team_url(['row_id' => $player['team_id'], 'title' => $player['team']])) ?>" class="ph-club"><?php endif; ?>
            <?php $ti = $player['team_image']; if ($ti): ?><img src="<?= e(team_img($ti)) ?>" alt="" width="18" height="18" loading="lazy"><?php endif; ?>
            <?= e($player['team']) ?><?php if ($player['team_id']): ?></a><?php endif; ?></span>
        <?php endif; ?>
        <?php if ($player['position_label']): ?><span class="ph-tag ph-pos"><?= e($player['position_label']) ?></span><?php endif; ?>
      </div>
      <?php if (!empty($description)): ?><p class="ph-desc"><?= e($description) ?></p><?php endif; ?>
    </div>
  </div>

  <!-- Vital cards -->
  <div class="container ph-vitals">
    <?php if ($player['position_label']): ?>
    <div class="vital glass"><b><?= e($player['position_label']) ?></b><span><?= e(t('player.position')) ?></span></div>
    <?php endif; ?>
    <?php if ($player['age'] > 0): ?>
    <div class="vital glass"><b><?= (int)$player['age'] ?></b><span><?= e(t('player.age')) ?></span></div>
    <?php endif; ?>
    <?php if ($player['height'] > 0): ?>
    <div class="vital glass"><b><?= (int)$player['height'] ?><i><?= e(t('player.cm')) ?></i></b><span><?= e(t('player.height')) ?></span></div>
    <?php endif; ?>
    <?php if ($player['weight'] > 0): ?>
    <div class="vital glass"><b><?= (int)$player['weight'] ?><i><?= e(t('player.kg')) ?></i></b><span><?= e(t('player.weight')) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($player['foot'])): ?>
    <div class="vital glass"><b class="vital-txt"><?= e($player['foot']) ?></b><span><?= e(t('player.foot')) ?></span></div>
    <?php endif; ?>
    <?php if ($player['number'] > 0): ?>
    <div class="vital glass"><b><?= (int)$player['number'] ?></b><span><?= e(t('player.number')) ?></span></div>
    <?php endif; ?>
  </div>
</section>

<div class="container player-body">
  <!-- Career totals -->
  <section class="stat-rings">
    <?php
    $rings = [
        ['label' => t('player.goals'),       'value' => $goals,   'color' => 'var(--primary)'],
        ['label' => t('player.assists'),     'value' => $assists, 'color' => 'var(--accent)'],
        ['label' => t('player.appearances'), 'value' => $apps,    'color' => '#a78bfa'],
    ];
    foreach ($rings as $r):
        $R = 46; $C = round(2 * M_PI * $R, 1);
        $pct = $ringMax > 0 ? min($r['value'] / $ringMax, 1) : 0;
        $off = round($C * (1 - $pct), 1);
    ?>
    <div class="ring-card glass" style="--rc:<?= $r['color'] ?>">
      <div class="ring-viz">
        <svg viewBox="0 0 110 110" width="110" height="110">
          <circle class="rc-bg" cx="55" cy="55" r="<?= $R ?>"/>
          <circle class="rc-fg" cx="55" cy="55" r="<?= $R ?>" stroke-dasharray="<?= $C ?>"
                  stroke-dashoffset="<?= $off ?>" data-target="<?= $off ?>" data-circ="<?= $C ?>"/>
        </svg>
        <b class="rc-val" data-count="<?= (int)$r['value'] ?>"><?= (int)$r['value'] ?></b>
      </div>
      <span class="rc-label"><?= e($r['label']) ?></span>
    </div>
    <?php endforeach; ?>
  </section>

  <!-- Statistics by competition (tabbed) -->
  <?php if (!empty($comps)): ?>
  <section class="stats-section">
    <h2 class="section-title"><?= e(t('player.stats')) ?></h2>
    <div class="comp-switch" role="tablist" data-comp-tabs>
      <?php foreach ($comps as $i => $c): ?>
      <button class="cs-pill<?= $i === 0 ? ' active' : '' ?>" role="tab" data-comp="cp<?= $i ?>">
        <span class="cs-badge" aria-hidden="true"><?= e(mb_substr(trim((string)$c['title']), 0, 1)) ?></span>
        <?= e($c['title'] ?: '—') ?>
      </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($comps as $i => $c): $s = $c['stats'];
      $cards = [
        ['k' => 'appearances', 'label' => t('player.appearances'), 'icon' => '🏟️', 'tone' => 'neutral'],
        ['k' => 'goals',       'label' => t('player.goals'),       'icon' => '⚽',  'tone' => 'green'],
        ['k' => 'disallowed',  'label' => t('player.disallowed'),  'icon' => '🚫',  'tone' => 'red'],
        ['k' => 'motm',        'label' => t('player.motm'),        'icon' => '⭐',  'tone' => 'gold'],
        ['k' => 'yellow',      'label' => t('player.yellow'),      'icon' => '',    'tone' => 'yellow', 'card' => 'y'],
        ['k' => 'red',         'label' => t('player.red'),         'icon' => '',    'tone' => 'red',    'card' => 'r'],
        ['k' => 'assists',     'label' => t('player.assists'),     'icon' => '👟',  'tone' => 'blue'],
        ['k' => 'penalties',   'label' => t('player.penalties'),   'icon' => '🥅',  'tone' => 'green'],
        ['k' => 'miss_pen',    'label' => t('player.miss_pen'),    'icon' => '❌',  'tone' => 'red'],
      ];
      $tm = (int)($s['team_matches'] ?? 0);
      $den = $tm > 0 ? $tm : max((int)$s['appearances'], (int)$s['started'] + (int)$s['sub'], 1);
    ?>
    <div class="comp-panel<?= $i === 0 ? ' active' : '' ?>" data-comp-panel="cp<?= $i ?>">
      <div class="pstat-grid">
        <?php foreach ($cards as $sc): $val = (int)($s[$sc['k']] ?? 0); ?>
        <div class="pstat-card glass-soft tone-<?= $sc['tone'] ?>">
          <span class="pstat-ic" aria-hidden="true">
            <?php if (!empty($sc['card'])): ?><i class="card-<?= $sc['card'] ?>"></i><?php else: ?><?= $sc['icon'] ?><?php endif; ?>
          </span>
          <b class="pstat-val" data-count="<?= $val ?>"><?= $val ?></b>
          <span class="pstat-label"><?= e($sc['label']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($tm > 0 || (int)$s['started'] > 0 || (int)$s['sub'] > 0): ?>
      <div class="pstat-donuts">
        <?php foreach ([
            ['k' => 'started', 'label' => t('player.started'), 'color' => '#a78bfa'],
            ['k' => 'sub',     'label' => t('player.sub'),     'color' => 'var(--accent)'],
        ] as $d):
            $val = (int)($s[$d['k']] ?? 0);
            $pct = $den > 0 ? min($val / $den, 1) : 0;
            $R = 42; $C = round(2 * M_PI * $R, 1); $off = round($C * (1 - $pct), 1);
        ?>
        <div class="donut-card glass-soft" style="--rc:<?= $d['color'] ?>">
          <div class="donut-viz">
            <svg viewBox="0 0 100 100" width="100" height="100">
              <circle class="rc-bg" cx="50" cy="50" r="<?= $R ?>"/>
              <circle class="rc-fg" cx="50" cy="50" r="<?= $R ?>" stroke-dasharray="<?= $C ?>"
                      stroke-dashoffset="<?= $off ?>" data-target="<?= $off ?>" data-circ="<?= $C ?>"/>
            </svg>
            <b class="donut-pct"><?= round($pct * 100) ?>%</b>
          </div>
          <b class="donut-val" data-count="<?= $val ?>"><?= $val ?></b>
          <span class="donut-label"><?= e($d['label']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($tm > 0): ?>
        <div class="donut-card glass-soft tm-card">
          <b class="tm-val" data-count="<?= $tm ?>"><?= $tm ?></b>
          <span class="donut-label"><?= e(t('player.team_matches')) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- Transfer history timeline -->
  <?php if (!empty($transfers)): ?>
  <section class="card glass-soft transfers-card">
    <h2 class="card-title"><?= e(t('player.transfers')) ?></h2>
    <ol class="transfer-timeline">
      <?php foreach ($transfers as $tr):
          $from = (string)($tr['team_from'] ?? '');
          $to   = (string)($tr['team_to'] ?? '');
          $type = (string)($tr['type'] ?? '');
          $date = (string)($tr['date_from'] ?? '');
          if ($from === '' && $to === '') continue;
          $isLoan = mb_strpos($type, 'إعار') !== false || stripos($type, 'loan') !== false;
          $isFree = mb_strpos($type, 'حر') !== false || stripos($type, 'free') !== false;
          $cls = $isLoan ? 'tt-loan' : ($isFree ? 'tt-free' : 'tt-move');
      ?>
      <li class="transfer-row">
        <span class="tr-dot" aria-hidden="true"></span>
        <div class="tr-body">
          <div class="tr-teams">
            <span class="tr-team tr-to"><?= e($to ?: '—') ?></span>
            <svg class="tr-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 12H4m0 0 6-6m-6 6 6 6"/></svg>
            <span class="tr-team tr-from"><?= e($from ?: '—') ?></span>
          </div>
          <div class="tr-meta">
            <?php if ($type !== ''): ?><span class="tr-type <?= $cls ?>"><?= e($type) ?></span><?php endif; ?>
            <?php if ($date !== ''): ?><time class="tr-date"><?= e($date) ?></time><?php endif; ?>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>
</div>
