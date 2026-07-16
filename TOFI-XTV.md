# قمهد لايف — تحويل موقع كرة القدم إلى تطبيق Android

تحويل كامل لموقع PHP (قمهد لايف) إلى تطبيق Android حديث بلغة **Kotlin**، مع نقل
جميع الوظائف والصفحات، وربط كل البيانات بـ **https://api.tofi-xtv.com**.

## المكوّنات في هذا المستودع

| المجلد | الوصف |
|---|---|
| **`android/`** | مشروع Android Studio كامل (Kotlin · Material 3 · ViewBinding · ExoPlayer · FCM · PiP). دليل البناء في `android/README.md`. |
| **`backend-api/`** | حزمة PHP الكاملة للرفع على `api.tofi-xtv.com` مع نقاط JSON للتطبيق. دليل في `backend-api/README.md`. |
| **`.github/workflows/android-release.yml`** | يبني **APK + AAB** موقّعَين على GitHub Actions ويرفعهما كـ Artifacts. |
| **`android/keystore/tofixtv-release.jks`** | مفتاح التوقيع (+ `keystore.properties`). |

## الميزات المنقولة (بدون حذف أي ميزة من الموقع)

المباريات المباشرة · مركز المباراة (نتيجة + أحداث + بث) · البطولات · الترتيب ·
الهدافين · الأخبار · الفيديوهات · القنوات · البحث · المفضلة · الإشعارات ·
صفحات الفريق واللاعب · Splash Screen · Bottom Navigation · Drawer Menu ·
الإعدادات (لغة/مظهر/إشعارات).

التقنيات: ExoPlayer (HLS/DASH/MP4) + Picture-in-Picture · Firebase Cloud Messaging
+ Push · Deep Links + App Links · Cache (OkHttp/Coil) + Lazy Loading + Offline Mode
(Room) · دعم Android 8 حتى Android 15/16.

## كيف تحصل على APK و AAB

> **مهم:** لم يكن ممكناً تجميع الحزم داخل بيئة التطوير هذه لأنها محجوبة عن خوادم
> Google (لا يمكن تنزيل Android SDK / Google Maven). لذلك:

1. **الأسهل — GitHub Actions:** بمجرد رفع الفرع، يعمل الـ workflow ويُنتج
   `TofiXTV-release-apk` و `TofiXTV-release-aab` جاهزين للتنزيل من تبويب **Actions**.
   (أو شغّله يدوياً: Actions → Android Release Build → Run workflow.)
2. **محلياً:** افتح `android/` في Android Studio أو نفّذ:
   ```bash
   cd android
   ./gradlew :app:assembleRelease   # APK
   ./gradlew :app:bundleRelease     # AAB لـ Google Play
   ```

راجع `android/README.md` لتفاصيل البناء والتوقيع وإعداد Firebase.
