/*
 * assets/js/player.js
 * -----------------------------------------------------------------------------
 * تهيئة محرّك التشغيل المختار (Video.js / Hls.js / Shaka) وربط أزرار التحكّم:
 * Picture-in-Picture، ملء الشاشة، السرعة، و Chromecast (عند توفّره).
 */

'use strict';

const { engine, src, isDash } = window.PLAYER;
const video = document.getElementById('player');

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
    const hls = new Hls({ lowLatencyMode: true, backBufferLength: 30 });
    hls.loadSource(src);
    hls.attachMedia(video);
    hls.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
  } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    video.src = src; // Safari الأصلي.
    video.play().catch(() => {});
  }
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

switch (engine) {
  case 'hlsjs': initHlsJs(); break;
  case 'shaka': initShaka(); break;
  default: initVideoJs();
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
