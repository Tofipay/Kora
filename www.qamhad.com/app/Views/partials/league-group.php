<?php
/** A league header + its matches. Expects: $group ['league'=>..,'matches'=>[]] */
use Qamhad\Core\View;
$lg = $group['league'] ?? [];
?>
<section class="league-block reveal">
  <a class="league-head glass-soft" href="<?= e(league_url($lg)) ?>">
    <img src="<?= e(league_img($lg)) ?>" alt="" width="26" height="26" loading="lazy" decoding="async">
    <h3><?= e($lg['title'] ?? '') ?></h3>
    <svg class="chev" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
  </a>
  <div class="league-matches">
    <?php foreach ($group['matches'] as $m): ?>
      <?= View::partial('match-card', ['m' => $m]) ?>
    <?php endforeach; ?>
  </div>
</section>
