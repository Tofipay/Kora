<?php
/**
 * Notification bottom sheet — Android 16 / iOS 26 style. Opened by
 * #notify-btn (JS). Two steps:
 *
 *   1. [data-push-step=enable]  shown while notifications are OFF — a short
 *      pitch + one "تفعيل الإشعارات" button (permission + FCM token, then
 *      subscribes to ALL championships).
 *   2. [data-push-step=manage]  shown once enabled — EVERY championship from
 *      the matches API (Api::allLeagues via notify_topics()), each with its
 *      logo and a switch, so the visitor can turn any league's alerts off.
 *
 * Hidden by default and inert until opened; JS picks the step.
 */
use TofiXTv\Core\Lang;
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
        <h2 id="notify-sheet-title" class="sheet-title"><?= $isAr ? 'إشعارات المباريات' : 'Match alerts' ?></h2>
        <p class="sheet-sub" data-push-sub><?= $isAr ? 'أهداف ونتائج لحظة بلحظة' : 'Goals and results as they happen' ?></p>
      </div>
      <button class="sheet-x" type="button" data-sheet-close aria-label="<?= e(t('misc.back')) ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <!-- Step 1 — notifications not enabled yet -->
    <div data-push-step="enable">
      <div class="push-pitch">
        <p><?= $isAr
          ? 'فعّل الإشعارات ليصلك كل ما يهمك فور حدوثه: بداية المباريات، الأهداف، ونتائج النهائية — لجميع البطولات، وتتحكم لاحقًا في أي بطولة تريد.'
          : 'Enable alerts to get kick-offs, goals and full-time results the moment they happen — for every competition, with per-league control afterwards.' ?></p>
      </div>
      <div class="sheet-foot">
        <button class="btn btn-primary sheet-save" type="button" data-push-enable>
          <span class="sv-label"><?= $isAr ? 'تفعيل الإشعارات' : 'Enable notifications' ?></span>
          <span class="sv-spin" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    <!-- Step 2 — enabled: manage per-championship alerts -->
    <div data-push-step="manage" hidden>
      <p class="push-status" data-push-status>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        <?= $isAr ? 'الإشعارات مفعّلة — أوقف أي بطولة لا تريد إشعاراتها' : 'Alerts are on — switch off any league you don\'t want' ?>
      </p>

      <label class="topic-row topic-all">
        <span class="topic-name"><?= $isAr ? 'جميع البطولات' : 'All competitions' ?></span>
        <span class="switch"><input type="checkbox" data-topics-all><span class="switch-track"></span></span>
      </label>

      <div class="sheet-body">
        <?php foreach ($topics as $tp): ?>
        <label class="topic-row">
          <img class="topic-logo" src="<?= e(league_img($tp, '64')) ?>" alt="" width="28" height="28" loading="lazy" decoding="async" onerror="this.src='/assets/brand/icon.svg'">
          <span class="topic-name"><?= e($tp['title']) ?></span>
          <span class="switch"><input type="checkbox" value="<?= e($tp['slug']) ?>"><span class="switch-track"></span></span>
        </label>
        <?php endforeach; ?>
      </div>

      <div class="sheet-foot">
        <button class="btn btn-primary sheet-save" type="button" data-topics-save>
          <span class="sv-label"><?= $isAr ? 'حفظ التفضيلات' : 'Save preferences' ?></span>
          <span class="sv-spin" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  </div>
</div>
