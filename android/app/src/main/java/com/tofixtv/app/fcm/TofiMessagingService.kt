package com.tofixtv.app.fcm

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.net.Uri
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.tofixtv.app.MainActivity
import com.tofixtv.app.R

/**
 * Receives FCM pushes (live match events, breaking news …) and posts a
 * notification. A `link` data field (e.g. tofixtv://match/123) makes the
 * notification deep-link straight into the relevant screen.
 */
class TofiMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // Persist + register the token with the backend so pushes can target it.
        FcmRegistrar.saveToken(applicationContext, token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        val title = message.notification?.title
            ?: message.data["title"] ?: getString(R.string.app_name)
        val body = message.notification?.body ?: message.data["body"] ?: ""
        // Backend (Fcm::buildMessage) puts the deep-link destination in `url`;
        // accept `link` too for compatibility.
        val link = message.data["link"] ?: message.data["url"]
        showNotification(title, body, link)
    }

    private fun showNotification(title: String, body: String, link: String?) {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            if (!link.isNullOrBlank()) data = Uri.parse(link)
        }
        val pending = PendingIntent.getActivity(
            this, System.currentTimeMillis().toInt(), intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, getString(R.string.fcm_default_channel_id))
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(pending)
            .build()

        (getSystemService(NOTIFICATION_SERVICE) as NotificationManager)
            .notify(System.currentTimeMillis().toInt(), notification)
    }
}

/** Small helper to subscribe / unsubscribe FCM topics and cache the token. */
object FcmRegistrar {
    fun saveToken(ctx: android.content.Context, token: String) {
        ctx.getSharedPreferences("fcm", android.content.Context.MODE_PRIVATE)
            .edit().putString("token", token).apply()
    }

    fun token(ctx: android.content.Context): String? =
        ctx.getSharedPreferences("fcm", android.content.Context.MODE_PRIVATE)
            .getString("token", null)

    fun subscribe(topic: String) {
        FirebaseMessaging.getInstance().subscribeToTopic(topic)
    }

    fun unsubscribe(topic: String) {
        FirebaseMessaging.getInstance().unsubscribeFromTopic(topic)
    }
}
