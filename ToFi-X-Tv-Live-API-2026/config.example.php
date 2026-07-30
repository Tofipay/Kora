<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  ToFi X Tv — ملف الإعدادات
 * ───────────────────────────────────────────────────────────────────────────
 *  انسخ هذا الملف باسم config.php ثم عدّل ما تحتاجه:
 *
 *      cp config.example.php config.php
 *
 *  ملف config.php محمي من الفتح المباشر عبر .htaccess.
 *
 *  ترتيب الأولوية للقيم:
 *      1) متغيّرات البيئة (Environment Variables) بالبادئة TOFI_
 *      2) القيم الموجودة في هذا الملف (config.php)
 *      3) القيم الافتراضية داخل hls-core.php (توافق رجعي مع النسخة القديمة)
 *
 *  أي مفتاح تحذفه من هنا يعود تلقائيًا لقيمته الافتراضية، فلا يتعطّل شيء.
 * ═══════════════════════════════════════════════════════════════════════════
 */

return [

    /* ─────────────────────── وضع التشغيل ─────────────────────── */

    /**
     * request_driven : الوضع الأساسي والافتراضي. يعمل على أي استضافة
     *                  مشتركة (Hostinger وغيرها) بلا أي خدمة خلفية ولا
     *                  root ولا cron. التحديث يتم داخل الطلبات نفسها مع
     *                  single-flight و stale-while-revalidate.
     *
     * worker         : خيار مستقبلي لـ VPS فقط. لا تستخدمه على استضافة
     *                  مشتركة. حتى على VPS فهو تحسين لا شرط: إن توقفت
     *                  الخدمة يعود النظام تلقائيًا إلى request_driven.
     *
     * على Hostinger Business اتركه request_driven دائمًا — راجع HOSTINGER.md
     */
    'mode' => getenv('TOFI_MODE') ?: 'request_driven',

    /* ─────────────────────── المصدر والرابط العام ─────────────────────── */

    'source_base_url'  => 'https://3.3loka.site:443/live/Abuturki/Abuturki/',
    'public_base_url'  => 'https://live-api-tofixtv.tofi-xtv.com',

    /**
     * النطاقات المسموح للبروكسي أن يجلب منها. أي رابط خارجها يُرفض،
     * حتى لو وُقّع بمفتاح صحيح (حماية من SSRF).
     * يقبل النطاق نفسه وكل نطاقاته الفرعية.
     */
    'allowed_source_hosts' => ['3loka.site'],

    /* ─────────────────────── الأسرار والتوقيع ─────────────────────── */

    /**
     * انقل هذه القيم إلى متغيّرات البيئة على السيرفر إن أمكن:
     *      TOFI_TOKEN_SECRET           و  TOFI_STREAM_SIGNING_SECRET
     *
     * إن تركتها فارغة هنا وبدون متغيّرات بيئة، يستخدم النظام المفاتيح
     * القديمة نفسها (توافق رجعي) حتى لا تتعطّل الروابط الموقّعة الحالية.
     * غيّرها بعد أن يُحدّث المستخدمون التطبيق.
     */
    'token_secret'           => getenv('TOFI_TOKEN_SECRET') ?: '',
    'stream_signing_secret'  => getenv('TOFI_STREAM_SIGNING_SECRET') ?: '',

    'app_user_agent'    => 'MTX Player',
    'stream_token_ttl'  => 3600,
    'token_clock_skew'  => 30,

    /* ─────────────────────── القوائم الحية (m3u8) ─────────────────────── */

    /**
     * مدة صلاحية القائمة قبل التحديث. تُحسب تلقائيًا من
     * ‎#EXT-X-TARGETDURATION‎ (النصف) و‎#EXT-X-PART-INF‎ و‎#EXT-X-SERVER-CONTROL‎،
     * ثم تُقيّد بين الحدّين التاليين.
     */
    'playlist_min_refresh_ms' => 500,
    'playlist_max_refresh_ms' => 1500,

    /**
     * أقصى عمر يُسمح فيه بإرسال قائمة قديمة (stale) أثناء تحديث عملية أخرى.
     * يُحسب من TARGETDURATION ويُقيّد بين الحدّين. بعد هذا الحد يُرجع
     * النظام خطأ واضحًا بدل تقديم قائمة منتهية.
     */
    'playlist_hard_stale_min_ms' => 4000,
    'playlist_hard_stale_max_ms' => 8000,

    /** أقصى انتظار لعملية لا تملك القفل ولا يوجد كاش صالح (لا تزد عن 500). */
    'playlist_cold_wait_ms' => 400,

    /** Master Playlist لا تتغيّر كثيرًا، فتأخذ مدد أطول. */
    'master_refresh_ms'     => 10000,
    'master_hard_stale_ms'  => 120000,

    /* ─────────────────────── النافذة الحية المحلية ─────────────────────── */

    /** عدد المقاطع في القائمة المحلية (المسموح 6 إلى 12). */
    'live_window_segments' => 8,

    /** عدد معرّفات المقاطع المحفوظة لمنع إعادة إضافة مقطع قديم. */
    'live_window_memory'   => 256,

    /**
     * وضع متقدّم للأحمال الضخمة (مطفأ افتراضيًا):
     *
     *   false → ‎/stream/{id}/index.m3u8‎ يرجع قائمة الوسائط مباشرة
     *           (السلوك الحالي تمامًا، ويُحتسب المشاهد من كل تحديث للقائمة).
     *
     *   true  → يرجع قائمة رئيسية صغيرة تشير إلى قائمة وسائط مشتركة
     *           بلا أي بيانات مشاهد، فيخزّنها CDN لثانية ويتقاسمها الجميع.
     *           في هذا الوضع اجعل التطبيق يستدعي ping_url كل 10–15 ثانية
     *           حتى يبقى إحصاء المتصلين دقيقًا.
     */
    'shared_media_playlist' => false,

    /* ─────────────────────── مهلات المصدر ─────────────────────── */

    'playlist_connect_timeout' => 3,
    'playlist_timeout'         => 6,
    'playlist_attempts'        => 2,

    'segment_connect_timeout'  => 3,
    'segment_timeout'          => 10,
    'segment_attempts'         => 2,

    /** أقصى انتظار لعملية تنتظر مقطعًا يجلبه غيرها (بالمللي ثانية). */
    'segment_wait_ms'          => 3000,

    /** أقصى حجم مقبول للمقطع الواحد. */
    'segment_max_bytes'        => 33554432,

    /* ─────────────────────── التخزين والتنظيف ─────────────────────── */

    'cache_root'          => __DIR__ . '/.tofi-cache',
    'segment_cache_root'  => __DIR__ . '/hls-cache',

    /** مدة بقاء المقطع على القرص بعد آخر تعديل. */
    'segment_keep_seconds' => 300,

    /** الفاصل بين جولات التنظيف، وأقصى عدد ملفات تُفحص في الجولة الواحدة. */
    'cleanup_interval_seconds' => 30,
    'cleanup_max_files'        => 600,

    /* ─────────────────────── إحصاء المتصلين ─────────────────────── */

    /**
     * auto  → apcu ثم sqlite ثم file (أول متوفّر).
     *
     * ملاحظة مهمة: الوضع التلقائي **لا يجرّب Redis إطلاقًا**، لأن تجربته
     * على استضافة بلا Redis تعني محاولة اتصال TCP في كل طلب.
     * على VPS فيه Redis اضبطه صراحةً: 'redis'
     *
     * القيم المقبولة: 'auto' | 'redis' | 'apcu' | 'sqlite' | 'file'
     */
    'viewer_backend' => getenv('TOFI_VIEWER_BACKEND') ?: 'auto',

    /** مدة اعتبار المشاهد متصلًا بعد آخر طلب فعلي. */
    'viewer_active_seconds' => 30,

    /** لا يُكتب سجل المشاهد أكثر من مرة خلال هذه المدة (تقليل الكتابة). */
    'viewer_touch_min_interval' => 10,

    /** مدة الاحتفاظ بالملفات المنتهية في الوضع file قبل حذفها. */
    'viewer_retention_seconds' => 120,

    'redis_host'   => getenv('TOFI_REDIS_HOST') ?: '127.0.0.1',
    'redis_port'   => (int) (getenv('TOFI_REDIS_PORT') ?: 6379),
    'redis_auth'   => getenv('TOFI_REDIS_AUTH') ?: '',
    'redis_prefix' => 'tofi:v:',

    /* ─────────────────────── القياسات (اختيارية) ─────────────────────── */

    /**
     * عند التفعيل تُحصى: upstream_playlist_fetches / upstream_segment_fetches /
     * playlist_cache_hits / segment_cache_hits / lock_contention /
     * stale_playlist_served ... وتُقرأ من:
     *      /api/metrics?key=<metrics_token>
     *
     * لا تُسجَّل أي أسرار أو توكنات.
     */
    'metrics_enabled' => false,
    'metrics_token'   => getenv('TOFI_METRICS_TOKEN') ?: '',

    /* ───────── إعدادات worker.php — VPS فقط، تُتجاهل تمامًا هنا ───────── */

    /*
     * كل ما تحت هذا السطر لا أثر له إطلاقًا في وضع request_driven.
     * لا تحتاج لمسه على الاستضافة المشتركة.
     */

    /** القنوات التي يسحبها الـ worker باستمرار. مثال: [10, 11, 20]. */
    'worker_channels' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) (getenv('TOFI_WORKER_CHANNELS') ?: '10'))
    ))),

    /** تحميل المقاطع الجديدة مسبقًا قبل أن يطلبها المشاهدون. */
    'worker_prefetch' => true,

    /** أقصى عدد مقاطع تُحمَّل مسبقًا في الجولة الواحدة لكل قناة. */
    'worker_prefetch_max' => 4,

    /** فاصل نبضة الحياة (heartbeat) بالثواني. */
    'worker_heartbeat_seconds' => 5,

    /* ─────────────────────── الاختبار فقط ─────────────────────── */

    /**
     * يسمح بمصدر http:// (بدون TLS) — للاختبار المحلي فقط.
     * اتركه false على الإنتاج.
     */
    'allow_insecure_source' => false,
];
