<?php require __DIR__ . '/_shell.php'; admin_top('Streaming Manager', 'streaming'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <b>Open a match to manage streams</b>
  <form method="get" action="/<?= ADMIN_PATH ?>/streaming" style="display:flex;gap:8px;margin-top:10px;max-width:360px">
    <input type="text" name="match" placeholder="Match ID (e.g. 4667827)" inputmode="numeric" pattern="[0-9]*">
    <button class="btn" type="submit">Open</button>
  </form>
  <small class="hint">Find a match ID in its URL: <code>/match/…-<b>12345</b></code></small>
</div>

<?php if ($edit): $cfg = $edit['cfg']; $mode = $cfg['mode'] ?? 'internal'; $servers = $cfg['servers'] ?? []; ?>
<div class="card">
  <b>#<?= (int)$edit['id'] ?> — <?= e($edit['title']) ?></b>
  <form method="post" action="/<?= ADMIN_PATH ?>/streaming" id="stream-form" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="match_id" value="<?= (int)$edit['id'] ?>">

    <label>Playback mode</label>
    <div class="row" style="font-size:13px;color:var(--tx2)">
      <label style="display:flex;gap:6px;margin:0;align-items:center"><input type="radio" name="mode" value="internal" <?= $mode !== 'external' ? 'checked' : '' ?> onchange="stMode()"> Internal player (servers below)</label>
      <label style="display:flex;gap:6px;margin:0;align-items:center"><input type="radio" name="mode" value="external" <?= $mode === 'external' ? 'checked' : '' ?> onchange="stMode()"> External link (redirect)</label>
    </div>

    <div id="ext-box" style="<?= $mode === 'external' ? '' : 'display:none' ?>">
      <label>External URL — the Watch button opens this directly</label>
      <input type="text" name="external_url" value="<?= e($cfg['external_url'] ?? '') ?>" placeholder="https://example.com/live">
    </div>

    <div id="srv-box" style="<?= $mode === 'external' ? 'display:none' : '' ?>">
      <label>Servers (unlimited) — name, stream URL, type, order, active</label>
      <table style="margin-top:4px">
        <thead><tr><th>Name</th><th>Stream URL</th><th>Type</th><th style="width:60px">Order</th><th style="width:56px">Active</th><th style="width:34px"></th></tr></thead>
        <tbody id="srv-rows">
          <?php
          $rows = $servers ?: [['name' => '', 'url' => '', 'type' => 'auto', 'order' => 1, 'active' => true]];
          foreach ($rows as $i => $s): ?>
          <tr>
            <td><input type="text" name="s_name[]" value="<?= e($s['name'] ?? '') ?>" placeholder="SSC HD"></td>
            <td><input type="text" name="s_url[]" value="<?= e($s['url'] ?? '') ?>" placeholder="https://…/live.m3u8"></td>
            <td>
              <select name="s_type[]">
                <?php foreach ($types as $tp): ?><option value="<?= e($tp) ?>" <?= ($s['type'] ?? 'auto') === $tp ? 'selected' : '' ?>><?= e($tp) ?></option><?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="s_order[]" value="<?= (int)($s['order'] ?? ($i + 1)) ?>" min="1" style="width:56px"></td>
            <td style="text-align:center"><input type="checkbox" name="s_active[<?= $i ?>]" <?= !empty($s['active']) || !isset($s['active']) ? 'checked' : '' ?>></td>
            <td><button type="button" class="btn danger" style="padding:4px 10px;margin:0" onclick="this.closest('tr').remove()">×</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn ghost" onclick="stAddRow()">+ Add server</button>
    </div>

    <div><button class="btn" type="submit">Save streams</button></div>
  </form>
</div>
<script>
let stIdx = <?= count($servers ?: [0]) ?>;
function stMode(){ const ext=document.querySelector('input[name=mode][value=external]').checked;
  document.getElementById('ext-box').style.display=ext?'':'none';
  document.getElementById('srv-box').style.display=ext?'none':''; }
function stAddRow(){
  const tb=document.getElementById('srv-rows'); const i=stIdx++;
  const opts="<?= implode('', array_map(fn($t) => '<option value=\'' . $t . '\'>' . $t . '</option>', $types)) ?>";
  const tr=document.createElement('tr');
  tr.innerHTML='<td><input type="text" name="s_name[]" placeholder="Server"></td>'+
    '<td><input type="text" name="s_url[]" placeholder="https://…"></td>'+
    '<td><select name="s_type[]">'+opts+'</select></td>'+
    '<td><input type="number" name="s_order[]" value="'+(i+1)+'" min="1" style="width:56px"></td>'+
    '<td style="text-align:center"><input type="checkbox" name="s_active['+i+']" checked></td>'+
    '<td><button type="button" class="btn danger" style="padding:4px 10px;margin:0" onclick="this.closest(\'tr\').remove()">×</button></td>';
  tb.appendChild(tr);
}
</script>
<?php endif; ?>

<div class="card">
  <b>Today's matches</b>
  <table style="margin-top:10px">
    <tr><th>Match</th><th>Competition</th><th>ID</th><th></th></tr>
    <?php foreach ($today as $t): if (!$t['id']) continue; ?>
    <tr>
      <td><?= e($t['title']) ?> <?= $t['has'] ? '<span style="color:#4C0ECD">● live</span>' : '' ?></td>
      <td style="color:var(--tx2)"><?= e($t['league']) ?></td>
      <td><?= (int)$t['id'] ?></td>
      <td><a class="btn ghost" style="padding:5px 14px" href="/<?= ADMIN_PATH ?>/streaming?match=<?= (int)$t['id'] ?>">Manage</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if (!empty($configured)): ?>
<div class="card">
  <b>Configured streams</b>
  <table style="margin-top:10px">
    <tr><th>Match</th><th>Competition</th><th>ID</th><th></th></tr>
    <?php foreach ($configured as $t): ?>
    <tr>
      <td><?= e($t['title']) ?></td><td style="color:var(--tx2)"><?= e($t['league']) ?></td><td><?= (int)$t['id'] ?></td>
      <td><a class="btn ghost" style="padding:5px 14px" href="/<?= ADMIN_PATH ?>/streaming?match=<?= (int)$t['id'] ?>">Edit</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<?php admin_bottom();
