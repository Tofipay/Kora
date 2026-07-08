<?php require __DIR__ . '/_shell.php'; admin_top('Notification Manager', 'notifications');

/* Public web-app defaults (safe for the browser — apiKey/appId are NOT secrets).
   Used only as a convenience fallback so the form is save-ready. */
$def = [
    'apiKey'            => 'AIzaSyA1KlaPZ8dpUnDpqQGuHgmsVtYvjdxJU4s',
    'authDomain'        => 'mobaryat-live-v1.firebaseapp.com',
    'projectId'         => 'mobaryat-live-v1',
    'messagingSenderId' => '285416736241',
    'appId'             => '1:285416736241:web:e062d814e436960fc77050',
    'vapidKey'          => 'BDy9xGVfFXlspOJOO5MzA4GFYeE06D95glBoxdZmyJBZiCwovgc4fRsxdsUtssN0nWvrwLvJKEdTUT58vGLKCDQ',
];
$v = fn(string $k): string => (string)($f[$k] ?? $def[$k] ?? '');
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <b>1 · Firebase Cloud Messaging — HTTP v1 (server credentials)</b>
  <p class="hint" style="margin:6px 0 12px">
    Push is sent with the modern <code>HTTP v1</code> API using an OAuth2 Bearer token minted from your
    <b>Service Account JSON</b>. The old “FCM Server Key” is no longer used.
  </p>

  <?php if ($sa): ?>
    <div class="msg" style="background:rgba(22,199,132,.12);border-color:#16C784">
      ✓ Service Account active —
      project <code><?= e($sa['project_id']) ?></code>,
      <code><?= e($sa['client_email']) ?></code>
      <?php if (!empty($sa['key_id'])): ?> · key <code><?= e(substr($sa['key_id'], 0, 10)) ?>…</code><?php endif; ?>
    </div>
    <form method="post" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="btn" type="submit" name="delete_sa" value="1"
              onclick="return confirm('Remove the Service Account? Push sending will stop until you upload a new one.')">
        Remove Service Account
      </button>
    </form>
  <?php else: ?>
    <div class="msg" style="background:rgba(255,180,0,.12);border-color:#f0b429">
      No Service Account uploaded yet. Firebase Console → Project settings → <b>Service accounts</b> →
      “Generate new private key”, then upload the JSON below. It is stored on the server only and never exposed to visitors.
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Service Account JSON (<?= $sa ? 'replace' : 'upload' ?>)</label>
    <input type="file" name="service_account" accept="application/json,.json" required>
    <button class="btn" type="submit" name="upload_sa" value="1">Upload &amp; activate</button>
  </form>
</div>

<div class="card">
  <b>2 · Public web-app configuration</b> — used by the browser to obtain push tokens
  <p class="hint" style="margin:6px 0 12px">
    These values are public (they ship to the client for <code>getToken()</code>). The Project ID must match the
    Service Account above.
  </p>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div><label>Firebase Project ID</label><input type="text" name="projectId" value="<?= e($v('projectId')) ?>"></div>
      <div><label>apiKey</label><input type="text" name="apiKey" value="<?= e($v('apiKey')) ?>"></div>
      <div><label>authDomain</label><input type="text" name="authDomain" value="<?= e($v('authDomain')) ?>"></div>
    </div>
    <div class="row">
      <div><label>messagingSenderId</label><input type="text" name="messagingSenderId" value="<?= e($v('messagingSenderId')) ?>"></div>
      <div><label>appId</label><input type="text" name="appId" value="<?= e($v('appId')) ?>"></div>
      <div><label>VAPID key (web push certificate)</label><input type="text" name="vapidKey" value="<?= e($v('vapidKey')) ?>"></div>
    </div>

    <label>Automatic events</label>
    <?php $ev = $f['events'] ?? []; ?>
    <div class="row" style="font-size:13px;color:#9FB0C8">
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_match_start" <?= !isset($f['events']) || !empty($ev['match_start']) ? 'checked' : '' ?>> Match start</label>
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_goal" <?= !isset($f['events']) || !empty($ev['goal']) ? 'checked' : '' ?>> Goal</label>
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_red_card" <?= !isset($f['events']) || !empty($ev['red_card']) ? 'checked' : '' ?>> Red card</label>
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_half_time" <?= !isset($f['events']) || !empty($ev['half_time']) ? 'checked' : '' ?>> Half time</label>
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_full_time" <?= !isset($f['events']) || !empty($ev['full_time']) ? 'checked' : '' ?>> Full time</label>
      <label style="display:flex;gap:6px;margin:0"><input type="checkbox" name="ev_news" <?= !isset($f['events']) || !empty($ev['news']) ? 'checked' : '' ?>> Breaking news</label>
    </div>
    <small class="hint">Automatic match events require the cron worker: <code>php deploy/notify-worker.php</code> every minute (see DEPLOYMENT.md).</small>
    <button class="btn" type="submit" name="save_config" value="1">Save configuration</button>
  </form>
</div>

<div class="card">
  <b>3 · Automatic match notifications (cron)</b>
  <p class="hint" style="margin:6px 0 10px">
    Run this every minute so goals / kick-off / full-time are pushed automatically.
    Pick <b>whichever one</b> your host supports.
  </p>

  <label>Option A — URL cron (easiest on shared hosting / cPanel “wget”)</label>
  <pre style="background:#0b1220;color:#9FB0C8;padding:10px;border-radius:8px;font-size:12px;white-space:pre-wrap;user-select:all">wget -q -O /dev/null "<?= e($cronUrl) ?>"</pre>
  <small class="hint">Schedule it <b>Once Per Minute</b> (<code>* * * * *</code>). Keep this URL secret — it carries the trigger key.</small>

  <label style="margin-top:12px">Option B — PHP cron (if you have shell/PHP-CLI access)</label>
  <pre style="background:#0b1220;color:#9FB0C8;padding:10px;border-radius:8px;font-size:12px;white-space:pre-wrap;user-select:all">php <?= e(BASE_DIR) ?>/deploy/notify-worker.php</pre>
  <small class="hint">The path above is this installation's real path — paste it as-is.</small>
</div>

<div class="card">
  <b>4 · Send a test / broadcast now</b> — <?= (int)$tokens ?> subscribed device(s)
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="row">
      <div><label>Title</label><input type="text" name="title" placeholder="هدف!"></div>
      <div><label>Body</label><input type="text" name="body" placeholder="..."></div>
    </div>
    <button class="btn" type="submit" name="send_test" value="1"<?= $sa ? '' : ' disabled title="Upload a Service Account first"' ?>>
      Send to all subscribers
    </button>
    <?php if (!$sa): ?><small class="hint">Upload a Service Account (step 1) to enable sending.</small><?php endif; ?>
  </form>

  <?php if (!empty($saLog)): ?>
    <label style="margin-top:14px">Recent delivery log</label>
    <pre style="max-height:160px;overflow:auto;background:#0b1220;color:#9FB0C8;padding:10px;border-radius:8px;font-size:12px;white-space:pre-wrap"><?php
      foreach ($saLog as $line) echo e($line) . "\n";
    ?></pre>
  <?php endif; ?>
</div>
<?php admin_bottom();
