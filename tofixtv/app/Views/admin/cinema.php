<?php
/**
 * Admin — Movies / Series manager.
 * Expects: $type ('movie'|'tv'), $msg, $managed, $catalog, $q, $filter, $block18, $csrf.
 */
require APP_DIR . '/Views/admin/_shell.php';
$isTv  = $type === 'tv';
$title = $isTv ? 'إدارة المسلسلات' : 'إدارة الأفلام';
$self  = '/' . ADMIN_PATH . '/cinema/' . ($isTv ? 'series' : 'movies');
admin_top($title, $isTv ? 'cinema-series' : 'cinema-movies');

$accessLabel = ['all' => 'الموقع + التطبيق', 'app' => 'التطبيق فقط', 'off' => 'معطّل'];
$ratingLabel = ['g' => 'عام', '13' => '+13', '16' => '+16', '18' => '+18'];
$accessChip = function (string $a): string {
    return match ($a) {
        'off' => '<span class="cchip c-off">معطّل</span>',
        'app' => '<span class="cchip c-app">التطبيق فقط</span>',
        default => '<span class="cchip c-on">مفعّل</span>',
    };
};
$posterUrl = fn(string $p): string => $p !== '' ? 'https://image.tmdb.org/t/p/w92' . $p : '';
?>
<style>
.cine-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.cine-toolbar form{display:flex;gap:8px;flex:1;min-width:220px}
.cine-toolbar input[type=text]{flex:1}
.cine-toolbar .btn{margin-top:0;padding:9px 18px}
.fchips{display:flex;gap:6px;flex-wrap:wrap}
.fchips a{padding:8px 14px;border-radius:999px;border:1px solid var(--line);color:var(--tx2);font-weight:700;font-size:12.5px}
.fchips a.on{background:linear-gradient(135deg,rgba(22,199,132,.18),rgba(34,211,238,.10));color:var(--p);border-color:rgba(22,199,132,.4)}
.cine-row{display:flex;align-items:center;gap:12px;padding:10px 4px;border-bottom:1px dashed var(--line);flex-wrap:wrap}
.cine-row:last-child{border-bottom:0}
.cine-row img{width:40px;height:60px;object-fit:cover;border-radius:8px;background:#0b1424;flex:none}
.cine-row .noimg{width:40px;height:60px;border-radius:8px;background:#0b1424;flex:none;display:grid;place-items:center;color:var(--tx3);font-size:10px}
.cine-row .ct{flex:1;min-width:150px}
.cine-row .ct b{display:block;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:340px}
.cine-row .ct small{color:var(--tx3);font-weight:700}
.cine-row form{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin:0}
.cine-row select{width:auto;min-width:118px;padding:8px 10px;font-size:12.5px}
.cine-row .btn{margin-top:0;padding:8px 16px;font-size:12.5px}
.cine-row .btn.ghost{padding:8px 12px}
.cchip{font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px;white-space:nowrap}
.c-on{background:rgba(22,199,132,.15);color:var(--p)}
.c-app{background:rgba(34,211,238,.15);color:var(--p2)}
.c-off{background:rgba(239,68,68,.15);color:#f87171}
.rchip{font-size:11px;font-weight:800;padding:4px 9px;border-radius:7px;background:#0b1424;color:var(--tx2)}
.rchip.r-18{background:rgba(239,68,68,.18);color:#f87171}
.rchip.r-16{background:rgba(245,158,11,.18);color:#fbbf24}
.bulkbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#0b1424;border:1px solid var(--line);border-radius:12px;padding:10px 14px;margin-bottom:12px}
.bulkbar select{width:auto;padding:8px 10px}
.bulkbar .btn{margin-top:0;padding:8px 18px;font-size:12.5px}
.b18{display:flex;gap:18px;flex-wrap:wrap;align-items:center}
.b18 label{display:inline-flex;align-items:center;gap:8px;margin:0;font-size:13px;color:var(--tx)}
@media(max-width:640px){
  .cine-row .ct b{max-width:170px}
  .cine-row form{width:100%;justify-content:flex-start}
}
</style>

<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <b>وضع عرض القسم بالكامل — <?= $isTv ? 'المسلسلات' : 'الأفلام' ?></b>
  <small class="hint">«التطبيق فقط» يبقي كل الصفحات والوصف والبيانات المهيكلة كما هي للأرشفة، لكنه يخفي مشغّل الفيديو على الموقع ويعرض شاشة قفل مع رابط تحميل التطبيق. المشغّل يعمل طبيعياً داخل التطبيق (User-Agent: com.tofixtv.app).</small>
  <form method="post" action="<?= e($self) ?>" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <select name="section_mode" style="width:auto;min-width:200px">
      <option value="all" <?= ($mode ?? 'all') === 'all' ? 'selected' : '' ?>>الموقع + التطبيق</option>
      <option value="app" <?= ($mode ?? '') === 'app' ? 'selected' : '' ?>>التطبيق فقط</option>
      <option value="off" <?= ($mode ?? '') === 'off' ? 'selected' : '' ?>>معطّل</option>
    </select>
    <button class="btn" name="save_mode" value="1" style="margin-top:0">حفظ الوضع</button>
    <?= $accessChip($mode ?? 'all') ?>
  </form>
</div>

<div class="card">
  <b>إعدادات المحتوى +18</b>
  <small class="hint">تحكّم كامل بعرض المحتوى المصنّف +18 على مستوى المنصة (يشمل الأفلام والمسلسلات معاً).</small>
  <form method="post" action="<?= e($self) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="b18" style="margin-top:12px">
      <label><input type="checkbox" name="b18_global" <?= $block18['global'] ? 'checked' : '' ?>> حظر شامل (الموقع والتطبيق)</label>
      <label><input type="checkbox" name="b18_web" <?= $block18['web'] ? 'checked' : '' ?>> حظر على الموقع فقط</label>
      <label><input type="checkbox" name="b18_app" <?= $block18['app'] ? 'checked' : '' ?>> حظر على التطبيق فقط</label>
    </div>
    <button class="btn" name="save_block18" value="1">حفظ إعدادات +18</button>
  </form>
</div>

<div class="cine-toolbar">
  <form method="get" action="<?= e($self) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= $isTv ? 'ابحث عن مسلسل لإدارته…' : 'ابحث عن فيلم لإدارته…' ?>">
    <button class="btn" type="submit">بحث</button>
  </form>
  <div class="fchips">
    <a class="<?= $filter === '' ? 'on' : '' ?>" href="<?= e($self) ?>">الكل</a>
    <a class="<?= $filter === 'off' ? 'on' : '' ?>" href="<?= e($self) ?>?filter=off">المعطّل</a>
    <a class="<?= $filter === 'app' ? 'on' : '' ?>" href="<?= e($self) ?>?filter=app">التطبيق فقط</a>
    <a class="<?= $filter === '18' ? 'on' : '' ?>" href="<?= e($self) ?>?filter=18">+18</a>
  </div>
</div>

<div class="card">
  <b>العناصر المُدارة (<?= count($managed) ?>)</b>
  <small class="hint">كل عنصر عدّلت إعداداته يظهر هنا. «إعادة للافتراضي» يعيده مفعّلاً بتصنيف عام.</small>
  <?php if (!$managed): ?>
    <div class="empty">لا توجد عناصر مُدارة بعد — ابحث في الكتالوج بالأسفل وعدّل أي عنصر.</div>
  <?php else: ?>
  <form method="post" action="<?= e($self) ?>" id="bulk-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="bulkbar">
      <label style="margin:0;display:inline-flex;align-items:center;gap:7px;font-size:12.5px">
        <input type="checkbox" onclick="document.querySelectorAll('#bulk-form input[name=\'keys[]\']').forEach(c=>c.checked=this.checked)"> تحديد الكل
      </label>
      <select name="bulk_action">
        <option value="all">تفعيل (الموقع + التطبيق)</option>
        <option value="app">التطبيق فقط</option>
        <option value="off">تعطيل</option>
      </select>
      <button class="btn" name="bulk_apply" value="1">تطبيق على المحدد</button>
    </div>
    <?php foreach ($managed as $r): ?>
    <div class="cine-row">
      <input type="checkbox" name="keys[]" value="<?= e($r['key']) ?>">
      <?php if ($r['poster']): ?><img src="<?= e($posterUrl($r['poster'])) ?>" alt="" loading="lazy"><?php else: ?><span class="noimg">—</span><?php endif; ?>
      <span class="ct">
        <b><?= e($r['title'] !== '' ? $r['title'] : ('#' . $r['id'])) ?></b>
        <small><?= e($r['year']) ?> · ID <?= (int)$r['id'] ?></small>
      </span>
      <?= $accessChip($r['access']) ?>
      <span class="rchip r-<?= e($r['rating']) ?>"><?= e($ratingLabel[$r['rating']] ?? 'عام') ?></span>
    </div>
    <?php endforeach; ?>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <b><?= $q !== '' ? 'نتائج البحث' : ($isTv ? 'المسلسلات الرائجة' : 'الأفلام الرائجة') ?></b>
  <small class="hint">عدّل حالة العرض والتصنيف العمري لأي عنصر ثم اضغط حفظ.</small>
  <?php if (!$catalog): ?>
    <div class="empty">لا توجد نتائج<?= $q !== '' ? ' لبحثك «' . e($q) . '»' : '' ?>.</div>
  <?php endif; ?>
  <?php foreach ($catalog as $r): ?>
  <div class="cine-row">
    <?php if ($r['poster']): ?><img src="<?= e($posterUrl($r['poster'])) ?>" alt="" loading="lazy"><?php else: ?><span class="noimg">—</span><?php endif; ?>
    <span class="ct">
      <b><?= e($r['title']) ?></b>
      <small><?= e($r['year']) ?> · ID <?= (int)$r['id'] ?></small>
    </span>
    <?= $accessChip($r['access']) ?>
    <form method="post" action="<?= e($self) ?><?= $q !== '' ? '?q=' . rawurlencode($q) : '' ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="item_id" value="<?= (int)$r['id'] ?>">
      <input type="hidden" name="item_title" value="<?= e($r['title']) ?>">
      <input type="hidden" name="item_poster" value="<?= e($r['poster']) ?>">
      <input type="hidden" name="item_year" value="<?= e($r['year']) ?>">
      <select name="access">
        <?php foreach ($accessLabel as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $r['access'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="rating">
        <?php foreach ($ratingLabel as $k => $lbl): ?>
        <option value="<?= e($k) ?>" <?= $r['rating'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" name="save_item" value="1">حفظ</button>
      <button class="btn ghost" name="remove_key" value="<?= e($r['key']) ?>" title="إعادة للوضع الافتراضي">افتراضي</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>

<?php admin_bottom(); ?>
