<div dir="rtl">

# تقرير تحويل الهوية — من ToFi X Tv إلى ALOKA Live

تم تحويل هوية المشروع بالكامل إلى **ALOKA Live** مع الحفاظ الكامل على التصميم والوظائف
وقواعد البيانات (ملفات JSON) وواجهات API ولوحة الإدارة والمشغّل ونظام القنوات والمباريات
والأفلام والمسلسلات والأخبار وجميع الصفحات. لم يُحذف أي ملف أو ميزة.

---

## 1) ملخص الاستبدالات

| العنصر | القديم | الجديد |
|---|---|---|
| اسم الموقع (عربي/إنجليزي) | `توفي إكس تيفي` / `ToFi X Tv` | `ALOKA Live` |
| اللون الرئيسي | `#0f172a` (كحلي) | `#1B0761` (بنفسجي داكن) |
| اللون الفرعي / الأزرار | `#3b82f6` (أزرق) | `#4C0ECD` (بنفسجي ساطع) |
| قناة تيليجرام | `t.me/tofixtv` , `t.me/tofix_tv` , `@tofixtv` | `https://t.me/alokalive` , `@alokalive` |
| رابط الموقع | `https://tofi-xtv.com` | `https://aloka-code.shop/` |
| رابط تحميل التطبيق | `https://apk.tofi-xtv.com` | `https://t.me/alokalive` |
| اسم الحزمة (Package) | `com.tofixtv.app` | `com.aloka.live.app` |
| البريد/التواصل | `info@tofi-xtv.com` (mailto) | زر «تواصل عبر تيليجرام» → `https://t.me/alokalive` |
| المتغير العام في JS | `window.TOFIXTV` | `window.ALOKA` |
| إصدار البناء (Cache bust) | `2026-07-21-v5.0` | `2026-07-23-aloka-v1` |

### لوحة ألوان ALOKA المشتقة (مضافة في `:root` داخل `app.css`)
```css
--aloka-primary: #1B0761;
--aloka-secondary: #4C0ECD;
--aloka-primary-dark: #10033D;
--aloka-primary-light: #2A0A83;
--aloka-secondary-hover: #5D1AE0;
--aloka-glow: rgba(76, 14, 205, .40);
```
تم إبقاء ألوان الحالات كما هي: الأحمر للبث المباشر، الأخضر للنجاح/الفوز، الأصفر للتحذير،
وألوان النتائج ورسائل الخطأ، بالإضافة إلى أزرق تيليجرام الأصلي في زر تيليجرام.

---

## 2) الشعارات والصور الجديدة

تم توليد جميع أصول العلامة من ملفات ALOKA المرفقة ووضعها في نفس المسارات القديمة
(لا حاجة لتغيير أي رابط في الكود) داخل `public/assets/brand/`:

**SVG:** `logo.svg` , `logo-en.svg` (أفقي بنص داكن للخلفية الفاتحة) — `logo-dark.svg` ,
`logo-en-dark.svg` (أفقي بنص أبيض للخلفية الداكنة) — `favicon.svg` , `icon.svg` ,
`icon-maskable.svg` (أيقونة التطبيق المربعة) — `og.svg` , `cover.svg` , `splash.svg` (خلفية بنفسجية).

**PNG/ICO:** `favicon-32.png` , `favicon.png` , `icon-192.png` , `icon-512.png` ,
`icon-maskable-512.png` , `og-default.png` , `social-cover.png` , `splash.png` , و`public/favicon.ico`.

روعيت الشفافية والأبعاد ونسبة العرض (SVG يحافظ على النسبة تلقائياً بلا تشويه) وعدم وضع
خلفية بيضاء خلف الشعار. أُبقيت أسماء الملفات كما هي لضمان عدم وجود روابط 404.

---

## 3) الملفات المعدّلة (97 ملفاً + هذا التقرير)

- **الإعدادات المركزية:** `app/config.php` (الاسم، الألوان، الدومين، البريد→تيليجرام،
  كلمة مرور الأدمن الافتراضية `aloka-admin-2026`), `app/Core/Seo.php` (اسم الناشر + إصدار البناء).
- **الواجهة:** `app/Views/layout/{base,header,footer,app-required-dialog}.php` ,
  `app/Views/pages/{more,match,static-about,static-contact,static-privacy,static-terms}.php` ,
  `app/Views/partials/{channel-card,cinema-locked}.php`.
- **لوحة الإدارة:** `app/Views/admin/*` (بما فيها `_shell.php` — تحويل ثيم الأدمن من الأخضر
  إلى البنفسجي، `login.php`، والصفحات كافة).
- **المنطق:** `app/Core/Ai.php` (بطاقات التواصل عبر تيليجرام), `app/Core/ChannelCatalog.php`
  (الحزمة/رابط التحميل), `app/Controllers/Sitemap.php` (اسم الناشر), `app/helpers.php`
  (كشف التطبيق ورابط intent بالحزمة الجديدة), وملفات Core/Controllers أخرى (نصوص/تعليقات).
- **اللغة:** `app/Lang/ar.php` , `app/Lang/en.php`.
- **الأصول:** `public/assets/css/app.css` + `app.min.css` (لوحة الألوان),
  `public/assets/js/{app,ai-chat,api-service,watch}.js` + نسخ `.min.js`,
  `public/assets/img/{channel,episode}.svg`.
- **PWA/SW/Manifest:** `public/manifest.webmanifest` (الاسم/الألوان), `public/sw.js`,
  `public/index.php`, `.htaccess` , `public/.htaccess`.
- **الإعدادات المخزّنة (JSON):** `storage/settings/channel_catalog.json`
  (`package` + `download_url`), `storage/settings/editorial_news.json` (اسم الكاتب الافتراضي).
- **التوثيق/النشر:** `README.md` , `.env.example` , `deploy/*` , `docs/*`.

---

## 4) واجهات API — لم تتغير أسماء الحقول

حافظنا على جميع أسماء حقول API التي يعتمد عليها التطبيق (مثل `package` , `scheme` ,
`download_url` , `url` , `title`). تم تغيير **القيم** فقط إلى هوية ALOKA. لم تُحذف أو تُعاد تسمية
أي مفاتيح، ولم تتغير مسارات المسارات (routes) أو نقاط النهاية.

---

## 5) قاعدة البيانات

المشروع **لا يستخدم قاعدة بيانات SQL** — كل الإعدادات في ملفات JSON داخل `storage/settings/`.
لذلك **لا حاجة إلى أي Migration أو ملف SQL**. القيم الافتراضية للاسم والألوان والروابط تأتي من
ثوابت `app/config.php` ومن ملفات JSON التي تم تحديثها، ويمكن للأدمن تعديلها لاحقاً من اللوحة
(الشعار والهوية / الثيم / SEO / القنوات). لم يُحذف أي جدول أو بيانات.

---

## 6) بيانات بقيت من "tofi" لأنها ضرورية تقنياً (غير ظاهرة للمستخدم إطلاقاً)

هذه معرّفات برمجية داخلية لا تظهر في أي واجهة، وتغييرها يكسر عمل الموقع دون أي فائدة مرئية،
لذلك أُبقيت عمداً (تم فحص كل نتيجة يدوياً):

1. **مساحة أسماء PHP** `TofiXTv\Core\*` و`TofiXTv\Controllers\*` — تُستخدم في كل ملف كلاس
   ومحمّلات الأصناف (autoloaders في `app/bootstrap.php` , `public/api/_boot.php` ,
   `deploy/notify-worker.php`) وملف `app/routes.php`. لا تُطبع في HTML أبداً.
2. **الثابت** `TOFIXTV` (حارس الوصول) و**متغير البيئة** `TOFIXTV_SITE_URL` (اختياري لتثبيت الدومين).
3. **أسماء دوال داخلية:** `is_tofix_app()` , `tofixtv_session_start()` , `tofixtv_legacy_redirect()`.
4. **مفتاح التوقيع الداخلي** `YACINE_PROXY_SECRET` = `'tofixtv|' . YACINE_KEY` — سِرّ خادمي لتوقيع
   روابط البروكسي (يمنع إساءة استخدام البروكسي)، غير ظاهر.
5. **أمثلة داخل لوحة الأدمن** في `app/Views/admin/app-channels.php`:
   `...?tofi-api&tofiUrlname=...` — هذه معاملات تُلحق حرفياً بروابط قنوات خادم Yacine الخارجي
   (`ver3.yacinelive.com`) وتُمرّر كما هي دون أن يقرأها موقعنا. تركناها كما هي حتى لا نكسر توافق
   خادم Yacine الخارجي (نص مساعدة للأدمن فقط، لا يظهر للزوّار).
6. ملفات `docs/*.md` تقارير تاريخية تذكر أسماء علامات سابقة (Qamhad ثم TofiXTv) — غير مخدومة
   كصفحات وغير ظاهرة للمستخدم.

عناوين الـ API الخارجية (`ysscores.com` , `themoviedb.org` , `yacinelive.com` , `imgs.ysscores.com`)
**لم تُلمس** لأنها خدمات فعلية يعتمد عليها الموقع.

---

## 7) ملاحظات تخص Firebase و Package Name وتوقيع APK (إجراءات خارجية عليك تنفيذها)

الموقع أصبح جاهزاً للحزمة الجديدة، لكن **تطبيق أندرويد نفسه ملف خارجي** يجب تحديثه لديك:

- **إعادة بناء APK** بالحزمة `com.aloka.live.app` (`applicationId` / `namespace`) وضبط
  `User-Agent` الخاص بالـ WebView إلى `com.aloka.live.app` (الموقع يكتشف التطبيق بهذا النص)،
  وتسجيل مخطط الروابط `xmtv` (لم نغيّر المخطط). حتى تفعل ذلك، لن يتعرّف الموقع على النسخة
  القديمة `com.tofixtv.app`، وروابط `intent://…package=com.aloka.live.app;` ستفتح الحزمة الجديدة فقط.
- **Firebase / google-services.json:** لم نجد ملف `google-services.json` مضمّناً في مشروع الويب.
  إعدادات FCM هنا تُدار من لوحة الأدمن (`storage/settings/fcm.json` و`service-account.json` — فارغة حالياً).
  إن كنت تستخدم الإشعارات، أنشئ تطبيق أندرويد جديداً في Firebase باسم `com.aloka.live.app`
  واستبدل `google-services.json` داخل مشروع الأندرويد، وحدّث حساب الخدمة في اللوحة إن غيّرت المشروع.
- **توقيع APK:** الحزمة الجديدة = هوية تطبيق جديدة؛ وقّع الإصدار بمفتاح keystore الخاص بك.
- **assetlinks.json:** غير موجود حالياً. إن أضفت Android App Links للدومين `aloka-code.shop`
  ضع `/.well-known/assetlinks.json` بالحزمة الجديدة وبصمة شهادة التوقيع SHA-256.

---

## 8) تنظيف الكاش

- روابط CSS/JS تحمل `?v={filemtime}` تلقائياً، وأي تعديل يبدّل الإصدار فيُحدّث المتصفح الملف.
- تم رفع إصدار البناء إلى `2026-07-23-aloka-v1`، وService Worker يسجَّل بـ `/sw.js?v={build}`
  ويحذف الكاش القديم عند التفعيل — فتظهر الهوية الجديدة للزوّار دون مسح بيانات المتصفح.
- ألوان `theme_color`/`background_color` في manifest أصبحت `#1B0761`.

---

## 9) طريقة التشغيل

1. ارفع المشروع كما هو مع الحفاظ على هيكل المجلدات.
2. اجعل جذر الويب (Document Root) يشير إلى مجلد `public/`.
3. يتطلب PHP 8.x، مع صلاحية الكتابة على `storage/`.
4. الموقع Domain-agnostic (يعمل على أي دومين تلقائياً)؛ اختيارياً ثبّت الدومين عبر
   متغير البيئة `TOFIXTV_SITE_URL`.
5. لوحة الإدارة على `/admin` (كلمة المرور الافتراضية `aloka-admin-2026` — غيّرها عند أول دخول).

---

## 10) نتائج الاختبارات التي أُجريت

- **PHP Lint:** كل ملفات `.php` (عدا مكتبات vendor) — لا أخطاء صياغة.
- **JSON:** جميع ملفات JSON و`manifest.webmanifest` صالحة.
- **JavaScript:** `node --check` نجح على `app/ai-chat/api-service/watch` و`sw.js` ونسخ `.min.js`.
- **SVG:** جميع ملفات الشعار سليمة XML.
- **تشغيل فعلي (خادم PHP مدمج):** `/` , `/about` , `/contact` , `/privacy` , `/terms` , صفحة دخول
  `/admin` — جميعها HTTP 200، العنوان «ALOKA Live»، `theme-color = #1B0761`، الشعار من
  `/assets/brand/`، روابط تيليجرام تعمل، ثيم الأدمن بنفسجي، وبدون أي ظهور لاسم ToFi X Tv في
  الواجهة وبدون أخطاء PHP.
- تم إرجاع `storage/settings/analytics.json` إلى أصله وحذف كاش الاختبار حتى لا تُشحن بيانات اختبار.

---

## 11) نظام التفعيل والترخيص (مضاف)

أُضيف نظام تفعيل مركزي يتحكم بتشغيل النسخة عن بُعد عبر صفحة Blogspot. التفاصيل الكاملة
وطريقة إدارة العملاء والكود الذي يُوضع في Blogspot موجودة في ملف **`LICENSE_SETUP.md`**.

باختصار: الموقع مقفل حتى يُدخل المالك كود التفعيل مرة واحدة في لوحة الإدارة؛ يتحقق الخادم من
الكود + الدومين + الحالة من `admin-cod.blogspot.com`، ثم يعمل الموقع طبيعيًا مع تحقق خلفي مُخزَّن
كل 10 دقائق. عند `disabled` أو حذف الترخيص أو عدم تطابق الدومين يتوقف الموقع بالكامل ويعرض صفحة
«تم إيقاف تفعيل الموقع» مع زر `https://t.me/Abu5Turkish`، ويعود تلقائيًا عند إعادة `active`.

الملفات: `app/Core/License.php`، `app/Views/admin/activate.php`، وربط في `app/bootstrap.php`
و`public/api/_boot.php` و`app/Controllers/Admin.php`. الحالة تُحفظ في `storage/settings/license.json`
(محمي بـ `storage/.htaccess`). الكود يُتحقق منه في PHP فقط ولا يظهر في المتصفح إطلاقًا.

**ملاحظة:** جزء «حذف/تخريب ملفات الاستضافة» عند الإيقاف **لم يُنفّذ عمدًا** لأنه مدمّر وغير قابل
للتراجع؛ واستُبدل بقفل كامل من جهة الخادم يوقف كل شيء ويعود فورًا عند إعادة التفعيل (التفاصيل في
`LICENSE_SETUP.md`).

---

## 12) وضع التطبيق (تشغيل الموقع في التطبيق فقط أو في الاثنين) — مضاف

في لوحة الإدارة قسم جديد **«وضع التطبيق»** فيه خياران:

- **التطبيق والمتصفح معاً** (افتراضي): الموقع يعمل طبيعيًا في المتصفح وداخل التطبيق.
- **داخل التطبيق فقط**: لا يعمل الموقع في المتصفح — يظهر للزائر **صفحة هبوط لتحميل التطبيق**
  (بنفس هوية ALOKA) مع زر تنزيل ينزّل ملف <code>aloka-live.apk</code>، ويعمل داخل التطبيق طبيعيًا
  عبر <code>User-Agent: com.aloka.live.app</code>. لوحة الإدارة وواجهة التنزيل والأصول تبقى متاحة من المتصفح.

**مكان ملف التطبيق:** ضع الـ APK الحقيقي في <code>storage/app/aloka-live.apk</code>. زر التحميل
(<code>/download/app</code> و<code>/aloka-live.apk</code>) يخدمه عبر PHP باسم <code>aloka-live.apk</code>؛
وإن لم يُرفع بعد يحوّل الزر مؤقتًا إلى <code>https://t.me/alokalive</code>.

**صفحة 404:** تعمل على أي مسار غير معروف (الراوتر يعرض صفحة 404 المصمّمة لأي رابط لا يطابق أي مسار).

الملفات: `app/Core/AppMode.php`، `app/Controllers/Download.php`، `app/Views/admin/app-mode.php`،
وربط في `app/bootstrap.php` و`public/api/_boot.php` و`app/routes.php` و`app/Controllers/Admin.php`
و`app/Views/admin/_shell.php`. الإعداد يُحفظ في `storage/settings/access.json`.

---

## 13) التحكم بطريقة تشغيل الأفلام والمسلسلات — مضاف

في **إدارة الأفلام / المسلسلات** خيار جديد **«طريقة تشغيل الفيديو»**:

- **مشغّل داخل الصفحة** (افتراضي): يشتغل الفيديو مباشرة داخل الموقع (السلوك القديم دون تغيير).
- **عبر التطبيق (intent · xmtv)**: عند الضغط على فيلم / زر مشاهدة / حلقة تظهر **نافذة اختيار سيرفر**
  بنفس تصميم الموقع، تعرض المصادر: **Videasy · VidSrc CC · VidSrc**. وعند اختيار سيرفر يُفتح عبر:
  <code dir="ltr">intent://&lt;مشفّر&gt;#Intent;scheme=xmtv;package=com.aloka.live.app;end</code>

**التشفير:** يُضاف <code dir="ltr">#aloka=web</code> إلى نهاية رابط المصدر، ثم يُشفّر **بنفس مفتاح
تشفير القنوات** (AES-192-ECB، الدالة <code>ChannelCatalog::encryptPlayValue</code>). مثال مطابق:
<code dir="ltr">https://vidsrc.to/embed/movie/1368337#aloka=web</code> →
<code dir="ltr">4/pL7qa+q2sgTQKKOv0X/ViPQHfpmbr6/wsSOBRoUkTYZ+C9DbGEzfPqE0Spz4ll</code> →
<code dir="ltr">intent://4/pL7qa+q2sgTQKKOv0X/ViPQHfpmbr6/wsSOBRoUkTYZ+C9DbGEzfPqE0Spz4ll#Intent;scheme=xmtv;package=com.aloka.live.app;end</code>

التشفير يتم **من جهة الخادم (PHP)** فلا يظهر المفتاح في المتصفح. الملفات:
`app/Core/CinemaPlay.php`، `app/Views/partials/cinema-servers.php` (نافذة الاختيار)، وتعديلات في
`app/Views/pages/movie.php` و`series-show.php` و`app/Views/partials/episode-card.php`
و`app/Controllers/Admin.php` و`app/Views/admin/cinema.php`. الإعداد يُحفظ في
`storage/settings/cinema_playback.json`. جميع الإضافات السابقة محفوظة دون تغيير.

</div>
