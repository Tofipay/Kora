<?php
use Qamhad\Core\View;

$render = [];
foreach ($sections as $s) { if ($s['on']) $render[] = $s['id']; }
?>
<?php foreach ($render as $sectionId): ?>
<?php if ($sectionId === 'hero'): ?>
<section class="hero">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="container hero-inner">
    <h1 class="hero-title"><?= e(t('home.hero.title')) ?></h1>
    <p class="hero-sub"><?= e(t('home.hero.sub')) ?></p>
    <div class="hero-cta">
      <a class="btn btn-primary" href="<?= e(path('today')) ?>"><?= e(t('home.hero.cta')) ?></a>
      <a class="btn btn-ghost" href="<?= e(path('live')) ?>"><span class="live-dot"></span> <?= e(t('home.hero.cta2')) ?></a>
    </div>
  </div>
</section>

<?php elseif ($sectionId === 'live' && !empty($live)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><span class="live-dot"></span> <?= e(t('home.live')) ?></h2>
    <a class="view-all" href="<?= e(path('live')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <div class="hscroll live-slider">
    <?php foreach ($live as $m): $home = team_of($m, 'home'); $away = team_of($m, 'away'); $st = match_state($m); ?>
    <a class="live-card glass" href="<?= e(match_url($m)) ?>" data-match="<?= (int)$m['match_id'] ?>">
      <div class="lc-league"><img src="<?= e(league_img($m['championship'] ?? [])) ?>" alt="" width="16" height="16" loading="lazy"><span><?= e($m['championship']['title'] ?? '') ?></span></div>
      <div class="lc-row">
        <span class="lc-team"><img src="<?= e(team_img($home)) ?>" alt="" width="28" height="28" loading="lazy"><?= e(team_name($home)) ?></span>
        <span class="lc-score" data-hs><?= (int)($m['home_scores'] ?? 0) ?></span>
      </div>
      <div class="lc-row">
        <span class="lc-team"><img src="<?= e(team_img($away)) ?>" alt="" width="28" height="28" loading="lazy"><?= e(team_name($away)) ?></span>
        <span class="lc-score" data-as><?= (int)($m['away_scores'] ?? 0) ?></span>
      </div>
      <span class="lc-minute" data-status<?= live_clock_attrs($st) ?>><?= e($st['label']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<?php elseif ($sectionId === 'today'): ?>
<section class="section container reveal" id="today">
  <div class="section-head">
    <h2><?= e(t('home.today')) ?></h2>
    <a class="view-all" href="<?= e(path('matches')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <?php if (empty($grouped)): ?>
    <p class="empty-note"><?= e(t('matches.none')) ?></p>
  <?php else: ?>
    <?php foreach (array_slice($grouped, 0, 10, true) as $group): ?>
      <?= View::partial('league-group', ['group' => $group]) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php elseif ($sectionId === 'featured' && !empty($featured)): ?>
<section class="section container reveal">
  <div class="section-head"><h2><?= e(t('home.featured')) ?></h2></div>
  <div class="featured-grid">
    <?php foreach ($featured as $m): $home = team_of($m, 'home'); $away = team_of($m, 'away'); $st = match_state($m); ?>
    <a class="featured-card glass card-hover" href="<?= e(match_url($m)) ?>" data-match="<?= (int)$m['match_id'] ?>">
      <div class="fc-league"><img src="<?= e(league_img($m['championship'] ?? [])) ?>" alt="" width="18" height="18" loading="lazy"><span><?= e($m['championship']['title'] ?? '') ?></span></div>
      <div class="fc-teams">
        <span class="fc-team"><img src="<?= e(team_img($home, '128')) ?>" alt="<?= e(team_name($home)) ?>" width="52" height="52" loading="lazy"><b><?= e(team_name($home)) ?></b></span>
        <span class="fc-mid">
          <?php if ($st['started']): ?>
            <b class="fc-score"><span data-hs><?= (int)($m['home_scores'] ?? 0) ?></span> - <span data-as><?= (int)($m['away_scores'] ?? 0) ?></span></b>
            <small class="<?= $st['live'] ? 'is-live' : '' ?>" data-status<?= live_clock_attrs($st) ?>><?= e($st['label']) ?></small>
          <?php else: ?>
            <b class="fc-time" data-ts="<?= (int)($m['match_timestamp'] ?? 0) ?>"><?= e($st['label']) ?></b>
            <small class="fc-date"><?= e(format_date_long($m['match_date'] ?? '')) ?></small>
          <?php endif; ?>
        </span>
        <span class="fc-team"><img src="<?= e(team_img($away, '128')) ?>" alt="<?= e(team_name($away)) ?>" width="52" height="52" loading="lazy"><b><?= e(team_name($away)) ?></b></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<?php elseif ($sectionId === 'leagues' && !empty($leagues)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e(t('home.top_leagues')) ?></h2>
    <a class="view-all" href="<?= e(path('leagues')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <div class="hscroll league-pills">
    <?php foreach ($leagues as $lg): ?>
    <a class="league-pill glass-soft card-hover" href="<?= e(league_url($lg)) ?>">
      <img src="<?= e(league_img($lg)) ?>" alt="" width="30" height="30" loading="lazy">
      <span><?= e($lg['title']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<?php elseif ($sectionId === 'news' && !empty($news)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e(t('home.trending_news')) ?></h2>
    <a class="view-all" href="<?= e(path('news')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <?php if (count($news) >= 4): ?>
  <div class="news-grid">
    <?php foreach (array_slice($news, 0, 1) as $n): ?>
      <?= View::partial('news-card', ['n' => $n, 'big' => true]) ?>
    <?php endforeach; ?>
    <div class="news-side">
      <?php foreach (array_slice($news, 1, 4) as $n): ?>
        <?= View::partial('news-card', ['n' => $n]) ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: /* too few items for the split layout — plain grid, no empty column */ ?>
  <div class="news-list">
    <?php foreach ($news as $n): ?><?= View::partial('news-card', ['n' => $n]) ?><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php elseif ($sectionId === 'highlights'): ?>
<?php $finishedWithVideo = array_values(array_filter($todayMatches, fn($m) => match_state($m)['key'] === 'finished' && (int)($m['video'] ?? 0) === 1)); ?>
<?php if (!empty($finishedWithVideo)): ?>
<section class="section container reveal">
  <div class="section-head"><h2><?= e(t('home.highlights')) ?></h2></div>
  <div class="hl-grid">
    <?php foreach (array_slice($finishedWithVideo, 0, 4) as $m): $home = team_of($m, 'home'); $away = team_of($m, 'away'); ?>
      <a class="hl-card card-hover" href="<?= e(match_url($m)) ?>">
        <span class="hl-play"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
        <span class="hl-title"><?= e(team_name($home)) ?> <?= (int)($m['home_scores'] ?? 0) ?> - <?= (int)($m['away_scores'] ?? 0) ?> <?= e(team_name($away)) ?></span>
        <small><?= e($m['championship']['title'] ?? '') ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php elseif ($sectionId === 'teams' && !empty($teams)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e(t('home.popular_teams')) ?></h2>
    <a class="view-all" href="<?= e(path('teams')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <div class="hscroll team-pills">
    <?php foreach ($teams as $tm): ?>
    <a class="team-pill glass-soft card-hover" href="<?= e(team_url($tm)) ?>">
      <img src="<?= e(team_img($tm, '128')) ?>" alt="<?= e(team_name($tm)) ?>" width="44" height="44" loading="lazy">
      <span><?= e(team_name($tm)) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<?php elseif ($sectionId === 'standings' && !empty($standingRows)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e(t('home.standings')) ?><?php if ($standingLeague): ?> — <?= e($standingLeague['title']) ?><?php endif; ?></h2>
    <a class="view-all" href="<?= e(path('standings')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <div class="card glass-soft">
    <?= View::partial('standings-table', ['rows' => $standingRows, 'compact' => true]) ?>
  </div>
</section>

<?php elseif ($sectionId === 'scorers' && !empty($scorers)): ?>
<section class="section container reveal">
  <div class="section-head">
    <h2><?= e(t('home.top_scorers')) ?><?php if ($standingLeague): ?> — <?= e($standingLeague['title']) ?><?php endif; ?></h2>
    <a class="view-all" href="<?= e(path('top-scorers')) ?>"><?= e(t('home.view_all')) ?></a>
  </div>
  <div class="card glass-soft">
    <?= View::partial('scorers-table', ['scorers' => $scorers, 'leagueId' => $standingLeague['url_id'] ?? 0]) ?>
  </div>
</section>

<?php elseif ($sectionId === 'stats'): ?>
<section class="section container reveal">
  <div class="stats-strip glass">
    <div class="stat-box"><b data-count="<?= (int)$stats['matches'] ?>"><?= (int)$stats['matches'] ?></b><span><?= e(t('home.today')) ?></span></div>
    <div class="stat-box"><b data-count="<?= (int)$stats['live'] ?>"><?= (int)$stats['live'] ?></b><span><?= e(t('nav.live')) ?></span></div>
    <div class="stat-box"><b data-count="<?= (int)$stats['finished'] ?>"><?= (int)$stats['finished'] ?></b><span><?= e(t('status.finished')) ?></span></div>
    <div class="stat-box"><b data-count="<?= (int)$stats['goals'] ?>"><?= (int)$stats['goals'] ?></b><span><?= e(t('scorers.goals')) ?></span></div>
    <div class="stat-box"><b data-count="<?= (int)$stats['leagues'] ?>"><?= (int)$stats['leagues'] ?></b><span><?= e(t('nav.leagues')) ?></span></div>
  </div>
</section>

<?php elseif ($sectionId === 'app'): ?>
<section class="section container reveal">
  <div class="app-banner">
    <div class="ab-copy">
      <h2><?= e(t('home.app_banner.title')) ?></h2>
      <p><?= e(t('home.app_banner.sub')) ?></p>
      <button class="btn btn-light" id="install-btn" hidden><?= e(t('home.app_banner.cta')) ?></button>
      <button class="btn btn-light" id="notify-btn"><?= e(t('misc.enable_notifications')) ?></button>
    </div>
    <div class="ab-art" aria-hidden="true">
      <img src="/assets/brand/icon-192.png" alt="" width="120" height="120" loading="lazy">
    </div>
  </div>
</section>

<?php elseif ($sectionId === 'newsletter'): ?>
<section class="section container reveal">
  <div class="newsletter glass-soft">
    <div>
      <h2><?= e(t('home.newsletter.title')) ?></h2>
      <p><?= e(t('home.newsletter.sub')) ?></p>
    </div>
    <form id="newsletter-form" class="nl-form" action="/api/newsletter" method="post">
      <input type="email" name="email" required placeholder="<?= e(t('home.newsletter.email')) ?>" autocomplete="email">
      <button class="btn btn-primary" type="submit"><?= e(t('home.newsletter.cta')) ?></button>
    </form>
  </div>
</section>
<?php endif; ?>
<?php endforeach; ?>
