package com.tofixtv.app.util

import com.tofixtv.app.BuildConfig

/**
 * Builds first-party media-proxy URLs (mirrors the PHP media_url() helper).
 * Images are always served through https://api.tofi-xtv.com/media/... so the
 * upstream CDN host never leaks and sizing/WebP is handled server-side.
 */
object Media {

    private val base = BuildConfig.API_BASE_URL.trimEnd('/')

    private fun isDefault(file: String) =
        Regex("(^|/)[a-z0-9_]*default\\.(png|jpe?g|gif|webp)$", RegexOption.IGNORE_CASE).containsMatchIn(file)

    private fun url(kind: String, size: String, file: String?): String? {
        if (file.isNullOrBlank()) return null
        if (isDefault(file)) return null
        if (file.startsWith("http://", true) || file.startsWith("https://", true)) {
            // Already absolute — rewrite known CDN hosts through the proxy path.
            val path = runCatching { java.net.URL(file).path }.getOrNull()
            return if (path != null && (file.contains("imgs.", true)))
                "$base/media$path" else file
        }
        val clean = file.trimStart('/')
        if (!Regex("^[A-Za-z0-9._\\-]+$").matches(clean)) return null
        return "$base/media/$kind/$size/$clean"
    }

    fun team(file: String?, size: String = "64") = url("teams", size, file)
    fun league(file: String?, size: String = "128") = url("championship", size, file)
    fun player(file: String?, size: String = "64") = url("player", size, file)
    fun news(file: String?, size: String = "640") = url("news", size, file)
    fun country(file: String?, size: String = "64") = url("country", size, file)
}
