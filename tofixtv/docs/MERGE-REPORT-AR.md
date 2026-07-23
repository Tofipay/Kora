<div dir="rtl">

# تقرير الدمج وإعادة الهوية — ALOKA Live

**التاريخ:** 2026-07-14
**المشروع الأساسي:** Qamhad Live (موقع المباريات) — **المشروع المدموج:** TV (أفلام ومسلسلات TMDB)
**الاسم الجديد:** ALOKA Live — **ALOKA Live**

---

## 1) ملخص تنفيذي

تم دمج المشروعين في منصة واحدة Production-Ready تعمل بدون قاعدة بيانات وبدون أي إطار عمل خارجي:

- كل ميزات موقع المباريات (المباريات، البث، المركز، الأخبار، الفيديوهات، الترتيب، الهدافون، البطولات، الفرق، اللاعبون، المفضلة، الإشعارات، الأدمن، PWA) **بقيت كما هي بدون أي حذف أو كسر لأي Route**.
- ميزات مشروع TV أعيدت كتابتها داخل بنية MVC الخاصة بالمشروع الأساسي (وليست نسخاً ملفات منفصلة) فأصبحت الأفلام والمسلسلات جزءاً أصيلاً من نفس المنصة: نفس القالب، نفس الهيدر/الفوتر، نفس نظام الترجمة AR/EN، نفس نظام الكاش، نفس SEO pipeline.
- الموقع أصبح **Domain-Agnostic بالكامل**: يعمل على أي دومين فور رفعه بدون تعديل أي ملف.

## 2) الملفات الجديدة

| الملف | الوظيفة |
|---|---|
| `app/Core/Tmdb.php` | عميل TMDB مع كاش قرص + Stale-fallback + memo لكل طلب |
| `app/Controllers/Cinema.php` | صفحات: الأفلام، المسلسلات، تفاصيل فيلم/مسلسل، التصنيفات، البحث |
| `app/Views/pages/movies.php` | الصفحة الرئيسية للأفلام (Hero + صفوف بوسترات + تصنيفات + بحث) |
| `app/Views/pages/series.php` | الصفحة الرئيسية للمسلسلات |
| `app/Views/pages/movie.php` | صفحة تفاصيل الفيلم (مشغّل + مصادر بديلة + تريلر + طاقم + مشابه) |
| `app/Views/pages/series-show.php` | صفحة تفاصيل المسلسل (مواسم/حلقات قابلة للزحف بروابط GET) |
| `app/Views/pages/cinema-genre.php` | صفحة تصفح التصنيف مع Pagination |
| `app/Views/pages/cinema-search.php` | صفحة بحث الأفلام والمسلسلات |
| `app/Views/partials/poster-card.php` | بطاقة بوستر موحّدة |
| `app/Views/partials/poster-row.php` | صف بوسترات أفقي |
| `app/Views/partials/cinema-hero.php` | سلايدر Hero سينمائي |
| `public/assets/brand/*` | هوية بصرية جديدة كاملة (انظر بند 6) |
| `docs/MERGE-REPORT-AR.md` | هذا التقرير |

## 3) الملفات المعدّلة (أبرزها)

- `app/config.php` — الهوية الجديدة، ألوان البراند، SITE_URL ديناميكي من HTTP_HOST، ثوابت TMDB والمشغّلات، البريد الجديد.
- `app/routes.php` — مسارات جديدة: `/movies`, `/series`, `/movie/{slug}`, `/series/{slug}`, `/movies/genre/…`, `/series/genre/…`, `/cinema/search`, `/sitemap-cinema.xml`, `/robots.txt` (ديناميكي). **لم يُمس أي مسار قديم**.
- `app/Core/Seo.php` — إضافة `movieSchema` (Movie)، `tvSeriesSchema` (TVSeries)، `cinemaListSchema` (ItemList).
- `app/Controllers/Sitemap.php` — خريطة `sitemap-cinema.xml` + robots.txt ديناميكي + تسجيلها في sitemap index.
- `app/Controllers/Home.php` + `app/Views/pages/home.php` — أقسام رئيسية جديدة: الأفلام، المسلسلات، الأكثر مشاهدة، المضاف حديثاً (قابلة للتحكم من الأدمن مثل بقية الأقسام).
- `app/Core/Settings.php` — أقسام الصفحة الرئيسية الجديدة ضمن `homeSections`.
- `app/Views/layout/header.php` / `footer.php` / `bottomnav.php` — روابط الأفلام والمسلسلات (الفيديوهات والترتيب بقيت في الهيدر والفوتر).
- `app/Lang/ar.php` / `en.php` — ~50 مفتاح ترجمة جديد لقسم السينما.
- `public/assets/css/app.css` — لوحة الألوان الجديدة + طبقة مكونات السينما (انظر بند 7).
- `public/assets/js/app.js` — موديول السينما: تحميل المشغّل عند الطلب + سلايدر Hero.
- `public/manifest.webmanifest` — الاسم الجديد، ألوان `#0f172a`، اختصارات أفلام/مسلسلات.
- `.htaccess` + `public/.htaccess` + `deploy/nginx.conf` — إزالة أي دومين ثابت (انظر بند 5).
- `app/bootstrap.php` وكل الملفات — إعادة تسمية الـ namespace والنصوص بالكامل.

## 4) إعادة الهوية (Rebranding)

استُبدلت جميع أشكال الاسم القديم (Qamhad Live / Qamhad / قمهد / قمهد لايف / Gamhed / Gamhed Sport) بـ **ALOKA Live / ALOKA Live** في:

اسم الموقع، `<title>`، Meta Description، Open Graph، Twitter Cards، Manifest، Service Worker، Sitemaps، Robots، الشعارات، جميع النصوص والصفحات الثابتة (من نحن/الخصوصية/الشروط/اتصل بنا)، صفحات المشاركة، رسائل الإشعارات، لوحة الأدمن، وحتى الـ namespace البرمجي (`Qamhad\` → `TofiXTv\`) والمتغير العام (`window.QAMHAD` → `window.TOFIXTV`).

تم أيضاً حذف بيانات المشروع القديم الحساسة من الحزمة (مفاتيح Firebase الخاصة، توكنات الإشعارات، سجل التحليلات).

## 5) الدومين — يعمل على أي دومين

- `SITE_URL` يُشتق تلقائياً من `HTTP_HOST` مع كشف HTTPS خلف أي Proxy/Cloudflare — كل canonical / hreflang / OG / JSON-LD / sitemap `<loc>` يتبع الدومين الحالي.
- `robots.txt` أصبح يولَّد بـ PHP فيحمل روابط Sitemap على الدومين الحالي دائماً.
- `.htaccess` (الجذر + public) يعيد التوجيه إلى HTTPS على **نفس المضيف** `%{HTTP_HOST}` — لا يوجد أي دومين ثابت.
- `nginx.conf` أصبح `server_name _` (catch-all).
- لا يوجد Canonical أو Redirect مربوط بالدومين القديم إطلاقاً؛ `LEGACY_HOSTS` فارغة.
- الدومين الافتراضي للسياقات بدون طلب (CLI/Cron): `https://aloka-code.shop`، ويمكن تثبيته عبر `TOFIXTV_SITE_URL`.

## 6) الهوية البصرية الجديدة

مستوحاة من التصميمين المرسلين (شاشة TV بحرفي TX + علامة X مع مثلث التشغيل):

- `logo.svg`, `logo-dark.svg`, `logo-en.svg`, `logo-en-dark.svg` — علامة X-Play + الاسم
- `favicon.svg`, `favicon.png`, `favicon-32.png`, `favicon.ico`
- `icon.svg`, `icon-192.png`, `icon-512.png` — أيقونة TV/TX على مربع كحلي
- `icon-maskable.svg`, `icon-maskable-512.png` — بمنطقة أمان maskable
- `splash.svg`, `splash.png` (1080×1920)
- `og-image` → `og.svg`, `og-default.png` (1200×630)
- `social-cover.png` (1500×500), `cover.svg`

## 7) UI/UX 2026

- **الألوان:** أساسي `#0f172a`، ثانوي `#1e293b`، الأزرار/الهيدر/الفوتر/الروابط النشطة `#0f172a`، أكسنت أزرق `#3b82f6/#38bdf8` من الشعار.
- **الوضع النهاري:** خلفية بيضاء، بطاقات بيضاء، نصوص كحلية داكنة. **الوضع الليلي:** خلفيات `#0f172a/#1e293b` بتباين ممتاز (الأزرار التفاعلية في الليلي تعتمد الأزرق حتى لا يختفي الكحلي على الكحلي). اختيار المستخدم محفوظ في `localStorage`.
- الهيدر والفوتر وشريط الجوال بلون البراند الكحلي في الوضعين مع Glass خفيف (backdrop-blur).
- مكونات جديدة: بطاقات بوستر بنسبة 2:3 مع Hover play، سلايدر Hero سينمائي، شرائح تصنيفات/مواسم/حلقات (Chips)، شبكة بوسترات Responsive، بطاقات طاقم عمل، Pager، Skeleton shimmer عند برود الكاش.
- Animations خفيفة فقط (CSS transitions + `prefers-reduced-motion` محترم) — بدون أي مكتبة خارجية.

## 8) SEO

- **Schema.org:** Organization + WebSite (موجودة) و**Movie** و**TVSeries** (مع AggregateRating وdirector/actor/duration) و**ItemList** لصفحات الأقسام و**BreadcrumbList** لكل صفحات السينما — إضافة إلى SportsEvent وNewsArticle وFAQPage الحالية.
- **Sitemaps:** إضافة `sitemap-cinema.xml` (الأقسام + التصنيفات + الأعمال الرائجة بكلا اللغتين مع hreflang) إلى الفهرس، مع بقاء خرائط المباريات والأخبار والفيديو والصور كما هي.
- Canonical slug redirect لصفحات الأفلام/المسلسلات (`/movie/123` → `/movie/{slug}-123` بـ301 واحدة) — نفس نمط المباريات.
- أرشفة جوجل الحالية محمية: لم يتغير أي URL قديم، ولم يُحذف أي Route.

## 9) الأداء

- **صفر مكتبات جديدة**: قسم السينما CSS+JS خالص داخل نفس الملفات المصغّرة بكاش سنة + versioning تلقائي.
- مشغّل الفيديو الخارجي (iframe) **لا يُحمَّل إلا عند النقر** — لا أثر على LCP/INP/TBT.
- صور TMDB بأحجام مناسبة (`w342` للبوستر، `w1280` للخلفية) مع `loading="lazy"` و`decoding="async"` وأبعاد صريحة (CLS = 0).
- استجابات TMDB مكاشة على القرص (ساعة) مع Stale-fallback عند تعطل المصدر + memo داخل الطلب الواحد؛ الصفحة الرئيسية لا تنفذ أي نداء شبكة إضافياً بعد أول تخزين.
- سلايدر الـ Hero بأول صورة `fetchpriority="high"` والبقية lazy.

## 10) التعارضات التي حُلّت

| التعارض | الحل |
|---|---|
| مساران محتملان `/series/genre/…` مقابل `/series/{slug}` | تسجيل مسارات التصنيف قبل مسار التفاصيل (نمط المقطع الواحد لا يلتقط مقطعين) |
| بحث المباريات مقابل بحث السينما | إبقاء `/search` للرياضة و`/cinema/search` للسينما مع روابط تبادلية بينهما |
| لون البراند الكحلي غير مرئي على الخلفية الليلية | نفس الكحلي في النهاري، والتفاعلات الليلية بأكسنت الشعار الأزرق |
| كاش/إعدادات المشروع القديم (مفاتيح Firebase وتوكنات) | تفريغها من الحزمة (تُعاد تعبئتها من لوحة الأدمن) |
| `TmdbApi` القديم (file_get_contents بلا fallback) | إعادة كتابته كـ `Core\Tmdb` بـ cURL + مهلات + كاش + Stale-fallback |
| الحماية من فقدان الميزات في الـ bottom-nav | الأفلام والمسلسلات في الشريط السفلي، والفيديوهات/الترتيب باقية في الهيدر والفوتر وكل الصفحات |

## 11) النشر

1. ارفع محتويات المجلد إلى استضافة PHP 8.1+ (يدعم أي `document root`: الجذر عبر `.htaccess` أو `public/` مباشرة).
2. لا حاجة لأي تعديل — الموقع يعمل على أي دومين فوراً.
3. (اختياري) ضع `TMDB_BEARER_TOKEN` الخاص بك و`TOFIXTV_SITE_URL` في بيئة التشغيل.
4. لوحة التحكم: `/admin` — كلمة المرور الافتراضية في `app/config.php` (غيّرها فور الدخول).

</div>
