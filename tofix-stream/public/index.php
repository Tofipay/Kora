<?php

/**
 * public/index.php
 * -----------------------------------------------------------------------------
 * لوحة التحكّم الرئيسية لمنصّة ToFi X Stream.
 * صفحة SPA خفيفة: الهيكل HTML/CSS، والبيانات تُحمَّل عبر AJAX من api/.
 *
 * @package ToFiXStream\Dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use ToFiXStream\Config;

$appName    = Config::get('app.name');
$version    = Config::get('app.version');
$baseUrl    = Config::get('app.base_url');
$categories = Config::get('dashboard.categories', []);
$qualities  = array_keys((array) Config::get('ffmpeg.qualities', []));
$sourceTypes = \ToFiXStream\Channel::SOURCE_TYPES;
// مفتاح API يُحقن في الواجهة لأنها لوحة تحكّم محميّة على الخادم.
$apiKey = Config::get('security.api_key');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> — Control Panel</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div class="layout">

  <!-- ============ Sidebar ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <span class="logo"><i class="bi bi-broadcast"></i></span>
      <div>ToFi X Stream <small>Streaming Platform</small></div>
    </div>
    <nav>
      <div class="nav-item active" data-view="dashboard"><i class="bi bi-grid-1x2"></i> لوحة التحكم</div>
      <div class="nav-item" data-view="channels"><i class="bi bi-collection-play"></i> القنوات</div>
      <div class="nav-item" data-view="monitor"><i class="bi bi-activity"></i> مراقبة البثّ</div>
      <div class="nav-item" data-view="server"><i class="bi bi-hdd-network"></i> حالة السيرفر</div>
    </nav>
    <div class="glass" style="margin-top:30px;padding:16px;font-size:12px;color:var(--text-dim)">
      <b style="color:var(--text)">النسخة</b> v<?= htmlspecialchars($version) ?><br>
      REST API متاح على <code>/api/</code>
    </div>
  </aside>

  <!-- ============ Main ============ -->
  <main class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="btn btn-icon menu-toggle" id="menuToggle"><i class="bi bi-list"></i></button>
        <div>
          <h1 id="viewTitle">لوحة التحكم</h1>
          <div class="sub" id="viewSub">نظرة عامة على منصّة البثّ الخاصة بك</div>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn" id="refreshBtn"><i class="bi bi-arrow-clockwise"></i> تحديث</button>
        <button class="btn btn-primary" id="addChannelBtn"><i class="bi bi-plus-lg"></i> قناة جديدة</button>
      </div>
    </div>

    <!-- ===== View: Dashboard ===== -->
    <section id="view-dashboard">
      <div class="stat-grid" id="statCards"></div>

      <div class="section-title"><i class="bi bi-cpu"></i> حالة الخادم</div>
      <div class="meter-grid" id="serverMeters"></div>

      <div class="section-title" style="justify-content:space-between;display:flex">
        <span><i class="bi bi-collection-play"></i> أحدث القنوات</span>
        <a href="#" class="muted" data-view="channels" style="font-size:13px">عرض الكل</a>
      </div>
      <div class="glass table-wrap"><table class="data"><thead>
        <tr><th>القناة</th><th>التصنيف</th><th>الجودة</th><th>الحالة</th><th>البثّ</th><th></th></tr>
      </thead><tbody id="recentChannels"></tbody></table></div>
    </section>

    <!-- ===== View: Channels ===== -->
    <section id="view-channels" style="display:none">
      <div class="glass table-wrap"><table class="data"><thead>
        <tr><th>القناة</th><th>المصدر</th><th>التصنيف</th><th>الدولة</th><th>الجودة</th><th>الحالة</th><th>رابط البثّ الجديد (.m3u8)</th><th>إجراءات</th></tr>
      </thead><tbody id="channelsTable"></tbody></table></div>
    </section>

    <!-- ===== View: Monitor ===== -->
    <section id="view-monitor" style="display:none">
      <div class="glass table-wrap"><table class="data"><thead>
        <tr><th>القناة</th><th>الحالة</th><th>الدقة</th><th>FPS</th><th>Video Codec</th><th>Audio Codec</th><th>Bitrate</th><th></th></tr>
      </thead><tbody id="monitorTable"></tbody></table></div>
    </section>

    <!-- ===== View: Server ===== -->
    <section id="view-server" style="display:none">
      <div class="stat-grid" id="serverCards"></div>
      <div class="meter-grid" id="serverMetersFull"></div>
    </section>
  </main>
</div>

<!-- ============ Channel Modal ============ -->
<div class="modal-backdrop-x" id="channelModal">
  <div class="modal-x glass">
    <h3 id="modalTitle"><i class="bi bi-plus-circle"></i> إضافة قناة جديدة</h3>
    <form id="channelForm">
      <input type="hidden" name="id">
      <div class="grid-2">
        <div class="field"><label>اسم القناة *</label><input name="name" required placeholder="مثال: ToFi Sports HD"></div>
        <div class="field"><label>شعار القناة (رابط)</label><input name="logo" placeholder="https://..."></div>
      </div>
      <div class="field"><label>رابط مصدر البثّ *</label>
        <input name="source_url" required placeholder="https://stream.gumlet.io/.../main.m3u8"></div>
      <div class="grid-2">
        <div class="field"><label>نوع المصدر</label><select name="source_type">
          <?php foreach ($sourceTypes as $t): ?><option value="<?= $t ?>"><?= strtoupper($t) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>وضع إعادة البثّ</label><select name="mode">
          <option value="proxy">Proxy (خفيف — إعادة كتابة المانيفست)</option>
          <option value="ffmpeg">FFmpeg (إعادة ترميز/نسخ)</option>
        </select></div>
      </div>
      <div class="grid-2">
        <div class="field"><label>التصنيف</label><select name="category">
          <?php foreach ($categories as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>الجودة</label><select name="quality">
          <?php foreach ($qualities as $q): ?><option value="<?= $q ?>"><?= ucfirst($q) ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="grid-2">
        <div class="field"><label>الدولة (رمز)</label><input name="country" placeholder="SA" maxlength="3"></div>
        <div class="field"><label>لغة الصوت</label><input name="audio_lang" placeholder="ar" value="ar"></div>
      </div>
      <div class="field"><label>الوصف</label><textarea name="description" rows="2" placeholder="وصف مختصر للقناة"></textarea></div>
      <div class="field"><label>الحالة</label><select name="status">
        <option value="active">نشطة (Active)</option>
        <option value="inactive">متوقّفة (Inactive)</option>
      </select></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn" id="cancelModal">إلغاء</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> حفظ</button>
      </div>
    </form>
  </div>
</div>

<div id="toaster"></div>

<script>
  // إعدادات يحقنها الخادم للواجهة.
  window.TOFIX = {
    apiBase: '../api/index.php',
    baseUrl: <?= json_encode($baseUrl) ?>,
    apiKey:  <?= json_encode($apiKey) ?>,
  };
</script>
<script src="../assets/js/app.js"></script>
</body>
</html>
