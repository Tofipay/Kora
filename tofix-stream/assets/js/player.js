/*
 * assets/js/player.js
 * -----------------------------------------------------------------------------
 * تهيئة محرّك التشغيل المختار (Video.js / Hls.js / Shaka) وربط أزرار التحكّم:
 * Picture-in-Picture، ملء الشاشة، السرعة، و Chromecast (عند توفّره).
 */

'use strict';

const { engine, src, isDash, isTs } = window.PLAYER;
const video = document.getElementById('player');

/* بثّ ts مباشر: يُشغَّل عبر mpegts.js (hls.js لا يدعم TS المستمرّ). */
function initMpegTs() {
  if (window.mpegts && mpegts.isSupported()) {
    const player = mpegts.createPlayer(
      { type: 'mpegts', isLive: true, url: src },
      { enableStashBuffer: false, liveBufferLatencyChasing: true }
    );
    player.attachMediaElement(video);
    player.load();
    player.play().catch(() => {});
    player.on(mpegts.Events.ERROR, () =>
      showError('تعذّر تشغيل بثّ TS المباشر. تأكّد أن الرابط يعمل (زرّ اختبار المصدر).'));
    window._mpegts = player;
  } else {
    showError('متصفّحك لا يدعم تشغيل بثّ TS المباشر.');
  }
}

/* ---------------- تهيئة المحرّك ---------------- */
function initVideoJs() {
  const player = videojs('player', {
    autoplay: true,
    liveui: true,
    responsive: true,
    fluid: true,
    html5: { vhs: { overrideNative: true } },
    playbackRates: [0.5, 1, 1.25, 1.5, 2],
  });
  player.src({ src, type: isDash ? 'application/dash+xml' : 'application/x-mpegURL' });
  player.ready(() => player.play().catch(() => {}));
  window._vjs = player;
}

function initHlsJs() {
  if (isDash) return initShaka(); // Hls.js لا يدعم DASH.
  if (window.Hls && Hls.isSupported()) {
    const hls = new Hls({
      lowLatencyMode: false,       // أكثر استقرارًا لبثّ IPTV.
      backBufferLength: 30,
      manifestLoadingTimeOut: 20000,
      manifestLoadingMaxRetry: 4,
      levelLoadingMaxRetry: 4,
      fragLoadingMaxRetry: 6,      // مقاطع IPTV قد تتأخّر — نعيد المحاولة.
      fragLoadingTimeOut: 30000,
    });
    window._hls = hls;
    hls.loadSource(src);
    hls.attachMedia(video);
    hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));

    // استرداد تلقائي محدود المحاولات (حتى لا يُرهق المصدر عند خطأ دائم).
    let netRetry = 0, mediaRetry = 0;
    hls.on(Hls.Events.ERROR, (_evt, data) => {
      if (!data.fatal) return;
      switch (data.type) {
        case Hls.ErrorTypes.NETWORK_ERROR:
          if (netRetry++ < 4) {
            console.warn('HLS network error — retrying…', data.details);
            setTimeout(() => hls.startLoad(), 1000);   // تمهّل قبل إعادة المحاولة.
          } else {
            showError('تعذّر تحميل البثّ من المصدر. تأكّد أن الرابط يعمل (زرّ اختبار المصدر).');
            hls.destroy();
          }
          break;
        case Hls.ErrorTypes.MEDIA_ERROR:
          if (mediaRetry++ < 3) {
            console.warn('HLS media error — recovering…', data.details);
            hls.recoverMediaError();
          } else {
            showError('تعذّر فكّ ترميز الفيديو في هذا المتصفّح (قد يكون الترميز غير مدعوم). جرّب متصفّحًا آخر.');
            hls.destroy();
          }
          break;
        default:
          showError('تعذّر تشغيل البثّ. تأكّد أن رابط المصدر يعمل (استخدم زرّ اختبار المصدر في اللوحة).');
          hls.destroy();
      }
    });
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = src; // Safari/iOS يشغّل HLS أصلًا.
    video.play().catch(() => {});
  } else {
    showError('متصفّحك لا يدعم HLS.');
  }
}

/* عرض رسالة خطأ واضحة فوق الفيديو. */
function showError(msg) {
  let el = document.getElementById('playerError');
  if (!el) {
    el = document.createElement('div');
    el.id = 'playerError';
    el.style.cssText = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;'
      + 'text-align:center;padding:24px;color:#fff;background:rgba(0,0,0,.75);font-family:sans-serif;z-index:9';
    document.querySelector('.video-frame')?.appendChild(el);
  }
  el.textContent = msg;
}

async function initShaka() {
  shaka.polyfill.installAll();
  if (!shaka.Player.isBrowserSupported()) return initHlsJs();
  const player = new shaka.Player(video);
  try {
    await player.load(src);
    video.play().catch(() => {});
  } catch (e) { console.error('Shaka error', e); }
  window._shaka = player;
}

// بثّ ts المباشر يتجاوز اختيار المحرّك ويستخدم mpegts.js دائمًا.
if (isTs) {
  initMpegTs();
} else {
  switch (engine) {
    case 'hlsjs': initHlsJs(); break;
    case 'shaka': initShaka(); break;
    default: initVideoJs();
  }
}

/* ---------------- أزرار التحكّم ---------------- */
document.getElementById('pipBtn')?.addEventListener('click', async () => {
  try {
    if (document.pictureInPictureElement) await document.exitPictureInPicture();
    else await video.requestPictureInPicture();
  } catch { alert('المتصفّح لا يدعم Picture-in-Picture'); }
});

document.getElementById('fsBtn')?.addEventListener('click', () => {
  const el = document.querySelector('.video-frame');
  if (document.fullscreenElement) document.exitFullscreen();
  else el.requestFullscreen?.();
});

document.getElementById('speedSel')?.addEventListener('change', (e) => {
  const rate = parseFloat(e.target.value);
  video.playbackRate = rate;
  if (window._vjs) window._vjs.playbackRate(rate);
});

/* Chromecast (Google Cast SDK يُحمّل عند توفّره فقط) */
document.getElementById('castBtn')?.addEventListener('click', () => {
  if (window.cast && window.chrome?.cast) {
    const context = cast.framework.CastContext.getInstance();
    context.requestSession().catch(() => {});
  } else {
    alert('Chromecast غير متاح في هذا المتصفّح. استخدم Google Chrome.');
  }
});
