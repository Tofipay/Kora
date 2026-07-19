/*
 * assets/js/app.js
 * -----------------------------------------------------------------------------
 * منطق واجهة لوحة التحكّم (Vanilla JS ES2025).
 *   - تحميل الإحصائيات والقنوات ومؤشّرات الخادم عبر REST API.
 *   - CRUD للقنوات + التحكّم بالبثّ (start/stop/restart) + المراقبة.
 *   - التنقّل بين الأقسام، النوافذ المنبثقة، والإشعارات.
 * لا يعتمد أي إطار عمل خارجي.
 */

'use strict';

const { apiBase, baseUrl, apiKey } = window.TOFIX;

/* ============ طبقة الاتصال بالـ API ============ */
const api = {
  async request(params = {}, { method = 'GET', body = null } = {}) {
    const url = new URL(apiBase, location.href);
    Object.entries(params).forEach(([k, v]) => v != null && url.searchParams.set(k, v));
    const opts = { method, headers: { 'X-API-Key': apiKey } };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    const res = await fetch(url, opts);
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.success === false) {
      throw new Error(json.error || `خطأ ${res.status}`);
    }
    return json.data;
  },
  channels:    ()        => api.request({ resource: 'channels' }),
  createCh:    (d)       => api.request({ resource: 'channels' }, { method: 'POST', body: d }),
  updateCh:    (id, d)   => api.request({ resource: 'channels', id }, { method: 'PUT', body: d }),
  deleteCh:    (id)      => api.request({ resource: 'channels', id }, { method: 'DELETE' }),
  duplicateCh: (id)      => api.request({ resource: 'channels', action: 'duplicate', id }, { method: 'POST' }),
  stream:      (act, id) => api.request({ resource: 'stream', action: act, id }, { method: act === 'status' || act === 'monitor' ? 'GET' : 'POST' }),
  stats:       ()        => api.request({ resource: 'stats' }),
  system:      ()        => api.request({ resource: 'system' }),
};

/* ============ أدوات مساعدة ============ */
const $  = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));

function toast(msg, type = 'ok') {
  const el = document.createElement('div');
  el.className = `toast-x ${type}`;
  el.innerHTML = `<i class="bi bi-${type === 'ok' ? 'check-circle' : 'exclamation-triangle'}"></i> ${esc(msg)}`;
  $('#toaster').appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3200);
}

/* حالة عامة للتطبيق */
const state = { channels: [], stats: {}, system: {} };

/* ============ العرض (Rendering) ============ */
function statusBadge(status, running) {
  if (running) return `<span class="badge online"><span class="dot"></span> LIVE</span>`;
  if (status === 'active') return `<span class="badge idle"><span class="dot"></span> جاهزة</span>`;
  return `<span class="badge offline"><span class="dot"></span> متوقّفة</span>`;
}

function chCell(ch) {
  const logo = ch.logo
    ? `<img src="${esc(ch.logo)}" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'ph',textContent:'${esc((ch.name||'?')[0])}'}))">`
    : `<div class="ph">${esc((ch.name || '?')[0].toUpperCase())}</div>`;
  return `<div class="ch">${logo}<div><b>${esc(ch.name)}</b><small>${esc(ch.audio_lang || '')} · ${esc(ch.source_type || '')}</small></div></div>`;
}

function renderStats() {
  const s = state.stats;
  const cards = [
    { icon: 'collection-play', value: s.total_channels ?? 0, label: 'إجمالي القنوات', trend: '' },
    { icon: 'broadcast-pin',   value: s.active_channels ?? 0, label: 'قنوات نشطة', trend: '' },
    { icon: 'reception-4',     value: s.live_streams ?? 0, label: 'بثوث مباشرة (FFmpeg)', trend: '' },
    { icon: 'people',          value: (s.total_viewers ?? 0).toLocaleString('ar'), label: 'إجمالي المشاهدين', trend: '' },
  ];
  $('#statCards').innerHTML = cards.map((c) => `
    <div class="glass stat-card">
      <div class="icon"><i class="bi bi-${c.icon}"></i></div>
      <div class="value">${c.value}</div>
      <div class="label">${c.label}</div>
    </div>`).join('');
}

function meter(label, percent, detail, warn = false) {
  const p = Math.min(100, Number(percent) || 0);
  return `<div class="glass meter">
    <div class="head"><span>${label}</span><span>${p}%</span></div>
    <div class="bar ${warn || p > 85 ? 'warn' : ''}"><span style="width:${p}%"></span></div>
    <small>${detail}</small>
  </div>`;
}

function renderServer(targetIds = ['#serverMeters']) {
  const sys = state.system;
  if (!sys.cpu) return;
  const html = [
    meter(`<i class="bi bi-cpu"></i> المعالج CPU`, sys.cpu.percent, `${sys.cpu.cores} أنوية · حِمل ${sys.cpu.load}`),
    meter(`<i class="bi bi-memory"></i> الذاكرة RAM`, sys.ram.percent, `${sys.ram.used} / ${sys.ram.total}`),
    meter(`<i class="bi bi-hdd"></i> التخزين`, sys.storage.percent, `${sys.storage.used} / ${sys.storage.total}`),
    meter(`<i class="bi bi-clock-history"></i> Uptime`, 100, `${sys.uptime} · ${esc(sys.os || '')}`),
  ].join('');
  targetIds.forEach((id) => { const el = $(id); if (el) el.innerHTML = html; });
}

function actionButtons(ch) {
  return `
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <button class="btn btn-sm" data-act="play" data-id="${ch.id}" title="تشغيل"><i class="bi bi-play-fill"></i></button>
      <button class="btn btn-sm" data-act="start" data-id="${ch.id}" title="تشغيل FFmpeg"><i class="bi bi-broadcast"></i></button>
      <button class="btn btn-sm" data-act="stop" data-id="${ch.id}" title="إيقاف"><i class="bi bi-stop-fill"></i></button>
      <button class="btn btn-sm" data-act="restart" data-id="${ch.id}" title="إعادة تشغيل"><i class="bi bi-arrow-repeat"></i></button>
      <button class="btn btn-sm" data-act="edit" data-id="${ch.id}" title="تعديل"><i class="bi bi-pencil"></i></button>
      <button class="btn btn-sm" data-act="duplicate" data-id="${ch.id}" title="تكرار"><i class="bi bi-files"></i></button>
      <button class="btn btn-sm btn-danger" data-act="delete" data-id="${ch.id}" title="حذف"><i class="bi bi-trash"></i></button>
    </div>`;
}

function renderChannels() {
  const rows = state.channels;
  const empty = `<tr><td colspan="8"><div class="empty"><i class="bi bi-collection" style="font-size:34px"></i><p>لا توجد قنوات بعد. اضغط "قناة جديدة" للبدء.</p></div></td></tr>`;

  // جدول القنوات الكامل
  $('#channelsTable').innerHTML = rows.length ? rows.map((ch) => `
    <tr>
      <td>${chCell(ch)}</td>
      <td><code class="muted" title="${esc(ch.playback?.hls || '')}">${esc(ch.source_type)}</code></td>
      <td>${esc(ch.category)}</td>
      <td>${esc(ch.country || '—')}</td>
      <td>${esc(ch.quality)}</td>
      <td>${statusBadge(ch.status, ch._running)}</td>
      <td><a class="muted" href="player.php?channel=${ch.id}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> المشغّل</a></td>
      <td>${actionButtons(ch)}</td>
    </tr>`).join('') : empty;

  // أحدث القنوات في الداشبورد
  $('#recentChannels').innerHTML = rows.length ? rows.slice(0, 6).map((ch) => `
    <tr>
      <td>${chCell(ch)}</td>
      <td>${esc(ch.category)}</td>
      <td>${esc(ch.quality)}</td>
      <td>${statusBadge(ch.status, ch._running)}</td>
      <td><a class="muted" href="player.php?channel=${ch.id}" target="_blank">فتح</a></td>
      <td>${actionButtons(ch)}</td>
    </tr>`).join('') : empty.replace('colspan="8"', 'colspan="6"');
}

function renderMonitor() {
  const rows = state.channels;
  $('#monitorTable').innerHTML = rows.length ? rows.map((ch) => {
    const m = ch.metrics || {};
    const st = m.status === 'online' ? statusBadge('active', true)
      : (m.status ? `<span class="badge offline"><span class="dot"></span> ${esc(m.status)}</span>` : '<span class="muted">لم يُفحص</span>');
    return `<tr>
      <td>${chCell(ch)}</td>
      <td>${st}</td>
      <td>${esc(m.resolution || '—')}</td>
      <td>${esc(m.fps || '—')}</td>
      <td>${esc(m.video_codec || '—')}</td>
      <td>${esc(m.audio_codec || '—')}</td>
      <td>${esc(m.bitrate || '—')}</td>
      <td><button class="btn btn-sm" data-act="probe" data-id="${ch.id}"><i class="bi bi-search"></i> فحص</button></td>
    </tr>`;
  }).join('') : `<tr><td colspan="8"><div class="empty">لا توجد قنوات للمراقبة.</div></td></tr>`;
}

function renderServerCards() {
  const sys = state.system;
  if (!sys.cpu) return;
  const cards = [
    { icon: 'cpu', value: sys.cpu.percent + '%', label: `CPU · ${sys.cpu.cores} أنوية` },
    { icon: 'memory', value: sys.ram.percent + '%', label: `RAM · ${sys.ram.used}/${sys.ram.total}` },
    { icon: 'hdd', value: sys.storage.percent + '%', label: `التخزين · ${sys.storage.free} متاح` },
    { icon: 'filetype-php', value: sys.php, label: sys.os },
  ];
  $('#serverCards').innerHTML = cards.map((c) => `
    <div class="glass stat-card"><div class="icon"><i class="bi bi-${c.icon}"></i></div>
    <div class="value" style="font-size:24px">${esc(c.value)}</div><div class="label">${esc(c.label)}</div></div>`).join('');
  renderServer(['#serverMetersFull']);
}

/* ============ تحميل البيانات ============ */
async function loadAll() {
  try {
    const [channels, stats, system] = await Promise.all([api.channels(), api.stats(), api.system()]);
    state.channels = channels || [];
    state.stats = stats || {};
    state.system = system || {};

    // فحص أي القنوات تعمل عبر FFmpeg (وسم _running).
    await Promise.all(state.channels.map(async (ch) => {
      try { ch._running = (await api.stream('status', ch.id))?.running || false; }
      catch { ch._running = false; }
    }));

    renderStats(); renderServer(); renderChannels(); renderMonitor(); renderServerCards();
  } catch (e) { toast(e.message, 'err'); }
}

/* ============ النوافذ المنبثقة ============ */
const modal = $('#channelModal');
const form = $('#channelForm');

function openModal(ch = null) {
  form.reset();
  form.id.value = ch?.id || '';
  $('#modalTitle').innerHTML = ch
    ? `<i class="bi bi-pencil-square"></i> تعديل القناة`
    : `<i class="bi bi-plus-circle"></i> إضافة قناة جديدة`;
  if (ch) {
    ['name', 'logo', 'source_url', 'source_type', 'mode', 'category', 'quality', 'country', 'audio_lang', 'description', 'status']
      .forEach((k) => { if (form[k] != null && ch[k] != null) form[k].value = ch[k]; });
  }
  modal.classList.add('show');
}
function closeModal() { modal.classList.remove('show'); }

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  const id = data.id; delete data.id;
  try {
    if (id) { await api.updateCh(id, data); toast('تم تحديث القناة'); }
    else { await api.createCh(data); toast('تمت إضافة القناة'); }
    closeModal(); loadAll();
  } catch (err) { toast(err.message, 'err'); }
});

/* ============ إجراءات الجداول ============ */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const { act, id } = btn.dataset;
  const ch = state.channels.find((c) => c.id === id);

  try {
    switch (act) {
      case 'play':
        window.open(`player.php?channel=${id}`, '_blank'); break;
      case 'edit':
        openModal(ch); break;
      case 'delete':
        if (confirm('هل تريد حذف هذه القناة؟')) { await api.deleteCh(id); toast('تم الحذف'); loadAll(); }
        break;
      case 'duplicate':
        await api.duplicateCh(id); toast('تم تكرار القناة'); loadAll(); break;
      case 'start': {
        const r = await api.stream('start', id); toast(r.message, r.ok ? 'ok' : 'err'); loadAll(); break;
      }
      case 'stop': {
        const r = await api.stream('stop', id); toast(r.message, r.ok ? 'ok' : 'err'); loadAll(); break;
      }
      case 'restart': {
        const r = await api.stream('restart', id); toast(r.message, r.ok ? 'ok' : 'err'); loadAll(); break;
      }
      case 'probe': {
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i>';
        const m = await api.stream('monitor', id);
        ch.metrics = m; renderMonitor();
        toast(`الحالة: ${m.status}`); break;
      }
    }
  } catch (err) { toast(err.message, 'err'); }
});

/* ============ التنقّل بين الأقسام ============ */
const titles = {
  dashboard: ['لوحة التحكم', 'نظرة عامة على منصّة البثّ الخاصة بك'],
  channels:  ['القنوات', 'إدارة قنواتك وإعادة بثّ الروابط'],
  monitor:   ['مراقبة البثّ', 'المقاييس التقنية الحيّة لكل قناة'],
  server:    ['حالة السيرفر', 'موارد الخادم في الوقت الفعلي'],
};
function switchView(view) {
  $$('.nav-item').forEach((n) => n.classList.toggle('active', n.dataset.view === view));
  ['dashboard', 'channels', 'monitor', 'server'].forEach((v) => {
    const el = $(`#view-${v}`); if (el) el.style.display = v === view ? '' : 'none';
  });
  $('#viewTitle').textContent = titles[view][0];
  $('#viewSub').textContent = titles[view][1];
  $('#sidebar').classList.remove('open');
}

/* ============ ربط الأحداث ============ */
document.addEventListener('click', (e) => {
  const nav = e.target.closest('[data-view]');
  if (nav) { e.preventDefault(); switchView(nav.dataset.view); }
});
$('#addChannelBtn').addEventListener('click', () => openModal());
$('#cancelModal').addEventListener('click', closeModal);
modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
$('#refreshBtn').addEventListener('click', () => { toast('جارٍ التحديث…'); loadAll(); });
$('#menuToggle').addEventListener('click', () => $('#sidebar').classList.toggle('open'));

/* التحديث التلقائي كل 15 ثانية لمؤشّرات الخادم والإحصائيات. */
setInterval(async () => {
  try {
    state.system = await api.system();
    state.stats = await api.stats();
    renderServer(); renderStats(); renderServerCards();
  } catch {}
}, 15000);

/* الإقلاع */
loadAll();
