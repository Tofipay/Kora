package com.tofixtv.app

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import androidx.appcompat.app.AppCompatDelegate
import com.tofixtv.app.data.local.SettingsStore
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

class App : Application() {

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
        applySavedTheme()
    }

    private fun applySavedTheme() {
        val store = SettingsStore(this)
        CoroutineScope(SupervisorJob() + Dispatchers.Main).launch {
            com.tofixtv.app.util.AppState.lang = store.lang.first()
            val mode = when (store.theme.first()) {
                1 -> AppCompatDelegate.MODE_NIGHT_NO
                2 -> AppCompatDelegate.MODE_NIGHT_YES
                else -> AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM
            }
            AppCompatDelegate.setDefaultNightMode(mode)
        }
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                getString(R.string.fcm_default_channel_id),
                getString(R.string.fcm_default_channel_name),
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = getString(R.string.fcm_default_channel_desc)
            }
            (getSystemService(NotificationManager::class.java)).createNotificationChannel(channel)
        }
    }
}
