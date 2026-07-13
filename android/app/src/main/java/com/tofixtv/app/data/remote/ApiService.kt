package com.tofixtv.app.data.remote

import com.tofixtv.app.data.model.*
import retrofit2.http.GET
import retrofit2.http.Query

/**
 * First-party JSON API served from https://api.tofi-xtv.com.
 * Every endpoint returns the standard { ok, stale, lang, count, data } envelope
 * (except the compact internal ones which are already flat).
 */
interface ApiService {

    @GET("api/matches.php")
    suspend fun matchesByDate(
        @Query("date") date: String,
        @Query("lang") lang: String = "ar"
    ): Envelope<List<Match>>

    @GET("api/live.php")
    suspend fun liveMatches(
        @Query("lang") lang: String = "ar"
    ): Envelope<List<Match>>

    @GET("api/news.php")
    suspend fun newsPage(
        @Query("page") page: Int,
        @Query("lang") lang: String = "ar"
    ): Envelope<List<NewsItem>>

    @GET("api/news.php")
    suspend fun newsDetail(
        @Query("id") id: Long,
        @Query("lang") lang: String = "ar"
    ): Envelope<NewsItem>

    @GET("api/standings.php")
    suspend fun standings(
        @Query("league") league: Long,
        @Query("lang") lang: String = "ar"
    ): Envelope<StandingsData>

    @GET("api/team.php")
    suspend fun team(
        @Query("id") id: Long,
        @Query("lang") lang: String = "ar"
    ): Envelope<TeamData>

    @GET("api/player.php")
    suspend fun player(
        @Query("id") id: Long,
        @Query("slug") slug: String = "",
        @Query("lang") lang: String = "ar"
    ): Envelope<PlayerInfo>

    @GET("api/videos.php")
    suspend fun videos(
        @Query("champ") champ: String = "all",
        @Query("page") page: Int = 1,
        @Query("q") query: String = "",
        @Query("lang") lang: String = "ar"
    ): Envelope<VideosData>

    @GET("api/channels.php")
    suspend fun channels(
        @Query("lang") lang: String = "ar"
    ): Envelope<List<Channel>>

    @GET("api/leagues.php")
    suspend fun leagues(
        @Query("lang") lang: String = "ar"
    ): Envelope<List<League>>

    @GET("api/search.php")
    suspend fun search(
        @Query("q") query: String,
        @Query("lang") lang: String = "ar"
    ): Envelope<SearchData>

    @GET("api/match_info.php")
    suspend fun matchInfo(
        @Query("id") id: Long,
        @Query("lang") lang: String = "ar"
    ): Envelope<Match>
}
