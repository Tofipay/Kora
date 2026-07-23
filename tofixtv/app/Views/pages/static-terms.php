<?php use TofiXTv\Core\Lang; $isAr = Lang::current() === 'ar'; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container legal-page">
<div class="legal-card">
  <p class="legal-meta">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 9h18"/></svg>
    <?= $isAr ? 'آخر تحديث: 1 يوليو 2026' : 'Last updated: July 1, 2026' ?>
  </p>
<?php if ($isAr): ?>
  <p class="legal-intro">تحدد هذه الشروط القواعد المنظمة لاستخدام موقع <?= e(Lang::siteName()) ?> وجميع خدماته. يرجى قراءتها بعناية؛ فدخولك إلى الموقع واستمرارك في استخدامه يعني موافقتك الكاملة عليها.</p>
  <div class="legal-body">
    <h2>1. طبيعة الخدمة</h2>
    <p>يقدم <?= e(Lang::siteName()) ?> محتوى رياضياً إخبارياً يشمل نتائج المباريات المباشرة، وجداول الترتيب، وإحصاءات اللاعبين، والأخبار الرياضية. المحتوى مقدم لأغراض إعلامية عامة فقط، ولا يشكل أي التزام تعاقدي أو استشارة من أي نوع.</p>

    <h2>2. دقة المعلومات</h2>
    <p>نحرص على تحديث البيانات لحظة بلحظة من مصادر موثوقة، إلا أننا لا نضمن دقة أو اكتمال أو حداثة المعلومات في جميع الأوقات، ولا نتحمل مسؤولية أي قرارات تُتخذ بناءً عليها. النتائج والإحصاءات الرسمية هي الصادرة عن الاتحادات والهيئات الرياضية المختصة.</p>

    <h2>3. الملكية الفكرية</h2>
    <ul>
      <li>تصميم الموقع وهويته البصرية وشعاره ونصوصه التحريرية ملك لـ<?= e(Lang::siteName()) ?>.</li>
      <li>شعارات الأندية والبطولات وصور اللاعبين هي علامات تجارية مملوكة لأصحابها وتُعرض لأغراض تعريفية إخبارية فقط.</li>
      <li>لا يجوز إعادة نشر المحتوى أو نسخه أو استخدامه تجارياً دون إذن كتابي مسبق منا، باستثناء الاقتباس المحدود مع ذكر المصدر ورابطه.</li>
    </ul>

    <h2>4. الاستخدام المقبول</h2>
    <p>يلتزم المستخدم عند استخدام الموقع بالامتناع عن:</p>
    <ul>
      <li>أي استخدام يخالف الأنظمة والقوانين المعمول بها.</li>
      <li>محاولة الوصول غير المصرح به إلى أنظمة الموقع أو تعطيل عمله أو إثقال خوادمه.</li>
      <li>جمع المحتوى بشكل آلي (Scraping) أو إعادة توزيعه دون إذن.</li>
      <li>انتحال صفة الموقع أو استخدام اسمه أو شعاره بشكل مضلل.</li>
    </ul>

    <h2>5. الروابط والمحتوى الخارجي</h2>
    <p>قد يتضمن الموقع روابط لمواقع أو خدمات خارجية لا نملك السيطرة عليها. إدراج أي رابط لا يعني تأييدنا لمحتواه، ولا نتحمل أي مسؤولية عن محتوى أو ممارسات تلك الجهات.</p>

    <h2>6. الإعلانات</h2>
    <p>قد يعرض الموقع إعلانات من شبكات إعلانية خارجية مثل Google AdSense. المعلنون مسؤولون وحدهم عن محتوى إعلاناتهم، ولا يشكل ظهور إعلان على صفحاتنا توصية منا بالمنتج أو الخدمة المعلن عنها. راجع <a href="<?= e(path('privacy')) ?>">سياسة الخصوصية</a> لمعرفة كيفية عمل ملفات تعريف الارتباط الإعلانية وخيارات التحكم بها.</p>

    <h2>7. إخلاء المسؤولية</h2>
    <p>تُقدَّم الخدمة «كما هي» و«حسب توفرها» دون أي ضمانات صريحة أو ضمنية. لا نتحمل — إلى الحد الذي يسمح به النظام — أي مسؤولية عن أضرار مباشرة أو غير مباشرة تنشأ عن استخدام الموقع أو تعذر استخدامه أو الاعتماد على محتواه.</p>

    <h2>8. توفر الخدمة</h2>
    <p>نبذل جهدنا لإبقاء الموقع متاحاً على مدار الساعة، لكننا لا نضمن استمرارية الخدمة دون انقطاع، ويحق لنا تعديل أو إيقاف أي ميزة مؤقتاً أو دائماً لأغراض الصيانة أو التطوير.</p>

    <h2>9. تعديل الشروط</h2>
    <p>يحق لنا تحديث هذه الشروط في أي وقت، ويُعتبر استمرارك في استخدام الموقع بعد نشر أي تعديل موافقةً عليه. يُوضّح تاريخ آخر تحديث أعلى هذه الصفحة.</p>

    <h2>10. التواصل</h2>
    <p>لأي استفسار حول هذه الشروط يمكنك مراسلتنا عبر صفحة <a href="<?= e(path('contact')) ?>">اتصل بنا</a> أو على البريد <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>.</p>
  </div>
  <p class="legal-note">تُقرأ هذه الشروط جنباً إلى جنب مع <a href="<?= e(path('privacy')) ?>">سياسة الخصوصية</a> التي تشكل جزءاً لا يتجزأ منها.</p>
<?php else: ?>
  <p class="legal-intro">These terms set the rules governing the use of <?= e(Lang::siteName()) ?> and all of its services. Please read them carefully — accessing and continuing to use the site means you fully accept them.</p>
  <div class="legal-body">
    <h2>1. Nature of the service</h2>
    <p><?= e(Lang::siteName()) ?> provides sports media content including live scores, standings, player statistics and football news. Content is offered for general informational purposes only and constitutes no contractual commitment or advice of any kind.</p>

    <h2>2. Accuracy of information</h2>
    <p>We strive to update data in real time from reliable sources, yet we do not guarantee the accuracy, completeness or freshness of information at all times, and accept no responsibility for decisions made based on it. Official results and statistics are those issued by the competent football federations and bodies.</p>

    <h2>3. Intellectual property</h2>
    <ul>
      <li>The site's design, visual identity, logo and editorial texts belong to <?= e(Lang::siteName()) ?>.</li>
      <li>Club and competition logos and player images are trademarks of their respective owners, displayed solely for news identification purposes.</li>
      <li>Republishing, copying or commercial use of content requires our prior written permission, except limited quotation with source attribution and a link.</li>
    </ul>

    <h2>4. Acceptable use</h2>
    <p>When using the site you agree not to:</p>
    <ul>
      <li>use it in any way that violates applicable laws and regulations;</li>
      <li>attempt unauthorized access to our systems, disrupt the service or overload our servers;</li>
      <li>scrape or automatically harvest content, or redistribute it without permission;</li>
      <li>impersonate the site or use its name or logo in a misleading way.</li>
    </ul>

    <h2>5. External links and content</h2>
    <p>The site may contain links to external sites or services we do not control. Listing a link does not imply endorsement, and we accept no responsibility for the content or practices of those parties.</p>

    <h2>6. Advertising</h2>
    <p>The site may display ads served by third-party networks such as Google AdSense. Advertisers are solely responsible for their ad content, and the appearance of an ad on our pages is not a recommendation of the advertised product or service. See our <a href="<?= e(path('privacy')) ?>">Privacy Policy</a> for how advertising cookies work and your control options.</p>

    <h2>7. Disclaimer</h2>
    <p>The service is provided "as is" and "as available" without warranties of any kind, express or implied. To the maximum extent permitted by law, we accept no liability for direct or indirect damages arising from using the site, inability to use it, or reliance on its content.</p>

    <h2>8. Service availability</h2>
    <p>We do our best to keep the site available around the clock, but we do not guarantee uninterrupted service and may modify or suspend any feature temporarily or permanently for maintenance or development.</p>

    <h2>9. Changes to these terms</h2>
    <p>We may update these terms at any time; continuing to use the site after a change is published constitutes acceptance. The last-updated date is shown at the top of this page.</p>

    <h2>10. Contact</h2>
    <p>For any question about these terms, reach us via the <a href="<?= e(path('contact')) ?>">contact page</a> or at <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>.</p>
  </div>
  <p class="legal-note">These terms are to be read together with our <a href="<?= e(path('privacy')) ?>">Privacy Policy</a>, which forms an integral part of them.</p>
<?php endif; ?>
</div>
</div>
