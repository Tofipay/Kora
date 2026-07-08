/* Qamhad Live — frontend runtime (no framework, ~6KB gzipped) */
(function () {
  'use strict';
  const Q = window.QAMHAD || { lang: 'ar', prefix: '', t: {}, fcm: {} };
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---------------- Theme ---------------- */
  const root = document.documentElement;
  function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', dark);
  }
  const savedTheme = localStorage.getItem('q-theme') || root.getAttribute('data-theme') || 'auto';
  applyTheme(savedTheme);
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if ((localStorage.getItem('q-theme') || 'auto') === 'auto') applyTheme('auto');
  });
  $('#theme-toggle') && $('#theme-toggle').addEventListener('click', () => {
    const next = root.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('q-theme', next);
    applyTheme(next);
  });

  /* ---------------- Toast ---------------- */
  let toastTimer;
  window.QToast = function (msg) {
    const el = $('#toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
  };

  /* ---------------- Share ---------------- */
  window.QShare = function () {
    const data = { title: document.title, url: location.href };
    if (navigator.share) { navigator.share(data).catch(() => {}); return; }
    navigator.clipboard && navigator.clipboard.writeText(location.href).then(() => QToast(Q.t.copied || 'Copied'));
  };

  /* ---------------- Local 12-hour time correction ---------------- */
  function fmt12(ts) {
    const d = new Date(ts * 1000);
    let h = d.getHours();
    const m = String(d.getMinutes()).padStart(2, '0');
    const pm = h >= 12;
    h = h % 12 || 12;
    return String(h).padStart(2, '0') + ':' + m + ' ' + (pm ? (Q.t.pm || 'PM') : (Q.t.am || 'AM'));
  }
  $$('[data-ts]').forEach(el => { const ts = +el.getAttribute('data-ts'); if (ts > 0) el.textContent = fmt12(ts); });
  $$('[data-ts-inline]').forEach(el => { const ts = +el.getAttribute('data-ts-inline'); if (ts > 0) el.textContent = fmt12(ts); });

  /* ---------------- Countdown ---------------- */
  const cd = $('[data-countdown]');
  if (cd) {
    const target = +cd.getAttribute('data-countdown') * 1000;
    const cells = { d: $('[data-cd="d"]', cd), h: $('[data-cd="h"]', cd), m: $('[data-cd="m"]', cd), s: $('[data-cd="s"]', cd) };
    const tick = () => {
      let diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
      const d = Math.floor(diff / 86400); diff %= 86400;
      const h = Math.floor(diff / 3600); diff %= 3600;
      const m = Math.floor(diff / 60), s = diff % 60;
      cells.d.textContent = String(d).padStart(2, '0');
      cells.h.textContent = String(h).padStart(2, '0');
      cells.m.textContent = String(m).padStart(2, '0');
      cells.s.textContent = String(s).padStart(2, '0');
      if (target - Date.now() < -60000) location.reload();
    };
    tick();
    setInterval(tick, 1000);
  }

  /* ---------------- Tabs ---------------- */
  $$('.tabs').forEach(tabs => {
    tabs.addEventListener('click', e => {
      const btn = e.target.closest('.tab');
      if (!btn) return;
      const name = btn.getAttribute('data-tab');
      $$('.tab', tabs).forEach(t => t.classList.toggle('active', t === btn));
      $$('.tab-panel').forEach(p => p.classList.toggle('active', p.getAttribute('data-panel') === name));
      history.replaceState(null, '', '#' + name);
    });
  });
  if (location.hash) {
    const btn = $('.tab[data-tab="' + location.hash.slice(1) + '"]');
    if (btn) btn.click();
  }
  /* events filter segments */
  $$('[data-events-filter]').forEach(seg => {
    seg.addEventListener('click', () => {
      const val = seg.getAttribute('data-events-filter');
      $$('[data-events-filter]').forEach(s => s.classList.toggle('active', s === seg));
      $$('[data-events-view]').forEach(v => v.hidden = v.getAttribute('data-events-view') !== val);
    });
  });

  /* "Show more" reveal (videos grid etc.): un-hide the targeted items once */
  $$('[data-show-more]').forEach(btn => {
    btn.addEventListener('click', () => {
      $$(btn.getAttribute('data-show-more')).forEach(el => el.hidden = false);
      const wrap = btn.closest('.show-more-wrap');
      (wrap || btn).remove();
    });
  });
  /* In-page links that jump to a tab (e.g. overview highlights → videos) */
  $$('[data-goto-tab]').forEach(a => {
    a.addEventListener('click', e => {
      const btn = $('.tab[data-tab="' + a.getAttribute('data-goto-tab') + '"]');
      if (btn) { e.preventDefault(); btn.click(); btn.scrollIntoView({ block: 'nearest' }); }
    });
  });

  /* ---------------- Player stat rings (draw + count up) ---------------- */
  function animateRings(root) {
    (root || document).querySelectorAll('.rc-fg[data-target]').forEach(fg => {
      requestAnimationFrame(() => { fg.style.strokeDashoffset = fg.getAttribute('data-target'); });
    });
    (root || document).querySelectorAll('[data-count]').forEach(el => {
      const target = +el.getAttribute('data-count') || 0;
      if (target === 0) { el.textContent = '0'; return; }
      // Value already rendered server-side (progressive enhancement): leave it
      // as-is so it never flashes back to 0 when the enhancement JS runs.
      if (el.textContent.trim() === String(target)) return;
      const dur = 900, t0 = performance.now();
      const step = now => {
        const p = Math.min((now - t0) / dur, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toString();
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }
  if ($('.stat-rings')) {
    if ('IntersectionObserver' in window) {
      const rio = new IntersectionObserver((es, o) => {
        es.forEach(en => { if (en.isIntersecting) { animateRings(en.target); o.unobserve(en.target); } });
      }, { threshold: .3 });
      rio.observe($('.stat-rings'));
    } else animateRings();
  }

  /* ---------------- Player competition tabs ---------------- */
  $$('[data-comp-tabs]').forEach(bar => {
    bar.addEventListener('click', e => {
      const pill = e.target.closest('.cs-pill');
      if (!pill) return;
      const id = pill.getAttribute('data-comp');
      $$('.cs-pill', bar).forEach(p => p.classList.toggle('active', p === pill));
      $$('.comp-panel').forEach(p => p.classList.toggle('active', p.getAttribute('data-comp-panel') === id));
    });
  });
  // Draw all competition panels' rings/counts up-front (hidden ones included)
  // so switching tabs shows finished visuals instantly.
  if ($('.stats-section')) animateRings($('.stats-section'));

  /* ---------------- Reveal on scroll ---------------- */
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    $$('.reveal').forEach(el => io.observe(el));
  } else {
    $$('.reveal').forEach(el => el.classList.add('in'));
  }

  /* ---------------- Date jump ---------------- */
  const dj = $('#date-jump');
  if (dj) dj.addEventListener('change', () => { if (dj.value) location.href = Q.prefix + '/matches/' + dj.value; });

  /* ---------------- Favorites (localStorage) ---------------- */
  const FKEY = 'q-favs-v1';
  const favs = () => { try { return JSON.parse(localStorage.getItem(FKEY)) || {}; } catch (e) { return {}; } };
  const saveFavs = f => localStorage.setItem(FKEY, JSON.stringify(f));
  window.QF = {
    toggle(btn) {
      const kind = btn.getAttribute('data-fav'), id = btn.getAttribute('data-id');
      const f = favs();
      f[kind] = f[kind] || {};
      if (f[kind][id]) { delete f[kind][id]; btn.classList.remove('is-fav'); }
      else {
        f[kind][id] = { id, title: btn.getAttribute('data-title') || '', url: btn.getAttribute('data-url') || '', img: btn.getAttribute('data-img') || '' };
        btn.classList.add('is-fav');
      }
      saveFavs(f);
    },
    mark() {
      const f = favs();
      $$('[data-fav]').forEach(btn => {
        const kind = btn.getAttribute('data-fav'), id = btn.getAttribute('data-id');
        btn.classList.toggle('is-fav', !!(f[kind] && f[kind][id]));
      });
    }
  };
  QF.mark();

  /* Favorites page render */
  const favRoot = $('#favorites-root');
  if (favRoot) {
    const f = favs();
    const groups = [['team', favRoot.dataset.l10nTeams], ['league', favRoot.dataset.l10nLeagues], ['match', favRoot.dataset.l10nMatches]];
    let html = '', any = false;
    groups.forEach(([kind, label]) => {
      const items = Object.values(f[kind] || {});
      if (!items.length) return;
      any = true;
      html += '<section class="fav-group"><h2>' + label + '</h2><div class="fav-items">';
      items.forEach(it => {
        html += '<a class="fav-item card-hover" href="' + it.url + '">' +
          (it.img ? '<img src="' + it.img + '" alt="" loading="lazy">' : '') +
          '<span>' + (it.title || '').replace(/</g, '&lt;') + '</span>' +
          '<button class="fav-x" data-k="' + kind + '" data-i="' + it.id + '" aria-label="remove">✕</button></a>';
      });
      html += '</div></section>';
    });
    favRoot.innerHTML = any ? html : '<div class="empty-state glass-soft"><p>' + favRoot.dataset.l10nEmpty + '</p></div>';
    favRoot.addEventListener('click', e => {
      const x = e.target.closest('.fav-x');
      if (!x) return;
      e.preventDefault();
      const f2 = favs();
      if (f2[x.dataset.k]) { delete f2[x.dataset.k][x.dataset.i]; saveFavs(f2); }
      x.closest('.fav-item').remove();
    });
  }

  /* ---------------- Live match clock (mirrors PHP live_clock) ---------------- */
  function liveLabel(st, startTs) {
    if (st === 2) return { label: Q.t.ht || 'HT', progress: 0.5 };
    if (st === 7 || st === 8 || st === 13) return { label: Q.t.pens || 'PSO', progress: 1 };
    const bases = { 1: [0, 45], 3: [45, 90], 5: [90, 105], 6: [105, 120] };
    const bc = bases[st] || [0, 45];
    if (!startTs) return null; // no period-start ts — keep the server label
    const minute = bc[0] + Math.floor(Math.max(0, Date.now() / 1000 - startTs) / 60) + 1;
    const label = minute > bc[1] ? bc[1] + '+' + (minute - bc[1]) + '′' : minute + '′';
    return { label, progress: Math.min(minute / 90, 1) };
  }
  function tickLiveClocks() {
    $$('[data-ls]').forEach(el => {
      const st = +el.getAttribute('data-ls');
      const ts = +el.getAttribute('data-lt') || 0;
      const r = liveLabel(st, ts);
      if (!r) return;
      el.textContent = r.label;
      const ring = el.closest('.live-ring');
      if (ring) {
        const fg = ring.querySelector('.ring-fg');
        if (fg) {
          const circ = parseFloat(fg.getAttribute('data-ring')) || 188.5;
          fg.style.strokeDashoffset = (circ * (1 - r.progress)).toFixed(2);
        }
      }
    });
  }
  tickLiveClocks();
  setInterval(tickLiveClocks, 20000);

  /* ---------------- Live score polling ---------------- */
  const liveCards = $$('[data-match]');
  const hasLive = liveCards.some(c => c.getAttribute('data-state') === 'live') || $('[data-match-page]') || $$('.lc-minute').length;
  function applyScore(el, m) {
    const hs = $('[data-hs]', el), as = $('[data-as]', el), st = $('[data-status]', el);
    if (hs && +hs.textContent !== m.hs) { hs.textContent = m.hs; hs.classList.remove('score-flash'); void hs.offsetWidth; hs.classList.add('score-flash'); }
    if (as && +as.textContent !== m.as) { as.textContent = m.as; as.classList.remove('score-flash'); void as.offsetWidth; as.classList.add('score-flash'); }
    if (st && m.state === 'live') {
      st.textContent = m.label;
      st.className = st.className.replace('is-soon', 'is-live').replace('is-ft', 'is-live');
      if (m.st !== undefined) { st.setAttribute('data-ls', m.st); st.setAttribute('data-lt', m.ps || 0); }
    }
    if (st && m.state === 'finished') {
      st.textContent = Q.t.ft || 'FT';
      st.className = st.className.replace('is-live', 'is-ft');
      st.removeAttribute('data-ls'); st.removeAttribute('data-lt');
      const ring = st.closest('.live-ring');
      if (ring) ring.classList.add('ring-done');
    }
    if (st && m.state === 'live') tickLiveClocks();
  }
  async function poll() {
    try {
      const res = await fetch('/api/live-scores', { headers: { 'Accept': 'application/json' } });
      if (!res.ok) return;
      const data = await res.json();
      const byId = {};
      (data.matches || []).forEach(m => byId[m.id] = m);
      $$('[data-match]').forEach(el => { const m = byId[+el.getAttribute('data-match')]; if (m) applyScore(el, m); });
      const page = $('[data-match-page]');
      if (page) { const m = byId[+page.getAttribute('data-match-page')]; if (m) applyScore(page, m); }
    } catch (e) { /* offline — ignore */ }
  }
  if (hasLive) { setInterval(poll, 60000); }

  /* ---------------- Newsletter ---------------- */
  const nl = $('#newsletter-form');
  if (nl) nl.addEventListener('submit', async e => {
    e.preventDefault();
    const email = nl.email.value.trim();
    if (!email) return;
    try {
      const res = await fetch('/api/newsletter', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });
      if (res.ok) { QToast(nl.closest('.newsletter') ? (document.documentElement.lang === 'ar' ? 'تم الاشتراك بنجاح!' : 'Subscribed!') : 'OK'); nl.reset(); }
    } catch (err) { /* ignore */ }
  });

  /* ---------------- PWA install ---------------- */
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    const b = $('#install-btn');
    if (b) b.hidden = false;
  });
  $('#install-btn') && $('#install-btn').addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    $('#install-btn').hidden = true;
  });

  /* ---------------- Service worker ---------------- */
  if ('serviceWorker' in navigator) {
    addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }

  /* ---------------- Push notifications (FCM) ---------------- */
  const notifyBtn = $('#notify-btn');
  if (notifyBtn) {
    if (!('Notification' in window) || !Q.fcm || !Q.fcm.apiKey) {
      notifyBtn.addEventListener('click', () => {
        Notification && Notification.requestPermission && Notification.requestPermission();
        QToast(document.documentElement.lang === 'ar' ? 'الإشعارات غير مهيأة بعد' : 'Notifications not configured yet');
      });
    } else {
      notifyBtn.addEventListener('click', async () => {
        try {
          const perm = await Notification.requestPermission();
          if (perm !== 'granted') return;
          const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js');
          const { getMessaging, getToken } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging.js');
          const app = initializeApp(Q.fcm);
          const messaging = getMessaging(app);
          const reg = await navigator.serviceWorker.ready;
          const token = await getToken(messaging, { vapidKey: Q.fcm.vapidKey, serviceWorkerRegistration: reg });
          if (token) {
            await fetch('/api/push-subscribe', {
              method: 'POST', headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ token, topics: ['all'] })
            });
            QToast(document.documentElement.lang === 'ar' ? 'تم تفعيل الإشعارات ✓' : 'Notifications enabled ✓');
          }
        } catch (err) { QToast(document.documentElement.lang === 'ar' ? 'تعذر تفعيل الإشعارات' : 'Could not enable notifications'); }
      });
    }
  }
})();
