<?php use Qamhad\Core\Lang; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container"><div class="card glass-soft prose">
<?php if (Lang::current() === 'ar'): ?>
  <p><strong><?= e(Lang::siteName()) ?></strong> منصة رياضية متكاملة لمتابعة كرة القدم لحظة بلحظة: نتائج مباشرة، مركز مباريات تفصيلي، جداول الترتيب، الهدافون، وآخر الأخبار الرياضية باللغتين العربية والإنجليزية.</p>
  <p>نغطي أهم البطولات العالمية والعربية — كأس العالم، الدوريات الأوروبية الخمسة الكبرى، دوري روشن السعودي وغيرها — بتجربة استخدام سريعة وحديثة تعمل على جميع الأجهزة وتدعم وضعي الإضاءة الفاتح والداكن.</p>
  <p>يمكنك تثبيت الموقع كتطبيق على هاتفك ومتابعة الإشعارات الفورية للأهداف وبداية ونهاية المباريات.</p>
<?php else: ?>
  <p><strong><?= e(Lang::siteName()) ?></strong> is a complete football platform: live scores, a detailed match center, standings, top scorers and the latest sports news in Arabic and English.</p>
  <p>We cover the biggest global and Arab competitions — the World Cup, Europe's top five leagues, the Saudi Pro League and more — with a fast, modern experience that works on every device with light and dark modes.</p>
  <p>Install the site as an app on your phone and receive instant notifications for goals, kick-offs and full-time results.</p>
<?php endif; ?>
</div></div>
