# قمهد لايف — واجهة Next.js (App Router · Static Export)

تحويل واجهة موقع **qamhad.com** من قوالب PHP إلى **Next.js (App Router)** مع
**Static Export** — مع الإبقاء الكامل على طبقة الـ PHP الخاصة بالـ API والبث
المباشر والـ Proxy والتواقيع (HMAC) كما هي دون أي تعديل، والواجهة الجديدة
تستهلك نفس نقاط الـ API الموجودة.

> **التصميم لم يتغيّر إطلاقاً:** يُعاد استخدام نفس `app.css` و`app.js`
> و`api-service.js` والأصول والأيقونات حرفياً. مكوّنات React تُصدِّر نفس بنية
> الـ HTML (الأصناف وسمات `data-*`)، ويُحمَّل `app.js` كما هو ليُطبّق الثيم
> والعدّاد التنازلي والتحديث الحي للنتائج وتسجيل الـ Service Worker.

---

## البناء

```bash
npm install
npm run build      # يُنتج مجلد out/ الثابت
```

بعد البناء يظهر مجلد **`out/`** يحتوي كل صفحات HTML الثابتة والأصول. لا يحتاج
السيرفر إلى Node أو pm2 إطلاقاً.

## التركيب على الاستضافة (public_html)

الموقع نهائياً = **واجهة Next الثابتة** + **خلفية PHP الحالية دون تعديل**،
داخل نفس `public_html`:

```
public_html/
├─ (محتويات out/ كاملة)      ← index.html, news/, videos/, assets/, _next/, .htaccess ...
├─ index.php                 ← الـ front controller الخاص بمشروع PHP الحالي (public/index.php)
├─ api/                      ← مجلد /api/*.php الحالي كما هو (نتائج/أخبار/بث ...)
└─ (بقية ملفات PHP كما رفعتها سابقاً: app/, config.php, storage/, btolat_php_api/ ...)
```

الخطوات:

1. `npm run build` على جهازك.
2. ارفع **محتويات `out/`** إلى `public_html`.
3. ارفع/أبقِ ملفات مشروع PHP الحالي في نفس `public_html` (خاصة `index.php`
   و`api/` و`app/` و`config.php` و`storage/`).
4. افتح `https://qamhad.com` — يعمل مباشرة.

ملف **`.htaccess`** (المرفق داخل `out/`) يوجّه الطلبات تلقائياً:

| المسار | يُخدَم بواسطة |
|---|---|
| `/`, `/news`, `/videos`, `/standings`, `/leagues`, الصفحات الثابتة … | Next الثابت (HTML) |
| `/api/*` ، `/stream` ، `/media/*` ، `/watch/*` ، `/yacine/*` | PHP (بلا تعديل — بث ونتائج حية) |
| `/match/*` (مركز المباراة + البث الحي + SEO الديناميكي) | PHP |
| `/search` (بحث متعدد الكيانات على الخادم) | PHP |
| `/sitemap*.xml` (خرائط ديناميكية) | PHP |
| `/news/{slug}` ، `/video/{id}` ، `/team/{slug}` … | قشرة Next (تجلب من `/api`) |

## لماذا هذا التقسيم؟

الموقع يعتمد على **بث مباشر** (يتطلب توقيع HMAC وترويسات خاصة على الخادم)،
و**بيانات لحظية**، و**أسرار API**، و**SEO ديناميكي** لصفحات المباريات/الأخبار.
هذه كلها لا يمكن أن تكون Static خالصة، لذلك تبقى في PHP كما طلبت — بينما تحوّلت
صفحات التصفّح والعرض إلى Next ثابت سريع.

> **ملاحظة SEO:** صفحات التفاصيل (`/news/{slug}` ، `/video/{id}`) لها نسختان:
> قشرة Next (تعمل من الـ API)، ونسخة PHP الأصلية بترويسات SEO كاملة لكل عنصر.
> إن أردت أفضل SEO لهذه الصفحات، وجّهها إلى PHP في `.htaccess` (استبدل قواعد
> «القشرة» بـ `RewriteRule ^news/.+$ index.php [L]` مثلاً). أما `/match/*`
> فموجّهة إلى PHP افتراضياً للحفاظ على SEO مركز المباراة.

---

## البنية

```
app/                     صفحات App Router (كل مسار = مجلد + index.html بعد التصدير)
  page.tsx               الرئيسية (مباشر + مباريات اليوم + آخر الأخبار)
  matches|today|…|live/  جداول المباريات حسب اليوم
  news/ + news/[slug]/   قائمة الأخبار + قشرة المقال
  videos/ + video/[id]/  قائمة الفيديوهات + مشغّل داخل الموقع
  standings|top-scorers/ الترتيب والهدافون (كل البطولات المميزة)
  leagues + league/[slug]  البطولات + ترتيب بطولة
  team/[slug]|player/[slug]  صفحات الفريق واللاعب
  about|privacy|terms|contact  الصفحات الثابتة
  favorites|offline|not-found
components/               الهيدر/الفوتر/التنقّل + البطاقات (ports من partials)
lib/                     i18n (عربي) · helpers (منقولة من helpers.php) · api · hooks
public/                  الأصول الأصلية حرفياً (app.css, app.js, brand, manifest, sw.js, .htaccess)
```

## تطوير محلي

```bash
# لتجربة الواجهة مع بيانات حقيقية، وجّه الـ API إلى الموقع المباشر:
NEXT_PUBLIC_API_BASE=https://www.qamhad.com/api npm run dev
```

## ملاحظات

- **اللغة:** العربية (الجمهور الأساسي) مكتملة. الإنجليزية (`/en`) تبقى في PHP
  حالياً، ويمكن نقلها لاحقاً عبر نفس واجهة `t()` في `lib/i18n.ts`.
- **PWA:** يُعاد استخدام `manifest.webmanifest` و`sw.js` كما هما؛ التسجيل يتم
  داخل `app.js`.
- **الأداء:** خطوط غير حاجبة، تقسيم كود تلقائي من Next، صور بأبعاد محددة،
  تحميل الإعلانات عند أول تفاعل فقط.
