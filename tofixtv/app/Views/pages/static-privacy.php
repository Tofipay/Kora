<?php use TofiXTv\Core\Lang; $isAr = Lang::current() === 'ar'; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container legal-page">
<div class="legal-card">
  <p class="legal-meta">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 9h18"/></svg>
    <?= $isAr ? 'آخر تحديث: 1 يوليو 2026' : 'Last updated: July 1, 2026' ?>
  </p>
<?php if ($isAr): ?>
  <p class="legal-intro">خصوصيتك تهمنا. توضح هذه السياسة بشفافية ما هي البيانات التي نجمعها عند استخدامك موقع <?= e(Lang::siteName()) ?>، وكيف نستخدمها، وما هي خياراتك للتحكم بها. باستخدامك الموقع فإنك توافق على الممارسات الموضحة في هذه الصفحة.</p>
  <div class="legal-body">
    <h2>من نحن</h2>
    <p><?= e(Lang::siteName()) ?> منصة إخبارية رياضية تقدم نتائج المباريات المباشرة وجداول الترتيب والأخبار الرياضية. لا يتطلب استخدام الموقع إنشاء حساب أو تقديم أي بيانات شخصية تعريفية.</p>

    <h2>البيانات التي نجمعها</h2>
    <ul>
      <li><strong>تفضيلات المتصفح:</strong> نستخدم التخزين المحلي (Local Storage) في متصفحك لحفظ تفضيلاتك مثل الوضع الليلي/النهاري، واللغة، وقائمة الفرق والبطولات المفضلة. تبقى هذه البيانات على جهازك ولا تُرسل إلى خوادمنا.</li>
      <li><strong>الإشعارات:</strong> عند تفعيلك للإشعارات الفورية، نخزّن رمز جهاز مجهول الهوية (Device Token) يُستخدم حصرياً لإرسال تنبيهات الأهداف والمباريات، ويمكنك إلغاؤه في أي وقت من إعدادات المتصفح.</li>
      <li><strong>بيانات الاستخدام:</strong> قد نجمع إحصاءات مجهولة الهوية عن الصفحات الأكثر زيارة ونوع الجهاز والمتصفح، بهدف تحسين الأداء وتجربة الاستخدام فقط.</li>
    </ul>

    <h2>ملفات تعريف الارتباط (Cookies)</h2>
    <p>نستخدم ملفات تعريف ارتباط تشغيلية ضرورية لعمل الموقع، وأدوات قياس مثل Google Analytics بشكل مجهول الهوية لفهم كيفية استخدام الموقع وتحسينه. يمكنك تعطيل ملفات تعريف الارتباط من إعدادات متصفحك، مع العلم أن بعض الميزات قد لا تعمل بالشكل الأمثل عندها.</p>

    <h2>الإعلانات والجهات الخارجية</h2>
    <p>قد نعرض إعلانات من جهات خارجية مثل <strong>Google AdSense</strong> لدعم استمرارية الخدمة المجانية. تنطبق النقاط التالية:</p>
    <ul>
      <li>تستخدم Google، بصفتها مورّداً خارجياً، ملفات تعريف الارتباط لعرض الإعلانات على موقعنا.</li>
      <li>يتيح استخدام Google لملف تعريف الارتباط الإعلاني عرض إعلانات لمستخدمينا بناءً على زياراتهم لموقعنا ومواقع أخرى على الإنترنت.</li>
      <li>يمكنك إيقاف الإعلانات المخصصة في أي وقت عبر صفحة <a href="https://adssettings.google.com" target="_blank" rel="noopener nofollow">إعدادات إعلانات Google</a>، أو عبر <a href="https://www.aboutads.info" target="_blank" rel="noopener nofollow">www.aboutads.info</a> لإلغاء استخدام ملفات تعريف الارتباط الخاصة بمورّدين آخرين.</li>
      <li>لمزيد من التفاصيل حول كيفية استخدام Google للبيانات، راجع <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener nofollow">سياسة Google لبيانات المواقع الشريكة</a>.</li>
    </ul>

    <h2>مزودو بيانات المباريات</h2>
    <p>تُعرض نتائج المباريات والإحصاءات والأخبار من مزودي بيانات رياضية موثوقين. قد تحتوي صفحاتنا على روابط لمواقع خارجية؛ لسنا مسؤولين عن ممارسات الخصوصية أو محتوى تلك المواقع، وننصحك بمراجعة سياسات الخصوصية الخاصة بها.</p>

    <h2>مشاركة البيانات</h2>
    <p>لا نبيع أو نؤجر أو نتاجر بأي بيانات تخص زوارنا مع أي طرف ثالث. تقتصر المشاركة على ما تتطلبه الخدمات التقنية الموضحة أعلاه (القياس والإعلانات والإشعارات).</p>

    <h2>أمان البيانات</h2>
    <p>يعمل الموقع بالكامل عبر اتصال مشفّر (HTTPS)، ونطبق إجراءات تقنية وتنظيمية مناسبة لحماية أي بيانات نعالجها من الوصول غير المصرح به.</p>

    <h2>خصوصية الأطفال</h2>
    <p>موقعنا موجه للجمهور العام ولا نجمع عن قصد أي بيانات شخصية من الأطفال دون سن 13 عاماً. إذا كنت تعتقد أن طفلاً زوّدنا ببيانات شخصية، يرجى التواصل معنا لحذفها فوراً.</p>

    <h2>حقوقك</h2>
    <ul>
      <li>حذف بيانات التفضيلات في أي وقت عبر مسح بيانات الموقع من متصفحك.</li>
      <li>إلغاء الاشتراك في الإشعارات من إعدادات المتصفح أو الجهاز.</li>
      <li>إيقاف الإعلانات المخصصة عبر الروابط الموضحة أعلاه.</li>
      <li>التواصل معنا للاستفسار عن أي بيانات تخصك أو طلب حذفها.</li>
    </ul>

    <h2>التعديلات على هذه السياسة</h2>
    <p>قد نحدّث هذه السياسة من وقت لآخر لمواكبة التغييرات التقنية أو التنظيمية. سيُشار إلى تاريخ آخر تحديث أعلى هذه الصفحة، ويسري أي تعديل فور نشره هنا.</p>

    <h2>التواصل معنا</h2>
    <p>لأي استفسار يتعلق بالخصوصية أو لطلب حذف بياناتك، راسلنا عبر صفحة <a href="<?= e(path('contact')) ?>">اتصل بنا</a> أو مباشرة على <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>.</p>
  </div>
  <p class="legal-note">هذه الصفحة جزء من التزامنا بالشفافية تجاه زوارنا، وتُقرأ جنباً إلى جنب مع <a href="<?= e(path('terms')) ?>">شروط الاستخدام</a>.</p>
<?php else: ?>
  <p class="legal-intro">Your privacy matters to us. This policy explains transparently what data we collect when you use <?= e(Lang::siteName()) ?>, how we use it, and the choices you have. By using the site you agree to the practices described on this page.</p>
  <div class="legal-body">
    <h2>Who we are</h2>
    <p><?= e(Lang::siteName()) ?> is a sports media platform providing live scores, standings and football news. Using the site requires no account and no personally identifying information.</p>

    <h2>Data we collect</h2>
    <ul>
      <li><strong>Browser preferences:</strong> we use your browser's local storage to keep preferences such as dark/light mode, language, and your favorite teams and competitions. This data stays on your device and is never sent to our servers.</li>
      <li><strong>Notifications:</strong> if you enable push notifications, we store an anonymous device token used solely to deliver goal and match alerts. You can revoke it at any time from your browser settings.</li>
      <li><strong>Usage data:</strong> we may collect anonymous statistics about popular pages, device type and browser, used only to improve performance and user experience.</li>
    </ul>

    <h2>Cookies</h2>
    <p>We use essential functional cookies plus anonymous measurement tools such as Google Analytics to understand how the site is used and improve it. You can disable cookies in your browser settings, though some features may not work optimally.</p>

    <h2>Advertising and third parties</h2>
    <p>We may display ads from third-party vendors such as <strong>Google AdSense</strong> to keep the service free. The following applies:</p>
    <ul>
      <li>Third-party vendors, including Google, use cookies to serve ads on our site.</li>
      <li>Google's use of the advertising cookie enables it and its partners to serve ads to our users based on their visits to our site and/or other sites on the Internet.</li>
      <li>You may opt out of personalized advertising at any time via <a href="https://adssettings.google.com" target="_blank" rel="noopener nofollow">Google Ads Settings</a>, or via <a href="https://www.aboutads.info" target="_blank" rel="noopener nofollow">www.aboutads.info</a> to opt out of other vendors' cookies.</li>
      <li>For details on how Google uses data, see <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener nofollow">Google's partner sites policy</a>.</li>
    </ul>

    <h2>Sports data providers</h2>
    <p>Match results, statistics and news are supplied by trusted sports data providers. Our pages may link to external sites; we are not responsible for their privacy practices or content and encourage you to review their policies.</p>

    <h2>Data sharing</h2>
    <p>We do not sell, rent or trade any visitor data with third parties. Sharing is limited to what the technical services described above require (measurement, advertising, notifications).</p>

    <h2>Security</h2>
    <p>The entire site runs over an encrypted connection (HTTPS), and we apply appropriate technical and organizational measures to protect any data we process against unauthorized access.</p>

    <h2>Children's privacy</h2>
    <p>Our site is intended for a general audience and we do not knowingly collect personal data from children under 13. If you believe a child has provided us personal data, please contact us and we will delete it promptly.</p>

    <h2>Your rights</h2>
    <ul>
      <li>Delete preference data at any time by clearing site data in your browser.</li>
      <li>Unsubscribe from notifications via browser or device settings.</li>
      <li>Opt out of personalized ads via the links above.</li>
      <li>Contact us to ask about, or request deletion of, any data related to you.</li>
    </ul>

    <h2>Changes to this policy</h2>
    <p>We may update this policy from time to time to reflect technical or regulatory changes. The last-updated date is shown at the top of this page and any change takes effect once published here.</p>

    <h2>Contact us</h2>
    <p>For any privacy question or data deletion request, reach us via the <a href="<?= e(path('contact')) ?>">contact page</a> or directly at <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>.</p>
  </div>
  <p class="legal-note">This page is part of our commitment to transparency and should be read together with our <a href="<?= e(path('terms')) ?>">Terms of Use</a>.</p>
<?php endif; ?>
</div>
</div>
