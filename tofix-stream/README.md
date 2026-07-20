# ToFi X Stream 🎥

منصّة بثّ احترافية ذاتية الاستضافة مبنيّة بـ **PHP 8.3+** خالصة (بدون Laravel / Node.js / React / Vue).
تُعيد بثّ روابط **HLS (m3u8)** و **MPEG-DASH (mpd)** الخاصّة بك عبر **بروكسي ذكي** يُخفي المصدر الأصلي،
وتُدار بالكامل من **لوحة تحكّم Glassmorphism** شبيهة بـ Gumlet / Mux / Cloudflare Stream.

> إعادة بثّ + إخفاء المصدر + إدارة قنوات غير محدودة + مشغّل متعدّد المحرّكات + REST API + FFmpeg.

---

## ✨ المميّزات

| المجال | التفاصيل |
|--------|----------|
| **إعادة البثّ** | تحويل `https://source/live.m3u8` إلى `https://yourdomain/proxy/index.php?channel=ID` |
| **البروكسي الذكي** | إعادة كتابة كاملة للمانيفست: كل روابط `ts` / `m4s` / `mp4` / المفاتيح / البلاي-ليست الفرعية تمرّ عبر سيرفرك — **لا يُكشف المصدر أبدًا** |
| **الأمان** | روابط موقّعة HMAC، حماية SSRF، توكن + انتهاء صلاحية، Hotlink Protection، IP Allowlist، Rate Limit، مفتاح API |
| **FFmpeg** | تشغيل/إيقاف/إعادة تشغيل، نسخ (copy) أو إعادة ترميز، إعادة اتصال تلقائي، Auto-Restart، Detect-Offline |
| **العلامة المائية** | دمج شعار (صورة) أو نصّ **داخل الفيديو نفسه** (يظهر لكل المشاهدين وفي أي مشغّل)، مع اختيار الموضع/الحجم/الشفافية |
| **المصادر المدعومة** | m3u8, mpd, mp4, rtmp, udp, http, https |
| **الجودات** | Source, 1080p, 720p, 480p, 360p, 240p |
| **المشغّل** | Video.js + Hls.js + Shaka — PiP، Fullscreen، AirPlay، Chromecast، سرعات، ترجمات، تعدّد صوت |
| **اللوحة** | بطاقات إحصائيات، مراقبة حيّة (Bitrate/FPS/Resolution/Codecs)، مؤشّرات الخادم (CPU/RAM/Storage) |
| **REST API** | CRUD كامل بصيغة JSON موحّدة |
| **التخزين** | JSON مع قفل ملفّات (بدون قاعدة بيانات) |

---

## 📁 هيكل المشروع

```
tofix-stream/
├── index.php                 # يعيد التوجيه إلى لوحة التحكّم
├── bootstrap.php             # المُحمِّل التلقائي + تحميل الإعدادات + التهيئة
├── config/
│   ├── config.php            # كل الإعدادات (قابلة للتجاوز بمتغيّرات البيئة)
│   └── nginx.conf.example    # نموذج إعداد Nginx
├── classes/                  # النواة (OOP / SOLID)
│   ├── Config.php            # قراءة الإعدادات بأسلوب النقطة
│   ├── Logger.php            # تسجيل الأحداث في ملفّات يومية
│   ├── JsonStore.php         # طبقة تخزين JSON مع قفل (Repository)
│   ├── Channel.php           # كيان القناة + التحقّق من الصحّة
│   ├── ChannelManager.php    # خدمة CRUD للقنوات + بناء الروابط
│   ├── HlsProxy.php          # ★ البروكسي الذكي وإعادة كتابة المانيفست
│   ├── FFmpegManager.php     # إدارة عمليات FFmpeg (PID/start/stop)
│   ├── StreamMonitor.php     # مقاييس ffprobe (bitrate/fps/codecs)
│   ├── StreamSupervisor.php  # Auto-Restart + Detect-Offline (يعمل عبر Cron)
│   ├── SystemStats.php       # مؤشّرات الخادم (CPU/RAM/Storage)
│   ├── Security.php          # التوقيع/التوكن/Rate-limit/Hotlink/IP
│   └── Response.php          # استجابات JSON موحّدة
├── api/
│   └── index.php             # موجّه REST API
├── proxy/
│   └── index.php             # ★ نقطة دخول البروكسي (الرابط الجديد للمشاهدين)
├── public/
│   ├── index.php             # لوحة التحكّم (Dashboard)
│   ├── player.php            # المشغّل الاحترافي (3 محرّكات)
│   └── embed.php             # مشغّل خفيف للتضمين عبر iframe
├── assets/
│   ├── css/app.css           # تصميم اللوحة (Glassmorphism)
│   ├── css/player.css        # تصميم المشغّل
│   ├── js/app.js             # منطق اللوحة (AJAX/CRUD)
│   └── js/player.js          # تهيئة محرّكات التشغيل
├── cron/
│   └── supervisor.php        # سكربت Cron للمراقبة وإعادة التشغيل
├── storage/                  # ملفّات JSON (channels.json) + pids/
├── streams/                  # مخرجات HLS من FFmpeg
├── cache/                    # كاش البروكسي + عدّادات Rate-limit
├── logs/                     # السجلّات
├── .htaccess                 # إعداد Apache + رؤوس أمان
└── .env.example              # نموذج متغيّرات البيئة
```

---

## 🚀 التشغيل السريع

### المتطلّبات
- PHP 8.3+ مع إضافة `curl`
- (اختياري) `ffmpeg` و `ffprobe` لإعادة الترميز والمراقبة التقنية
- Apache (mod_rewrite) أو Nginx (php-fpm)

### محليًّا (خادم PHP المدمج)
```bash
cd tofix-stream
APP_URL="http://localhost:8080" php -S 127.0.0.1:8080
# افتح لوحة التحكّم:
#   http://localhost:8080/public/index.php
```

### الإنتاج
1. انسخ المجلّد إلى جذر الويب (مثال `/var/www/tofix-stream`).
2. انسخ `.env.example` إلى `.env` وعدّل على الأقل:
   - `APP_URL` — نطاقك العام.
   - `APP_SECRET` — مفتاح عشوائي طويل (لتوقيع الروابط).
   - `API_KEY` — مفتاح لوحة التحكّم.
3. اجعل `storage/`, `streams/`, `cache/`, `logs/` قابلة للكتابة (`chmod -R 775`).
4. لـ Nginx استخدم `config/nginx.conf.example`.
5. (اختياري) فعّل المراقبة التلقائية عبر Cron:
   ```cron
   * * * * * /usr/bin/php /var/www/tofix-stream/cron/supervisor.php >> /var/www/tofix-stream/logs/cron.log 2>&1
   ```

---

## 🔌 REST API

كل الاستجابات JSON بالشكل: `{ "success": bool, "data": ..., "error": ... }`.
عمليات الكتابة (POST/PUT/DELETE) تتطلّب رأس `X-API-Key`.

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `/api/index.php?resource=channels` | قائمة القنوات |
| `GET` | `/api/index.php?resource=channels&id=ID` | قناة واحدة |
| `POST` | `/api/index.php?resource=channels` | إنشاء قناة (JSON body) |
| `PUT` | `/api/index.php?resource=channels&id=ID` | تحديث قناة |
| `DELETE` | `/api/index.php?resource=channels&id=ID` | حذف قناة |
| `POST` | `/api/index.php?resource=channels&action=duplicate&id=ID` | تكرار |
| `POST` | `/api/index.php?resource=stream&action=start&id=ID` | تشغيل FFmpeg |
| `POST` | `/api/index.php?resource=stream&action=stop&id=ID` | إيقاف |
| `POST` | `/api/index.php?resource=stream&action=restart&id=ID` | إعادة تشغيل |
| `GET` | `/api/index.php?resource=stream&action=status&id=ID` | حالة البثّ |
| `GET` | `/api/index.php?resource=stream&action=monitor&id=ID` | مقاييس ffprobe |
| `GET` | `/api/index.php?resource=stream&action=test&id=ID` | اختبار المصدر (حيّ؟ رمز HTTP؟ مانيفست صالح؟) |
| `GET` | `/api/index.php?resource=stats` | إحصائيات اللوحة |
| `GET` | `/api/index.php?resource=system` | مؤشّرات الخادم |

### مثال — إنشاء قناة
```bash
curl -X POST "http://localhost:8080/api/index.php?resource=channels" \
  -H "X-API-Key: tofix_admin_key_change_me" \
  -H "Content-Type: application/json" \
  -d '{
        "name": "My Sports HD",
        "source_url": "https://stream.gumlet.io/.../main.m3u8",
        "source_type": "m3u8",
        "category": "Sports",
        "quality": "source",
        "mode": "proxy"
      }'
```
الاستجابة تتضمّن `playback.hls` — وهو **رابط البثّ الجديد الذي تعطيه للمشاهدين** (المصدر مخفيّ).

---

## 🧠 كيف يعمل البروكسي الذكي؟

```
المشاهد → /proxy/index.php?channel=ID
              │
              ▼
   ChannelManager يجلب الرابط الأصلي من التخزين
              │
              ▼
   HlsProxy يسحب المانيفست عبر cURL (بهويّة السيرفر، مع انتحال Referer)
              │
              ▼
   إعادة كتابة كل الروابط الداخلية إلى روابط موقّعة:
     /proxy/index.php?u=<base64url(الرابط الأصلي)>&s=<HMAC>
              │
              ▼
   المشاهد يطلب كل مقطع عبر نفس البروكسي → لا يرى المصدر إطلاقًا
```

- التوقيع `HMAC-SHA256` يمنع حقن روابط خارجية (**حماية SSRF**): أي رابط غير موقّع أو بغير بروتوكول `http/https` يُرفض.
- يدعم الروابط النسبية والمطلقة وروابط جذر النطاق و`//cdn`.
- يعالج وسوم `EXT-X-KEY` و`EXT-X-MAP` و`EXT-X-MEDIA` (سمة `URI=`) في HLS، و`BaseURL` وقوالب `media`/`initialization` في DASH.

### اتصال واحد بالمصدر مهما كثر المشاهدون (لروابط IPTV أحادية الاتصال) ⭐

روابط IPTV غالبًا **محدودة باتصال/مستخدم واحد**؛ لو دخل 100 مشاهد على رابطك مباشرة لضربوا المصدر
100 مرّة وحُظر الرابط. يحلّ البروكسي هذا عبر **كاش + دمج الطلبات (request coalescing)**:

```
100 مشاهد  ──►  Proxy HLS  ──►  اتصال واحد فقط بالمصدر
   (كاش على القرص + قفل ذرّي يمنع الطلبات المتزامنة المكرّرة)
```

- **المانيفست** يُكاش لثوانٍ قليلة (`PROXY_MANIFEST_TTL`): كل المشاهدين خلال النافذة يقرأون نسخة واحدة.
- **كل مقطع** (ts/m4s) يُجلب من المصدر **مرّة واحدة**، يُخزَّن على القرص، ويُخدَّم لكل المشاهدين
  (`PROXY_SEGMENT_CACHE_TTL`، افتراضيًا 120 ثانية).
- **دمج الطلبات**: عند طلب 100 مشاهد نفس المقطع في اللحظة نفسها، يأخذ أوّلهم قفلًا ويجلب المقطع،
  وينتظر الباقون ثم يقرؤون من الكاش — فيرى المصدر **اتصالًا واحدًا** لا 100.

> مُختبَر فعليًا: 100 طلب متزامن على نفس المقطع ⟵ المصدر تلقّى **اتصالًا واحدًا فقط**.
> عطّل الكاش بـ `PROXY_CACHE=false` إن أردت تمريرًا مباشرًا.

---

## 🖼️ العلامة المائية داخل البثّ (Logo / Text Overlay)

هناك طريقتان لعرض الشعار، تُختاران حسب **وضع القناة**:

| الوضع | كيف يظهر الشعار | يتطلّب FFmpeg/exec؟ | يظهر في تطبيقات IPTV الخارجية؟ |
|-------|------------------|:---:|:---:|
| **Proxy** | طبقة فوق مشغّل ToFi و embed (HTML) — يدعم SVG والعربية | ❌ لا | ❌ داخل مشغّلنا فقط |
| **FFmpeg** | محروق داخل ملف الفيديو نفسه | ✅ نعم | ✅ في كل المشغّلات |

> إن كانت `exec` معطّلة في استضافتك (شائع في الاستضافة المشتركة)، استخدم **وضع Proxy** — الشعار
> سيظهر كطبقة فوق المشغّل و embed بدون أي حاجة إلى FFmpeg.

في وضع FFmpeg يمكنك حرق شعار أو نصّ **داخل الفيديو نفسه** بحيث يظهر لكل مشاهد في أي مشغّل أو تطبيق:

1. عند إضافة/تعديل قناة فعّل **«إضافة شعار / نصّ داخل البثّ»**.
2. اختر النوع:
   - **صورة**: ارفع شعارك (يُفضّل PNG بخلفية شفّافة) أو الصق رابط صورة.
   - **نصّ**: اكتب النصّ واختر لونه (للنصّ العربي استخدم صورة، لأن FFmpeg لا يشكّل الحروف العربية).
3. اضبط الموضع والحجم والشفافية، ثم احفظ.
4. العلامة تُدمج عبر **FFmpeg**، لذا تتحوّل القناة تلقائيًا لوضع FFmpeg — شغّل البثّ من زرّ
   <kbd>Broadcast</kbd> ليبدأ الإنتاج ويظهر الشعار داخل الفيديو على رابط `.m3u8` نفسه.

> يتطلّب تثبيت `ffmpeg` على الخادم (مع `libfreetype` لعرض النصّ). العلامة على الصورة تعمل بأي بناء FFmpeg قياسي.
> شعار SVG يُحوَّل تلقائيًا إلى PNG عبر Imagick أو rsvg-convert، أو يقرأه FFmpeg مباشرة إن بُني مع `librsvg` — والأضمن رفع شعار PNG بخلفية شفّافة.

### لماذا لا يبدأ البثّ / لا يظهر الشعار؟ (التشخيص)

تعرض اللوحة **شريط تشخيص** أعلى الصفحة يوضّح جاهزية الخادم، أو استعلم مباشرة:
```
GET /api/index.php?resource=diagnostics
```
يُرجع حالة: `exec_enabled` (تفعيل exec/shell_exec)، `ffmpeg`، `ffprobe`، `imagick`، `streams_writable`.

- **exec معطّلة** (شائع في الاستضافة المشتركة CloudLinux/LiteSpeed): لا يمكن تشغيل FFmpeg —
  إعادة البثّ الحقيقي والشعار داخل الفيديو لن تعمل. استخدم **وضع Proxy** (يعمل دائمًا)، أو
  انقل لخادم/VPS يسمح بـ exec.
- **ffmpeg غير مثبّت**: ثبّته (`apt install ffmpeg`) أو اضبط `FFMPEG_BIN`.
- **streams/ غير قابلة للكتابة**: `chmod -R 775 streams`.

> ملاحظة: العلامة المائية والجودة (transcode) تعملان فقط في **وضع FFmpeg** بعد تشغيل البثّ.
> وضع **Proxy** يعيد بثّ الرابط ويخفي المصدر لكن دون دمج شعار (لأنه لا يعيد ترميز الفيديو).

## 🛡️ ملاحظات أمان للإنتاج

- **غيّر** `APP_SECRET` و`API_KEY` إلى قيم عشوائية طويلة.
- فعّل `HOTLINK_PROTECTION` واضبط `ALLOWED_REFERERS` لمنع سرقة الروابط.
- استخدم HTTPS دائمًا؛ الرؤوس الأمنية مضبوطة في `.htaccess` / نموذج Nginx.
- مجلّدات `storage/`, `logs/`, `config/`, `classes/` محجوبة عن الوصول المباشر.

---

## ⚖️ تنبيه قانوني
أعد بثّ المحتوى الذي تملك حقوقه أو المصرّح لك به فقط. أنت مسؤول عن استخدامك للمنصّة.

**ToFi X Stream** — صُنع بحبّ للبثّ. 🚀
