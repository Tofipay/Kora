<?php
require __DIR__ . '/_shell.php';
admin_top('لوحة التحكم', 'dashboard');

/* Small local formatters */
$fmt   = fn($n) => number_format((int)$n);
$mb    = fn($b) => $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : round($b / 1048576, 1) . ' MB';
$pct   = fn($u, $t) => $t > 0 ? round($u / $t * 100) : 0;
$arName = [
  // sources
  'google'=>'جوجل والبحث','facebook'=>'فيسبوك وإنستغرام','telegram'=>'تيليجرام','twitter'=>'X (تويتر)',
  'direct'=>'مباشر','referral'=>'مواقع أخرى',
  // devices
  'android'=>'أندرويد','iphone'=>'آيفون / آيباد','windows'=>'ويندوز','mac'=>'ماك','linux'=>'لينكس',
  'smarttv'=>'شاشات ذكية','other'=>'أخرى',
  // browsers
  'chrome'=>'كروم','safari'=>'سفاري','edge'=>'إيدج','firefox'=>'فايرفوكس','samsung'=>'سامسونج','opera'=>'أوبرا',
];
$L = fn($k) => $arName[$k] ?? $k;

/** Horizontal metric-bar block from a [key=>count] map. */
$metric = function (array $data) use ($L) {
    if (!$data) { echo '<p class="empty">لا توجد بيانات بعد</p>'; return; }
    arsort($data);
    $data = array_slice($data, 0, 6, true);
    $max = max($data) ?: 1;
    echo '<div class="metric">';
    foreach ($data as $k => $v) {
        $w = max(4, round($v / $max * 100));
        echo '<div class="mrow"><span class="name">' . e($L((string)$k)) . '</span>'
           . '<span class="track"><span class="fill" style="width:' . $w . '%"></span></span>'
           . '<span class="val">' . number_format((int)$v) . '</span></div>';
    }
    echo '</div>';
};

/** Ranked list from a [label=>count] map. */
$ranked = function (array $data) {
    if (!$data) { echo '<p class="empty">لا توجد بيانات بعد</p>'; return; }
    echo '<ol class="rank">';
    $i = 0;
    foreach ($data as $label => $count) {
        $i++;
        echo '<li><span class="n">' . $i . '</span><span class="t">' . e((string)$label) . '</span>'
           . '<span class="c">' . number_format((int)$count) . '</span></li>';
    }
    echo '</ol>';
};
?>

<?php if (!empty($flash)): ?><div class="msg"><?= e($flash) ?></div><?php endif; ?>

<!-- Publish a new release to every visitor -->
<div class="card update-hero">
  <div class="u-txt">
    <b>تحديث الموقع</b>
    <p>ينشر إصدارًا جديدًا فورًا لكل المستخدمين، ويحدّث الكاش وworker — بدون أي حاجة لمسح بيانات المتصفح.</p>
  </div>
  <form method="post" action="/<?= ADMIN_PATH ?>/update" onsubmit="this.querySelector('button').disabled=true">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn" type="submit">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 12a9 9 0 1 1-3-6.7M21 4v5h-5"/></svg>
      تحديث الموقع الآن
    </button>
  </form>
</div>

<!-- Headline stats -->
<div class="stat-grid">
  <div class="stat live"><span class="lbl"><span class="pulse"></span> المتصلون الآن</span><b><?= $fmt($onlineNow) ?></b></div>
  <div class="stat"><span class="lbl">زوار اليوم</span><b><?= $fmt($today) ?></b></div>
  <div class="stat"><span class="lbl">هذا الأسبوع</span><b><?= $fmt($week) ?></b></div>
  <div class="stat"><span class="lbl">هذا الشهر</span><b><?= $fmt($month) ?></b></div>
  <div class="stat"><span class="lbl">إجمالي الزيارات</span><b><?= $fmt($total) ?></b></div>
  <div class="stat"><span class="lbl">مشتركو الإشعارات</span><b><?= $fmt($tokens) ?></b></div>
  <div class="stat"><span class="lbl">النشرة البريدية</span><b><?= $fmt($emails) ?></b></div>
</div>

<!-- 14-day traffic -->
<div class="card">
  <b>الزيارات — آخر ١٤ يومًا</b>
  <?php $max = 1; foreach ($days as $d) $max = max($max, (int)($d['total'] ?? 0)); ?>
  <div class="bar">
    <?php foreach ($days as $day => $d): ?>
      <div style="height:<?= max(4, round(((int)($d['total'] ?? 0)) / $max * 100)) ?>%" title="<?= e($day) ?>: <?= (int)($d['total'] ?? 0) ?>"><span><?= e(substr($day, 5)) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if (empty($days)): ?><small class="hint">تبدأ الإحصائيات بالتجميع مع زيارات الموقع.</small><?php endif; ?>
</div>

<div class="cols c2">
  <div class="card"><b>مصادر الزيارات</b><?php $metric($sources); ?></div>
  <div class="card"><b>الأجهزة</b><?php $metric($devices); ?></div>
</div>
<div class="cols c2">
  <div class="card"><b>المتصفحات</b><?php $metric($browsers); ?></div>
  <div class="card"><b>أكثر البطولات زيارة (فيديو)</b><?php $ranked($topChamps); ?></div>
</div>

<div class="cols c2">
  <div class="card"><b>أكثر المباريات مشاهدة</b><?php $ranked($topMatches); ?></div>
  <div class="card"><b>أكثر الأخبار قراءة</b><?php $ranked($topNews); ?></div>
</div>
<div class="card"><b>أكثر الفيديوهات مشاهدة</b><?php $ranked($topVideos); ?></div>

<!-- Server & cache health -->
<div class="card">
  <b>حالة الخادم والكاش</b>
  <div class="metric" style="margin-top:12px">
    <?php if (!empty($server['mem_total'])): ?>
    <div class="mrow"><span class="name">الذاكرة (RAM)</span><span class="track"><span class="fill" style="width:<?= $pct($server['mem_used'], $server['mem_total']) ?>%"></span></span><span class="val"><?= $pct($server['mem_used'], $server['mem_total']) ?>%</span></div>
    <?php endif; ?>
    <?php if (!empty($server['disk_total'])): ?>
    <div class="mrow"><span class="name">التخزين</span><span class="track"><span class="fill" style="width:<?= $pct($server['disk_used'], $server['disk_total']) ?>%"></span></span><span class="val"><?= $pct($server['disk_used'], $server['disk_total']) ?>%</span></div>
    <?php endif; ?>
  </div>
  <div class="grid" style="margin-top:16px">
    <div class="kpi"><b><?= $server['load'] !== null ? e($server['load']) : '—' ?></b><span>حمل المعالج<?= $server['cores'] ? ' · ' . (int)$server['cores'] . ' نواة' : '' ?></span></div>
    <div class="kpi"><b><?= $mb($server['php_mem']) ?></b><span>ذاكرة PHP</span></div>
    <div class="kpi"><b><?= e($server['php']) ?></b><span>إصدار PHP</span></div>
    <div class="kpi"><b><?= $fmt($cache['api_files']) ?></b><span>ملفات كاش API</span></div>
    <div class="kpi"><b><?= $mb($cache['api_bytes'] + $cache['media_bytes']) ?></b><span>حجم الكاش</span></div>
    <div class="kpi"><b><?= !empty($server['opcache']) ? 'مفعّل' : 'متوقف' ?></b><span>OPcache</span></div>
  </div>
  <small class="hint">الإصدار الحالي: <code><?= e($build) ?></code></small>
</div>

<?php admin_bottom();
