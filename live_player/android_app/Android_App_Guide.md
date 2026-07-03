# 📱 دليل تطبيق Android — LiveStream Pro

دليل كامل لبناء تطبيق أندرويد بلغة **Java** يستهلك واجهة `player.php` ويشغّل
روابط البث (HLS / DASH / MP4) باستخدام **ExoPlayer**، مع دعم وضع النافذة العائمة
(Picture-in-Picture) وإعادة الاتصال التلقائي عند انقطاع البث.

> ملاحظة: استخدم هذا التطبيق للمحتوى المصرّح لك بالوصول إليه فقط.

---

## 1) الإعتماديات — `app/build.gradle`

```gradle
android {
    compileSdk 34

    defaultConfig {
        applicationId "com.livestream.pro"
        minSdk 21
        targetSdk 34
        versionCode 1
        versionName "1.0"
    }

    compileOptions {
        sourceCompatibility JavaVersion.VERSION_1_8
        targetCompatibility JavaVersion.VERSION_1_8
    }
}

dependencies {
    // ── ExoPlayer: نواة التشغيل + الواجهة + دعم HLS و DASH ──
    implementation "com.google.android.exoplayer:exoplayer-core:2.19.1"
    implementation "com.google.android.exoplayer:exoplayer-ui:2.19.1"
    implementation "com.google.android.exoplayer:exoplayer-hls:2.19.1"
    implementation "com.google.android.exoplayer:exoplayer-dash:2.19.1"

    // ── Retrofit + Gson: لاستهلاك واجهة player.php ──
    implementation "com.squareup.retrofit2:retrofit:2.9.0"
    implementation "com.squareup.retrofit2:converter-gson:2.9.0"

    // ── RecyclerView: لعرض قائمة السيرفرات ──
    implementation "androidx.recyclerview:recyclerview:1.3.1"
    implementation "androidx.appcompat:appcompat:1.6.1"
    implementation "com.google.android.material:material:1.11.0"
}
```

---

## 2) الأذونات والإعدادات — `AndroidManifest.xml`

```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android"
    package="com.livestream.pro">

    <!-- إذن الإنترنت وحالة الشبكة -->
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />

    <application
        android:allowBackup="true"
        android:label="LiveStream Pro"
        android:theme="@style/Theme.Material3.Dark.NoActionBar"
        android:usesCleartextTraffic="true">   <!-- السماح بروابط http غير المشفّرة -->

        <activity
            android:name=".PlayerActivity"
            android:exported="true"
            android:supportsPictureInPicture="true"                <!-- دعم النافذة العائمة -->
            android:configChanges="screenSize|smallestScreenSize|screenLayout|orientation|keyboardHidden">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
            </intent-filter>
        </activity>
    </application>
</manifest>
```

---

## 3) النماذج (Models)

### `Server.java`
```java
package com.livestream.pro;

// نموذج يمثّل سيرفر/مورد بث واحد
public class Server {
    public String name;   // اسم المورد
    public String url;    // رابط البث
    public String type;   // النوع: m3u8 / mpd / mp4 ...

    public Server(String name, String url, String type) {
        this.name = name;
        this.url = url;
        this.type = type;
    }
}
```

### `ApiResponse.java`
```java
package com.livestream.pro;

import java.util.List;

// نموذج استجابة الـ API القادمة من player.php
public class ApiResponse {
    public String title;          // اسم القناة
    public List<Server> servers;  // قائمة الموارد
    public String error;          // رسالة خطأ إن وُجدت
}
```

---

## 4) واجهة الشبكة — `ApiService.java`

```java
package com.livestream.pro;

import retrofit2.Call;
import retrofit2.http.GET;
import retrofit2.http.Query;

// واجهة Retrofit لاستدعاء player.php?url=...
public interface ApiService {
    @GET("player.php")
    Call<ApiResponse> getStreams(@Query("url") String pageUrl);
}
```

> عند إنشاء Retrofit استخدم `baseUrl` لمجلّد المشروع على الاستضافة، مثال:
> `https://your-host.com/live_player/`

---

## 5) محوّل القائمة — `ServerAdapter.java`

```java
package com.livestream.pro;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import java.util.List;

public class ServerAdapter extends RecyclerView.Adapter<ServerAdapter.VH> {

    // واجهة الاستماع للنقر على مورد
    public interface OnServerClickListener {
        void onServerClick(Server server, int position);
    }

    private final List<Server> servers;
    private final OnServerClickListener listener;
    private int selectedPos = -1;   // موضع العنصر المحدّد حالياً

    public ServerAdapter(List<Server> servers, OnServerClickListener listener) {
        this.servers = servers;
        this.listener = listener;
    }

    @NonNull @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View v = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_server, parent, false);
        return new VH(v);
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        Server s = servers.get(position);
        h.tvName.setText(s.name);
        h.tvType.setText(s.type.toUpperCase());

        // تمييز العنصر المحدّد بلون مختلف
        h.itemView.setSelected(position == selectedPos);
        h.itemView.setAlpha(position == selectedPos ? 1f : 0.85f);

        h.itemView.setOnClickListener(v -> {
            int old = selectedPos;
            selectedPos = h.getAdapterPosition();
            notifyItemChanged(old);
            notifyItemChanged(selectedPos);
            listener.onServerClick(s, selectedPos);
        });
    }

    @Override public int getItemCount() { return servers.size(); }

    static class VH extends RecyclerView.ViewHolder {
        TextView tvName, tvType;
        VH(@NonNull View v) {
            super(v);
            tvName = v.findViewById(R.id.tvServerName);
            tvType = v.findViewById(R.id.tvServerType);
        }
    }
}
```

---

## 6) النشاط الرئيسي — `PlayerActivity.java`

```java
package com.livestream.pro;

import android.app.PictureInPictureParams;
import android.content.res.Configuration;
import android.os.Build;
import android.os.Bundle;
import android.util.Rational;
import android.view.View;
import android.widget.ProgressBar;
import android.widget.TextView;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.exoplayer2.ExoPlayer;
import com.google.android.exoplayer2.MediaItem;
import com.google.android.exoplayer2.PlaybackException;
import com.google.android.exoplayer2.Player;
import com.google.android.exoplayer2.trackselection.DefaultTrackSelector;
import com.google.android.exoplayer2.ui.StyledPlayerView;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;
import retrofit2.Retrofit;
import retrofit2.converter.gson.GsonConverterFactory;

public class PlayerActivity extends AppCompatActivity {

    private static final String BASE_URL = "https://your-host.com/live_player/";

    private StyledPlayerView playerView;
    private ExoPlayer player;
    private DefaultTrackSelector trackSelector;
    private ProgressBar progressBar;
    private TextView tvStatus;
    private RecyclerView rvServers;

    private final List<Server> serverList = new ArrayList<>();
    private ServerAdapter adapter;

    private int reconnectAttempts = 0;   // عدّاد إعادة الاتصال
    private String currentUrl = null;    // الرابط قيد التشغيل

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_player);

        playerView  = findViewById(R.id.playerView);
        progressBar = findViewById(R.id.progressBar);
        tvStatus    = findViewById(R.id.tvStatus);
        rvServers   = findViewById(R.id.rvServers);

        setupPlayer();
        setupRecycler();

        // مثال: جلب سيرفرات صفحة بث معيّنة
        fetchStreams("https://example.com/stream-page");
    }

    // ── تهيئة ExoPlayer مع مُحدّد المسارات ──
    private void setupPlayer() {
        trackSelector = new DefaultTrackSelector(this);
        player = new ExoPlayer.Builder(this)
                .setTrackSelector(trackSelector)
                .build();
        playerView.setPlayer(player);

        // مستمع لإعادة الاتصال عند حدوث خطأ في التشغيل
        player.addListener(new Player.Listener() {
            @Override
            public void onPlayerError(@NonNull PlaybackException error) {
                scheduleReconnect();
            }

            @Override
            public void onPlaybackStateChanged(int state) {
                if (state == Player.STATE_READY) {
                    reconnectAttempts = 0;
                    progressBar.setVisibility(View.GONE);
                    tvStatus.setText("يبث الآن");
                } else if (state == Player.STATE_BUFFERING) {
                    progressBar.setVisibility(View.VISIBLE);
                }
            }
        });
    }

    // ── تهيئة قائمة السيرفرات ──
    private void setupRecycler() {
        adapter = new ServerAdapter(serverList, (server, pos) -> playStream(server.url));
        rvServers.setLayoutManager(new LinearLayoutManager(this));
        rvServers.setAdapter(adapter);
    }

    // ── جلب الروابط من player.php عبر Retrofit ──
    private void fetchStreams(String pageUrl) {
        Retrofit retrofit = new Retrofit.Builder()
                .baseUrl(BASE_URL)
                .addConverterFactory(GsonConverterFactory.create())
                .build();

        ApiService api = retrofit.create(ApiService.class);
        api.getStreams(pageUrl).enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(@NonNull Call<ApiResponse> call,
                                   @NonNull Response<ApiResponse> resp) {
                if (resp.body() != null && resp.body().servers != null) {
                    serverList.clear();
                    serverList.addAll(resp.body().servers);
                    adapter.notifyDataSetChanged();
                    setTitle(resp.body().title);
                    // تشغيل أول مورد تلقائياً
                    if (!serverList.isEmpty()) playStream(serverList.get(0).url);
                }
            }
            @Override
            public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                tvStatus.setText("فشل جلب الموارد: " + t.getMessage());
            }
        });
    }

    // ── تشغيل رابط بث ──
    private void playStream(String url) {
        currentUrl = url;
        progressBar.setVisibility(View.VISIBLE);
        tvStatus.setText("جارٍ التحميل…");
        MediaItem item = MediaItem.fromUri(url);   // ExoPlayer يكتشف النوع تلقائياً
        player.setMediaItem(item);
        player.prepare();
        player.setPlayWhenReady(true);
    }

    // ── إعادة الاتصال بتأخير متصاعد (3s × عدد المحاولات) حتى 5 مرات ──
    private void scheduleReconnect() {
        if (reconnectAttempts >= 5 || currentUrl == null) {
            tvStatus.setText("تعذّر التشغيل");
            return;
        }
        reconnectAttempts++;
        long delay = 3000L * reconnectAttempts;
        tvStatus.setText("إعادة المحاولة " + reconnectAttempts + "…");
        playerView.postDelayed(() -> playStream(currentUrl), delay);
    }

    // ── الدخول لوضع النافذة العائمة عند مغادرة المستخدم ──
    @Override
    public void onUserLeaveHint() {
        super.onUserLeaveHint();
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && player.isPlaying()) {
            PictureInPictureParams params = new PictureInPictureParams.Builder()
                    .setAspectRatio(new Rational(16, 9))
                    .build();
            enterPictureInPictureMode(params);
        }
    }

    // ── إخفاء/إظهار عناصر الواجهة عند تبديل وضع PiP ──
    @Override
    public void onPictureInPictureModeChanged(boolean isInPip, @NonNull Configuration newConfig) {
        super.onPictureInPictureModeChanged(isInPip, newConfig);
        int visibility = isInPip ? View.GONE : View.VISIBLE;
        rvServers.setVisibility(visibility);
        tvStatus.setVisibility(visibility);
        playerView.setUseController(!isInPip);   // إخفاء أزرار التحكم في وضع PiP
    }

    @Override
    protected void onStop() {
        super.onStop();
        if (player != null) player.pause();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        if (player != null) { player.release(); player = null; }
    }
}
```

---

## 7) التخطيطات (Layouts)

### `res/layout/activity_player.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="match_parent"
    android:orientation="vertical"
    android:background="#0a0a0a">

    <!-- مشغّل ExoPlayer بنسبة 16:9 -->
    <com.google.android.exoplayer2.ui.StyledPlayerView
        android:id="@+id/playerView"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        android:layout_weight="0"
        android:minHeight="220dp"
        app:resize_mode="fit"
        app:show_buffering="when_playing" />

    <!-- مؤشّر التحميل -->
    <ProgressBar
        android:id="@+id/progressBar"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:layout_gravity="center_horizontal"
        android:layout_margin="8dp"
        android:visibility="gone" />

    <!-- نص الحالة -->
    <TextView
        android:id="@+id/tvStatus"
        android:layout_width="match_parent"
        android:layout_height="wrap_content"
        android:padding="10dp"
        android:textColor="#00bcd4"
        android:text="في الانتظار" />

    <!-- قائمة السيرفرات -->
    <androidx.recyclerview.widget.RecyclerView
        android:id="@+id/rvServers"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        android:layout_weight="1"
        android:padding="8dp" />
</LinearLayout>
```

### `res/layout/item_server.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<LinearLayout xmlns:android="http://schemas.android.com/apk/res/android"
    android:layout_width="match_parent"
    android:layout_height="wrap_content"
    android:orientation="horizontal"
    android:gravity="center_vertical"
    android:padding="14dp"
    android:layout_marginBottom="8dp"
    android:background="#1b2130">

    <!-- اسم المورد -->
    <TextView
        android:id="@+id/tvServerName"
        android:layout_width="0dp"
        android:layout_height="wrap_content"
        android:layout_weight="1"
        android:textColor="#e8eef5"
        android:textStyle="bold"
        android:text="مورد 1" />

    <!-- نوع المورد -->
    <TextView
        android:id="@+id/tvServerType"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:paddingHorizontal="10dp"
        android:paddingVertical="4dp"
        android:textColor="#00e676"
        android:textSize="12sp"
        android:text="M3U8" />
</LinearLayout>
```

---

## 8) ملاحظات التشغيل

1. عدّل `BASE_URL` في `PlayerActivity` ليشير إلى مجلّد `live_player/` على استضافتك.
2. ExoPlayer يكتشف نوع البث (HLS/DASH/MP4) تلقائياً من `MediaItem.fromUri()`،
   لكن يمكنك تحديد `MimeTypes` يدوياً عند الحاجة.
3. `usesCleartextTraffic="true"` ضروري لتشغيل روابط `http://`؛ إن كانت كل روابطك
   `https` يُفضّل إزالته لأسباب أمنية.
4. لإعادة الاتصال الأكثر ذكاءً استعمل `DefaultLoadControl` مع
   `LoadErrorHandlingPolicy` مخصّصة بدل المؤقّت اليدوي.
