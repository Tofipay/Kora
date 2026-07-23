<?php
require __DIR__ . '/_shell.php';
admin_top('قوائم القنوات', 'channel-catalog');
$groups = is_array($groups ?? null) ? $groups : [];
$counts = is_array($counts ?? null) ? $counts : [];
$baseUrl = '/' . ADMIN_PATH . '/channel-catalog/category/' . (int)$category['id'];
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<a class="catalog-back" href="/<?= ADMIN_PATH ?>/channel-catalog">← الأقسام</a>
<div class="catalog-toolbar">
  <div><span class="catalog-parent-label"><?= e($category['name_ar'] ?: $category['name_en']) ?></span><b>قوائم القنوات</b><small>مثال: beIN SPORTS أو SSC أو STC TV. اضغط مطولاً للتعديل أو الحذف.</small></div>
  <button class="btn catalog-add" type="button" data-catalog-add>+ إضافة قائمة</button>
</div>

<?php if (!$groups): ?>
  <div class="card catalog-empty"><b>لا توجد قوائم داخل هذا القسم</b><p>أضف قائمة مثل beIN SPORTS ثم افتحها لإضافة القنوات.</p><button class="btn" type="button" data-catalog-add>إضافة أول قائمة</button></div>
<?php else: ?>
  <div class="catalog-admin-grid group-admin-grid">
  <?php foreach ($groups as $row):
    $id = (int)$row['id'];
    $edit = json_encode(['id'=>$id,'name_ar'=>$row['name_ar']??'','name_en'=>$row['name_en']??'','image'=>$row['image']??'','order'=>(int)($row['order']??0)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>
    <a class="catalog-admin-card media-admin-card<?= empty($row['visible'])?' is-hidden':'' ?>" href="/<?= ADMIN_PATH ?>/channel-catalog/group/<?= $id ?>" data-catalog-card data-id="<?= $id ?>" data-edit="<?= e($edit ?: '{}') ?>">
      <span class="catalog-card-image"><img src="<?= e(catalog_image($row['image'] ?? null)) ?>" alt="" width="96" height="64"></span>
      <span class="catalog-card-copy"><b><?= e($row['name_ar'] ?: $row['name_en']) ?></b><?php if (!empty($row['name_en']) && $row['name_en'] !== $row['name_ar']): ?><small dir="ltr"><?= e($row['name_en']) ?></small><?php endif; ?><em><?= (int)($counts[$id] ?? 0) ?> قناة · ترتيب <?= (int)($row['order'] ?? 0) ?></em></span>
      <svg class="catalog-card-arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 6-6 6 6 6"/></svg>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="catalog-dialog" id="catalog-entry-dialog" hidden aria-hidden="true">
  <button class="catalog-dialog-backdrop" type="button" data-catalog-close aria-label="إغلاق"></button>
  <section class="catalog-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="catalog-entry-title" tabindex="-1">
    <div class="catalog-dialog-head"><div><b id="catalog-entry-title">إضافة قائمة</b><small>الصورة والاسم والترتيب فقط.</small></div><button type="button" class="catalog-dialog-x" data-catalog-close>×</button></div>
    <form id="catalog-entry-form" method="post" enctype="multipart/form-data" action="<?= e($baseUrl) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="" data-field="id"><input type="hidden" name="existing_image" value="" data-field="image">
      <div class="catalog-image-preview"><img src="/assets/img/channel.svg" alt="" data-catalog-preview><span>صورة القائمة</span></div>
      <label>الاسم بالعربي<input type="text" name="name_ar" data-field="name_ar" placeholder="بي إن سبورت"></label>
      <label>الاسم بالإنجليزي<input type="text" name="name_en" data-field="name_en" dir="ltr" placeholder="beIN SPORTS"></label>
      <label>رفع الصورة<input type="file" name="image_upload" accept="image/png,image/jpeg,image/webp,image/gif" data-image-input></label>
      <label>الترتيب<input type="number" min="0" name="order" data-field="order" value="0"></label>
      <button class="btn catalog-save" type="submit" name="save_group">حفظ القائمة</button>
    </form>
  </section>
</div>

<?php
$catalogDeleteField = 'delete_group';
$catalogDeleteText = 'حذف القائمة سيحذف جميع القنوات الموجودة داخلها. هل أنت متأكد؟';
$catalogAddTitle = 'إضافة قائمة';
$catalogEditTitle = 'تعديل القائمة';
require __DIR__ . '/_catalog-interactions.php';
admin_bottom();
?>
