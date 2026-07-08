# Qamhad Live — YSScores API endpoints (for JSON verification)

> ## ⚠️ Required app headers (anti-bot) — read first
> The API now returns a **"download the official app" placeholder** (fake team
> names such as `حمّل يلا شووت` / `التطبيق الأصلي`) unless the request carries the
> real mobile-app headers. They are sent on **every** upstream call (matches,
> news, player/team scrape, images) and are defined in **one place**:
> `app/config.php → api_headers()` / the `API_APP_VERSION`, `API_APP_VERSIONNAME`,
> `API_TIMEZONE`, `API_USER_AGENT`, `API_APP_PLATFORM` constants.
>
> ```
> user-agent: Dart/3.10 (dart:io)
> app-lang: ar            (follows the active site language)
> app-brightness: light
> timezone: 180
> app-platform: android
> app-versionname: 4.15.0
> app-version: 543
> accept: application/json
> ```
> When the API expires, bump `API_APP_VERSION` / `API_APP_VERSIONNAME` (or set
> the `api_app` admin setting) — nothing else changes.
>
> ## First-party JSON proxy (`/api/*.php`)
> The browser never calls ysscores directly. Server-rendered pages use the
> `Api`/`Www` classes; any client code uses the proxy endpoints below, which
> wrap the same classes (central config, disk cache, retry, stale fallback,
> friendly error). JS helper: `assets/js/api-service.js` →
> `matchesService.getMatches(date)`, `getLiveMatches()`, `getYesterdayMatches()`,
> `getTomorrowMatches()`, plus `newsService`/`playerService`/`teamService`/`standingsService`.
>
> | Endpoint | Params | Returns |
> |---|---|---|
> | `/api/matches.php` | `date=YYYY-MM-DD`, `lang` | all matches for the day |
> | `/api/live.php` | `lang` | matches currently in play |
> | `/api/news.php` | `page` \| `id`, `lang` | news list or article |
> | `/api/player.php` | `id`, `slug`, `lang` | full player profile |
> | `/api/team.php` | `id`, `lang` | fixtures/results + squad |
> | `/api/standings.php` | `league`, `lang` | table + scorers |
>
> Envelope: `{ ok, stale, lang, count, data }`. Cache: `60s` live / `600s` other;
> snapshots at `storage/cache/api/matches_{lang}_{date}.json`.

---

**Bases**
- Matches: `https://api-ar.ysscores.com/api` (ar) · `https://api-en.ysscores.com/api` (en)
- News:    `https://news-ar.ysscores.com/api` (ar) · `https://news-en.ysscores.com/api` (en)
- Images:  `https://imgs.ysscores.com`

**Example IDs:** match `4667827` · leagues `894789`,`900326`,`903294` · teams `5922`,`102043` · players `1334889`,`1382000`

Legend: ✅ CONFIRMED (already used by the original site, shape known) ·
❓ VERIFY (candidate — open each, tell me which returns real JSON).
Everything returns `application/json` shaped `{status, status_code, message, data}`.

---

## 1. Homepage

| What | Status | URL pattern | Example |
|---|---|---|---|
| Today's / any-day matches — ALL competitions | ✅ | `/matches/matches_date_get/{YYYY-MM-DD}/[ids…]/[]/[]/L/180` | `…/api/matches/matches_date_get/2026-07-04/["560100","901130",…]/[]/[]/L/180` |
| Today's / any-day matches — followed only | ✅ | `/matches/matches_date_get/{YYYY-MM-DD}/[]/[]/[]/L/180` | `…/api/matches/matches_date_get/2026-07-03/[]/[]/[]/L/180` |
| Featured leagues | ✅ (derived) | *no dedicated call — leagues are discovered from the day-matches `championship` object* | — |
| News (homepage) | ✅ | `/News/latest_news?page={n}` | `https://news-ar.ysscores.com/api/News/latest_news?page=1` |

> The `180` = timezone offset in minutes (GMT+3). The brackets after the date are the **championship-id / country / sport** filters.
>
> **Coverage fix (verified):** leaving the FIRST bracket empty (`[]/…/L`) returns **followed competitions only** — every league in an empty-bracket response has `followed=1`, which is why "only the World Cup showed". Passing the explicit tracked-championship id list in the first bracket (`["560100",…]`) returns EVERY one of those competitions, including non-followed leagues (`followed=0`, e.g. the Swedish league). `Api::matchesByDate()` sends the id list (from `MATCHES_FOLLOW_IDS`) as the primary call and unions the followed/all modes on top, deduped by `match_id`.
>
> **Score breakdown fields** ride on `matches_event` (not `match_info`): `fh_scores` (1st half), `sh_scores` (2nd half), `fe_scores` / `se_scores` (extra-time halves), `match_penalties` (penalty shootout). Each is a two-entry list `[{home_team,home_scores},{away_team,away_scores}]`. Parsed by `match_periods()` and surfaced in the match hero + Score Breakdown card.

## 2. Match

| What | Status | URL pattern | Example |
|---|---|---|---|
| Match details/info (incl. live score, status, ht_time) | ✅ | `/matches/match_info/{id}` | `…/matches/match_info/4667827` |
| Match events (goals/cards/subs/VAR) | ✅ | `/matches/matches_event/{id}` | `…/matches/matches_event/4667827` |
| Match statistics | ✅ | `/matches/statics_match/{id}` | `…/matches/statics_match/4667827` |
| Match lineup / formation | ✅ | `/matches/matches_lineup/{id}` | `…/matches/matches_lineup/4667827` |
| Match referees | ✅ | `/matches/referees_match/{id}` | `…/matches/referees_match/4667827` |
| Match channels + commentators | ✅ | `/matches/channel_match/{id}` | `…/matches/channel_match/4667827` |
| Match standings / scorers | ✅ (via league) | use §3 league standings & scorers for the match's `championship.url_id` | — |
| Live data | ✅ | same as `match_info` (poll it) | — |

## 3. League

| What | Status | URL pattern | Example |
|---|---|---|---|
| League standings (table) | ✅ | `/matches/league_standing/{url_id}` | `…/matches/league_standing/894789` |
| League top scorers | ✅ | `/matches/league_scorers/{url_id}` | `…/matches/league_scorers/894789` |
| League top assists | ✅ | `/matches/league_assist/{url_id}` | `…/matches/league_assist/894789` |
| League news | ✅ | `/News/news_league/{url_id}?page={n}` | `https://news-ar.ysscores.com/api/News/news_league/894789?page=1` |
| League info | ❓ | `/matches/championship_info/{url_id}` **(guess — try it)** | `…/matches/championship_info/894789` |
| League fixtures / results | ❓ | is there a dedicated one? try `/matches/matches_championship/{url_id}` | `…/matches/matches_championship/894789` |
| League teams | ❓ | try `/matches/championship_teams/{url_id}` | `…/matches/championship_teams/894789` |

> Fixtures/results/teams are currently derived by scanning `matches_date_get`. **If a dedicated league-matches or league-teams endpoint exists, send me its URL + JSON** and I'll switch to it.

## 4. Team — ✅ ALL VERIFIED against real captures (v5)

| What | Status | URL | Notes |
|---|---|---|---|
| Team matches (old + new) | ✅ | `/matches/team_matches/{id}` | buckets `online/coming/end/postponed/cancel`, each `{data:[full match objects], next_page}` |
| Team squad + coach | ✅ | `https://www.ysscores.com/{ar\|en}/get_players_team?team={id}` | `{G,D,M,F,coach}`; player: row_id/title/player_number/image(full CDN url /player/48)/position(localized)/link |
| Team news | ✅ | `/News/news_team/{id}?page=1` | standard news items |
| Team standings | ✅ | `/matches/league_standing/{league url_id}` | highlight the team row |
| Team info endpoint | ✖ 404 | `team_info` variants don't exist — identity derived from team_matches / registry | — |

> **This is the most important group.** Open each candidate for a real team id (e.g. Al‑Nassr — find its id in your team URL `/team/…-<id>`), tell me **which one returns players**, and paste that JSON. `{id}` is the team `row_id` seen in match `home_team_info.row_id` / standings `team_id`.

## 5. Player

| What | Status | Candidate URL patterns (try each) | Example |
|---|---|---|---|
| Player information | ✖ 404 (all player_info variants) — vitals scraped from `www.ysscores.com/{lang}/player/{id}/…` page (Www::playerProfile, cached 7d) | — | — |
| Player statistics | ❓ | usually inside `player_info` (a `statics`/`stats` array) — confirm | — |
| Player matches | ❓ | try `/players/player_matches/{id}` or `/matches/matches_player/{id}` | `…/players/player_matches/1334889` |
| Player career | ❓ | try `/players/player_career/{id}` | `…/players/player_career/1334889` |
| Player transfers | ❓ | try `/players/player_transfers/{id}` | `…/players/player_transfers/1334889` |

> `{id}` is the player id from scorers `player_info.id` or event `player_name.id`. **Send me `player_info` JSON for one player** so I can lock the exact field names for position / age / height / weight / nationality flag / stats.

## 6. News

| What | Status | URL pattern | Example |
|---|---|---|---|
| News list (paginated) | ✅ | `/News/latest_news?page={n}` | `https://news-ar.ysscores.com/api/News/latest_news?page=1` |
| News details / article | ✅ | `/News/news_detail/{id}` | `https://news-ar.ysscores.com/api/News/news_detail/13990410` |
| Featured / trending news | ❓ | try `/News/featured_news` or `/News/important_news` | `https://news-ar.ysscores.com/api/News/featured_news` |
| News comments | ✅ (available) | `/comments/get_comment_news/{id}/0?page={n}` | `…/api/comments/get_comment_news/13990410/0?page=1` |

## 7. Search — *(not implemented as an API call yet; I search cached data)*

| What | Status | Candidate URL patterns (try each) | Example |
|---|---|---|---|
| Global search | ✅ | `https://api-{lang}.ysscores.com/api/search/{query}` | returns `data.player[]` + `data.teams[]` (team includes country {code, image} for flags) |

> If a real search endpoint exists, send its URL + JSON and I'll wire live server-side search (currently search runs over cached fixtures/news).

## 8. Media (images) — direct CDN, no JSON

`{file}` = the `image` field from any JSON object (e.g. `9901763117716.png`).

| Asset | URL pattern | Working size | Example |
|---|---|---|---|
| Team logo | `https://imgs.ysscores.com/teams/{size}/{file}` | 64, 128 | `…/teams/128/9901763117716.png` |
| League logo | `https://imgs.ysscores.com/championship/{size}/{file}` | **128** (confirmed) | `…/championship/128/3411694791422.png` |
| Player photo | `https://imgs.ysscores.com/player/{size}/{file}` | **64** (confirmed) | `…/player/64/9721758467326.png` |
| News image | `https://imgs.ysscores.com/news/{size}/{file}` | 150, 640 | `…/news/640/17829893743874.jpg` |
| Country flag | `https://imgs.ysscores.com/country/{size}/{file}` ❓ | verify path/size | `…/country/64/<file>` |

> **Please confirm the exact country-flag path** (is it `/country/…`, `/flags/…`, or a `country_image` field in `player_info`?). Also tell me which sizes 404 vs work for teams/championship/player.

## 9. Sitemaps — *first-party, not upstream*

`/sitemap.xml`, `/sitemap-ar.xml`, `/sitemap-en.xml`, `/sitemap-news.xml`, `/sitemap-images.xml`
are generated on OUR server FROM the endpoints above (matches_date_get, allLeagues,
latest_news). No separate upstream call. Nothing to verify here.

---

## What I need back (priority order)

1. **`match_info/4667827`** — confirm current live field names (`ht_time`, `status`, `minutes`).
2. **team squad** — which candidate in §4 returns players, + the JSON.
3. **`player_info`** — the JSON for one player (§5).
4. **team info + team matches + team news** — which candidates work (§4).
5. **`matches_date_get` for a busy day** — confirm all leagues present.
6. Anything for §5 career/transfers, §7 search, §3 league-matches if those endpoints exist.

Paste each raw JSON (even truncated to the first item of any list) and I'll map every field exactly.
