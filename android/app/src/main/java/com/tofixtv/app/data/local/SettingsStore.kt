package com.tofixtv.app.data.local

import android.content.Context
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map

private val Context.dataStore by preferencesDataStore(name = "settings")

/** User preferences: language, theme, notifications and subscribed topics. */
class SettingsStore(private val context: Context) {

    companion object {
        val LANG = stringPreferencesKey("lang")            // ar | en
        val THEME = intPreferencesKey("theme")             // 0 system, 1 light, 2 dark
        val NOTIFY = booleanPreferencesKey("notify")
        val TOPICS = stringSetPreferencesKey("topics")     // subscribed topic slugs
    }

    val lang: Flow<String> = context.dataStore.data.map { it[LANG] ?: "ar" }
    val theme: Flow<Int> = context.dataStore.data.map { it[THEME] ?: 0 }
    val notify: Flow<Boolean> = context.dataStore.data.map { it[NOTIFY] ?: true }
    val topics: Flow<Set<String>> = context.dataStore.data.map { it[TOPICS] ?: setOf("all") }

    suspend fun setLang(value: String) = context.dataStore.edit { it[LANG] = value }
    suspend fun setTheme(value: Int) = context.dataStore.edit { it[THEME] = value }
    suspend fun setNotify(value: Boolean) = context.dataStore.edit { it[NOTIFY] = value }
    suspend fun setTopics(value: Set<String>) = context.dataStore.edit { it[TOPICS] = value }
}
