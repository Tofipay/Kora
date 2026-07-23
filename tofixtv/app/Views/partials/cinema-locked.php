<?php
/**
 * Locked / unavailable panel for cinema content.
 * Expects: $policy (CinemaPolicy::decision result), $title (string).
 * reason 'app_only'  → professional app-lock screen (play in the app).
 * reason 'disabled'|'age' → unavailable state.
 */
use TofiXTv\Core\Lang;

$isAr    = Lang::current() === 'ar';
$reason  = (string)($policy['reason'] ?? '');
$appOnly = $reason === 'app_only';
?>
<div class="locked-panel glass <?= $appOnly ? 'is-app-lock' : 'is-unavailable' ?>">
  <span class="lp-icon" aria-hidden="true">
    <?php if ($appOnly): ?>
    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/></svg>
    <?php else: ?>
    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.5" r="1.6"/></svg>
    <?php endif; ?>
  </span>
  <?php if ($appOnly): ?>
    <h3 class="lp-title"><?= e(t('cinema.app_only_title')) ?></h3>
    <p class="lp-text"><?= e(t('cinema.app_only_text')) ?></p>
    <div class="lp-actions">
      <a class="btn btn-primary" href="https://t.me/alokalive" target="_blank" rel="noopener nofollow">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
        <?= e(t('cinema.get_app')) ?>
      </a>
      <button class="btn btn-ghost" type="button" onclick="this.closest('.locked-panel').classList.add('lp-min')">
        <?= e(t('cinema.close')) ?>
      </button>
    </div>
    <ul class="lp-perks">
      <li><?= $isAr ? 'مشاهدة بجودة عالية وبدون تقطيع' : 'HD playback without interruptions' ?></li>
      <li><?= $isAr ? 'تجربة مشغّل أسرع داخل التطبيق' : 'Faster in-app player experience' ?></li>
      <li><?= $isAr ? 'إشعارات فورية بالجديد' : 'Instant alerts for new releases' ?></li>
    </ul>
  <?php elseif ($reason === 'age'): ?>
    <h3 class="lp-title"><?= e(t('cinema.age_blocked_title')) ?></h3>
    <p class="lp-text"><?= e(t('cinema.age_blocked_text')) ?></p>
  <?php else: ?>
    <h3 class="lp-title"><?= e(t('cinema.unavailable_title')) ?></h3>
    <p class="lp-text"><?= e(t('cinema.unavailable_text')) ?></p>
  <?php endif; ?>
</div>
