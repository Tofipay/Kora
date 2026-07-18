<?php
/** @var \TofiXTv\Core\Seo $seo */
use TofiXTv\Core\Lang;
use TofiXTv\Core\Settings;

$theme = Settings::get('theme', []);
$seoSettings = Settings::get('seo', []);
$fcm = Settings::get('fcm', []);
$altPath = Lang::alternatePath($_SERVER['REQUEST_URI'] ?? '/');
$isAr = Lang::isRtl();
?><!DOCTYPE html>
<html lang="<?= Lang::current() ?>" dir="<?= Lang::dir() ?>" class="dark" data-theme="<?= e($theme['default_mode'] ?? 'dark') ?>" style="background-color:#0f172a;color-scheme:dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>
/* Anti-FOUC theme boot — runs BEFORE any CSS. Dark is the default theme;
 * light applies only when the user explicitly chose it. The class + inline
 * background are set synchronously, so no white flash can ever paint. */
(function(){try{
  var m=localStorage.getItem('q-theme')||'dark',d=m!=='light',r=document.documentElement;
  r.classList.toggle('dark',d);
  r.style.backgroundColor=d?'#0f172a':'#ffffff';
  r.style.colorScheme=d?'dark':'light';
}catch(e){}})();
</script>
<title><?= e($seo->title) ?></title>
<meta name="description" content="<?= e($seo->description) ?>">
<link rel="canonical" href="<?= e($seo->canonical) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<?php
// hreflang alternates — always absolute on the canonical domain and
// percent-encoded (Arabic slugs) via absolute_url(). The current page links
// itself with its canonical slug; the OTHER language always comes from
// Lang::alternatePath() (bare-id form, one 301 to its own slug) so both
// directions derive identically and never carry a foreign-language slug.
// x-default = the Arabic URL (Arabic is the primary audience).
// REQUEST_URI arrives percent-encoded — decode before absolute_url() so the
// path is encoded exactly once (never %25D8 double-encoding).
$curPath = rawurldecode(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/');
$arPath  = Lang::current() === 'ar' ? $curPath : ($altPath ?: '/');
$enPath  = Lang::current() === 'en' ? $curPath : ($altPath ?: '/en');
?>
<link rel="alternate" hreflang="ar" href="<?= e(absolute_url($arPath)) ?>">
<link rel="alternate" hreflang="en" href="<?= e(absolute_url($enPath)) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(absolute_url($arPath)) ?>">
<?php if (!empty($seoSettings['gsc'])): ?><meta name="google-site-verification" content="<?= e($seoSettings['gsc']) ?>"><?php endif; ?>

<meta name="theme-color" content="<?= e($theme['primary'] ?? BRAND_PRIMARY) ?>">
<meta name="color-scheme" content="light dark">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(Lang::siteName()) ?>">

<!-- Open Graph -->
<meta property="og:site_name" content="<?= e(Lang::siteName()) ?>">
<meta property="og:type" content="<?= e($seo->type) ?>">
<meta property="og:title" content="<?= e($seo->title) ?>">
<meta property="og:description" content="<?= e($seo->description) ?>">
<meta property="og:url" content="<?= e($seo->canonical) ?>">
<meta property="og:image" content="<?= e($seo->image) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="<?= $isAr ? 'ar_SA' : 'en_US' ?>">
<meta property="og:locale:alternate" content="<?= $isAr ? 'en_US' : 'ar_SA' ?>">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($seo->title) ?>">
<meta name="twitter:description" content="<?= e($seo->description) ?>">
<meta name="twitter:image" content="<?= e($seo->image) ?>">

<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/brand/icon-192.png">
<link rel="manifest" href="/manifest.webmanifest">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://image.tmdb.org">

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap"></noscript>

<link rel="stylesheet" href="<?= e(asset_min_url('/assets/css/app.css')) ?>">
<?php if (!empty($theme['primary']) || !empty($theme['accent'])): ?>
<style>:root{<?= !empty($theme['primary']) ? '--primary:' . e($theme['primary']) . ';' : '' ?><?= !empty($theme['accent']) ? '--accent:' . e($theme['accent']) . ';' : '' ?>}</style>
<?php endif; ?>

<!-- qseo-build: <?= \TofiXTv\Core\Seo::BUILD ?> -->
<?php foreach ($seo->jsonLd as $schema): ?>
<?= \TofiXTv\Core\Seo::renderJsonLd($schema) ?>

<?php endforeach; ?>

<?php if (!empty($seoSettings['ga4'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($seoSettings['ga4']) ?>"></script>
<script>
/* Google Consent Mode v2 — defaults derive from the visitor's stored cookie
 * choice (q_consent, managed by the consent banner); everything is denied
 * until a choice exists. app.js sends consent updates on change. */
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}
(function(){var qc=null;try{qc=JSON.parse(localStorage.getItem('q_consent')||'null')}catch(e){}
gtag('consent','default',{
  analytics_storage:(qc&&qc.analytics)?'granted':'denied',
  ad_storage:(qc&&qc.marketing)?'granted':'denied',
  ad_user_data:(qc&&qc.marketing)?'granted':'denied',
  ad_personalization:(qc&&qc.marketing)?'granted':'denied',
  wait_for_update:500});})();
gtag('js',new Date());gtag('config','<?= e($seoSettings['ga4']) ?>');</script>
<?php endif; ?>

<script>
window.TOFIXTV = {
  lang: '<?= Lang::current() ?>',
  prefix: '<?= Lang::prefix() ?>',
  build: '<?= e(build_token()) ?>',
  t: { am:'<?= e(t('misc.am')) ?>', pm:'<?= e(t('misc.pm')) ?>', copied:'<?= e(t('misc.copy_done')) ?>',
       live:'<?= e(t('status.live')) ?>', ft:'<?= e(t('status.finished')) ?>',
       ht:'<?= e(t('status.halftime')) ?>', pens:'<?= e(t('match.penalties')) ?>',
       d:'<?= e(t('misc.days')) ?>', h:'<?= e(t('misc.hours')) ?>', m:'<?= e(t('misc.minutes')) ?>', s:'<?= e(t('misc.seconds')) ?>',
       update_ready:'<?= e(t('misc.update_ready')) ?>', update_now:'<?= e(t('misc.update_now')) ?>', update_later:'<?= e(t('misc.update_later')) ?>', updating:'<?= e(t('misc.updating')) ?>',
       ck_title:'<?= e(t('cookies.banner_title')) ?>', ck_text:'<?= e(t('cookies.banner_text')) ?>',
       ck_accept:'<?= e(t('cookies.accept_all')) ?>', ck_reject:'<?= e(t('cookies.reject')) ?>',
       ck_custom:'<?= e(t('cookies.customize')) ?>', ck_saved:'<?= e(t('cookies.saved')) ?>',
       ck_policy:'<?= e(t('page.cookies.title')) ?>',
       tg_title:'<?= e(t('tg.title')) ?>', tg_desc:'<?= e(t('tg.desc')) ?>',
       tg_join:'<?= e(t('tg.join')) ?>', tg_later:'<?= e(t('tg.later')) ?>' },
  fcm: <?= json_encode(array_intersect_key(is_array($fcm) ? $fcm : [], array_flip(['apiKey','authDomain','projectId','messagingSenderId','appId','vapidKey'])), JSON_UNESCAPED_SLASHES) ?: '{}' ?>
};
</script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('nav.home')) ?></a>

<?php require APP_DIR . '/Views/layout/header.php'; ?>

<main id="main" class="page">
<?php if (!empty($seo->breadcrumbs) && count($seo->breadcrumbs) > 1): ?>
  <nav class="breadcrumbs container" aria-label="breadcrumbs">
    <?php foreach ($seo->breadcrumbs as $i => [$name, $u]): ?>
      <?php if ($i > 0): ?><span class="crumb-sep">/</span><?php endif; ?>
      <?php if ($i < count($seo->breadcrumbs) - 1): ?>
        <a href="<?= e($u) ?>"><?= e(mb_substr($name, 0, 40)) ?></a>
      <?php else: ?>
        <span aria-current="page"><?= e(mb_substr($name, 0, 40)) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php require $contentView; ?>
</main>

<?php require APP_DIR . '/Views/layout/footer.php'; ?>
<?php require APP_DIR . '/Views/layout/bottomnav.php'; ?>
<?php require APP_DIR . '/Views/layout/notify-sheet.php'; ?>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset_min_url('/assets/js/api-service.js')) ?>" defer></script>
<script src="<?= e(asset_min_url('/assets/js/app.js')) ?>" defer></script>

<!-- Google AdSense — Auto Ads. Consent-gated (marketing category) AND loaded
     on the FIRST user interaction with a 6s fallback timer, so it can never
     affect first paint, LCP, TBT or the Lighthouse trace. When the visitor
     grants marketing consent later, the q-consent event loads ads there and
     then; a rejection keeps the page entirely ad-script free. -->
<script>
(function () {
  var loaded = false, armed = false, evs = ['scroll', 'touchstart', 'pointerdown', 'keydown', 'mousemove'];
  function consentOK() {
    try { var c = JSON.parse(localStorage.getItem('q_consent') || 'null'); return !!(c && c.marketing); }
    catch (e) { return false; }
  }
  function loadAds() {
    if (loaded || !consentOK()) return; loaded = true;
    evs.forEach(function (e) { removeEventListener(e, loadAds, { passive: true }); });
    var s = document.createElement('script');
    s.async = true; s.crossOrigin = 'anonymous';
    s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6543754410644923';
    document.head.appendChild(s);
  }
  function arm() {
    if (armed) return; armed = true;
    evs.forEach(function (e) { addEventListener(e, loadAds, { passive: true, once: true }); });
    setTimeout(loadAds, 6000);
  }
  if (consentOK()) arm();
  addEventListener('q-consent', function () { if (consentOK()) { arm(); loadAds(); } });
})();
</script>
</body>
</html>
