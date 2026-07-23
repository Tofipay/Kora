<div class="container page-head">
  <h1><?= e(t('leagues.title')) ?></h1>
</div>
<div class="container">
  <div class="leagues-grid">
    <?php foreach ($leagues as $lg): ?>
    <a class="league-card glass-soft card-hover" href="<?= e(league_url($lg)) ?>">
      <img src="<?= e(league_img($lg)) ?>" alt="<?= e($lg['title']) ?>" width="42" height="42" loading="lazy" decoding="async">
      <b><?= e($lg['title']) ?></b>
      <button class="fav-btn" data-fav="league" data-id="<?= (int)$lg['url_id'] ?>" data-title="<?= e($lg['title']) ?>" data-url="<?= e(league_url($lg)) ?>" data-img="<?= e(league_img($lg)) ?>" onclick="event.preventDefault();QF.toggle(this)" aria-label="<?= e(t('fav.add')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.9 5.8 6.1.9-4.5 4.4 1 6.2L12 17.4 6.5 20.3l1-6.2L3 9.7l6.1-.9z"/></svg>
      </button>
    </a>
    <?php endforeach; ?>
  </div>
</div>
