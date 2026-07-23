<?php
require __DIR__ . '/_shell.php';
admin_top('أقسام القنوات', 'channel-catalog');
$categories = is_array($categories ?? null) ? $categories : [];
$counts = is_array($counts ?? null) ? $counts : [];
$slides = is_array($slides ?? null) ? $slides : [];
$allGroups = is_array($allGroups ?? null) ? $allGroups : [];
$categoryNames = [];
foreach ($categories as $categoryRow) {
  $categoryNames[(int)($categoryRow['id'] ?? 0)] = (string)($categoryRow['name_ar'] ?: $categoryRow['name_en']);
}
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="catalog-toolbar channel-slider-toolbar">
  <div><b>سلايدر صفحة القنوات</b><small>أضف إعلاناً أو صورة فقط أو صورة تفتح قائمة قنوات.</small></div>
  <button class="btn catalog-add" type="button" data-slide-add>+ إضافة سلايد</button>
</div>

<?php if (!$slides): ?>
  <div class="card channel-slider-empty"><b>لم تتم إضافة صور للسلايدر</b><p>لن يظهر أي نص أو فراغ كبير أعلى صفحة القنوات حتى تضيف أول صورة.</p></div>
<?php else: ?>
  <div class="channel-slider-admin-grid">
  <?php foreach ($slides as $slide):
    $slideId = (int)($slide['id'] ?? 0);
    $slideData = json_encode([
      'id' => $slideId,
      'image' => $slide['image'] ?? '',
      'title_ar' => $slide['title_ar'] ?? '',
      'title_en' => $slide['title_en'] ?? '',
      'description_ar' => $slide['description_ar'] ?? '',
      'description_en' => $slide['description_en'] ?? '',
      'button_ar' => $slide['button_ar'] ?? '',
      'button_en' => $slide['button_en'] ?? '',
      'target_type' => $slide['target_type'] ?? 'none',
      'group_id' => (int)($slide['group_id'] ?? 0),
      'target_url' => $slide['target_url'] ?? '',
      'order' => (int)($slide['order'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $slideLabel = trim((string)($slide['title_ar'] ?? '')) ?: (trim((string)($slide['title_en'] ?? '')) ?: ('سلايد ' . $slideId));
  ?>
    <article class="channel-slider-admin-card">
      <img src="<?= e(catalog_image($slide['image'] ?? null)) ?>" alt="<?= e($slideLabel) ?>" width="480" height="210">
      <div><b><?= e($slideLabel) ?></b><small>ترتيب <?= (int)($slide['order'] ?? 0) ?></small></div>
      <div class="channel-slider-admin-actions">
        <button type="button" data-slide-edit data-slide="<?= e($slideData ?: '{}') ?>">تعديل</button>
        <form method="post" action="/<?= ADMIN_PATH ?>/channel-catalog" onsubmit="return confirm('هل تريد حذف هذا السلايد؟')">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $slideId ?>">
          <button type="submit" name="delete_slide">حذف</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="catalog-dialog" id="channel-slide-dialog" hidden aria-hidden="true">
  <button class="catalog-dialog-backdrop" type="button" data-slide-close aria-label="إغلاق"></button>
  <section class="catalog-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="channel-slide-title" tabindex="-1">
    <div class="catalog-dialog-head"><div><b id="channel-slide-title">إضافة سلايد</b><small>يفضل رفع صورة أفقية بنسبة 16:7.</small></div><button type="button" class="catalog-dialog-x" data-slide-close>×</button></div>
    <form id="channel-slide-form" method="post" enctype="multipart/form-data" action="/<?= ADMIN_PATH ?>/channel-catalog">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="" data-slide-field="id"><input type="hidden" name="existing_slide_image" value="" data-slide-field="image">
      <div class="catalog-image-preview channel-slide-preview"><img src="/assets/img/channel.svg" alt="" data-slide-preview><span>صورة السلايد</span></div>
      <label>رفع الصورة<input type="file" name="slide_image_upload" accept="image/png,image/jpeg,image/webp,image/gif" data-slide-image></label>
      <label>العنوان بالعربي <small>(اختياري — يظهر داخل الصورة)</small><input type="text" name="title_ar" data-slide-field="title_ar" placeholder="مثال: وجهتك الأولى للمشاهدة"></label>
      <label>العنوان بالإنجليزي <small>(اختياري)</small><input type="text" name="title_en" dir="ltr" data-slide-field="title_en" placeholder="Your first destination"></label>
      <label>الوصف بالعربي <small>(اختياري — يظهر أسفل العنوان)</small><textarea name="description_ar" rows="2" data-slide-field="description_ar" placeholder="اكتب وصفاً قصيراً للسلايد"></textarea></label>
      <label>الوصف بالإنجليزي <small>(اختياري)</small><textarea name="description_en" rows="2" dir="ltr" data-slide-field="description_en" placeholder="Short slide description"></textarea></label>
      <label>اسم الزر بالعربي <small>(اختياري — مثل شاهد الآن أو استكشف)</small><input type="text" name="button_ar" data-slide-field="button_ar" placeholder="استكشف الآن"></label>
      <label>اسم الزر بالإنجليزي <small>(اختياري)</small><input type="text" name="button_en" dir="ltr" data-slide-field="button_en" placeholder="Explore now"></label>
      <label>عند الضغط على السلايد
        <select name="target_type" data-slide-field="target_type" data-slide-target>
          <option value="none">صورة فقط — بدون رابط</option>
          <option value="group">فتح قائمة قنوات</option>
          <option value="url">فتح رابط إعلان</option>
        </select>
      </label>
      <label data-slide-group-wrap>قائمة القنوات
        <select name="group_id" data-slide-field="group_id">
          <option value="0">اختر القائمة</option>
          <?php foreach ($allGroups as $groupRow): ?>
            <option value="<?= (int)$groupRow['id'] ?>"><?= e(($categoryNames[(int)($groupRow['category_id'] ?? 0)] ?? '') . ' — ' . ($groupRow['name_ar'] ?: $groupRow['name_en'])) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label data-slide-url-wrap>رابط الإعلان<input type="url" name="target_url" dir="ltr" data-slide-field="target_url" placeholder="https://example.com"></label>
      <label>الترتيب<input type="number" min="0" name="order" data-slide-field="order" value="0"></label>
      <button class="btn catalog-save" type="submit" name="save_slide">حفظ السلايد</button>
    </form>
  </section>
</div>

<div class="catalog-toolbar">
  <div><b>أقسام القنوات</b><small>اضغط على القسم لفتح قوائمه، واضغط مطولاً للتعديل أو الحذف.</small></div>
  <button class="btn catalog-add" type="button" data-catalog-add>+ إضافة قسم</button>
</div>

<style>
.channel-slider-toolbar{margin-top:0}.channel-slider-empty{padding:22px;text-align:center}.channel-slider-empty p{margin:4px 0 0;color:var(--tx2)}.channel-slider-admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px;margin-bottom:24px}.channel-slider-admin-card{overflow:hidden;border:1px solid var(--line);border-radius:16px;background:rgba(27, 7, 97,.82)}.channel-slider-admin-card>img{display:block;width:100%;aspect-ratio:16/7;object-fit:cover}.channel-slider-admin-card>div:not(.channel-slider-admin-actions){padding:10px 12px 4px}.channel-slider-admin-card b,.channel-slider-admin-card small{display:block}.channel-slider-admin-card small{color:var(--tx2)}.channel-slider-admin-actions{display:flex;gap:8px;padding:8px 12px 12px}.channel-slider-admin-actions form{margin:0}.channel-slider-admin-actions button{min-height:38px;padding:7px 14px;border:1px solid var(--line);border-radius:10px;color:var(--tx);background:rgba(255,255,255,.04);font:inherit;font-weight:800;cursor:pointer}.channel-slider-admin-actions form button{color:#fca5a5;background:rgba(239,68,68,.1)}.channel-slide-preview img{width:120px;aspect-ratio:16/7;height:auto;object-fit:cover}
@media(max-width:620px){.channel-slider-admin-grid{grid-template-columns:1fr}.channel-slider-admin-actions button{min-height:44px}}
</style>

<script>
(function(){
  var dialog=document.getElementById('channel-slide-dialog'),form=document.getElementById('channel-slide-form');
  if(!dialog||!form)return;
  var title=document.getElementById('channel-slide-title'),target=form.querySelector('[data-slide-target]'),groupWrap=form.querySelector('[data-slide-group-wrap]'),urlWrap=form.querySelector('[data-slide-url-wrap]'),preview=form.querySelector('[data-slide-preview]');
  function toggleTarget(){var value=target.value;groupWrap.hidden=value!=='group';urlWrap.hidden=value!=='url'}
  function close(){dialog.hidden=true;dialog.setAttribute('aria-hidden','true');document.body.style.overflow=''}
  function open(data){form.reset();data=data||{};form.querySelectorAll('[data-slide-field]').forEach(function(field){field.value=data[field.getAttribute('data-slide-field')]??''});if(!target.value)target.value='none';preview.src=data.image||'/assets/img/channel.svg';title.textContent=data.id?'تعديل السلايد':'إضافة سلايد';toggleTarget();dialog.hidden=false;dialog.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';setTimeout(function(){dialog.querySelector('.catalog-dialog-panel').focus()},50)}
  document.querySelectorAll('[data-slide-add]').forEach(function(button){button.addEventListener('click',function(){open({target_type:'none',order:0})})});
  document.querySelectorAll('[data-slide-edit]').forEach(function(button){button.addEventListener('click',function(){try{open(JSON.parse(button.getAttribute('data-slide')||'{}'))}catch(e){open({})}})});
  document.querySelectorAll('[data-slide-close]').forEach(function(button){button.addEventListener('click',close)});
  target.addEventListener('change',toggleTarget);
  var imageInput=form.querySelector('[data-slide-image]');imageInput.addEventListener('change',function(){var file=imageInput.files&&imageInput.files[0];if(file)preview.src=URL.createObjectURL(file)});
  document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!dialog.hidden)close()});
})();
</script>

<?php if (!$categories): ?>
  <div class="card catalog-empty"><b>لا توجد أقسام بعد</b><p>ابدأ بإضافة القنوات الرياضية أو قنوات التلفاز.</p><button class="btn" type="button" data-catalog-add>إضافة أول قسم</button></div>
<?php else: ?>
  <div class="catalog-admin-grid categories-admin-grid">
  <?php foreach ($categories as $row):
    $id = (int)$row['id'];
    $edit = json_encode(['id'=>$id,'name_ar'=>$row['name_ar']??'','name_en'=>$row['name_en']??''], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>
    <a class="catalog-admin-card category-admin-card<?= empty($row['visible'])?' is-hidden':'' ?>" href="/<?= ADMIN_PATH ?>/channel-catalog/category/<?= $id ?>" data-catalog-card data-id="<?= $id ?>" data-edit="<?= e($edit ?: '{}') ?>">
      <span class="catalog-card-icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4zM8 5v14M16 5v14"/></svg></span>
      <span class="catalog-card-copy"><b><?= e($row['name_ar'] ?: $row['name_en']) ?></b><?php if (!empty($row['name_en']) && $row['name_en'] !== $row['name_ar']): ?><small dir="ltr"><?= e($row['name_en']) ?></small><?php endif; ?><em><?= (int)($counts[$id] ?? 0) ?> قائمة</em></span>
      <svg class="catalog-card-arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 6-6 6 6 6"/></svg>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="catalog-dialog" id="catalog-entry-dialog" hidden aria-hidden="true">
  <button class="catalog-dialog-backdrop" type="button" data-catalog-close aria-label="إغلاق"></button>
  <section class="catalog-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="catalog-entry-title" tabindex="-1">
    <div class="catalog-dialog-head"><div><b id="catalog-entry-title">إضافة قسم</b><small>اسم القسم فقط، بدون صورة.</small></div><button type="button" class="catalog-dialog-x" data-catalog-close>×</button></div>
    <form id="catalog-entry-form" method="post" action="/<?= ADMIN_PATH ?>/channel-catalog">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="" data-field="id">
      <label>اسم القسم بالعربي<input type="text" name="name_ar" data-field="name_ar" placeholder="القنوات الرياضية"></label>
      <label>اسم القسم بالإنجليزي<input type="text" name="name_en" data-field="name_en" dir="ltr" placeholder="Sports Channels"></label>
      <button class="btn catalog-save" type="submit" name="save_category">حفظ القسم</button>
    </form>
  </section>
</div>

<?php
$catalogDeleteField = 'delete_category';
$catalogDeleteText = 'حذف القسم سيحذف جميع القوائم والقنوات الموجودة داخله. هل أنت متأكد؟';
$catalogAddTitle = 'إضافة قسم';
$catalogEditTitle = 'تعديل القسم';
require __DIR__ . '/_catalog-interactions.php';
admin_bottom();
?>
