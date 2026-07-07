<?php use Qamhad\Core\Lang; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container"><div class="card glass-soft prose">
<?php if (Lang::current() === 'ar'): ?>
  <p>يسعدنا تواصلك معنا لأي استفسار أو اقتراح أو تعاون إعلاني:</p>
<?php else: ?>
  <p>We'd love to hear from you — questions, suggestions or advertising:</p>
<?php endif; ?>
  <p class="contact-line">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
    <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>
  </p>
</div></div>
