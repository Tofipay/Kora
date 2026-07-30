# اختبار الحمل

أداتان تحاكيان مشغّل HLS حقيقيًا — لا مجرد طلب رابط واحد متكرر.

كل مشاهد افتراضي يفعل ما يفعله المشغل تمامًا:

1. يطلب `/api/token/{channels}` بترويسة `User-Agent: MTX Player`.
2. يتبع الرابط الموقّع.
3. إن كانت النتيجة Master Playlist يقرأها ويختار جودة.
4. يحدّث Media Playlist باستمرار حسب `TARGETDURATION`.
5. ينزّل المقاطع **الجديدة فقط** ولا يعيد تحميل ما نزّله.
6. يستدعي `leave_url` عند الخروج.

---

## 1) k6 (الأفضل للأرقام النهائية)

```bash
# تثبيت k6:  https://k6.io/docs/get-started/installation/

k6 run -e BASE=https://live-api-tofixtv.tofi-xtv.com \
       -e CHANNELS=10 \
       -e PROFILE=50 \
       -e METRICS_KEY=مفتاح-القياسات \
       hls-loadtest.js
```

| المتغيّر | القيمة |
|---|---|
| `BASE` | العنوان العام للبروكسي |
| `CHANNELS` | `10` أو `10-20-30` للجودات المتعددة |
| `PROFILE` | `smoke` \| `50` \| `200` \| `1000` |
| `WATCH_SECONDS` | مدة مشاهدة كل VU (افتراضي 60) |
| `METRICS_KEY` | لعرض اتصالات المصدر قبل/بعد (اختياري) |

المقاييس المطبوعة:

```
tofi_token_ms            زمن إصدار التوكن
tofi_playlist_ms         زمن تحديث القائمة (p50/p90/p95)
tofi_segment_ms          زمن تنزيل المقطع
tofi_segments_downloaded عدد المقاطع المنزّلة
tofi_playlist_polls      عدد تحديثات القائمة
tofi_playback_healthy    نسبة التحديثات التي لم يرجع فيها التسلسل للخلف
tofi_errors              عدد الأخطاء
```

وفي النهاية يطبع فرق عدّادات المصدر:

```
── اتصالات المصدر الفعلية خلال الاختبار ──
{
  "upstream_playlist_fetches": 40,
  "playlist_cache_hits": 11658,
  ...
}
```

**هذا هو الدليل المطلوب:** عدد اتصالات المصدر لا يتناسب مع عدد المشاهدين.

---

## 2) بديل PHP (بلا تثبيت أي شيء)

```bash
php hls-loadtest.php \
    --base=https://live-api-tofixtv.tofi-xtv.com \
    --channels=10 \
    --viewers=200 \
    --duration=60 \
    --metrics-key=مفتاح-القياسات
```

يستخدم `curl_multi` لتشغيل كل المشاهدين بالتوازي داخل عملية واحدة،
ويطبع p50/p95/p99 وعدد الأخطاء وعدّادات المصدر.

خيار إضافي عند الاختبار على المصدر الوهمي المحلي:

```bash
php hls-loadtest.php --base=http://127.0.0.1:8802 \
                     --origin=http://127.0.0.1:8801 --viewers=50
```

---

## قراءة النتيجة

| المؤشر | المعنى |
|---|---|
| `playlist_cache_hits` ≫ `upstream_playlist_fetches` | الكاش مشترك ويعمل |
| `upstream_segment_fetches` ≈ عدد المقاطع الجديدة | كل مقطع يُجلب مرة واحدة |
| `stale_playlist_served` > 0 | stale-while-revalidate يعمل (لا انتظار) |
| `lock_contention` > 0 مع `p95` منخفض | تزاحم بلا طابور — وهذا المطلوب |
| `segment_wait_timeout` > 0 | المصدر بطيء جدًا؛ زد `segment_wait_ms` |
| `tofi_playback_healthy` < 1 | تسلسل رجع للخلف — أبلغ عن الحالة |

---

## تحذير مهم بشأن سيناريو 1000 مشاهد

- شغّل الاختبار من **أكثر من جهاز**. جهاز واحد يستنفد منافذه قبل أن يستنفد
  سيرفرك موارده، فتقيس جهازك لا سيرفرك.
- 1000 مشاهد × 2.5 ميجابت = **2.5 جيجابت/ثانية**. بدون CDN أمام
  `/hls-cache/*` سيتوقف الاختبار عند حد الباندويث لا عند حد الكود.
- النتيجة تعتمد على المعالج والذاكرة والقرص والشبكة و CDN. الاختبار يقيس
  ولا يَعِد بأي رقم.
