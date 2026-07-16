package com.tofixtv.app.util

/** Process-wide cached language, kept in sync with SettingsStore. */
object AppState {
    @Volatile var lang: String = "ar"
}
