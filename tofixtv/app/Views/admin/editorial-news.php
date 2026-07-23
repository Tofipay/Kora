<?php
use TofiXTv\Core\EditorialNews;

require __DIR__ . '/_shell.php';
admin_top('إدارة الأخبار', 'editorial-news');
$local = is_array($editLocal ?? null) ? $editLocal : [];
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<form class="card news-lookup-card" method="get" action="/<?= ADMIN_PATH ?>/editorial-news">
  <div class="editor-head"><div><b>البحث عن أي خبر</b><small>ابحث بواسطة رقم الخبر أو الصق رابط الخبر كاملاً، وسيتم فتحه للتعديل مباشرة.</small></div><?php if (!empty($lookupQuery)): ?><a class="btn ghost" href="/<?= ADMIN_PATH ?>/editorial-news">مسح البحث</a><?php endif; ?></div>
  <div class="news-lookup-row">
    <span class="news-lookup-icon"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span>
    <input type="text" name="find_news" dir="auto" inputmode="url" value="<?= e($lookupQuery ?? '') ?>" placeholder="14006412 أو https://test.aloka-code.shop/news/...-14006412" required>
    <button class="btn" type="submit">بحث</button>
  </div>
  <?php if (!empty($lookupError)): ?><p class="news-lookup-error"><?= e($lookupError) ?></p><?php elseif (!empty($lookupId)): ?><p class="news-lookup-success">تم استخراج رقم الخبر: <b>#<?= (int)$lookupId ?></b></p><?php endif; ?>
</form>

<form class="card" method="post" action="/<?= ADMIN_PATH ?>/editorial-news">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <b>اسم الكاتب الافتراضي</b>
  <div class="news-form-grid">
    <label>العربي<input type="text" name="default_author_ar" value="<?= e($settings['default_author_ar'] ?? 'فريق ALOKA Live') ?>"></label>
    <label>English<input type="text" name="default_author_en" dir="ltr" value="<?= e($settings['default_author_en'] ?? 'ALOKA Live Team') ?>"></label>
  </div>
  <button class="btn" type="submit" name="save_news_settings">حفظ الإعداد</button>
</form>

<form class="card" method="post" enctype="multipart/form-data" action="/<?= ADMIN_PATH ?>/editorial-news">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="id" value="<?= (int)($local['id'] ?? 0) ?>">
  <input type="hidden" name="image" value="<?= e($local['image'] ?? '') ?>">
  <div class="editor-head"><div><b><?= $local ? 'تعديل الخبر المحلي' : 'إضافة خبر جديد' ?></b><small>يُدمج مع الأخبار الحالية دون تغيير مصدر الـAPI</small></div><?php if ($local): ?><a class="btn ghost" href="/<?= ADMIN_PATH ?>/editorial-news">خبر جديد</a><?php endif; ?></div>
  <div class="news-form-grid">
    <label>العنوان العربي<input type="text" name="title_ar" value="<?= e($local['title_ar'] ?? '') ?>" required></label>
    <label>English title<input type="text" name="title_en" dir="ltr" value="<?= e($local['title_en'] ?? '') ?>"></label>
    <label>الوصف العربي<textarea name="description_ar" rows="3"><?= e($local['description_ar'] ?? '') ?></textarea></label>
    <label>English description<textarea name="description_en" dir="ltr" rows="3"><?= e($local['description_en'] ?? '') ?></textarea></label>
    <label class="wide">المحتوى العربي<textarea class="content-area" name="content_ar" rows="10"><?= e($local['content_ar'] ?? '') ?></textarea></label>
    <label class="wide">English content<textarea class="content-area" name="content_en" dir="ltr" rows="10"><?= e($local['content_en'] ?? '') ?></textarea></label>
    <label>الكاتب<input type="text" name="author_ar" value="<?= e($local['author_ar'] ?? '') ?>" placeholder="<?= e($settings['default_author_ar'] ?? '') ?>"></label>
    <label>English author<input type="text" name="author_en" dir="ltr" value="<?= e($local['author_en'] ?? '') ?>"></label>
    <label>التصنيف<input type="text" name="category_ar" value="<?= e($local['category_ar'] ?? '') ?>"></label>
    <label>English category<input type="text" name="category_en" dir="ltr" value="<?= e($local['category_en'] ?? '') ?>"></label>
    <label>رابط الصورة<input type="text" name="image_url_preview" value="<?= e($local['image'] ?? '') ?>" readonly></label>
    <label>رفع/تغيير الصورة<input type="file" name="news_image" accept="image/png,image/jpeg,image/webp,image/gif"></label>
    <label>وقت النشر<input type="datetime-local" name="published_at" value="<?= !empty($local['published_at']) ? e(date('Y-m-d\TH:i', strtotime((string)$local['published_at']))) : e(date('Y-m-d\TH:i')) ?>"></label>
    <label>الحالة<select name="status"><option value="published"<?= ($local['status']??'published')==='published'?' selected':'' ?>>منشور</option><option value="draft"<?= ($local['status']??'')==='draft'?' selected':'' ?>>مسودة</option><option value="hidden"<?= ($local['status']??'')==='hidden'?' selected':'' ?>>مخفي</option></select></label>
  </div>
  <button class="btn" type="submit" name="save_local_news">حفظ الخبر</button>
</form>

<?php if (!empty($editUpstream)): $base=$editUpstream['base']; $ov=$editUpstream['override']; ?>
<form class="card upstream-editor" method="post" enctype="multipart/form-data" action="/<?= ADMIN_PATH ?>/editorial-news">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="upstream_id" value="<?= (int)$base['id'] ?>"><input type="hidden" name="image" value="<?= e($ov['image'] ?? '') ?>">
  <div class="editor-head"><div><b>تعديل خبر API رقم <?= (int)$base['id'] ?></b><small>التعديل محلي، ولا يغيّر Endpoint أو الخبر في المصدر</small></div><a class="btn ghost" href="/<?= ADMIN_PATH ?>/editorial-news">إغلاق</a></div>
  <div class="news-form-grid">
    <label>العنوان العربي<input type="text" name="title_ar" value="<?= e($ov['title_ar'] ?? $base['title'] ?? '') ?>"></label>
    <label>English title<input type="text" name="title_en" dir="ltr" value="<?= e($ov['title_en'] ?? '') ?>"></label>
    <label>الوصف العربي<textarea name="description_ar"><?= e($ov['description_ar'] ?? $base['news_desc'] ?? '') ?></textarea></label>
    <label>English description<textarea name="description_en" dir="ltr"><?= e($ov['description_en'] ?? '') ?></textarea></label>
    <label class="wide">المحتوى العربي<textarea class="content-area" name="content_ar" rows="10"><?= e($ov['content_ar'] ?? $base['full_news'] ?? '') ?></textarea></label>
    <label class="wide">English content<textarea class="content-area" name="content_en" dir="ltr" rows="10"><?= e($ov['content_en'] ?? '') ?></textarea></label>
    <label>الكاتب<input type="text" name="author_ar" value="<?= e($ov['author_ar'] ?? news_author($base)) ?>"></label>
    <label>English author<input type="text" name="author_en" dir="ltr" value="<?= e($ov['author_en'] ?? '') ?>"></label>
    <label>التصنيف<input type="text" name="category_ar" value="<?= e($ov['category_ar'] ?? news_category($base)) ?>"></label>
    <label>English category<input type="text" name="category_en" dir="ltr" value="<?= e($ov['category_en'] ?? '') ?>"></label>
    <label>تغيير الصورة<input type="file" name="news_image" accept="image/png,image/jpeg,image/webp,image/gif"></label>
    <label>وقت النشر<input type="datetime-local" name="published_at" value="<?= e(date('Y-m-d\TH:i', to_ts($ov['published_at'] ?? $base['created_at'] ?? null) ?: time())) ?>"></label>
    <label>الحالة<select name="status"><option value="published"<?= empty($ov['hidden'])?' selected':'' ?>>منشور</option><option value="hidden"<?= !empty($ov['hidden'])?' selected':'' ?>>مخفي</option></select></label>
  </div>
  <button class="btn" type="submit" name="save_upstream_override">حفظ التعديلات</button>
</form>
<?php endif; ?>

<div class="card">
  <b>الأخبار المحلية</b>
  <div class="news-admin-list">
  <?php if (empty($items)): ?><p class="empty">لا توجد أخبار محلية بعد.</p><?php endif; ?>
  <?php foreach ($items as $row): ?>
    <div class="news-admin-item">
      <span class="news-admin-copy"><b><?= e($row['title_ar'] ?: $row['title_en']) ?></b><small><?= e($row['author_ar'] ?: ($settings['default_author_ar'] ?? '')) ?> · <?= e($row['status'] ?? 'draft') ?> · <?= e(date('Y-m-d H:i', strtotime((string)($row['published_at'] ?? 'now')))) ?></small></span>
      <span class="news-admin-actions"><a class="btn ghost" href="/<?= ADMIN_PATH ?>/editorial-news?edit=<?= (int)$row['id'] ?>">تعديل</a><form method="post" action="/<?= ADMIN_PATH ?>/editorial-news" onsubmit="return confirm('هل تريد حذف الخبر نهائياً؟')"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn danger" name="delete_local_news">حذف</button></form></span>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <b>آخر أخبار API</b>
  <p class="hint">يمكنك تعديلها محلياً أو إخفاءها. لن يتم حذفها أو تغيير طريقة جلبها من المصدر.</p>
  <div class="news-admin-list">
  <?php foreach ($upstream as $row): $hidden=!empty(EditorialNews::overrideFor((int)$row['id'])['hidden']); ?>
    <div class="news-admin-item<?= $hidden?' is-hidden':'' ?>">
      <span class="news-admin-copy"><b><?= e($row['title'] ?? '') ?></b><small>#<?= (int)$row['id'] ?> · <?= e(news_author($row)) ?><?= $hidden?' · مخفي':'' ?></small></span>
      <span class="news-admin-actions"><a class="btn ghost" href="/<?= ADMIN_PATH ?>/editorial-news?upstream=<?= (int)$row['id'] ?>">تعديل</a><form method="post" action="/<?= ADMIN_PATH ?>/editorial-news"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="upstream_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="hidden" value="<?= $hidden?'0':'1' ?>"><button class="btn <?= $hidden?'':'danger' ?>" name="set_upstream_visibility"><?= $hidden?'إظهار':'إخفاء' ?></button></form></span>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<style>
.news-lookup-card{border-color:rgba(124, 77, 255,.3);background:linear-gradient(135deg,rgba(124, 77, 255,.08),rgba(76, 14, 205,.05))}.news-lookup-row{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:8px}.news-lookup-row input{margin:0;min-height:48px}.news-lookup-row .btn{margin:0;min-height:48px;padding-inline:28px}.news-lookup-icon{height:48px;display:grid;place-items:center;border-radius:12px;color:var(--p2);background:#10033d;border:1px solid var(--line)}.news-lookup-error,.news-lookup-success{margin-top:10px;padding:8px 11px;border-radius:10px;font-size:12px;font-weight:700}.news-lookup-error{color:#fca5a5;background:rgba(239,68,68,.1)}.news-lookup-success{color:var(--p);background:rgba(76, 14, 205,.1)}.news-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px}.news-form-grid .wide{grid-column:1/-1}.news-form-grid label{margin:0}.news-form-grid input,.news-form-grid textarea,.news-form-grid select{margin-top:5px}.content-area{min-height:180px}.editor-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:15px}.editor-head small{display:block;color:var(--tx3)}.editor-head .btn{margin:0}.upstream-editor{border-color:rgba(124, 77, 255,.4);scroll-margin-top:20px}.news-admin-list{display:grid;gap:8px;margin-top:12px}.news-admin-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border-radius:12px;background:#10033d;border:1px solid var(--line)}.news-admin-item.is-hidden{opacity:.58}.news-admin-copy{min-width:0}.news-admin-copy b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.news-admin-copy small{display:block;color:var(--tx3);margin-top:2px}.news-admin-actions{display:flex;gap:7px;align-items:center;flex:none}.news-admin-actions form{margin:0}.news-admin-actions .btn{margin:0;padding:7px 13px}@media(max-width:700px){.news-form-grid{grid-template-columns:1fr}.news-form-grid .wide{grid-column:auto}.news-admin-item{align-items:flex-start;flex-direction:column}.news-admin-actions{width:100%}.news-lookup-row{grid-template-columns:40px minmax(0,1fr)}.news-lookup-row .btn{grid-column:1/-1;width:100%;justify-content:center}.editor-head{align-items:flex-start}}
</style>
<?php if (!empty($editUpstream) || !empty($editLocal)): ?><script>document.addEventListener('DOMContentLoaded',function(){var e=document.querySelector('.upstream-editor')||document.querySelector('input[name="id"][value="<?= (int)($local['id'] ?? 0) ?>"]');if(e)(e.closest('.card')||e).scrollIntoView({behavior:'smooth',block:'start'})});</script><?php endif; ?>
<?php admin_bottom(); ?>
