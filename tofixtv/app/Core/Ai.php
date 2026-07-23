<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * ALOKA Live AI Assistant v2 — AI UI Generator (site-data-first).
 *
 * Architecture:
 *   1. SITE KNOWLEDGE SYSTEM — the assistant always knows the site name,
 *      every section URL, the contact email, Telegram, and app links,
 *      extracted from the site's own config/lang files (never invented).
 *   2. CONTEXT ENGINE — the page the visitor is currently on (title,
 *      description, path) is sent with each question and enriched
 *      server-side with the REAL entity payload behind that page
 *      (match / team / league / movie / series / news).
 *   3. PROMPT BUILDER — Site Information / Current Page / Data / User
 *      Question sections, assembled automatically per request.
 *   4. ANSWERS ARE ALWAYS HTML — data intents (matches, cinema, news,
 *      contact, telegram, email, about…) render professional HTML cards
 *      LOCALLY from real API payloads (instant + hallucination-free);
 *      general questions go to the LLM (Gemini native API by default)
 *      which must reply {"type":"html","html":"…"}; every byte of model
 *      HTML passes an allowlist sanitizer before reaching the browser.
 *   5. FALLBACK SYSTEM — whenever the model fails or returns unusable
 *      output, a local HTML template is generated instead. The visitor
 *      never sees markdown, raw text errors or an empty reply.
 *
 * Admin control (Settings group "ai"): enabled flag, provider selection
 * (gemini | openai-compatible) and credentials/model overrides.
 */
final class Ai
{
    /* Provider defaults — overridable at runtime from Admin → المساعد الذكي. */
    private const DEFAULT_PROVIDER        = 'gemini';
    private const DEFAULT_GEMINI_BASE     = 'https://generativelanguage.googleapis.com/v1beta';
    private const DEFAULT_GEMINI_KEY      = 'AIzaSyCwivDrU7gkTIpc6LJFAgBgVWTIWxjlBEo';
    private const DEFAULT_GEMINI_MODEL    = 'models/gemini-3.1-flash-lite';
    private const DEFAULT_OPENAI_BASE     = 'https://api.bluesminds.com/v1';
    private const DEFAULT_OPENAI_KEY      = 'sk-hs43RAsxJtjtUWranWrnvf3iFV4EdtHWLIimGV4HPO7xsJ9N';
    private const DEFAULT_OPENAI_MODEL    = 'gpt-5.2-chat';

    private const MAX_MSG_LEN  = 600;
    private const MAX_HISTORY  = 8;
    private const RATE_LIMIT   = 14;    // requests per RATE_WINDOW per IP
    private const RATE_WINDOW  = 60;    // seconds
    private const TELEGRAM_URL = 'https://t.me/alokalive';
    private const APK_URL      = 'https://t.me/alokalive';

    /** Last provider-call failure detail (for the admin connection test). */
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function config(): array
    {
        $s = Settings::get('ai', []);
        if (!is_array($s)) $s = [];
        $provider = in_array($s['provider'] ?? '', ['gemini', 'openai'], true)
            ? $s['provider'] : self::DEFAULT_PROVIDER;
        return [
            'enabled'      => !array_key_exists('enabled', $s) || !empty($s['enabled']),
            'provider'     => $provider,
            'gemini_base'  => rtrim((string)($s['gemini_base'] ?? '') ?: self::DEFAULT_GEMINI_BASE, '/'),
            'gemini_key'   => (string)($s['gemini_key'] ?? '') ?: self::DEFAULT_GEMINI_KEY,
            'gemini_model' => (string)($s['gemini_model'] ?? '') ?: self::DEFAULT_GEMINI_MODEL,
            'base_url'     => rtrim((string)($s['base_url'] ?? '') ?: self::DEFAULT_OPENAI_BASE, '/'),
            'api_key'      => (string)($s['api_key'] ?? '') ?: self::DEFAULT_OPENAI_KEY,
            'model'        => (string)($s['model'] ?? '') ?: self::DEFAULT_OPENAI_MODEL,
        ];
    }

    /** Widget visibility (admin can stop/hide the assistant site-wide). */
    public static function enabled(): bool
    {
        return self::config()['enabled'];
    }

    /* ==================== Rate limiting (per IP, file-based) ==================== */

    public static function rateLimited(): bool
    {
        $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'ai-rate|' . md5($ip);
        $row = Cache::get($key, self::RATE_WINDOW);
        $n   = is_array($row) ? (int)($row['n'] ?? 0) : 0;
        if ($n >= self::RATE_LIMIT) return true;
        if ($n === 0) Cache::set($key, ['n' => 1]);
        else {
            $row['n'] = $n + 1;
            @file_put_contents(Cache::path($key), json_encode($row));
        }
        return false;
    }

    /* ==================== SITE KNOWLEDGE SYSTEM ==================== */

    /** Everything the assistant is allowed to state about the site itself. */
    public static function siteKnowledge(): array
    {
        $ar = Lang::current() === 'ar';
        return [
            'name'     => Lang::siteName(),
            'slogan'   => Lang::siteSlogan(),
            'email'    => SITE_EMAIL,
            'telegram' => self::TELEGRAM_URL,
            'app_apk'  => self::APK_URL,
            'pages'    => [
                ($ar ? 'الرئيسية' : 'Home')                 => path('/'),
                ($ar ? 'مباريات اليوم' : "Today's matches") => path('matches'),
                ($ar ? 'المباريات المباشرة' : 'Live')       => path('live'),
                ($ar ? 'الأخبار' : 'News')                  => path('news'),
                ($ar ? 'الترتيب' : 'Standings')             => path('standings'),
                ($ar ? 'الهدافون' : 'Top scorers')          => path('top-scorers'),
                ($ar ? 'البطولات' : 'Championships')        => path('leagues'),
                ($ar ? 'الفيديوهات' : 'Videos')             => path('videos'),
                ($ar ? 'الأفلام' : 'Movies')                => path('movies'),
                ($ar ? 'المسلسلات' : 'Series')              => path('series'),
                ($ar ? 'المفضلة' : 'Favorites')             => path('favorites'),
                ($ar ? 'الإعدادات والمزيد' : 'Settings & More') => path('more'),
                ($ar ? 'من نحن' : 'About us')               => path('about'),
                ($ar ? 'اتصل بنا' : 'Contact us')           => path('contact'),
                ($ar ? 'سياسة الخصوصية' : 'Privacy policy') => path('privacy'),
                ($ar ? 'شروط الاستخدام' : 'Terms of use')   => path('terms'),
                ($ar ? 'سياسة ملفات الارتباط' : 'Cookie policy') => path('cookies'),
            ],
        ];
    }

    private static function siteKnowledgeText(): string
    {
        $k = self::siteKnowledge();
        $lines = [
            'Site name: ' . $k['name'],
            'Slogan: ' . $k['slogan'],
            'Official contact (Telegram): ' . $k['telegram'],
            'Android app (APK) download: ' . $k['app_apk'],
            'Internal pages (label => relative URL, always link with these):',
        ];
        foreach ($k['pages'] as $label => $url) $lines[] = "- {$label}: {$url}";
        return implode("\n", $lines);
    }

    /* ==================== CONTEXT ENGINE ==================== */

    /**
     * Normalize the page info sent by the client and enrich it server-side
     * with the REAL entity payload behind that URL.
     * @param array $page {path?, title?, desc?}
     */
    public static function pageContext(array $page): array
    {
        $path  = (string)($page['path'] ?? '');
        $path  = '/' . ltrim(strip_tags(mb_substr($path, 0, 200)), '/');
        if (!preg_match('#^/[\x20-\x7E\p{Arabic}%\-_/.]*$#u', $path)) $path = '/';
        $ctx = [
            'path'  => $path,
            'title' => mb_substr(strip_tags((string)($page['title'] ?? '')), 0, 200),
            'desc'  => mb_substr(strip_tags((string)($page['desc'] ?? '')), 0, 300),
            'data'  => '',
        ];
        $bare = preg_replace('#^/en(/|$)#', '/', rawurldecode($path)) ?? $path;

        // Match page → live payload summary (unified status resolver).
        if (preg_match('#^/match/.*?(\d+)$#u', $bare, $m)) {
            $info = Api::matchInfo((int)$m[1]);
            if (!empty($info['match_id'])) {
                $info = Api::unifyMatchState($info);
                $st = match_state($info);
                $ctx['data'] = 'Match: ' . team_name(team_of($info, 'home')) . ' vs ' . team_name(team_of($info, 'away'))
                    . ' | ' . (string)($info['championship']['title'] ?? '')
                    . ' | status: ' . $st['key'] . ' (' . $st['label'] . ')'
                    . ' | score: ' . (int)($info['home_scores'] ?? 0) . '-' . (int)($info['away_scores'] ?? 0)
                    . ' | date: ' . (string)($info['match_date'] ?? '') . ' ' . format_ts_time((int)($info['match_timestamp'] ?? 0))
                    . (!empty($info['Stadium']) ? ' | stadium: ' . (string)$info['Stadium'] : '');
            }
        } elseif (preg_match('#^/league/.*?(\d+)$#u', $bare, $m)) {
            foreach (Api::allLeagues() as $lg) {
                if ((int)$lg['url_id'] === (int)$m[1]) { $ctx['data'] = 'League: ' . (string)$lg['title']; break; }
            }
        } elseif (preg_match('#^/(movie|series)/.*?(\d+)$#u', $bare, $m)) {
            $type = $m[1] === 'series' ? 'tv' : 'movie';
            $it = $type === 'tv' ? Tmdb::tv((int)$m[2]) : Tmdb::movie((int)$m[2]);
            if (!empty($it['id'])) {
                $ctx['data'] = ($type === 'tv' ? 'Series: ' : 'Movie: ') . Tmdb::titleOf($it)
                    . ' (' . Tmdb::yearOf($it) . ') | rating: ' . Tmdb::rating($it['vote_average'] ?? 0)
                    . ' | ' . excerpt((string)($it['overview'] ?? ''), 200);
            }
        } elseif (preg_match('#^/news/.*?(\d+)$#u', $bare, $m)) {
            $n = Api::findNewsItem((int)$m[1]);
            if (!empty($n['title'])) $ctx['data'] = 'Article: ' . (string)$n['title'];
        }
        return $ctx;
    }

    /* ==================== PROMPT BUILDER ==================== */

    private static function buildPrompt(string $q, array $ctx, string $dataBlock): string
    {
        $p  = "Site Information:\n" . self::siteKnowledgeText() . "\n\n";
        $p .= "Current Page:\n"
            . 'URL: ' . ($ctx['path'] ?? '/') . "\n"
            . ($ctx['title'] !== '' ? 'Title: ' . $ctx['title'] . "\n" : '')
            . ($ctx['desc'] !== '' ? 'Description: ' . $ctx['desc'] . "\n" : '');
        if (($ctx['data'] ?? '') !== '') $p .= "Page Content:\n" . $ctx['data'] . "\n";
        $p .= "\n";
        if ($dataBlock !== '') $p .= "Site Data:\n" . $dataBlock . "\n\n";
        $p .= "User Question:\n" . $q;
        return $p;
    }

    /** Permanent system instruction (#anti-markdown, HTML-only contract). */
    private static function systemPrompt(): string
    {
        $ar = Lang::current() === 'ar';
        return
            "أنت مساعد ALOKA Live الرسمي. أنت جزء من الموقع نفسه، لست روبوت محادثة خارجياً.\n"
            . "كل البيانات المرسلة إليك في الأقسام Site Information وCurrent Page وSite Data صحيحة ومؤكدة — استخدمها مباشرة، "
            . "وممنوع أن ترد بعبارات مثل: لا أعرف، لا أملك معلومات، لا أستطيع الوصول، غير متوفر لدي — طالما أن المعلومة موجودة في تلك الأقسام.\n"
            . "إذا سُئلت عن معلومة غير موجودة في البيانات ولا يمكن تأكيدها، لا تخترعها أبداً: اعرض بدلاً منها واجهة توجّه المستخدم للقسم المناسب من صفحات الموقع.\n\n"
            . "قواعد الإخراج الإلزامية:\n"
            . "1) ممنوع الرد بنص عادي. 2) ممنوع Markdown نهائياً. 3) ممنوع استخدام * أو ## أو ### أو ``` .\n"
            . "4) مهمتك إنشاء واجهات HTML احترافية فقط، جاهزة للعرض مباشرة داخل نافذة المحادثة.\n"
            . "5) الرد دائماً بصيغة JSON واحدة فقط بلا أي شيء قبلها أو بعدها:\n"
            . '{"type":"html","html":"..."}' . "\n\n"
            . "دليل التصميم (استخدم هذه الأصناف فقط مع وسوم div/span/p/a/img/b/i/small/br/ul/li/h4):\n"
            . "- فقرة نصية: <p class=\"ai-p\">...</p>\n"
            . "- بطاقة عامة: <div class=\"ai-card\"><h4 class=\"aic-title\">عنوان</h4><p class=\"ai-p\">نص</p></div>\n"
            . "- صف معلومة: <div class=\"aic-row\"><span class=\"aic-k\">التسمية</span><span class=\"aic-v\">القيمة</span></div>\n"
            . "- زر/رابط: <a class=\"ai-cta\" href=\"/الرابط\">النص</a> — استخدم روابط الموقع الداخلية من Site Information فقط، أو mailto: للبريد أو رابط تيليجرام الرسمي.\n"
            . "- قائمة: <ul class=\"ai-list\"><li>...</li></ul>\n"
            . ($ar ? "أجب دائماً بلغة المستخدم (العربية إن كتب بالعربية)." : "Always answer in the user's language.")
            . " اجعل الواجهة موجزة وأنيقة ومتوافقة مع الجوال.";
    }

    /* ==================== LANGUAGE DETECTION ==================== */

    /**
     * Decide the response language:
     *   1. site language of the page the visitor is on (client hint / /en path)
     *   2. overridden by the MESSAGE language: Arabic script → ar,
     *      clearly-English sentences (≥2 latin words) → en.
     * English pages never get Arabic answers again.
     */
    public static function detectLang(string $message, array $page, string $hint = ''): string
    {
        $pageLang = in_array($hint, ['ar', 'en'], true)
            ? $hint
            : (preg_match('#^/en(/|$)#', (string)($page['path'] ?? '')) ? 'en' : 'ar');

        $msg = trim($message);
        if ($msg === '') return $pageLang;

        $arabic = preg_match_all('/\p{Arabic}+/u', $msg) ?: 0;
        $latin  = preg_match_all('/[A-Za-z]{2,}/u', $msg) ?: 0;

        if ($arabic > 0 && $arabic >= $latin) return 'ar';
        if ($latin > 0 && $latin > $arabic) return 'en';
        return $pageLang;
    }

    /* ==================== Entry point ==================== */

    /**
     * @param string $message raw user message (sanitized here)
     * @param array  $history [{role:'user'|'assistant', content:string}, …]
     * @param array  $page    client page info {path,title,desc} — a HINT only,
     *                        it never limits the search scope
     * @param array  $memory  conversation memory echoed back by the client
     *                        (last entity discussed: match/team/series…)
     * @return array{type:string, html:string, suggestions:array, memory:array}
     */
    public static function handle(string $message, array $history = [], array $page = [], array $memory = [], string $langHint = ''): array
    {
        // LANGUAGE DETECTION — boot the whole answer (copy + upstream data
        // host) into the visitor's language. Fixes "English pages return
        // Arabic": the /api/ai-chat route boots 'ar' by default, so we
        // re-boot here from the page path + message script.
        Lang::boot(self::detectLang($message, $page, $langHint));

        $q = trim(strip_tags($message));
        $q = mb_substr($q, 0, self::MAX_MSG_LEN);
        if ($q === '') {
            return self::out('<p class="ai-p">' . e(self::t('empty')) . '</p>', self::suggestions(), $memory);
        }

        // 1) Site data first — deterministic HTML, zero hallucination.
        $found = self::resolve($q, $memory);
        if ($found['html'] !== '') {
            // Carry conversation memory forward: an explicit memory from the
            // branch, else the entity behind the cards we just rendered, else
            // keep the previous memory so short follow-ups still have context.
            $mem = $found['memory'] ?? (self::memoryFrom(self::$lastCards) ?: $memory);
            return self::out($found['html'], $found['suggestions'] ?: self::suggestions(), $mem);
        }

        // 2) General questions → LLM (Gemini) with full context + memory;
        //    local HTML fallback when the model fails.
        $ctx = self::pageContext($page);
        $html = self::generate($q, $history, $ctx, $memory);
        if ($html === null) $html = self::fallbackHtml();
        return self::out($html, self::suggestions(), $memory);
    }

    private static function out(string $html, array $suggestions, array $memory): array
    {
        return ['type' => 'html', 'html' => $html, 'suggestions' => $suggestions, 'memory' => $memory];
    }

    /** Conversation memory from the entities just shown (first card wins). */
    private static function memoryFrom(array $cards): array
    {
        foreach ($cards as $c) {
            switch ($c['type'] ?? '') {
                case 'match':
                    return ['type' => 'match', 'id' => (int)$c['id'],
                            'title' => $c['home']['name'] . ' × ' . $c['away']['name']];
                case 'series':
                case 'movie':
                case 'team':
                case 'player':
                case 'league':
                    return ['type' => $c['type'], 'id' => (int)$c['id'], 'title' => (string)($c['title'] ?? '')];
            }
        }
        return [];
    }

    /* ==================== Intent + entity resolution (HTML out) ==================== */

    /** @var array cards behind the last answerHtml() — feeds conversation memory */
    private static array $lastCards = [];

    /** @return array{html:string, suggestions:array, memory?:array} */
    private static function resolve(string $q, array $memory = []): array
    {
        self::$lastCards = [];
        $norm = self::normalize($q);
        $none = ['html' => '', 'suggestions' => []];

        // ---- CONVERSATION MEMORY follow-ups ----
        // "who scored?" after a match answer → real goal events of THAT match.
        if (preg_match('/(من سجل|من احرز|مين سجل|سجل الاهداف|الاهداف|هداف المباراه|who scored|scorers?|goals\??)\s*[؟?]?\s*$/ui', $norm)
            && ($memory['type'] ?? '') === 'match' && (int)($memory['id'] ?? 0) > 0) {
            $html = self::goalsHtml((int)$memory['id']);
            if ($html !== '') return ['html' => $html, 'suggestions' => self::suggestions('matches'), 'memory' => $memory];
        }
        // "how many episodes / seasons?" after a series answer.
        if (preg_match('/(كم (حلقه|حلقات|موسم|مواسم)|عدد (الحلقات|المواسم)|how many (episodes|seasons)|episodes\??|seasons\??)/ui', $norm)
            && ($memory['type'] ?? '') === 'series' && (int)($memory['id'] ?? 0) > 0) {
            $cards = [self::seriesCardFrom(Tmdb::tv((int)$memory['id']), true)];
            if (!empty($cards[0]['id'])) {
                return ['html' => self::answerHtml(self::t('series_intro'), $cards),
                        'suggestions' => self::suggestions('cinema'), 'memory' => $memory];
            }
        }
        // "when is the match / متى المباراة" after a team/match answer.
        if (preg_match('/^\s*(متي|متى|when)\b/ui', $norm) && !preg_match('/\p{L}{2,}\s+\p{L}{2,}\s+\p{L}{2,}/u', trim(preg_replace('/^(متي|متى|when|المباراه|match|is|the)\s*/ui', '', $norm) ?? ''))
            && in_array($memory['type'] ?? '', ['team', 'match'], true) && !empty($memory['title'])) {
            $matches = AiTools::searchMatches(AiTools::teamTokens((string)$memory['title']), null, 'future', 2);
            if ($matches) {
                $cards = self::matchCards($matches, 2);
                return ['html' => self::answerHtml(self::t('match_intro'), $cards),
                        'suggestions' => self::suggestions('matches')];
            }
        }

        // ---- Date extraction (works for ANY intent below) ----
        $dx = AiTools::extractDate($norm);
        $qDate = $dx['date'];

        // Date-first queries: show the full fixtures/results list for that day
        // even when the user didn't literally type "matches".
        if ($qDate !== null && (trim((string)preg_replace('/[\d\/\-\s]+/u', '', $norm)) === '' || preg_match('/(جدول|schedule|fixtures|calendar|agenda|مواعيد|مباريات|matches|games|results?|نتائج|يوم|for\s+date)/ui', $norm))) {
            $cards = self::matchCards(Api::matchesByDate($qDate), 12);
            $intro = self::t('day_intro') . ' ' . $qDate . ':';
            if ($cards) {
                return ['html' => self::answerHtml($intro, $cards), 'suggestions' => self::suggestions('matches')];
            }
            return ['html' => '<p class="ai-p">' . e(self::t('no_matches')) . '</p>', 'suggestions' => self::suggestions('matches')];
        }

        // ---- Team form: "آخر 5 مباريات الهلال" / "last 5 Al Hilal matches" ----
        if (preg_match('/(?:اخر|آخر|last)\s*(\d{1,2})?\s*(?:مباريات|نتائج|matches|games|results)(?:\s+(?:of|for))?\s+(.{2,})$/ui', $norm, $m)
            || preg_match('/(?:مباريات|نتائج)\s+(.{2,})\s+(?:الاخيره|الأخيرة|السابقه)/ui', $norm, $m2)) {
            $token = trim($m[2] ?? $m2[1] ?? '');
            $n = max(1, min(8, (int)($m[1] ?? 5) ?: 5));
            $tf = AiTools::teamForm($token, $n);
            if ($tf['matches']) {
                $cards = self::matchCards($tf['matches'], $n);
                return ['html' => self::answerHtml(self::t('form_intro') . ' ' . team_name($tf['team']) . ':', $cards),
                        'suggestions' => self::suggestions('matches')];
            }
        }

        // ---- Standings: "ترتيب الدوري الإنجليزي" / "Premier League standings" ----
        if (preg_match('/(?:ترتيب|جدول ترتيب|standings?|league table|table of)\s*(.*)$/ui', $norm, $m)
            && preg_match('/(ترتيب|standing|table)/ui', $norm)) {
            $st = AiTools::standings(trim($m[1]));
            if ($st) {
                return ['html' => self::standingsHtml($st['league'], $st['rows']),
                        'suggestions' => self::suggestions('matches'),
                        'memory' => ['type' => 'league', 'id' => (int)$st['league']['url_id'], 'title' => (string)$st['league']['title']]];
            }
        }

        // ---- Top scorers: "هدافي الدوري الإسباني" / "La Liga top scorers" ----
        if (preg_match('/(?:هداف|الهدافين|هدافي|top ?scorers?)\s*(.*)$/ui', $norm, $m)) {
            $ts = AiTools::topScorers(trim($m[1]));
            if ($ts) {
                return ['html' => self::scorersHtml($ts['league'], $ts['rows']),
                        'suggestions' => self::suggestions('matches'),
                        'memory' => ['type' => 'league', 'id' => (int)$ts['league']['url_id'], 'title' => (string)$ts['league']['title']]];
            }
        }

        // ---- Site-identity intents (real values from site config) ----
        if (preg_match('/(تلي?جرام|تلغرام|telegram)/ui', $norm)) {
            return ['html' => self::telegramHtml(), 'suggestions' => self::suggestions()];
        }
        if (preg_match('/(بريد|ايميل|إيميل|email|e-?mail)/ui', $norm)) {
            return ['html' => self::emailHtml(), 'suggestions' => self::suggestions()];
        }
        if (preg_match('/(اتصل بنا|تواصل|راسل|contact|support|دعم)/ui', $norm)) {
            return ['html' => self::contactHtml(), 'suggestions' => self::suggestions()];
        }
        if (preg_match('/^\s*(من نحن|عن الموقع|ما هو الموقع|about( us)?)\s*[؟?]?\s*$/ui', $norm)) {
            return ['html' => self::aboutHtml(), 'suggestions' => self::suggestions()];
        }
        if (preg_match('/(حمل|تحميل|تنزيل).*(تطبيق|البرنامج)|download.*app|apk/ui', $norm)) {
            return ['html' => self::appHtml(), 'suggestions' => self::suggestions()];
        }

        // ---- Latest / filtered news ----
        if (preg_match('/(?:latest|احدث|أحدث|newest|recent)\s*(\d{1,2})?\s*(?:news|اخبار|خبر)(?:\s+(?:of|about|عن))?\s*(.*)$/ui', $norm, $m)) {
            $limit = max(1, min(8, (int)($m[1] ?? 5) ?: 5));
            $topic = trim((string)($m[2] ?? ''));
            $cards = self::newsCards($topic, $limit);
            return ['html' => self::answerHtml($cards ? self::t('news_intro') : self::t('not_found_news'), $cards),
                    'suggestions' => self::suggestions('news')];
        }

        // ---- Fixed sports/cinema intents ----
        // Day listings for ANY date: اليوم / أمس / غداً / بعد غد / 2026-07-19…
        if (preg_match('/(مباريات|matches)/ui', $norm)
            && trim((string)preg_replace('/(مباريات|matches|يوم|on|of)/ui', ' ', $dx['rest'])) === ''
        ) {
            $date = $qDate ?? date('Y-m-d');
            $cards = self::matchCards(Api::matchesByDate($date), 8);
            $intro = $date === date('Y-m-d') ? self::t('today_intro')
                : ($date === date('Y-m-d', strtotime('+1 day')) ? self::t('tomorrow_intro')
                : self::t('day_intro') . ' ' . $date . ':');
            return ['html' => self::answerHtml($cards ? $intro : self::t('no_matches'), $cards),
                    'suggestions' => self::suggestions('matches')];
        }
        if (preg_match('/(مباشر|live)/ui', $norm) && !preg_match('/(فيلم|مسلسل|movie|series)/ui', $norm)) {
            $live = array_values(array_filter(Api::matchesByDate(), fn($m) => match_state($m)['key'] === 'live'));
            $cards = self::matchCards($live, 6);
            return ['html' => self::answerHtml($cards ? self::t('live_intro') : self::t('no_live'), $cards),
                    'suggestions' => self::suggestions('matches')];
        }
        if (preg_match('/(احدث|جديد).*(افلام|فيلم)|latest movies|new movies/ui', $norm)) {
            $rows = CinemaPolicy::filterList(Tmdb::nowPlayingMovies()['results'] ?? [], 'movie');
            $cards = array_map([self::class, 'movieCardFrom'], array_slice($rows, 0, 4));
            if ($cards) return ['html' => self::answerHtml(self::t('movie_intro'), $cards), 'suggestions' => self::suggestions('cinema')];
        }
        if (preg_match('/(احدث|جديد).*(مسلسلات|مسلسل)|latest series|new series/ui', $norm)) {
            $rows = CinemaPolicy::filterList(Tmdb::onTheAirTv()['results'] ?? [], 'tv');
            $cards = array_map(fn($tv) => self::seriesCardFrom($tv, false), array_slice($rows, 0, 4));
            if ($cards) return ['html' => self::answerHtml(self::t('series_intro'), $cards), 'suggestions' => self::suggestions('cinema')];
        }
        if (preg_match('/(الدوريات|البطولات|دوريات|championships|leagues)\s*$/ui', $norm)) {
            $cards = [];
            foreach (array_slice(Api::allLeagues(), 0, 6) as $lg) {
                $cards[] = ['type' => 'league', 'id' => (int)$lg['url_id'], 'url' => league_url($lg),
                            'title' => (string)$lg['title'], 'img' => league_img($lg)];
            }
            if ($cards) return ['html' => self::answerHtml(self::t('entity_intro'), $cards), 'suggestions' => self::suggestions('matches')];
        }

        // ---- Movie ----
        // Accept titles anywhere in the sentence so requests like
        // "ارسل الرابط مسلسل المؤسس عثمان" still resolve to the real page.
        if (preg_match('/(?:^|\s)(?:فيلم|افلام|movie|film)\s+(.{2,})$/ui', $norm, $m)) {
            $cards = self::movieCards(trim($m[1]));
            if (!$cards && preg_match('/(?:رابط|صفحة|مشاهدة|watch|link)/ui', $norm)) {
                $cards = self::cinemaMultiCards(trim($m[1]));
            }
            return ['html' => self::answerHtml($cards ? self::t('movie_intro') : self::t('not_found_movie'), $cards),
                    'suggestions' => self::suggestions('cinema')];
        }
        // ---- Series ----
        if (preg_match('/(?:^|\s)(?:مسلسل|مسلسلات|series|show|anime|انمي)\s+(.{2,})$/ui', $norm, $m)) {
            $cards = self::seriesCards(trim($m[1]));
            if (!$cards && preg_match('/(?:رابط|صفحة|مشاهدة|watch|link)/ui', $norm)) {
                $cards = self::cinemaMultiCards(trim($m[1]));
            }
            return ['html' => self::answerHtml($cards ? self::t('series_intro') : self::t('not_found_series'), $cards),
                    'suggestions' => self::suggestions('cinema')];
        }
        // ---- News ----
        if (preg_match('/^\s*(?:اخبار|خبر|news(?:\s+(?:of|about))?)\s*(.*)$/ui', $norm, $m)) {
            $cards = self::newsCards(trim($m[1]));
            return ['html' => self::answerHtml($cards ? self::t('news_intro') : self::t('not_found_news'), $cards),
                    'suggestions' => self::suggestions('news')];
        }
        // ---- Channel ----
        if (preg_match('/^\s*(?:قناه|قنوات|channel)\s*(.*)$/ui', $norm, $m)) {
            $cards = self::channelCards(trim($m[1]));
            if ($cards) return ['html' => self::answerHtml(self::t('channel_intro'), $cards), 'suggestions' => self::suggestions('matches')];
        }

        // Generic "matches X" questions should search the whole site data
        // even if the user didn't specify result/schedule wording.
        if (preg_match('/^\s*(?:مباريات|matches)\s+(.{2,})$/ui', $norm, $m)) {
            $topic = trim((string)$m[1]);
            $tokens = AiTools::teamTokens($topic);
            $found = AiTools::searchMatches($tokens ?: [$topic], $qDate, 'any', 4);
            if ($found) {
                return ['html' => self::answerHtml(self::t('match_intro'), self::matchCards($found, 4)), 'suggestions' => self::suggestions('matches')];
            }
        }

        // ---- GLOBAL MATCH SEARCH — past seasons, live, and future rounds.
        // Phrasing decides the direction: result questions prefer history
        // ("كيف انتهت مباراة إسبانيا والأرجنتين؟"), schedule questions prefer
        // the future ("متى مباراة النصر والأهلي؟"); the search itself is never
        // limited to today.
        $prefer = 'any';
        if (preg_match('/(كيف انتهت|انتهت|نتيجه|النتيجه|خلصت|من فاز|من كسب|how did|result|final score|who won|ended)/ui', $norm)) $prefer = 'past';
        if (preg_match('/^\s*(متي|متى|when)\b|موعد|قادمه|القادمه|next match|upcoming/ui', $norm)) $prefer = 'future';
        $matchQuery = trim((string)preg_replace(
            '/(كيف انتهت|انتهت|نتيجه|النتيجه|من فاز|من كسب|موعد|متي|متى|مباراه|ماتش|القادمه|قادمه|how did|result of|result|when is|when|match|the|final score|who won|end(ed)?\??)/ui',
            ' ', $dx['rest']));
        if ($matchQuery !== '' && mb_strlen($matchQuery) >= 2) {
            $tokens = AiTools::teamTokens($matchQuery);
            $found = AiTools::searchMatches($tokens, $qDate, $prefer, 3);
            if (!$found && count($tokens) >= 1) {
                foreach ($tokens as $tok) {
                    $more = AiTools::searchMatches([$tok], $qDate, $prefer, 3);
                    if ($more) { $found = array_merge($found, $more); }
                    $aliasMore = AiTools::searchMatches([$tok], $qDate, $prefer, 3);
                    if ($aliasMore) { $found = array_merge($found, $aliasMore); }
                }
                $found = array_values(array_unique($found, SORT_REGULAR));
            }
            if ($found) {
                $cards = self::matchCards($found, 3);
                $intro = $prefer === 'past' ? self::t('result_intro')
                       : ($prefer === 'future' ? self::t('schedule_intro') : self::t('match_intro'));
                return ['html' => self::answerHtml($intro, $cards), 'suggestions' => self::suggestions('matches')];
            }
            // Explicit result/schedule question but nothing found upstream →
            // honest answer (never guessed).
            if ($prefer !== 'any' && count($tokens) >= 1) {
                return ['html' => '<p class="ai-p">' . e(self::t('no_match_found')) . '</p>',
                        'suggestions' => self::suggestions('matches')];
            }
        }

        // ---- General search: teams/players → cinema → league ----
        if (mb_strlen($norm) >= 2) {
            $sr = Api::search($norm);
            $cards = [];
            foreach (array_slice($sr['teams'] ?? [], 0, 2) as $t) {
                $team = is_array($t['name'] ?? null) ? $t['name'] : $t;
                if (!empty($team['row_id'])) $cards[] = self::teamCard($team);
            }
            foreach (array_slice($sr['player'] ?? [], 0, 2) as $p) {
                $pl = is_array($p['name'] ?? null) ? $p['name'] : $p;
                if (!empty($pl['row_id'])) $cards[] = self::playerCard($pl);
            }
            if (count($cards) === 1 && ($cards[0]['type'] ?? '') === 'team') {
                $cards = array_merge($cards, self::teamMatchCards((int)$cards[0]['id'], 2));
            }
            if ($cards) {
                return ['html' => self::answerHtml(self::t('entity_intro'), array_values(array_filter($cards))),
                        'suggestions' => self::suggestions()];
            }
            $cine = self::cinemaMultiCards($norm);
            if ($cine) return ['html' => self::answerHtml(self::t('cinema_intro'), $cine), 'suggestions' => self::suggestions('cinema')];
            $lg = self::leagueCard($norm);
            if ($lg) return ['html' => self::answerHtml(self::t('entity_intro'), [$lg]), 'suggestions' => self::suggestions('matches')];
        }

        return $none;
    }

    /* ==================== Card data builders (real payloads) ==================== */

    private static function matchCards(array $matches, int $max): array
    {
        $cards = [];
        foreach ($matches as $m) {
            if (!is_array($m) || empty($m['match_id'])) continue;
            $state = match_state($m);
            $home = team_of($m, 'home');
            $away = team_of($m, 'away');
            $cards[] = [
                'type'   => 'match',
                'id'     => (int)$m['match_id'],
                'url'    => match_url($m),
                'home'   => ['name' => team_name($home), 'img' => team_img($home)],
                'away'   => ['name' => team_name($away), 'img' => team_img($away)],
                'league' => (string)($m['championship']['title'] ?? ''),
                'state'  => $state['key'],
                'label'  => $state['label'],
                'score'  => $state['started'] ? ((int)($m['home_scores'] ?? 0) . ' - ' . (int)($m['away_scores'] ?? 0)) : '',
                'time'   => format_ts_time((int)($m['match_timestamp'] ?? 0)),
                'date'   => (string)($m['match_date'] ?? ''),
            ];
            if (count($cards) >= $max) break;
        }
        return $cards;
    }

    /** Match by team names in a ±3-day window ("فرنسا والأرجنتين", "X vs Y"). */
    private static function findMatchCards(string $q): array
    {
        $tokens = preg_split('/\s+(?:ضد|مع|vs\.?|versus|and|x)\s+|\s+و(?=\S)\s*|\s*[×–]\s*|\s+-\s+/ui', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), fn($t) => mb_strlen($t) >= 2));
        if (!$tokens) $tokens = [trim($q)];
        if (mb_strlen($tokens[0]) < 2) return [];
        $tokens = array_map(fn($t) => mb_strtolower(self::normalize($t)), array_slice($tokens, 0, 3));

        $best = [];
        for ($i = -1; $i <= 3; $i++) {
            $d = date('Y-m-d', strtotime("{$i} days"));
            foreach (Api::matchesByDate($d) as $m) {
                $names = mb_strtolower(self::normalize(
                    team_name(team_of($m, 'home')) . ' ' . team_name(team_of($m, 'away'))));
                $hits = 0;
                foreach ($tokens as $t) {
                    if (mb_stripos($names, $t) !== false) $hits++;
                }
                if ($hits === 0) continue;
                $best[] = ['hits' => $hits, 'i' => abs($i), 'm' => $m];
            }
        }
        if (!$best) return [];
        usort($best, fn($a, $b) => [$b['hits'], -$a['i']] <=> [$a['hits'], -$b['i']]);
        if (count($tokens) >= 2 && $best[0]['hits'] < 2) return [];
        return self::matchCards(array_column(array_slice($best, 0, 3), 'm'), 3);
    }

    private static function teamMatchCards(int $teamId, int $max): array
    {
        $b = Api::teamMatchesBuckets($teamId);
        $list = array_merge(array_slice($b['fixtures'], 0, $max), array_slice($b['results'], 0, 1));
        return self::matchCards($list, $max);
    }

    private static function movieCardFrom(array $mv): array
    {
        return [
            'type'     => 'movie',
            'id'       => (int)($mv['id'] ?? 0),
            'url'      => movie_url($mv),
            'title'    => Tmdb::titleOf($mv),
            'year'     => Tmdb::yearOf($mv),
            'rating'   => (float)($mv['vote_average'] ?? 0) > 0 ? Tmdb::rating($mv['vote_average']) : '',
            'age'      => CinemaPolicy::ratingLabel(CinemaPolicy::itemFor('movie', (int)($mv['id'] ?? 0))['rating']),
            'poster'   => tmdb_poster($mv['poster_path'] ?? null, 'w185'),
            'overview' => excerpt((string)($mv['overview'] ?? ''), 120),
        ];
    }

    private static function seriesCardFrom(array $tv, bool $enrich): array
    {
        $seasons = $episodes = 0;
        if ($enrich) {
            $full = Tmdb::tv((int)($tv['id'] ?? 0));
            $seasons  = (int)($full['number_of_seasons'] ?? 0);
            $episodes = (int)($full['number_of_episodes'] ?? 0);
        }
        return [
            'type'     => 'series',
            'id'       => (int)($tv['id'] ?? 0),
            'url'      => series_url($tv),
            'title'    => Tmdb::titleOf($tv),
            'year'     => Tmdb::yearOf($tv),
            'rating'   => (float)($tv['vote_average'] ?? 0) > 0 ? Tmdb::rating($tv['vote_average']) : '',
            'age'      => CinemaPolicy::ratingLabel(CinemaPolicy::itemFor('tv', (int)($tv['id'] ?? 0))['rating']),
            'poster'   => tmdb_poster($tv['poster_path'] ?? null, 'w185'),
            'overview' => excerpt((string)($tv['overview'] ?? ''), 120),
            'seasons'  => $seasons,
            'episodes' => $episodes,
        ];
    }

    private static function movieCards(string $title, int $max = 3): array
    {
        $res = Tmdb::get('/search/movie', ['query' => $title, 'include_adult' => 'false']);
        $rows = CinemaPolicy::filterList($res['results'] ?? [], 'movie');
        $rows = array_values(array_filter($rows, fn($r) => !empty($r['id'])));
        return array_map([self::class, 'movieCardFrom'], array_slice($rows, 0, $max));
    }

    private static function seriesCards(string $title, int $max = 3): array
    {
        $res = Tmdb::get('/search/tv', ['query' => $title, 'include_adult' => 'false']);
        $rows = CinemaPolicy::filterList($res['results'] ?? [], 'tv');
        $rows = array_values(array_filter($rows, fn($r) => !empty($r['id'])));
        $cards = [];
        foreach (array_slice($rows, 0, $max) as $i => $tv) {
            $cards[] = self::seriesCardFrom($tv, $i === 0);
        }
        return $cards;
    }

    /** Mixed movie+tv lookup for bare titles ("Avatar", "Squid Game"). */
    private static function cinemaMultiCards(string $title, int $max = 3): array
    {
        $res = Tmdb::searchMulti($title);
        $rows = CinemaPolicy::filterList(array_values(array_filter(
            $res['results'] ?? [],
            fn($r) => in_array($r['media_type'] ?? '', ['movie', 'tv'], true)
                && !empty($r['id'])
                && (float)($r['popularity'] ?? 0) > 1.5
        )));
        $cards = [];
        foreach (array_slice($rows, 0, $max) as $i => $it) {
            $cards[] = Tmdb::typeOf($it) === 'tv'
                ? self::seriesCardFrom($it, $i === 0)
                : self::movieCardFrom($it);
        }
        return $cards;
    }

    private static function newsCards(string $topic, int $max = 4): array
    {
        $topic = trim($topic);
        if ($topic !== '' && mb_strlen($topic) >= 2) {
            $sr = Api::search($topic);
            $team = $sr['teams'][0]['name'] ?? null;
            if (is_array($team) && !empty($team['row_id'])) {
                $items = Api::teamNews((int)$team['row_id']);
                if ($items) return self::newsItemCards($items, $max);
            }
            $pool = array_merge(Api::newsPage(1)['items'], Api::allNewsPage()['last_news']);
            $hits = array_values(array_filter($pool, fn($n) =>
                mb_stripos((string)($n['title'] ?? ''), $topic) !== false));
            if ($hits) return self::newsItemCards($hits, $max);
            return [];
        }
        return self::newsItemCards(Api::newsPage(1)['items'], $max);
    }

    private static function newsItemCards(array $items, int $max): array
    {
        $cards = [];
        foreach ($items as $n) {
            if (!is_array($n) || empty($n['id']) || empty($n['title'])) continue;
            $cards[] = [
                'type'  => 'news',
                'id'    => (int)$n['id'],
                'url'   => news_url($n),
                'title' => (string)$n['title'],
                'img'   => news_img($n, '300'),
                'time'  => time_ago($n['created_at'] ?? ($n['date'] ?? '')),
            ];
            if (count($cards) >= $max) break;
        }
        return $cards;
    }

    private static function channelCards(string $name, int $max = 4): array
    {
        $all = [];
        foreach (ChannelLib::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        foreach (AppChannels::all() as $c) { if (!empty($c['name'])) $all[(string)$c['name']] = true; }
        $names = array_keys($all);
        if ($name !== '') {
            $names = array_values(array_filter($names, fn($n) => mb_stripos($n, $name) !== false));
        }
        $cards = [];
        foreach (array_slice($names, 0, $max) as $n) {
            $cards[] = ['type' => 'channel', 'name' => $n, 'url' => path('live')];
        }
        return $cards;
    }

    private static function teamCard(array $team): array
    {
        return ['type' => 'team', 'id' => (int)($team['row_id'] ?? 0), 'url' => team_url($team),
                'title' => team_name($team), 'img' => team_img($team)];
    }

    private static function playerCard(array $p): array
    {
        return ['type' => 'player', 'id' => (int)($p['row_id'] ?? 0), 'url' => player_url($p),
                'title' => player_label($p), 'img' => player_img($p)];
    }

    private static function leagueCard(string $q): ?array
    {
        foreach (Api::allLeagues() as $lg) {
            $title = (string)($lg['title'] ?? '');
            if ($title !== '' && mb_stripos($title, $q) !== false) {
                return ['type' => 'league', 'id' => (int)$lg['url_id'], 'url' => league_url($lg),
                        'title' => $title, 'img' => league_img($lg)];
            }
        }
        return null;
    }

    /* ==================== LOCAL HTML RENDERER (fallback + data intents) ==================== */

    /** Intro paragraph + card stack → ready-to-render HTML. */
    private static function answerHtml(string $intro, array $cards): string
    {
        // Remember the entities we just showed so a bare follow-up
        // ("who scored?", "how many episodes?") resolves against them.
        if ($cards) self::$lastCards = $cards;
        $html = '<p class="ai-p">' . e($intro) . '</p>';
        if ($cards) {
            $html .= '<div class="ai-cards">';
            foreach (array_slice($cards, 0, 6) as $c) $html .= self::cardHtml($c);
            $html .= '</div>';
        }
        return $html;
    }

    /* ==================== Goal / standings / scorers HTML ==================== */

    /** Goal scorers of a match ("who scored?"). Real events only. */
    private static function goalsHtml(int $matchId): string
    {
        $g = AiTools::matchGoals($matchId);
        if (!$g['match']) return '';
        $info = $g['match'];
        $home = team_name(team_of($info, 'home'));
        $away = team_name(team_of($info, 'away'));
        $hs = (int)($info['home_scores'] ?? 0);
        $as = (int)($info['away_scores'] ?? 0);
        $state = match_state($info);

        $html = '<div class="ai-card">'
            . '<h4 class="aic-title">' . e($home . ' ' . $hs . ' - ' . $as . ' ' . $away) . '</h4>';
        if (!$g['home'] && !$g['away']) {
            $html .= '<p class="ai-p">' . e($state['key'] === 'upcoming'
                ? self::t('no_goals_yet') : self::t('no_goals')) . '</p>';
        } else {
            $side = function (string $team, array $goals): string {
                if (!$goals) return '';
                $items = '';
                foreach ($goals as $gl) {
                    $tag = $gl['kind'] === 'penalty' ? ' (' . t('event.penalty') . ')'
                         : ($gl['kind'] === 'owngoal' ? ' (' . t('event.owngoal') . ')' : '');
                    $items .= '<li>' . e($gl['player'] . ' ' . $gl['minute'] . '′' . $tag) . '</li>';
                }
                return '<div class="aic-goals"><b class="aicg-team">' . e($team) . '</b><ul class="ai-list">' . $items . '</ul></div>';
            };
            $html .= $side($home, $g['home']) . $side($away, $g['away']);
        }
        $html .= '<a class="ai-cta" href="' . e(match_url($info)) . '">' . e(t('ai.cta_details')) . '</a></div>';
        return $html;
    }

    /** League standings table (real rows). */
    private static function standingsHtml(array $league, array $rows): string
    {
        $ar = Lang::current() === 'ar';
        $html = '<div class="ai-card"><h4 class="aic-title">' . e(($ar ? 'ترتيب ' : 'Standings — ') . (string)$league['title']) . '</h4>'
            . '<table class="ai-table"><thead><tr>'
            . '<th>#</th><th>' . e($ar ? 'الفريق' : 'Team') . '</th>'
            . '<th>' . e($ar ? 'لعب' : 'P') . '</th><th>' . e($ar ? 'نقاط' : 'Pts') . '</th></tr></thead><tbody>';
        $i = 0;
        foreach (array_slice($rows, 0, 12) as $r) {
            $i++;
            $team = team_name($r['team_name'] ?? []);
            $html .= '<tr><td>' . $i . '</td><td>' . e($team) . '</td>'
                . '<td>' . (int)($r['play'] ?? 0) . '</td>'
                . '<td><b>' . (int)($r['points'] ?? 0) . '</b></td></tr>';
        }
        $html .= '</tbody></table>'
            . '<a class="ai-cta" href="' . e(league_url($league)) . '">' . e(t('ai.cta_details')) . '</a></div>';
        return $html;
    }

    /** League top scorers (real rows). */
    private static function scorersHtml(array $league, array $rows): string
    {
        $ar = Lang::current() === 'ar';
        $html = '<div class="ai-card"><h4 class="aic-title">' . e(($ar ? 'هدافو ' : 'Top scorers — ') . (string)$league['title']) . '</h4>'
            . '<table class="ai-table"><thead><tr>'
            . '<th>#</th><th>' . e($ar ? 'اللاعب' : 'Player') . '</th>'
            . '<th>' . e($ar ? 'أهداف' : 'Goals') . '</th></tr></thead><tbody>';
        $i = 0;
        foreach ($rows as $s) {
            $i++;
            $pi = $s['player_info'] ?? [];
            $html .= '<tr><td>' . $i . '</td><td>' . e(player_label($pi, '—'))
                . (!empty($pi['team_name']) ? ' <small>' . e((string)$pi['team_name']) . '</small>' : '')
                . '</td><td><b>' . (int)($s['goals'] ?? 0) . '</b></td></tr>';
        }
        $html .= '</tbody></table>'
            . '<a class="ai-cta" href="' . e(league_url($league)) . '">' . e(t('ai.cta_details')) . '</a></div>';
        return $html;
    }

    private static function cardHtml(array $c): string
    {
        $cta = fn(string $label): string => '<span class="ai-cta">' . e($label) . '</span>';
        switch ($c['type'] ?? '') {
            case 'match':
                $stateCls = $c['state'] === 'live' ? 'is-live' : ($c['state'] === 'finished' ? 'is-ft' : 'is-soon');
                $mid = ($c['score'] !== '' ? '<b class="acm-score">' . e($c['score']) . '</b>' : '')
                     . '<span class="acm-state ' . $stateCls . '">' . e($c['label']) . '</span>';
                $meta = [];
                if ($c['league'] !== '') $meta[] = $c['league'];
                if ($c['state'] === 'upcoming' && $c['time'] !== '') $meta[] = ($c['date'] !== '' ? $c['date'] . ' · ' : '') . $c['time'];
                return '<a class="ai-card ai-card-match" href="' . e($c['url']) . '">'
                    . '<div class="acm-row">'
                    . '<span class="acm-team"><img class="acm-logo" src="' . e($c['home']['img']) . '" alt="" loading="lazy"><b>' . e($c['home']['name']) . '</b></span>'
                    . '<span class="acm-mid">' . $mid . '</span>'
                    . '<span class="acm-team"><img class="acm-logo" src="' . e($c['away']['img']) . '" alt="" loading="lazy"><b>' . e($c['away']['name']) . '</b></span>'
                    . '</div>'
                    . ($meta ? '<div class="acm-meta">' . e(implode(' — ', $meta)) . '</div>' : '')
                    . $cta($c['state'] === 'live' ? t('ai.cta_watch_match') : t('ai.cta_details'))
                    . '</a>';
            case 'movie':
            case 'series':
                $chips = '';
                if ($c['rating'] !== '') $chips .= '<span class="acp-chip acp-rate">⭐ ' . e($c['rating']) . '</span>';
                if (!in_array($c['age'], ['عام', 'G'], true)) $chips .= '<span class="acp-chip acp-age">' . e($c['age']) . '</span>';
                if ($c['type'] === 'series' && ($c['seasons'] ?? 0) > 0) {
                    $chips .= '<span class="acp-chip">' . (int)$c['seasons'] . ' ' . e(t('cinema.seasons'))
                        . (($c['episodes'] ?? 0) > 0 ? ' · ' . (int)$c['episodes'] . ' ' . e(t('cinema.episodes')) : '') . '</span>';
                }
                return '<a class="ai-card ai-card-poster" href="' . e($c['url']) . '">'
                    . '<img class="acp-poster" src="' . e($c['poster']) . '" alt="" loading="lazy">'
                    . '<div class="acp-info"><b class="acp-title">' . e($c['title'] . ($c['year'] !== '' ? ' (' . $c['year'] . ')' : '')) . '</b>'
                    . ($chips !== '' ? '<div class="acp-chips">' . $chips . '</div>' : '')
                    . ($c['overview'] !== '' ? '<p class="acp-ov">' . e($c['overview']) . '</p>' : '')
                    . $cta($c['type'] === 'series' ? t('ai.cta_watch_series') : t('ai.cta_watch_movie'))
                    . '</div></a>';
            case 'news':
                return '<a class="ai-card ai-card-news" href="' . e($c['url']) . '">'
                    . '<img class="acn-img" src="' . e($c['img']) . '" alt="" loading="lazy">'
                    . '<div class="acn-info"><b class="acn-title">' . e($c['title']) . '</b>'
                    . ($c['time'] !== '' ? '<small class="acn-time">' . e($c['time']) . '</small>' : '')
                    . $cta(t('ai.cta_details')) . '</div></a>';
            case 'team':
            case 'player':
            case 'league':
                return '<a class="ai-card ai-card-entity" href="' . e($c['url']) . '">'
                    . '<img class="ace-img" src="' . e($c['img']) . '" alt="" loading="lazy">'
                    . '<b class="ace-title">' . e($c['title']) . '</b>' . $cta(t('ai.cta_details')) . '</a>';
            case 'channel':
                return '<a class="ai-card ai-card-entity" href="' . e($c['url']) . '">'
                    . '<span class="ace-img ace-tv"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="13" rx="3"/><path d="m8 2 4 4 4-4"/></svg></span>'
                    . '<b class="ace-title">' . e($c['name']) . '</b>' . $cta(t('ai.cta_open_channel')) . '</a>';
        }
        return '';
    }

    /* ---- Site-identity HTML templates (values from the site itself) ---- */

    private static function infoRow(string $k, string $v, string $href = ''): string
    {
        $val = $href !== ''
            ? '<a class="aic-v aic-link" href="' . e($href) . '"' . (str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '') . '>' . e($v) . '</a>'
            : '<span class="aic-v">' . e($v) . '</span>';
        return '<div class="aic-row"><span class="aic-k">' . e($k) . '</span>' . $val . '</div>';
    }

    private static function contactHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        return '<div class="ai-card"><h4 class="aic-title">' . e($ar ? 'تواصل مع ' . $k['name'] : 'Contact ' . $k['name']) . '</h4>'
            . self::infoRow($ar ? 'قناة تيليجرام' : 'Telegram', '@alokalive', $k['telegram'])
            . self::infoRow($ar ? 'صفحة التواصل' : 'Contact page', $ar ? 'اتصل بنا' : 'Contact us', path('contact'))
            . '<a class="ai-cta" href="' . e(path('contact')) . '">' . e($ar ? 'فتح صفحة اتصل بنا' : 'Open contact page') . '</a>'
            . '</div>';
    }

    private static function emailHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        return '<div class="ai-card"><h4 class="aic-title">' . e($ar ? 'التواصل الرسمي' : 'Official contact') . '</h4>'
            . '<p class="ai-p">' . e($ar ? 'يمكنك التواصل معنا مباشرة عبر تيليجرام:' : 'You can reach us directly on Telegram:') . '</p>'
            . self::infoRow('Telegram', '@alokalive', $k['telegram'])
            . '<a class="ai-cta" href="' . e($k['telegram']) . '" target="_blank" rel="noopener">' . e($ar ? 'تواصل عبر تيليجرام' : 'Contact on Telegram') . '</a>'
            . '</div>';
    }

    private static function telegramHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        return '<div class="ai-card"><h4 class="aic-title">' . e($ar ? 'قناة تيليجرام الرسمية' : 'Official Telegram channel') . '</h4>'
            . '<p class="ai-p">' . e($ar ? 'تابع آخر الأخبار والمباريات والتحديثات مباشرة عبر قناتنا الرسمية.' : 'Get the latest news, matches and updates on our official channel.') . '</p>'
            . self::infoRow('Telegram', '@alokalive', $k['telegram'])
            . '<a class="ai-cta" href="' . e($k['telegram']) . '" target="_blank" rel="noopener">' . e($ar ? 'الانضمام الآن' : 'Join now') . '</a>'
            . '</div>';
    }

    private static function aboutHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        return '<div class="ai-card"><h4 class="aic-title">' . e($k['name']) . '</h4>'
            . '<p class="ai-p">' . e($k['slogan']) . '</p>'
            . '<ul class="ai-list">'
            . '<li>' . e($ar ? 'مباريات مباشرة ونتائج لحظة بلحظة وجداول ترتيب وهدافون' : 'Live matches, instant scores, standings and top scorers') . '</li>'
            . '<li>' . e($ar ? 'أخبار رياضية متجددة على مدار الساعة' : 'Round-the-clock sports news') . '</li>'
            . '<li>' . e($ar ? 'مكتبة أفلام ومسلسلات ضخمة بجودة عالية' : 'A huge movies & series library in HD') . '</li>'
            . '</ul>'
            . '<a class="ai-cta" href="' . e(path('about')) . '">' . e($ar ? 'صفحة من نحن كاملة' : 'Full about page') . '</a>'
            . '</div>';
    }

    private static function appHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        return '<div class="ai-card"><h4 class="aic-title">' . e($ar ? 'تطبيق ' . $k['name'] . ' للأندرويد' : $k['name'] . ' Android app') . '</h4>'
            . '<p class="ai-p">' . e($ar ? 'حمّل التطبيق الرسمي وشاهد المحتوى بجودة عالية وتجربة أسرع.' : 'Download the official app for HD playback and a faster experience.') . '</p>'
            . '<a class="ai-cta" href="' . e($k['app_apk']) . '" target="_blank" rel="noopener nofollow">' . e($ar ? 'تحميل التطبيق (APK)' : 'Download the app (APK)') . '</a>'
            . '</div>';
    }

    /** Local fallback UI whenever the model fails (#Fallback System). */
    private static function fallbackHtml(): string
    {
        $ar = Lang::current() === 'ar';
        $k = self::siteKnowledge();
        $html = '<p class="ai-p">' . e($ar
            ? 'لم أتمكن من توليد إجابة الآن — إليك أقسام الموقع الرئيسية للوصول السريع:'
            : "I couldn't generate an answer right now — here are the main sections for quick access:") . '</p>';
        $html .= '<div class="ai-cards">';
        $quick = $ar
            ? ['مباريات اليوم' => path('matches'), 'المباريات المباشرة' => path('live'), 'الأخبار' => path('news'), 'الأفلام' => path('movies'), 'المسلسلات' => path('series')]
            : ["Today's matches" => path('matches'), 'Live' => path('live'), 'News' => path('news'), 'Movies' => path('movies'), 'Series' => path('series')];
        $html .= '<div class="ai-card">';
        foreach ($quick as $label => $url) {
            $html .= self::infoRow($label, $ar ? 'فتح' : 'Open', $url);
        }
        $html .= '</div></div>';
        return $html;
    }

    /* ==================== LLM (Gemini native / OpenAI-compatible) ==================== */

    /** General question → sanitized HTML (or null → caller falls back). */
    private static function generate(string $q, array $history, array $ctx, array $memory = []): ?string
    {
        // Single-turn, page-agnostic, memoryless questions are cacheable.
        $cacheable = !$history && !$memory && ($ctx['data'] ?? '') === '';
        $cacheKey = 'ai-html|' . Lang::current() . '|' . md5($q);
        if ($cacheable) {
            $hit = Cache::get($cacheKey, 3600);
            if (is_string($hit) && $hit !== '') return $hit;
        }

        // CONVERSATION MEMORY → prepend the last discussed entity as data so a
        // pronoun/short follow-up keeps its referent even at the LLM stage.
        $dataBlock = self::todayDataBlock();
        if (!empty($memory['title'])) {
            $dataBlock = 'Last discussed ' . (string)($memory['type'] ?? 'item') . ': '
                . (string)$memory['title'] . "\n" . $dataBlock;
        }

        $turns = [];
        foreach (array_slice($history, -self::MAX_HISTORY) as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = mb_substr(trim(strip_tags((string)($h['content'] ?? ''))), 0, self::MAX_MSG_LEN);
            if ($content !== '') $turns[] = ['role' => $role, 'content' => $content];
        }
        $turns[] = ['role' => 'user', 'content' => self::buildPrompt($q, $ctx, $dataBlock)];

        $raw = self::llm2(self::systemPrompt(), $turns);
        if ($raw === null) return null;
        $html = self::toHtml($raw);
        if ($html === null || trim($html) === '') return null;
        if ($cacheable) Cache::set($cacheKey, $html);
        return $html;
    }

    /** Compact real-data snapshot injected into every prompt. */
    private static function todayDataBlock(): string
    {
        $lines = [];
        foreach (array_slice(Api::matchesByDate(), 0, 12) as $m) {
            $st = match_state($m);
            $lines[] = team_name(team_of($m, 'home')) . ' vs ' . team_name(team_of($m, 'away'))
                . ' | ' . ($m['championship']['title'] ?? '')
                . ' | ' . ($st['key'] === 'upcoming' ? format_ts_time((int)($m['match_timestamp'] ?? 0)) : $st['label'])
                . ($st['started'] ? ' | ' . (int)($m['home_scores'] ?? 0) . '-' . (int)($m['away_scores'] ?? 0) : '')
                . ' | url: ' . match_url($m);
        }
        return $lines ? "Today's matches (" . date('Y-m-d') . "):\n" . implode("\n", $lines) : '';
    }

    /**
     * Provider dispatch. $turns: [{role:'user'|'assistant', content}].
     * Returns raw model text or null (see lastError()).
     */
    public static function llm2(string $system, array $turns, int $maxTokens = 2048): ?string
    {
        $cfg = self::config();
        return $cfg['provider'] === 'gemini'
            ? self::geminiCall($cfg, $system, $turns, $maxTokens)
            : self::openaiCall($cfg, $system, $turns, $maxTokens);
    }

    /** Back-compat shim (admin test used llm(messages)). */
    public static function llm(array $messages, int $maxTokens = 600): ?string
    {
        $system = '';
        $turns = [];
        foreach ($messages as $m) {
            $role = (string)($m['role'] ?? 'user');
            if ($role === 'system') { $system .= ($system ? "\n" : '') . (string)($m['content'] ?? ''); continue; }
            $turns[] = ['role' => $role === 'assistant' ? 'assistant' : 'user', 'content' => (string)($m['content'] ?? '')];
        }
        return self::llm2($system, $turns, $maxTokens);
    }

    private static function geminiCall(array $cfg, string $system, array $turns, int $maxTokens): ?string
    {
        self::$lastError = '';
        if ($cfg['gemini_key'] === '') { self::$lastError = 'missing gemini key'; return null; }
        $model = ltrim($cfg['gemini_model'], '/');
        if (!str_starts_with($model, 'models/')) $model = 'models/' . $model;
        $url = $cfg['gemini_base'] . '/' . $model . ':generateContent?key=' . rawurlencode($cfg['gemini_key']);

        $contents = [];
        foreach ($turns as $t) {
            $contents[] = [
                'role'  => $t['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $t['content']]],
            ];
        }
        $payload = [
            'contents'         => $contents,
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => $maxTokens],
        ];
        if ($system !== '') $payload['system_instruction'] = ['parts' => [['text' => $system]]];

        $body = self::post($url, ['Content-Type: application/json'], $payload);
        if ($body === null) return null;
        $data = json_decode($body, true);
        $text = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= (string)($part['text'] ?? '');
        }
        $text = trim($text);
        if ($text === '') {
            self::$lastError = 'empty completion — ' . mb_substr($body, 0, 220);
            return null;
        }
        return $text;
    }

    private static function openaiCall(array $cfg, string $system, array $turns, int $maxTokens): ?string
    {
        self::$lastError = '';
        if ($cfg['api_key'] === '') { self::$lastError = 'missing api_key'; return null; }
        $messages = [];
        if ($system !== '') $messages[] = ['role' => 'system', 'content' => $system];
        foreach ($turns as $t) $messages[] = ['role' => $t['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $t['content']];
        $body = self::post($cfg['base_url'] . '/chat/completions', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ], ['model' => $cfg['model'], 'messages' => $messages, 'max_tokens' => $maxTokens, 'temperature' => 0.4]);
        if ($body === null) return null;
        $data = json_decode($body, true);
        $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            self::$lastError = 'empty completion — ' . mb_substr($body, 0, 220);
            return null;
        }
        return $text;
    }

    private static function post(string $url, array $headers, array $payload): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $body    = curl_exec($ch);
        $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            self::$lastError = $curlErr !== ''
                ? 'cURL: ' . $curlErr
                : 'HTTP ' . $code . (is_string($body) && $body !== ''
                    ? ' — ' . mb_substr(trim(strip_tags($body)), 0, 220) : '');
            return null;
        }
        return $body;
    }

    /* ==================== HTML RENDERER (model output → safe HTML) ==================== */

    /**
     * Model output → sanitized HTML. Accepts the {"type":"html","html":…}
     * contract (with or without code fences); anything else is treated as
     * text and converted to clean HTML (markdown symbols stripped).
     */
    public static function toHtml(string $raw): ?string
    {
        $raw = trim($raw);
        // Strip code fences the model may wrap around the JSON.
        $raw = preg_replace('/^```[a-z]*\s*|\s*```$/mi', '', $raw) ?? $raw;
        $raw = trim($raw);

        $html = null;
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['html'])) {
            $html = (string)$data['html'];
        } elseif (str_starts_with($raw, '{')) {
            // Malformed JSON — try to pull the html field out with a regex.
            if (preg_match('/"html"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/su', $raw, $m)) {
                $html = json_decode('"' . $m[1] . '"') ?: null;
            }
        }
        if ($html === null) {
            // Plain text / markdown → convert to clean HTML (#no-markdown rule).
            $html = self::textToHtml($raw);
        }
        $html = self::sanitizeHtml((string)$html);
        return trim($html) !== '' ? $html : null;
    }

    /** Markdown-ish text → minimal safe HTML paragraphs. */
    private static function textToHtml(string $text): string
    {
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;   // headings
        $text = str_replace('`', '', $text);
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $para) {
            $para = trim($para);
            if ($para === '') continue;
            $safe = e($para);
            // **bold** → <b>, then drop any leftover stars.
            $safe = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $safe) ?? $safe;
            $safe = str_replace('*', '', $safe);
            $out .= '<p class="ai-p">' . nl2br($safe) . '</p>';
        }
        return $out;
    }

    /**
     * Allowlist HTML sanitizer for model output. Everything not explicitly
     * allowed is stripped: scripts, styles, iframes, event handlers,
     * javascript: URLs, unknown tags/attributes.
     */
    public static function sanitizeHtml(string $html): string
    {
        $html = mb_substr($html, 0, 30000);
        $allowedTags = ['div', 'span', 'p', 'a', 'img', 'b', 'strong', 'i', 'em', 'u', 'small',
                        'br', 'ul', 'ol', 'li', 'h3', 'h4', 'h5', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
        $allowedAttrs = ['class', 'href', 'src', 'alt', 'title', 'dir', 'loading', 'width', 'height', 'target', 'rel'];

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__ai_root__">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('__ai_root__');
        if (!$root) return '';

        $walk = function (\DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs): void {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);
                if ($child instanceof \DOMComment) { $node->removeChild($child); continue; }
                if (!$child instanceof \DOMElement) continue;
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'], true)) {
                    $node->removeChild($child);           // drop tag AND content
                    continue;
                }
                if (!in_array($tag, $allowedTags, true)) {
                    // Unwrap: keep children, drop the tag itself.
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                // Attribute allowlist + URL scheme checks.
                for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                    $attr = $child->attributes->item($a);
                    $an = strtolower($attr->name);
                    $av = trim($attr->value);
                    $bad = !in_array($an, $allowedAttrs, true) || str_starts_with($an, 'on');
                    if (!$bad && ($an === 'href' || $an === 'src')) {
                        $ok = preg_match('#^(https?:)?//#i', $av)
                            || str_starts_with($av, '/')
                            || str_starts_with($av, '#')
                            || ($an === 'href' && (str_starts_with($av, 'mailto:') || str_starts_with($av, 'tel:')));
                        if (!$ok) $bad = true;
                    }
                    if ($bad) $child->removeAttribute($attr->name);
                }
                if (strtolower($child->getAttribute('target')) === '_blank') {
                    $child->setAttribute('rel', 'noopener');
                }
                $walk($child);
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    /* ==================== Copy + suggestions ==================== */

    private static function normalize(string $q): string
    {
        $q = preg_replace('/\s+/u', ' ', trim($q)) ?? $q;
        return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $q);
    }

    private static function t(string $key): string
    {
        $ar = Lang::current() === 'ar';
        $map = [
            'empty'           => [$ar ? 'اكتب سؤالك وسأساعدك فوراً 👋' : 'Type your question and I\'ll help right away 👋'],
            'thinking'        => [$ar ? 'جارٍ التحليل والبحث…' : 'Analyzing and searching…'],
            'today_intro'     => [$ar ? 'إليك أبرز مباريات اليوم على ALOKA Live:' : "Here are today's top matches on ALOKA Live:"],
            'tomorrow_intro'  => [$ar ? 'مباريات الغد على ALOKA Live:' : "Tomorrow's matches on ALOKA Live:"],
            'day_intro'       => [$ar ? 'مباريات يوم' : 'Matches on'],
            'result_intro'    => [$ar ? 'نتيجة المباراة:' : 'Match result:'],
            'schedule_intro'  => [$ar ? 'موعد المباراة القادمة:' : 'Upcoming fixture:'],
            'form_intro'      => [$ar ? 'آخر نتائج' : 'Recent results —'],
            'no_match_found'  => [$ar ? 'لم أجد هذه المباراة في بيانات الموقع (لا في السجل ولا في الجدول القادم). تأكد من أسماء الفريقين أو التاريخ.' : "I couldn't find that match in the site data (past, live, or scheduled). Check the team names or the date."],
            'no_goals'        => [$ar ? 'لا توجد أهداف مسجّلة في أحداث هذه المباراة.' : 'No goals are recorded in this match\'s events.'],
            'no_goals_yet'    => [$ar ? 'لم تبدأ المباراة بعد، ولا توجد أهداف حتى الآن.' : 'The match hasn\'t started yet — no goals so far.'],
            'live_intro'      => [$ar ? 'هذه المباريات الجارية الآن:' : 'These matches are live right now:'],
            'no_live'         => [$ar ? 'لا توجد مباريات مباشرة في هذه اللحظة. تفقد مباريات اليوم من قسم المباريات.' : 'No matches are live at this moment — check today\'s fixtures in the matches section.'],
            'no_matches'      => [$ar ? 'لا توجد مباريات مسجلة في هذا اليوم.' : 'No matches are scheduled for that day.'],
            'match_intro'     => [$ar ? 'وجدت هذه المباراة لك:' : 'I found this match for you:'],
            'movie_intro'     => [$ar ? 'إليك ما وجدته في الأفلام:' : 'Here\'s what I found in movies:'],
            'series_intro'    => [$ar ? 'إليك ما وجدته في المسلسلات:' : 'Here\'s what I found in series:'],
            'cinema_intro'    => [$ar ? 'وجدت هذه النتائج في الأفلام والمسلسلات:' : 'I found these in movies & series:'],
            'news_intro'      => [$ar ? 'أحدث الأخبار المتعلقة بطلبك:' : 'The latest related news:'],
            'channel_intro'   => [$ar ? 'القنوات المتوفرة تجدها داخل صفحات المباريات المباشرة:' : 'Available channels are inside the live match pages:'],
            'entity_intro'    => [$ar ? 'إليك ما وجدته في بيانات الموقع:' : 'Here\'s what I found on the site:'],
            'not_found_movie' => [$ar ? 'لم أجد هذا الفيلم في مكتبة الموقع. جرّب اسماً آخر أو تصفح قسم الأفلام.' : 'I couldn\'t find that movie in the site library. Try another title or browse the movies section.'],
            'not_found_series'=> [$ar ? 'لم أجد هذا المسلسل في مكتبة الموقع. جرّب اسماً آخر أو تصفح قسم المسلسلات.' : 'I couldn\'t find that series. Try another title or browse the series section.'],
            'not_found_news'  => [$ar ? 'لم أجد أخباراً مطابقة الآن. تصفح قسم الأخبار لآخر المستجدات.' : 'No matching news right now. Browse the news section for the latest.'],
        ];
        return $map[$key][0] ?? '';
    }

    /** Contextual quick-suggestion chips. */
    public static function suggestions(string $ctx = ''): array
    {
        $ar = Lang::current() === 'ar';
        $all = [
            'matches' => $ar ? 'مباريات اليوم' : "Today's matches",
            'live'    => $ar ? 'المباريات المباشرة' : 'Live matches',
            'movies'  => $ar ? 'أحدث الأفلام' : 'Latest movies',
            'series'  => $ar ? 'أحدث المسلسلات' : 'Latest series',
            'news'    => $ar ? 'الأخبار الرياضية' : 'Sports news',
            'contact' => $ar ? 'اتصل بنا' : 'Contact us',
        ];
        $q = [
            'matches' => $ar ? 'مباريات اليوم' : 'today matches',
            'live'    => $ar ? 'المباريات المباشرة' : 'live matches',
            'movies'  => $ar ? 'أحدث الأفلام' : 'latest movies',
            'series'  => $ar ? 'أحدث المسلسلات' : 'latest series',
            'news'    => $ar ? 'أخبار' : 'news',
            'contact' => $ar ? 'اتصل بنا' : 'contact',
        ];
        $order = match ($ctx) {
            'matches' => ['live', 'matches', 'news', 'movies'],
            'cinema'  => ['movies', 'series', 'matches', 'live'],
            'news'    => ['news', 'matches', 'live', 'movies'],
            default   => ['matches', 'live', 'movies', 'series', 'news', 'contact'],
        };
        $out = [];
        foreach ($order as $k) $out[] = ['label' => $all[$k], 'q' => $q[$k]];
        return $out;
    }
}
