package com.tofixtv.app;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.Uri;
import android.os.AsyncTask;
import android.os.Build;
import androidx.core.app.NotificationCompat;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;
import java.io.InputStream;
import java.net.URL;

/**
 * FCMService class for managing and displaying notifications.
 *
 * Copyright (c) 2024 Bokku. All rights reserved.
 *
 * This class provides functionality to create and show notifications
 * with optional images downloaded from a given URL.
 */

public class FCMService extends FirebaseMessagingService {

    private static final String CHANNEL_ID = "fcm_channel";

    @Override
    public void onMessageReceived(RemoteMessage remoteMessage) {
        if (remoteMessage.getData().size() > 0) {
            String title = remoteMessage.getData().get("title");
            String body = remoteMessage.getData().get("body");
            String imageUrl = remoteMessage.getData().get("image"); // This is the video URL

            showNotification(title, body, imageUrl);
        }
    }

    private void showNotification(String title, String body, String imageUrl) {
        // Create an Intent to open MainActivity with ACTION_VIEW
        Intent intent = new Intent(Intent.ACTION_VIEW);
        intent.setClass(this, MainActivity.class);
        intent.setData(Uri.parse(imageUrl)); // Pass the video URL as data

        PendingIntent pendingIntent = PendingIntent.getActivity(
                this, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);

        NotificationManager notificationManager =
                (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID, "FCM Channel", NotificationManager.IMPORTANCE_HIGH);
            notificationManager.createNotificationChannel(channel);
        }

        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.drawable.notific) // استخدام الأيقونة المخصصة بدون خلفية
                .setColor(getResources().getColor(R.color.red)) // تعيين الخلفية الحمراء للأيقونة
                .setAutoCancel(true)
                .setContentTitle(title)
                .setContentText(body)
                .setContentIntent(pendingIntent);

        new DownloadImageTask(builder).execute(imageUrl);
    }

    private class DownloadImageTask extends AsyncTask<String, Void, Bitmap> {

        private NotificationCompat.Builder builder;

        public DownloadImageTask(NotificationCompat.Builder builder) {
            this.builder = builder;
        }

        @Override
        protected Bitmap doInBackground(String... params) {
            String imageUrl = params[0];
            try {
                InputStream in = new URL(imageUrl).openStream();
                return BitmapFactory.decodeStream(in);
            } catch (Exception e) {
                return null;
            }
        }

        @Override
        protected void onPostExecute(Bitmap result) {
            NotificationManager notificationManager =
                    (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
            if (result != null) {
                builder.setStyle(new NotificationCompat.BigPictureStyle()
                        .bigPicture(result)
                        .bigLargeIcon((Bitmap) null));
            }
            if (notificationManager != null) {
                notificationManager.notify((int) System.currentTimeMillis(), builder.build());
            }
        }
    }
}