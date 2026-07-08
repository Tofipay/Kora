# Qamhad Live — API Architecture

## Data flow

```
Browser ──► Qamhad Live (PHP front controller)
                 │
                 ├── storage/cache/api/*.json   (disk cache w/ TTL + stale fallback + 60s negative cache)
                 │
                 ├── api-ar.ysscores.com  /  api-en.ysscores.com    (matches — switched by language)
                 ├── news-ar.ysscores.com /  news-en.ysscores.com   (news — switched by language)
                 │
                 └── imgs.ysscores.com  ──►  /media/* first-party proxy
                                             (disk cache + WebP + immutable headers)
```

The upstream host **never appears in HTML** — all images are rewritten to
first-party `/media/...` URLs and all data is server-rendered.

## Language → API switching

| Language | URL prefix | Matches API | News API |
|---|---|---|---|
| العربية (default) | `/` | `api-ar.ysscores.com/api` | `news-ar.ysscores.com/api` |
| English | `/en` | `api-en.ysscores.com/api` | `news-en.ysscores.com/api` |

Configured in `app/config.php` → `API_BASES`. `Qamhad\Core\Api::base()` picks
the host from `Lang::current()` which is derived from the URL prefix.

## Upstream endpoints used

| Purpose | Endpoint | TTL |
|---|---|---|
| Day fixtures | `GET /matches/matches_date_get/{Y-m-d}/[]/[]/[]/L/180` | 60s today / 5min other days |
| Match info | `GET /matches/match_info/{id}` | 60s |
| Match events | `GET /matches/matches_event/{id}` | 60s |
| Lineups | `GET /matches/matches_lineup/{id}` | 5min |
| Match stats | `GET /matches/statics_match/{id}` | 60s |
| Referees | `GET /matches/referees_match/{id}` | 5min |
| TV channels | `GET /matches/channel_match/{id}` | 5min |
| Standings | `GET /matches/league_standing/{leagueUrlId}` | 60min |
| Top scorers | `GET /matches/league_scorers/{leagueUrlId}` | 60min |
| Top assists | `GET /matches/league_assist/{leagueUrlId}` | 60min |
| Latest news | `GET /News/latest_news?page={n}` | 15min |
| News article | `GET /News/news_detail/{id}` | 15min |
| League news | `GET /News/news_league/{leagueUrlId}?page={n}` | 15min |
| Team profile | `GET /matches/team_info/{id}` (candidates probed) | 60min |
| Team matches | `GET /matches/matches_team/{id}` (candidates probed) | 5min |
| Player profile | `GET /players/player_info/{id}` (candidates probed) | 60min |

### Team / player resolution (no fixture-window dependency)

Team and player detail pages **do not** depend on the entity having a fixture
inside the scanned date window — that was the cause of `/team/arsenal-9825`
returning 404. Resolution is layered:

1. **Endpoint** — `Api::teamInfo()` / `Api::playerInfo()` probe the ysscores
   team/player endpoints. Their exact names are not publicly documented; the
   client tries the most likely candidates (`matches/team_info`,
   `matches/matches_team`, `players/player_info`, …) and uses whichever
   responds. **If your ysscores plan exposes different paths, correct them in
   one place:** the candidate arrays in `app/Core/Api.php`
   (`teamInfo`, `teamMatches`, `playerInfo`).
2. **Registry** — every team and player seen anywhere (fixtures, standings,
   scorers, lineups, events) is recorded in
   `storage/settings/registry_{teams,players}_{lang}.json` by id, so any entity
   seen once is resolvable forever, cross-language.
3. **Window scan** — finally, the ±window fixtures are scanned as before.

A page 404s only when the id is unknown to all three layers.

### Event `type` codes (empirically mapped)

| type | meaning |
|---|---|
| 1 | Goal |
| 2 | Yellow card |
| 3 | Own goal |
| 4 | Penalty goal |
| 5 | Second yellow → red |
| 6 / 7 | Red card |
| 8 | Substitution (assist_player = second player) |
| 21 | Missed penalty |
| 22 | Goal disallowed |
| 100 | Period marker (`status`: 1 kick-off · 2 HT · 3 second half · 4 FT · 5/6 ET · 7/8/13 pens) |

### Match `status` codes

`0` not started · `1` first half · `2` half time · `3` second half · `4` finished
(`live: 1` also marks in-play).

## Internal JSON API (first-party, consumed by the frontend)

| Route | Method | Purpose |
|---|---|---|
| `/api/live-scores` | GET | Compact score/status payload for today's matches — polled every 60s by match cards |
| `/api/match/{id}` | GET | One match: state + score + normalized events |
| `/api/newsletter` | POST `{email}` | Newsletter signup (stored in settings, exportable CSV) |
| `/api/push-subscribe` | POST `{token, topics}` | Register an FCM device token |

## Media proxy

`GET /media/{kind}/{size}/{file}` where:

- `kind` ∈ `teams` · `championship` (logos live at **/128**) · `news` ·
  `player` (photos live at **/64**; /100 is often missing) · `country` (flags)
- `file` must match `[A-Za-z0-9._-]+.(png|jpe?g|gif|webp)`
- allowed sizes and the per-kind fallback are in `app/config.php`
  (`MEDIA_KINDS`, `MEDIA_FALLBACK_SIZE`)

Behaviour: disk cache 7 days → **if the requested size 404s upstream, retry
once at the kind's fallback size** (e.g. player/100 → player/64,
championship/48 → championship/128) → WebP transcode when `Accept: image/webp`
(GD) → `Cache-Control: public, max-age=604800, s-maxage=2592000, immutable` +
`ETag`/304 → placeholder SVG on 404 → **per-file** negative cache for missing
images (a single 404 never suppresses the whole proxy) + 60s global backoff on
transport errors only.

## Streaming (admin-managed, no API)

Per-match stream config lives in `storage/settings/streams.json` (see
`app/Core/Streams.php`). Admin → **Streaming** manages, per match:

- **mode**: `internal` (built-in player at `/watch/{id}`) or `external` (Watch
  button links straight to the URL)
- **servers**: unlimited, each `{name, url, type, order, active}`; types
  `m3u8, mpd, ts, ism, isml, rtsp, mp4, mkv, webm, auto`

The `/watch/{id}` player uses vendored **hls.js** (HLS) and **dash.js** (DASH)
in `public/assets/vendor/`, native playback for MP4/WebM/MKV, instant server
switching, quality/speed/PiP/Chromecast/AirPlay/fullscreen and HLS error
recovery. Only `http(s)` external URLs are ever surfaced.

## Caching layers

1. **Disk API cache** — TTL per data type, stale-served on upstream failure,
   60-second negative cache after a failed fetch.
2. **Media cache** — 7-day disk cache + on-the-fly WebP variant.
3. **HTTP** — immutable static assets (1y), long-lived media, ETag/304.
4. **Service worker** — static cache-first, pages network-first with offline
   fallback, media stale-while-revalidate.
