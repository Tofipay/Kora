<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  index.php  —  الصفحة الرئيسية لنظام LiveStream Pro
 * ───────────────────────────────────────────────────────────────────────────
 *  واجهة داكنة احترافية فيها:
 *    - حقل إدخال رابط الصفحة المستهدفة
 *    - زر "فتح المستكشف"  → يوجّه إلى sniffer.php?url=...
 *    - زر "تشغيل مباشر"    → يوجّه إلى watch.php?url=...
 *    - شبكة بطاقات تعرض مميزات النظام
 * ═══════════════════════════════════════════════════════════════════════════
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiveStream Pro — مستكشف البث المباشر</title>
    <link rel="stylesheet" href="assets/css/player.css">
</head>
<body>

<!-- ── الشريط العلوي مع شعار وشارة LIVE النابضة ───────────────────────────── -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <span class="brand-icon">📡</span>
            <span class="brand-name">LiveStream <b>Pro</b></span>
            <span class="live-badge">LIVE</span>
        </div>
        <nav class="topnav">
            <a href="index.php" class="active">الرئيسية</a>
            <a href="sniffer.php">المستكشف</a>
            <a href="watch.php">تشغيل مباشر</a>
        </nav>
    </div>
</header>

<!-- ── القسم البطولي (Hero): إدخال الرابط والأزرار ─────────────────────────── -->
<main class="container">
    <section class="hero">
        <h1 class="hero-title">مستكشف البث المباشر الاحترافي</h1>
        <p class="hero-sub">
            أدخل رابط أي صفحة بث، وسيقوم النظام باكتشاف روابط البث
            (m3u8 / mpd / mp4) تلقائياً وتشغيلها فوراً — تماماً مثل 1DM.
        </p>

        <!-- نموذج الإدخال: يوجّه إلى المستكشف أو المشغّل المباشر -->
        <form class="url-form" id="mainForm" onsubmit="return false;">
            <input type="url" id="urlInput" class="url-input"
                   placeholder="https://example.com/stream-page" autocomplete="off" required>
            <div class="url-actions">
                <button type="button" class="btn btn-primary" onclick="goSniffer()">
                    🔍 فتح المستكشف
                </button>
                <button type="button" class="btn btn-ghost" onclick="goWatch()">
                    ▶ تشغيل مباشر
                </button>
            </div>
        </form>
        <p class="hint">
            نصيحة: استخدم «المستكشف» لصفحات البث التي تُخفي الرابط،
            و«تشغيل مباشر» إن كان لديك رابط m3u8/mpd جاهز.
        </p>
    </section>

    <!-- ── شبكة بطاقات المميزات ──────────────────────────────────────────── -->
    <section class="features">
        <h2 class="section-title">مميزات النظام</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-ico">🧭</div>
                <h3>اكتشاف تلقائي</h3>
                <p>يفحص الصفحة والإطارات (iframes) ويلتقط روابط البث تلقائياً كل بضع ثوانٍ.</p>
            </div>
            <div class="feature-card">
                <div class="feature-ico">⚡</div>
                <h3>تشغيل فوري</h3>
                <p>دعم HLS عبر hls.js وDASH عبر dash.js مع وضع زمن الوصول المنخفض.</p>
            </div>
            <div class="feature-card">
                <div class="feature-ico">🔁</div>
                <h3>إعادة اتصال ذكية</h3>
                <p>يعيد الاتصال تلقائياً عند انقطاع البث بتأخير متصاعد حتى استقرار التشغيل.</p>
            </div>
            <div class="feature-card">
                <div class="feature-ico">🎚️</div>
                <h3>تحكم بالجودة</h3>
                <p>لوحة اختيار الجودة تُبنى تلقائياً من مستويات البث المتاحة في HLS.</p>
            </div>
            <div class="feature-card">
                <div class="feature-ico">🧩</div>
                <h3>محرك استخراج قوي</h3>
                <p>Regex + DOMDocument + XPath + فك base64 لالتقاط الروابط المخفية.</p>
            </div>
            <div class="feature-card">
                <div class="feature-ico">📱</div>
                <h3>جاهز للموبايل</h3>
                <p>تصميم متجاوب بالكامل، مع دليل تطبيق Android يعتمد على ExoPlayer.</p>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <p>LiveStream Pro — أداة استكشاف وتشغيل بث مباشر. استخدمها للمحتوى المصرّح لك بالوصول إليه فقط.</p>
</footer>

<script>
// ── قراءة الرابط والتوجيه إلى الصفحة المناسبة ───────────────────────────────
function currentUrl() {
    const v = document.getElementById('urlInput').value.trim();
    if (!v) { alert('الرجاء إدخال رابط صحيح.'); return null; }
    return v;
}
// فتح المستكشف
function goSniffer() {
    const u = currentUrl();
    if (u) location.href = 'sniffer.php?url=' + encodeURIComponent(u);
}
// التشغيل المباشر
function goWatch() {
    const u = currentUrl();
    if (u) location.href = 'watch.php?url=' + encodeURIComponent(u);
}
// السماح بالإرسال عبر Enter → المستكشف افتراضياً
document.getElementById('urlInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); goSniffer(); }
});
</script>
</body>
</html>
