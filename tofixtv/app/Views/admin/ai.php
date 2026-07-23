<?php
/** Admin — AI Assistant control. Expects: $msg, $test, $s (saved), $cfg (effective), $csrf. */
require __DIR__ . '/_shell.php';
admin_top('المساعد الذكي', 'ai');
$enabled  = $cfg['enabled'];
$provider = $cfg['provider'];
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<?php if (!empty($test)): ?><div class="msg"><?= e($test) ?></div><?php endif; ?>

<div class="card">
  <b>حالة المساعد والمزوّد</b>
  <small class="hint">عند الإيقاف تختفي أيقونة المساعد من الموقع بالكامل ويتوقف مسار /api/ai-chat فوراً — بدون أي تأثير على بقية الموقع.</small>
  <form method="post" action="/<?= ADMIN_PATH ?>/ai">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label style="display:inline-flex;align-items:center;gap:10px;margin-top:14px;font-size:14px;color:var(--tx)">
      <input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>>
      تفعيل المساعد الذكي (إظهاره للزوار)
    </label>

    <label>مزوّد الذكاء الاصطناعي</label>
    <select name="provider">
      <option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Google Gemini (الافتراضي)</option>
      <option value="openai" <?= $provider === 'openai' ? 'selected' : '' ?>>OpenAI-Compatible (bluesminds وغيره)</option>
    </select>

    <div class="row" style="margin-top:6px">
      <div>
        <b style="font-size:13px">إعدادات Gemini</b>
        <label>Base URL</label>
        <input type="url" name="gemini_base" dir="ltr" value="<?= e((string)($s['gemini_base'] ?? '')) ?>" placeholder="<?= e($cfg['gemini_base']) ?>">
        <label>API Key</label>
        <input type="password" name="gemini_key" dir="ltr" value="<?= e((string)($s['gemini_key'] ?? '')) ?>" placeholder="اتركه فارغاً لاستخدام المفتاح المدمج">
        <label>الموديل</label>
        <input type="text" name="gemini_model" dir="ltr" value="<?= e((string)($s['gemini_model'] ?? '')) ?>" placeholder="<?= e($cfg['gemini_model']) ?>">
      </div>
      <div>
        <b style="font-size:13px">إعدادات OpenAI-Compatible</b>
        <label>Base URL</label>
        <input type="url" name="base_url" dir="ltr" value="<?= e((string)($s['base_url'] ?? '')) ?>" placeholder="<?= e($cfg['base_url']) ?>">
        <label>API Key</label>
        <input type="password" name="api_key" dir="ltr" value="<?= e((string)($s['api_key'] ?? '')) ?>" placeholder="اتركه فارغاً لاستخدام المفتاح المدمج">
        <label>الموديل</label>
        <input type="text" name="model" dir="ltr" value="<?= e((string)($s['model'] ?? '')) ?>" placeholder="<?= e($cfg['model']) ?>">
      </div>
    </div>
    <small class="hint">أي حقل فارغ يعني استخدام القيمة الافتراضية المدمجة. المفاتيح لا تظهر أبداً في صفحات الزوار — تُستخدم من الخادم فقط.</small>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn" name="save_ai" value="1">حفظ الإعدادات</button>
      <button class="btn ghost" name="test_ai" value="1">اختبار الاتصال بالمزوّد</button>
    </div>
  </form>
</div>

<div class="card">
  <b>كيف يعمل المساعد؟ (AI UI Generator)</b>
  <small class="hint" style="margin-top:8px">
    1) <b>Site Knowledge:</b> المساعد يعرف تلقائياً اسم الموقع وكل صفحاته وروابطه والبريد الرسمي وقناة تيليجرام ورابط التطبيق — من ملفات الموقع مباشرة.<br>
    2) <b>Context Engine:</b> مع كل سؤال تُرسل بيانات الصفحة المفتوحة حالياً (العنوان والوصف والرابط) وتُثرى من الخادم ببيانات المباراة/الفريق/الفيلم/الخبر الحقيقية خلف تلك الصفحة.<br>
    3) <b>البيانات أولاً:</b> أسئلة المباريات والأفلام والمسلسلات والأخبار والقنوات والتواصل تُبنى واجهاتها محلياً من APIs الموقع الحقيقية — فورية وبلا أي هلوسة.<br>
    4) <b>Gemini للأسئلة العامة:</b> يستلم Prompt مبني تلقائياً (معلومات الموقع + الصفحة الحالية + بيانات اليوم + السؤال) ويُلزم بالرد {"type":"html","html":"..."} بلا نص عادي ولا Markdown ولا نجوم.<br>
    5) <b>الأمان والاحتياط:</b> كل HTML من النموذج يمر بمنقٍّ صارم (لا سكربتات/أحداث/iframes)، وإذا فشل النموذج يُولَّد قالب HTML محلي بديل تلقائياً.
  </small>
</div>

<?php admin_bottom(); ?>
