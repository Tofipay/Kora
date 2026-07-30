<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  viewers.php — إحصاء المتصلين الآن (منفصل تمامًا عن مسار البث)
 * ───────────────────────────────────────────────────────────────────────────
 *  قواعد ثابتة:
 *    • لا يمر مسار المقاطع (TS/M4S) على هذا الملف إطلاقًا.
 *    • لا تُكتب بيانات المشاهد أكثر من مرة خلال viewer_touch_min_interval.
 *    • أي فشل هنا لا يوقف الفيديو أبدًا (كل الاستدعاءات محاطة بمعالجة أخطاء).
 *
 *  التخزين بالترتيب: Redis ← APCu ← SQLite (WAL) ← ملفات محسّنة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/hls-core.php';

if (!defined('VIEWER_ROOT')) {
    define('VIEWER_ROOT', HLS_ROOT . '/.tofi-viewers');
    define('VIEWER_ACTIVE_SECONDS', (int) hls_config('viewer_active_seconds'));
    define(
        'VIEWER_RETENTION_SECONDS',
        (int) hls_config('viewer_retention_seconds')
    );
}

/* ═════════════════════════ أدوات التحقق ═════════════════════════ */

function viewer_new_id(): string
{
    return bin2hex(random_bytes(16));
}

function viewer_valid_id(string $viewerId): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $viewerId) === 1;
}

function viewer_valid_channel(int $channel): bool
{
    return $channel >= 1 && $channel <= 999999;
}

function viewer_ensure_directory(string $directory): void
{
    if (
        !is_dir($directory)
        && !@mkdir($directory, 0750, true)
        && !is_dir($directory)
    ) {
        throw new RuntimeException('Unable to create viewer directory');
    }
}

function viewer_channel_directory(int $channel): string
{
    return VIEWER_ROOT . '/live/' . $channel;
}

/* ═════════════════════════ اختيار مخزن البيانات ═════════════════════════ */

/**
 * يختار المخزن مرة واحدة لكل عملية ويحتفظ به. الفشل يتدرّج للأدنى بهدوء.
 */
function viewer_backend(): string
{
    static $backend = null;

    if ($backend !== null) {
        return $backend;
    }

    $requested = strtolower((string) hls_config('viewer_backend', 'auto'));

    if ($requested !== 'auto') {
        if ($requested === 'redis' && viewer_redis() !== null) {
            return $backend = 'redis';
        }

        if (
            $requested === 'apcu'
            && function_exists('apcu_enabled')
            && apcu_enabled()
        ) {
            return $backend = 'apcu';
        }

        if ($requested === 'sqlite' && viewer_sqlite() !== null) {
            return $backend = 'sqlite';
        }

        return $backend = 'file';
    }

    /*
     * مهم للاستضافة المشتركة (Hostinger وغيرها):
     * الوضع التلقائي لا يجرّب Redis إطلاقًا. تجربته تعني محاولة اتصال TCP
     * في كل طلب على خادم لا يوجد فيه Redis أصلًا، وقد تُكلّف مئات
     * المللي ثانية إن كان المنفذ محجوبًا بدل مرفوض.
     *
     * على VPS فيه Redis اضبطه صراحةً:  'viewer_backend' => 'redis'
     */
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        return $backend = 'apcu';
    }

    if (viewer_sqlite() !== null) {
        return $backend = 'sqlite';
    }

    return $backend = 'file';
}

function viewer_backend_name(): string
{
    try {
        return viewer_backend();
    } catch (Throwable $error) {
        return 'file';
    }
}

function viewer_redis(): ?object
{
    static $client = false;

    if ($client !== false) {
        return $client;
    }

    $client = null;

    if (!class_exists('Redis')) {
        return $client;
    }

    try {
        $redis = new Redis();
        $connected = @$redis->connect(
            (string) hls_config('redis_host', '127.0.0.1'),
            (int) hls_config('redis_port', 6379),
            0.35
        );

        if ($connected !== true) {
            return $client = null;
        }

        $auth = (string) hls_config('redis_auth', '');

        if ($auth !== '') {
            @$redis->auth($auth);
        }

        @$redis->setOption(Redis::OPT_READ_TIMEOUT, 0.5);
        $redis->ping();

        return $client = $redis;
    } catch (Throwable $error) {
        return $client = null;
    }
}

function viewer_sqlite(): ?PDO
{
    static $connection = false;

    if ($connection !== false) {
        return $connection;
    }

    $connection = null;

    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return $connection;
    }

    try {
        viewer_ensure_directory(VIEWER_ROOT);
        $file = VIEWER_ROOT . '/viewers.sqlite';
        $isNew = !is_file($file);

        $pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);

        /* هذان رخيصان (لا يلمسان القرص) ويجب ضبطهما لكل اتصال. */
        $pdo->exec('PRAGMA busy_timeout=1500');
        $pdo->exec('PRAGMA synchronous=NORMAL');

        /*
         * إنشاء الجداول و WAL مرة واحدة فقط عند أول إنشاء للملف.
         * تكرارها في كل طلب يعني عمليات DDL على المسار الساخن بلا داعٍ.
         */
        if ($isNew) {
            viewer_sqlite_schema($pdo);
            @chmod($file, 0640);
        }

        return $connection = $pdo;
    } catch (Throwable $error) {
        return $connection = null;
    }
}

function viewer_sqlite_schema(PDO $pdo): void
{
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS viewers ('
        . 'viewer TEXT NOT NULL, channel INTEGER NOT NULL, '
        . 'expires_at INTEGER NOT NULL, '
        . 'PRIMARY KEY (viewer, channel))'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS viewers_expiry '
        . 'ON viewers (expires_at)'
    );
}

/**
 * ينفّذ عملية على SQLite، وإن كان الجدول مفقودًا (حُذف الملف يدويًا مثلًا)
 * ينشئ المخطط مرة واحدة ويعيد المحاولة.
 */
function viewer_sqlite_run(callable $operation, PDO $pdo)
{
    try {
        return $operation($pdo);
    } catch (PDOException $error) {
        if (!str_contains($error->getMessage(), 'no such table')) {
            throw $error;
        }

        viewer_sqlite_schema($pdo);

        return $operation($pdo);
    }
}

function viewer_redis_key(int $channel): string
{
    return (string) hls_config('redis_prefix', 'tofi:v:') . 'ch:' . $channel;
}

/* ═════════════════════════ تجديد الجلسة ═════════════════════════ */

/**
 * يجدّد جلسة المشاهدة. لا يكتب شيئًا إذا جُدّدت قبل أقل من
 * viewer_touch_min_interval ثانية — هذا هو حدّ الكتابة المطلوب.
 */
function viewer_touch(
    string $viewerId,
    int $channel,
    int $leaseSeconds = 0
): void {
    if (!viewer_valid_id($viewerId) || !viewer_valid_channel($channel)) {
        return;
    }

    if ($leaseSeconds <= 0) {
        $leaseSeconds = (int) hls_config('viewer_active_seconds', 30);
    }

    $leaseSeconds = max(10, min(300, $leaseSeconds));
    $minimumInterval = max(
        1,
        min(
            $leaseSeconds - 2,
            (int) hls_config('viewer_touch_min_interval', 10)
        )
    );

    $now = time();
    $expiresAt = $now + $leaseSeconds;

    /* عتبة تخطّي الكتابة: ما زالت الجلسة طازجة بما يكفي. */
    $skipThreshold = $expiresAt - $minimumInterval;

    switch (viewer_backend()) {
        case 'redis':
            viewer_touch_redis($viewerId, $channel, $expiresAt, $skipThreshold, $leaseSeconds);
            return;

        case 'apcu':
            viewer_touch_apcu($viewerId, $channel, $expiresAt, $skipThreshold, $leaseSeconds);
            return;

        case 'sqlite':
            viewer_touch_sqlite($viewerId, $channel, $expiresAt, $skipThreshold);
            return;

        default:
            viewer_touch_file($viewerId, $channel, $expiresAt, $skipThreshold);
    }
}

function viewer_touch_redis(
    string $viewerId,
    int $channel,
    int $expiresAt,
    int $skipThreshold,
    int $leaseSeconds
): void {
    $redis = viewer_redis();

    if ($redis === null) {
        return;
    }

    $key = viewer_redis_key($channel);
    $current = $redis->zScore($key, $viewerId);

    if (is_numeric($current) && (int) $current >= $skipThreshold) {
        return;
    }

    $redis->zAdd($key, $expiresAt, $viewerId);
    $redis->expire($key, $leaseSeconds + 120);
    $redis->sAdd(
        (string) hls_config('redis_prefix', 'tofi:v:') . 'channels',
        (string) $channel
    );
}

function viewer_touch_apcu(
    string $viewerId,
    int $channel,
    int $expiresAt,
    int $skipThreshold,
    int $leaseSeconds
): void {
    $key = 'tofi_v_' . $channel . '_' . $viewerId;
    $current = apcu_fetch($key);

    if (is_int($current) && $current >= $skipThreshold) {
        return;
    }

    apcu_store($key, $expiresAt, $leaseSeconds + 5);
}

function viewer_touch_sqlite(
    string $viewerId,
    int $channel,
    int $expiresAt,
    int $skipThreshold
): void {
    $pdo = viewer_sqlite();

    if ($pdo === null) {
        return;
    }

    /*
     * جملة واحدة قصيرة: لا تكتب شيئًا إن كانت الجلسة مجدّدة حديثًا،
     * فلا يوجد قفل كتابة على المسار الساخن بلا داعٍ.
     */
    viewer_sqlite_run(
        static function (PDO $pdo) use (
            $viewerId,
            $channel,
            $expiresAt,
            $skipThreshold
        ): void {
            $statement = $pdo->prepare(
                'INSERT INTO viewers (viewer, channel, expires_at) '
                . 'VALUES (:viewer, :channel, :expires) '
                . 'ON CONFLICT(viewer, channel) DO UPDATE SET '
                . 'expires_at = excluded.expires_at '
                . 'WHERE viewers.expires_at < :threshold'
            );

            $statement->execute([
                ':viewer' => $viewerId,
                ':channel' => $channel,
                ':expires' => $expiresAt,
                ':threshold' => $skipThreshold,
            ]);
        },
        $pdo
    );
}

function viewer_touch_file(
    string $viewerId,
    int $channel,
    int $expiresAt,
    int $skipThreshold
): void {
    $directory = viewer_channel_directory($channel);
    $presenceFile = $directory . '/' . $viewerId . '.live';

    clearstatcache(true, $presenceFile);

    if (is_file($presenceFile)) {
        $current = (int) @filemtime($presenceFile);

        /* قراءة حالة فقط — بلا كتابة — عندما تكون الجلسة طازجة. */
        if ($current >= $skipThreshold) {
            return;
        }

        @touch($presenceFile, $expiresAt);
        return;
    }

    viewer_ensure_directory($directory);
    $handle = @fopen($presenceFile, 'c');

    if (is_resource($handle)) {
        @fclose($handle);
        @chmod($presenceFile, 0640);
    }

    @touch($presenceFile, $expiresAt);
}

/* ═════════════════════════ إنهاء الجلسة ═════════════════════════ */

function viewer_forget(string $viewerId, int $channel): void
{
    if (!viewer_valid_id($viewerId) || !viewer_valid_channel($channel)) {
        return;
    }

    switch (viewer_backend()) {
        case 'redis':
            $redis = viewer_redis();

            if ($redis !== null) {
                $redis->zRem(viewer_redis_key($channel), $viewerId);
            }

            return;

        case 'apcu':
            apcu_delete('tofi_v_' . $channel . '_' . $viewerId);
            return;

        case 'sqlite':
            $pdo = viewer_sqlite();

            if ($pdo !== null) {
                $statement = $pdo->prepare(
                    'DELETE FROM viewers WHERE viewer = :viewer '
                    . 'AND channel = :channel'
                );
                $statement->execute([
                    ':viewer' => $viewerId,
                    ':channel' => $channel,
                ]);
            }

            return;

        default:
            $presenceFile = viewer_channel_directory($channel)
                . '/' . $viewerId . '.live';

            if (is_file($presenceFile)) {
                @unlink($presenceFile);
            }
    }
}

/* ═════════════════════════ الإحصاء (لوحة التحكم فقط) ═════════════════════════ */

/**
 * يعيد الإجمالي والتوزيع لكل قناة من الجلسات النشطة فقط.
 * يُستدعى من panel.php المحمي، ولا يُستدعى من أي مسار بث.
 */
function viewer_stats(?int $selectedChannel = null): array
{
    $now = time();

    switch (viewer_backend()) {
        case 'redis':
            $counts = viewer_counts_redis($now);
            break;

        case 'apcu':
            $counts = viewer_counts_apcu($now);
            break;

        case 'sqlite':
            $counts = viewer_counts_sqlite($now);
            break;

        default:
            $counts = viewer_counts_file($now);
    }

    ksort($counts, SORT_NUMERIC);

    $channels = [];
    $total = 0;

    foreach ($counts as $channel => $count) {
        if ($count < 1) {
            continue;
        }

        $channels[] = ['id' => (int) $channel, 'count' => (int) $count];
        $total += (int) $count;
    }

    return [
        'total' => $total,
        'active_channels' => count($channels),
        'channels' => $channels,
        'selected_channel' => $selectedChannel,
        'selected_count' => $selectedChannel !== null
            ? (int) ($counts[$selectedChannel] ?? 0)
            : null,
        'lease_seconds' => (int) hls_config('viewer_active_seconds', 30),
        'backend' => viewer_backend_name(),
        'updated_at' => $now,
    ];
}

function viewer_counts_redis(int $now): array
{
    $redis = viewer_redis();

    if ($redis === null) {
        return [];
    }

    $prefix = (string) hls_config('redis_prefix', 'tofi:v:');
    $channels = $redis->sMembers($prefix . 'channels');
    $counts = [];

    foreach ((array) $channels as $channelName) {
        if (preg_match('/^[1-9][0-9]{0,5}$/', (string) $channelName) !== 1) {
            continue;
        }

        $channel = (int) $channelName;
        $key = viewer_redis_key($channel);

        $redis->zRemRangeByScore($key, '-inf', (string) $now);
        $count = (int) $redis->zCard($key);

        if ($count > 0) {
            $counts[$channel] = $count;
        } else {
            $redis->sRem($prefix . 'channels', (string) $channelName);
        }
    }

    return $counts;
}

function viewer_counts_apcu(int $now): array
{
    if (!class_exists('APCUIterator')) {
        return [];
    }

    $counts = [];
    $iterator = new APCUIterator('/^tofi_v_/', APC_ITER_KEY | APC_ITER_VALUE);

    foreach ($iterator as $item) {
        if (
            preg_match(
                '/^tofi_v_([1-9][0-9]{0,5})_[a-f0-9]{32}$/',
                (string) $item['key'],
                $matches
            ) !== 1
        ) {
            continue;
        }

        if ((int) $item['value'] < $now) {
            apcu_delete((string) $item['key']);
            continue;
        }

        $channel = (int) $matches[1];
        $counts[$channel] = ($counts[$channel] ?? 0) + 1;
    }

    return $counts;
}

function viewer_counts_sqlite(int $now): array
{
    $pdo = viewer_sqlite();

    if ($pdo === null) {
        return [];
    }

    return viewer_sqlite_run(
        static function (PDO $pdo) use ($now): array {
            $pdo->prepare('DELETE FROM viewers WHERE expires_at < :cutoff')
                ->execute([
                    ':cutoff' => $now
                        - (int) hls_config('viewer_retention_seconds', 120),
                ]);

            $statement = $pdo->prepare(
                'SELECT channel, COUNT(*) AS total FROM viewers '
                . 'WHERE expires_at >= :now GROUP BY channel'
            );
            $statement->execute([':now' => $now]);

            $counts = [];

            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[(int) $row['channel']] = (int) $row['total'];
            }

            return $counts;
        },
        $pdo
    );
}

function viewer_counts_file(int $now): array
{
    $counts = [];
    $liveRoot = VIEWER_ROOT . '/live';

    if (!is_dir($liveRoot)) {
        return $counts;
    }

    $retention = (int) hls_config('viewer_retention_seconds', 120);

    foreach (glob($liveRoot . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $channelName = basename($directory);

        if (preg_match('/^[1-9][0-9]{0,5}$/', $channelName) !== 1) {
            continue;
        }

        $channel = (int) $channelName;
        $count = 0;
        $remaining = 0;

        foreach (glob($directory . '/*.live') ?: [] as $presenceFile) {
            clearstatcache(true, $presenceFile);
            $expiresAt = @filemtime($presenceFile);

            if (!is_int($expiresAt)) {
                continue;
            }

            if ($expiresAt >= $now) {
                $count++;
                $remaining++;
                continue;
            }

            if ($expiresAt < $now - $retention) {
                @unlink($presenceFile);
                continue;
            }

            $remaining++;
        }

        if ($count > 0) {
            $counts[$channel] = $count;
        }

        if ($remaining === 0) {
            @rmdir($directory);
        }
    }

    return $counts;
}

/* ═════════════════════════ توافق رجعي ═════════════════════════ */

/**
 * أُبقيت هاتان الدالتان للتوافق مع الروابط الموقّعة الصادرة قبل التحديث.
 * لم تعد روابط المقاطع أو القوائم الداخلية تحمل بيانات مشاهد إطلاقًا.
 */
function viewer_create_proof(
    string $viewerId,
    int $channel,
    string $resource = ''
): string {
    if (!viewer_valid_id($viewerId) || !viewer_valid_channel($channel)) {
        return '';
    }

    $secret = defined('STREAM_SIGNING_SECRET')
        ? STREAM_SIGNING_SECRET
        : (string) hls_config('stream_signing_secret');

    if ($secret === '') {
        return '';
    }

    return hash_hmac(
        'sha256',
        'viewer|' . $viewerId . '|' . $channel . '|' . $resource,
        $secret
    );
}

function viewer_valid_proof(
    string $viewerId,
    int $channel,
    string $providedProof,
    string $resource = ''
): bool {
    if (preg_match('/^[a-f0-9]{64}$/', $providedProof) !== 1) {
        return false;
    }

    $expected = viewer_create_proof($viewerId, $channel, $resource);

    return $expected !== ''
        && hash_equals($expected, strtolower($providedProof));
}
