<?php use TofiXTv\Core\Lang; $isAr = Lang::current() === 'ar'; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container legal-page">
<div class="legal-card">
<?php if ($isAr): ?>
  <p class="legal-intro"><strong><?= e(Lang::siteName()) ?></strong> منصة رياضية عربية متكاملة لعشاق كرة القدم: نتائج مباشرة لحظة بلحظة، مركز مباريات تفصيلي، جداول ترتيب، هدافون، وأخبار رياضية موثوقة — بتجربة سريعة وعصرية باللغتين العربية والإنجليزية.</p>
  <div class="legal-body">
    <h2>رسالتنا</h2>
    <p>أن نكون الوجهة الأولى للجمهور العربي لمتابعة كرة القدم، عبر تقديم معلومة دقيقة وسريعة في واجهة نظيفة تحترم وقت الزائر وتعمل بكفاءة على أي جهاز وأي سرعة اتصال.</p>

    <h2>ماذا نقدم</h2>
    <div class="about-grid">
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <b>نتائج مباشرة</b>
        <p>تحديث لحظي للأهداف والبطاقات وأحداث المباريات في أهم البطولات العالمية والعربية.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
        <b>إحصاءات وجداول</b>
        <p>جداول الترتيب، قوائم الهدافين، تشكيلات الفرق، وإحصاءات تفصيلية لكل مباراة ولاعب.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h6"/></svg>
        <b>أخبار موثوقة</b>
        <p>تغطية إخبارية لأهم الانتقالات والمستجدات من مصادر رياضية معتمدة.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M12 18h.01"/></svg>
        <b>تطبيق ويب حديث</b>
        <p>ثبّت الموقع كتطبيق على هاتفك، وفعّل الإشعارات الفورية للأهداف وبداية المباريات ونهايتها.</p>
      </div>
    </div>

    <h2>تغطيتنا</h2>
    <p>نغطي أبرز البطولات: كأس العالم، دوري أبطال أوروبا، الدوريات الأوروبية الخمسة الكبرى، دوري روشن السعودي، الدوري المصري، البطولات الخليجية والعربية، وكأس أمم أفريقيا وآسيا — مع صفحات مخصصة لكل بطولة وفريق ولاعب.</p>

    <h2>قيمنا التحريرية</h2>
    <ul>
      <li><strong>الدقة قبل السبق:</strong> نعتمد على مزودي بيانات رياضية موثوقين ونصحح أي خطأ فور اكتشافه.</li>
      <li><strong>الحياد:</strong> لا ننحاز لفريق أو اتحاد، ونعرض الأرقام كما هي.</li>
      <li><strong>احترام الزائر:</strong> صفحات سريعة، تصميم واضح، وبدون ممارسات مزعجة.</li>
    </ul>

    <h2>تواصل معنا</h2>
    <p>نرحب دائماً بملاحظاتكم واقتراحاتكم عبر صفحة <a href="<?= e(path('contact')) ?>">اتصل بنا</a>، أو مباشرة على <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>.</p>
  </div>
<?php else: ?>
  <p class="legal-intro"><strong><?= e(Lang::siteName()) ?></strong> is a complete Arabic-first football platform: minute-by-minute live scores, a detailed match center, standings, top scorers and trusted sports news — in a fast, modern experience in both Arabic and English.</p>
  <div class="legal-body">
    <h2>Our mission</h2>
    <p>To be the first destination for Arab football fans by delivering accurate, fast information in a clean interface that respects the visitor's time and performs well on any device and connection speed.</p>

    <h2>What we offer</h2>
    <div class="about-grid">
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <b>Live scores</b>
        <p>Real-time updates for goals, cards and match events across the biggest global and Arab competitions.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
        <b>Stats &amp; standings</b>
        <p>League tables, top-scorer charts, lineups and detailed statistics for every match and player.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h6"/></svg>
        <b>Trusted news</b>
        <p>Coverage of the most important transfers and developments from reliable sports sources.</p>
      </div>
      <div class="about-tile">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="3"/><path d="M12 18h.01"/></svg>
        <b>Modern web app</b>
        <p>Install the site as an app on your phone and enable instant notifications for goals, kick-offs and results.</p>
      </div>
    </div>

    <h2>Our coverage</h2>
    <p>We cover the leading competitions: the World Cup, UEFA Champions League, Europe's top five leagues, the Saudi Pro League, the Egyptian Premier League, Gulf and Arab tournaments, and the Africa Cup of Nations and Asian Cup — with dedicated pages for every competition, team and player.</p>

    <h2>Editorial values</h2>
    <ul>
      <li><strong>Accuracy before speed:</strong> we rely on trusted sports data providers and correct any error as soon as it is discovered.</li>
      <li><strong>Neutrality:</strong> we favor no team or federation and present the numbers as they are.</li>
      <li><strong>Respect for the visitor:</strong> fast pages, clear design and no intrusive practices.</li>
    </ul>

    <h2>Get in touch</h2>
    <p>We always welcome your feedback and suggestions via the <a href="<?= e(path('contact')) ?>">contact page</a> or directly at <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a>.</p>
  </div>
<?php endif; ?>
</div>
</div>
