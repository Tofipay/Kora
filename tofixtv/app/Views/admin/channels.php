<?php require __DIR__ . '/_shell.php'; admin_top('Channels Library', 'channels'); ?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <b>Channels Library</b>
  <p class="hint" style="margin:6px 0 0">
    Add channels once, reuse them everywhere. Each channel has a <b>name</b> and one or more
    <b>stream URLs</b> (Yacine API links like <code>http://ver3.yacinelive.com/api/channel/1471</code>
    and/or plain <code>.m3u8</code>) — one URL per line.<br>
    Matches auto-pull links: when a match's broadcasting channel name matches a channel here,
    its links are used automatically. Name it like the broadcast (e.g. <code>beIN Max 2</code>).
  </p>
</div>

<form method="post" action="/<?= ADMIN_PATH ?>/channels">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <div id="ch-rows">
    <?php
    $rows = $items ?: [['name' => '', 'urls' => []]];
    foreach ($rows as $c):
        $name = (string)($c['name'] ?? '');
        $urls = implode("\n", (array)($c['urls'] ?? []));
    ?>
    <div class="card ch-row">
      <div class="ch-grid">
        <label>Channel name
          <input type="text" name="c_name[]" value="<?= e($name) ?>" placeholder="beIN Max 2">
        </label>
        <label>Stream URLs (one per line)
          <textarea name="c_urls[]" rows="3" placeholder="http://ver3.yacinelive.com/api/channel/1472&#10;https://cdn.example/beinmax2.m3u8"><?= e($urls) ?></textarea>
        </label>
      </div>
      <button type="button" class="btn btn-del" onclick="this.closest('.ch-row').remove()">Remove</button>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:8px;margin-top:12px">
    <button type="button" class="btn" id="ch-add">+ Add channel</button>
    <button type="submit" class="btn btn-primary">Save all</button>
  </div>
</form>

<template id="ch-tpl">
  <div class="card ch-row">
    <div class="ch-grid">
      <label>Channel name
        <input type="text" name="c_name[]" value="" placeholder="beIN Max 2">
      </label>
      <label>Stream URLs (one per line)
        <textarea name="c_urls[]" rows="3" placeholder="http://ver3.yacinelive.com/api/channel/1472&#10;https://cdn.example/beinmax2.m3u8"></textarea>
      </label>
    </div>
    <button type="button" class="btn btn-del" onclick="this.closest('.ch-row').remove()">Remove</button>
  </div>
</template>

<style>
.ch-grid{display:grid;grid-template-columns:1fr 2fr;gap:12px}
.ch-grid label{display:flex;flex-direction:column;gap:5px;font-weight:600;font-size:13px}
.ch-grid input,.ch-grid textarea{width:100%;font-family:inherit}
.ch-row{position:relative}
.btn-del{margin-top:10px;background:#ef4444;color:#fff}
@media(max-width:640px){.ch-grid{grid-template-columns:1fr}}
</style>
<script>
document.getElementById('ch-add').addEventListener('click', function () {
  const tpl = document.getElementById('ch-tpl').content.cloneNode(true);
  document.getElementById('ch-rows').appendChild(tpl);
});
</script>
