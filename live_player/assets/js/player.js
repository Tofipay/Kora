/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  player.js  —  محرك الاستكشاف والتشغيل الخاص بـ sniffer.php
 * ───────────────────────────────────────────────────────────────────────────
 *  يوفّر كائناً عاماً واحداً هو Sniffer، مسؤول عن:
 *    - نداء player.php لجلب الروابط من جهة الخادم
 *    - فتح الصفحة في iframe مدمج وفحص محتواه دورياً
 *    - الاستماع لرسائل postMessage
 *    - عرض كل مورد مكتشف كبطاقة فيها «تشغيل» و«نسخ»
 *    - تشغيل المورد عبر hls.js / dash.js / HTML5 مع إعادة اتصال تلقائية
 * ═══════════════════════════════════════════════════════════════════════════
 */
const Sniffer = (function () {

    // ── الحالة الداخلية ─────────────────────────────────────────────────────
    const state = {
        resources: new Map(),   // الرابط => {name, url, type} لمنع التكرار
        scanTimer: null,        // مؤقّت الفحص الدوري
        scanCount: 0,           // عدّاد جولات الفحص
        maxScans: 10,           // 10 جولات × 3 ثوانٍ = 30 ثانية
        hls: null,              // نسخة hls.js الحالية
        dashPlayer: null,       // نسخة dash.js الحالية
        reconnectAttempts: 0,   // عدّاد إعادة الاتصال
        reconnectTimer: null,
        currentUrl: '',         // الرابط قيد التشغيل
    };

    // ── أنماط Regex للبحث عن روابط البث في النصوص الخام ─────────────────────
    const PATTERNS = [
        { re: /https?:\/\/[^\s"'<>]+?\.m3u8(?:\?[^\s"'<>]+)?/gi, type: 'm3u8' },
        { re: /https?:\/\/[^\s"'<>]+?\.mpd(?:\?[^\s"'<>]+)?/gi,  type: 'mpd'  },
        { re: /https?:\/\/[^\s"'<>]+?\.ts(?:\?[^\s"'<>]+)?/gi,   type: 'ts'   },
        { re: /https?:\/\/[^\s"'<>]+?\.mp4(?:\?[^\s"'<>]+)?/gi,  type: 'mp4'  },
        { re: /https?:\/\/[^\s"'<>]+?\.webm(?:\?[^\s"'<>]+)?/gi, type: 'webm' },
        { re: /https?:\/\/[^\s"'<>]+?\.flv(?:\?[^\s"'<>]+)?/gi,  type: 'flv'  },
    ];

    // ═══════════════════════════════════════════════════════════════════════
    //  عناصر الواجهة
    // ═══════════════════════════════════════════════════════════════════════
    const $ = (id) => document.getElementById(id);
    const video = () => $('video');

    // ── تحديث مؤشّرات الحالة ────────────────────────────────────────────────
    function setType(t)   { $('infoType').textContent = t; }
    function setStatus(s, cls) {
        const el = $('infoStatus');
        el.textContent = s;
        el.className = 'pill ' + (cls || '');
    }
    function showScanning(on) {
        $('scanIndicator').style.display = on ? 'inline-flex' : 'none';
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (1) بدء الفحص
    // ═══════════════════════════════════════════════════════════════════════
    function start() {
        const url = $('targetUrl').value.trim();
        if (!url) { alert('الرجاء إدخال رابط الصفحة المستهدفة.'); return; }
        if (!/^https?:\/\//i.test(url)) { alert('الرابط يجب أن يبدأ بـ http/https.'); return; }

        clear();                       // تصفير أي نتائج سابقة
        state.currentUrl = '';
        showScanning(true);

        // (أ) الاكتشاف من جهة الخادم عبر player.php
        fetchFromServer(url);

        // (ب) فتح الصفحة في iframe المدمج لبدء الاكتشاف من جهة العميل
        try { $('siteFrame').src = url; } catch (e) {}

        // (ج) بدء الفحص الدوري كل 3 ثوانٍ لمدة 30 ثانية
        state.scanCount = 0;
        if (state.scanTimer) clearInterval(state.scanTimer);
        state.scanTimer = setInterval(periodicScan, 3000);
        periodicScan();                // فحص فوري أول
    }

    // ── نداء player.php لجلب الروابط المستخرجة من جهة الخادم ────────────────
    function fetchFromServer(url) {
        fetch('player.php?url=' + encodeURIComponent(url))
            .then(r => r.json())
            .then(data => {
                if (data && Array.isArray(data.servers)) {
                    data.servers.forEach(s => addResource(s.url, s.type, 'خادم'));
                }
            })
            .catch(() => { /* تجاهل أخطاء الشبكة بصمت والاعتماد على الفحص العميل */ });
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (2) الفحص الدوري من جهة العميل
    // ═══════════════════════════════════════════════════════════════════════
    function periodicScan() {
        state.scanCount++;
        // إيقاف الفحص بعد بلوغ الحد الأقصى
        if (state.scanCount > state.maxScans) {
            clearInterval(state.scanTimer);
            state.scanTimer = null;
            showScanning(false);
            return;
        }
        scanIframe();
    }

    // ── فحص محتوى الـ iframe المدمج (يعمل فقط إن سمح same-origin) ────────────
    function scanIframe() {
        const frame = $('siteFrame');
        let doc = null;
        try {
            // الوصول لمستند الإطار — قد يفشل بسبب سياسة same-origin
            doc = frame.contentDocument || frame.contentWindow.document;
        } catch (e) {
            // مصدر مختلف: لا يمكن قراءة المحتوى مباشرة، نعتمد على postMessage + الخادم
            return;
        }
        if (!doc) return;

        // (أ) فحص innerHTML كاملاً بالأنماط
        try { scanText(doc.documentElement.innerHTML); } catch (e) {}

        // (ب) فحص عناصر الفيديو والمصادر مباشرة
        try {
            doc.querySelectorAll('video, source').forEach(el => {
                const src = el.src || el.getAttribute('src') || '';
                if (src) addResource(absolutize(src, frame.src), guessType(src), 'فيديو');
            });
        } catch (e) {}

        // (ج) محاولة فحص الإطارات الداخلية (same-origin فقط)
        try {
            doc.querySelectorAll('iframe').forEach(inner => {
                try {
                    const idoc = inner.contentDocument;
                    if (idoc) scanText(idoc.documentElement.innerHTML);
                } catch (e) { /* إطار من مصدر مختلف */ }
            });
        } catch (e) {}
    }

    // ── تطبيق أنماط Regex على نص خام وإضافة كل تطابق كمورد ──────────────────
    function scanText(text) {
        if (!text) return;
        PATTERNS.forEach(p => {
            let m;
            p.re.lastIndex = 0;
            while ((m = p.re.exec(text)) !== null) {
                addResource(m[0], p.type, 'فحص');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (3) الاستماع لرسائل postMessage القادمة من الصفحة/الإطارات
    // ═══════════════════════════════════════════════════════════════════════
    window.addEventListener('message', function (ev) {
        try {
            let payload = ev.data;
            if (typeof payload === 'object') payload = JSON.stringify(payload);
            if (typeof payload === 'string') scanText(payload);
        } catch (e) {}
    });

    // ═══════════════════════════════════════════════════════════════════════
    //  أدوات مساعدة للروابط
    // ═══════════════════════════════════════════════════════════════════════
    function guessType(url) {
        const u = (url || '').toLowerCase();
        if (u.includes('.m3u8')) return 'm3u8';
        if (u.includes('.mpd'))  return 'mpd';
        if (u.includes('.webm')) return 'webm';
        if (u.includes('.flv'))  return 'flv';
        if (u.includes('.mp4'))  return 'mp4';
        if (u.includes('.ts'))   return 'ts';
        return 'stream';
    }
    // تحويل رابط نسبي إلى مطلق اعتماداً على رابط الأساس
    function absolutize(link, base) {
        try { return new URL(link, base).href; } catch (e) { return link; }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (4) إضافة مورد وعرضه كبطاقة
    // ═══════════════════════════════════════════════════════════════════════
    function addResource(url, type, source) {
        if (!url || !/^https?:\/\//i.test(url)) return;
        if (state.resources.has(url)) return;   // منع التكرار

        const index = state.resources.size + 1;
        const res = { name: 'مورد ' + index, url, type: type || guessType(url), source };
        state.resources.set(url, res);
        renderResource(res, index);
        $('resCount').textContent = state.resources.size;

        // إخفاء رسالة الحالة الفارغة
        const empty = $('emptyState');
        if (empty) empty.style.display = 'none';
    }

    // ── رسم بطاقة المورد في القائمة ─────────────────────────────────────────
    function renderResource(res, index) {
        const list = $('resourceList');
        const card = document.createElement('div');
        card.className = 'resource-card type-' + res.type;

        // شارة النوع الملونة
        const badgeClass = 'type-badge badge-' + res.type;

        card.innerHTML = `
            <div class="res-head">
                <span class="res-num">#${index}</span>
                <span class="res-name">${escapeHtml(res.name)}</span>
                <span class="${badgeClass}">${res.type.toUpperCase()}</span>
                <span class="res-source">${escapeHtml(res.source || '')}</span>
            </div>
            <div class="res-url" title="${escapeHtml(res.url)}">${escapeHtml(res.url)}</div>
            <div class="res-actions">
                <button class="btn btn-sm btn-primary act-play">▶ Load &amp; Play</button>
                <button class="btn btn-sm btn-ghost act-copy">📋 نسخ</button>
            </div>
        `;
        // ربط الأحداث برمجياً (أأمن من onclick المضمّن)
        card.querySelector('.act-play').addEventListener('click', () => loadAndPlay(res.url, res.type));
        card.querySelector('.act-copy').addEventListener('click', (e) => copyUrl(res.url, e.currentTarget));
        list.appendChild(card);
    }

    // ── نسخ الرابط إلى الحافظة ──────────────────────────────────────────────
    function copyUrl(url, btn) {
        const done = () => { const t = btn.textContent; btn.textContent = '✓ تم النسخ';
                             setTimeout(() => btn.textContent = t, 1500); };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(done).catch(() => fallbackCopy(url, done));
        } else { fallbackCopy(url, done); }
    }
    function fallbackCopy(text, cb) {
        const ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); cb && cb(); } catch (e) {}
        document.body.removeChild(ta);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (5) تشغيل المورد
    // ═══════════════════════════════════════════════════════════════════════
    function loadAndPlay(url, type) {
        cleanupPlayer();
        state.currentUrl = url;
        state.reconnectAttempts = 0;
        type = type || guessType(url);
        setType(type.toUpperCase());
        setStatus('جارٍ التحميل…', 'pill-loading');
        highlightActive(url);

        if (type === 'm3u8')      playHls(url);
        else if (type === 'mpd')  playDash(url);
        else                      playNative(url);   // mp4 / ts / webm / flv
    }

    // إبراز البطاقة قيد التشغيل حالياً
    function highlightActive(url) {
        document.querySelectorAll('.resource-card').forEach(c => c.classList.remove('playing'));
        document.querySelectorAll('.resource-card').forEach(c => {
            const u = c.querySelector('.res-url');
            if (u && u.getAttribute('title') === url) c.classList.add('playing');
        });
    }

    // ── تشغيل HLS ───────────────────────────────────────────────────────────
    function playHls(url) {
        if (window.Hls && Hls.isSupported()) {
            state.hls = new Hls({ lowLatencyMode: true, backBufferLength: 30 });
            state.hls.loadSource(url);
            state.hls.attachMedia(video());
            state.hls.on(Hls.Events.MANIFEST_PARSED, () => {
                setStatus('يبث الآن', 'pill-live');
                state.reconnectAttempts = 0;
                video().play().catch(() => {});
            });
            state.hls.on(Hls.Events.ERROR, (_e, data) => {
                if (data.fatal) {
                    if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        try { state.hls.recoverMediaError(); } catch (e) { scheduleReconnect(); }
                    } else {
                        scheduleReconnect();
                    }
                }
            });
        } else if (video().canPlayType('application/vnd.apple.mpegurl')) {
            video().src = url;
            video().play().catch(() => {});
            setStatus('يبث الآن', 'pill-live');
        } else {
            setStatus('HLS غير مدعوم', 'pill-error');
        }
    }

    // ── تشغيل DASH ──────────────────────────────────────────────────────────
    function playDash(url) {
        if (!window.dashjs) { setStatus('DASH غير محمّل', 'pill-error'); return; }
        state.dashPlayer = dashjs.MediaPlayer().create();
        state.dashPlayer.updateSettings({ streaming: { lowLatencyEnabled: true } });
        state.dashPlayer.initialize(video(), url, true);
        state.dashPlayer.on(dashjs.MediaPlayer.events.STREAM_INITIALIZED, () => {
            setStatus('يبث الآن', 'pill-live');
            state.reconnectAttempts = 0;
        });
        state.dashPlayer.on(dashjs.MediaPlayer.events.ERROR, scheduleReconnect);
    }

    // ── تشغيل native (mp4/ts/webm) ─────────────────────────────────────────
    function playNative(url) {
        const v = video();
        v.src = url;
        v.onloadedmetadata = () => { setStatus('يبث الآن', 'pill-live'); v.play().catch(() => {}); };
        v.onerror = () => scheduleReconnect();
    }

    // ── إعادة الاتصال بتأخير متصاعد حتى 5 محاولات ──────────────────────────
    function scheduleReconnect() {
        if (state.reconnectAttempts >= 5) {
            setStatus('تعذّر التشغيل بعد 5 محاولات', 'pill-error');
            return;
        }
        state.reconnectAttempts++;
        const delay = 3000 * state.reconnectAttempts;   // 3s × عدد المحاولات
        setStatus(`إعادة المحاولة ${state.reconnectAttempts} خلال ${delay/1000}ث…`, 'pill-loading');
        if (state.reconnectTimer) clearTimeout(state.reconnectTimer);
        state.reconnectTimer = setTimeout(() => {
            if (state.currentUrl) loadAndPlay(state.currentUrl);
        }, delay);
    }

    // ── تنظيف المشغّل قبل تشغيل جديد ────────────────────────────────────────
    function cleanupPlayer() {
        if (state.hls) { try { state.hls.destroy(); } catch (e) {} state.hls = null; }
        if (state.dashPlayer) { try { state.dashPlayer.reset(); } catch (e) {} state.dashPlayer = null; }
        if (state.reconnectTimer) { clearTimeout(state.reconnectTimer); state.reconnectTimer = null; }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  (6) مسح النتائج
    // ═══════════════════════════════════════════════════════════════════════
    function clear() {
        state.resources.clear();
        cleanupPlayer();
        if (state.scanTimer) { clearInterval(state.scanTimer); state.scanTimer = null; }
        showScanning(false);
        $('resourceList').innerHTML =
            '<div class="empty-state" id="emptyState">لا توجد موارد بعد — اضغط «فحص» لبدء الاستكشاف.</div>';
        $('resCount').textContent = '0';
        setStatus('في الانتظار', 'pill-idle');
        setType('—');
    }

    // ── هروب HTML لمنع حقن الشيفرة عند عرض الروابط ──────────────────────────
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // ── الواجهة العامة ──────────────────────────────────────────────────────
    return { start, clear, loadAndPlay };
})();
