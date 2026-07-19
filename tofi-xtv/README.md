# ToFi X Tv — صفحة التحميل الرسمية 2026

صفحة هبوط ثابتة (HTML + CSS + JS فقط) بتصميم Premium 2026 — عربية RTL بخط Cairo،
بدون أي مكتبات خارجية أو صور أفلام/مسلسلات؛ لقطات الشاشة الحقيقية للتطبيق فقط.

## البنية

```
.
├── index.html          # الصفحة الكاملة — CSS وJS مدمجان بداخلها (SEO + Schema.org)
├── favicon.ico
├── robots.txt          # يسمح بالزحف الكامل + رابط sitemap
├── sitemap.xml         # خريطة الموقع مع صور
├── site.webmanifest    # PWA manifest
└── assets/
    ├── brand/          # الشعار والأيقونات
    ├── css/main.css    # نسخة مصدرية من التنسيقات (مدمجة أصلًا في index.html)
    ├── js/app.js       # نسخة مصدرية من الجافاسكربت (مدمج أصلًا في index.html)
    └── screens/        # لقطات شاشة WebP حقيقية من التطبيق
```

> **ملاحظة:** كل التنسيقات والجافاسكربت مدمجة داخل `index.html` مباشرة،
> فلا تتأثر الصفحة بإعدادات MIME أو مسارات الاستضافة — يكفي رفع
> `index.html` مع مجلد `assets` (للصور) في أي مكان.

## Schema.org المضمّنة

- `Organization` + `WebSite`
- `SoftwareApplication` + `MobileApplication` (مع `AggregateRating` و`Offer`)
- `BreadcrumbList`
- `FAQPage` (8 أسئلة مطابقة لقسم الأسئلة الشائعة)

## النشر

ارفع محتوى المجلد إلى `https://apk.tofi-xtv.com/` (أو أي مجلد —
كل المسارات نسبية). أزرار «الموقع الرسمي» تشير إلى
`https://www.tofi-xtv.com/` وزر التحميل إلى `https://apk.tofi-xtv.com/`.

معاينة محلية:

```bash
python3 -m http.server 8000
```

## ملاحظات الأداء (Core Web Vitals)

- صورة الـ Hero فقط `fetchpriority=high` + preload، والبقية `loading=lazy`.
- كل الصور WebP بأبعاد صريحة (`width`/`height`) لمنع CLS.
- خط Cairo عبر `display=swap` مع `preconnect`.
- CSS وJS مدمجان في HTML: صفر طلبات حاجبة للعرض.
- JS واحد صغير (`defer`) بدون أي اعتماديات، والحركات تحترم
  `prefers-reduced-motion`.
- كود التفعيل: زر «نسخ الكود» ينسخ `1212` ويعرض رسالة «تم نسخ الكود بنجاح».
