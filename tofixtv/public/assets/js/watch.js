/* ALOKA Live — internal video player
 * HLS (hls.js) + DASH (dash.js) + native MP4/WebM/MKV, instant server switch,
 * quality/speed/PiP/Chromecast/AirPlay/fullscreen, error recovery. */
(function () {
  'use strict';
  const shell = document.getElementById('qplayer');
  if (!shell) return;
  const video = document.getElementById('qvideo');
  const $ = (s) => shell.querySelector(s);
  const servers = JSON.parse(shell.getAttribute('data-servers') || '[]');
  const HLS_SRC = shell.getAttribute('data-hls');
  const DASH_SRC = shell.getAttribute('data-dash');
  if (!servers.length) return;

  const els = {
    loading: $('[data-loading]'), error: $('[data-error]'), errorMsg: $('[data-error-msg]'),
    controls: $('[data-controls]'), bigplay: $('[data-bigplay]'), toggle: $('[data-toggle]'),
    icPlay: $('.ic-play'), icPause: $('.ic-pause'), mute: $('[data-mute]'), icVol: $('.ic-vol'),
    icMute: $('.ic-mute'), volbar: $('[data-volbar]'), live: $('[data-live]'), time: $('[data-time]'),
    qMenu: $('[data-menu="quality"]'), qList: $('[data-menu-list]'), qBtn: $('[data-menu="quality"] [data-menu-btn]'),
    sBtn: $('[data-menu="speed"] [data-menu-btn]'), cast: $('[data-cast]'), airplay: $('[data-airplay]'),
    pip: $('[data-pip]'), fs: $('[data-fs]'), retry: $('[data-retry]'), stage: $('.pl-stage'),
  };

  let hls = null, dash = null, current = -1, scriptCache = {}, recovery = 0;
  // When present (Yacine player), the stream link expires and must be renewed;
  // this endpoint returns fresh source URLs so we reconnect the SAME server.
  const REFRESH_URL = shell.getAttribute('data-refresh') || '';
  let reloadTimer = null, reloadAttempts = 0, proactiveTimer = null;
  let TTL = parseInt(shell.getAttribute('data-ttl') || '0', 10) || 0; // seconds until link expiry

  function loadScript(src) {
    if (scriptCache[src]) return scriptCache[src];
    scriptCache[src] = new Promise((res, rej) => {
      const s = document.createElement('script');
      s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s);
    });
    return scriptCache[src];
  }

  // ---- overlay state machine (loading / bigplay / error are exclusive) ----
  let pendingErr = null, lastProgressAt = 0;
  function isAdvancing() { return performance.now() - lastProgressAt < 2500; } // clock moved recently

  function showLoading(v) { if (v) { hideError(); els.bigplay.hidden = true; } els.loading.hidden = !v; }
  function hideError() { els.error.hidden = true; clearTimeout(pendingErr); pendingErr = null; }
  function reallyShowError(msg) {
    if (isAdvancing()) return;            // stream is actually playing — never cover it
    els.error.hidden = false; els.loading.hidden = true; els.bigplay.hidden = true;
    if (msg) els.errorMsg.textContent = msg;
  }
  function showError(msg) {
    // Defer: if the stream starts (or keeps) progressing within a moment, skip it.
    clearTimeout(pendingErr);
    pendingErr = setTimeout(() => reallyShowError(msg), 1400);
  }
  // Any confirmed playback progress clears every overlay.
  function markAlive() {
    lastProgressAt = performance.now();
    els.loading.hidden = true; els.bigplay.hidden = true; hideError(); recovery = 0;
    reloadAttempts = 0; clearTimeout(reloadTimer); // working stream → reset reconnect backoff
    // NOTE: no periodic/proactive reload here — reloading a healthy stream is
    // what caused the repeated stutter. We only reconnect REACTIVELY, on a real
    // failure (see onFatal / the stall watchdog).
  }

  function destroy() {
    if (hls) { try { hls.destroy(); } catch (e) {} hls = null; }
    if (dash) { try { dash.reset(); } catch (e) {} dash = null; }
    video.removeAttribute('src'); video.load();
    els.qMenu.hidden = true; els.qList.innerHTML = '';
  }

  async function play(index) {
    if (index < 0 || index >= servers.length) return;
    current = index;
    clearTimeout(proactiveTimer); proactiveTimer = null; // re-armed on next markAlive
    hideError(); showLoading(true); destroy();
    shell.querySelectorAll('[data-server]').forEach(b =>
      b.classList.toggle('on', +b.getAttribute('data-server') === index));

    const srv = servers[index];
    const type = (srv.type || 'auto').toLowerCase();
    const url = srv.url;
    const canNativeHls = video.canPlayType('application/vnd.apple.mpegurl');

    try {
      if (type === 'm3u8' || type === 'ts') {
        if (canNativeHls) { video.src = url; }
        else {
          await loadScript(HLS_SRC);
          if (!window.Hls || !window.Hls.isSupported()) throw new Error('HLS unsupported');
          hls = new window.Hls({
            lowLatencyMode: false,
            // ---- Large, stable buffer like a TV app: absorbs proxy/CDN jitter
            // so brief slow segments never drain the buffer into a stall. ----
            maxBufferLength: 60,                 // aim ~60s ahead
            maxMaxBufferLength: 600,
            maxBufferSize: 120 * 1000 * 1000,    // 120 MB
            backBufferLength: 60,
            // Sit ~a few segments behind the live edge so there is always a
            // cushion (low sync count = tiny buffer = the stutter you saw).
            liveSyncDurationCount: 6,
            liveMaxLatencyDurationCount: 30,
            liveDurationInfinity: true,
            // Generous, patient retries so a slow proxy fetch retries instead of
            // erroring — hls.js keeps playing from buffer meanwhile.
            fragLoadingMaxRetry: 20,
            fragLoadingRetryDelay: 500,
            fragLoadingMaxRetryTimeout: 64000,
            levelLoadingMaxRetry: 10,
            manifestLoadingMaxRetry: 6,
            // Don't over-correct small gaps; let the big buffer ride them out.
            maxBufferHole: 0.5,
            highBufferWatchdogPeriod: 3,
            nudgeMaxRetry: 8,
          });
          hls.loadSource(url); hls.attachMedia(video);
          hls.on(window.Hls.Events.MANIFEST_PARSED, () => { buildHlsQuality(); tryPlay(); });
          hls.on(window.Hls.Events.ERROR, onHlsError);
        }
      } else if (type === 'mpd' || type === 'ism' || type === 'isml') {
        await loadScript(DASH_SRC);
        if (!window.dashjs) throw new Error('DASH unsupported');
        dash = window.dashjs.MediaPlayer().create();
        // Large, stable buffer + patient retries for smooth DASH playback.
        dash.updateSettings({ streaming: {
          buffer: { fastSwitchEnabled: true, stableBufferTime: 40, bufferTimeAtTopQuality: 60, bufferToKeep: 60 },
          retryAttempts: { MediaSegment: 15, MPD: 6 },
          retryIntervals: { MediaSegment: 600 },
        } });
        const drm = srv.drm;
        if (drm && drm.clearkeys && Object.keys(drm.clearkeys).length) {
          // ClearKey DRM (e.g. beIN via Yacine): set the keys before the source.
          dash.initialize();
          dash.setProtectionData({ 'org.w3.clearkey': { clearkeys: drm.clearkeys } });
          dash.attachView(video);
          dash.setAutoPlay(true);
          dash.attachSource(url);
        } else {
          dash.initialize(video, url, true);
        }
        dash.on(window.dashjs.MediaPlayer.events.STREAM_INITIALIZED, buildDashQuality);
        dash.on(window.dashjs.MediaPlayer.events.ERROR, () => onFatal('DASH error'));
      } else if (type === 'rtsp') {
        onFatal(document.documentElement.lang === 'ar'
          ? 'هذا النوع (RTSP) لا يعمل في المتصفح — استخدم سيرفراً آخر.'
          : 'RTSP streams cannot play in a browser — pick another server.');
        return;
      } else { // mp4, webm, mkv, direct
        video.src = url;
      }
      if (!hls && !dash) tryPlay();
    } catch (e) { onFatal(e && e.message); }
  }

  function tryPlay() {
    showLoading(false);
    const p = video.play();
    if (p && p.catch) p.catch(() => { els.bigplay.hidden = false; }); // autoplay blocked → show big play
  }

  function onHlsError(_e, data) {
    if (!data || !data.fatal) return;  // let hls.js ride out non-fatal buffering
    if (window.Hls && data.type === window.Hls.ErrorTypes.NETWORK_ERROR && recovery < 6) {
      recovery++; setTimeout(() => hls && hls.startLoad(), 600 * recovery);
    } else if (window.Hls && data.type === window.Hls.ErrorTypes.MEDIA_ERROR && recovery < 4) {
      recovery++; try { hls.recoverMediaError(); } catch (e) { onFatal(); }
    } else { onFatal(); }
  }

  // Gently resume loading WITHOUT seeking to the live edge (seeking to the edge
  // throws away the buffer cushion and causes more stutter, not less).
  function resumeLoad() {
    try { if (hls) hls.startLoad(); } catch (e) {}
    const p = video.play(); if (p && p.catch) p.catch(() => {});
  }
  function onFatal(msg) {
    // Yacine links expire (~1 min). Instead of switching servers, renew the
    // link for the SAME server and restart it automatically, retrying with
    // backoff — so a live stream keeps playing for a long time on its own.
    if (REFRESH_URL) { scheduleReload(); return; }
    showError(msg || (document.documentElement.lang === 'ar'
      ? 'تعذّر تشغيل هذا السيرفر. جرّب سيرفراً آخر.'
      : 'This server failed. Try another one.'));
  }

  // Auto-reconnect the current server with a freshly-decrypted link.
  function scheduleReload() {
    if (!REFRESH_URL) { showError(); return; }
    clearTimeout(reloadTimer);
    showLoading(true);
    const delay = Math.min(1000 * Math.pow(1.6, Math.min(reloadAttempts, 8)), 6000);
    reloadAttempts++;
    reloadTimer = setTimeout(reloadCurrent, delay);
  }
  async function reloadCurrent() {
    try {
      const res = await fetch(REFRESH_URL, { cache: 'no-store' });
      const data = await res.json();
      if (data && data.ok && Array.isArray(data.sources) && data.sources.length) {
        for (let i = 0; i < servers.length && i < data.sources.length; i++) {
          servers[i].url = data.sources[i].url;
          servers[i].type = data.sources[i].type;
          servers[i].drm = data.sources[i].drm;
        }
        if (data.ttl) TTL = data.ttl;   // next renewal window
      }
    } catch (e) {}
    play(current < 0 ? 0 : current);   // replay the SAME server, fresh link
  }

  // Proactively renew the link a little BEFORE it expires, so playback keeps
  // going without waiting for a failure.
  function scheduleProactive() {
    if (proactiveTimer) return;                  // arm once per playback session
    if (REFRESH_URL && TTL > 0) {
      proactiveTimer = setTimeout(() => { proactiveTimer = null; reloadCurrent(); }, TTL * 1000);
    }
  }

  function buildHlsQuality() {
    if (!hls || !hls.levels || hls.levels.length < 2) return;
    els.qMenu.hidden = false;
    const levels = [{ i: -1, label: document.documentElement.lang === 'ar' ? 'تلقائي' : 'Auto' }]
      .concat(hls.levels.map((l, i) => ({ i, label: (l.height ? l.height + 'p' : Math.round(l.bitrate / 1000) + 'k') })));
    els.qList.innerHTML = '';
    levels.forEach(lv => {
      const b = document.createElement('button');
      b.textContent = lv.label; b.className = lv.i === -1 ? 'on' : '';
      b.onclick = () => {
        hls.currentLevel = lv.i;
        els.qBtn.textContent = lv.i === -1 ? 'HD' : lv.label;
        els.qList.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
        closeMenus();
      };
      els.qList.appendChild(b);
    });
  }
  function buildDashQuality() {
    showLoading(false);
    try {
      const reps = dash.getBitrateInfoListFor('video') || [];
      if (reps.length < 2) return;
      els.qMenu.hidden = false; els.qList.innerHTML = '';
      const mk = (label, fn, on) => { const b = document.createElement('button'); b.textContent = label; if (on) b.className = 'on';
        b.onclick = () => { fn(); els.qBtn.textContent = label === 'Auto' ? 'HD' : label; els.qList.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b)); closeMenus(); }; els.qList.appendChild(b); };
      mk('Auto', () => dash.updateSettings({ streaming: { abr: { autoSwitchBitrate: { video: true } } } }), true);
      reps.forEach(r => mk((r.height || '') + 'p', () => { dash.updateSettings({ streaming: { abr: { autoSwitchBitrate: { video: false } } } }); dash.setQualityFor('video', r.qualityIndex); }));
    } catch (e) {}
  }

  /* ---- controls ---- */
  function closeMenus() { shell.querySelectorAll('.pl-menu').forEach(m => m.classList.remove('open')); }
  shell.querySelectorAll('[data-menu-btn]').forEach(btn => btn.addEventListener('click', e => {
    e.stopPropagation(); const m = btn.closest('.pl-menu'); const open = m.classList.contains('open'); closeMenus(); if (!open) m.classList.add('open');
  }));
  document.addEventListener('click', closeMenus);

  function toggle() { video.paused ? video.play() : video.pause(); }
  els.toggle.addEventListener('click', toggle);
  els.bigplay.addEventListener('click', () => { els.bigplay.hidden = true; toggle(); });
  els.stage.addEventListener('click', e => { if (e.target === video) toggle(); });
  video.addEventListener('play', () => { els.icPlay.hidden = true; els.icPause.hidden = false; els.bigplay.hidden = true; });
  video.addEventListener('pause', () => {
    els.icPlay.hidden = false; els.icPause.hidden = true;
    // Show the big-play only on a genuine user pause (not a transient buffering stall).
    if (video.readyState > 2 && !video.ended && !video.seeking) els.bigplay.hidden = false;
  });
  video.addEventListener('waiting', () => { if (!isAdvancing()) showLoading(true); });
  video.addEventListener('canplay', () => showLoading(false));
  video.addEventListener('playing', markAlive);
  video.addEventListener('error', () => { if (!hls && !dash) onFatal(); });

  els.mute.addEventListener('click', () => { video.muted = !video.muted; els.icVol.hidden = video.muted; els.icMute.hidden = !video.muted; });
  els.volbar.addEventListener('input', () => { video.volume = +els.volbar.value; video.muted = video.volume === 0; els.icVol.hidden = video.muted; els.icMute.hidden = !video.muted; });

  // VOD shows time; live hides it
  video.addEventListener('durationchange', () => {
    const live = !isFinite(video.duration);
    els.live.hidden = !live; els.time.hidden = live;
  });
  // Watchdog: the media clock advancing is proof the stream is truly playing,
  // so clear ALL overlays (bug: play button + error card lingered over a
  // working stream). Fires ~4×/s while playing.
  let _wtLast = -1;
  video.addEventListener('timeupdate', () => {
    if (video.currentTime !== _wtLast) { _wtLast = video.currentTime; if (!video.paused) markAlive(); }
    if (!isFinite(video.duration)) return;
    const f = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
    els.time.textContent = f(video.currentTime) + ' / ' + f(video.duration);
  });

  shell.querySelectorAll('[data-speed]').forEach(b => b.addEventListener('click', () => {
    video.playbackRate = +b.getAttribute('data-speed'); els.sBtn.textContent = b.getAttribute('data-speed') + 'x';
    b.parentNode.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b)); closeMenus();
  }));

  els.pip.addEventListener('click', async () => {
    try { if (document.pictureInPictureElement) await document.exitPictureInPicture(); else await video.requestPictureInPicture(); } catch (e) {}
  });
  if (!document.pictureInPictureEnabled) els.pip.hidden = true;

  els.fs.addEventListener('click', () => {
    const el = shell;
    if (document.fullscreenElement) document.exitFullscreen();
    else if (el.requestFullscreen) el.requestFullscreen();
    else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen(); // iOS
  });

  // AirPlay (Safari)
  if (window.WebKitPlaybackTargetAvailabilityEvent) {
    video.addEventListener('webkitplaybacktargetavailabilitychanged', e => {
      els.airplay.hidden = e.availability !== 'available';
    });
    els.airplay.addEventListener('click', () => video.webkitShowPlaybackTargetPicker && video.webkitShowPlaybackTargetPicker());
  }

  // Chromecast
  window.__onGCastApiAvailable = function (ok) {
    if (!ok || !window.cast) return;
    try {
      cast.framework.CastContext.getInstance().setOptions({
        receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
        autoJoinPolicy: chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED,
      });
      els.cast.hidden = false;
      els.cast.addEventListener('click', () => {
        const ctx = cast.framework.CastContext.getInstance();
        ctx.requestSession().then(() => {
          const session = ctx.getCurrentSession(); if (!session) return;
          const media = new chrome.cast.media.MediaInfo(servers[current].url, 'application/x-mpegURL');
          session.loadMedia(new chrome.cast.media.LoadRequest(media));
        }).catch(() => {});
      });
    } catch (e) {}
  };
  loadScript('https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1').catch(() => {});

  els.retry.addEventListener('click', () => play(current));
  shell.querySelectorAll('[data-server]').forEach(b => b.addEventListener('click', () => {
    reloadAttempts = 0; clearTimeout(reloadTimer); // manual pick → fresh start
    play(+b.getAttribute('data-server'));
  }));

  // auto-hide controls
  let hideTimer;
  function poke() { shell.classList.remove('idle'); clearTimeout(hideTimer); hideTimer = setTimeout(() => { if (!video.paused) shell.classList.add('idle'); }, 2800); }
  ['mousemove', 'touchstart', 'click'].forEach(ev => shell.addEventListener(ev, poke));
  poke();

  // Start muted so autoplay is allowed everywhere (no lingering play button).
  video.muted = true; video.volume = 1;
  els.icVol.hidden = true; els.icMute.hidden = false; els.volbar.value = 1;

  // The first tap anywhere on the video unmutes it — so the user gets sound
  // without reaching for the volume button. That first tap is swallowed so it
  // doesn't also pause the video.
  els.stage.addEventListener('click', function _unmuteOnce(e) {
    els.stage.removeEventListener('click', _unmuteOnce, true);
    if (video.muted) {
      video.muted = false;
      els.icVol.hidden = false; els.icMute.hidden = true; els.volbar.value = video.volume || 1;
      e.stopPropagation();
    }
  }, true);

  // ---- Aspect ratio: Fit / Stretch / Zoom — switchable live, remembered ----
  const AR_MODES = ['fit', 'stretch', 'zoom'];
  const AR_AR = document.documentElement.lang === 'ar';
  const AR_LABELS = AR_AR
    ? { fit: 'احتواء الشاشة', stretch: 'تمديد', zoom: 'تكبير (قص الحواف)' }
    : { fit: 'Fit', stretch: 'Stretch', zoom: 'Zoom' };
  let arMode = 'fit';
  try { arMode = localStorage.getItem('q-aspect') || 'fit'; } catch (e) {}
  const arHint = shell.querySelector('[data-arhint]');
  let arHintTimer;
  function applyAspect(mode, announce) {
    if (AR_MODES.indexOf(mode) < 0) mode = 'fit';
    arMode = mode;
    video.classList.remove('ar-fit', 'ar-stretch', 'ar-zoom');
    video.classList.add('ar-' + mode);
    try { localStorage.setItem('q-aspect', mode); } catch (e) {}
    if (announce && arHint) {
      arHint.textContent = AR_LABELS[mode] || mode;
      arHint.hidden = false;
      clearTimeout(arHintTimer);
      arHintTimer = setTimeout(() => { arHint.hidden = true; }, 1100);
    }
  }
  const arBtn = shell.querySelector('[data-aspect]');
  if (arBtn) arBtn.addEventListener('click', () => {
    applyAspect(AR_MODES[(AR_MODES.indexOf(arMode) + 1) % AR_MODES.length], true);
  });
  applyAspect(arMode, false); // restore the saved mode on load

  // Stall watchdog — LAST RESORT only. hls.js's large buffer + patient retries
  // handle normal buffering; we intervene only on a genuinely long freeze, so
  // we don't cause the very stutter we're trying to avoid.
  let _swLast = -1, _swStalls = 0;
  setInterval(() => {
    if (video.paused || video.seeking || video.ended || video.readyState < 2) {
      _swLast = video.currentTime; _swStalls = 0; return;
    }
    if (Math.abs(video.currentTime - _swLast) < 0.01) {
      _swStalls++;
      if (_swStalls === 12) {
        resumeLoad();                         // ~12s frozen → resume loading (no seek)
      } else if (_swStalls >= 30) {
        _swStalls = 0;                        // ~30s frozen → renew link & restart
        if (REFRESH_URL) scheduleReload(); else resumeLoad();
      }
    } else { _swStalls = 0; }
    _swLast = video.currentTime;
  }, 1000);

  play(0);
})();
