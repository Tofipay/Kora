<?php
/**
 * Notification topic picker — Android 16 / iOS 26 style bottom sheet.
 * Opened by #notify-btn (JS). The visitor chooses the competitions they
 * want alerts for; on save the token subscribes only to those topics.
 * Hidden by default and inert until opened.
 */
use Qamhad\Core\Lang;
$isAr = Lang::current() === 'ar';
$topics = notify_topics();
?>
<div id="notify-sheet" class="sheet" hidden role="dialog" aria-modal="true" aria-labelledby="notify-sheet-title">
  <div class="sheet-scrim" data-sheet-close></div>
  <div class="sheet-panel glass">
    <div class="sheet-grip" data-sheet-close aria-hidden="true"></div>
    <div class="sheet-head">
      <div class="sheet-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
      </div>
      <div>
        <h2 id="notify-sheet-title" class="sheet-title"><?= $isAr ? 'اختر البطولات' : 'Choose competitions' ?></h2>
        <p class="sheet-sub"><?= $isAr ? 'اختر البطولات التي تريد استلام إشعاراتها' : 'Pick the competitions you want alerts for' ?></p>
      </div>
      <button class="sheet-x" type="button" data-sheet-close aria-label="<?= e(t('misc.back')) ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <label class="topic-row topic-all">
      <span class="topic-name"><?= $isAr ? 'تحديد الكل' : 'Select all' ?></span>
      <span class="switch"><input type="checkbox" data-topics-all><span class="switch-track"></span></span>
    </label>

    <div class="sheet-body">
      <?php foreach ($topics as $tp): ?>
      <label class="topic-row">
        <span class="topic-name"><?= e($isAr ? $tp['ar'] : $tp['en']) ?></span>
        <span class="switch"><input type="checkbox" value="<?= e($tp['slug']) ?>"><span class="switch-track"></span></span>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="sheet-foot">
      <button class="btn btn-primary sheet-save" type="button" data-topics-save>
        <span class="sv-label"><?= $isAr ? 'حفظ وتفعيل الإشعارات' : 'Save & enable' ?></span>
        <span class="sv-spin" aria-hidden="true"></span>
      </button>
    </div>
  </div>
</div>
