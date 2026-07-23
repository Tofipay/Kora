<div class="app-dialog" id="app-required-dialog" hidden aria-hidden="true">
  <div class="app-dialog-backdrop" data-app-dialog-close></div>
  <section class="app-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="app-dialog-title" aria-describedby="app-dialog-text" tabindex="-1">
    <button class="app-dialog-x" type="button" data-app-dialog-close aria-label="<?= e(t('misc.close')) ?>">×</button>
    <img src="/assets/brand/icon-192.png" alt="" width="72" height="72">
    <h2 id="app-dialog-title"><?= e(t('channels.app_missing_title')) ?></h2>
    <p id="app-dialog-text"><?= e(t('channels.app_missing_text')) ?></p>
    <div class="app-dialog-actions">
      <a class="btn btn-primary" id="app-dialog-download" href="https://t.me/alokalive" target="_blank" rel="noopener nofollow"><?= e(t('channels.download_app')) ?></a>
      <button class="btn btn-ghost" type="button" data-app-dialog-close><?= e(t('misc.cancel')) ?></button>
    </div>
  </section>
</div>
