<?php
/**
 * Global view/controller helpers.
 */
declare(strict_types=1);

use Qamhad\Core\Lang;
use Qamhad\Core\Settings;

/* ============ Output safety ============ */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function t(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}

/* ============ URLs (clean, language aware) ============ */

/**
 * Percent-encode a DECODED path for use in HTTP contexts that require
 * ASCII (Location headers, sitemap <loc>, canonical). Keeps "/" and
 * common safe chars, never double-encodes because input is always the
 * decoded logical path produced by our URL builders.
 */
function encode_path(string $path): string
{
    [$p, $q] = array_pad(explode('?', $path, 2), 2, null);
    $segments = array_map('rawurlencode', explode('/', $p));
    $encoded = implode('/', $segments);
    return $encoded . ($q !== null ? '?' . $q : '');
}

/** Absolute, ASCII-safe URL (for Location / sitemaps / schema). */
function absolute_url(string $pathOrUrl): string
{
    if (preg_match('#^https?://#i', $pathOrUrl)) {
        $parts = parse_url($pathOrUrl);
        $path = $parts['path'] ?? '/';
        // Re-encode only if the path contains raw non-ASCII bytes
        if (preg_match('/[^\x20-\x7E]/', $path)) $path = encode_path($path);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
    return SITE_URL . encode_path('/' . ltrim($pathOrUrl, '/'));
}

/** Absolute URL for a path in the CURRENT language. url('match/123') */
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/') return SITE_URL . (Lang::prefix() ?: '/');
    return SITE_URL . Lang::prefix() . $path;
}

/** Relative path (used in nav so it works behind any host). */
function path(string $p = ''): string
{
    $p = '/' . ltrim($p, '/');
    if ($p === '/') return Lang::prefix() ?: '/';
    return Lang::prefix() . $p;
}

/* ============ Slugs ============ */

/**
 * URL-safe slug. Keeps Arabic letters (Arabic URLs are valid & SEO friendly),
 * transliterates Latin accents, strips everything else.
 */
function slugify(?string $text, string $fallback = ''): string
{
    $text = trim((string)$text);
    if ($text === '') return $fallback;
    // Normalize whitespace/dashes
    $text = preg_replace('/[\s_]+/u', '-', $text);
    // Transliterate Latin accents when possible
    if (function_exists('transliterator_transliterate') && !preg_match('/\p{Arabic}/u', $text)) {
        $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if (is_string($t) && $t !== '') $text = $t;
    }
    $text = mb_strtolower($text, 'UTF-8');
    // Keep Arabic letters, latin letters, digits and dashes
    $text = preg_replace('/[^\p{Arabic}a-z0-9\-]+/u', '', $text);
    $text = trim((string)preg_replace('/-+/', '-', (string)$text), '-');
    if ($text === '') return $fallback;
    return mb_substr($text, 0, 90);
}

/** slug-id builders => /match/spain-vs-austria-12345 */
function match_url(array $m): string
{
    $id   = (int)($m['match_id'] ?? $m['id'] ?? 0);
    $home = team_of($m, 'home')['title'] ?? '';
    $away = team_of($m, 'away')['title'] ?? '';
    $glue = Lang::current() === 'ar' ? '-' : '-vs-';
    $slug = slugify($home . $glue . $away);
    return path('match/' . ($slug !== '' ? $slug . '-' : '') . $id);
}

function league_url($league, ?string $title = null): string
{
    if (is_array($league)) {
        $id = (int)($league['url_id'] ?? $league['id'] ?? 0);
        $title = $title ?? (string)($league['title'] ?? '');
    } else {
        $id = (int)$league;
    }
    $slug = slugify((string)$title);
    return path('league/' . ($slug !== '' ? $slug . '-' : '') . $id);
}

function team_url($team, ?string $title = null): string
{
    if (is_array($team)) {
        $id = (int)($team['row_id'] ?? $team['team_id'] ?? $team['id'] ?? 0);
        $title = $title ?? (string)($team['title'] ?? $team['full_title'] ?? '');
    } else {
        $id = (int)$team;
    }
    $slug = slugify((string)$title);
    return path('team/' . ($slug !== '' ? $slug . '-' : '') . $id);
}

function player_url($player, ?string $title = null): string
{
    if (is_array($player)) {
        $id = (int)($player['row_id'] ?? $player['player_id'] ?? $player['id'] ?? 0);
        $title = $title ?? (string)($player['title'] ?? $player['full_title'] ?? '');
    } else {
        $id = (int)$player;
    }
    $slug = slugify((string)$title);
    return path('player/' . ($slug !== '' ? $slug . '-' : '') . $id);
}

function news_url(array $n): string
{
    $id   = (int)($n['id'] ?? 0);
    $slug = slugify((string)($n['slug'] ?? $n['title'] ?? ''));
    return path('news/' . ($slug !== '' ? $slug . '-' : '') . $id);
}

/** Extract trailing numeric id from "some-slug-123". */
function id_from_slug(string $slug): int
{
    if (preg_match('/(\d+)$/', $slug, $m)) return (int)$m[1];
    return 0;
}

/* ============ Media proxy URLs (first-party) ============ */

/**
 * Build a first-party /media URL. Never leaks the upstream CDN host.
 */
function media_url(string $kind, string $size, ?string $file, string $fallback): string
{
    if (empty($file)) return $fallback;
    // Upstream "default.*" placeholders carry the provider's own logo —
    // never show it; use our first-party fallback instead.
    if (preg_match('#(^|/)default\.(png|jpe?g|gif|webp)$#i', (string)$file)) return $fallback;
    if (preg_match('#^https?://#i', (string)$file)) {
        // Absolute upstream URL — rewrite through proxy if it's the known CDN
        $p = parse_url((string)$file, PHP_URL_PATH);
        if ($p && str_starts_with((string)parse_url((string)$file, PHP_URL_HOST), 'imgs.')) {
            // Honor the REQUESTED size when the CDN path is kind/size/file —
            // otherwise a detail payload carrying e.g. …/news/640/x.jpg would
            // silently pin the image to 640 no matter what the caller asked.
            if (preg_match('#^/([a-z]+)/\d{2,4}/([A-Za-z0-9._\-]+)$#i', $p, $mm)
                && isset(MEDIA_KINDS[$mm[1]])
                && in_array($size, MEDIA_KINDS[$mm[1]], true)) {
                return "/media/{$mm[1]}/{$size}/{$mm[2]}";
            }
            return '/media' . $p;
        }
        return (string)$file; // unknown external image, leave as-is
    }
    $file = ltrim((string)$file, '/');
    if (!preg_match('/^[A-Za-z0-9._\-]+$/', $file)) return $fallback;
    return "/media/{$kind}/{$size}/{$file}";
}

function team_img($teamOrFile, string $size = '64'): string
{
    $file = is_array($teamOrFile) ? ($teamOrFile['image'] ?? $teamOrFile['logo'] ?? null) : $teamOrFile;
    return media_url('teams', $size, $file, '/assets/brand/icon.svg');
}

function league_img($lgOrFile, string $size = '128'): string
{
    $file = is_array($lgOrFile) ? ($lgOrFile['image'] ?? null) : $lgOrFile;
    return media_url('championship', $size, $file, '/assets/brand/icon.svg');
}

function news_img($nOrFile, string $size = '640'): string
{
    $file = is_array($nOrFile) ? ($nOrFile['image'] ?? null) : $nOrFile;
    return media_url('news', $size, $file, '/assets/img/news.svg');
}

function player_img($pOrFile, string $size = '64'): string
{
    // Upstream only renders player photos at /64 reliably (100 is often
    // missing); the proxy will fall back to /64 anyway, but request it directly.
    $file = is_array($pOrFile) ? ($pOrFile['image'] ?? null) : $pOrFile;
    return media_url('player', $size, $file, '/assets/img/player.svg');
}

/** Country flag through the media proxy; '' when the upstream file is unknown. */
function flag_img($fileOrPlayer, string $size = '64'): string
{
    $file = is_array($fileOrPlayer)
        ? ($fileOrPlayer['country_image'] ?? $fileOrPlayer['country_flag'] ?? $fileOrPlayer['flag'] ?? null)
        : $fileOrPlayer;
    if (empty($file)) return '';
    return media_url('country', $size, $file, '');
}

/* ============ Match helpers ============ */

/** Normalize home/away team across API shapes (home_team_info vs home_team). */
function team_of(array $m, string $side): array
{
    $t = $m[$side . '_team_info'] ?? $m[$side . '_team'] ?? [];
    return is_array($t) ? $t : [];
}

function team_name($team, string $default = '—'): string
{
    if (is_string($team)) return $team !== '' ? $team : $default;
    if (!is_array($team)) return $default;
    foreach (['title', 'full_title', 'name', 'short_title', 'team_name'] as $k) {
        if (!empty($team[$k]) && is_string($team[$k])) return $team[$k];
    }
    return $default;
}

/**
 * Live match clock.
 *
 * The upstream API does NOT send a live minute. `ht_time` carries the unix
 * timestamp at which the CURRENT PERIOD kicked off (1st half, 2nd half, ET),
 * and `minutes`, when present, was computed upstream at fetch time (it goes
 * stale in cache — values like 4233 — and raw ht_time is what produced the
 * "1783018817'" bug). The clock is therefore always derived from ht_time at
 * render time, then ticked client-side.
 *
 * Phases by status: 1=1st half · 2=HT · 3=2nd half · 5=ET1 · 6=ET2 ·
 * 7/8/13=penalty shootout.
 */
function live_clock(array $m): array
{
    $status = (int)($m['status'] ?? 0);

    if ($status === 2) {
        return ['label' => t('status.halftime'), 'minute' => 45, 'progress' => 0.5,
                'phase' => 'ht', 'start' => 0, 'base' => 45, 'cap' => 45];
    }
    if (in_array($status, [7, 8, 13], true)) {
        return ['label' => t('match.penalties'), 'minute' => 120, 'progress' => 1,
                'phase' => 'pens', 'start' => 0, 'base' => 120, 'cap' => 120];
    }

    [$base, $cap] = match ($status) {
        1 => [0, 45],
        3 => [45, 90],
        5 => [90, 105],
        6 => [105, 120],
        default => [0, 45],
    };

    $raw     = $m['ht_time'] ?? null;
    $minutes = $m['minutes'] ?? null;
    $start   = (is_numeric($raw) && (int)$raw > 1_000_000_000) ? (int)$raw : 0;

    if ($start > 0) {
        $minute = $base + intdiv(max(0, time() - $start), 60) + 1;
    } elseif (is_numeric($minutes) && (int)$minutes > 0 && (int)$minutes <= 130) {
        $minute = (int)$minutes;
    } elseif (is_numeric($raw) && (int)$raw > 0 && (int)$raw <= 130) {
        $minute = (int)$raw;
    } elseif (!in_array($status, [1, 3, 5, 6], true)) {
        // live flag without a period status or usable clock — plain LIVE badge
        return ['label' => t('status.live'), 'minute' => 0, 'progress' => 0.05,
                'phase' => 'live', 'start' => 0, 'base' => 0, 'cap' => 45];
    } else {
        $minute = $base + 1;
    }

    $label = $minute > $cap
        ? $cap . '+' . ($minute - $cap) . '′'
        : $minute . '′';

    return [
        'label'    => $label,
        'minute'   => $minute,
        'progress' => min($minute / 90, 1),
        'phase'    => $status === 1 ? '1h' : ($status === 3 ? '2h' : 'et'),
        'start'    => $start,
        'base'     => $base,
        'cap'      => $cap,
    ];
}

/**
 * Normalize a period score pair into [home, away].
 *
 * The API reports each period's score as a two-element list, e.g.
 *   [{"home_team":10204,"home_scores":2},{"away_team":8586,"away_scores":1}]
 * but also (for penalties/aggregate) sometimes as a flat object
 *   {"home_scores":4,"away_scores":3} or {"home":4,"away":3}.
 * Returns null when the pair is absent or unusable.
 */
function score_pair($v): ?array
{
    if (!is_array($v)) return null;
    $h = null; $a = null;
    // Flat object form
    foreach (['home_scores' => 'away_scores', 'home' => 'away'] as $hk => $ak) {
        if (array_key_exists($hk, $v) || array_key_exists($ak, $v)) {
            $h = isset($v[$hk]) ? (int)$v[$hk] : $h;
            $a = isset($v[$ak]) ? (int)$v[$ak] : $a;
        }
    }
    // List-of-entries form
    foreach ($v as $entry) {
        if (!is_array($entry)) continue;
        if (array_key_exists('home_scores', $entry)) $h = (int)$entry['home_scores'];
        if (array_key_exists('away_scores', $entry)) $a = (int)$entry['away_scores'];
        if (array_key_exists('home', $entry) && !array_key_exists('home_scores', $entry)) $h = (int)$entry['home'];
        if (array_key_exists('away', $entry) && !array_key_exists('away_scores', $entry)) $a = (int)$entry['away'];
    }
    if ($h === null || $a === null) return null;
    return [$h, $a];
}

/**
 * Period-by-period score breakdown for a match.
 *
 * These fields ride on the matches_event payload (NOT match_info):
 *   fh_scores → first half · sh_scores → second half ·
 *   fe_scores → 1st extra-time half · se_scores → 2nd extra-time half ·
 *   match_penalties (or penalties) → penalty shootout.
 * Returns only the periods that are actually present, plus convenience
 * flags `has_et` and `has_pens` so views can render accurately.
 */
function match_periods(array $src): array
{
    $out = [
        'fh'   => score_pair($src['fh_scores'] ?? null),
        'sh'   => score_pair($src['sh_scores'] ?? null),
        'fe'   => score_pair($src['fe_scores'] ?? null),
        'se'   => score_pair($src['se_scores'] ?? null),
        'pens' => score_pair($src['match_penalties'] ?? $src['penalties'] ?? null),
    ];
    // Per-player shootout form: {"<teamId>":[{player_id,player_name,score},…],…}
    // — score_pair can't read it; derive the totals from the attempt lists.
    if ($out['pens'] === null) {
        $so = penalty_shootout($src,
            (int)($src['home_team']['row_id'] ?? 0),
            (int)($src['away_team']['row_id'] ?? 0));
        if ($so !== null) $out['pens'] = $so['score'];
    }
    $out['has_et']   = $out['fe'] !== null || $out['se'] !== null;
    $out['has_pens'] = $out['pens'] !== null;
    return $out;
}

/**
 * Per-player penalty shootout parsed from `match_penalties`.
 *
 * Verified upstream shape (matches_event / match_info payloads):
 *   "match_penalties": { "<teamId>": [ {player_id, player_name:{…}, score:0|1}, … ], … }
 * Attempts are listed in shooting order. Returns null when the field is
 * absent, empty, or in the flat score-pair form (match_periods covers that).
 *
 * @return array{home:array<int,array{player:array,scored:bool}>,
 *               away:array<int,array{player:array,scored:bool}>,
 *               score:array{0:int,1:int}}|null
 */
function penalty_shootout(array $src, int $homeId, int $awayId): ?array
{
    $v = $src['match_penalties'] ?? $src['penalties'] ?? null;
    if (!is_array($v) || $homeId <= 0 || $awayId <= 0) return null;
    $sides = [];
    foreach ([$homeId, $awayId] as $tid) {
        $list = $v[(string)$tid] ?? $v[$tid] ?? null;
        if (!is_array($list)) continue;
        $rows = [];
        foreach ($list as $at) {
            if (!is_array($at) || !array_key_exists('score', $at)) continue;
            $rows[] = [
                'player' => is_array($at['player_name'] ?? null) ? $at['player_name'] : [],
                'scored' => (int)$at['score'] === 1,
            ];
        }
        if ($rows) $sides[$tid] = $rows;
    }
    if (empty($sides[$homeId]) && empty($sides[$awayId])) return null;
    $h = $sides[$homeId] ?? [];
    $a = $sides[$awayId] ?? [];
    return [
        'home'  => $h,
        'away'  => $a,
        'score' => [
            count(array_filter($h, fn($r) => $r['scored'])),
            count(array_filter($a, fn($r) => $r['scored'])),
        ],
    ];
}

/**
 * Match status descriptor.
 * status: 0 not started, 1..3 in play (1=1st half, 2=HT, 3=2nd half), 4 finished.
 */
function match_state(array $m): array
{
    $status = (int)($m['status'] ?? 0);
    $live   = (int)($m['live'] ?? 0);

    if ($status === 4) {
        return ['key' => 'finished', 'label' => t('status.finished'), 'live' => false, 'started' => true, 'clock' => null];
    }
    if ($live === 1 || in_array($status, [1, 2, 3, 5, 6, 7, 8, 13], true)) {
        $clock = live_clock($m);
        return ['key' => 'live', 'label' => $clock['label'], 'live' => true, 'started' => true,
                'clock' => $clock, 'status' => $status];
    }
    return ['key' => 'upcoming', 'label' => format_time_12h($m['match_time'] ?? ''), 'live' => false, 'started' => false, 'clock' => null];
}

function match_has_score(array $m): bool
{
    $s = match_state($m);
    return $s['started'];
}

/** data-* attributes that let the frontend tick a live clock between polls. */
function live_clock_attrs(array $state): string
{
    if (empty($state['live']) || empty($state['clock'])) return '';
    $c = $state['clock'];
    return ' data-ls="' . (int)($state['status'] ?? 0) . '" data-lt="' . (int)$c['start'] . '"';
}

/* ============ Time & date (12-hour everywhere) ============ */

/** "22:00:00" => "10:00 م" | "10:00 PM" */
function format_time_12h(?string $time): string
{
    $time = trim((string)$time);
    if ($time === '') return '';
    $ts = strtotime('2000-01-01 ' . $time);
    if (!$ts) return e($time);
    $h = (int)date('g', $ts);
    $i = date('i', $ts);
    $isPm = date('a', $ts) === 'pm';
    $suffix = $isPm ? t('misc.pm') : t('misc.am');
    return sprintf('%02d:%s %s', $h, $i, $suffix);
}

/** Unix timestamp => localized "10:00 م" (rendered server-side, corrected client-side). */
function format_ts_time(int $ts): string
{
    if ($ts <= 0) return '';
    $h = (int)date('g', $ts);
    $i = date('i', $ts);
    $suffix = date('a', $ts) === 'pm' ? t('misc.pm') : t('misc.am');
    return sprintf('%02d:%s %s', $h, $i, $suffix);
}

/** "2026-07-02" => "الخميس 2 يوليو 2026" | "Thursday, July 2, 2026" */
function format_date_long($value): string
{
    $ts = to_ts($value);
    if (!$ts) return '';
    $wd = t('wd.' . date('w', $ts));
    $mo = t('mo.' . (int)date('n', $ts));
    $d  = (int)date('j', $ts);
    $y  = date('Y', $ts);
    return Lang::current() === 'ar' ? "{$wd} {$d} {$mo} {$y}" : "{$wd}, {$mo} {$d}, {$y}";
}

function format_date_short($value): string
{
    $ts = to_ts($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function format_datetime($value): string
{
    $ts = to_ts($value);
    if (!$ts) return '';
    return format_date_long($ts) . ' — ' . format_ts_time($ts);
}

/**
 * Build all dynamic SEO strings for a match from EXISTING data only
 * (teams, league, time, stadium, channels, commentator, status). No stuffing:
 * every phrase is generated from real fields and adapts to the match state.
 *
 * @return array{title:string,description:string,h1:string,h2:array<int,string>,faq:array<int,array{q:string,a:string}>}
 */
function match_seo(array $m, array $state, array $channels = []): array
{
    $isAr = \Qamhad\Core\Lang::current() === 'ar';

    $home = team_name(team_of($m, 'home'));
    $away = team_name(team_of($m, 'away'));
    $league  = trim((string)($m['championship']['title'] ?? ''));
    $stadium = trim((string)($m['Stadium'] ?? ''));
    $ts = (int)($m['match_timestamp'] ?? 0);
    if (!$ts) $ts = to_ts($m['match_date'] ?? '');
    $date = $ts ? format_date_long($ts) : '';
    $time = $ts ? format_ts_time($ts) : '';
    $hs = (int)($m['home_scores'] ?? 0);
    $as = (int)($m['away_scores'] ?? 0);
    $isToday = $ts && date('Y-m-d', $ts) === date('Y-m-d');

    // Broadcast channels + commentators (deduped by channel name, capped)
    $withCom = [];
    $commentators = [];
    foreach ($channels as $c) {
        if (!is_array($c)) continue;
        $cn = trim((string)($c['channel_name'] ?? ''));
        if ($cn === '' || isset($withCom[$cn])) continue;
        $com = trim((string)($c['commentator_name'] ?? ''));
        $withCom[$cn] = $com !== '' ? ($cn . ' — ' . $com) : $cn;
        if ($com !== '') $commentators[$com] = true;
    }
    $names = array_keys($withCom);
    $chList    = implode('، ', array_slice($names, 0, 5));
    $chListCom = implode('، ', array_slice(array_values($withCom), 0, 5));
    $comList   = implode('، ', array_slice(array_keys($commentators), 0, 4));
    $chListEn  = implode(', ', array_slice($names, 0, 5));

    // Venue label: avoid "ملعب ملعب X" when the API value already carries the word.
    $venueAr = $stadium === '' ? ''
        : (mb_strpos($stadium, 'ملعب') === 0 ? $stadium : 'ملعب ' . $stadium);

    $key = $state['key'] ?? 'upcoming';       // live | finished | upcoming
    $when = $isToday ? ($isAr ? 'اليوم' : 'today') : $date;
    $put = static fn(string $s, string $x): string => $x !== '' ? $s : '';

    if ($isAr) {
        $pair = "{$home} و{$away}";
        $vs   = "{$home} ضد {$away}";

        if ($key === 'live') {
            $title = "مباراة {$pair} بث مباشر";
            $h1    = "مباراة {$pair} بث مباشر" . ($league ? " — {$league}" : '');
            $desc  = "مشاهدة مباراة {$pair} بث مباشر الآن" . ($league ? " في {$league}" : '')
                   . ". {$vs} مباشر" . ($isToday ? " اليوم" : '')
                   . ($chList ? " على {$chList}" : '') . ". تابع النتيجة والأحداث لحظة بلحظة.";
        } elseif ($key === 'finished') {
            $title = "نتيجة مباراة {$pair} {$hs}-{$as}";
            $h1    = "نتيجة مباراة {$pair} {$hs}-{$as}" . ($league ? " — {$league}" : '');
            $desc  = "انتهت مباراة {$pair} بنتيجة {$hs} - {$as}" . ($league ? " في {$league}" : '')
                   . ". ملخص وأهداف وأحداث {$vs}"
                   . ($chList ? " — القنوات الناقلة: {$chList}" : '') . ".";
        } else {
            $title = "موعد مباراة {$pair}" . ($isToday ? " اليوم" : '') . " والقنوات الناقلة";
            $h1    = "موعد مباراة {$pair} والقنوات الناقلة" . ($league ? " — {$league}" : '');
            $desc  = "موعد مباراة {$pair}" . ($when ? " {$when}" : '') . ($time ? " الساعة {$time}" : '')
                   . ($stadium ? " على {$venueAr}" : '') . ($league ? " في {$league}" : '')
                   . ". مشاهدة {$vs} بث مباشر" . ($chList ? " والقنوات الناقلة: {$chList}" : '')
                   . " والتشكيلة المتوقعة.";
        }

        // Section keywords (rendered as hidden H2s — accurate to the page)
        $h2 = ["مشاهدة مباراة {$pair} بث مباشر"];
        if ($chList) $h2[] = "القنوات الناقلة لمباراة {$pair}";
        $h2[] = $state['started'] ? "نتيجة مباراة {$pair}" : "موعد مباراة {$pair}";
        $h2[] = "تشكيلة {$vs}";

        // FAQ (only questions we truly have data for)
        $faq = [];
        if ($date || $time) {
            $a = "تقام مباراة {$pair}" . ($when ? " {$when}" : '') . ($time ? " الساعة {$time}" : '')
               . ($league ? " ضمن {$league}" : '') . ($stadium ? " على {$venueAr}" : '') . ".";
            $faq[] = ['q' => "متى موعد مباراة {$pair}؟", 'a' => $a];
        }
        if ($chList) {
            $faq[] = ['q' => "ما هي القنوات الناقلة لمباراة {$pair}؟",
                      'a' => "تُنقل مباراة {$pair} على " . ($chListCom ?: $chList) . "."];
        }
        if ($comList) {
            $faq[] = ['q' => "من هو معلق مباراة {$pair}؟", 'a' => "يُعلّق على مباراة {$pair}: {$comList}."];
        }
        if ($key === 'finished') {
            $faq[] = ['q' => "ما نتيجة مباراة {$pair}؟", 'a' => "انتهت مباراة {$pair} بنتيجة {$hs} - {$as}."];
        } elseif ($key === 'live') {
            $faq[] = ['q' => "ما نتيجة مباراة {$pair} الآن؟",
                      'a' => "النتيجة الحالية {$hs} - {$as} والمباراة مباشرة الآن."];
        } else {
            $faq[] = ['q' => "ما نتيجة مباراة {$pair}؟",
                      'a' => "لم تبدأ مباراة {$pair} بعد، وتظهر النتيجة فور انطلاقها."];
        }
        if ($stadium) {
            $faq[] = ['q' => "أين تقام مباراة {$pair}؟", 'a' => "تقام مباراة {$pair} على {$venueAr}."];
        }
    } else {
        $pair = "{$home} vs {$away}";
        if ($key === 'live') {
            $title = "{$pair} live stream";
            $h1    = "{$pair} — live stream" . ($league ? " · {$league}" : '');
            $desc  = "Watch {$pair} live now" . ($league ? " in {$league}" : '')
                   . ($chListEn ? " on {$chListEn}" : '') . ". Live score and updates.";
        } elseif ($key === 'finished') {
            $title = "{$pair} result {$hs}-{$as}";
            $h1    = "{$pair} result {$hs}-{$as}" . ($league ? " · {$league}" : '');
            $desc  = "{$pair} ended {$hs}-{$as}" . ($league ? " in {$league}" : '')
                   . ". Highlights, goals and match events.";
        } else {
            $title = "{$pair} kickoff time & TV channels";
            $h1    = "{$pair} — kickoff time & TV channels" . ($league ? " · {$league}" : '');
            $desc  = "{$pair}" . ($when ? " {$when}" : '') . ($time ? " at {$time}" : '')
                   . ($stadium ? " at {$stadium}" : '') . ($league ? " in {$league}" : '')
                   . ". Watch live" . ($chListEn ? ", TV channels: {$chListEn}" : '') . " and lineups.";
        }
        $h2 = ["Watch {$pair} live"];
        if ($chListEn) $h2[] = "TV channels for {$pair}";
        $h2[] = $state['started'] ? "{$pair} result" : "{$pair} kickoff time";
        $h2[] = "{$pair} lineups";

        $faq = [];
        if ($date || $time) {
            $faq[] = ['q' => "When is {$pair}?",
                      'a' => "{$pair} is played" . ($when ? " {$when}" : '') . ($time ? " at {$time}" : '')
                           . ($league ? " in {$league}" : '') . '.'];
        }
        if ($chListEn) $faq[] = ['q' => "Which channels broadcast {$pair}?", 'a' => "{$pair} airs on {$chListEn}."];
        if ($key === 'finished') $faq[] = ['q' => "What was the {$pair} result?", 'a' => "{$pair} ended {$hs}-{$as}."];
    }

    return [
        'title'       => $title,
        'description' => trim(preg_replace('/\s+/u', ' ', $desc)),
        'h1'          => $h1,
        'h2'          => array_values(array_unique(array_filter($h2))),
        'faq'         => $faq,
    ];
}

/**
 * SEO Content Engine — builds a unique, crawlable 300–600 word article for a
 * match page purely from its JSON data (teams, tournament, round, stadium,
 * referee, date/time, broadcasters, commentators, score). Fully server-rendered
 * (no JS), status-adaptive (upcoming / live / finished), Arabic-first.
 *
 * Returns a structured tree the view renders as semantic HTML:
 *   [
 *     'lead'     => string,                         // opening paragraph
 *     'sections' => [ ['h2'=>…, 'paras'=>[…], 'list'=>[…]], … ],
 *     'facts'    => [ ['label'=>…, 'value'=>…], … ], // "Match Facts" box
 *     'words'    => int,
 *   ]
 *
 * @param array $opts optional: 'referee', 'stadium', 'round' (from controller)
 */
function match_article(array $m, array $state, array $channels = [], array $opts = []): array
{
    $isAr = \Qamhad\Core\Lang::current() === 'ar';

    $home    = team_name(team_of($m, 'home'));
    $away    = team_name(team_of($m, 'away'));
    $league  = trim((string)($m['championship']['title'] ?? ''));
    $round   = trim((string)($opts['round']   ?? ($m['round'] ?? '')));
    $stadium = trim((string)($opts['stadium'] ?? ($m['Stadium'] ?? '')));
    $referee = trim((string)($opts['referee'] ?? ''));

    $ts = (int)($m['match_timestamp'] ?? 0);
    if (!$ts) $ts = to_ts($m['match_date'] ?? '');
    $date = $ts ? format_date_long($ts) : '';
    $time = $ts ? format_ts_time($ts) : '';
    $isToday = $ts && date('Y-m-d', $ts) === date('Y-m-d');

    $hs  = (int)($m['home_scores'] ?? 0);
    $as  = (int)($m['away_scores'] ?? 0);
    $key = $state['key'] ?? 'upcoming';

    // Broadcasters + commentators (deduped by channel name).
    $chans = [];
    $coms  = [];
    foreach ($channels as $c) {
        if (!is_array($c)) continue;
        $cn = trim((string)($c['channel_name'] ?? ''));
        if ($cn === '' || isset($chans[$cn])) continue;
        $com = trim((string)($c['commentator_name'] ?? ''));
        $chans[$cn] = $com;
        if ($com !== '') $coms[$com] = true;
    }
    $chanNames = array_keys($chans);
    $comNames  = array_keys($coms);

    if ($isAr) {
        $pair = "{$home} و{$away}";
        $vs   = "{$home} ضد {$away}";
        $venue = $stadium === '' ? '' : (mb_strpos($stadium, 'ملعب') === 0 ? $stadium : "ملعب {$stadium}");
        $whenPhrase = $isToday ? 'اليوم' : ($date ?: '');
        $chanList = $chanNames ? implode('، ', array_slice($chanNames, 0, 6)) : '';
        $comList  = $comNames ? implode('، ', array_slice($comNames, 0, 4)) : '';

        // ---- Lead paragraph (status-aware) ----
        if ($key === 'live') {
            $lead = "تابعوا مباراة {$pair} بث مباشر الآن"
                . ($league ? " ضمن منافسات {$league}" : '')
                . ($round ? " في {$round}" : '') . '. '
                . "المباراة تُقام" . ($venue ? " على {$venue}" : '') . " ويمكنكم متابعة أحداثها ونتيجتها لحظة بلحظة"
                . ($chanList ? " عبر القنوات الناقلة: {$chanList}" : '') . '.';
        } elseif ($key === 'finished') {
            $lead = "انتهت مباراة {$pair}"
                . ($league ? " في {$league}" : '')
                . ($round ? " ({$round})" : '')
                . " بنتيجة {$hs} - {$as}. "
                . "فيما يلي ملخص وأحداث ونتيجة اللقاء الذي أُقيم"
                . ($venue ? " على {$venue}" : '')
                . ($date ? " بتاريخ {$date}" : '') . '.';
        } else {
            $lead = "تنتظر الجماهير العربية مباراة {$pair}"
                . ($league ? " ضمن {$league}" : '')
                . ($round ? " في {$round}" : '') . "، "
                . "حيث يلتقي الفريقان" . ($whenPhrase ? " {$whenPhrase}" : '')
                . ($time ? " في تمام الساعة {$time}" : '')
                . ($venue ? " على {$venue}" : '') . '. '
                . "في هذا التقرير نستعرض موعد المباراة والقنوات الناقلة وكل التفاصيل.";
        }

        $sections = [];

        // 1) بث مباشر اليوم
        $p = [];
        $p[] = ($key === 'finished')
            ? "أُقيمت مباراة {$pair}" . ($league ? " ضمن {$league}" : '') . " وشهدت متابعة واسعة من عشّاق كرة القدم، وانتهت بنتيجة {$hs} - {$as}."
            : "مباراة {$pair} من أبرز مباريات اليوم" . ($league ? " في {$league}" : '') . "، ويترقّبها جمهور الفريقين لمتابعتها بث مباشر بجودة عالية وبدون تقطيع.";
        if ($key === 'live') {
            $p[] = "المباراة مباشرة الآن، والنتيجة الحالية {$hs} - {$as}، ويمكنكم متابعة الشوط الأول والشوط الثاني وكل الأحداث أولاً بأول من خلال المشغّل أعلى الصفحة.";
        }
        $p[] = "وتوفّر صفحة مباراة {$pair} على قمهد لايف التشكيلة والإحصائيات وأحداث اللقاء وترتيب الفريقين في البطولة، إضافةً إلى البث المباشر بجودة عالية.";
        $sections[] = ['h2' => "مباراة {$pair} بث مباشر اليوم", 'paras' => $p];

        // Status-specific focus section (directly serves the target intents).
        if ($key === 'upcoming') {
            $sp = [
                "تكتسب مباراة {$pair} أهمية كبيرة" . ($round ? " في {$round}" : '') . ($league ? " من {$league}" : '')
                    . "، إذ يسعى كل فريق لتحقيق نتيجة إيجابية تعزّز موقفه في البطولة.",
                "وتترقّب الجماهير التشكيلة المتوقعة للفريقين قبل انطلاق اللقاء، والتي تُنشر عادةً قبل صافرة البداية بنحو ساعة، ويمكن متابعتها من قسم التشكيلة أعلى الصفحة فور توفّرها.",
            ];
            $sections[] = ['h2' => "التشكيلة المتوقعة وأهمية مباراة {$pair}", 'paras' => $sp];
        } elseif ($key === 'live') {
            $sp = [
                "المباراة جارية الآن ويمكنكم متابعة أحداث اللقاء أولاً بأول: الأهداف والبطاقات وأبرز اللقطات في الشوطين الأول والثاني، مع تحديث النتيجة {$hs} - {$as} لحظة بلحظة دون الحاجة لإعادة تحميل الصفحة.",
            ];
            $sections[] = ['h2' => "متابعة أحداث مباراة {$pair} لحظة بلحظة", 'paras' => $sp];
        } else {
            $sp = [
                "انتهت مباراة {$pair} بنتيجة {$hs} - {$as}، ويمكنكم مراجعة ملخص المباراة والأهداف وأبرز الأحداث من قسم الأحداث أعلى الصفحة، إضافةً إلى إحصائيات اللقاء الكاملة بين الفريقين.",
            ];
            $sections[] = ['h2' => "نتيجة وملخص وأهداف مباراة {$pair}", 'paras' => $sp];
        }

        // 2) موعد المباراة
        $mp = [];
        $mp[] = "يبحث كثيرون عن موعد مباراة {$vs}"
            . ($whenPhrase ? "؛ تُقام المباراة {$whenPhrase}" : '')
            . ($time ? " في تمام الساعة {$time}" : '')
            . ($venue ? " على {$venue}" : '')
            . ($league ? " ضمن منافسات {$league}" : '')
            . ($round ? " في {$round}" : '') . '.';
        if ($referee !== '') $mp[] = "يدير اللقاء الحكم {$referee}.";
        $sections[] = ['h2' => "موعد مباراة {$vs}", 'paras' => $mp];

        // 3) القنوات الناقلة
        if ($chanList) {
            $cp = ["تُنقل مباراة {$pair} عبر عدد من القنوات الرياضية الناقلة، أبرزها: {$chanList}."];
            if ($comList) $cp[] = "ويعلّق على المباراة نخبة من المعلّقين: {$comList}.";
            $clist = [];
            foreach (array_slice($chanNames, 0, 6) as $cn) {
                $clist[] = $chans[$cn] !== '' ? "{$cn} — تعليق: {$chans[$cn]}" : $cn;
            }
            $sections[] = ['h2' => "القنوات الناقلة لمباراة {$pair}", 'paras' => $cp, 'list' => $clist];
        }

        // 4) معلومات المباراة (نصّية، بالإضافة لصندوق الحقائق)
        $info = [];
        if ($league)  $info[] = "البطولة: {$league}.";
        if ($round)   $info[] = "الجولة: {$round}.";
        if ($venue)   $info[] = "الملعب: {$venue}.";
        if ($referee) $info[] = "الحكم: {$referee}.";
        if ($date)    $info[] = "التاريخ: {$date}" . ($time ? " — الساعة {$time}" : '') . '.';
        if ($info) {
            $sections[] = [
                'h2' => "معلومات مباراة {$pair}",
                'paras' => ["إليكم أبرز المعلومات الرسمية عن مباراة {$vs}:"],
                'list' => $info,
            ];
        }

        // 5) مقارنة بين الفريقين
        if ($key === 'finished') {
            $winner = $hs > $as ? $home : ($as > $hs ? $away : '');
            $cmp = $winner !== ''
                ? "حسم {$winner} مواجهة {$pair} لصالحه بنتيجة {$hs} - {$as} في لقاء" . ($round ? " ضمن {$round}" : '') . " لا يُنسى."
                : "تعادل الفريقان في مباراة {$pair} بنتيجة {$hs} - {$as} في مواجهة متكافئة بين الطرفين.";
        } else {
            $cmp = "يسعى كلا الفريقين، {$home} و{$away}، إلى تحقيق الفوز في هذه المواجهة"
                . ($round ? " ضمن {$round}" : '')
                . "، ما يمنح اللقاء طابعاً خاصاً وأهمية كبيرة لكلا الطرفين وجماهيرهما.";
        }
        $sections[] = ['h2' => "مقارنة بين الفريقين", 'paras' => [$cmp]];

        // 6) كيف تشاهد
        $howList = [];
        $howList[] = "افتح صفحة مباراة {$pair} على قمهد لايف.";
        if ($key === 'live') $howList[] = "اضغط زر «شاهد المباراة الآن» أعلى الصفحة لتشغيل البث المباشر.";
        else $howList[] = "عند انطلاق المباراة سيظهر مشغّل البث المباشر أعلى الصفحة.";
        if ($chanList) $howList[] = "أو تابع المباراة عبر القنوات الناقلة: {$chanList}.";
        $howList[] = "اختر جودة العرض المناسبة لسرعة الإنترنت لديك لمشاهدة بدون تقطيع.";
        $sections[] = [
            'h2' => "كيف تشاهد مباراة {$pair} بث مباشر",
            'paras' => ["يمكنك مشاهدة مباراة {$vs} بث مباشر بعدّة طرق:"],
            'list' => $howList,
        ];

        // ---- Match Facts box ----
        $facts = [];
        if ($league)  $facts[] = ['label' => 'البطولة', 'value' => $league];
        if ($round)   $facts[] = ['label' => 'الجولة',  'value' => $round];
        if ($venue)   $facts[] = ['label' => 'الملعب',  'value' => $venue];
        if ($referee) $facts[] = ['label' => 'الحكم',   'value' => $referee];
        if ($date)    $facts[] = ['label' => 'التاريخ', 'value' => $date];
        if ($time)    $facts[] = ['label' => 'الوقت',   'value' => $time];
    } else {
        // ---- English variant (site is Arabic-first; kept concise) ----
        $pair = "{$home} vs {$away}";
        $venue = $stadium;
        $whenPhrase = $isToday ? 'today' : ($date ?: '');
        $chanList = $chanNames ? implode(', ', array_slice($chanNames, 0, 6)) : '';

        if ($key === 'live') {
            $lead = "Watch {$pair} live now" . ($league ? " in {$league}" : '')
                . ". Follow the score and events minute by minute"
                . ($chanList ? " — broadcast on {$chanList}" : '') . '.';
        } elseif ($key === 'finished') {
            $lead = "{$pair} ended {$hs}-{$as}" . ($league ? " in {$league}" : '')
                . ". Full result, goals and match events below.";
        } else {
            $lead = "{$pair}" . ($whenPhrase ? " takes place {$whenPhrase}" : '')
                . ($time ? " at {$time}" : '') . ($venue ? " at {$venue}" : '')
                . ($league ? " in {$league}" : '') . ". Kickoff time, TV channels and details below.";
        }

        $sections = [];
        $sections[] = ['h2' => "{$pair} live stream today",
            'paras' => ["{$pair} is one of today's key fixtures" . ($league ? " in {$league}" : '') . ", available to watch live in HD."]];
        $mp = ["{$pair} kicks off" . ($whenPhrase ? " {$whenPhrase}" : '') . ($time ? " at {$time}" : '') . ($venue ? " at {$venue}" : '') . '.'];
        if ($referee !== '') $mp[] = "The match is refereed by {$referee}.";
        $sections[] = ['h2' => "{$pair} kickoff time", 'paras' => $mp];
        if ($chanList) $sections[] = ['h2' => "TV channels for {$pair}", 'paras' => ["{$pair} is broadcast on: {$chanList}."]];
        $cmp = $key === 'finished'
            ? ($hs > $as ? "{$home} won {$hs}-{$as}." : ($as > $hs ? "{$away} won {$as}-{$hs}." : "The match ended {$hs}-{$as}."))
            : "Both {$home} and {$away} aim to win this important fixture.";
        $sections[] = ['h2' => "Head to head", 'paras' => [$cmp]];

        $facts = [];
        if ($league)  $facts[] = ['label' => 'Competition', 'value' => $league];
        if ($round)   $facts[] = ['label' => 'Round',       'value' => $round];
        if ($venue)   $facts[] = ['label' => 'Stadium',     'value' => $venue];
        if ($referee) $facts[] = ['label' => 'Referee',     'value' => $referee];
        if ($date)    $facts[] = ['label' => 'Date',        'value' => $date];
        if ($time)    $facts[] = ['label' => 'Time',        'value' => $time];
    }

    // Word count across lead + all section text.
    $blob = $lead;
    foreach ($sections as $s) {
        $blob .= ' ' . $s['h2'] . ' ' . implode(' ', $s['paras'] ?? []);
        if (!empty($s['list'])) $blob .= ' ' . implode(' ', $s['list']);
    }
    $words = count(preg_split('/\s+/u', trim($blob)) ?: []);

    return ['lead' => $lead, 'sections' => $sections, 'facts' => $facts, 'words' => $words];
}

/** Accepts string date, unix ts, or API {date: "..."} arrays. */
function to_ts($value): int
{
    if (empty($value)) return 0;
    if (is_int($value)) return $value;
    if (is_array($value)) $value = $value['date'] ?? '';
    $ts = strtotime((string)$value);
    return $ts ?: 0;
}

/** Relative "منذ 3 ساعات" / "3h ago" for news. */
function time_ago($value): string
{
    $ts = to_ts($value);
    if (!$ts) return '';
    $diff = max(0, time() - $ts);
    $ar = Lang::current() === 'ar';
    if ($diff < 60)    return $ar ? 'الآن' : 'now';
    if ($diff < 3600)  { $n = intdiv($diff, 60);    return $ar ? "منذ {$n} دقيقة" : "{$n}m ago"; }
    if ($diff < 86400) { $n = intdiv($diff, 3600);  return $ar ? "منذ {$n} ساعة" : "{$n}h ago"; }
    if ($diff < 2592000) { $n = intdiv($diff, 86400); return $ar ? "منذ {$n} يوم" : "{$n}d ago"; }
    return format_date_long($ts);
}

/* ============ Events ============ */

/**
 * Map upstream numeric event types to renderable descriptors.
 * Derived empirically from live API payloads.
 */
function event_type(array $ev): array
{
    $type = (int)($ev['type'] ?? 0);
    $map = [
        1   => ['key' => 'goal',          'label' => t('event.goal'),          'icon' => 'goal'],
        2   => ['key' => 'yellow',        'label' => t('event.yellow'),        'icon' => 'yellow'],
        3   => ['key' => 'owngoal',       'label' => t('event.owngoal'),       'icon' => 'owngoal'],
        4   => ['key' => 'penalty',       'label' => t('event.penalty'),       'icon' => 'goal'],
        5   => ['key' => 'second_yellow', 'label' => t('event.second_yellow'), 'icon' => 'red'],
        6   => ['key' => 'red',           'label' => t('event.red'),           'icon' => 'red'],
        7   => ['key' => 'red',           'label' => t('event.red'),           'icon' => 'red'],
        8   => ['key' => 'sub',           'label' => t('event.sub'),           'icon' => 'sub'],
        21  => ['key' => 'missed_pen',    'label' => t('event.missed_pen'),    'icon' => 'missed'],
        22  => ['key' => 'cancelled',     'label' => t('event.cancelled'),     'icon' => 'cancelled'],
        100 => ['key' => 'period',        'label' => '',                       'icon' => 'whistle'],
    ];
    return $map[$type] ?? ['key' => 'other', 'label' => '', 'icon' => 'dot'];
}

/**
 * Label for type-100 period markers based on their status code.
 * Chronology verified against a full ET+pens match payload:
 *   1 kickoff · 2 half-time · 3 second-half start · 5/6 end of regulation ·
 *   7 ET1 start · 8 ET1 end · 9 ET2 start · 10 end of extra time ·
 *   11/13 penalty shootout · 4 match end.
 */
function period_label(array $ev): string
{
    $status = (int)($ev['status'] ?? 0);
    return match ($status) {
        1       => t('match.kickoff'),
        2       => t('match.halftime'),
        3       => t('match.secondhalf'),
        4       => t('match.fulltime'),
        5, 6    => t('match.end_regulation'),
        7       => t('match.et1_start'),
        8       => t('match.et1_end'),
        9       => t('match.et2_start'),
        10      => t('match.et_end'),
        11, 13  => t('match.pens_start'),
        default => t('match.halftime'),
    };
}

function player_label($p, string $default = ''): string
{
    if (is_array($p)) {
        foreach (['title', 'short_title', 'full_title'] as $k) {
            if (!empty($p[$k])) return (string)$p[$k];
        }
        return $default;
    }
    return is_string($p) && $p !== '' ? $p : $default;
}

/* ============ Misc ============ */

function excerpt(?string $text, int $len = 140): string
{
    $text = trim(strip_tags((string)$text));
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '…';
}

function query_int(string $key, int $default = 0): int
{
    return (int)($_GET[$key] ?? $default);
}

/**
 * True when the ORIGINAL client request used HTTPS. Proxy-aware: behind
 * Cloudflare or any TLS-terminating proxy the origin sees plain HTTP with
 * forwarded headers — trusting only $_SERVER['HTTPS'] causes redirect loops.
 */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') return true;
    if (stripos((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https') !== false) return true;
    return false;
}

/**
 * HTTP validation caching: emits truthful Last-Modified + ETag headers and
 * answers If-None-Match / If-Modified-Since with 304 Not Modified.
 *
 * Live sports pages change constantly while finished ones are stable — a
 * strong validator pair lets Googlebot re-crawl hot pages cheaply (304 costs
 * ~no bandwidth) and is a direct crawl-budget win for the whole site.
 *
 * @param int    $lastMod   unix timestamp of the last content change
 * @param string $etagSeed  any string that changes when the content changes
 *                          (e.g. match state + score); hashed into the ETag
 */
function http_cache_validate(int $lastMod, string $etagSeed): void
{
    if ($lastMod <= 0) $lastMod = time();
    $etag = '"' . substr(sha1($etagSeed . '|' . $lastMod . '|' . \Qamhad\Core\Lang::current()), 0, 20) . '"';

    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastMod) . ' GMT');
    header('ETag: ' . $etag);

    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $ifModSince  = (string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
    $notModified = false;
    if ($ifNoneMatch !== '') {
        // Compare ignoring weak prefixes — proxies may add W/.
        $notModified = str_replace('W/', '', $ifNoneMatch) === $etag || $ifNoneMatch === $etag;
    } elseif ($ifModSince !== '') {
        $since = strtotime($ifModSince);
        $notModified = $since !== false && $since >= $lastMod;
    }
    if ($notModified) {
        http_response_code(304);
        exit;
    }
}

/** Group a flat match list by championship, favourites first. */
function group_matches_by_league(array $matches): array
{
    $groups = [];
    foreach ($matches as $m) {
        if (!is_array($m)) continue;
        $c   = $m['championship'] ?? [];
        $uid = (int)($c['url_id'] ?? 0);
        if (!isset($groups[$uid])) {
            $groups[$uid] = [
                'league'   => $c,
                'followed' => (int)($c['followed'] ?? 0),
                'ranking'  => (int)($c['ranking'] ?? 999),
                'matches'  => [],
            ];
        }
        $groups[$uid]['matches'][] = $m;
    }
    $favIds = array_column(FAVORITE_LEAGUES, 'url_id');
    uasort($groups, function ($a, $b) use ($favIds) {
        $fa = in_array((int)($a['league']['url_id'] ?? 0), $favIds, true) ? 0 : 1;
        $fb = in_array((int)($b['league']['url_id'] ?? 0), $favIds, true) ? 0 : 1;
        if ($fa !== $fb) return $fa - $fb;
        if ($a['followed'] !== $b['followed']) return $b['followed'] - $a['followed'];
        return $a['ranking'] <=> $b['ranking'];
    });
    return $groups;
}

/**
 * Cache-busting URL for a public asset. Uses the file's modification time so
 * the query string changes automatically on every deploy — no more stale CSS/
 * JS after an upload (a hardcoded ?v=N never busts if you forget to bump it).
 */
function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = PUBLIC_DIR . $path;
    $ver  = is_file($file) ? (int)filemtime($file) : 1;
    return $path . '?v=' . $ver;
}

/** Site logo URL (custom upload from admin wins, else brand default). */
function site_logo(bool $dark = false): string
{
    $s = Settings::get('branding', []);
    $key = $dark ? 'logo_dark' : 'logo';
    if (!empty($s[$key])) return '/assets/uploads/' . $s[$key];
    return $dark ? '/assets/brand/logo-dark.svg' : '/assets/brand/logo.svg';
}

/**
 * Notification topics — the leagues/teams a visitor can subscribe to from the
 * notification bottom sheet. Each topic has a stable slug (the FCM/topic key,
 * also stored per-token) and a bilingual label. Extend this list to add a new
 * topic; the sheet, the stored subscription and the sender all read from here.
 *
 * @return array<int,array{slug:string,ar:string,en:string}>
 */
function notify_topics(): array
{
    return [
        ['slug' => 'ucl',      'ar' => 'دوري أبطال أوروبا',  'en' => 'UEFA Champions League'],
        ['slug' => 'epl',      'ar' => 'الدوري الإنجليزي',    'en' => 'Premier League'],
        ['slug' => 'laliga',   'ar' => 'الدوري الإسباني',     'en' => 'La Liga'],
        ['slug' => 'spl',      'ar' => 'الدوري السعودي',      'en' => 'Saudi Pro League'],
        ['slug' => 'seriea',   'ar' => 'الدوري الإيطالي',     'en' => 'Serie A'],
        ['slug' => 'ligue1',   'ar' => 'الدوري الفرنسي',      'en' => 'Ligue 1'],
        ['slug' => 'worldcup', 'ar' => 'كأس العالم',          'en' => 'World Cup'],
        ['slug' => 'afccup',   'ar' => 'كأس آسيا',            'en' => 'AFC Asian Cup'],
        ['slug' => 'acl',      'ar' => 'دوري أبطال آسيا',     'en' => 'AFC Champions League'],
        ['slug' => 'caf',      'ar' => 'دوري أبطال أفريقيا',  'en' => 'CAF Champions League'],
        ['slug' => 'egypt',    'ar' => 'الدوري المصري',       'en' => 'Egyptian Premier League'],
        ['slug' => 'morocco',  'ar' => 'الدوري المغربي',      'en' => 'Botola (Morocco)'],
        ['slug' => 'iraq',     'ar' => 'الدوري العراقي',      'en' => 'Iraqi League'],
        ['slug' => 'qatar',    'ar' => 'الدوري القطري',       'en' => 'Qatar Stars League'],
        ['slug' => 'uae',      'ar' => 'الدوري الإماراتي',    'en' => 'UAE Pro League'],
        ['slug' => 'turkey',   'ar' => 'الدوري التركي',       'en' => 'Süper Lig (Turkey)'],
        ['slug' => 'nt_ksa',   'ar' => 'منتخب السعودية',      'en' => 'Saudi Arabia NT'],
        ['slug' => 'nt_mar',   'ar' => 'منتخب المغرب',        'en' => 'Morocco NT'],
        ['slug' => 'nt_egy',   'ar' => 'منتخب مصر',           'en' => 'Egypt NT'],
        ['slug' => 'nt_alg',   'ar' => 'منتخب الجزائر',       'en' => 'Algeria NT'],
    ];
}
