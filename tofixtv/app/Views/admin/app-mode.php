<?php require __DIR__ . '/_shell.php'; admin_top('وضع التطبيق', 'app-mode'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <label style="display:flex;gap:12px;align-items:flex-start;cursor:pointer;padding:14px;border:1.5px solid var(--line);border-radius:12px;margin-bottom:12px;<?= ($mode ?? 'both') === 'both' ? 'border-color:var(--p);background:rgba(76,14,205,.10)' : '' ?>">
      <input type="radio" name="mode" value="both" <?= ($mode ?? 'both') === 'both' ? 'checked' : '' ?> style="margin-top:3px">
      <span>
        <b style="font-size:15px">التطبيق والمتصفح معاً</b><br>
        <small class="hint">الوضع الافتراضي — يعمل الموقع بشكل طبيعي في المتصفح وداخل التطبيق.</small>
      </span>
    </label>

    <label style="display:flex;gap:12px;align-items:flex-start;cursor:pointer;padding:14px;border:1.5px solid var(--line);border-radius:12px;margin-bottom:14px;<?= ($mode ?? 'both') === 'app' ? 'border-color:var(--p);background:rgba(76,14,205,.10)' : '' ?>">
      <input type="radio" name="mode" value="app" <?= ($mode ?? 'both') === 'app' ? 'checked' : '' ?> style="margin-top:3px">
      <span>
        <b style="font-size:15px">داخل التطبيق فقط</b><br>
        <small class="hint">لا يعمل الموقع في المتصفح — يظهر للزائر صفحة تحميل التطبيق مع زر تنزيل
        <code dir="ltr">aloka-live.apk</code>. ويعمل داخل التطبيق طبيعياً عبر
        <code dir="ltr">User-Agent: com.aloka.live.app</code>. لوحة الإدارة تبقى متاحة من المتصفح.</small>
      </span>
    </label>

    <button class="btn" type="submit">حفظ</button>
  </form>
</div>

<div class="card">
  <b style="font-size:15px">ملف التطبيق (APK)</b>
  <p class="hint" style="margin:8px 0 12px">
    زر التحميل ينزّل الملف <code dir="ltr">aloka-live.apk</code>. ضع ملف التطبيق الحقيقي في المسار التالي على الاستضافة:
  </p>
  <div style="background:#10033d;border:1px solid var(--line);border-radius:10px;padding:11px 13px;direction:ltr;text-align:left;font-family:monospace;font-size:13px;margin-bottom:12px">
    storage/app/aloka-live.apk
  </div>
  <?php if (!empty($apk)): ?>
    <span class="cchip" style="background:rgba(22,199,132,.15);border:1px solid rgba(22,199,132,.4);color:#4ade80;padding:6px 12px;border-radius:999px;font-weight:700;font-size:12.5px">✔ ملف APK موجود ويعمل زر التحميل</span>
    <p class="hint" style="margin-top:10px"><a href="/download/app" target="_blank" style="color:var(--p)">تجربة رابط التحميل ↗</a></p>
  <?php else: ?>
    <span class="cchip" style="background:rgba(245,158,11,.14);border:1px solid rgba(245,158,11,.4);color:#fbbf24;padding:6px 12px;border-radius:999px;font-weight:700;font-size:12.5px">⚠ لم يُرفع ملف APK بعد — زر التحميل يحوّل مؤقتاً إلى قناة تيليجرام</span>
  <?php endif; ?>
  <p class="hint" style="margin-top:12px">
    ملاحظة: تأكد أن حزمة التطبيق هي <code dir="ltr">com.aloka.live.app</code> وأن الـ WebView داخل التطبيق
    يرسل نفس المُعرّف في الـ User-Agent، حتى يعمل الموقع داخل التطبيق في وضع «التطبيق فقط».
  </p>
</div>

<?php admin_bottom();
