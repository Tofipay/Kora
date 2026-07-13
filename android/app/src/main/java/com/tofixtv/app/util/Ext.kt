package com.tofixtv.app.util

import android.view.View
import android.widget.ImageView
import coil.load
import coil.request.CachePolicy
import com.tofixtv.app.R
import java.text.SimpleDateFormat
import java.util.*

fun View.visible() { visibility = View.VISIBLE }
fun View.gone() { visibility = View.GONE }
fun View.showIf(cond: Boolean) { visibility = if (cond) View.VISIBLE else View.GONE }

/** Coil image load with disk caching and a brand placeholder. */
fun ImageView.loadImage(url: String?, placeholder: Int = R.drawable.ic_placeholder) {
    if (url.isNullOrBlank()) {
        setImageResource(placeholder)
        return
    }
    load(url) {
        crossfade(true)
        placeholder(placeholder)
        error(placeholder)
        diskCachePolicy(CachePolicy.ENABLED)
        memoryCachePolicy(CachePolicy.ENABLED)
    }
}

/** "22:00:00" -> "10:00 م" style 12-hour label. */
fun formatTime12(time: String?): String {
    if (time.isNullOrBlank()) return ""
    return try {
        val parsed = SimpleDateFormat("HH:mm:ss", Locale.US).parse(time)
            ?: SimpleDateFormat("HH:mm", Locale.US).parse(time)
        SimpleDateFormat("hh:mm a", Locale("ar")).format(parsed!!)
    } catch (e: Exception) { time }
}

fun relativeTime(iso: String?): String {
    if (iso.isNullOrBlank()) return ""
    val ts = parseTs(iso)
    if (ts == 0L) return ""
    val diff = (System.currentTimeMillis() / 1000 - ts).coerceAtLeast(0)
    return when {
        diff < 60 -> "الآن"
        diff < 3600 -> "منذ ${diff / 60} دقيقة"
        diff < 86400 -> "منذ ${diff / 3600} ساعة"
        diff < 2592000 -> "منذ ${diff / 86400} يوم"
        else -> SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date(ts * 1000))
    }
}

private fun parseTs(value: String): Long {
    value.toLongOrNull()?.let { return it }
    val patterns = listOf("yyyy-MM-dd HH:mm:ss", "yyyy-MM-dd'T'HH:mm:ssXXX", "yyyy-MM-dd")
    for (p in patterns) {
        try {
            val d = SimpleDateFormat(p, Locale.US).parse(value)
            if (d != null) return d.time / 1000
        } catch (_: Exception) {}
    }
    return 0L
}

fun todayDate(): String = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())

fun dateOffset(days: Int): String {
    val c = Calendar.getInstance()
    c.add(Calendar.DAY_OF_YEAR, days)
    return SimpleDateFormat("yyyy-MM-dd", Locale.US).format(c.time)
}
