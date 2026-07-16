package com.tofixtv.app.data.remote

import android.content.Context
import com.tofixtv.app.BuildConfig
import okhttp3.Cache
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.io.File
import java.util.concurrent.TimeUnit

/**
 * Retrofit / OkHttp factory with a 20 MB disk cache. The cache interceptor
 * rewrites responses so pages can be read back when the device is offline
 * (Offline Mode) and lightly caches while online (Lazy Loading friendly).
 */
object ApiClient {

    @Volatile private var service: ApiService? = null

    fun get(context: Context): ApiService {
        return service ?: synchronized(this) {
            service ?: build(context.applicationContext).also { service = it }
        }
    }

    private fun build(context: Context): ApiService {
        val cacheDir = File(context.cacheDir, "http_cache")
        val cache = Cache(cacheDir, 20L * 1024 * 1024) // 20 MB

        val logging = HttpLoggingInterceptor().apply {
            level = if (BuildConfig.DEBUG) HttpLoggingInterceptor.Level.BASIC
            else HttpLoggingInterceptor.Level.NONE
        }

        val offlineInterceptor = Interceptor { chain ->
            var request = chain.request()
            if (!NetworkMonitor.isOnline(context)) {
                request = request.newBuilder()
                    .header("Cache-Control", "public, only-if-cached, max-stale=${60 * 60 * 24 * 7}")
                    .build()
            }
            chain.proceed(request)
        }

        val onlineInterceptor = Interceptor { chain ->
            val response = chain.proceed(chain.request())
            response.newBuilder()
                .removeHeader("Pragma")
                .header("Cache-Control", "public, max-age=60")
                .build()
        }

        val client = OkHttpClient.Builder()
            .cache(cache)
            .addInterceptor(offlineInterceptor)
            .addNetworkInterceptor(onlineInterceptor)
            .addInterceptor(logging)
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .build()

        return Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(ApiService::class.java)
    }
}
