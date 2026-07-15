<?php require __DIR__ . '/_shell.php'; admin_top('التطبيق — مكتبة القنوات', 'app'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="app-tabs">
  <a class="app-tab" href="/<?= ADMIN_PATH ?>/app/links">روابط مباريات التطبيق</a>
  <a class="app-tab on" href="/<?= ADMIN_PATH ?>/app/channels">مكتبة القنوات</a>
</div>

<div class="card">
  <b>مكتبة قنوات التطبيق</b>
  <p class="hint" style="margin:6px 0 0">
    قسم مستقل خاص بتطبيق Android فقط — لا يؤثر على مكتبة قنوات الموقع.
    لكل قناة: <b>اسم القناة</b> و<b>روابط المشاهدة</b> (يمكن إضافة أكثر من رابط مفصول بفاصلة أو بسطر جديد).<br>
    مثال: <code dir="ltr">http://ver3.yacinelive.com/api/channel/1473?tofi-api&amp;tofiUrlname=beIN Max 1-HD,
    http://ver3.yacinelive.com/api/channel/1472?tofi-api&amp;tofiUrlname=beIN Max 1-SD</code><br>
    إذا كانت المباراة تحتوي على قناة (مثل <code>beIN Max 1</code>) يبحث النظام تلقائياً هنا،
    وعند التطابق تُستخدم روابط القناة في زر التطبيق الأزرق — حتى لو لم يُضف رابط مباشر للمباراة.
  </p>
</div>

<form method="post" action="/<?= ADMIN_PATH ?>/app/channels">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div id="ac-rows">
    <?php
    $rows = $items ?: [['name' => '', 'urls' => []]];
    foreach ($rows as $c):
        $name = (string)($c['name'] ?? '');
        $urls = implode(",\n", (array)($c['urls'] ?? []));
    ?>
    <div class="card ac-row">
      <div class="ac-grid">
        <label>اسم القناة
          <input type="text" name="ac_name[]" value="<?= e($name) ?>" placeholder="beIN Max 1">
        </label>
        <label>روابط المشاهدة (مفصولة بفاصلة أو سطر جديد)
          <textarea name="ac_urls[]" rows="3" dir="ltr" placeholder="http://ver3.yacinelive.com/api/channel/1473?tofi-api&tofiUrlname=beIN Max 1-HD,&#10;http://ver3.yacinelive.com/api/channel/1472?tofi-api&tofiUrlname=beIN Max 1-SD"><?= e($urls) ?></textarea>
        </label>
      </div>
      <button type="button" class="btn btn-del" onclick="this.closest('.ac-row').remove()">حذف القناة</button>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:8px;margin-top:12px">
    <button type="button" class="btn ghost" id="ac-add">+ إضافة قناة</button>
    <button type="submit" class="btn">حفظ الكل</button>
  </div>
</form>

<template id="ac-tpl">
  <div class="card ac-row">
    <div class="ac-grid">
      <label>اسم القناة
        <input type="text" name="ac_name[]" value="" placeholder="beIN Max 1">
      </label>
      <label>روابط المشاهدة (مفصولة بفاصلة أو سطر جديد)
        <textarea name="ac_urls[]" rows="3" dir="ltr" placeholder="http://ver3.yacinelive.com/api/channel/1473?tofi-api&tofiUrlname=beIN Max 1-HD,&#10;http://ver3.yacinelive.com/api/channel/1472?tofi-api&tofiUrlname=beIN Max 1-SD"></textarea>
      </label>
    </div>
    <button type="button" class="btn btn-del" onclick="this.closest('.ac-row').remove()">حذف القناة</button>
  </div>
</template>

<style>
.app-tabs{display:flex;gap:8px;margin-bottom:16px}
.app-tab{padding:9px 18px;border-radius:999px;border:1.5px solid var(--line);font-weight:800;color:var(--tx2)}
.app-tab.on{background:linear-gradient(135deg,rgba(37,99,235,.25),rgba(59,130,246,.12));border-color:rgba(59,130,246,.5);color:#93c5fd}
.ac-grid{display:grid;grid-template-columns:1fr 2fr;gap:12px}
.ac-grid label{display:flex;flex-direction:column;gap:5px;font-weight:600;font-size:13px;margin:0}
.ac-grid input,.ac-grid textarea{width:100%;font-family:inherit}
.ac-row{position:relative}
.btn-del{margin-top:10px;background:#ef4444;color:#fff}
@media(max-width:640px){.ac-grid{grid-template-columns:1fr}}
</style>
<script>
document.getElementById('ac-add').addEventListener('click', function () {
  const tpl = document.getElementById('ac-tpl').content.cloneNode(true);
  document.getElementById('ac-rows').appendChild(tpl);
});
</script>
<?php admin_bottom(); ?>
