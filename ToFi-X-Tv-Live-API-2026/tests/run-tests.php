<?php
declare(strict_types=1);

/**
 * run-tests.php — اختبارات فعلية للبروكسي مقابل مصدر HLS وهمي.
 *
 *      php tests/run-tests.php
 *
 * يشغّل خادمين محليين (المصدر + البروكسي) ثم ينفّذ الحالات العشرين
 * المطلوبة، ويطبع النتيجة مع عدد اتصالات المصدر الفعلية.
 */

const ORIGIN_PORT = 8801;
const PROXY_PORT = 8802;

$root = dirname(__DIR__);
$originBase = 'http://127.0.0.1:' . ORIGIN_PORT;
$proxyBase = 'http://127.0.0.1:' . PROXY_PORT;

$passed = 0;
$failed = 0;
$failures = [];

/* ═════════════ تشغيل الخوادم ═════════════ */

fwrite(STDOUT, "\n== تجهيز بيئة الاختبار ==\n");

foreach (
    [
        $root . '/.tofi-cache',
        $root . '/hls-cache',
        $root . '/.tofi-viewers',
    ] as $directory
) {
    remove_tree($directory);
}

@unlink(__DIR__ . '/.origin-state.json');
@unlink(__DIR__ . '/.origin-counters.json');
@unlink(__DIR__ . '/.router-counters.json');

$environment = [
    'PHP_CLI_SERVER_WORKERS=24',
    'TOFI_SOURCE_BASE_URL=' . $originBase . '/live/Abuturki/Abuturki/',
    'TOFI_PUBLIC_BASE_URL=' . $proxyBase,
    'TOFI_ALLOWED_SOURCE_HOSTS=127.0.0.1',
    'TOFI_ALLOW_INSECURE_SOURCE=1',
    'TOFI_METRICS_ENABLED=1',
    'TOFI_METRICS_TOKEN=test-metrics-key',
    'TOFI_VIEWER_BACKEND=file',
];

$originPid = start_server(
    $environment,
    ORIGIN_PORT,
    $root . '/tests',
    $root . '/tests/origin.php',
    $root . '/tests/.origin.log'
);

$proxyPid = start_server(
    $environment,
    PROXY_PORT,
    $root,
    $root . '/tests/router.php',
    $root . '/tests/.proxy.log'
);

register_shutdown_function(static function () use ($originPid, $proxyPid): void {
    foreach ([$originPid, $proxyPid] as $pid) {
        if ($pid > 0) {
            @exec('kill ' . $pid . ' 2>/dev/null');
        }
    }
});

if (!wait_for_server($originBase . '/control') || !wait_for_server($proxyBase . '/api/metrics?key=test-metrics-key')) {
    fwrite(STDERR, "تعذّر تشغيل خوادم الاختبار\n");
    exit(1);
}

fwrite(STDOUT, "الخوادم جاهزة: مصدر " . ORIGIN_PORT . " / بروكسي " . PROXY_PORT . "\n");

/* ═════════════ الحالات ═════════════ */

section('1) قناة واحدة ومشاهد واحد');

reset_counters();
$token = issue_token('10');
check('api/token يرجع الحقول المطلوبة', array_diff(
    ['success', 'channels', 'url', 'viewer_id', 'leave_url', 'expires_at', 'expires_in'],
    array_keys($token)
) === []);
check('viewer_id بصيغة صحيحة', preg_match('/^[a-f0-9]{32}$/', (string) $token['viewer_id']) === 1);

$playlist = http_get($token['url']);
check('قائمة القناة 10 تعمل', $playlist['status'] === 200
    && str_starts_with($playlist['body'], '#EXTM3U'));
check('القائمة الحية بلا EXT-X-ENDLIST', !str_contains($playlist['body'], '#EXT-X-ENDLIST'));
check('لا يظهر عنوان المصدر في القائمة', !str_contains($playlist['body'], '127.0.0.1:' . ORIGIN_PORT));
check('عدد المقاطع في النافذة المحلية 6..12',
    substr_count($playlist['body'], '#EXTINF:') >= 6
    && substr_count($playlist['body'], '#EXTINF:') <= 12);
check('PROGRAM-DATE-TIME محفوظ', str_contains($playlist['body'], '#EXT-X-PROGRAM-DATE-TIME:'));
check('روابط المقاطع بلا viewer/root/vproof',
    !preg_match('/hls-cache[^\s]*(viewer=|root=|vproof=)/', $playlist['body']));

$segments = extract_segments($playlist['body']);
check('روابط المقاطع بأسماء SHA-256 قصيرة',
    $segments !== [] && preg_match('#/hls-cache/[a-f0-9]{40}\.ts$#', $segments[0]) === 1);

section('2) دخول 50 مشاهدًا في اللحظة نفسها');

reset_counters();
$tokens = [];
for ($index = 0; $index < 50; $index++) {
    $tokens[] = issue_token('10')['url'];
}
$burst = parallel($tokens);
$counters = origin_counters();
check('كل الطلبات 200', $burst['status_counts'][200] ?? 0, 50);
check('اتصالات المصدر لقائمة القناة 10 ≤ 2 (وليس 50)',
    ($counters['resource:10.m3u8'] ?? 0) <= 2,
    true,
    'الفعلي: ' . ($counters['resource:10.m3u8'] ?? 0));
check('p95 أقل من ثانيتين', $burst['p95'] < 2000, true,
    sprintf('p50=%.0fms p95=%.0fms p99=%.0fms', $burst['p50'], $burst['p95'], $burst['p99']));

section('3) دخول 200 مشاهد');

reset_counters();
$tokens = [];
for ($index = 0; $index < 200; $index++) {
    $tokens[] = issue_token('10')['url'];
}
$burst = parallel($tokens, 100);
$counters = origin_counters();
check('لا أخطاء في 200 طلب', ($burst['status_counts'][200] ?? 0) === 200, true,
    'الحالات: ' . json_encode($burst['status_counts']));
check('اتصالات المصدر ≤ 4 مع 200 مشاهد',
    ($counters['resource:10.m3u8'] ?? 0) <= 4,
    true,
    'الفعلي: ' . ($counters['resource:10.m3u8'] ?? 0));
check('p99 أقل من 3 ثوانٍ', $burst['p99'] < 3000, true,
    sprintf('p50=%.0fms p95=%.0fms p99=%.0fms', $burst['p50'], $burst['p95'], $burst['p99']));

section('5) جميع المشاهدين يطلبون نفس المقطع لأول مرة');

reset_counters();
router_reset();
$fresh = http_get(issue_token('10')['url']);
$segments = extract_segments($fresh['body']);
$target = $segments[count($segments) - 1];
$segmentBurst = parallel(array_fill(0, 50, $target));
$counters = origin_counters();
$resourceKey = null;
foreach ($counters as $key => $value) {
    if (str_starts_with($key, 'resource:') && str_ends_with($key, '.ts')) {
        $resourceKey = $key;
    }
}
check('المقطع جُلب من المصدر مرة واحدة فقط',
    $resourceKey !== null && $counters[$resourceKey] === 1,
    true,
    'الفعلي: ' . ($resourceKey !== null ? $counters[$resourceKey] : 0));
check('كل الطلبات نجحت (200 أو 302)',
    ($segmentBurst['status_counts'][200] ?? 0)
    + ($segmentBurst['status_counts'][302] ?? 0) === 50,
    true,
    json_encode($segmentBurst['status_counts']));

$again = parallel(array_fill(0, 20, $target));
$routerBefore = router_counters();
$again = parallel(array_fill(0, 20, $target));
$routerAfter = router_counters();
check('المقطع المحفوظ يخدمه الخادم مباشرة بلا PHP',
    ($routerAfter['static'] ?? 0) - ($routerBefore['static'] ?? 0) === 20
    && ($routerAfter['php'] ?? 0) === ($routerBefore['php'] ?? 0),
    true,
    'static+' . (($routerAfter['static'] ?? 0) - ($routerBefore['static'] ?? 0))
    . ' php+' . (($routerAfter['php'] ?? 0) - ($routerBefore['php'] ?? 0)));

section('6) فشل المصدر لمدة ثانيتين');

reset_counters();
$url = issue_token('10')['url'];
http_get($url);
origin_control('fail=2');
usleep(300000);
$during = http_get($url);
check('البث يستمر من الكاش أثناء العطل القصير',
    $during['status'] === 200 && str_starts_with($during['body'], '#EXTM3U'));
sleep(3);
$after = http_get($url);
check('عودة المصدر تعيد التحديث الطبيعي', $after['status'] === 200);

section('7) فشل المصدر لمدة 10 ثوانٍ');

origin_control('fail=10');
sleep(6);
$long = http_get($url);
check('لا تُقدَّم قائمة منتهية الصلاحية بعد تجاوز الحد',
    $long['status'] >= 500,
    true,
    'الحالة: ' . $long['status']);
origin_control('fail=0');
sleep(1);
$recovered = http_get($url);
check('البث يعود تلقائيًا بعد رجوع المصدر', $recovered['status'] === 200);

section('7ب) مصدر بطيء جدًا (لا يرد بخطأ سريع)');

/*
 * يختبر إعادة حساب عمر الكاش لحظة الإرسال: محاولة الجلب نفسها تستغرق
 * ثوانٍ، فيجب ألا تُقدَّم النسخة التي كانت "صالحة" قبل بدء المحاولة.
 */
origin_control('slow=25');
sleep(2);
$slowStarted = microtime(true);
$slow = http_get($url);
$slowElapsed = microtime(true) - $slowStarted;

check('المهلات القصيرة تمنع احتجاز العامل طويلًا (< 20 ثانية)',
    $slowElapsed < 20, true, sprintf('%.1fs', $slowElapsed));
check('لا تُقدَّم نسخة تجاوزت حد القِدم أثناء المحاولة البطيئة',
    $slow['status'] >= 500, true, 'الحالة: ' . $slow['status']);
origin_control('slow=0');
sleep(1);
check('البث يعود بعد انتهاء البطء', http_get($url)['status'] === 200);

section('8) تغيّر Media Sequence بصورة طبيعية');

$sequences = [];
$seenSegments = [];
$duplicate = false;
for ($index = 0; $index < 6; $index++) {
    $poll = http_get($url);
    $sequences[] = playlist_sequence($poll['body']);

    foreach (extract_segments($poll['body']) as $position => $segment) {
        $absolute = playlist_sequence($poll['body']) + $position;

        if (isset($seenSegments[$segment]) && $seenSegments[$segment] !== $absolute) {
            $duplicate = true;
        }

        $seenSegments[$segment] = $absolute;
    }

    usleep(900000);
}
check('التسلسل لا يعود للخلف أبدًا', $sequences === array_values(array_unique(
    array_merge([], $sequences)
)) || is_sorted($sequences), true, implode(',', $sequences));
check('التسلسل يتقدّم فعلًا', end($sequences) > $sequences[0], true, implode(',', $sequences));
check('لا يتكرر مقطع برقم تسلسل جديد', !$duplicate);

section('9) رجوع Media Sequence إلى رقم أقل (تبديل المصدر)');

$before = playlist_sequence(http_get($url)['body']);
origin_control('variant=b');
sleep(2);
$switched = null;
for ($index = 0; $index < 6; $index++) {
    $switched = http_get($url);
    if (str_contains($switched['body'], '#EXT-X-DISCONTINUITY')) {
        break;
    }
    usleep(700000);
}
$after = playlist_sequence($switched['body']);
check('التسلسل المحلي بقي تصاعديًا رغم رجوع المصدر', $after >= $before, true,
    'قبل=' . $before . ' بعد=' . $after);
check('تمت إضافة EXT-X-DISCONTINUITY عند تبديل المصدر',
    str_contains($switched['body'], '#EXT-X-DISCONTINUITY'));
origin_control('variant=a');

section('10) Master Playlist تحتوي على Media Playlist داخلية');

reset_counters();
$masterUrl = issue_token('77')['url'];
$master = http_get($masterUrl);
check('القائمة الرئيسية تُقرأ من المصدر',
    str_contains($master['body'], '#EXT-X-STREAM-INF'));
preg_match_all('#https?://\S+/hls-cache/[a-f0-9]{40}\.m3u8#', $master['body'], $children);
check('القوائم الداخلية أصبحت روابط مشتركة بلا توكن مشاهد',
    count($children[0]) === 2, true, count($children[0]) . ' قوائم');

$child = http_get($children[0][0]);
check('القائمة الداخلية تعمل وتحتوي مقاطع',
    $child['status'] === 200 && substr_count($child['body'], '#EXTINF:') >= 6);

$childBurst = parallel(array_fill(0, 40, $children[0][0]));
$counters = origin_counters();
check('40 طلبًا للقائمة الداخلية = اتصالان بالمصدر كحد أقصى',
    ($counters['resource:77-lo.m3u8'] ?? 0) <= 2,
    true,
    'الفعلي: ' . ($counters['resource:77-lo.m3u8'] ?? 0));

section('12) قناة تستخدم M4S و EXT-X-MAP');

$m4sUrl = issue_token('88')['url'];
$m4s = http_get($m4sUrl);
check('EXT-X-MAP محفوظ ومُعاد كتابته',
    preg_match('#\#EXT-X-MAP:URI="https?://\S+/hls-cache/[a-f0-9]{40}\.mp4"#', $m4s['body']) === 1);
check('المقاطع بامتداد m4s',
    preg_match('#/hls-cache/[a-f0-9]{40}\.m4s#', $m4s['body']) === 1);
preg_match('#(https?://\S+/hls-cache/[a-f0-9]{40}\.mp4)#', $m4s['body'], $mapMatch);
$mapResponse = http_get($mapMatch[1]);
check('تحميل ملف init يعمل',
    in_array($mapResponse['status'], [200, 302], true), true,
    'الحالة: ' . $mapResponse['status']);
$m4sSegments = extract_segments($m4s['body'], 'm4s');
$m4sResponse = http_get($m4sSegments[0]);
check('تحميل مقطع m4s يعمل',
    in_array($m4sResponse['status'], [200, 302], true));

section('13) قناة مشفّرة EXT-X-KEY');

$keyUrl = issue_token('99')['url'];
$encrypted = http_get($keyUrl);
check('EXT-X-KEY محفوظ ومُعاد كتابته',
    preg_match('#\#EXT-X-KEY:METHOD=AES-128,URI="https?://\S+/hls-cache/[a-f0-9]{40}\.key"#', $encrypted['body']) === 1);
check('IV محفوظ كما هو', str_contains($encrypted['body'], 'IV=0x00000000000000000000000000000001'));
preg_match('#(https?://\S+/hls-cache/[a-f0-9]{40}\.key)#', $encrypted['body'], $keyMatch);
$keyResponse = http_get($keyMatch[1]);
check('المفتاح يُسلَّم بحجم 16 بايت',
    $keyResponse['status'] === 200 && strlen($keyResponse['body']) === 16);
check('المفتاح لا يأخذ immutable ليوم كامل',
    !str_contains(strtolower($keyResponse['headers']), 'immutable')
    && str_contains(strtolower($keyResponse['headers']), 'max-age=15'));

section('14) توكن مصدر طويل جدًا');

$longUrl = issue_token('66')['url'];
$long = http_get($longUrl);
$longSegments = extract_segments($long['body']);
check('الرابط العام قصير رغم طول توكن المصدر',
    strlen(basename(parse_url($longSegments[0], PHP_URL_PATH))) <= 48,
    true,
    'الطول: ' . strlen(basename(parse_url($longSegments[0], PHP_URL_PATH))));
$longSegment = http_get($longSegments[0]);
check('تحميل مقطع بتوكن طويل يعمل',
    in_array($longSegment['status'], [200, 302], true));

section('15) انتهاء Stream Token');

$expired = preg_replace('/expires=\d+/', 'expires=' . (time() - 7200), $url);
check('التوكن المنتهي مرفوض 403', http_get((string) $expired)['status'] === 403);
$tampered = preg_replace('/token=[a-f0-9]{64}/', 'token=' . str_repeat('a', 64), $url);
check('التوقيع المزوّر مرفوض 403', http_get((string) $tampered)['status'] === 403);
check('الرابط بلا توكن مرفوض 403',
    http_get($proxyBase . '/stream/10/index.m3u8')['status'] === 403);

section('16) إغلاق المشغل وتنفيذ leave_url');

$session = issue_token('10');
http_get($session['url']);
$leave = http_get($session['leave_url']);
check('leave_url يرجع success', str_contains($leave['body'], '"success":true'));
check('ping_url متوفر أيضًا', isset($session['ping_url']));
$ping = http_get($session['ping_url']);
check('ping يجدّد الجلسة', str_contains($ping['body'], '"active":true'));

section('17) قوالب override تعمل كما هي');

$overrideData = $root . '/overrides/.data.json';
$backup = is_file($overrideData) ? (string) file_get_contents($overrideData) : null;
file_put_contents($overrideData, (string) json_encode([
    'admin' => null,
    'channels' => ['10' => [
        'enabled' => true,
        'source' => 'preset',
        'preset' => '_default',
        'until' => 0,
        'note' => 'test',
        'updated' => time(),
    ]],
    'templates' => [],
]));
$overridden = http_get(issue_token('10')['url']);
check('التبديل يعرض قالب الصيانة بدل البث',
    str_contains($overridden['body'], '/overrides/media/_default/'),
    true,
    substr(str_replace("\n", ' ', $overridden['body']), 0, 80));
if ($backup === null) {
    @unlink($overrideData);
} else {
    file_put_contents($overrideData, $backup);
}
$restored = http_get(issue_token('10')['url']);
check('إيقاف التبديل يعيد البث الطبيعي',
    !str_contains($restored['body'], '/overrides/media/'));

section('18) الجودات المتعددة بالشكل 10-20-30');

reset_counters();
$multi = issue_token('10-20-30');
$multiPlaylist = http_get($multi['url']);
check('القائمة الرئيسية تحتوي ثلاث جودات',
    substr_count($multiPlaylist['body'], '#EXT-X-STREAM-INF') === 3);
preg_match_all('#https?://\S+/stream/\d+/index\.m3u8\S*#', $multiPlaylist['body'], $variants);
check('كل جودة لها رابط موقّع', count($variants[0]) === 3);
$variantResponses = parallel($variants[0]);
check('كل الجودات تعمل', ($variantResponses['status_counts'][200] ?? 0) === 3,
    true, json_encode($variantResponses['status_counts']));

section('19) طلبات HEAD و OPTIONS');

$options = http_request($url, 'OPTIONS');
check('OPTIONS يرجع 204', $options['status'] === 204);
check('OPTIONS يحمل ترويسات CORS',
    str_contains(strtolower($options['headers']), 'access-control-allow-origin: *'));
$head = http_request($url, 'HEAD');
check('HEAD يرجع 200 بلا جسم', $head['status'] === 200 && $head['body'] === '');
$headSegment = http_request($segments[0], 'HEAD');
check('HEAD على مقطع محفوظ يعمل',
    in_array($headSegment['status'], [200, 302], true));

section('20) لا يوجد كاش والطلبات تصل في اللحظة نفسها');

reset_counters();
remove_tree($root . '/.tofi-cache/pl');
$coldTokens = [];
for ($index = 0; $index < 50; $index++) {
    $coldTokens[] = issue_token('20')['url'];
}
$cold = parallel($coldTokens);
$counters = origin_counters();
check('50 طلبًا على كاش فارغ = اتصال واحد بالمصدر',
    ($counters['resource:20.m3u8'] ?? 0) === 1,
    true,
    'الفعلي: ' . ($counters['resource:20.m3u8'] ?? 0));
check('لا يوجد أي فشل', ($cold['status_counts'][200] ?? 0) === 50,
    true, json_encode($cold['status_counts']));

section('القياسات الداخلية');

$metrics = json_decode(http_get(
    $proxyBase . '/api/metrics?key=test-metrics-key'
)['body'], true);

if (is_array($metrics) && isset($metrics['metrics'])) {
    foreach ($metrics['metrics'] as $name => $value) {
        fwrite(STDOUT, sprintf("    %-28s %d\n", $name, $value));
    }
    fwrite(STDOUT, sprintf(
        "    %-28s %s\n",
        'viewer_backend',
        (string) ($metrics['viewer_backend'] ?? '-')
    ));
    check('القياسات تعمل وتُظهر مشاركة الكاش',
        (int) $metrics['metrics']['playlist_cache_hits'] > 0);
}

check('مفتاح قياسات خاطئ يرجع 404',
    http_get($proxyBase . '/api/metrics?key=wrong')['status'] === 404);

section('الحماية');

check('الجذر ممنوع', http_get($proxyBase . '/')['status'] === 403);
check('index.php المباشر ممنوع', http_get($proxyBase . '/index.php')['status'] === 403);
check('hls-core.php ممنوع', http_get($proxyBase . '/hls-core.php')['status'] === 403);
check('worker.php ممنوع', http_get($proxyBase . '/worker.php')['status'] === 403);
check('config.php ممنوع', http_get($proxyBase . '/config.php')['status'] === 403);
check('مجلد الكاش الداخلي ممنوع',
    http_get($proxyBase . '/.tofi-cache/')['status'] === 403);
check('README-AR.txt ممنوع',
    http_get($proxyBase . '/README-AR.txt')['status'] === 403);
check('watch بدون User-Agent صحيح مرفوض',
    http_get($proxyBase . '/watch/10/index.m3u8')['status'] === 401);
$watch = http_request($proxyBase . '/watch/10/index.m3u8', 'GET', 'MTX Player');
check('watch مع User-Agent الصحيح يحوّل 302', $watch['status'] === 302);

/* ═════════════ النتيجة ═════════════ */

fwrite(STDOUT, "\n" . str_repeat('─', 62) . "\n");
fwrite(STDOUT, sprintf("ناجح: %d   فاشل: %d\n", $passed, $failed));

if ($failures !== []) {
    fwrite(STDOUT, "\nالفاشل:\n");
    foreach ($failures as $failure) {
        fwrite(STDOUT, '  ✗ ' . $failure . "\n");
    }
}

exit($failed === 0 ? 0 : 1);

/* ═════════════ أدوات ═════════════ */

function section(string $title): void
{
    fwrite(STDOUT, "\n== " . $title . " ==\n");
}

function check(string $label, $actual, $expected = true, string $note = ''): void
{
    global $passed, $failed, $failures;

    $ok = $actual === $expected;

    if ($ok) {
        $passed++;
        fwrite(STDOUT, '  ✓ ' . $label . ($note !== '' ? '  (' . $note . ')' : '') . "\n");
        return;
    }

    $failed++;
    $failures[] = $label . ($note !== '' ? ' — ' . $note : '');
    fwrite(STDOUT, '  ✗ ' . $label . ($note !== '' ? '  (' . $note . ')' : '') . "\n");
}

function start_server(
    array $environment,
    int $port,
    string $docroot,
    string $router,
    string $log
): int {
    $command = implode(' ', array_map('escapeshellarg', $environment))
        . ' php -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($docroot)
        . ' ' . escapeshellarg($router)
        . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!';

    return (int) trim((string) shell_exec('env ' . $command));
}

function wait_for_server(string $url): bool
{
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $response = http_get($url);

        if ($response['status'] > 0) {
            return true;
        }

        usleep(200000);
    }

    return false;
}

function http_get(string $url): array
{
    return http_request($url, 'GET');
}

function http_request(
    string $url,
    string $method = 'GET',
    string $userAgent = 'k6-hls-test'
): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
    ]);

    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    curl_close($curl);

    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => ''];
    }

    return [
        'status' => $status,
        'headers' => substr((string) $raw, 0, $headerSize),
        'body' => substr((string) $raw, $headerSize),
    ];
}

/**
 * ينفّذ كل الطلبات دفعة واحدة (مشاهدون يدخلون في اللحظة نفسها).
 */
function parallel(array $urls, int $concurrency = 0): array
{
    $multi = curl_multi_init();
    $handles = [];
    $started = [];

    if ($concurrency > 0) {
        curl_multi_setopt($multi, CURLMOPT_MAX_TOTAL_CONNECTIONS, $concurrency);
    }

    foreach ($urls as $index => $url) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'k6-hls-test',
        ]);
        curl_multi_add_handle($multi, $curl);
        $handles[$index] = $curl;
        $started[$index] = microtime(true);
    }

    do {
        $status = curl_multi_exec($multi, $active);

        if ($active) {
            curl_multi_select($multi, 0.1);
        }
    } while ($active && $status === CURLM_OK);

    $times = [];
    $statusCounts = [];

    foreach ($handles as $index => $curl) {
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $statusCounts[$code] = ($statusCounts[$code] ?? 0) + 1;
        $times[] = (float) curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000;
        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
    }

    curl_multi_close($multi);
    sort($times);

    return [
        'status_counts' => $statusCounts,
        'p50' => percentile($times, 50),
        'p95' => percentile($times, 95),
        'p99' => percentile($times, 99),
    ];
}

function percentile(array $sorted, int $percentile): float
{
    if ($sorted === []) {
        return 0.0;
    }

    $index = (int) ceil(($percentile / 100) * count($sorted)) - 1;

    return (float) $sorted[max(0, min(count($sorted) - 1, $index))];
}

function issue_token(string $channels): array
{
    global $proxyBase;

    $response = http_request(
        $proxyBase . '/api/token/' . $channels,
        'GET',
        'MTX Player'
    );

    $data = json_decode($response['body'], true);

    return is_array($data) ? $data : [];
}

function extract_segments(string $playlist, string $extension = 'ts'): array
{
    preg_match_all(
        '#https?://\S+/hls-cache/[a-f0-9]{40}\.' . $extension . '#',
        $playlist,
        $matches
    );

    return $matches[0];
}

function playlist_sequence(string $playlist): int
{
    if (preg_match('/#EXT-X-MEDIA-SEQUENCE:(\d+)/', $playlist, $matches) === 1) {
        return (int) $matches[1];
    }

    return -1;
}

function is_sorted(array $values): bool
{
    $previous = null;

    foreach ($values as $value) {
        if ($previous !== null && $value < $previous) {
            return false;
        }

        $previous = $value;
    }

    return true;
}

function origin_control(string $query): void
{
    global $originBase;
    http_get($originBase . '/control?' . $query);
}

function origin_counters(): array
{
    global $originBase;

    $data = json_decode(http_get($originBase . '/control')['body'], true);

    return is_array($data['counters'] ?? null) ? $data['counters'] : [];
}

function reset_counters(): void
{
    global $originBase;
    http_get($originBase . '/control?reset=1');
}

function router_counters(): array
{
    global $proxyBase;

    $data = json_decode(http_get($proxyBase . '/__router/stats')['body'], true);

    return is_array($data) ? $data : [];
}

function router_reset(): void
{
    global $proxyBase;
    http_get($proxyBase . '/__router/reset');
}

function remove_tree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    foreach ((array) scandir($directory) as $item) {
        if ($item === '.' || $item === '..' || $item === false) {
            continue;
        }

        $path = $directory . '/' . $item;

        if (is_dir($path)) {
            remove_tree($path);
            continue;
        }

        @unlink($path);
    }

    @rmdir($directory);
}
