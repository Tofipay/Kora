package com.tofixtv.app.data.model

import com.google.gson.JsonDeserializationContext
import com.google.gson.JsonDeserializer
import com.google.gson.JsonElement
import com.google.gson.annotations.JsonAdapter
import com.google.gson.annotations.SerializedName
import java.lang.reflect.Type

/**
 * A date that the API returns EITHER as a plain string
 * ("2026-07-12T05:29:00+03:00") OR as an object
 * ({"date":"2026-07-14 01:43:53.000000","timezone_type":3,"timezone":"..."}).
 * This adapter accepts both so news/videos never fail to parse.
 */
@JsonAdapter(ApiDate.Adapter::class)
data class ApiDate(val raw: String? = null) {
    class Adapter : JsonDeserializer<ApiDate> {
        override fun deserialize(json: JsonElement?, t: Type?, c: JsonDeserializationContext?): ApiDate {
            if (json == null || json.isJsonNull) return ApiDate(null)
            return when {
                json.isJsonPrimitive -> ApiDate(json.asString)
                json.isJsonObject -> ApiDate(json.asJsonObject.get("date")?.takeIf { !it.isJsonNull }?.asString)
                else -> ApiDate(null)
            }
        }
    }
}

/**
 * In the day-fixtures payload `events` is an integer flag (0/1); in match_info
 * it is a real array. This adapter yields an empty list unless it is an array,
 * so parsing the matches list never throws (that was the "no matches" bug).
 */
class EventsAdapter : JsonDeserializer<List<MatchEvent>> {
    override fun deserialize(json: JsonElement?, t: Type?, c: JsonDeserializationContext): List<MatchEvent> {
        if (json == null || !json.isJsonArray) return emptyList()
        return json.asJsonArray.mapNotNull {
            runCatching { c.deserialize<MatchEvent>(it, MatchEvent::class.java) }.getOrNull()
        }
    }
}

/** Standard first-party API envelope: { ok, stale, lang, count, data }. */
data class Envelope<T>(
    val ok: Boolean = false,
    val stale: Boolean = false,
    val lang: String? = null,
    val count: Int? = null,
    val error: String? = null,
    val data: T? = null
)

/** A team / club as returned across match, standings and squad payloads. */
data class Team(
    @SerializedName("row_id") val rowId: Long? = null,
    @SerializedName("team_id") val teamId: Long? = null,
    val id: Long? = null,
    val title: String? = null,
    @SerializedName("full_title") val fullTitle: String? = null,
    @SerializedName("short_title") val shortTitle: String? = null,
    val name: String? = null,
    val image: String? = null
) {
    val identifier: Long get() = rowId ?: teamId ?: id ?: 0L
    val displayName: String
        get() = title ?: fullTitle ?: name ?: shortTitle ?: "—"
}

/** A competition / championship. */
data class League(
    @SerializedName("url_id") val urlId: Long? = null,
    val id: Long? = null,
    val title: String? = null,
    val image: String? = null,
    val followed: Int? = null,
    val ranking: Int? = null,
    @SerializedName("country_name") val countryName: String? = null
) {
    val identifier: Long get() = urlId ?: id ?: 0L
    val displayName: String get() = title ?: "—"
}

/** A single fixture / match. */
data class Match(
    @SerializedName("match_id") val matchId: Long? = null,
    val id: Long? = null,
    @SerializedName("home_scores") val homeScores: Int? = null,
    @SerializedName("away_scores") val awayScores: Int? = null,
    val status: Int? = null,
    val live: Int? = null,
    @SerializedName("ht_time") val htTime: Long? = null,
    val minutes: Int? = null,
    @SerializedName("match_time") val matchTime: String? = null,
    @SerializedName("match_date") val matchDate: String? = null,
    @SerializedName("match_timestamp") val matchTimestamp: Long? = null,
    @SerializedName("Stadium") val stadium: String? = null,
    val round: String? = null,
    val championship: League? = null,
    @SerializedName("home_team_info") val homeTeamInfo: Team? = null,
    @SerializedName("home_team") val homeTeamAlt: Team? = null,
    @SerializedName("away_team_info") val awayTeamInfo: Team? = null,
    @SerializedName("away_team") val awayTeamAlt: Team? = null,
    @JsonAdapter(EventsAdapter::class) val events: List<MatchEvent>? = null
) {
    val identifier: Long get() = matchId ?: id ?: 0L
    val homeTeam: Team get() = homeTeamInfo ?: homeTeamAlt ?: Team()
    val awayTeam: Team get() = awayTeamInfo ?: awayTeamAlt ?: Team()
}

data class MatchEvent(
    val type: Int? = null,
    val status: Int? = null,
    @SerializedName("time_minute") val minute: Int? = null,
    @SerializedName("time_plus") val plus: Int? = null,
    @SerializedName("team_id") val teamId: Long? = null,
    @SerializedName("player_name") val player: Team? = null,
    @SerializedName("assist_player_name") val assist: Team? = null
)

/** A standings table row. `team_name` is an object (name + logo). */
data class StandingRow(
    @SerializedName("team_id") val teamId: Long? = null,
    @SerializedName("team_name") val team: Team? = null,
    val points: Int? = null,
    val play: Int? = null,
    val wins: Int? = null,
    val draw: Int? = null,
    val lose: Int? = null,
    @SerializedName("for") val forGoals: Int? = null,
    val against: Int? = null,
    val diff: Int? = null,
    val color: String? = null
)

/** A top-scorer / assist row. */
data class Scorer(
    @SerializedName("player_id") val playerId: Long? = null,
    @SerializedName("player_info") val player: PlayerInfo? = null,
    val goals: Int? = null,
    val assists: Int? = null,
    val assist: Int? = null,
    @SerializedName("score_penalty") val penalties: Int? = null
)

data class PlayerInfo(
    val id: Long? = null,
    val title: String? = null,
    @SerializedName("short_title") val shortTitle: String? = null,
    @SerializedName("full_title") val fullTitle: String? = null,
    val image: String? = null,
    @SerializedName("team_name") val teamName: String? = null,
    val position: String? = null,
    val age: String? = null,
    val nationality: String? = null
) {
    val displayName: String get() = title ?: fullTitle ?: shortTitle ?: "—"
}

/** Standings endpoint payload: { standings:[], scorers:[] }. */
data class StandingsData(
    val standings: List<StandingRow>? = null,
    val scorers: List<Scorer>? = null
)

/** Team endpoint payload. */
data class TeamData(
    val team: Team? = null,
    val league: League? = null,
    val fixtures: List<Match>? = null,
    val results: List<Match>? = null,
    val squad: List<PlayerInfo>? = null
)

/** A news article. */
data class NewsItem(
    val id: Long? = null,
    val title: String? = null,
    val slug: String? = null,
    val image: String? = null,
    @SerializedName("news_desc") val desc: String? = null,
    @SerializedName("news_text") val body: String? = null,
    @SerializedName("created_at") val createdAt: ApiDate? = null,
    val league: League? = null
)

/** News list payload: the API wraps it as { items, has_next, page }. */
data class NewsData(
    val items: List<NewsItem>? = null,
    @SerializedName("has_next") val hasNext: Boolean = false,
    val page: Int = 1
)

/** A highlight video (Btolat feed). */
data class Video(
    val id: Long? = null,
    val title: String? = null,
    val thumbnail: String? = null,
    @SerializedName("champ_title") val champTitle: String? = null,
    @SerializedName("created_at") val createdAt: ApiDate? = null,
    @SerializedName("youtube_id") val youtubeId: String? = null,
    @SerializedName("media_url") val mediaUrl: String? = null,
    @SerializedName("tweet_id") val tweetId: String? = null,
    @SerializedName("embed_iframe") val embedIframe: String? = null,
    @SerializedName("video_url") val videoUrl: String? = null,
    @SerializedName("video_type") val videoType: String? = null
)

/** A video category/championship filter tab. */
data class VideoCategory(val id: String? = null, val title: String? = null)

data class VideosData(
    val items: List<Video>? = null,
    @SerializedName("has_next") val hasNext: Boolean = false,
    val page: Int = 1,
    val categories: List<VideoCategory>? = null
)

/** A resolved, ready-to-play stream source (channels). */
data class StreamSource(
    val name: String? = null,
    val url: String? = null,
    val type: String? = null
)

data class ResolveData(
    val sources: List<StreamSource>? = null
)

/** A TV channel with playable stream URLs. */
data class Channel(
    val name: String? = null,
    val urls: List<String>? = null,
    val logo: String? = null
)

/** Global search result. */
data class SearchData(
    val player: List<PlayerInfo>? = null,
    val teams: List<Team>? = null
)

/* ---------- Match Center (full detail) ---------- */

/** Full match-detail payload — mirrors /api/match_full. */
data class MatchFull(
    val match: Match? = null,
    val live: Boolean = false,
    val status: String? = null,
    val events: List<FullEvent>? = null,
    val lineups: Lineups? = null,
    val stats: List<StatRow>? = null,
    val channels: List<MatchChannel>? = null,
    val standings: List<StandingRow>? = null,
    val scorers: List<Scorer>? = null
)

/** A normalized timeline event (side = "home"|"away", key = goal/yellow/…). */
data class FullEvent(
    val minute: String? = null,
    val side: String? = null,
    val key: String? = null,
    val label: String? = null,
    val player: String? = null,
    val assist: String? = null
) {
    val isHome: Boolean get() = side == "home"
}

data class Lineups(
    val home: LineupSide? = null,
    val away: LineupSide? = null
)

data class LineupSide(
    val formation: String? = null,
    val starters: List<LineupPlayer>? = null,
    val bench: List<LineupPlayer>? = null
)

data class LineupPlayer(
    val name: String? = null,
    val number: Int? = null,
    val image: String? = null,
    val position: String? = null,
    val captain: Boolean = false,
    val goal: Int = 0,
    val yellow: Boolean = false,
    val red: Boolean = false,
    val rating: String? = null
)

/** One head-to-head stat bar (home vs away, homePct drives the bar split). */
data class StatRow(
    val label: String? = null,
    val home: String? = null,
    val away: String? = null,
    @SerializedName("home_pct") val homePct: Int = 50
)

/** A broadcast channel for a match (name + commentator). */
data class MatchChannel(
    val name: String? = null,
    val commentator: String? = null
)

/** A subscribable notification topic (a competition). */
data class NotifyTopic(
    val slug: String,
    val title: String,
    val image: String? = null
)
