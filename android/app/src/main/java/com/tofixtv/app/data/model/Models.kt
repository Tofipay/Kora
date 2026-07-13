package com.tofixtv.app.data.model

import com.google.gson.annotations.SerializedName

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
    val events: List<MatchEvent>? = null
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
    @SerializedName("created_at") val createdAt: String? = null,
    val league: League? = null
)

/** A highlight video (Btolat feed). */
data class Video(
    val id: Long? = null,
    val title: String? = null,
    val thumbnail: String? = null,
    @SerializedName("champ_title") val champTitle: String? = null,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("youtube_id") val youtubeId: String? = null,
    @SerializedName("media_url") val mediaUrl: String? = null,
    @SerializedName("tweet_id") val tweetId: String? = null,
    @SerializedName("embed_iframe") val embedIframe: String? = null,
    @SerializedName("video_url") val videoUrl: String? = null
)

data class VideosData(
    val items: List<Video>? = null,
    @SerializedName("has_next") val hasNext: Boolean = false,
    val page: Int = 1
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

/** A subscribable notification topic (a competition). */
data class NotifyTopic(
    val slug: String,
    val title: String,
    val image: String? = null
)
