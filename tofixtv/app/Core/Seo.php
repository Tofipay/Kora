<?php
declare(strict_types=1);

namespace TofiXTv\Core;

/**
 * SEO context for the current page: meta, Open Graph, Twitter cards,
 * canonical/hreflang and JSON-LD structured data.
 */
final class Seo
{
    /**
     * Build stamp — printed as an HTML comment next to the JSON-LD so you can
     * confirm which code the live server is actually running (view-source →
     * search "qseo-build"). If Search Console shows old schema but this stamp
     * is the new one, the issue is Google's cache, not your deploy.
     */
    public const BUILD = '2026-07-23-aloka-v1';

    public string $title;
    public string $description;
    public string $image;
    public string $canonical;
    public string $type = 'website';
    public array  $jsonLd = [];
    public array  $breadcrumbs = []; // [ [name, url], ... ]

    public function __construct()
    {
        $custom = Settings::get('seo', []);
        $name = Lang::siteName();
        $sloganKey = Lang::current() === 'ar' ? 'title_ar' : 'title_en';
        $descKey   = Lang::current() === 'ar' ? 'desc_ar' : 'desc_en';

        $this->title = !empty($custom[$sloganKey])
            ? (string)$custom[$sloganKey]
            : $name . ' — ' . Lang::siteSlogan();
        $this->description = !empty($custom[$descKey])
            ? (string)$custom[$descKey]
            : Lang::siteSlogan();
        $this->image = SITE_URL . '/assets/brand/og-default.png';
        $this->canonical = SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    public function title(string $t): self
    {
        $this->title = $t . ' | ' . Lang::siteName();
        return $this;
    }

    public function rawTitle(string $t): self { $this->title = $t; return $this; }

    public function description(string $d): self
    {
        $this->description = mb_substr(trim($d), 0, 300);
        return $this;
    }

    public function image(string $url): self
    {
        $this->image = str_starts_with($url, 'http') ? $url : SITE_URL . $url;
        return $this;
    }

    public function canonical(string $pathOrUrl): self
    {
        $this->canonical = absolute_url($pathOrUrl);
        return $this;
    }

    public function type(string $t): self { $this->type = $t; return $this; }

    public function addJsonLd(array $schema): self
    {
        $this->jsonLd[] = $schema;
        return $this;
    }

    /**
     * Encode one schema block to a validated <script type="application/ld+json">
     * tag. Recursively drops null/empty-array values, then validates with
     * JSON_THROW_ON_ERROR. On any encoding failure it logs the reason and
     * returns '' — so a malformed block is skipped, never emitted broken
     * (a broken block would invalidate the whole page's structured data).
     */
    public static function renderJsonLd(array $schema): string
    {
        $clean = self::pruneEmpty($schema);
        try {
            $json = json_encode(
                $clean,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            self::logSchemaError(($schema['@type'] ?? 'unknown') . ': ' . $e->getMessage());
            return '';
        }
        if ($json === '' || $json === 'null' || $json === '[]') return '';
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /** Recursively remove null values and empty arrays so no dangling keys ship. */
    private static function pruneEmpty(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if ($v === null) continue;
            if (is_array($v)) {
                $v = self::pruneEmpty($v);
                if ($v === []) continue;
            } elseif ($v === '') {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    private static function logSchemaError(string $msg): void
    {
        @file_put_contents(
            SETTINGS_DIR . '/seo.log',
            '[' . date('c') . '] JSON-LD ' . $msg . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /** @param array $crumbs [ [name, absolute-or-relative-url], ... ] */
    public function breadcrumbs(array $crumbs): self
    {
        $this->breadcrumbs = $crumbs;
        $items = [];
        foreach ($crumbs as $i => [$name, $u]) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $name,
                'item' => absolute_url($u),
            ];
        }
        return $this->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    public function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name'  => Lang::siteName(),
            'alternateName' => Lang::current() === 'ar' ? SITE_NAME_EN : SITE_NAME_AR,
            'url'   => SITE_URL,
            'logo'  => SITE_URL . '/assets/brand/icon-512.png',
            'sameAs'=> [],
        ];
    }

    public function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => Lang::siteName(),
            'url'  => SITE_URL . (Lang::prefix() ?: '/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => SITE_URL . Lang::prefix() . '/search?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * SportsEvent JSON-LD built purely from the current match payload.
     * Optional $channels ([{channel_name, commentator_name}, …]) enrich the
     * schema with broadcastOfEvent / BroadcastService entries.
     */
    public static function sportsEventSchema(array $m, array $channels = []): array
    {
        $home = team_of($m, 'home');
        $away = team_of($m, 'away');
        $state = match_state($m);
        // Event status per schema.org — only Postponed/Cancelled deviate; we
        // have no such signal, so scheduled covers upcoming/live/finished.
        $status = 'https://schema.org/EventScheduled';
        $start = (int)($m['match_timestamp'] ?? 0);
        if (!$start) $start = to_ts($m['match_date'] ?? '');
        $end = (int)($m['match_end_timestamp'] ?? 0);

        $homeName = team_name($home);
        $awayName = team_name($away);
        $league = trim((string)($m['championship']['title'] ?? ''));
        $stadium = trim((string)($m['Stadium'] ?? ''));

        $matchUrl = absolute_url(match_url($m));

        $homeTeam = ['@type' => 'SportsTeam', 'name' => $homeName];
        $awayTeam = ['@type' => 'SportsTeam', 'name' => $awayName];
        $hLogo = team_img($home); $aLogo = team_img($away);
        if ($hLogo) $homeTeam['logo'] = absolute_url($hLogo);
        if ($aLogo) $awayTeam['logo'] = absolute_url($aLogo);

        // Google recommends endDate; football runs ~2h — derive when the feed
        // has no explicit end so the field is always present and reasonable.
        if (!$end && $start) $end = $start + 7200;

        $startISO = $start ? date('c', $start) : (string)($m['match_date'] ?? '');
        $endISO   = $end   ? date('c', $end)   : $startISO;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => $homeName . ' ' . t('match.vs') . ' ' . $awayName,
            'url' => $matchUrl,
            'description' => self::eventDescription($homeName, $awayName, $league, $state, (int)($m['home_scores'] ?? 0), (int)($m['away_scores'] ?? 0), $startISO),
            'sport' => 'Soccer',
            'eventStatus' => $status,
            'startDate' => $startISO,
            'endDate' => $endISO,
            'homeTeam' => $homeTeam,
            'awayTeam' => $awayTeam,
            'competitor' => [$homeTeam, $awayTeam],
            // performer: GSC flags this for events — the two competing teams.
            'performer' => [$homeTeam, $awayTeam],
            // image: required by Google; large branded OG first, then real logos.
            'image' => self::eventImages($m, $home, $away),
        ];

        // Venue. Physical match → Offline + Place(address); otherwise Online.
        if ($stadium) {
            $schema['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
            $schema['location'] = [
                '@type' => 'Place',
                'name' => $stadium,
                'address' => $league !== '' ? ($stadium . '، ' . $league) : $stadium,
            ];
        } else {
            $schema['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
            $schema['location'] = ['@type' => 'VirtualLocation', 'url' => $matchUrl];
        }

        if ($league) {
            // NOTE: no `superEvent`. A nested SportsEvent with only a name makes
            // Google validate it as a second, incomplete event (missing
            // startDate/location). The league is fully conveyed by `organizer`
            // (a SportsOrganization — not an Event, so no event fields required).
            $org = ['@type' => 'SportsOrganization', 'name' => $league];
            $lgUrl = league_url($m['championship'] ?? []);
            if ($lgUrl) $org['url'] = absolute_url($lgUrl);
            $schema['organizer'] = $org;
        }

        // offers: how to watch — free live stream. Clears the missing "offers".
        $schema['offers'] = [
            '@type' => 'Offer',
            'url' => $matchUrl,
            'price' => '0',
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'category' => 'free',
            'validFrom' => $start ? date('c', $start) : (string)($m['match_date'] ?? ''),
        ];

        // Broadcast services (channels) — helps "القنوات الناقلة" intent.
        $broadcasts = [];
        foreach ($channels as $c) {
            $cn = trim((string)($c['channel_name'] ?? ''));
            if ($cn === '') continue;
            $broadcasts[$cn] = ['@type' => 'BroadcastService', 'name' => $cn];
        }
        if ($broadcasts) {
            $schema['broadcastOfEvent'] = array_values($broadcasts);
        }

        return $schema;
    }

    /** Human, status-aware description — always present so the field never misses. */
    private static function eventDescription(string $home, string $away, string $league, array $state, int $hs, int $as, string $startISO): string
    {
        $key = $state['key'] ?? 'upcoming';
        $lg = $league !== '' ? " — {$league}" : '';
        return match ($key) {
            'live'     => "مباراة {$home} و{$away} بث مباشر الآن، النتيجة {$hs} - {$as}{$lg}.",
            'finished' => "انتهت مباراة {$home} و{$away} بنتيجة {$hs} - {$as}{$lg}.",
            default    => "موعد مباراة {$home} و{$away} والقنوات الناقلة والبث المباشر{$lg}.",
        };
    }

    /** Absolute image URLs for a match event (large OG first, then real logos). */
    private static function eventImages(array $m, array $home, array $away): array
    {
        $imgs = [SITE_URL . '/assets/brand/og-default.png']; // 1200×630 guaranteed
        foreach ([$m['championship'] ?? null, $home, $away] as $src) {
            if (!is_array($src)) continue;
            $file = $src['image'] ?? $src['logo'] ?? null;
            if (!$file) continue;
            $u = is_array($m['championship'] ?? null) && $src === ($m['championship'] ?? null)
                ? league_img($src, '128') : team_img($src, '128');
            $abs = absolute_url($u);
            if (!in_array($abs, $imgs, true)) $imgs[] = $abs;
        }
        return $imgs;
    }

    /**
     * FAQPage JSON-LD from [ ['q'=>…, 'a'=>…], … ]. Returns [] when empty so
     * the caller can skip adding an empty schema.
     */
    public static function matchFaqSchema(array $faq): array
    {
        $items = [];
        foreach ($faq as $qa) {
            $q = trim((string)($qa['q'] ?? ''));
            $a = trim((string)($qa['a'] ?? ''));
            if ($q === '' || $a === '') continue;
            $items[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        if (!$items) return [];
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    /**
     * ItemList of match pages for the homepage / listing pages — Google's
     * "summary page" carousel form: each ListItem carries ONLY position + url.
     *
     * Deliberately NO nested SportsEvent entities here. Google validates every
     * typed entity inside an ItemList as a standalone event (location must be
     * a Place, image/offers/organizer/performer/description expected) — data
     * the day-list feed cannot provide, so each entry surfaced in Search
     * Console as an "invalid item". The full, valid SportsEvent lives on each
     * match page itself; this list only signals coverage + discovery.
     * $matches is the raw API list; capped for size.
     */
    public static function matchListSchema(array $matches, string $name, string $listUrl, int $limit = 30): array
    {
        $items = [];
        $pos = 0;
        foreach ($matches as $m) {
            if ($pos >= $limit) break;
            if (!is_array($m) || empty($m['match_id'])) continue;
            $pos++;
            $items[] = [
                '@type' => 'ListItem',
                'position' => $pos,
                'url' => absolute_url(match_url($m)),
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'url' => absolute_url($listUrl),
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ];
    }

    /**
     * Movie JSON-LD from a TMDB detail payload (Cinema section).
     */
    public static function movieSchema(array $m, string $url): array
    {
        $genres = array_values(array_filter(array_map(
            fn($g) => (string)($g['name'] ?? ''), (array)($m['genres'] ?? [])
        )));
        $directors = [];
        foreach ((array)($m['credits']['crew'] ?? []) as $c) {
            if (($c['job'] ?? '') === 'Director' && !empty($c['name'])) {
                $directors[] = ['@type' => 'Person', 'name' => (string)$c['name']];
            }
        }
        $actors = [];
        foreach (array_slice((array)($m['credits']['cast'] ?? []), 0, 8) as $c) {
            if (!empty($c['name'])) $actors[] = ['@type' => 'Person', 'name' => (string)$c['name']];
        }
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'Movie',
            'name'       => (string)($m['title'] ?? ''),
            'url'        => $url,
            'description'=> excerpt((string)($m['overview'] ?? ''), 300),
            'image'      => !empty($m['poster_path']) ? tmdb_poster($m['poster_path'], 'w500') : null,
            'datePublished' => (string)($m['release_date'] ?? '') ?: null,
            'genre'      => $genres ?: null,
            'director'   => $directors ?: null,
            'actor'      => $actors ?: null,
            'inLanguage' => (string)($m['original_language'] ?? '') ?: null,
        ];
        if (!empty($m['runtime'])) $schema['duration'] = 'PT' . (int)$m['runtime'] . 'M';
        if (!empty($m['vote_average']) && !empty($m['vote_count'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float)$m['vote_average'], 1),
                'bestRating'  => 10,
                'ratingCount' => (int)$m['vote_count'],
            ];
        }
        return $schema;
    }

    /**
     * TVSeries JSON-LD from a TMDB detail payload (Cinema section).
     */
    public static function tvSeriesSchema(array $tv, string $url): array
    {
        $genres = array_values(array_filter(array_map(
            fn($g) => (string)($g['name'] ?? ''), (array)($tv['genres'] ?? [])
        )));
        $actors = [];
        foreach (array_slice((array)($tv['credits']['cast'] ?? []), 0, 8) as $c) {
            if (!empty($c['name'])) $actors[] = ['@type' => 'Person', 'name' => (string)$c['name']];
        }
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'TVSeries',
            'name'       => (string)($tv['name'] ?? ''),
            'url'        => $url,
            'description'=> excerpt((string)($tv['overview'] ?? ''), 300),
            'image'      => !empty($tv['poster_path']) ? tmdb_poster($tv['poster_path'], 'w500') : null,
            'startDate'  => (string)($tv['first_air_date'] ?? '') ?: null,
            'numberOfSeasons'  => (int)($tv['number_of_seasons'] ?? 0) ?: null,
            'numberOfEpisodes' => (int)($tv['number_of_episodes'] ?? 0) ?: null,
            'genre'      => $genres ?: null,
            'actor'      => $actors ?: null,
            'inLanguage' => (string)($tv['original_language'] ?? '') ?: null,
        ];
        if (!empty($tv['vote_average']) && !empty($tv['vote_count'])) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float)$tv['vote_average'], 1),
                'bestRating'  => 10,
                'ratingCount' => (int)$tv['vote_count'],
            ];
        }
        return $schema;
    }

    /**
     * ItemList for a cinema hub page — position + url only, same Google-safe
     * form as matchListSchema (full typed entities live on the detail pages).
     */
    public static function cinemaListSchema(array $items, string $type, string $name, string $listUrl, int $limit = 20): array
    {
        $els = [];
        $pos = 0;
        foreach ($items as $it) {
            if ($pos >= $limit) break;
            if (!is_array($it) || empty($it['id'])) continue;
            $pos++;
            $els[] = [
                '@type' => 'ListItem',
                'position' => $pos,
                'url' => absolute_url($type === 'tv' ? series_url($it) : movie_url($it)),
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'url'  => absolute_url($listUrl),
            'numberOfItems' => count($els),
            'itemListElement' => $els,
        ];
    }

    /**
     * NewsArticle JSON-LD (NewsArticle is the schema.org subtype of Article —
     * Google's Article rich result and Discover accept both; both types are
     * declared explicitly). Image list leads with the 1200px render: Discover
     * requires large (1200px+) images, paired with the global
     * max-image-preview:large robots hint in the layout head.
     */
    public static function articleSchema(array $n, string $url): array
    {
        $published = date('c', to_ts($n['created_at'] ?? null) ?: time());
        $modified  = date('c', to_ts($n['updated_at'] ?? $n['created_at'] ?? null) ?: time());
        return [
            '@context' => 'https://schema.org',
            '@type' => ['NewsArticle', 'Article'],
            'headline' => mb_substr((string)($n['title'] ?? ''), 0, 110),
            'description' => excerpt((string)($n['news_desc'] ?? ''), 200),
            'image' => [
                absolute_url(news_img($n, '1200')),
                absolute_url(news_img($n, '640')),
            ],
            'inLanguage' => Lang::current(),
            'isAccessibleForFree' => true,
            'datePublished' => $published,
            'dateModified'  => $modified,
            'author' => ['@type' => 'Person', 'name' => news_author($n)],
            'publisher' => [
                '@type' => 'Organization',
                'name' => Lang::siteName(),
                'logo' => ['@type' => 'ImageObject', 'url' => SITE_URL . '/assets/brand/icon-512.png'],
            ],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];
    }
}
