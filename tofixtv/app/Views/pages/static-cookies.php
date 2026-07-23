<?php use TofiXTv\Core\Lang; $isAr = Lang::current() === 'ar'; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container legal-page">
<div class="legal-card">
  <p class="legal-meta">
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 9h18"/></svg>
    <?= $isAr ? 'آخر تحديث: 17 يوليو 2026' : 'Last updated: July 17, 2026' ?>
  </p>
<?php if ($isAr): ?>
  <p class="legal-intro">توضح هذه السياسة ما هي ملفات تعريف الارتباط (الكوكيز) وكيف يستخدمها موقع <?= e(Lang::siteName()) ?>، وما هي الفئات التي يمكنك التحكم بها من <a href="<?= e(path('cookie-settings')) ?>">إعدادات الكوكيز</a>.</p>
  <div class="legal-body">
    <h2>ما هي ملفات تعريف الارتباط؟</h2>
    <p>ملفات تعريف الارتباط هي ملفات نصية صغيرة تُخزَّن على جهازك عند زيارة المواقع. تساعدنا على تشغيل الموقع بشكل صحيح، وتذكّر تفضيلاتك، وفهم كيفية استخدام الموقع لتحسينه.</p>

    <h2>فئات ملفات تعريف الارتباط التي نستخدمها</h2>
    <ul>
      <li><strong>الضرورية:</strong> لازمة لعمل الموقع الأساسي (تفضيل اللغة، الوضع الليلي، أمان الجلسات). لا يمكن تعطيلها لأن الموقع لا يعمل بدونها.</li>
      <li><strong>التحليلات:</strong> أدوات قياس مجهولة الهوية (مثل Google Analytics) تساعدنا على معرفة الصفحات الأكثر زيارة وتحسين الأداء. تُفعَّل فقط بعد موافقتك.</li>
      <li><strong>الوظيفية:</strong> تحفظ تفضيلات إضافية مثل الفرق المفضلة وإعدادات المشغّل لتجربة أفضل.</li>
      <li><strong>التسويقية:</strong> تُستخدم من شركاء الإعلانات (مثل Google AdSense) لعرض إعلانات ملائمة. تُفعَّل فقط بعد موافقتك، ويمكنك رفضها بالكامل مع استمرار الموقع بالعمل طبيعياً.</li>
    </ul>

    <h2>كيف تتحكم بالكوكيز؟</h2>
    <ul>
      <li>من صفحة <a href="<?= e(path('cookie-settings')) ?>">إعدادات الكوكيز</a> يمكنك قبول الكل أو رفض غير الضروري أو تخصيص كل فئة على حدة، وتغيير اختيارك في أي وقت.</li>
      <li>يمكنك أيضاً حذف أو حظر ملفات تعريف الارتباط من إعدادات متصفحك.</li>
      <li>لإيقاف الإعلانات المخصصة من Google راجع <a href="https://adssettings.google.com" target="_blank" rel="noopener nofollow">إعدادات إعلانات Google</a>.</li>
    </ul>

    <h2>المزيد من المعلومات</h2>
    <p>تُقرأ هذه الصفحة جنباً إلى جنب مع <a href="<?= e(path('privacy')) ?>">سياسة الخصوصية</a>. لأي استفسار راسلنا عبر <a href="<?= e(path('contact')) ?>">صفحة التواصل</a>.</p>
  </div>
<?php else: ?>
  <p class="legal-intro">This policy explains what cookies are, how <?= e(Lang::siteName()) ?> uses them, and which categories you can control from the <a href="<?= e(path('cookie-settings')) ?>">cookie settings</a> page.</p>
  <div class="legal-body">
    <h2>What are cookies?</h2>
    <p>Cookies are small text files stored on your device when you visit websites. They help the site work correctly, remember your preferences, and help us understand how the site is used so we can improve it.</p>

    <h2>Cookie categories we use</h2>
    <ul>
      <li><strong>Necessary:</strong> required for core functionality (language preference, dark mode, session security). They cannot be disabled because the site does not work without them.</li>
      <li><strong>Analytics:</strong> anonymous measurement tools (such as Google Analytics) that show us which pages are popular and help improve performance. Enabled only with your consent.</li>
      <li><strong>Functional:</strong> store extra preferences such as favorite teams and player settings for a better experience.</li>
      <li><strong>Marketing:</strong> used by advertising partners (such as Google AdSense) to serve relevant ads. Enabled only with your consent — you can reject them entirely and the site keeps working normally.</li>
    </ul>

    <h2>How to control cookies</h2>
    <ul>
      <li>From the <a href="<?= e(path('cookie-settings')) ?>">cookie settings</a> page you can accept all, reject non-essential, or customize each category — and change your choice at any time.</li>
      <li>You can also delete or block cookies from your browser settings.</li>
      <li>To opt out of Google personalized ads, see <a href="https://adssettings.google.com" target="_blank" rel="noopener nofollow">Google Ads Settings</a>.</li>
    </ul>

    <h2>More information</h2>
    <p>This page should be read together with our <a href="<?= e(path('privacy')) ?>">Privacy Policy</a>. For any question, reach us via the <a href="<?= e(path('contact')) ?>">contact page</a>.</p>
  </div>
<?php endif; ?>
</div>
</div>
