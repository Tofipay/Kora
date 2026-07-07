<?php use Qamhad\Core\Lang; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container"><div class="card glass-soft prose">
<?php if (Lang::current() === 'ar'): ?>
  <p>باستخدامك موقع <?= e(Lang::siteName()) ?> فإنك توافق على هذه الشروط:</p>
  <ul>
    <li>المحتوى مقدم لأغراض إعلامية فقط، ولا نضمن دقة أو اكتمال البيانات في كل الأوقات.</li>
    <li>يُمنع إعادة نشر المحتوى أو استخدامه تجارياً دون إذن كتابي.</li>
    <li>يُمنع أي استخدام يسيء للخدمة أو يعطّلها أو يحاول الوصول غير المصرح به.</li>
    <li>قد نعدّل هذه الشروط في أي وقت وتسري النسخة المنشورة على هذه الصفحة.</li>
  </ul>
<?php else: ?>
  <p>By using <?= e(Lang::siteName()) ?> you agree to the following:</p>
  <ul>
    <li>Content is provided for informational purposes only; we do not guarantee accuracy or completeness at all times.</li>
    <li>Republishing or commercial use of content requires written permission.</li>
    <li>Any abusive use, service disruption or unauthorized access attempt is prohibited.</li>
    <li>We may update these terms at any time; the version published on this page applies.</li>
  </ul>
<?php endif; ?>
</div></div>
