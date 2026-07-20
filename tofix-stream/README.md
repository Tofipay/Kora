# ToFi X Stream 🎥

**بروكسي HLS/IPTV احترافي** ذاتي الاستضافة مبني بـ **PHP 8.3+** خالص (بدون Laravel / Node.js / React / Vue / FFmpeg).
يعيد بثّ روابط **HLS (m3u8)** و **MPEG-TS (ts)** و **DASH (mpd)** الخاصّة بك عبر **بروكسي ذكي** يُخفي المصدر الأصلي،
ويُدار بالكامل من **لوحة تحكّم Glassmorphism** شبيهة بـ Gumlet / Mux / Cloudflare Stream.

> إعادة بثّ + إخفاء المصدر + **اتصال واحد بالمصدر مهما كثر المشاهدون** + رابط `.m3u8`/`.ts` نظيف + REST API.

---

## ✨ المميّزات

| المجال | التفاصيل |
|--------|----------|
| **إعادة البثّ** | تحويل `http://server/live/USER/PASS/564.m3u8` (أو `.ts`) إلى `https://yourdomain/stream/ID/index.m3u8` |
| **البروكسي الذكي** | إعادة كتابة كاملة للمانيفست: كل روابط `ts`/`m4s`/المفاتيح/البلاي-ليست الفرعية تمرّ عبر سيرفرك — **لا يُكشف المصدر أبدًا** |
| **اتصال واحد بالمصدر** | كاش + دمج الطلبات: 100 مشاهد = **اتصال واحد** بالمصدر (مثالي لروابط IPTV أحادية الاتصال) |
| **بثّ TS مباشر** | تمرير حيّ لروابط `.ts` المستمرّة دون تخزين |
| **User-Agent مخصّص** | لكل قناة UA خاصّ (VLC افتراضيًا) — يُمرَّر للمانيفست **والمقاطع** معًا لتوافق IPTV |
| **اختبار المصدر** | فحص من الخادم: هل الرابط حيّ؟ رمز HTTP؟ مانيفست صالح؟ **وهل المقاطع تعمل؟** |
| **الأمان** | روابط موقّعة HMAC، حماية SSRF، توكن + انتهاء، Hotlink Protection، IP Allowlist، Rate Limit |
| **المصادر المدعومة** | m3u8, ts, mpd, mp4, http, https |
| **المشغّل** | Hls.js (افتراضي) + Video.js + Shaka + **mpegts.js** لبثّ TS — PiP، Fullscreen، AirPlay، Chromecast |
| **الشعار** | شعار/نصّ كطبقة فوق مشغّل ToFi و embed (يدعم SVG والعربية) |
| **REST API** | CRUD كامل بصيغة JSON موحّدة |
| **التخزين** | JSON مع قفل ملفّات (بدون قاعدة بيانات) |

---

## 📁 هيكل المشروع

```
tofix-stream/
├── index.php                 # يعيد التوجيه إلى لوحة التحكّم
├── router.php                # موجّه الروابط النظيفة لخادم PHP المدمج
├── bootstrap.php             # المُحمِّل التلقائي + تحميل الإعدادات + التهيئة
├── config/
│   ├── config.php            # كل الإعدادات (قابلة للتجاوز بمتغيّرات البيئة)
│   └── nginx.conf.example    # نموذج إعداد Nginx
├── classes/                  # النواة (OOP / SOLID)
│   ├── Config.php            # قراءة الإعدادات + اكتشاف النطاق تلقائيًا
│   ├── Logger.php            # تسجيل الأحداث
│   ├── JsonStore.php         # طبقة تخزين JSON مع قفل (Repository)
│   ├── Channel.php           # كيان القناة + التحقّق من الصحّة
│   ├── ChannelManager.php    # خدمة CRUD للقنوات + بناء الروابط
│   ├── HlsProxy.php          # ★ البروكسي: إعادة الكتابة + الكاش + دمج الطلبات + بثّ ts + اختبار المصدر
│   ├── SystemStats.php       # مؤشّرات الخادم (CPU/RAM/Storage)
│   ├── Security.php          # التوقيع/التوكن/Rate-limit/Hotlink/IP
│   └── Response.php          # استجابات JSON موحّدة
├── api/
│   └── index.php             # موجّه REST API
├── proxy/
│   └── index.php             # ★ نقطة دخول البروكسي (الرابط الجديد للمشاهدين)
├── public/
│   ├── index.php             # لوحة التحكّم (Dashboard)
│   ├── player.php            # المشغّل (Hls.js/Video.js/Shaka/mpegts.js)
│   └── embed.php             # مشغّل خفيف للتضمين عبر iframe
├── assets/ (css, js, watermarks/)
├── storage/                  # channels.json
├── cache/                    # كاش المانيفست والمقاطع + Rate-limit
└── logs/                     # السجلّات
```

---

## 🚀 التشغيل السريع

### المتطلّبات
- PHP 8.3+ مع إضافة **cURL** مفعّلة.
- Apache (mod_rewrite) أو Nginx (php-fpm). **لا حاجة لـ FFmpeg إطلاقًا.**

### محليًّا
```bash
cd tofix-stream
php -S 0.0.0.0:8080 router.php
# لوحة التحكّم:  http://localhost:8080/public/index.php
# رابط البثّ:    http://localhost:8080/stream/CHANNEL_ID/index.m3u8
```

### الإنتاج
1. انسخ المجلّد إلى جذر الويب (مثال `/var/www/tofix-stream`).
2. انسخ `.env.example` إلى `.env` وعدّل على الأقل: `APP_URL` (أو اتركه فارغًا للاكتشاف التلقائي)، `APP_SECRET`، `API_KEY`.
3. اجعل `storage/`, `cache/`, `logs/` قابلة للكتابة (`chmod -R 775`).
4. لـ Nginx استخدم `config/nginx.conf.example`. الرابط النظيف `/stream/ID/index.m3u8` يعمل عبر إعادة الكتابة.

---

## 🔌 REST API

كل الاستجابات JSON: `{ "success": bool, "data": ..., "error": ... }`. عمليات الكتابة تتطلّب رأس `X-API-Key`.

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `?resource=channels` | قائمة القنوات |
| `POST` | `?resource=channels` | إنشاء قناة (JSON body) |
| `PUT` | `?resource=channels&id=ID` | تحديث قناة |
| `DELETE` | `?resource=channels&id=ID` | حذف قناة |
| `POST` | `?resource=channels&action=duplicate&id=ID` | تكرار |
| `GET` | `?resource=stream&action=test&id=ID` | **اختبار المصدر** (مانيفست + مقطع) |
| `GET` | `?resource=stats` / `?resource=system` / `?resource=diagnostics` | إحصائيات/الخادم/تشخيص |
| `POST` | `?resource=upload` | رفع صورة شعار |

---

## 🧠 كيف يعمل «الاتصال الواحد»؟

```
100 مشاهد ──► /stream/ID/index.m3u8 ──► Proxy ──► اتصال واحد فقط بالمصدر
```
- **المانيفست** يُكاش لثوانٍ قليلة (`PROXY_MANIFEST_TTL`) — كل المشاهدين يقرؤون نسخة واحدة.
- **كل مقطع** يُجلب مرّة واحدة، يُخزَّن على القرص، ويُخدَّم للجميع (`PROXY_SEGMENT_CACHE_TTL`).
- **دمج الطلبات (flock)**: عند طلب متزامن لنفس المقطع، أوّل طلب يجلبه والباقون يقرؤون من الكاش.
- **مُختبَر**: 100 طلب متزامن ⟵ المصدر تلقّى **اتصالًا واحدًا فقط**.

---

## 🛠️ لماذا لا يشتغل رابط IPTV؟ (التشخيص)

استخدم زرّ **«اختبار المصدر»** 💓 في اللوحة (أو `?resource=stream&action=test&id=ID`). يفحص من خادمك:
المانيفست **وأوّل مقطع** معًا، فيخبرك بالضبط:

- ✅ **يعمل بالكامل** → رابط البثّ جاهز.
- ⚠️ **المانيفست يعمل لكن المقاطع محجوبة** → المصدر يربط التوكن بمشغّل معيّن.
  الحلّ: اضبط **User-Agent مخصّص** للقناة (مثل `VLC/3.0.20` أو `IPTVSmartersPlayer` أو `okhttp`).
- ❌ **غير متاح / 403** → الرابط منتهٍ أو محمي بـ IP.

> نصيحة: أغلب سيرفرات IPTV تقبل `VLC` (الافتراضي). إن رفضت، جرّب UA المشغّل الذي يعمل معك عادةً.
> رابط `http` على موقع `https` يُحجب كـ Mixed Content — لهذا **استخدم رابط البروكسي دائمًا** (https من نطاقك).

---

## ⚖️ تنبيه قانوني
أعد بثّ المحتوى الذي تملك حقوقه أو المصرّح لك به فقط. أنت مسؤول عن استخدامك للمنصّة.

**ToFi X Stream** — Pure PHP Streaming Proxy. 🚀
