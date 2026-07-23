<?php use TofiXTv\Core\Lang; $isAr = Lang::current() === 'ar'; ?>
<div class="container page-head"><h1><?= e($title) ?></h1></div>
<div class="container legal-page">
<div class="legal-card">
<?php if ($isAr): ?>
  <p class="legal-intro">يسعدنا تواصلك معنا — سواء كان لديك استفسار، اقتراح لتطوير الموقع، ملاحظة على المحتوى، أو رغبة في التعاون الإعلاني. نرد على الرسائل عادةً خلال 24 إلى 48 ساعة عمل.</p>
  <div class="contact-grid">
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
      </span>
      <div>
        <b>الاستفسارات العامة</b>
        <p>لأي سؤال حول الموقع وخدماته.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>
      </span>
      <div>
        <b>المحتوى والحقوق</b>
        <p>لملاحظات المحتوى أو طلبات الحقوق والتصحيح.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-7-7 18-2.5-7.5z"/></svg>
      </span>
      <div>
        <b>الإعلانات والشراكات</b>
        <p>لعروض التعاون الإعلاني والرعايات.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
  </div>
  <p class="legal-note">عند مراسلتنا بخصوص مشكلة تقنية، يُفضّل ذكر نوع الجهاز والمتصفح ورابط الصفحة لمساعدتنا على معالجتها بشكل أسرع. للاطلاع على كيفية تعاملنا مع بياناتك راجع <a href="<?= e(path('privacy')) ?>">سياسة الخصوصية</a>.</p>
<?php else: ?>
  <p class="legal-intro">We'd love to hear from you — whether you have a question, a suggestion, feedback on our content, or an advertising partnership inquiry. We typically reply within 24–48 business hours.</p>
  <div class="contact-grid">
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
      </span>
      <div>
        <b>General inquiries</b>
        <p>Any question about the site and its services.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/></svg>
      </span>
      <div>
        <b>Content &amp; rights</b>
        <p>Content feedback, rights requests and corrections.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
    <div class="contact-card">
      <span class="contact-ic">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-7-7 18-2.5-7.5z"/></svg>
      </span>
      <div>
        <b>Advertising &amp; partnerships</b>
        <p>Advertising offers and sponsorships.</p>
        <a href="<?= e(SITE_EMAIL) ?>" target="_blank" rel="noopener">@alokalive</a>
      </div>
    </div>
  </div>
  <p class="legal-note">When reporting a technical issue, please mention your device, browser and the page URL so we can address it faster. To learn how we handle your data, see our <a href="<?= e(path('privacy')) ?>">Privacy Policy</a>.</p>
<?php endif; ?>
</div>
</div>
