# قمهد لايف — واجهة الـ API (PHP) للرفع على api.tofi-xtv.com

هذه هي حزمة الخادم الكاملة التي يعتمد عليها تطبيق Android. وهي نفس مشروع الموقع
(PHP بدون قاعدة بيانات — كل البيانات من واجهة النتائج وتُخزَّن مؤقتاً على القرص)،
مضافاً إليها نقاط JSON التي يستهلكها التطبيق مباشرة.
(توثيق الموقع الأصلي الكامل في `README-original-site.md`.)

## الرفع

ارفع محتوى هذا المجلد إلى الاستضافة بحيث يكون **`public/`** هو جذر الويب
(document root) للنطاق `api.tofi-xtv.com`.

```
backend-api/
├── public/                 ← اجعله document root
│   ├── index.php           الموجّه الأمامي (front controller) + كل صفحات الموقع
│   ├── api/                نقاط JSON للتطبيق
│   │   ├── matches.php         ?date=YYYY-MM-DD          مباريات اليوم
│   │   ├── live.php            المباريات المباشرة
│   │   ├── news.php            ?page=N | ?id=N           الأخبار
│   │   ├── standings.php       ?league=URL_ID            الترتيب + الهدافين
│   │   ├── team.php            ?id=N                     الفريق (مباريات + تشكيلة)
│   │   ├── player.php          ?id=N                     اللاعب
│   │   ├── leagues.php   ★     البطولات (للتطبيق)
│   │   ├── videos.php    ★     ?champ=&page=&q=&id=      الفيديوهات (JSON)
│   │   ├── channels.php  ★     القنوات + روابط البث
│   │   ├── search.php    ★     ?q=QUERY                  البحث (لاعبون + فرق)
│   │   └── match_info.php ★    ?id=N                     مركز المباراة (مع الأحداث)
│   └── .well-known/assetlinks.json   للتحقّق من App Links
├── app/                    منطق التطبيق (Core/Controllers/Views)
├── storage/                الكاش والإعدادات (قابلة للكتابة)
└── database/schema.sql     (اختياري)
```

`★` = نقاط أضيفت خصيصاً للتطبيق. البقية كانت أصلاً في الموقع.

## التهيئة

- **النطاق:** اضبط متغيّر البيئة قبل التشغيل حتى تُبنى الروابط على النطاق الجديد:
  ```
  QAMHAD_SITE_URL=https://api.tofi-xtv.com
  ```
- **مصدر البيانات:** الإعدادات في `app/config.php` (`API_BASES`) تشير إلى واجهة
  النتائج العلوية (ysscores). الخادم يعمل كوسيط (proxy) + كاش، والتطبيق يتحدّث فقط
  مع `api.tofi-xtv.com` — أي أنّ `api.tofi-xtv.com` هو المصدر الأساسي للتطبيق.
- **رؤوس مكافحة الحظر:** إن انتهت صلاحية نسخة التطبيق العلوية، عدّل
  `API_APP_VERSION` / `API_APP_VERSIONNAME` في `app/config.php` (مكان واحد).
- **الصلاحيات:** اجعل `storage/` قابلاً للكتابة (`chmod -R 775 storage`).
- **PHP:** 8.1+ مع إضافات `curl`, `mbstring`, `gd` (لتحويل الصور WebP).

## الإشعارات (FCM)

- التطبيق يشترك في مواضيع FCM: `all` وكل `lg_{url_id}`.
- لوحة الإدارة (`/admin` → الإشعارات) تدير الإرسال. اضبط
  `storage/settings/service-account.json` بحساب خدمة Firebase، ثم يستطيع الخادم
  إرسال إشعارات المباريات إلى المواضيع نفسها التي يشترك بها التطبيق.
- الكرون: `curl -s https://api.tofi-xtv.com/cron/notify?key=SECRET` كل دقيقة.

## التحقّق من الإجابة (envelope)

كل نقاط `/api/*.php` تُعيد:

```json
{ "ok": true, "stale": false, "lang": "ar", "count": 12, "data": [ ... ] }
```

جرّب بعد الرفع:

```bash
curl "https://api.tofi-xtv.com/api/matches.php?date=$(date +%F)&lang=ar"
curl "https://api.tofi-xtv.com/api/leagues.php"
curl "https://api.tofi-xtv.com/api/videos.php?champ=all&page=1"
```
