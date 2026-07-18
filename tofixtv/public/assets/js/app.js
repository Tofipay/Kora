/* ToFi X Tv — frontend runtime (no framework, ~8KB gzipped)
 *
 * Structure:
 *   1. One-time shell modules  — theme, toast, SW, push, delegated handlers,
 *      PJAX app-navigation, prefetch, image fallback. Run exactly once.
 *   2. initPage(scope)         — everything bound to page CONTENT. Re-run by
 *      the PJAX router after each content swap, so navigation feels like a
 *      native app (fixed header/bottom-nav, only <main> re-renders).
 */
(function () {
  'use strict';
  const Q = window.TOFIXTV || { lang: 'ar', prefix: '', t: {}, fcm: {} };
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---------------- Theme (dark is the DEFAULT) ----------------
   * The inline boot script in <head> already applied the class before CSS
   * loaded (no FOUC); this only wires the toggle + system-change listener. */
  const root = document.documentElement;
  function applyTheme(mode) {
    const dark = mode !== 'light'; // dark unless the user explicitly chose light
    root.classList.toggle('dark', dark);
    root.style.backgroundColor = dark ? '#0f172a' : '#ffffff';
    root.style.colorScheme = dark ? 'dark' : 'light';
  }
  applyTheme(localStorage.getItem('q-theme') || 'dark');
  $('#theme-toggle') && $('#theme-toggle').addEventListener('click', () => {
    const next = root.classList.contains('dark') ? 'light' : 'dark';
    localStorage.setItem('q-theme', next);
    applyTheme(next);
    syncThemeSeg();
  });
  /* Theme segments on the Settings & More page (delegated — PJAX safe). */
  function syncThemeSeg() {
    const cur = (localStorage.getItem('q-theme') || 'dark');
    $$('[data-set-theme]').forEach(b => b.classList.toggle('active', b.getAttribute('data-set-theme') === cur));
  }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-set-theme]');
    if (!b) return;
    const mode = b.getAttribute('data-set-theme') === 'light' ? 'light' : 'dark';
    localStorage.setItem('q-theme', mode);
    applyTheme(mode);
    syncThemeSeg();
  });

  /* ---------------- Language persistence ----------------
   * Priority: saved choice → browser language → default (Arabic).
   * The choice is stored in localStorage AND the q_lang cookie so the server
   * can reopen the site in the saved language on the next visit (refresh,
   * browser restart, PWA launch, app WebView). */
  function setLangPref(lang) {
    if (lang !== 'ar' && lang !== 'en') return;
    try { localStorage.setItem('q_lang', lang); } catch (e) {}
    document.cookie = 'q_lang=' + lang + ';path=/;max-age=31536000;SameSite=Lax';
  }
  document.addEventListener('click', e => {
    const a = e.target.closest('.lang-switch,[data-set-lang]');
    if (!a) return;
    setLangPref(a.getAttribute('data-set-lang') || a.getAttribute('hreflang')
      || (Q.lang === 'ar' ? 'en' : 'ar'));
  });
  (function () {
    try {
      const saved = localStorage.getItem('q_lang');
      if (saved === 'ar' || saved === 'en') {
        setLangPref(saved);                 // keep the cookie alive
        return;
      }
      // First visit only: honor an English browser on the Arabic homepage.
      if (localStorage.getItem('q_lang_auto')) return;
      localStorage.setItem('q_lang_auto', '1');
      const nav = (navigator.language || '').toLowerCase();
      if (Q.lang === 'ar' && nav.indexOf('en') === 0 && location.pathname === '/') {
        setLangPref('en');
        location.replace('/en');
      } else {
        setLangPref(Q.lang);
      }
    } catch (e) { /* storage disabled */ }
  })();

  /* ================= Cookie consent =================
   * Categories: necessary (always on) · analytics · functional · marketing.
   * Stored in localStorage q_consent; Google Consent Mode is updated and a
   * `q-consent` event released so gated scripts (ads) can react. */
  const CKEY = 'q_consent';
  const getConsent = () => { try { return JSON.parse(localStorage.getItem(CKEY)) || null; } catch (e) { return null; } };
  function applyConsent(c) {
    if (!c) return;
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage:  c.analytics ? 'granted' : 'denied',
        ad_storage:         c.marketing ? 'granted' : 'denied',
        ad_user_data:       c.marketing ? 'granted' : 'denied',
        ad_personalization: c.marketing ? 'granted' : 'denied'
      });
    }
    try { window.dispatchEvent(new CustomEvent('q-consent', { detail: c })); } catch (e) {}
  }
  function saveConsent(c, silent) {
    c.necessary = true;
    c.v = 1;
    c.at = new Date().toISOString();
    try { localStorage.setItem(CKEY, JSON.stringify(c)); } catch (e) {}
    document.cookie = 'q_consent=1;path=/;max-age=15552000;SameSite=Lax';
    applyConsent(c);
    const bar = $('#cookie-bar');
    if (bar) { bar.classList.remove('show'); setTimeout(() => bar.remove(), 350); }
    if (!silent) QToast(Q.t.ck_saved || (Q.lang === 'ar' ? 'تم حفظ تفضيلات الكوكيز ✓' : 'Cookie preferences saved ✓'));
    syncConsentUI(document);
  }
  function showConsentBanner() {
    if ($('#cookie-bar') || /\/cookie-settings$/.test(location.pathname)) return;
    const T = Q.t;
    const bar = document.createElement('div');
    bar.id = 'cookie-bar';
    bar.className = 'cookie-bar glass';
    bar.setAttribute('role', 'dialog');
    bar.setAttribute('aria-label', T.ck_title || 'Cookies');
    bar.innerHTML =
      '<div class="cb-text"><b>' + (T.ck_title || '') + '</b><p>' + (T.ck_text || '') +
      ' <a href="' + Q.prefix + '/cookies">' + (T.ck_policy || 'Cookie Policy') + '</a></p></div>' +
      '<div class="cb-actions">' +
        '<button type="button" class="btn btn-primary" data-ck-accept>' + (T.ck_accept || 'Accept all') + '</button>' +
        '<button type="button" class="btn btn-ghost" data-ck-reject>' + (T.ck_reject || 'Reject non-essential') + '</button>' +
        '<a class="btn btn-ghost" href="' + Q.prefix + '/cookie-settings">' + (T.ck_custom || 'Customize') + '</a>' +
      '</div>';
    document.body.appendChild(bar);
    requestAnimationFrame(() => bar.classList.add('show'));
  }
  /* Preference page toggles (state sync — binding is delegated below). */
  function syncConsentUI(scope) {
    const box = $('[data-cookie-prefs]', scope);
    if (!box) return;
    const c = getConsent() || { analytics: false, functional: false, marketing: false };
    $$('[data-consent-cat]', box).forEach(inp => {
      const cat = inp.getAttribute('data-consent-cat');
      if (cat === 'necessary') { inp.checked = true; return; }
      inp.checked = !!c[cat];
    });
  }
  window.__qSyncConsent = syncConsentUI;
  document.addEventListener('click', e => {
    if (e.target.closest('[data-ck-accept],[data-consent-accept-all]')) {
      const box = $('[data-cookie-prefs]');
      if (box) $$('[data-consent-cat]', box).forEach(i => { i.checked = true; });
      saveConsent({ analytics: true, functional: true, marketing: true });
      return;
    }
    if (e.target.closest('[data-ck-reject],[data-consent-reject]')) {
      const box = $('[data-cookie-prefs]');
      if (box) $$('[data-consent-cat]', box).forEach(i => { if (i.getAttribute('data-consent-cat') !== 'necessary') i.checked = false; });
      saveConsent({ analytics: false, functional: false, marketing: false });
      return;
    }
    if (e.target.closest('[data-consent-save]')) {
      const box = $('[data-cookie-prefs]');
      const c = { analytics: false, functional: false, marketing: false };
      if (box) $$('[data-consent-cat]', box).forEach(i => {
        const cat = i.getAttribute('data-consent-cat');
        if (cat !== 'necessary') c[cat] = i.checked;
      });
      saveConsent(c);
    }
  });
  (function () {
    const c = getConsent();
    if (c) applyConsent(c);
    else setTimeout(showConsentBanner, 900);
  })();

  /* ================= Telegram join dialog =================
   * Shown after page load. "Join Now" opens the channel and never shows the
   * dialog again; "Later" hides it for the next 10 page visits. Stored in
   * localStorage (q_tg). Skipped while the cookie banner is pending so two
   * dialogs never stack. */
  (function () {
    const TG_URL = 'https://t.me/tofi_tv';
    const TKEY = 'q_tg';
    const read = () => { try { return JSON.parse(localStorage.getItem(TKEY)) || {}; } catch (e) { return {}; } };
    const write = v => { try { localStorage.setItem(TKEY, JSON.stringify(v)); } catch (e) {} };

    const st = read();
    if (st.state === 'joined') return;
    if (st.state === 'later') {
      st.visits = (st.visits || 0) + 1;
      write(st);
      if (st.visits < 10) return;
    }
    if (!getConsent()) return;            // cookie banner first — this visit still counted

    function dismiss(later) {
      const dlg = $('#tg-dialog');
      if (!dlg) return;
      dlg.classList.remove('show');
      setTimeout(() => dlg.remove(), 300);
      if (later) write({ state: 'later', visits: 0 });
    }
    function show() {
      if ($('#tg-dialog')) return;
      const T = Q.t;
      const dlg = document.createElement('div');
      dlg.id = 'tg-dialog';
      dlg.className = 'tg-dialog';
      dlg.setAttribute('role', 'dialog');
      dlg.setAttribute('aria-modal', 'true');
      dlg.setAttribute('aria-label', T.tg_title || 'Telegram');
      dlg.innerHTML =
        '<div class="tgd-overlay" data-tg-later></div>' +
        '<div class="tgd-card glass">' +
          '<span class="tgd-ic" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor"><path d="M21.9 4.3 18.7 19.4c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-4.9 9-8.1c.4-.3-.1-.5-.6-.2L6.2 13.4l-4.8-1.5c-1-.3-1.1-1 .2-1.5l18.7-7.2c.9-.3 1.7.2 1.4 1.1z"/></svg>' +
          '</span>' +
          '<h3 class="tgd-title">' + (T.tg_title || '') + '</h3>' +
          '<p class="tgd-desc">' + (T.tg_desc || '') + '</p>' +
          '<div class="tgd-actions">' +
            '<a class="btn btn-primary" data-tg-join href="' + TG_URL + '" target="_blank" rel="noopener">' + (T.tg_join || 'Join') + '</a>' +
            '<button class="btn btn-ghost" type="button" data-tg-later>' + (T.tg_later || 'Later') + '</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(dlg);
      requestAnimationFrame(() => dlg.classList.add('show'));
      dlg.addEventListener('click', e => {
        if (e.target.closest('[data-tg-join]')) {
          write({ state: 'joined' });
          dismiss(false);
          return; // let the link open normally
        }
        if (e.target.closest('[data-tg-later]')) dismiss(true);
      });
    }
    setTimeout(show, 1400);
  })();

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

  /* ---------------- Broken-image fallback (brand logo) ----------------
   * Any <img> that fails to load anywhere on the site (movies, series, news,
   * channels, matches) swaps to the ToFi X Tv mark — never a broken icon.
   * Capture-phase listener = works for every current and future image. */
  const IMG_FALLBACK = '/assets/brand/icon.svg';
  addEventListener('error', e => {
    const img = e.target;
    if (!(img instanceof HTMLImageElement)) return;
    if (img.dataset.fbk) return;          // already swapped once
    img.dataset.fbk = '1';
    img.src = IMG_FALLBACK;
    img.classList.add('img-fallback');
  }, true);

  /* ---------------- Local 12-hour time helper ---------------- */
  function fmt12(ts) {
    const d = new Date(ts * 1000);
    let h = d.getHours();
    const m = String(d.getMinutes()).padStart(2, '0');
    const pm = h >= 12;
    h = h % 12 || 12;
    return String(h).padStart(2, '0') + ':' + m + ' ' + (pm ? (Q.t.pm || 'PM') : (Q.t.am || 'AM'));
  }

  /* ---------------- Live match clock (mirrors PHP live_clock) ---------------- */
  function liveLabel(st, startTs) {
    if (st === 2) return { label: Q.t.ht || 'HT', progress: 0.5 };
    if (st === 7 || st === 8 || st === 10 || st === 11 || st === 13) return { label: Q.t.pens || 'PSO', progress: 1 };
    const bases = { 1: [0, 45], 3: [45, 90], 5: [90, 105], 6: [105, 120], 9: [105, 120] };
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
  setInterval(tickLiveClocks, 20000);

  /* ---------------- Live score polling (page-agnostic) ---------------- */
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
    // Poll only when the CURRENT page shows matches (works across PJAX swaps).
    if (!$('[data-match]') && !$('[data-match-page]') && !$('.lc-minute')) return;
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
  setInterval(poll, 60000);

  /* ---------------- Favorites (localStorage) ----------------
   * kinds: team / league / match (sports) + movie / series (cinema). */
  const FKEY = 'q-favs-v1';
  const favs = () => { try { return JSON.parse(localStorage.getItem(FKEY)) || {}; } catch (e) { return {}; } };
  const saveFavs = f => localStorage.setItem(FKEY, JSON.stringify(f));
  window.QF = {
    toggle(btn) {
      const kind = btn.getAttribute('data-fav'), id = btn.getAttribute('data-id');
      const f = favs();
      f[kind] = f[kind] || {};
      let added = false;
      if (f[kind][id]) { delete f[kind][id]; }
      else {
        f[kind][id] = { id, title: btn.getAttribute('data-title') || '', url: btn.getAttribute('data-url') || '', img: btn.getAttribute('data-img') || '' };
        added = true;
      }
      saveFavs(f);
      // Sync EVERY button for this item on the page (card + detail page).
      $$('[data-fav="' + kind + '"][data-id="' + id + '"]').forEach(b => b.classList.toggle('is-fav', added));
      QToast(added ? (Q.t.fav_added || (Q.lang === 'ar' ? 'أُضيف إلى المفضلة ✓' : 'Added to favorites ✓'))
                   : (Q.t.fav_removed || (Q.lang === 'ar' ? 'أُزيل من المفضلة' : 'Removed from favorites')));
    },
    mark(scope) {
      const f = favs();
      $$('[data-fav]', scope).forEach(btn => {
        const kind = btn.getAttribute('data-fav'), id = btn.getAttribute('data-id');
        btn.classList.toggle('is-fav', !!(f[kind] && f[kind][id]));
      });
    }
  };
  /* Delegated toggle — survives PJAX swaps, works for buttons inside links. */
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-fav]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    QF.toggle(btn);
  });

  /* ---------------- Poster details on touch ----------------
   * The hover info panel (overview + rating + genres) only exists for real
   * pointers (@media hover:hover), so Chrome on Android and app WebViews
   * never showed it. On touch: the FIRST tap reveals the panel, the second
   * tap opens the title. Tapping anywhere else closes it. Mouse users keep
   * plain hover + instant click. Capture phase = runs before the PJAX router. */
  let lastPointerType = 'mouse';
  addEventListener('pointerdown', e => { lastPointerType = e.pointerType || 'mouse'; }, { capture: true, passive: true });
  addEventListener('touchstart', () => { lastPointerType = 'touch'; }, { capture: true, passive: true });
  document.addEventListener('click', e => {
    const card = e.target.closest('.poster-card');
    if (!card) {
      $$('.poster-card.pi-open').forEach(c => c.classList.remove('pi-open'));
      return;
    }
    if (lastPointerType !== 'touch') return;          // mouse/pen: native hover
    if (e.target.closest('.poster-fav')) return;      // the star keeps working
    if (!card.querySelector('.poster-info')) return;
    if (!card.classList.contains('pi-open')) {
      e.preventDefault();
      e.stopPropagation();
      $$('.poster-card.pi-open').forEach(c => c.classList.remove('pi-open'));
      card.classList.add('pi-open');
    }
    // Already open → let the tap navigate normally.
  }, true);

  /* ---------------- Newsletter (delegated) ---------------- */
  document.addEventListener('submit', async e => {
    const nl = e.target.closest('#newsletter-form');
    if (!nl) return;
    e.preventDefault();
    const email = nl.email.value.trim();
    if (!email) return;
    try {
      const res = await fetch('/api/newsletter', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });
      if (res.ok) { QToast(document.documentElement.lang === 'ar' ? 'تم الاشتراك بنجاح!' : 'Subscribed!'); nl.reset(); }
    } catch (err) { /* ignore */ }
  });

  /* ---------------- Copy-link (delegated) ---------------- */
  const isArNow = () => document.documentElement.lang === 'ar';
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-copy-link]');
    if (!btn) return;
    const url = btn.getAttribute('data-copy-link') || location.href;
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
      .then(() => QToast(isArNow() ? 'تم نسخ الرابط ✓' : 'Link copied ✓'))
      .catch(() => QToast(url));
  });

  /* ================================================================
     initPage(scope) — everything bound to page CONTENT. Idempotent:
     called on first load and after every PJAX content swap.
     ================================================================ */
  let cdTimer = 0;
  function initPage(scope) {
    scope = scope || document;

    /* 12-hour local time correction */
    $$('[data-ts]', scope).forEach(el => { const ts = +el.getAttribute('data-ts'); if (ts > 0) el.textContent = fmt12(ts); });
    $$('[data-ts-inline]', scope).forEach(el => { const ts = +el.getAttribute('data-ts-inline'); if (ts > 0) el.textContent = fmt12(ts); });

    /* Countdown */
    clearInterval(cdTimer);
    const cd = $('[data-countdown]', scope);
    if (cd) {
      const target = +cd.getAttribute('data-countdown') * 1000;
      const cells = { d: $('[data-cd="d"]', cd), h: $('[data-cd="h"]', cd), m: $('[data-cd="m"]', cd), s: $('[data-cd="s"]', cd) };
      const tick = () => {
        if (!cd.isConnected) { clearInterval(cdTimer); return; }
        let diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
        const d = Math.floor(diff / 86400); diff %= 86400;
        const h = Math.floor(diff / 3600); diff %= 3600;
        const m = Math.floor(diff / 60), s = diff % 60;
        cells.d.textContent = String(d).padStart(2, '0');
        cells.h.textContent = String(h).padStart(2, '0');
        cells.m.textContent = String(m).padStart(2, '0');
        cells.s.textContent = String(s).padStart(2, '0');
        // Kickoff passed: refresh ONCE to pull the live layout, then stop.
        // The old unconditional reload re-fired on every render when the
        // server still reported the match as upcoming (unknown status code),
        // freezing the page in an infinite refresh loop.
        if (target - Date.now() < -60000) {
          clearInterval(cdTimer);
          var ck = 'q_cd_' + Math.floor(target / 1000);
          var seen = null;
          try { seen = sessionStorage.getItem(ck); sessionStorage.setItem(ck, '1'); } catch (e) { seen = '1'; }
          if (!seen) location.reload();
          else cd.style.display = 'none';
        }
      };
      tick();
      cdTimer = setInterval(tick, 1000);
    }

    /* Tabs */
    $$('.tabs', scope).forEach(tabs => {
      if (tabs.dataset.bound) return;
      tabs.dataset.bound = '1';
      tabs.addEventListener('click', e => {
        const btn = e.target.closest('.tab');
        if (!btn) return;
        const name = btn.getAttribute('data-tab');
        $$('.tab', tabs).forEach(t => t.classList.toggle('active', t === btn));
        $$('.tab-panel').forEach(p => p.classList.toggle('active', p.getAttribute('data-panel') === name));
        history.replaceState(history.state, '', '#' + name);
      });
    });
    if (location.hash) {
      const btn = $('.tab[data-tab="' + location.hash.slice(1) + '"]', scope);
      if (btn) btn.click();
    }

    /* events filter segments */
    $$('[data-events-filter]', scope).forEach(seg => {
      if (seg.dataset.bound) return;
      seg.dataset.bound = '1';
      seg.addEventListener('click', () => {
        const val = seg.getAttribute('data-events-filter');
        $$('[data-events-filter]').forEach(s => s.classList.toggle('active', s === seg));
        $$('[data-events-view]').forEach(v => v.hidden = v.getAttribute('data-events-view') !== val);
      });
    });

    /* "Show more" reveal */
    $$('[data-show-more]', scope).forEach(btn => {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', () => {
        $$(btn.getAttribute('data-show-more')).forEach(el => el.hidden = false);
        const wrap = btn.closest('.show-more-wrap');
        (wrap || btn).remove();
      });
    });

    /* In-page links that jump to a tab */
    $$('[data-goto-tab]', scope).forEach(a => {
      if (a.dataset.bound) return;
      a.dataset.bound = '1';
      a.addEventListener('click', e => {
        const btn = $('.tab[data-tab="' + a.getAttribute('data-goto-tab') + '"]');
        if (btn) { e.preventDefault(); btn.click(); btn.scrollIntoView({ block: 'nearest' }); }
      });
    });

    /* Player stat rings (draw + count up) */
    function animateRings(r) {
      (r || scope).querySelectorAll('.rc-fg[data-target]').forEach(fg => {
        requestAnimationFrame(() => { fg.style.strokeDashoffset = fg.getAttribute('data-target'); });
      });
      (r || scope).querySelectorAll('[data-count]').forEach(el => {
        const target = +el.getAttribute('data-count') || 0;
        if (target === 0) { el.textContent = '0'; return; }
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
    const rings = $('.stat-rings', scope);
    if (rings) {
      if ('IntersectionObserver' in window) {
        const rio = new IntersectionObserver((es, o) => {
          es.forEach(en => { if (en.isIntersecting) { animateRings(en.target); o.unobserve(en.target); } });
        }, { threshold: .3 });
        rio.observe(rings);
      } else animateRings();
    }

    /* Player competition tabs */
    $$('[data-comp-tabs]', scope).forEach(bar => {
      if (bar.dataset.bound) return;
      bar.dataset.bound = '1';
      bar.addEventListener('click', e => {
        const pill = e.target.closest('.cs-pill');
        if (!pill) return;
        const id = pill.getAttribute('data-comp');
        $$('.cs-pill', bar).forEach(p => p.classList.toggle('active', p === pill));
        $$('.comp-panel').forEach(p => p.classList.toggle('active', p.getAttribute('data-comp-panel') === id));
      });
    });
    if ($('.stats-section', scope)) animateRings($('.stats-section', scope));

    /* Reveal on scroll */
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(entries => {
        entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
      }, { rootMargin: '0px 0px -8% 0px' });
      $$('.reveal:not(.in)', scope).forEach(el => io.observe(el));
    } else {
      $$('.reveal', scope).forEach(el => el.classList.add('in'));
    }

    /* Date jump */
    const dj = $('#date-jump', scope);
    if (dj && !dj.dataset.bound) {
      dj.dataset.bound = '1';
      dj.addEventListener('change', () => { if (dj.value) location.href = Q.prefix + '/matches/' + dj.value; });
    }

    /* Favorites: mark all buttons + render the favorites page */
    QF.mark(scope);
    const favRoot = $('#favorites-root', scope);
    if (favRoot && !favRoot.dataset.bound) {
      favRoot.dataset.bound = '1';
      const f = favs();
      const groups = [
        ['team', favRoot.dataset.l10nTeams],
        ['league', favRoot.dataset.l10nLeagues],
        ['match', favRoot.dataset.l10nMatches],
        ['movie', favRoot.dataset.l10nMovies],
        ['series', favRoot.dataset.l10nSeries]
      ];
      let html = '', any = false;
      groups.forEach(([kind, label]) => {
        if (!label) return;
        const items = Object.values(f[kind] || {});
        if (!items.length) return;
        any = true;
        const poster = kind === 'movie' || kind === 'series';
        html += '<section class="fav-group"><h2>' + label + '</h2><div class="fav-items' + (poster ? ' fav-posters' : '') + '">';
        items.forEach(it => {
          const safeTitle = (it.title || '').replace(/</g, '&lt;');
          if (poster) {
            // Cinema favorites: real poster cards, same visual language as
            // the movies/series section grids.
            html += '<a class="fav-pcard card-hover" href="' + it.url + '" title="' + safeTitle.replace(/"/g, '&quot;') + '">' +
              '<span class="fp-thumb">' +
                (it.img ? '<img src="' + it.img + '" alt="" loading="lazy" decoding="async">'
                        : '<span class="fp-empty" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M7 4l2.5 5M12 4l2.5 5M17 4l2.5 5"/></svg></span>') +
                '<span class="fp-caption">' + safeTitle + '</span>' +
              '</span>' +
              '<button class="fav-x fp-x" data-k="' + kind + '" data-i="' + it.id + '" aria-label="remove">✕</button></a>';
          } else {
            html += '<a class="fav-item card-hover" href="' + it.url + '">' +
              (it.img ? '<img src="' + it.img + '" alt="" loading="lazy">' : '') +
              '<span>' + safeTitle + '</span>' +
              '<button class="fav-x" data-k="' + kind + '" data-i="' + it.id + '" aria-label="remove">✕</button></a>';
          }
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
        const card = x.closest('.fav-item,.fav-pcard');
        if (card) card.remove();
      });
    }

    /* Live clocks for freshly rendered content */
    tickLiveClocks();

    /* Settings & More / cookie-settings page state */
    syncThemeSeg();
    syncConsentUI(scope);

    /* In-site YouTube player (click poster → iframe, embed-block guard) */
    $$('[data-yt-player]', scope).forEach(stage => {
      if (stage.dataset.bound) return;
      stage.dataset.bound = '1';
      const playBtn = $('[data-yt-play]', stage);
      if (!playBtn) return;
      playBtn.addEventListener('click', () => {
        const id = stage.getAttribute('data-yt');
        if (!id) return;
        const guard = stage.hasAttribute('data-yt-guard');
        const xFallback = stage.getAttribute('data-x-fallback');
        const blockedTpl = $('template[data-yt-blocked]', stage);

        const iframe = document.createElement('iframe');
        iframe.className = 'vp-iframe';
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + id
          + '?autoplay=1&rel=0&modestbranding=1&playsinline=1'
          + (guard ? '&enablejsapi=1&origin=' + encodeURIComponent(location.origin) : '');
        iframe.title = 'YouTube';
        iframe.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        iframe.setAttribute('frameborder', '0');

        function showBlocked() {
          if (xFallback) {
            const x = document.createElement('iframe');
            x.className = 'vp-iframe vp-x-frame';
            x.src = xFallback;
            x.title = 'X';
            x.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
            x.allowFullscreen = true;
            stage.classList.add('vp-x');
            stage.replaceChildren(x);
          } else if (blockedTpl) {
            stage.replaceChildren(blockedTpl.content.cloneNode(true));
          }
        }

        if (guard) {
          const onMsg = e => {
            if (!/https:\/\/www\.youtube(-nocookie)?\.com$/.test(e.origin)) return;
            let d = e.data;
            if (typeof d === 'string') { try { d = JSON.parse(d); } catch (err) { return; } }
            if (d && d.event === 'onError' && [101, 150, '101', '150'].includes(d.info)) {
              removeEventListener('message', onMsg);
              showBlocked();
            }
          };
          addEventListener('message', onMsg);
          iframe.addEventListener('load', () => {
            try {
              iframe.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 'q-yt', channel: 'widget' }), '*');
              iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'addEventListener', args: ['onError'], id: 'q-yt', channel: 'widget' }), '*');
            } catch (err) { /* cross-origin quirks — guard is best-effort */ }
          });
        }

        const keepTpl = blockedTpl;
        stage.replaceChildren(iframe);
        if (keepTpl) stage.appendChild(keepTpl);
      });
    });

    /* HLS streams (m3u8) via the site's hls.js vendor */
    $$('video[data-hls]', scope).forEach(videoEl => {
      if (videoEl.dataset.bound) return;
      videoEl.dataset.bound = '1';
      const src = videoEl.getAttribute('data-hls');
      if (!src) return;
      if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
        videoEl.src = src;
        return;
      }
      const boot = () => {
        if (window.Hls && window.Hls.isSupported()) {
          const hls = new Hls({ maxBufferLength: 30 });
          hls.loadSource(src);
          hls.attachMedia(videoEl);
        } else {
          videoEl.src = src;
        }
      };
      if (window.Hls) { boot(); return; }
      const s = document.createElement('script');
      s.src = '/assets/vendor/hls.min.js';
      s.onload = boot;
      document.head.appendChild(s);
    });

    /* Cinema hero slider */
    const hero = $('#cinemaHero', scope);
    if (hero && !hero.dataset.bound) {
      hero.dataset.bound = '1';
      const slides = $$('.ch-slide', hero);
      const dots = $$('.ch-dot', hero);
      if (slides.length > 1) {
        let i = 0, timer = 0;
        const show = n => {
          i = (n + slides.length) % slides.length;
          slides.forEach((s, k) => s.classList.toggle('active', k === i));
          dots.forEach((d, k) => d.classList.toggle('active', k === i));
        };
        const play = () => { stop(); timer = setInterval(() => { if (!hero.isConnected) { stop(); return; } show(i + 1); }, 6000); };
        const stop = () => { if (timer) { clearInterval(timer); timer = 0; } };
        dots.forEach(d => d.addEventListener('click', () => { show(+d.dataset.goto || 0); play(); }));
        hero.addEventListener('pointerenter', stop);
        hero.addEventListener('pointerleave', play);
        play();
      }
    }
  }
  window.__qInitPage = initPage;
  initPage(document);

  /* ================================================================
     PJAX app-like navigation
     Fixed shell (header / bottom-nav / footer) + content-only swaps.
     SEO-safe: real URLs, pushState, server renders every page fully.
     ================================================================ */
  const mainEl = document.getElementById('main');
  const supportsPjax = !!(mainEl && window.history && history.pushState && window.DOMParser);
  const prefetchCache = new Map(); // url -> {t, html}
  const PREFETCH_TTL = 30000;

  function isInternalNav(a) {
    if (!a || a.target === '_blank' || a.hasAttribute('download') || a.getAttribute('rel') === 'external') return null;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return null;
    let u;
    try { u = new URL(a.href, location.href); } catch (e) { return null; }
    if (u.origin !== location.origin) return null;
    const p = u.pathname;
    // Bypass: admin, APIs, feeds, files, the stream proxy and cross-language
    // switches (they change <html lang/dir> — a clean full load is correct).
    if (/^\/(admin|api|cron|media|stream)(\/|$)/.test(p)) return null;
    if (/\.(xml|txt|ico|png|jpe?g|webp|svg|js|css|webmanifest|pdf|zip)$/i.test(p)) return null;
    const curEn = /^\/en(\/|$)/.test(location.pathname), nxtEn = /^\/en(\/|$)/.test(p);
    if (curEn !== nxtEn) return null;
    return u;
  }

  async function fetchPage(url) {
    const key = url.pathname + url.search;
    const hit = prefetchCache.get(key);
    if (hit && Date.now() - hit.t < PREFETCH_TTL) return hit.html;
    const res = await fetch(url.href, { headers: { 'X-Pjax': '1' }, credentials: 'same-origin' });
    if (!res.ok || !(res.headers.get('content-type') || '').includes('text/html')) throw new Error('bad response');
    const html = await res.text();
    prefetchCache.set(key, { t: Date.now(), html });
    return html;
  }

  function updateActiveNav(pathname) {
    const bare = '/' + (pathname.replace(/^\/en(\/|$)/, '/$1').replace(/^\/+/, ''));
    const mark = (links, aliasMap) => links.forEach(a => {
      let p;
      try { p = new URL(a.href, location.href).pathname.replace(/^\/en(?=\/|$)/, '') || '/'; } catch (e) { return; }
      let on = p === '/' ? bare === '/' : bare.startsWith(p);
      // aliases: /movie/* lights the movies tab, /video → videos, etc.
      (aliasMap[p] || []).forEach(alias => { if (bare.startsWith(alias)) on = true; });
      a.classList.toggle('active', on);
    });
    const aliases = { '/movies': ['/movie'], '/series': [], '/videos': ['/video'], '/matches': [], '/news': [] };
    mark($$('.site-header .nav-link'), aliases);
    mark($$('.bottom-nav .bn-item'), aliases);
  }

  function swapMeta(doc) {
    document.title = doc.title;
    ['meta[name="description"]', 'link[rel="canonical"]',
     'meta[property="og:title"]', 'meta[property="og:description"]',
     'meta[property="og:url"]', 'meta[property="og:image"]', 'meta[property="og:type"]',
     'meta[name="twitter:title"]', 'meta[name="twitter:description"]', 'meta[name="twitter:image"]'
    ].forEach(sel => {
      const nw = doc.querySelector(sel), cur = document.querySelector(sel);
      if (nw && cur) {
        if (nw.hasAttribute('content')) cur.setAttribute('content', nw.getAttribute('content'));
        if (nw.hasAttribute('href')) cur.setAttribute('href', nw.getAttribute('href'));
      }
    });
  }

  let navSeq = 0;
  async function pjaxGo(url, push) {
    const seq = ++navSeq;
    document.body.classList.add('pjax-loading');
    try {
      const html = await fetchPage(url);
      if (seq !== navSeq) return;                    // superseded by a newer nav
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newMain = doc.getElementById('main');
      // Pages whose content ships its own external scripts (stream players)
      // need a real load; same for anything unexpected.
      if (!newMain || newMain.querySelector('script[src]')) { location.href = url.href; return; }

      if (push) history.pushState({ pjax: true }, '', url.href);

      // Transition: fade out → swap → fade in (respects reduced motion via CSS)
      mainEl.classList.add('page-leave');
      await new Promise(r => setTimeout(r, 90));
      if (seq !== navSeq) return;

      mainEl.innerHTML = newMain.innerHTML;
      // Re-execute inline scripts from the incoming content (none today, safe).
      $$('script:not([src])', mainEl).forEach(old => {
        const s = document.createElement('script');
        s.textContent = old.textContent;
        old.replaceWith(s);
      });
      swapMeta(doc);
      updateActiveNav(url.pathname);
      scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
      mainEl.classList.remove('page-leave');
      mainEl.classList.add('page-enter');
      setTimeout(() => mainEl.classList.remove('page-enter'), 260);
      initPage(mainEl);
    } catch (e) {
      location.href = url.href;                       // graceful fallback
    } finally {
      if (seq === navSeq) document.body.classList.remove('pjax-loading');
    }
  }

  if (supportsPjax) {
    history.scrollRestoration = 'auto';

    document.addEventListener('click', e => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const a = e.target.closest('a');
      const u = isInternalNav(a);
      if (!u) return;
      // Same page + hash → let the browser scroll natively.
      if (u.pathname === location.pathname && u.search === location.search) {
        if (u.hash) return;
        e.preventDefault();
        scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }
      e.preventDefault();
      pjaxGo(u, true);
    });

    addEventListener('popstate', () => {
      pjaxGo(new URL(location.href), false);
    });

    /* Smart prefetch: warm pages the instant a link is hovered / touched. */
    let prefetchBudget = 40;
    const prefetch = a => {
      const u = isInternalNav(a);
      if (!u || prefetchBudget <= 0) return;
      const key = u.pathname + u.search;
      if (prefetchCache.has(key) || (u.pathname === location.pathname && u.search === location.search)) return;
      prefetchBudget--;
      fetchPage(u).catch(() => { prefetchCache.delete(key); });
    };
    document.addEventListener('pointerover', e => {
      const a = e.target.closest('a');
      if (a) prefetch(a);
    }, { passive: true });
    document.addEventListener('touchstart', e => {
      const a = e.target.closest('a');
      if (a) prefetch(a);
    }, { passive: true });
  }

  /* ---------------- PWA install ---------------- */
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    const b = $('#install-btn');
    if (b) b.hidden = false;
  });
  document.addEventListener('click', async e => {
    if (!e.target.closest('#install-btn,[data-pwa-install]')) return;
    if (!deferredPrompt) {
      // Already installed, or the browser doesn't expose the prompt.
      if (e.target.closest('[data-pwa-install]')) {
        QToast(document.documentElement.lang === 'ar'
          ? 'التطبيق مثبّت بالفعل أو المتصفح لا يدعم التثبيت'
          : 'Already installed, or not supported by this browser');
      }
      return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    const b = $('#install-btn');
    if (b) b.hidden = true;
  });

  /* ---------------- Service worker + auto-update prompt ----------------
   * Rebuilt update flow. Root causes of the old bugs, fixed here:
   *  1. "Nothing happens on update / random reloads": the old global
   *     controllerchange→reload fired on FIRST install too (clients.claim),
   *     causing surprise reloads. Reload is now gated behind a user-initiated
   *     flag, so it happens exactly once and only when the user clicked.
   *  2. "Prompt reappears after reopening the site": dismissal was stored in
   *     sessionStorage keyed by the CURRENT build. It is now stored in
   *     localStorage keyed by the NEW build (read from the waiting worker's
   *     scriptURL ?v=...), so each release prompts exactly once per browser.
   *  3. "Update didn't actually update": clicking now (a) deletes every
   *     Cache API cache from the page, (b) tells the waiting worker to take
   *     over, (c) waits for it to activate, (d) saves the new version to
   *     localStorage, then (e) reloads — with a hard 4s fallback reload.
   */
  if ('serviceWorker' in navigator) {
    const isAr = () => document.documentElement.lang === 'ar';
    const build = Q.build || 'base';
    const swUrl = '/sw.js?v=' + encodeURIComponent(build);
    let registration = null;
    let userUpdating = false;
    let reloaded = false;

    try { localStorage.setItem('q_build', build); } catch (e) {}

    const workerBuild = w => {
      try { return new URL(w.scriptURL).searchParams.get('v') || 'base'; } catch (e) { return 'base'; }
    };

    function reloadOnce() {
      if (reloaded) return;
      reloaded = true;
      location.reload();
    }

    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (userUpdating) reloadOnce();
    });

    async function applyUpdate(bar, newBuild) {
      userUpdating = true;
      bar.classList.add('is-updating');
      bar.innerHTML =
        '<span class="ub-txt"><span class="ub-spin" aria-hidden="true"></span>' +
        (Q.t.updating || (isAr() ? 'جاري تحديث الموقع...' : 'Updating the site...')) + '</span>';
      setTimeout(reloadOnce, 4000);
      try {
        if (window.caches && caches.keys) {
          const keys = await caches.keys();
          await Promise.all(keys.map(k => caches.delete(k)));
        }
        try {
          localStorage.setItem('q_build', newBuild);
          localStorage.setItem('q_upd_done_' + newBuild, '1');
        } catch (e) {}
        const w = registration && registration.waiting;
        if (w) w.postMessage({ type: 'SKIP_WAITING' });
        else reloadOnce();
      } catch (e) { reloadOnce(); }
    }

    function showUpdatePrompt(waitingWorker) {
      if ($('#update-bar')) return;
      const newBuild = workerBuild(waitingWorker);
      const activeW = registration && registration.active;
      if (activeW && workerBuild(activeW) === newBuild) return;
      try {
        if (localStorage.getItem('q_upd_done_' + newBuild)) return;
        if (localStorage.getItem('q_upd_seen_' + newBuild)) return;
        localStorage.setItem('q_upd_seen_' + newBuild, '1');
      } catch (e) {}
      const bar = document.createElement('div');
      bar.id = 'update-bar';
      bar.className = 'update-bar';
      bar.setAttribute('role', 'alert');
      bar.innerHTML =
        '<span class="ub-txt">' + (Q.t.update_ready || (isAr() ? 'يوجد إصدار جديد من الموقع' : 'A new version is available')) + '</span>' +
        '<div class="ub-actions">' +
          '<button class="ub-btn ub-now" type="button">' + (Q.t.update_now || (isAr() ? 'تحديث الآن' : 'Update now')) + '</button>' +
          '<button class="ub-btn ub-later" type="button">' + (Q.t.update_later || (isAr() ? 'لاحقاً' : 'Later')) + '</button>' +
        '</div>';
      document.body.appendChild(bar);
      requestAnimationFrame(() => bar.classList.add('show'));
      bar.querySelector('.ub-later').addEventListener('click', () => {
        bar.classList.remove('show');
        setTimeout(() => bar.remove(), 350);
      });
      bar.querySelector('.ub-now').addEventListener('click', () => applyUpdate(bar, newBuild));
    }

    function trackInstalling(worker) {
      if (!worker) return;
      worker.addEventListener('statechange', () => {
        if (worker.state === 'installed' && navigator.serviceWorker.controller) showUpdatePrompt(worker);
      });
    }

    addEventListener('load', () => {
      navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' }).then(reg => {
        registration = reg;
        if (reg.waiting && navigator.serviceWorker.controller) showUpdatePrompt(reg.waiting);
        else if (reg.installing) trackInstalling(reg.installing);
        reg.addEventListener('updatefound', () => trackInstalling(reg.installing));
        setInterval(() => reg.update().catch(() => {}), 30 * 60 * 1000);
      }).catch(() => {});
    });
  }

  /* ---------------- Push notifications (two-step bottom sheet) -------------
   * Step 1 (enable): permission + FCM token → subscribed to ALL championships.
   * Step 2 (manage): shown only once enabled — every championship from the
   * matches API with its own switch; turning one off stops its alerts, and
   * turning everything off disables push entirely. */
  const notifyBtn = $('#notify-btn');
  const sheet = $('#notify-sheet');
  const ar = () => document.documentElement.lang === 'ar';
  const configured = ('Notification' in window) && Q.fcm && Q.fcm.apiKey;
  const pushOn = () => {
    try { return localStorage.getItem('q_push_on') === '1' && Notification.permission === 'granted'; }
    catch (e) { return false; }
  };
  const getFcmToken = async () => {
    const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js');
    const { getMessaging, getToken } = await import('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging.js');
    const app = initializeApp(Q.fcm);
    const messaging = getMessaging(app);
    const reg = await navigator.serviceWorker.ready;
    return getToken(messaging, { vapidKey: Q.fcm.vapidKey, serviceWorkerRegistration: reg });
  };
  const subscribe = async (body) => {
    const asJson = async (res) => {
      let j = null;
      try { j = await res.json(); } catch (e) {}
      if (!res.ok || !j || j.ok !== true) throw new Error('HTTP ' + res.status);
      return j;
    };
    let postErr = null;
    try {
      return await fetch('/api/push-subscribe', {
        method: 'POST', headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
        body: JSON.stringify(body)
      }).then(asJson);
    } catch (e) { postErr = e; }
    try {
      const q = new URLSearchParams({
        token: body.token || '',
        topics: (body.topics || []).join(','),
        disable: body.disable ? '1' : ''
      });
      return await fetch('/api/push-subscribe?' + q, { cache: 'no-store' }).then(asJson);
    } catch (e) {
      throw postErr || e;
    }
  };
  const markSynced = (token) => {
    localStorage.setItem('q_push_token', token);
    localStorage.setItem('q_push_sync', String(Date.now()));
  };
  if (notifyBtn) {
    const stepEnable = sheet && $('[data-push-step="enable"]', sheet);
    const stepManage = sheet && $('[data-push-step="manage"]', sheet);
    const allBoxes = () => $$('#notify-sheet .sheet-body input[type=checkbox]');
    const syncStep = () => {
      if (!stepEnable || !stepManage) return;
      const on = pushOn();
      stepEnable.hidden = on;
      stepManage.hidden = !on;
      if (!on) return;
      let saved = [];
      try { saved = JSON.parse(localStorage.getItem('q_topics') || '[]'); } catch (e) {}
      const all = !saved.length || saved.includes('all');
      allBoxes().forEach(c => { c.checked = all || saved.includes(c.value); });
      const master = $('[data-topics-all]', sheet);
      if (master) master.checked = all;
    };
    const openSheet = () => {
      if (!sheet) return;
      sheet.hidden = false;
      requestAnimationFrame(() => sheet.classList.add('open'));
      document.body.style.overflow = 'hidden';
    };
    const closeSheet = () => {
      if (!sheet) return;
      sheet.classList.remove('open');
      document.body.style.overflow = '';
      setTimeout(() => { sheet.hidden = true; }, 320);
    };
    notifyBtn.addEventListener('click', () => {
      if (!configured) {
        Notification && Notification.requestPermission && Notification.requestPermission();
        QToast(ar() ? 'الإشعارات غير مهيأة بعد' : 'Notifications not configured yet');
        return;
      }
      syncStep();
      openSheet();
    });
    if (sheet) {
      $$('[data-sheet-close]', sheet).forEach(el => el.addEventListener('click', closeSheet));
      const master = $('[data-topics-all]', sheet);
      if (master) master.addEventListener('change', () => {
        allBoxes().forEach(c => { c.checked = master.checked; });
      });
      const busy = (btn, on) => { btn.disabled = on; btn.classList.toggle('is-loading', on); };
      const failMsg = (err) => (ar() ? 'تعذر الحفظ — ' : 'Could not save — ')
        + String(err && err.message ? err.message : 'network').slice(0, 60);

      const enableBtn = $('[data-push-enable]', sheet);
      if (enableBtn) enableBtn.addEventListener('click', async () => {
        busy(enableBtn, true);
        try {
          const perm = await Notification.requestPermission();
          if (perm !== 'granted') { QToast(ar() ? 'تم رفض الإذن' : 'Permission denied'); return; }
          const token = await getFcmToken();
          if (!token) throw new Error('no-token');
          await subscribe({ token, topics: ['all'] });
          localStorage.setItem('q_push_on', '1');
          localStorage.setItem('q_topics', JSON.stringify(['all']));
          markSynced(token);
          QToast(ar() ? 'تم تفعيل الإشعارات لجميع البطولات ✓' : 'Alerts enabled for all competitions ✓');
          syncStep();
        } catch (err) {
          QToast(failMsg(err));
        } finally {
          busy(enableBtn, false);
        }
      });

      const saveBtn = $('[data-topics-save]', sheet);
      if (saveBtn) saveBtn.addEventListener('click', async () => {
        const boxes = allBoxes();
        const picked = boxes.filter(c => c.checked).map(c => c.value);
        busy(saveBtn, true);
        try {
          const token = await getFcmToken();
          if (!token) throw new Error('no-token');
          if (!picked.length) {
            await subscribe({ token, disable: true });
            localStorage.removeItem('q_push_on');
            localStorage.setItem('q_topics', '[]');
            QToast(ar() ? 'تم إيقاف جميع الإشعارات' : 'All alerts turned off');
            syncStep();
            return;
          }
          const topics = picked.length === boxes.length ? ['all'] : picked;
          await subscribe({ token, topics });
          localStorage.setItem('q_push_on', '1');
          localStorage.setItem('q_topics', JSON.stringify(topics));
          markSynced(token);
          QToast(ar() ? 'تم حفظ تفضيلاتك ✓' : 'Preferences saved ✓');
          closeSheet();
        } catch (err) {
          QToast(failMsg(err));
        } finally {
          busy(saveBtn, false);
        }
      });
    }
  }

  /* Self-healing re-sync — runs on EVERY page (not just the home CTA): when
   * this device has notifications on, re-register its token + topics with the
   * server once a day. Covers FCM token rotation, a reset/pruned server list,
   * and any subscribe POST that failed silently in older versions. */
  if (configured && pushOn()) {
    const last = +(localStorage.getItem('q_push_sync') || 0);
    if (Date.now() - last > 864e5) {
      setTimeout(async () => {
        try {
          const token = await getFcmToken();
          if (!token) return;
          let topics = [];
          try { topics = JSON.parse(localStorage.getItem('q_topics') || '[]'); } catch (e) {}
          if (!topics.length || topics.indexOf('all') !== -1) topics = ['all'];
          await subscribe({ token, topics });
          markSynced(token);
        } catch (e) { /* retried next visit */ }
      }, 4000);
    }
  }
})();

/* ============ Cinema (movies & series) ============ */
(() => {
  'use strict';

  /* Third-party player embeds. NO sandbox attribute: vidsrc/videasy detect it
   * and refuse to play ("Iframe Sandbox Detected"), and YouTube throws error
   * 153 — the providers must run untouched, as required. Popup protection is
   * handled where it belongs: the PROJECT's own code contains zero
   * window.open/popup/redirect scripts (audited), and embeds only ever load
   * after an explicit user click.
   * referrerpolicy: YouTube requires the origin referrer for embed playback
   * (no-referrer causes error 153); other providers get origin-only. */
  function loadEmbed(frameBox, src) {
    let iframe = frameBox.querySelector('iframe');
    if (!iframe) {
      iframe = document.createElement('iframe');
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media; picture-in-picture');
      frameBox.innerHTML = '';
      frameBox.appendChild(iframe);
    }
    const isYT = /(^https?:)?\/\/(www\.)?youtube(-nocookie)?\.com\//.test(src);
    iframe.setAttribute('referrerpolicy', isYT ? 'strict-origin-when-cross-origin' : 'origin');
    iframe.src = src;
  }

  /* Click-to-load embeds: no third-party iframe until the user asks for it
   * (keeps LCP/INP clean on detail pages). Buttons/chips carry
   * data-embed-src + data-embed-target (the id of the .player-frame host). */
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-embed-src]');
    if (!btn) return;
    const host = document.getElementById(btn.getAttribute('data-embed-target') || 'player');
    const frameBox = host && (host.querySelector('[data-player]') || host);
    if (!frameBox) return;
    ev.preventDefault();
    loadEmbed(frameBox, btn.getAttribute('data-embed-src'));
    frameBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.querySelectorAll('.player-sources .chip').forEach(c => c.classList.toggle('active', c === btn));
  });

  /* ---------------- Netflix-style episode switching ----------------
   * Season/episode chips carry data-ep-season / data-ep-episode. Clicking one
   * only updates: the player source, the episode heading, the active chip and
   * the URL (replaceState) — the page itself never reloads. The chips keep
   * real hrefs, so crawlers and no-JS users still get server-rendered pages. */
  document.addEventListener('click', (ev) => {
    const chip = ev.target.closest('[data-ep-episode], [data-ep-season]');
    if (!chip) return;
    const box = document.getElementById('player');
    const cfgEl = box && box.querySelector('[data-ep-config]');
    if (!cfgEl) return;

    let cfg;
    try { cfg = JSON.parse(cfgEl.textContent); } catch (e) { return; }

    // Season switch → episode list length differs per season; the server page
    // has the correct list, so let PJAX/normal navigation handle seasons and
    // only hijack EPISODE switches (same season, instant).
    if (!chip.hasAttribute('data-ep-episode')) return;
    ev.preventDefault();
    ev.stopPropagation(); // don't let the PJAX router double-handle it

    const season = +chip.getAttribute('data-ep-season') || cfg.season || 1;
    const episode = +chip.getAttribute('data-ep-episode') || 1;
    cfg.season = season;
    cfg.episode = episode;
    cfgEl.textContent = JSON.stringify(cfg);

    // 1) Player source (active source preserved; default first source)
    const activeSrcBtn = document.querySelector('.player-sources .chip.active');
    const sourceKey = activeSrcBtn && activeSrcBtn.getAttribute('data-ep-source') || 'vidsrc';
    const tpl = cfg.sources[sourceKey] || cfg.sources.vidsrc;
    const src = tpl.replace('{s}', season).replace('{e}', episode);
    const frameBox = box.querySelector('[data-player]');
    if (frameBox && frameBox.querySelector('iframe')) loadEmbed(frameBox, src);
    else if (frameBox) loadEmbed(frameBox, src); // first play too — instant
    // Update source chips to the new episode
    document.querySelectorAll('.player-sources .chip[data-ep-source]').forEach(c => {
      const t = cfg.sources[c.getAttribute('data-ep-source')];
      if (t) c.setAttribute('data-embed-src', t.replace('{s}', season).replace('{e}', episode));
    });

    // 2) Episode info (heading)
    document.querySelectorAll('[data-ep-label-season]').forEach(el => el.textContent = season);
    document.querySelectorAll('[data-ep-label-episode]').forEach(el => el.textContent = episode);

    // 3) Active chip
    document.querySelectorAll('.episode-chips .chip').forEach(c =>
      c.classList.toggle('active', +c.getAttribute('data-ep-episode') === episode));

    // 4) Shareable URL — replaceState only, no navigation
    const u = new URL(location.href);
    u.searchParams.set('season', season);
    u.searchParams.set('episode', episode);
    u.hash = 'player';
    history.replaceState(history.state, '', u.href);
  }, true); // capture: runs before the PJAX click handler
})();
