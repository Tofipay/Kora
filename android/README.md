# Tofi XTV — تطبيق Android (Kotlin)

تطبيق أندرويد حديث لموقع كرة القدم (قمهد/توفي) — نتائج مباشرة، مركز مباريات،
بطولات، ترتيب، هدافين، أخبار، فيديوهات، قنوات، بحث وإشعارات. مبني بالكامل
بلغة **Kotlin** مع **Material Design 3** و **ViewBinding** و **ExoPlayer (Media3)**
و **Firebase Cloud Messaging**، ويعتمد كمصدر أساسي للبيانات على:

```
https://api.tofi-xtv.com
```

---

## المتطلبات

| الأداة | الإصدار |
|---|---|
| Android Studio | Ladybug (2024.2) أو أحدث |
| JDK | 17 |
| Android Gradle Plugin | 8.7.3 |
| Gradle | 8.14.3 (عبر الـ wrapper المرفق) |
| Kotlin | 2.0.21 |
| compileSdk / targetSdk | 35 (Android 15) — متوافق مع Android 16 |
| minSdk | 26 (Android 8.0) |

> **Android 15 / 16:** يُبنى المشروع على `compileSdk = 35` (Android 15). عند توفّر
> منصّة Android 16 (API 36) في الـ SDK، غيّر `compileSdk` و `targetSdk` إلى `36`
> في `app/build.gradle.kts` دون أي تغيير آخر.

---

## هيكل المشروع

```
android/
├── app/
│   ├── build.gradle.kts          إعدادات الوحدة + التبعيات + التوقيع
│   ├── google-services.json      إعداد Firebase (بديل — استبدله بملفك)
│   ├── proguard-rules.pro
│   └── src/main/
│       ├── AndroidManifest.xml   الأذونات + Deep/App Links + FCM + PiP
│       ├── java/com/tofixtv/app/
│       │   ├── App.kt                 تهيئة (قناة الإشعارات، المظهر)
│       │   ├── MainActivity.kt        Splash + BottomNav + Drawer + Navigation + PiP
│       │   ├── data/
│       │   │   ├── model/Models.kt    نماذج البيانات (Gson)
│       │   │   ├── remote/            Retrofit + OkHttp (Cache/Offline)
│       │   │   ├── local/             Room (المفضلة + لقطات Offline) + DataStore
│       │   │   └── repo/Repository.kt طبقة موحّدة مع fallback دون اتصال
│       │   ├── fcm/                    خدمة الإشعارات + إدارة الاشتراكات
│       │   ├── ui/                     كل الشاشات (fragments + adapters)
│       │   └── util/                   Media/MatchState/الوقت/المساعدات
│       └── res/                        layouts, drawables, menus, nav_graph, themes
├── gradle/wrapper/                 Gradle wrapper (مضمّن)
├── keystore/tofixtv-release.jks    مفتاح التوقيع (بديل — أنشئ مفتاحك للنشر)
├── keystore.properties             بيانات التوقيع
└── settings.gradle.kts
```

### الشاشات المنقولة من الموقع (بدون حذف أي ميزة)

الرئيسية · المباريات (أمس/اليوم/غداً/مباشر) · مركز المباراة (النتيجة + الأحداث +
البث) · البطولات · الترتيب · الهدافين · الأخبار (قائمة + تفاصيل) · الفيديوهات
(قائمة + مشغّل داخلي مع PiP) · القنوات · البحث · المفضلة · الإعدادات · الإشعارات ·
صفحة الفريق · صفحة اللاعب · مشغّل البث (ExoPlayer للروابط المباشرة + WebView لمشغّل
الموقع `/watch/{id}` الذي يحوي hls.js/dash.js وتبديل السيرفرات — فلا تُفقد أي ميزة بث).

---

## البناء محلياً

```bash
cd android

# 1) ضع مسار الـ SDK (أو اترك Android Studio يفعلها):
echo "sdk.dir=/path/to/Android/Sdk" > local.properties

# 2) APK للتجربة (debug)
./gradlew :app:assembleDebug
#   الناتج: app/build/outputs/apk/debug/app-debug.apk

# 3) APK موقّع للإصدار
./gradlew :app:assembleRelease
#   الناتج: app/build/outputs/apk/release/app-release.apk

# 4) حزمة AAB للرفع على Google Play
./gradlew :app:bundleRelease
#   الناتج: app/build/outputs/bundle/release/app-release.aab
```

> التوقيع يُقرأ تلقائياً من `keystore.properties`. إذا لم يكن موجوداً، يوقّع
> إصدار الـ release بمفتاح الـ debug حتى لا يفشل البناء.

### البناء عبر GitHub Actions (يُنتج APK + AAB جاهزين)

بيئة التطوير هنا محجوبة عن خوادم Google (لا يمكن تنزيل Android SDK / Google Maven)،
لذلك أُضيف مسار CI جاهز:

```
.github/workflows/android-release.yml
```

عند رفع الفرع إلى GitHub يعمل الـ workflow تلقائياً، يبني **APK موقّع** و **AAB**،
ويرفعهما كـ **Artifacts** يمكن تنزيلهما من صفحة الـ Actions:
`TofiXTV-release-apk` و `TofiXTV-release-aab`.
يمكن أيضاً تشغيله يدوياً من تبويب **Actions → Android Release Build → Run workflow**.

---

## إعداد Firebase (الإشعارات)

`app/google-services.json` الحالي **بديل (placeholder)** ليبنى المشروع فقط. للإشعارات
الحقيقية:

1. أنشئ مشروعاً في [Firebase Console](https://console.firebase.google.com) وأضف
   تطبيق Android بالحزمة `com.tofixtv.app`.
2. نزّل `google-services.json` الحقيقي وضعه مكان الملف في `app/`.
3. الإشعارات تعمل عبر **مواضيع FCM**: `all` (كل المباريات) و `lg_{url_id}` لكل
   بطولة. الخادم يرسل إلى هذه المواضيع (راجع `backend-api`).

---

## مفتاح التوقيع (Keystore)

مرفق مفتاح جاهز للتجربة:

```
keystore/tofixtv-release.jks
alias: tofixtv   |   storepass/keypass: Tofixtv2026
SHA-256: 54:04:63:6F:3B:D2:79:64:84:73:15:D8:7F:25:6D:20:00:B5:E7:87:99:F3:ED:93:FC:28:43:95:F7:75:B1:57
```

> ⚠️ **للنشر الحقيقي على Google Play** أنشئ مفتاحك الخاص واحفظه سرّاً:
> ```bash
> keytool -genkeypair -v -keystore my-release.jks -alias tofixtv \
>   -keyalg RSA -keysize 2048 -validity 10000
> ```
> ثم حدّث `keystore.properties`. عند تفعيل **Play App Signing** ستتغيّر بصمة
> الشهادة — حدّث `sha256_cert_fingerprints` في
> `backend-api/public/.well-known/assetlinks.json` ببصمة شهادة Play حتى تعمل App Links.

---

## الميزات التقنية

- **Material 3** + الوضع الليلي/النهاري + دعم RTL (عربي).
- **ViewBinding** في كل الشاشات.
- **Navigation Component** (Bottom Navigation + Drawer + رسم بياني واحد).
- **Splash Screen** الحديثة (androidx.core.splashscreen).
- **ExoPlayer / Media3** (HLS + DASH + MP4) مع **Picture-in-Picture**.
- **Deep Links** (`tofixtv://match/123` …) و **App Links** (`https://api.tofi-xtv.com/...`).
- **Firebase Cloud Messaging** + Push Notifications + قناة إشعارات.
- **Cache**: OkHttp قرص 20MB + Coil لصور، **Offline Mode** عبر لقطات Room،
  **Lazy Loading** في القوائم.
