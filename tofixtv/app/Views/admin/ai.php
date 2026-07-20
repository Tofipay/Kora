<?php
/** Admin — AI Assistant control. Expects: $msg, $test, $s (saved), $cfg (effective), $csrf. */
require __DIR__ . '/_shell.php';
admin_top('المساعد الذكي', 'ai');
$enabled = $cfg['enabled'];
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>
<?php if (!empty($test)): ?><div class="msg"><?= e($test) ?></div><?php endif; ?>

<div class="card">
  <b>حالة المساعد</b>
  <small class="hint">عند الإيقاف تختفي أيقونة المساعد من الموقع بالكامل ويتوقف مسار /api/ai-chat فوراً — بدون أي تأثير على بقية الموقع.</small>
  <form method="post" action="/<?= ADMIN_PATH ?>/ai">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label style="display:inline-flex;align-items:center;gap:10px;margin-top:14px;font-size:14px;color:var(--tx)">
      <input type="checkbox" name="enabled" <?= $enabled ? 'checked' : '' ?>>
      تفعيل المساعد الذكي (إظهاره للزوار)
    </label>

    <label>رابط المزوّد (Base URL)</label>
    <input type="url" name="base_url" dir="ltr" value="<?= e((string)($s['base_url'] ?? '')) ?>" placeholder="<?= e($cfg['base_url']) ?>">
    <small class="hint">اتركه فارغاً لاستخدام الافتراضي: <code dir="ltr"><?= e($cfg['base_url']) ?></code></small>

    <label>مفتاح API</label>
    <input type="password" name="api_key" dir="ltr" value="<?= e((string)($s['api_key'] ?? '')) ?>" placeholder="اتركه فارغاً لاستخدام المفتاح الافتراضي المدمج">
    <small class="hint">المفتاح لا يظهر أبداً في صفحات الزوار — يُستخدم من الخادم فقط.</small>

    <label>الموديل</label>
    <input type="text" name="model" dir="ltr" value="<?= e((string)($s['model'] ?? '')) ?>" placeholder="<?= e($cfg['model']) ?>">

    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn" name="save_ai" value="1">حفظ الإعدادات</button>
      <button class="btn ghost" name="test_ai" value="1">اختبار الاتصال بالمزوّد</button>
    </div>
  </form>
</div>

<div class="card">
  <b>كيف يعمل المساعد؟</b>
  <small class="hint" style="margin-top:8px">
    1) كل سؤال يُبحث أولاً في بيانات الموقع نفسها (المباريات، الفرق، اللاعبون، الأفلام، المسلسلات، الأخبار، القنوات) وتُبنى البطاقات التفاعلية من هذه البيانات الحقيقية مع روابط صفحات الموقع — فلا مجال للهلوسة في النتائج والمواعيد.<br>
    2) الأسئلة العامة فقط تذهب لنموذج الذكاء الاصطناعي مع تعليمات صارمة: الإجابة بإيجاز، عدم تخمين أي نتيجة أو موعد، والتصريح بعدم التأكد عند غياب المعلومة.<br>
    3) الحماية: تحقق من المصدر (same-origin)، حد 14 رسالة بالدقيقة لكل زائر، تنقية جميع المدخلات، وعرض الردود نصياً فقط في المتصفح (لا HTML).
  </small>
</div>

<?php admin_bottom(); ?>
