<?php
/**
 * Cinema server-picker dialog (intent playback mode). Rendered once per page.
 * Triggers: any element with [data-cinema-play] + a data-servers JSON attribute
 *   ([{"name":"Videasy","intent":"intent://…"}]). Clicking a server launches
 *   the official app via its xmtv deep link.
 */
?>
<div class="cinserv-backdrop" data-cinserv hidden>
  <div class="cinserv-dialog" role="dialog" aria-modal="true" aria-label="<?= e(t('cinema.sources')) ?>">
    <button class="cinserv-close" type="button" data-cinserv-close aria-label="إغلاق">&times;</button>
    <div class="cinserv-head">
      <span class="cinserv-ic" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
      </span>
      <b><?= e(t('cinema.choose_server') ?: 'اختر سيرفر المشاهدة') ?></b>
      <small><?= e(t('cinema.sources')) ?></small>
    </div>
    <div class="cinserv-list" data-cinserv-list></div>
  </div>
</div>

<style>
.cinserv-backdrop{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-end;justify-content:center;
  background:rgba(8,3,30,.62);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);padding:0}
.cinserv-backdrop[hidden]{display:none}
@media(min-width:600px){.cinserv-backdrop{align-items:center;padding:20px}}
.cinserv-dialog{position:relative;width:min(440px,100%);background:var(--surface,#1b0761);
  border:1px solid var(--line,rgba(124,77,255,.25));border-radius:22px 22px 0 0;padding:22px 20px 24px;
  box-shadow:0 -20px 60px rgba(0,0,0,.5);animation:cinservUp .22s cubic-bezier(.22,.72,.28,1)}
@media(min-width:600px){.cinserv-dialog{border-radius:22px;animation:cinservIn .2s ease}}
@keyframes cinservUp{from{transform:translateY(24px);opacity:.4}to{transform:none;opacity:1}}
@keyframes cinservIn{from{transform:scale(.96);opacity:0}to{transform:none;opacity:1}}
.cinserv-close{position:absolute;inset-inline-end:14px;top:12px;width:34px;height:34px;border:0;border-radius:50%;
  background:rgba(255,255,255,.08);color:var(--text,#ede9ff);font-size:22px;line-height:1;cursor:pointer}
.cinserv-head{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;margin-bottom:16px}
.cinserv-ic{width:54px;height:54px;display:grid;place-items:center;border-radius:16px;color:#fff;
  background:linear-gradient(135deg,#7C4DFF,#4C0ECD);box-shadow:0 12px 30px rgba(76,14,205,.45);margin-bottom:6px}
.cinserv-head b{font-size:17px;color:var(--text,#ede9ff)}
.cinserv-head small{color:var(--text-3,#9a86d8);font-size:12.5px}
.cinserv-list{display:flex;flex-direction:column;gap:10px}
.cinserv-item{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;text-decoration:none;
  background:var(--surface-2,#2a0a83);border:1px solid var(--line,rgba(124,77,255,.22));
  color:var(--text,#ede9ff);font-weight:800;font-size:15px;transition:transform .12s ease,border-color .12s ease,background .12s ease}
.cinserv-item:hover{transform:translateY(-2px);border-color:#7C4DFF;background:rgba(124,77,255,.18)}
.cinserv-item .cs-dot{width:34px;height:34px;flex:none;display:grid;place-items:center;border-radius:10px;
  background:rgba(124,77,255,.22);color:#B39DFF}
.cinserv-item .cs-dot svg{width:18px;height:18px;fill:currentColor}
.cinserv-item .cs-name{flex:1}
.cinserv-item .cs-go{color:#B39DFF}
</style>

<script>
/* Registered ONCE (survives PJAX content swaps). The click listener runs in the
 * CAPTURE phase so it fires BEFORE app.js's PJAX link handler (bubble phase) —
 * otherwise an episode <a> would trigger a page swap that leaves the body
 * scroll-locked and the dialog detached. The dialog element is re-queried every
 * time so it always targets the current (possibly re-swapped) DOM. */
(function(){
  if (window.__cinservInit) return; window.__cinservInit = true;
  function dlg(){ return document.querySelector('[data-cinserv]'); }
  function lock(on){ try { document.body.style.overflow = on ? 'hidden' : ''; } catch(e){} }
  function close(){ var b = dlg(); if (b) b.hidden = true; lock(false); }
  function open(servers){
    var b = dlg(); if (!b) return;
    var list = b.querySelector('[data-cinserv-list]'); if (!list) return;
    list.innerHTML = '';
    servers.forEach(function(s){
      if (!s || !s.intent) return;
      var a = document.createElement('a');
      a.className = 'cinserv-item';
      a.setAttribute('href', s.intent);
      a.setAttribute('rel', 'nofollow');
      a.setAttribute('data-cinserv-item', '');
      a.innerHTML =
        '<span class="cs-dot"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>' +
        '<span class="cs-name"></span>' +
        '<span class="cs-go">&#9656;</span>';
      a.querySelector('.cs-name').textContent = s.name || 'Server';
      list.appendChild(a);
    });
    b.hidden = false;
    lock(true);
  }
  document.addEventListener('click', function(ev){
    var t = ev.target;
    if (!t || !t.closest) return;
    var trigger = t.closest('[data-cinema-play]');
    if (trigger){
      var servers = [];
      try { servers = JSON.parse(trigger.getAttribute('data-servers') || '[]'); } catch(e){}
      if (servers && servers.length){
        ev.preventDefault();
        ev.stopPropagation();     // keep app.js PJAX from swapping the page
        open(servers);
      }
      return;
    }
    if (t.closest('[data-cinserv-item]')){ lock(false); return; } // let it launch the app
    var b = dlg();
    if (b && (t === b || t.closest('[data-cinserv-close]'))){
      ev.preventDefault();
      ev.stopPropagation();
      close();
    }
  }, true);
  document.addEventListener('keydown', function(e){
    var b = dlg();
    if (e.key === 'Escape' && b && !b.hidden) close();
  });
})();
</script>
