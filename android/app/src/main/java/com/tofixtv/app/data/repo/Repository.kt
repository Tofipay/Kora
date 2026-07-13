package com.tofixtv.app.data.repo

import android.content.Context
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import com.tofixtv.app.data.local.AppDatabase
import com.tofixtv.app.data.local.FavoriteEntity
import com.tofixtv.app.data.local.SnapshotEntity
import com.tofixtv.app.data.model.*
import com.tofixtv.app.data.remote.ApiClient
import com.tofixtv.app.data.remote.ApiService
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext

/**
 * Single entry point for all data. Each call fetches from the API and, on
 * failure, transparently serves the last on-disk snapshot (Offline Mode).
 */
class Repository private constructor(context: Context) {

    private val api: ApiService = ApiClient.get(context)
    private val db = AppDatabase.get(context)
    private val gson = Gson()

    companion object {
        @Volatile private var instance: Repository? = null
        fun get(context: Context): Repository =
            instance ?: synchronized(this) {
                instance ?: Repository(context.applicationContext).also { instance = it }
            }
    }

    private suspend fun <T> snapshot(key: String, data: T?): T? {
        if (data != null) {
            runCatching {
                db.snapshots().put(SnapshotEntity(key, gson.toJson(data)))
            }
        }
        return data
    }

    private suspend inline fun <reified T> restore(key: String): T? {
        val row = db.snapshots().get(key) ?: return null
        return runCatching { gson.fromJson<T>(row.json, object : TypeToken<T>() {}.type) }.getOrNull()
    }

    // ---------- Matches ----------
    suspend fun matches(date: String, lang: String): List<Match> = withContext(Dispatchers.IO) {
        runCatching { api.matchesByDate(date, lang).data }.getOrNull()
            ?.let { snapshot("matches_$date$lang", it) }
            ?: restore<List<Match>>("matches_$date$lang").orEmpty()
    }

    suspend fun live(lang: String): List<Match> = withContext(Dispatchers.IO) {
        runCatching { api.liveMatches(lang).data }.getOrNull().orEmpty()
    }

    suspend fun matchInfo(id: Long, lang: String): Match? = withContext(Dispatchers.IO) {
        runCatching { api.matchInfo(id, lang).data }.getOrNull()
            ?.let { snapshot("match_$id$lang", it) }
            ?: restore<Match>("match_$id$lang")
    }

    // ---------- News ----------
    suspend fun news(page: Int, lang: String): List<NewsItem> = withContext(Dispatchers.IO) {
        runCatching { api.newsPage(page, lang).data }.getOrNull()
            ?.let { snapshot("news_$page$lang", it) }
            ?: restore<List<NewsItem>>("news_$page$lang").orEmpty()
    }

    suspend fun newsDetail(id: Long, lang: String): NewsItem? = withContext(Dispatchers.IO) {
        runCatching { api.newsDetail(id, lang).data }.getOrNull()
            ?.let { snapshot("newsd_$id$lang", it) }
            ?: restore<NewsItem>("newsd_$id$lang")
    }

    // ---------- Standings / Scorers ----------
    suspend fun standings(league: Long, lang: String): StandingsData? = withContext(Dispatchers.IO) {
        runCatching { api.standings(league, lang).data }.getOrNull()
            ?.let { snapshot("standings_$league$lang", it) }
            ?: restore<StandingsData>("standings_$league$lang")
    }

    // ---------- Leagues ----------
    suspend fun leagues(lang: String): List<League> = withContext(Dispatchers.IO) {
        runCatching { api.leagues(lang).data }.getOrNull()
            ?.let { snapshot("leagues_$lang", it) }
            ?: restore<List<League>>("leagues_$lang").orEmpty()
    }

    // ---------- Team / Player ----------
    suspend fun team(id: Long, lang: String): TeamData? = withContext(Dispatchers.IO) {
        runCatching { api.team(id, lang).data }.getOrNull()
    }

    suspend fun player(id: Long, slug: String, lang: String): PlayerInfo? = withContext(Dispatchers.IO) {
        runCatching { api.player(id, slug, lang).data }.getOrNull()
    }

    // ---------- Videos ----------
    suspend fun videos(champ: String, page: Int, query: String, lang: String): VideosData? =
        withContext(Dispatchers.IO) {
            runCatching { api.videos(champ, page, query, lang).data }.getOrNull()
        }

    suspend fun videoById(id: Long, lang: String): com.tofixtv.app.data.model.Video? =
        withContext(Dispatchers.IO) {
            runCatching { api.videoById(id, lang).data?.items?.firstOrNull() }.getOrNull()
        }

    // ---------- Push registration ----------
    suspend fun registerPush(token: String, topics: Set<String>) = withContext(Dispatchers.IO) {
        runCatching { api.pushSubscribe(token, topics.joinToString(",")) }
    }

    // ---------- Channels ----------
    suspend fun channels(lang: String): List<Channel> = withContext(Dispatchers.IO) {
        runCatching { api.channels(lang).data }.getOrNull()
            ?.let { snapshot("channels_$lang", it) }
            ?: restore<List<Channel>>("channels_$lang").orEmpty()
    }

    // ---------- Search ----------
    suspend fun search(query: String, lang: String): SearchData? = withContext(Dispatchers.IO) {
        runCatching { api.search(query, lang).data }.getOrNull()
    }

    // ---------- Favorites ----------
    fun favorites(): Flow<List<FavoriteEntity>> = db.favorites().all()
    fun favoritesByType(type: String): Flow<List<FavoriteEntity>> = db.favorites().byType(type)
    suspend fun isFavorite(key: String) = db.favorites().exists(key)
    suspend fun addFavorite(item: FavoriteEntity) = db.favorites().add(item)
    suspend fun removeFavorite(key: String) = db.favorites().remove(key)
    suspend fun toggleFavorite(item: FavoriteEntity): Boolean {
        return if (db.favorites().exists(item.key)) {
            db.favorites().remove(item.key); false
        } else {
            db.favorites().add(item); true
        }
    }
}
