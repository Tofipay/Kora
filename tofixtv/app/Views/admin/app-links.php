<?php require __DIR__ . '/_shell.php'; admin_top('التطبيق — روابط مباريات التطبيق', 'app'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="app-tabs">
  <a class="app-tab on" href="/<?= ADMIN_PATH ?>/app/links">روابط مباريات التطبيق</a>
  <a class="app-tab" href="/<?= ADMIN_PATH ?>/app/channels">مكتبة القنوات</a>
</div>

<div class="card">
  <b>روابط مباريات التطبيق</b>
  <p class="hint" style="margin:6px 0 0">
    أضف قيمة التطبيق لكل مباراة. يمكن أن تكون رابطاً عادياً
    (<code dir="ltr">http://ver3.yacinelive.com/api/channel/1473</code>)
    <b>أو نصاً/توكن مشفّراً طويلاً</b> — تُحفظ كما أُدخلت تماماً بدون أي فلترة أو تحقق.
    عند فتح صفحة المباراة من تطبيق Android (<code dir="ltr">User-Agent: com.aloka.live.app</code>) يظهر
    الزر الأزرق «شاهد المباراة الآن» ويمرّر القيمة كما هي عبر
    <code dir="ltr">intent://…#Intent;scheme=xmtv;package=com.aloka.live.app;end</code>.<br>
    المباريات التي لا تملك قيمة مباشرة تسحب قيمها تلقائياً من
    <a href="/<?= ADMIN_PATH ?>/app/channels" style="color:var(--p)">مكتبة قنوات التطبيق</a>
    عند تطابق اسم قناة المباراة. الموقع العادي وزرّه البرتقالي لا يتأثران إطلاقاً.
  </p>
</div>

<div class="app-days">
  <?php foreach (['yesterday' => 'الأمس', 'today' => 'اليوم', 'tomorrow' => 'غداً'] as $k => $lbl): ?>
  <a class="app-day<?= $day === $k ? ' on' : '' ?>" href="/<?= ADMIN_PATH ?>/app/links?day=<?= $k ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" action="/<?= ADMIN_PATH ?>/app/links?day=<?= e($day) ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <div class="card" style="overflow-x:auto">
    <?php if (empty($list)): ?>
      <div class="empty">لا توجد مباريات في هذا اليوم.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>المباراة</th>
          <th>البطولة</th>
          <th>التوقيت</th>
          <th style="min-width:320px">رابط التطبيق</th>
          <th>قنوات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
        <tr>
          <td style="font-weight:700"><?= e($row['title']) ?><br><small class="hint" style="margin:0">#<?= (int)$row['id'] ?></small></td>
          <td><?= e($row['league']) ?></td>
          <td dir="ltr"><?= e($row['time']) ?></td>
          <td>
            <input type="text" dir="ltr" name="app_url[<?= (int)$row['id'] ?>]" value="<?= e($row['url']) ?>"
                   placeholder="http://ver3.yacinelive.com/api/channel/1473">
            <input type="hidden" name="app_title[<?= (int)$row['id'] ?>]" value="<?= e($row['title']) ?>">
          </td>
          <td>
            <?php if ($row['url'] !== ''): ?>
              <span class="app-badge ok">رابط مباشر</span>
            <?php elseif ($row['chUrls'] > 0): ?>
              <span class="app-badge auto">تلقائي (<?= (int)$row['chUrls'] ?>)</span>
            <?php else: ?>
              <span class="app-badge none">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="btn">حفظ الروابط</button>
    <?php endif; ?>
  </div>
</form>

<?php if (!empty($configured)): ?>
<div class="card">
  <b>مباريات أخرى مضاف لها رابط</b>
  <table>
    <thead><tr><th>المباراة</th><th>الرابط</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($configured as $c): ?>
      <tr>
        <td style="font-weight:700"><?= e($c['title']) ?> <small class="hint" style="display:inline;margin:0">#<?= (int)$c['id'] ?></small></td>
        <td dir="ltr" style="word-break:break-all"><?= e($c['url']) ?></td>
        <td>
          <form method="post" action="/<?= ADMIN_PATH ?>/app/links" style="margin:0">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="app_url[<?= (int)$c['id'] ?>]" value="">
            <button type="submit" class="btn danger" style="margin-top:0;padding:6px 14px">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <b>إضافة رابط بمعرّف المباراة</b>
  <p class="hint" style="margin:6px 0 0">لمباراة غير ظاهرة في قائمة اليوم — أدخل رقم المباراة (match id) والرابط.</p>
  <form method="post" action="/<?= ADMIN_PATH ?>/app/links">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <label>معرّف المباراة
        <input type="text" dir="ltr" name="manual_id" placeholder="123456">
      </label>
      <label>رابط التطبيق
        <input type="text" dir="ltr" name="manual_url" placeholder="http://ver3.yacinelive.com/api/channel/1473">
      </label>
    </div>
    <button type="submit" class="btn">إضافة</button>
  </form>
</div>

<style>
.app-tabs{display:flex;gap:8px;margin-bottom:16px}
.app-tab{padding:9px 18px;border-radius:999px;border:1.5px solid var(--line);font-weight:800;color:var(--tx2)}
.app-tab.on{background:linear-gradient(135deg,rgba(76, 14, 205,.25),rgba(124, 77, 255,.12));border-color:rgba(124, 77, 255,.5);color:#93c5fd}
.app-days{display:flex;gap:8px;margin-bottom:14px}
.app-day{padding:7px 16px;border-radius:999px;border:1px solid var(--line);font-weight:700;color:var(--tx2);font-size:13px}
.app-day.on{background:rgba(76, 14, 205,.14);border-color:rgba(76, 14, 205,.45);color:var(--p)}
.app-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;white-space:nowrap}
.app-badge.ok{background:rgba(76, 14, 205,.18);color:#93c5fd}
.app-badge.auto{background:rgba(76, 14, 205,.15);color:var(--p)}
.app-badge.none{color:var(--tx3)}
td input[type=text]{min-width:280px}
</style>
<?php admin_bottom(); ?>
