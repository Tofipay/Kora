# ToFi X Tv — صفحة التحميل الرسمية 2026

صفحة هبوط ثابتة (HTML + CSS + JS فقط) بتصميم Premium 2026 — عربية RTL بخط Cairo،
بدون أي مكتبات خارجية أو صور أفلام/مسلسلات؛ لقطات الشاشة الحقيقية للتطبيق فقط.

## البنية

```
.
├── index.html          # الصفحة الكاملة (SEO + Schema.org)
├── favicon.ico
├── robots.txt          # يسمح بالزحف الكامل + رابط sitemap
├── sitemap.xml         # خريطة الموقع مع صور
├── site.webmanifest    # PWA manifest
└── assets/
    ├── brand/          # الشعار والأيقونات
    ├── css/main.css    # كل التنسيقات (Design tokens)
    ├── js/app.js       # سلايدر الهاتف، نسخ الكود، القوائم، الحركات
    └── screens/        # لقطات شاشة WebP حقيقية من التطبيق
```

## Schema.org المضمّنة

- `Organization` + `WebSite`
- `SoftwareApplication` + `MobileApplication` (مع `AggregateRating` و`Offer`)
- `BreadcrumbList`
- `FAQPage` (8 أسئلة مطابقة لقسم الأسئلة الشائعة)

## النشر

ارفع محتوى المجلد إلى جذر النطاق `https://www.tofi-xtv.com/`
(المسارات جذرية `/assets/...`).

معاينة محلية:

```bash
python3 -m http.server 8000
```

## ملاحظات الأداء (Core Web Vitals)

- صورة الـ Hero فقط `fetchpriority=high` + preload، والبقية `loading=lazy`.
- كل الصور WebP بأبعاد صريحة (`width`/`height`) لمنع CLS.
- خط Cairo عبر `display=swap` مع `preconnect`.
- JS واحد صغير (`defer`) بدون أي اعتماديات، والحركات تحترم
  `prefers-reduced-motion`.
- كود التفعيل: زر «نسخ الكود» ينسخ `1212` ويعرض رسالة «تم نسخ الكود بنجاح».
