<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  sniffer.php  —  مستكشف الموارد (قلب النظام، يعمل مثل 1DM)
 * ───────────────────────────────────────────────────────────────────────────
 *  يجمع هذا المستكشف بين طريقتين للاكتشاف:
 *    (أ) جهة الخادم: نداء player.php الذي يجلب الصفحة عبر cURL ويستخرج الروابط.
 *    (ب) جهة العميل: فتح الصفحة في iframe مدمج وفحص محتواه بشكل دوري + الاستماع
 *        لرسائل postMessage لالتقاط أي رابط بث يظهر لاحقاً.
 *  كل الروابط المكتشفة تُعرض كبطاقات فيها أزرار «تشغيل» و«نسخ».
 * ═══════════════════════════════════════════════════════════════════════════
 */
$initialUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>المستكشف — LiveStream Pro</title>
    <link rel="stylesheet" href="assets/css/player.css">
    <!-- مكتبات التشغيل عبر CDN -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>
</head>
<body>

<!-- الشريط العلوي -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <span class="brand-icon">📡</span>
            <span class="brand-name">LiveStream <b>Pro</b></span>
            <span class="live-badge">LIVE</span>
        </div>
        <nav class="topnav">
            <a href="index.php">الرئيسية</a>
            <a href="sniffer.php" class="active">المستكشف</a>
            <a href="watch.php">تشغيل مباشر</a>
        </nav>
    </div>
</header>

<main class="container-wide">

    <!-- شريط أدوات علوي: حقل URL + فحص + مسح -->
    <div class="toolbar">
        <input type="url" id="targetUrl" class="url-input"
               placeholder="https://example.com/stream-page"
               value="<?php echo htmlspecialchars($initialUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <button class="btn btn-primary" onclick="Sniffer.start()">🔍 فحص</button>
        <button class="btn btn-ghost"   onclick="Sniffer.clear()">🧹 مسح</button>
        <span id="scanIndicator" class="scan-indicator" style="display:none;">
            <span class="dot-pulse"></span> يفحص…
        </span>
    </div>

    <!-- تخطيط عمودين: (يسار) الموارد — (يمين) المشغّل + المتصفح المدمج -->
    <div class="sniffer-layout">

        <!-- ── قسم الموارد المكتشفة ─────────────────────────────────────── -->
        <section class="resources-panel">
            <div class="panel-head">
                <h2>الموارد المكتشفة</h2>
                <span id="resCount" class="count-badge">0</span>
            </div>
            <div id="resourceList" class="resource-list">
                <div class="empty-state" id="emptyState">
                    لا توجد موارد بعد — اضغط «فحص» لبدء الاستكشاف.
                </div>
            </div>
        </section>

        <!-- ── قسم المشغّل + المتصفح المدمج ─────────────────────────────── -->
        <section class="viewer-panel">
            <!-- مشغّل الفيديو المدمج -->
            <div class="player-wrap">
                <video id="video" class="video" controls autoplay playsinline></video>
            </div>
            <div class="info-bar">
                <div class="info-item">النوع: <span id="infoType" class="pill">—</span></div>
                <div class="info-item">الحالة: <span id="infoStatus" class="pill pill-idle">في الانتظار</span></div>
            </div>

            <!-- المتصفح المدمج: يعرض الصفحة المستهدفة ويُفحص محتواه -->
            <div class="browser-frame">
                <div class="frame-label">🖥️ المتصفح المدمج (الصفحة المستهدفة)</div>
                <iframe id="siteFrame" class="site-frame"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
                        referrerpolicy="no-referrer"></iframe>
            </div>
        </section>
    </div>
</main>

<footer class="footer">
    <p>LiveStream Pro — المستكشف. للمحتوى المصرّح لك بالوصول إليه فقط.</p>
</footer>

<!-- محرك الاستكشاف والتشغيل -->
<script src="assets/js/player.js"></script>
<script>
// تشغيل تلقائي إن مُرّر رابط في الـ URL
window.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('targetUrl').value.trim()) Sniffer.start();
});
</script>
</body>
</html>
