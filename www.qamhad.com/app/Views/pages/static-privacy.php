<?php use Qamhad\Core\Lang; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container"><div class="card glass-soft prose">
<?php if (Lang::current() === 'ar'): ?>
  <h2>البيانات التي نجمعها</h2>
  <p>لا يتطلب استخدام الموقع إنشاء حساب. نستخدم التخزين المحلي في متصفحك لحفظ تفضيلاتك (الوضع الليلي، المفضلة). عند تفعيل الإشعارات نخزّن رمز الجهاز لإرسال التنبيهات فقط.</p>
  <h2>ملفات تعريف الارتباط</h2>
  <p>قد نستخدم ملفات تعريف ارتباط تشغيلية وأدوات قياس مجهولة الهوية لتحسين الأداء.</p>
  <h2>الطرف الثالث</h2>
  <p>تُعرض بيانات المباريات والأخبار من مزودي بيانات رياضية، وقد تحتوي صفحاتنا على روابط لمواقع خارجية لا نتحمل مسؤولية محتواها.</p>
  <h2>التواصل</h2>
  <p>لأي استفسار حول الخصوصية راسلنا عبر صفحة <a href="<?= e(path('contact')) ?>">اتصل بنا</a>.</p>
<?php else: ?>
  <h2>Data we collect</h2>
  <p>No account is required. We use your browser's local storage to keep preferences (dark mode, favorites). If you enable notifications we store a device token solely to deliver alerts.</p>
  <h2>Cookies</h2>
  <p>We may use functional cookies and anonymous analytics to improve performance.</p>
  <h2>Third parties</h2>
  <p>Match data and news are provided by sports data providers; our pages may link to external sites whose content we are not responsible for.</p>
  <h2>Contact</h2>
  <p>For privacy questions, reach us via the <a href="<?= e(path('contact')) ?>">contact page</a>.</p>
<?php endif; ?>
</div></div>
