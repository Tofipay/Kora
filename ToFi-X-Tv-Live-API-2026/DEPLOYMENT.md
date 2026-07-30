# دليل النشر والتشغيل — ToFi X Tv Live API 2026

> ### 📌 على استضافة مشتركة (Hostinger Business)؟
> اقرأ **[HOSTINGER.md](HOSTINGER.md)** بدلًا من هذا الملف. فيه خطوات الرفع
> المختصرة، وقواعد Cloudflare جاهزة للنسخ، وصفحة فحص تتأكد فعليًا أن
> الإعداد سليم بعد الرفع.
>
> هذا الملف يغطي الحالة العامة ويشمل خيارات VPS (worker / systemd / Redis)
> وهي **غير مطلوبة إطلاقًا** للتشغيل الأساسي.

هذا الدليل يشرح الرفع، والإعدادات، ومتطلبات السيرفر والباندويث، وضبط CDN،
وكيفية قياس النتيجة فعليًا.

> **تنبيه مهم قبل كل شيء:** لا أحد يستطيع أن يَعِد بعدد مشاهدين محدد دون
> اختبار على سيرفرك أنت. البنية هنا مصمّمة لتتوسّع إلى أعداد كبيرة لأنها
> تجعل عدد الاتصالات بالمصدر ثابتًا مهما زاد المشاهدون، وتجعل المقاطع ملفات
> ثابتة يرسلها Apache/Nginx أو CDN. الرقم النهائي يعتمد على المعالج والذاكرة
> والباندويث و CDN. استخدم سكربت اختبار الحمل المرفق لقياس سيرفرك.

---

## 1) ما الذي يُرفع

ارفع محتويات المجلد كاملًا إلى جذر الدومين (نفس مكان `index.php` القديم):

```
index.php            ← المعالج العام (توجيه وتحقق فقط)
hls-core.php         ← محرّك الكاش والنافذة الحية (جديد)
viewers.php          ← إحصاء المتصلين (Redis/APCu/SQLite/ملفات)
override.php         ← القوالب وتبديل القنوات (كما هو)
panel.php            ← لوحة التحكم (كما هي، بلا أي تغيير في التصميم)
error.php            ← صفحة الأخطاء (كما هي)
worker.php           ← خدمة السحب المسبق (VPS فقط، اختيارية)
config.example.php   ← انسخه إلى config.php وعدّله
.htaccess            ← قواعد Apache الجديدة
overrides/           ← القوالب والوسائط كما هي
systemd/ supervisor/ ← ملفات تشغيل الخدمة
loadtest/ tests/     ← أدوات القياس والاختبار (لا تُقرأ من الويب)
```

المجلدات `hls-cache/` و `.tofi-cache/` و `.tofi-viewers/` تُنشأ تلقائيًا عند
أول طلب. تأكد أن مستخدم الويب يملك صلاحية الكتابة في جذر الدومين.

### الروابط العامة لم تتغيّر إطلاقًا

```
GET /watch/{channels}/index.m3u8      (User-Agent: MTX Player)
GET /api/token/{channels}
GET /stream/{channels}/index.m3u8?expires&viewer&root&token
GET /api/viewer/leave/{channels}
GET /hls-cache/{name}.{ext}
```

وأُضيف مساران اختياريان لا يحتاجهما التطبيق الحالي:

```
GET /api/viewer/ping/{channels}       ← تجديد الجلسة كل 10–15 ثانية
GET /api/metrics?key=...              ← قياسات التشغيل (مطفأة افتراضيًا)
```

شكل استجابة `/api/token` كما هو حرفيًا (`success`, `channels`, `url`,
`viewer_id`, `leave_url`, `expires_at`, `expires_in`) مع إضافة `ping_url`
فقط — والإضافة لا تكسر أي عميل.

---

## 2) الإعدادات

```bash
cp config.example.php config.php
```

ثم عدّل ما تحتاجه. أهم ثلاثة أشياء:

### أ) الأسرار

انقل المفاتيح من الكود إلى متغيّرات البيئة أو `config.php`:

```php
'token_secret'          => getenv('TOFI_TOKEN_SECRET') ?: '',
'stream_signing_secret' => getenv('TOFI_STREAM_SIGNING_SECRET') ?: '',
```

على Apache (‎.htaccess‎ أو إعداد الموقع):

```apache
SetEnv TOFI_TOKEN_SECRET "ضع-سرًا-طويلًا-هنا"
SetEnv TOFI_STREAM_SIGNING_SECRET "ضع-سرًا-آخر-هنا"
```

**توافق رجعي:** إن لم تضع أي قيمة، يستخدم النظام المفاتيح القديمة نفسها،
فلا تتعطّل الروابط الموقّعة الصادرة قبل التحديث. غيّرها بعد أن يتحدّث
المستخدمون، لأن تغييرها يُبطل التوكنات الجارية فورًا.

### ب) وضع التشغيل

```php
'mode' => 'request_driven',   // يعمل على أي استضافة، بلا خدمات
'mode' => 'worker',           // VPS: خدمة تسحب وتحمّل مسبقًا
```

### ج) مخزن إحصاء المتصلين

```php
'viewer_backend' => 'auto',   // redis ← apcu ← sqlite ← file
```

على استضافة مشتركة اتركه `auto` (سيختار SQLite أو الملفات).
على VPS مع Redis سيختار Redis تلقائيًا وهو الأفضل.

---

## 3) وضع request_driven (الاستضافة المشتركة)

لا يحتاج أي خدمة خلفية. ارفع الملفات وانتهى الأمر.

ماذا يحدث عند وصول 50 مشاهدًا في اللحظة نفسها؟

1. أول عملية تأخذ قفلًا **غير حاجز** (`LOCK_EX | LOCK_NB`) وتجلب القائمة.
2. باقي العمليات لا تنتظر القفل إطلاقًا: تأخذ آخر نسخة صالحة وترسلها فورًا
   (stale-while-revalidate).
3. إن لم يوجد أي كاش (أول طلب في حياة القناة) تنتظر البقية 400 مللي ثانية
   كحد أقصى ثم تقرأ الملف الذي نشرته العملية الأولى.
4. النتيجة: **اتصال واحد بالمصدر لكل تحديث**، مهما بلغ عدد المشاهدين.

---

## 4) وضع worker (VPS فقط — اختياري بالكامل)

> **لا تحتاج هذا القسم على استضافة مشتركة.** النظام كامل الوظائف بوضع
> `request_driven` وحده. الـ worker تحسين إضافي لا شرط تشغيل.

الخدمة تسحب القوائم باستمرار وتحمّل المقاطع الجديدة **قبل** أن يطلبها أحد،
فيصل المشاهد ويجد كل شيء جاهزًا على القرص.

### systemd

```bash
sudo cp systemd/tofi-hls-worker.service /etc/systemd/system/
sudo nano /etc/systemd/system/tofi-hls-worker.service    # عدّل المسار والقنوات
sudo systemctl daemon-reload
sudo systemctl enable --now tofi-hls-worker
sudo systemctl status tofi-hls-worker
journalctl -u tofi-hls-worker -f
```

### Supervisor

```bash
sudo cp supervisor/tofi-hls-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status tofi-hls-worker
```

### تشغيل يدوي للتجربة

```bash
php worker.php --channels=10,11,20 --verbose
php worker.php --channels=10 --once        # جولة واحدة (مناسب لـ cron)
```

**مهم:** إذا توقفت الخدمة لا يتوقف البث. `index.php` يستخدم الكاش والقفل
نفسيهما، فيعود النظام تلقائيًا إلى `request_driven`.

يكتب الـ worker نبضة حياة في `.tofi-cache/worker.heartbeat` لمراقبته.

---

## 5) متطلبات السيرفر والباندويث

### الحد الأدنى (حتى ~150 مشاهدًا بدون CDN)

| المورد | القيمة |
|---|---|
| المعالج | 2 vCPU |
| الذاكرة | 2 GB |
| PHP | 8.0+ مع `curl`, `openssl`, `json` |
| PHP-FPM | `pm.max_children` ≥ 40 |
| الباندويث | راجع الحساب أدناه |

### الموصى به (500–1000+ مشاهد مع CDN)

| المورد | القيمة |
|---|---|
| المعالج | 4–8 vCPU |
| الذاكرة | 4–8 GB |
| القرص | SSD/NVMe، ‎2–5 GB‎ حرة للكاش |
| PHP-FPM | `pm = dynamic`, `pm.max_children` 80–200 حسب الذاكرة |
| Redis | مثبّت (لإحصاء المتصلين) |
| CDN | Cloudflare أو مثيله أمام `/hls-cache/*` |

### حساب الباندويث (هذا هو العامل الحاسم، وليس المعالج)

```
الباندويث المطلوب ≈ معدل بت البث × عدد المشاهدين
```

| معدل بت البث | 50 مشاهدًا | 200 مشاهد | 1000 مشاهد |
|---|---|---|---|
| 1.5 Mbps | 75 Mbps | 300 Mbps | 1.5 Gbps |
| 2.5 Mbps | 125 Mbps | 500 Mbps | 2.5 Gbps |
| 4 Mbps | 200 Mbps | 800 Mbps | 4 Gbps |

**1000 مشاهد على 2.5 Mbps تعني 2.5 جيجابت/ثانية.** لا يوجد سيرفر مشترك
يتحمل هذا. لذلك:

- **يجب** وضع CDN أمام `/hls-cache/*`. عندها يخرج من سيرفرك نسخة واحدة
  لكل مقطع فقط، ويوزّع CDN الباقي.
- استهلاك سيرفرك مع CDN ≈ (حجم المقاطع الجديدة/ثانية) × 1، أي بضعة
  ميجابت فقط بغض النظر عن عدد المشاهدين.

### حجم الكاش على القرص

```
حجم الكاش ≈ معدل البت ÷ 8 × segment_keep_seconds × عدد القنوات
مثال: 2.5 Mbps ÷ 8 × 300 ثانية ≈ 94 ميجابايت لكل قناة
```

اضبط `segment_keep_seconds` في `config.php` حسب مساحتك.

### الاتصالات بالمصدر (المهم)

بعد هذا التحديث، الاتصالات بالمصدر **لا تتأثر بعدد المشاهدين**:

```
قوائم m3u8  : اتصال واحد كل 0.5–1.5 ثانية لكل قناة
المقاطع     : اتصال واحد لكل مقطع، مرة واحدة فقط، مهما كان عدد المشاهدين
```

---

## 6) إعداد CDN (Cloudflare أو مماثل)

### القاعدة 1 — المقاطع (الأهم)

```
المطابقة : URI Path يبدأ بـ  /hls-cache/
           و امتداد الملف من (ts, m4s, mp4, m4a, aac, vtt)
الإجراء  : Cache Eligibility → Eligible for cache (Cache Everything)
           Edge TTL  → Respect origin (أو 1 يوم)
           Browser TTL → Respect origin
```

اسم المقطع مشتق من بصمة رابطه الفريد، فمحتواه لا يتغيّر أبدًا.
**لا حاجة إلى Purge إطلاقًا.**

### القاعدة 2 — القوائم

```
المطابقة : URI Path ينتهي بـ  .m3u8
الإجراء  : Edge TTL = 1 ثانية   (أو Bypass Cache)
           Browser TTL = No cache
```

قوائم `/stream/...` تُرسل أصلًا بترويسة `no-store` لأنها تحمل توكن كل
مشاهد. قوائم `/hls-cache/*.m3u8` مشتركة بلا بيانات مشاهد وتُرسل
`max-age=1` فيتقاسمها الجميع.

### القاعدة 3 — عدم تخزين الأخطاء والتحويلات

في نفس قاعدة `/hls-cache/*`:

```
Edge TTL → Status code TTL:
    200-299  →  1 day
    300-399  →  No-store      ← مهم جدًا
    400-499  →  No-store
    500-599  →  No-store
```

عند أول طلب لمقطع غير موجود، قد يرد الخادم بتحويل 302 إلى الملف نفسه بعد
حفظه. تخزين هذا التحويل في CDN يسبب حلقة. النظام يرسل معه
`Cache-Control: no-store` و `CDN-Cache-Control: no-store` و
`Cloudflare-CDN-Cache-Control: no-store`، والقاعدة أعلاه تأكيد إضافي.

### القاعدة 4 — مفتاح الكاش (Cache Key)

```
تجاهل في مفتاح الكاش : viewer, root, vproof, r
لا تتجاهل أبدًا      : channels, expires, token, name, ext
```

بعد هذا التحديث لم تعد روابط الملفات المشتركة تحمل `viewer` أو `root` أو
`vproof` إطلاقًا، لكن قد تصل روابط قديمة من نسخ التطبيق السابقة، لذلك
تجاهُلها في مفتاح الكاش يضمن مشاركة الكاش بينها أيضًا.

### القاعدة 5 — منع Cache Deception

```
لا تفعّل "Cache Everything" على المسار الجذر أو على /api/* أو /stream/*
لا تخزّن أي مسار يحمل توكن مشاهد
```

`/api/token` و `/api/viewer/*` و `/stream/*` كلها ترسل `no-store` أصلًا.

### تنبيه

CDN وحده لا يحل مشكلة التزاحم داخل PHP. الحل الأساسي في هذا التحديث هو
Single Flight داخل الكود، وCDN يضاعف الفائدة فوقه.

---

## 7) القياسات (اختيارية)

في `config.php`:

```php
'metrics_enabled' => true,
'metrics_token'   => 'ضع-مفتاحًا-عشوائيًا-طويلًا',
```

ثم:

```bash
curl "https://live-api-tofixtv.tofi-xtv.com/api/metrics?key=المفتاح"
```

النتيجة:

```json
{
  "success": true,
  "mode": "request_driven",
  "active_viewers": 87,
  "viewer_backend": "redis",
  "metrics": {
    "upstream_playlist_fetches": 30,
    "upstream_segment_fetches": 18,
    "playlist_cache_hits": 2165,
    "playlist_cache_misses": 136,
    "segment_cache_hits": 24,
    "segment_first_fetch": 18,
    "lock_contention": 126,
    "stale_playlist_served": 102,
    "playlist_wait_resolved": 11,
    "segment_wait_timeout": 0
  }
}
```

القراءة الصحيحة: `playlist_cache_hits` أكبر بكثير من
`upstream_playlist_fetches` = الكاش مشترك ويعمل.
لا تُسجَّل أي أسرار أو توكنات في القياسات أو السجلات.

أضف `&reset=1` لتصفير العدّادات بعد القراءة.

---

## 8) اختبار الحمل

### باستخدام k6 (موصى به للأرقام النهائية)

```bash
k6 run -e BASE=https://live-api-tofixtv.tofi-xtv.com \
       -e CHANNELS=10 -e PROFILE=50 \
       -e METRICS_KEY=مفتاح-القياسات \
       loadtest/hls-loadtest.js

# ثم 200 ثم 1000
k6 run ... -e PROFILE=200  loadtest/hls-loadtest.js
k6 run ... -e PROFILE=1000 loadtest/hls-loadtest.js
```

### بدون تثبيت أي أداة

```bash
php loadtest/hls-loadtest.php \
    --base=https://live-api-tofixtv.tofi-xtv.com \
    --channels=10 --viewers=200 --duration=60 \
    --metrics-key=مفتاح-القياسات
```

كلا الاختبارين يحاكيان مشغّل HLS حقيقيًا: توكن ← تتبّع التحويل ← قراءة
Master ← تحديث Media Playlist باستمرار ← تنزيل المقاطع الجديدة فقط.

**اختبر 1000 مشاهد من أكثر من جهاز.** جهاز واحد غالبًا يصل إلى حدّ منافذه
قبل أن يصل سيرفرك إلى حدّه، فتقيس جهازك لا سيرفرك.

---

## 9) ضبط PHP-FPM و Apache

```ini
; php.ini
memory_limit = 256M
max_execution_time = 30
opcache.enable = 1
opcache.memory_consumption = 128
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
```

```ini
; www.conf (PHP-FPM)
pm = dynamic
pm.max_children = 80
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 24
pm.max_requests = 2000
request_terminate_timeout = 30s
```

بما أن المقاطع لم تعد تمر عبر PHP، فإن عدد العمال المطلوب انخفض كثيرًا:
عامل PHP يعمل فقط عند تحديث قائمة، أو عند أول طلب لمقطع جديد.

على Apache تأكد من تفعيل: `mod_rewrite`, `mod_headers`, `mod_mime`.

---

## 10) التحقق بعد الرفع

```bash
# 1) التوكن
curl -A "MTX Player" https://live-api-tofixtv.tofi-xtv.com/api/token/10

# 2) الرابط الثابت للتطبيق (يجب أن يرد 302)
curl -I -A "MTX Player" https://live-api-tofixtv.tofi-xtv.com/watch/10/index.m3u8

# 3) القائمة (خذ url من الخطوة 1)
curl "<url>"

# 4) المقطع: أول طلب 200 أو 302، والثاني يجب أن يأتي من الملف الثابت
curl -I "https://live-api-tofixtv.tofi-xtv.com/hls-cache/<name>.ts"

# 5) الحماية (كلها يجب أن ترد 403)
curl -I https://live-api-tofixtv.tofi-xtv.com/
curl -I https://live-api-tofixtv.tofi-xtv.com/hls-core.php
curl -I https://live-api-tofixtv.tofi-xtv.com/config.php
curl -I https://live-api-tofixtv.tofi-xtv.com/.tofi-cache/
```

في الخطوة 4 ابحث عن `X-Tofi-Cache: MISS` في أول طلب فقط. الطلبات التالية
يجب ألا تحمل هذه الترويسة إطلاقًا — لأنها تأتي من Apache مباشرة بلا PHP.

إذا ظهرت `X-Tofi-Cache: FALLBACK` فهذا يعني أن قاعدة الملفات الثابتة في
`.htaccess` غير مفعّلة (mod_rewrite مطفأ أو `AllowOverride None`). راجع
إعداد الخادم فورًا، لأن البث سيعمل لكن بأداء أضعف بكثير.

---

## 11) استكشاف الأعطال

| العرض | السبب المرجّح | الحل |
|---|---|---|
| `X-Tofi-Cache: FALLBACK` في السجل | `.htaccess` غير فعّال | فعّل `AllowOverride All` و `mod_rewrite` |
| 502 على كل القنوات | المصدر لا يستجيب | جرّب رابط المصدر مباشرة من السيرفر |
| 503 على مقطع واحد | تزاحم على مقطع جديد | طبيعي ونادر؛ زد `segment_wait_ms` قليلًا |
| القائمة لا تتحدث | القرص ممتلئ أو بلا صلاحية كتابة | تحقق من صلاحيات `hls-cache/` و `.tofi-cache/` |
| عدد المتصلين صفر | مخزن الإحصاء غير قابل للكتابة | راجع `viewer_backend` وصلاحيات `.tofi-viewers/` |
| القرص يمتلئ | `segment_keep_seconds` كبير | خفّضه إلى 180 مثلًا |

للتشخيص السريع:

```bash
tail -f /var/log/apache2/error.log | grep "ToFi HLS"
```

لا يُسجَّل أي رابط مصدر أو توكن كامل في السجلات.
