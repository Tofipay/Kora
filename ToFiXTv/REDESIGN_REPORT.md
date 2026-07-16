# تقرير إعادة تصميم ToFi X Tv — نسخة 2026

## الملخص
أُعيد تصميم واجهات التطبيق بالكامل بأسلوب **Material Design 3 Expressive 2026** (داكن افتراضياً + Glassmorphism) مع الحفاظ **100%** على جميع الوظائف: البث، ExoPlayer (Media3)، WebView، الإشعارات (Firebase)، الإرسال للشاشات، نافذة داخل نافذة (PiP)، الإعلانات، والروابط العميقة. لم يُحذف أي ملف أو Activity أو Service أو Permission، ولم يُغيَّر أي منطق تشغيل أو API — جميع معرفات العناصر (IDs) وأنواعها في التخطيطات كما هي حتى يبقى كود Java يعمل دون أي تعديل وظيفي.

## نظام التصميم الجديد
- **الثيم**: `Theme.Material3.Dark.NoActionBar` داكن افتراضياً بهوية ToFi الكحلية (`#0A1324`) مع لمسة زرقاء حديثة (`#3EB2FF`) وذهبي تراثي (`#FFC94D`).
- **Dynamic Color**: على أندرويد 12+ تتبع لمساتُ التمييز لونَ النظام (Material You) عبر `values-v31`.
- **Glassmorphism**: بطاقات وحقول وأشرطة زجاجية شفافة بحدود مضيئة (`bg_glass_card`, `bg_appbar_glass`, `ripple_glass_*`).
- **الخطوط**: Tajawal كخط افتراضي للتطبيق كاملاً (Arabic-first) عبر موارد `res/font`.
- **الأيقونات**: أيقونات Material Symbols (Rounded) متجهية جديدة (`ic_ms_*`) للرجوع، الإغلاق، الإضافة، الخصوصية، المشاركة، التقييم، الحذف، التعديل وغيرها — مع الإبقاء على الأيقونات القديمة في المشروع دون حذف.
- **الحركات**: انتقالات Material Motion جديدة (fast-out-slow-in) عبر تنفيذ محلي لمكتبة Animatoo بنفس الواجهة البرمجية.
- **RTL**: تفعيل `supportsRtl` ودعم كامل للعربية.
- **Accessibility**: أوصاف محتوى (contentDescription) عربية لكل عناصر التحكم، أهداف لمس ≥44dp.

## الشاشات المعاد تصميمها (21 تخطيطاً)
| الشاشة | التحديث |
|---|---|
| Splash (`eng.xml`) | تدرّج كحلي + توهّج زجاجي خلف الشعار + اسم التطبيق وشعار نصي ومؤشر تحميل |
| الرئيسية (`test.xml`) | App Bar زجاجي، تبويبات M3 بمؤشر دائري، ViewPager2 شفاف فوق تدرّج الخلفية |
| ToFi Player (`listviwe.xml`) | بطاقة زجاجية للنموذج، حقول OutlinedBox بزوايا 16dp، زر SAVE متدرّج، FAB بلون الهوية |
| المشغل (`play.xml` + تحكم Exo) | ألوان شريط التقدم بلون الهوية، شريط سفلي زجاجي عائم، زر تشغيل/إيقاف زجاجي دائري — بأسلوب Netflix/Shahid/TOD |
| حوار الجودة/المسارات | بطاقة زجاجية داكنة بزر موافقة متدرّج أزرق |
| الخصوصية (`privacy.xml`) | شريط علوي زجاجي وأيقونة إغلاق Material Symbols |
| حول/التحديث (`app.xml`) | بطاقة زجاجية للشعار والإصدار وأزرار حبّية حديثة |
| الإشعارات (`main.xml`) | حقول زجاجية وزر إرسال متدرّج |
| القائمة الجانبية | شريط أيقونات زجاجي بأيقونات متجهية ملوّنة |
| عناصر القوائم (`save`, `ccc`, `cus`, `customview`, `urllist`, `dialog`) | بطاقات زجاجية، شارات صيغة (m3u8) حبّية، شرائح جودة متدرّجة |

## إصلاحات البناء والتوافق مع Android 15
- ترقية AGP إلى **8.7.3** و Gradle Wrapper **8.14.3** و Java 17.
- `compileSdk 35` / `targetSdk 35` مع الإبقاء على `minSdk 21`.
- استكمال التبعيّات الناقصة في المشروع الأصلي: Media3 (ExoPlayer/HLS/DASH/RTSP/SmoothStreaming/UI/Session)، Play Services Ads، Firebase Messaging، WorkManager، ViewPager2، AppCompat 1.7، Material 1.12.
- إصلاح أخطاء دمج الـ Manifest (إزالة تكرار مكوّنات إعلانات GMS المعلنة مرتين، ونقل `package` إلى `namespace`).
- إنشاء الموارد المفقودة التي كان يشير إليها الكود (`app_name`, `AppTheme`, `AppTheme.FullScreen`, `exo_track_selection_dialog`).
- Stubs تصريفية لحزم كانت مستوردة دون استخدام (`meorg.jsoup` وغيرها) دون أي تغيير في الكود الأصلي.
- تفعيل MultiDex وضبط `gradle.properties` لـ AndroidX.

## التوقيع والإخراج
- Keystore إصدار: `keystore/tofixtv-release.jks` (alias: `tofixtv`).
- البناء والتوقيع يتمّان تلقائياً عبر GitHub Actions (`.github/workflows/build-tofixtv.yml`) والتحقق بـ `apksigner verify`.
- الملف النهائي: **`ToFiXTv/dist/ToFiXTv-v2026.apk`**.
