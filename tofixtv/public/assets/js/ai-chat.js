/* ToFi X Tv — AI Assistant widget.
 * ChatGPT-style chat panel in the site's design language. All answer data
 * comes from /api/ai-chat (site-data-first backend); rendering here uses
 * DOM APIs with textContent only — no HTML injection paths (XSS-safe).
 * Loaded ONLY when the admin has the assistant enabled. */
(function () {
  'use strict';
  var Q = window.TOFIXTV || {};
  var A = Q.ai;
  if (!A) return;
  var isAr = (document.documentElement.lang || 'ar') === 'ar';
  var HKEY = 'q_ai_chat_v1';

  /* ---------------- tiny DOM helpers (safe by construction) ---------------- */
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function img(src, cls) {
    var i = el('img', cls);
    i.loading = 'lazy';
    i.decoding = 'async';
    i.alt = '';
    i.src = src;
    return i;
  }

  /* ---------------- session history ---------------- */
  function loadHist() {
    try { return JSON.parse(sessionStorage.getItem(HKEY)) || []; } catch (e) { return []; }
  }
  function saveHist(h) {
    try { sessionStorage.setItem(HKEY, JSON.stringify(h.slice(-30))); } catch (e) {}
  }
  var hist = loadHist(); // [{role, content, cards?, suggestions?}]

  /* ---------------- FAB + panel shell ---------------- */
  var fab = el('button', 'ai-fab');
  fab.type = 'button';
  fab.setAttribute('aria-label', A.title || 'AI');
  fab.innerHTML =
    '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">' +
    '<path d="M12 3l1.7 3.8L17.5 8.5l-3.8 1.7L12 14l-1.7-3.8L6.5 8.5l3.8-1.7z"/>' +
    '<path d="M18.5 13.5l.9 2 2 .9-2 .9-.9 2-.9-2-2-.9 2-.9z"/>' +
    '<path d="M5.5 14.5l.7 1.6 1.6.7-1.6.7-.7 1.6-.7-1.6-1.6-.7 1.6-.7z"/></svg>';

  var panel = el('div', 'ai-panel');
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', A.title || 'AI');
  panel.hidden = true;

  var head = el('div', 'ai-head');
  var headIc = el('span', 'ai-head-ic');
  headIc.innerHTML = fab.innerHTML;
  var headTxt = el('div', 'ai-head-txt');
  headTxt.appendChild(el('b', '', A.title || 'ToFi X Tv AI'));
  headTxt.appendChild(el('small', '', A.subtitle || ''));
  var clearBtn = el('button', 'ai-hbtn', '');
  clearBtn.type = 'button';
  clearBtn.title = A.clear || '';
  clearBtn.setAttribute('aria-label', A.clear || 'clear');
  clearBtn.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/></svg>';
  var closeBtn = el('button', 'ai-hbtn', '');
  closeBtn.type = 'button';
  closeBtn.setAttribute('aria-label', 'close');
  closeBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  head.appendChild(headIc);
  head.appendChild(headTxt);
  head.appendChild(clearBtn);
  head.appendChild(closeBtn);

  var body = el('div', 'ai-body');
  var inputRow = el('form', 'ai-input');
  var input = el('input');
  input.type = 'text';
  input.placeholder = A.placeholder || '';
  input.maxLength = 500;
  input.autocomplete = 'off';
  var sendBtn = el('button', 'ai-send');
  sendBtn.type = 'submit';
  sendBtn.setAttribute('aria-label', A.send || 'send');
  sendBtn.innerHTML = '<svg viewBox="0 0 24 24" width="19" height="19" fill="currentColor" aria-hidden="true"><path d="M3.4 20.6 21.6 12 3.4 3.4l2.8 7.2 9.3 1.4-9.3 1.4z"/></svg>';
  inputRow.appendChild(input);
  inputRow.appendChild(sendBtn);

  panel.appendChild(head);
  panel.appendChild(body);
  panel.appendChild(inputRow);

  var overlay = el('div', 'ai-overlay');
  document.body.appendChild(fab);
  document.body.appendChild(overlay);
  document.body.appendChild(panel);

  /* ---------------- open / close ---------------- */
  function openPanel() {
    panel.hidden = false;
    requestAnimationFrame(function () {
      panel.classList.add('open');
      overlay.classList.add('show');
    });
    document.body.classList.add('ai-open');
    if (!body.childElementCount) renderIntro();
    setTimeout(function () { input.focus(); }, 250);
    scrollBottom();
  }
  function closePanel() {
    panel.classList.remove('open');
    overlay.classList.remove('show');
    document.body.classList.remove('ai-open');
    setTimeout(function () { panel.hidden = true; }, 300);
  }
  fab.addEventListener('click', openPanel);
  closeBtn.addEventListener('click', closePanel);
  overlay.addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) closePanel(); });
  clearBtn.addEventListener('click', function () {
    hist = [];
    saveHist(hist);
    body.textContent = '';
    renderIntro();
  });
  /* Card CTA navigation closes the chat (PJAX takes over the page). */
  body.addEventListener('click', function (e) {
    if (e.target.closest('a')) closePanel();
  });

  /* ---------------- rendering ---------------- */
  function scrollBottom() { body.scrollTop = body.scrollHeight + 999; }

  function addBubble(role, text) {
    var b = el('div', 'ai-msg ai-' + role);
    var bubble = el('div', 'ai-bubble');
    String(text || '').split(/\n+/).forEach(function (line, i) {
      if (i > 0) bubble.appendChild(document.createElement('br'));
      bubble.appendChild(document.createTextNode(line));
    });
    b.appendChild(bubble);
    body.appendChild(b);
    scrollBottom();
    return b;
  }

  function stateClass(s) { return s === 'live' ? 'is-live' : (s === 'finished' ? 'is-ft' : 'is-soon'); }

  function ctaBtn(url, label) {
    var a = el('a', 'ai-cta', label);
    a.href = url;
    return a;
  }

  function renderCard(c) {
    var card;
    if (c.type === 'match') {
      card = el('a', 'ai-card ai-card-match');
      card.href = c.url;
      var row = el('div', 'acm-row');
      var home = el('span', 'acm-team');
      home.appendChild(img(c.home.img, 'acm-logo'));
      home.appendChild(el('b', '', c.home.name));
      var mid = el('span', 'acm-mid');
      if (c.score) mid.appendChild(el('b', 'acm-score', c.score));
      mid.appendChild(el('span', 'acm-state ' + stateClass(c.state), c.label || ''));
      var away = el('span', 'acm-team');
      away.appendChild(img(c.away.img, 'acm-logo'));
      away.appendChild(el('b', '', c.away.name));
      row.appendChild(home); row.appendChild(mid); row.appendChild(away);
      card.appendChild(row);
      var meta = [];
      if (c.league) meta.push(c.league);
      if (c.state === 'upcoming' && c.time) meta.push((c.date ? c.date + ' · ' : '') + c.time);
      if (meta.length) card.appendChild(el('div', 'acm-meta', meta.join(' — ')));
      card.appendChild(el('span', 'ai-cta', c.state === 'live' ? (A.cta_watch_match || '') : (A.cta_details || '')));
      return card;
    }
    if (c.type === 'movie' || c.type === 'series') {
      card = el('a', 'ai-card ai-card-poster');
      card.href = c.url;
      card.appendChild(img(c.poster, 'acp-poster'));
      var info = el('div', 'acp-info');
      info.appendChild(el('b', 'acp-title', c.title + (c.year ? ' (' + c.year + ')' : '')));
      var chips = el('div', 'acp-chips');
      if (c.rating) chips.appendChild(el('span', 'acp-chip acp-rate', '⭐ ' + c.rating));
      if (c.age && c.age !== 'عام' && c.age !== 'G') chips.appendChild(el('span', 'acp-chip acp-age', c.age));
      if (c.type === 'series' && c.seasons > 0) {
        chips.appendChild(el('span', 'acp-chip', c.seasons + ' ' + (A.seasons || '') + (c.episodes > 0 ? ' · ' + c.episodes + ' ' + (A.episodes || '') : '')));
      }
      if (chips.childElementCount) info.appendChild(chips);
      if (c.overview) info.appendChild(el('p', 'acp-ov', c.overview));
      info.appendChild(el('span', 'ai-cta', c.type === 'series' ? (A.cta_watch_series || '') : (A.cta_watch_movie || '')));
      card.appendChild(info);
      return card;
    }
    if (c.type === 'news') {
      card = el('a', 'ai-card ai-card-news');
      card.href = c.url;
      card.appendChild(img(c.img, 'acn-img'));
      var ni = el('div', 'acn-info');
      ni.appendChild(el('b', 'acn-title', c.title));
      if (c.time) ni.appendChild(el('small', 'acn-time', c.time));
      ni.appendChild(el('span', 'ai-cta', A.cta_details || ''));
      card.appendChild(ni);
      return card;
    }
    if (c.type === 'team' || c.type === 'player' || c.type === 'league') {
      card = el('a', 'ai-card ai-card-entity');
      card.href = c.url;
      card.appendChild(img(c.img, 'ace-img'));
      card.appendChild(el('b', 'ace-title', c.title));
      card.appendChild(el('span', 'ai-cta', A.cta_details || ''));
      return card;
    }
    if (c.type === 'channel') {
      card = el('a', 'ai-card ai-card-entity');
      card.href = c.url;
      var tv = el('span', 'ace-img ace-tv');
      tv.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="13" rx="3"/><path d="m8 2 4 4 4-4"/></svg>';
      card.appendChild(tv);
      card.appendChild(el('b', 'ace-title', c.name));
      card.appendChild(el('span', 'ai-cta', A.cta_open_channel || ''));
      return card;
    }
    return null;
  }

  function renderCards(cards) {
    if (!cards || !cards.length) return;
    var wrap = el('div', 'ai-cards');
    cards.forEach(function (c) {
      var node = renderCard(c);
      if (node) wrap.appendChild(node);
    });
    body.appendChild(wrap);
    scrollBottom();
  }

  function renderSuggestions(list) {
    var old = body.querySelector('.ai-sugs');
    if (old) old.remove();
    if (!list || !list.length) return;
    var wrap = el('div', 'ai-sugs');
    list.forEach(function (s) {
      var chip = el('button', 'ai-sug', s.label);
      chip.type = 'button';
      chip.addEventListener('click', function () { send(s.q || s.label); });
      wrap.appendChild(chip);
    });
    body.appendChild(wrap);
    scrollBottom();
  }

  function renderIntro() {
    addBubble('assistant', A.welcome || '');
    if (hist.length) {
      hist.forEach(function (m) {
        addBubble(m.role, m.content);
        if (m.cards) renderCards(m.cards);
      });
    }
    renderSuggestions(A.suggestions || []);
  }

  /* ---------------- send flow ---------------- */
  var busy = false;
  function send(text) {
    text = String(text || '').trim();
    if (!text || busy) return;
    busy = true;
    input.value = '';
    sendBtn.disabled = true;
    var oldSugs = body.querySelector('.ai-sugs');
    if (oldSugs) oldSugs.remove();
    addBubble('user', text);
    hist.push({ role: 'user', content: text });

    var typing = el('div', 'ai-msg ai-assistant ai-typing-row');
    var tb = el('div', 'ai-bubble ai-typing');
    tb.innerHTML = '<i></i><i></i><i></i>';
    typing.appendChild(tb);
    body.appendChild(typing);
    scrollBottom();

    fetch('/api/ai-chat', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        message: text,
        history: hist.slice(-8).map(function (m) { return { role: m.role, content: m.content }; })
      })
    }).then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
      .then(function (res) {
        typing.remove();
        var j = res.j || {};
        if (res.s === 429) {
          addBubble('assistant', A.rate_limited || '');
        } else if (!j.ok) {
          addBubble('assistant', A.error || '');
        } else {
          addBubble('assistant', j.text || '');
          hist.push({ role: 'assistant', content: j.text || '', cards: j.cards && j.cards.length ? j.cards : undefined });
          renderCards(j.cards);
          renderSuggestions(j.suggestions);
        }
        saveHist(hist);
      })
      .catch(function () {
        typing.remove();
        addBubble('assistant', A.error || '');
      })
      .finally(function () {
        busy = false;
        sendBtn.disabled = false;
        input.focus();
      });
  }

  inputRow.addEventListener('submit', function (e) {
    e.preventDefault();
    send(input.value);
  });
})();
