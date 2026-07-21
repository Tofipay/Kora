<?php
/** Cookie Settings — the persistent consent-preferences page.
 *  The toggles are wired by the consent module in app.js (q_consent). */
use TofiXTv\Core\Lang;
$isAr = Lang::current() === 'ar';
?>
<div class="container page-head">
  <h1><?= e(t('cookies.settings_title')) ?></h1>
  <p class="page-sub"><?= e(t('cookies.settings_sub')) ?></p>
</div>

<div class="container more-page">
  <section class="more-group glass-soft" id="cookie-prefs" data-cookie-prefs>
    <?php
    $cats = [
        ['necessary', t('cookies.cat_necessary'), t('cookies.cat_necessary_desc'), true],
        ['analytics', t('cookies.cat_analytics'), t('cookies.cat_analytics_desc'), false],
        ['functional', t('cookies.cat_functional'), t('cookies.cat_functional_desc'), false],
        ['marketing', t('cookies.cat_marketing'), t('cookies.cat_marketing_desc'), false],
    ];
    foreach ($cats as [$key, $label, $desc, $locked]): ?>
    <div class="mg-row ck-row">
      <span class="mg-label">
        <b><?= e($label) ?></b>
        <small><?= e($desc) ?></small>
      </span>
      <label class="switch<?= $locked ? ' is-locked' : '' ?>">
        <input type="checkbox" data-consent-cat="<?= e($key) ?>" <?= $locked ? 'checked disabled' : '' ?>>
        <span class="sw-track" aria-hidden="true"></span>
      </label>
    </div>
    <?php endforeach; ?>

    <div class="ck-actions">
      <button class="btn btn-primary" type="button" data-consent-save><?= e(t('cookies.save')) ?></button>
      <button class="btn btn-ghost" type="button" data-consent-accept-all><?= e(t('cookies.accept_all')) ?></button>
      <button class="btn btn-ghost" type="button" data-consent-reject><?= e(t('cookies.reject')) ?></button>
    </div>
    <p class="ck-note"><?= $isAr
        ? 'الفئة الضرورية مفعّلة دائماً لأن الموقع لا يعمل بدونها. التفاصيل الكاملة في '
        : 'The necessary category is always on because the site cannot work without it. Full details in the ' ?>
      <a href="<?= e(path('cookies')) ?>"><?= e(t('page.cookies.title')) ?></a>.
    </p>
  </section>
</div>
