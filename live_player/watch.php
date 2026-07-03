<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  watch.php  —  مشغّل الرابط المباشر
 * ───────────────────────────────────────────────────────────────────────────
 *  صفحة لتشغيل رابط بث مباشر مُعطى مباشرةً، تحتوي على:
 *    - شريط إدخال الرابط + زر "▶ Load"
 *    - مشغّل فيديو بنسبة 16:9
 *    - شريط معلومات: النوع / الجودة الحالية / حالة البث
 *    - لوحة تغيير الجودة (تظهر مع HLS فقط)
 *    - شريط إعادة الاتصال التلقائي
 *  منطق التشغيل مكتوب بالكامل في هذا الملف (Vanilla JS + hls.js + dash.js).
 * ═══════════════════════════════════════════════════════════════════════════
 */
// نقرأ الرابط الأولي (إن مُرّر) لملء الحقل مسبقاً
$initialUrl = isset($_GET['url']) ? trim($_GET['url']) : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تشغيل مباشر — LiveStream Pro</title>
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
            <a href="sniffer.php">المستكشف</a>
            <a href="watch.php" class="active">تشغيل مباشر</a>
        </nav>
    </div>
</header>

<main class="container">

    <!-- شريط إدخال الرابط -->
    <div class="toolbar">
        <input type="url" id="streamUrl" class="url-input"
               placeholder="ألصق رابط m3u8 / mpd / mp4 هنا"
               value="<?php echo htmlspecialchars($initialUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <button class="btn btn-primary" onclick="loadStream()">▶ Load</button>
    </div>

    <!-- مشغّل الفيديو 16:9 -->
    <div class="player-wrap">
        <video id="video" class="video" controls autoplay playsinline></video>
    </div>

    <!-- شريط المعلومات: النوع / الجودة / الحالة -->
    <div class="info-bar">
        <div class="info-item">النوع: <span id="infoType" class="pill">—</span></div>
        <div class="info-item">الجودة: <span id="infoQuality" class="pill">—</span></div>
        <div class="info-item">الحالة: <span id="infoStatus" class="pill pill-idle">في الانتظار</span></div>
    </div>

    <!-- شريط إعادة الاتصال (يظهر عند الحاجة) -->
    <div id="reconnectBar" class="reconnect-bar" style="display:none;">
        <span class="dot-pulse"></span>
        <span id="reconnectMsg">جارٍ إعادة الاتصال…</span>
    </div>

    <!-- لوحة اختيار الجودة (HLS فقط) -->
    <div id="qualityPanel" class="quality-panel" style="display:none;">
        <div class="quality-title">الجودة</div>
        <div id="qualityLevels" class="quality-levels"></div>
    </div>
</main>

<footer class="footer">
    <p>LiveStream Pro — مشغّل الروابط المباشرة.</p>
</footer>

<script>
/* ═══════════════════════════════════════════════════════════════════════════
 *  منطق التشغيل
 * ═══════════════════════════════════════════════════════════════════════════ */
const video   = document.getElementById('video');
let   hls      = null;    // نسخة hls.js الحالية
let   dashPlayer = null;  // نسخة dash.js الحالية
let   reconnectAttempts = 0;      // عدّاد محاولات إعادة الاتصال
let   reconnectTimer    = null;   // مؤقّت إعادة الاتصال
let   currentUrl = '';            // الرابط قيد التشغيل

// ── تحديث عناصر واجهة المعلومات ─────────────────────────────────────────────
function setType(t)    { document.getElementById('infoType').textContent = t; }
function setQuality(q) { document.getElementById('infoQuality').textContent = q; }
function setStatus(s, cls) {
    const el = document.getElementById('infoStatus');
    el.textContent = s;
    el.className = 'pill ' + (cls || '');
}

// ── كشف نوع البث من الرابط ──────────────────────────────────────────────────
function detectType(url) {
    const u = url.toLowerCase();
    if (u.includes('.m3u8')) return 'm3u8';
    if (u.includes('.mpd'))  return 'mpd';
    if (u.includes('.webm')) return 'webm';
    if (u.includes('.flv'))  return 'flv';
    if (u.includes('.mp4'))  return 'mp4';
    if (u.includes('.ts'))   return 'ts';
    return 'unknown';
}

// ── تنظيف أي مشغّل سابق قبل بدء تشغيل جديد ──────────────────────────────────
function cleanup() {
    if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
    if (dashPlayer) { try { dashPlayer.reset(); } catch (e) {} dashPlayer = null; }
    if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
    document.getElementById('qualityPanel').style.display = 'none';
    document.getElementById('reconnectBar').style.display = 'none';
}

// ── نقطة الدخول: تحميل الرابط من الحقل ──────────────────────────────────────
function loadStream() {
    const url = document.getElementById('streamUrl').value.trim();
    if (!url) { alert('الرجاء إدخال رابط.'); return; }
    reconnectAttempts = 0;
    play(url);
}

// ── التشغيل الفعلي حسب نوع البث ─────────────────────────────────────────────
function play(url) {
    cleanup();
    currentUrl = url;
    const type = detectType(url);
    setType(type.toUpperCase());
    setStatus('جارٍ التحميل…', 'pill-loading');

    if (type === 'm3u8') {
        playHls(url);
    } else if (type === 'mpd') {
        playDash(url);
    } else {
        playNative(url);   // mp4 / webm / ts / unknown
    }
}

// ── تشغيل HLS عبر hls.js أو دعم native ──────────────────────────────────────
function playHls(url) {
    if (window.Hls && Hls.isSupported()) {
        hls = new Hls({
            lowLatencyMode: true,       // وضع زمن الوصول المنخفض
            backBufferLength: 30,
            manifestLoadingRetryDelay: 1000,
        });
        hls.loadSource(url);
        hls.attachMedia(video);

        // عند قراءة قائمة التشغيل: نبني أزرار الجودة
        hls.on(Hls.Events.MANIFEST_PARSED, function (_e, data) {
            setStatus('يبث الآن', 'pill-live');
            reconnectAttempts = 0;
            document.getElementById('reconnectBar').style.display = 'none';
            buildQualityButtons(data.levels);
            video.play().catch(() => {});
        });

        // تحديث مؤشّر الجودة الحالية
        hls.on(Hls.Events.LEVEL_SWITCHED, function (_e, data) {
            const lv = hls.levels[data.level];
            if (lv) setQuality(lv.height ? lv.height + 'p' : (lv.bitrate/1000|0) + 'kbps');
        });

        // معالجة الأخطاء القاتلة → إعادة اتصال
        hls.on(Hls.Events.ERROR, function (_e, data) {
            if (data.fatal) {
                switch (data.type) {
                    case Hls.ErrorTypes.NETWORK_ERROR:
                        scheduleReconnect(); break;
                    case Hls.ErrorTypes.MEDIA_ERROR:
                        try { hls.recoverMediaError(); } catch (e) { scheduleReconnect(); }
                        break;
                    default:
                        scheduleReconnect();
                }
            }
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // دعم native (سفاري / iOS)
        video.src = url;
        video.addEventListener('loadedmetadata', () => {
            setStatus('يبث الآن', 'pill-live');
            video.play().catch(() => {});
        });
        video.addEventListener('error', scheduleReconnect);
    } else {
        setStatus('HLS غير مدعوم في هذا المتصفح', 'pill-error');
    }
}

// ── تشغيل DASH عبر dash.js ──────────────────────────────────────────────────
function playDash(url) {
    if (!window.dashjs) { setStatus('مكتبة DASH غير محمّلة', 'pill-error'); return; }
    dashPlayer = dashjs.MediaPlayer().create();
    dashPlayer.updateSettings({
        streaming: { lowLatencyEnabled: true, delay: { liveDelay: 3 } }
    });
    dashPlayer.initialize(video, url, true);

    dashPlayer.on(dashjs.MediaPlayer.events.STREAM_INITIALIZED, function () {
        setStatus('يبث الآن', 'pill-live');
        reconnectAttempts = 0;
        document.getElementById('reconnectBar').style.display = 'none';
    });
    dashPlayer.on(dashjs.MediaPlayer.events.QUALITY_CHANGE_RENDERED, function (e) {
        try {
            const info = dashPlayer.getBitrateInfoListFor('video')[e.newQuality];
            if (info) setQuality(info.height ? info.height + 'p' : '—');
        } catch (err) {}
    });
    dashPlayer.on(dashjs.MediaPlayer.events.ERROR, scheduleReconnect);
    dashPlayer.on(dashjs.MediaPlayer.events.PLAYBACK_ERROR, scheduleReconnect);
}

// ── تشغيل مباشر عبر HTML5 (mp4 / webm / ts) ────────────────────────────────
function playNative(url) {
    video.src = url;
    video.addEventListener('loadedmetadata', () => {
        setStatus('يبث الآن', 'pill-live');
        setQuality('أصلية');
        video.play().catch(() => {});
    }, { once: true });
    video.addEventListener('error', scheduleReconnect, { once: true });
}

// ── بناء أزرار الجودة من مستويات HLS ───────────────────────────────────────
function buildQualityButtons(levels) {
    const panel = document.getElementById('qualityPanel');
    const box   = document.getElementById('qualityLevels');
    box.innerHTML = '';
    if (!levels || levels.length <= 1) { panel.style.display = 'none'; return; }
    panel.style.display = 'block';

    // زر "تلقائي"
    const autoBtn = document.createElement('button');
    autoBtn.className = 'quality-btn active';
    autoBtn.textContent = 'تلقائي';
    autoBtn.onclick = () => { hls.currentLevel = -1; markActive(autoBtn); };
    box.appendChild(autoBtn);

    // زر لكل مستوى جودة
    levels.forEach((lv, i) => {
        const b = document.createElement('button');
        b.className = 'quality-btn';
        b.textContent = lv.height ? lv.height + 'p' : ((lv.bitrate/1000|0) + 'k');
        b.onclick = () => { hls.currentLevel = i; markActive(b); };
        box.appendChild(b);
    });
}
function markActive(btn) {
    document.querySelectorAll('.quality-btn').forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
}

// ── جدولة إعادة الاتصال بتأخير متصاعد (3s × عدد المحاولات) ──────────────────
function scheduleReconnect() {
    if (reconnectAttempts >= 8) {
        setStatus('تعذّر الاتصال — تحقّق من الرابط', 'pill-error');
        document.getElementById('reconnectBar').style.display = 'none';
        return;
    }
    reconnectAttempts++;
    const delay = 3000 * reconnectAttempts;   // 3s, 6s, 9s ...
    setStatus('انقطع البث', 'pill-error');
    const bar = document.getElementById('reconnectBar');
    bar.style.display = 'flex';
    document.getElementById('reconnectMsg').textContent =
        `جارٍ إعادة الاتصال (محاولة ${reconnectAttempts}) خلال ${delay/1000} ثانية…`;

    if (reconnectTimer) clearTimeout(reconnectTimer);
    reconnectTimer = setTimeout(() => { if (currentUrl) play(currentUrl); }, delay);
}

// ── تشغيل تلقائي إن مُرّر رابط في الـ URL ────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    const u = document.getElementById('streamUrl').value.trim();
    if (u) loadStream();
});
</script>
</body>
</html>
