<?php

declare(strict_types=1);

use BtolatApi\BtolatScraper;
use BtolatApi\Cache;
use BtolatApi\HttpClient;

require dirname(__DIR__) . '/src/Config.php';
require dirname(__DIR__) . '/src/Cache.php';
require dirname(__DIR__) . '/src/HttpClient.php';
require dirname(__DIR__) . '/src/BtolatScraper.php';

$cache = new Cache(dirname(__DIR__) . '/storage/test-cache', 60);
$scraper = new BtolatScraper(new HttpClient($cache));
$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    try {
        $callback();
        $tests[] = ['name' => $name, 'passed' => true, 'error' => null];
        fwrite(STDOUT, "[PASS] {$name}\n");
    } catch (Throwable $exception) {
        $tests[] = ['name' => $name, 'passed' => false, 'error' => $exception->getMessage()];
        fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n");
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

test('قسم الكل يعيد بطاقات فيديو سليمة', function () use ($scraper): void {
    $result = $scraper->videos('all', 1, 1, true);
    assertTrue(count($result['videos']) > 0, 'لم تعد الصفحة الأولى أي فيديو.');
    $video = $result['videos'][0];
    assertTrue((int) ($video['id'] ?? 0) > 0, 'معرّف الفيديو مفقود.');
    assertTrue(trim((string) ($video['title'] ?? '')) !== '', 'عنوان الفيديو مفقود.');
    assertTrue(str_starts_with((string) ($video['page_url'] ?? ''), 'https://mobile.btolat.com/video/'), 'رابط صفحة الفيديو غير صالح.');
    assertTrue(str_starts_with((string) ($video['thumbnail'] ?? ''), 'https://'), 'الصورة المصغرة غير صالحة.');
});

test('التحميل المتتابع يعيد الصفحة الثانية دون تكرار', function () use ($scraper): void {
    $first = $scraper->videos('all', 1, 1, true);
    $second = $scraper->videos('all', 2, 1, true);
    assertTrue(count($second['videos']) > 0, 'الدفعة الثانية فارغة.');
    $firstIds = array_column($first['videos'], 'id');
    $secondIds = array_column($second['videos'], 'id');
    assertTrue(array_intersect($firstIds, $secondIds) === [], 'وجد تكرار بين الدفعتين الأولى والثانية.');
});

test('تصنيف كأس العالم يعيد اسم التصنيف والروابط', function () use ($scraper): void {
    $result = $scraper->videos('world-cup', 1, 1, true);
    assertTrue(count($result['videos']) > 0, 'تصنيف كأس العالم فارغ.');
    $video = $result['videos'][0];
    assertTrue(isset($video['category']['name']), 'اسم التصنيف غير موجود.');
    assertTrue(str_contains((string) $video['category']['url'], '/league/'), 'رابط التصنيف غير صالح.');
});

test('تفاصيل الفيديو تعيد رابط الصفحة وبيانات المشغل القابلة للاكتشاف', function () use ($scraper): void {
    $listing = $scraper->videos('all', 1, 1, true);
    $id = (int) $listing['videos'][0]['id'];
    $detail = $scraper->video($id, true);
    assertTrue((int) $detail['id'] === $id, 'معرّف التفاصيل لا يطابق المطلوب.');
    assertTrue(str_contains((string) $detail['page_url'], '/video/'), 'رابط صفحة التفاصيل غير صالح.');
    assertTrue(array_key_exists('provider', $detail), 'حقل provider غير موجود.');
    assertTrue(array_key_exists('media_url', $detail), 'حقل media_url غير موجود.');
});

$failed = count(array_filter($tests, static fn (array $test): bool => !$test['passed']));
fwrite(STDOUT, sprintf("\n%d tests, %d passed, %d failed.\n", count($tests), count($tests) - $failed, $failed));
exit($failed === 0 ? 0 : 1);
