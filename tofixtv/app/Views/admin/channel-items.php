<?php
require __DIR__ . '/_shell.php';
admin_top('القنوات', 'channel-catalog');
$channels = is_array($channels ?? null) ? $channels : [];
$baseUrl = '/' . ADMIN_PATH . '/channel-catalog/group/' . (int)$group['id'];
?>
<?php if (!empty($msg)): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

<a class="catalog-back" href="/<?= ADMIN_PATH ?>/channel-catalog/category/<?= (int)$category['id'] ?>">← <?= e($category['name_ar'] ?: $category['name_en']) ?></a>
<div class="catalog-toolbar">
  <div><span class="catalog-parent-label"><?= e($group['name_ar'] ?: $group['name_en']) ?></span><b>القنوات</b><small>كل قناة تُفتح في تطبيق ALOKA Live، ويختار المستخدم إضافتها للمفضلة عند الحاجة.</small></div>
  <button class="btn catalog-add" type="button" data-catalog-add>+ إضافة قناة</button>
</div>

<?php if (!$channels): ?>
  <div class="card catalog-empty"><b>لا توجد قنوات داخل هذه القائمة</b><p>أضف اسم القناة وشعارها والرابط أو الكود المشفر.</p><button class="btn" type="button" data-catalog-add>إضافة أول قناة</button></div>
<?php else: ?>
  <div class="catalog-admin-grid channel-items-admin-grid">
  <?php foreach ($channels as $row):
    $id = (int)$row['id'];
    $playValue = (string)($row['play_value'] ?? '');
    $playEncrypted = !empty($row['play_encrypted']) || \TofiXTv\Core\ChannelCatalog::isEncryptedPlayValue($playValue);
    $edit = json_encode(['id'=>$id,'name_ar'=>$row['name_ar']??'','name_en'=>$row['name_en']??'','logo'=>$row['logo']??'','play_value'=>$playValue,'play_encrypted'=>$playEncrypted?'1':'0','order'=>(int)($row['order']??0)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?>
    <button class="catalog-admin-card media-admin-card channel-admin-tile<?= empty($row['visible'])?' is-hidden':'' ?>" type="button" data-catalog-card data-id="<?= $id ?>" data-edit="<?= e($edit ?: '{}') ?>">
      <span class="catalog-card-image"><img src="<?= e(catalog_image($row['logo'] ?? null)) ?>" alt="" width="96" height="64"></span>
      <span class="catalog-card-copy"><b><?= e($row['name_ar'] ?: $row['name_en']) ?></b><?php if (!empty($row['name_en']) && $row['name_en'] !== $row['name_ar']): ?><small dir="ltr"><?= e($row['name_en']) ?></small><?php endif; ?><em>ترتيب <?= (int)($row['order'] ?? 0) ?></em></span>
      <span class="catalog-hold-hint">ضغط مطوّل</span>
    </button>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="catalog-dialog" id="catalog-entry-dialog" hidden aria-hidden="true">
  <button class="catalog-dialog-backdrop" type="button" data-catalog-close aria-label="إغلاق"></button>
  <section class="catalog-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="catalog-entry-title" tabindex="-1">
    <div class="catalog-dialog-head"><div><b id="catalog-entry-title">إضافة قناة</b><small>الرابط أو الكود يُرسل كما هو إلى التطبيق.</small></div><button type="button" class="catalog-dialog-x" data-catalog-close>×</button></div>
    <form id="catalog-entry-form" method="post" enctype="multipart/form-data" action="<?= e($baseUrl) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="" data-field="id"><input type="hidden" name="existing_logo" value="" data-field="logo"><input type="hidden" name="play_encrypted" value="0" data-field="play_encrypted" data-play-encrypted>
      <div class="catalog-image-preview"><img src="/assets/img/channel.svg" alt="" data-catalog-preview><span>شعار القناة</span></div>
      <label>اسم القناة بالعربي<input type="text" name="name_ar" data-field="name_ar" placeholder="بي إن سبورت 1"></label>
      <label>اسم القناة بالإنجليزي<input type="text" name="name_en" data-field="name_en" dir="ltr" placeholder="beIN SPORTS 1"></label>
      <label>رفع لوقو القناة<input type="file" name="logo_upload" accept="image/png,image/jpeg,image/webp,image/gif" data-image-input></label>
      <label class="catalog-play-label"><span>الرابط أو الكود المشفر</span><em data-play-crypto-status>غير مشفّر</em><textarea name="play_value" data-field="play_value" dir="ltr" required spellcheck="false" placeholder="https://... أو الكود المشفر"></textarea></label>
      <div class="catalog-crypto-tools">
        <button class="catalog-crypto-btn is-encrypt" type="button" data-play-encrypt><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>تشفير الرابط</button>
        <button class="catalog-crypto-btn is-decrypt" type="button" data-play-decrypt hidden><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 7.5-2"/></svg>فك التشفير للتعديل</button>
        <small data-play-crypto-message aria-live="polite"></small>
      </div>
      <label>الترتيب<input type="number" min="0" name="order" data-field="order" value="0"></label>
      <div class="catalog-favorite-note">☆ المفضلة اختيارية، ولا تتفعّل إلا بعد ضغط المستخدم عليها.</div>
      <button class="btn catalog-save" type="submit" name="save_channel">حفظ القناة</button>
    </form>
  </section>
</div>

<script>
(function(){
  var form=document.getElementById('catalog-entry-form');if(!form)return;
  var field=form.querySelector('[name="play_value"]'),state=form.querySelector('[data-play-encrypted]');
  var encryptBtn=form.querySelector('[data-play-encrypt]'),decryptBtn=form.querySelector('[data-play-decrypt]');
  var status=form.querySelector('[data-play-crypto-status]'),message=form.querySelector('[data-play-crypto-message]');
  var endpoint='/<?= ADMIN_PATH ?>/channel-catalog/crypto',csrf=<?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
  function encrypted(){return state&&state.value==='1'}
  function setMessage(text,kind){message.textContent=text||'';message.className=kind?'is-'+kind:''}
  function sync(){
    var on=encrypted();encryptBtn.hidden=on;decryptBtn.hidden=!on;
    status.textContent=on?'مشفّر وجاهز للحفظ':'غير مشفّر';status.classList.toggle('is-encrypted',on);
  }
  function run(operation){
    var value=(field.value||'').trim();if(!value){setMessage('أدخل الرابط أو الكود أولاً.','error');field.focus();return}
    encryptBtn.disabled=true;decryptBtn.disabled=true;setMessage(operation==='encrypt'?'جاري التشفير…':'جاري فك التشفير…','');
    var body=new URLSearchParams({_csrf:csrf,operation:operation,value:value});
    fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','Accept':'application/json'},credentials:'same-origin',body:body.toString()})
      .then(function(res){return res.json().catch(function(){return {ok:false,message:'تعذر قراءة استجابة الخادم.'}})})
      .then(function(data){
        if(!data.ok)throw new Error(data.message||'تعذر تنفيذ العملية.');
        field.value=data.value||'';state.value=data.encrypted?'1':'0';sync();
        setMessage(data.encrypted?'تم تشفير الرابط بنفس صيغة التطبيق.':'تم فك التشفير، عدّل الرابط ثم اضغط تشفير من جديد.','ok');
        field.focus();field.setSelectionRange(0,field.value.length);
      }).catch(function(error){setMessage(error.message||'تعذر تنفيذ العملية.','error')})
      .finally(function(){encryptBtn.disabled=false;decryptBtn.disabled=false});
  }
  encryptBtn.addEventListener('click',function(){run('encrypt')});
  decryptBtn.addEventListener('click',function(){run('decrypt')});
  form.addEventListener('catalog:entry-open',function(){setMessage('','');sync()});
  sync();
})();
</script>

<?php
$catalogDeleteField = 'delete_channel';
$catalogDeleteText = 'هل تريد حذف هذه القناة نهائياً؟';
$catalogAddTitle = 'إضافة قناة';
$catalogEditTitle = 'تعديل القناة';
require __DIR__ . '/_catalog-interactions.php';
admin_bottom();
?>
