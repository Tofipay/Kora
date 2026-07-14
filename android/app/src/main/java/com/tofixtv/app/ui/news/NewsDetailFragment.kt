package com.tofixtv.app.ui.news

import android.annotation.SuppressLint
import android.content.res.Configuration
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.tofixtv.app.BuildConfig
import com.tofixtv.app.data.model.NewsItem
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentNewsDetailBinding
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/**
 * Full news article — rendered as a styled HTML document in a WebView so it
 * mirrors the website: hero image, category, headline, timestamp and the full
 * rich-text body (images, paragraphs, embeds) with a premium 2026 layout.
 */
class NewsDetailFragment : Fragment() {

    private var _b: FragmentNewsDetailBinding? = null
    private val b get() = _b!!

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentNewsDetailBinding.inflate(inflater, c, false)
        return b.root
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        with(b.webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            loadWithOverviewMode = true
            useWideViewPort = true
        }
        b.webView.setBackgroundColor(0x00000000)
        b.webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(v: WebView?, url: String?) { b.progress.gone() }
        }

        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val item = try { Repository.get(requireContext()).newsDetail(id, AppState.lang) }
            catch (e: Exception) { null }
            if (item == null) { b.progress.gone(); b.errorText.visible(); return@launch }
            b.webView.visible()
            b.webView.loadDataWithBaseURL(
                BuildConfig.API_BASE_URL, buildHtml(item), "text/html", "utf-8", null
            )
        }
    }

    private fun isDark(): Boolean =
        (resources.configuration.uiMode and Configuration.UI_MODE_NIGHT_MASK) ==
            Configuration.UI_MODE_NIGHT_YES

    private fun buildHtml(item: NewsItem): String {
        val dark = isDark()
        val bg = if (dark) "#111827" else "#F4F7F8"
        val card = if (dark) "#1B2534" else "#FFFFFF"
        val ink = if (dark) "#E6EAF0" else "#0B1220"
        val muted = if (dark) "#93A1B1" else "#6B7A8D"
        val border = if (dark) "#243040" else "#E3E9EC"

        val hero = Media.news(item.image)
        val title = item.title ?: ""
        val time = relativeTime(item.createdAt?.raw)
        val category = item.league?.displayName ?: ""
        val body = (item.body ?: item.desc ?: "").ifBlank {
            "<p style=\"color:$muted\">لا يتوفر نص كامل لهذا الخبر.</p>"
        }

        val heroBlock = if (!hero.isNullOrBlank())
            """<img class="hero" src="$hero" alt="">""" else ""
        val categoryBlock = if (category.isNotBlank())
            """<span class="cat">$category</span>""" else ""
        val timeBlock = if (time.isNotBlank())
            """<span class="time">🕒 $time</span>""" else ""

        return """
<!DOCTYPE html><html dir="rtl" lang="ar"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<style>
  :root { --brand:#0D9488; --accent:#F59E0B; }
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html,body { margin:0; padding:0; background:$bg; color:$ink;
    font-family: -apple-system, "Segoe UI", "Noto Naskh Arabic", Tahoma, sans-serif;
    line-height: 1.9; }
  .wrap { padding: 0 0 40px; }
  .hero { width:100%; height:auto; display:block; aspect-ratio: 16/9;
    object-fit: cover; }
  .card { background:$card; margin:-26px 14px 0; border-radius:22px;
    padding: 20px 18px; box-shadow: 0 10px 30px rgba(0,0,0,.10);
    border:1px solid $border; position:relative; }
  .cat { display:inline-block; background:linear-gradient(135deg,var(--brand),#0F766E);
    color:#fff; font-size:12px; font-weight:700; padding:5px 12px;
    border-radius:999px; margin-bottom:12px; }
  h1 { font-size:22px; font-weight:800; margin:6px 0 10px; line-height:1.5; color:$ink; }
  .meta { display:flex; gap:14px; align-items:center; color:$muted;
    font-size:13px; padding-bottom:14px; margin-bottom:14px;
    border-bottom:1px solid $border; }
  .body { font-size:16.5px; color:$ink; }
  .body p { margin: 0 0 16px; }
  .body img { max-width:100%; height:auto; border-radius:14px; margin:14px 0; display:block; }
  .body h2,.body h3 { color:$ink; font-weight:800; margin:20px 0 10px; }
  .body a { color:var(--brand); text-decoration:none; }
  .body iframe { max-width:100%; border:0; border-radius:14px; margin:14px 0; }
  .body ul,.body ol { padding-inline-start: 22px; }
  .body blockquote { margin:14px 0; padding:10px 16px;
    border-inline-start:4px solid var(--brand); background:rgba(13,148,136,.08);
    border-radius:10px; color:$ink; }
</style></head>
<body><div class="wrap">
  $heroBlock
  <div class="card">
    $categoryBlock
    <h1>$title</h1>
    <div class="meta">$timeBlock</div>
    <div class="body">$body</div>
  </div>
</div></body></html>
""".trimIndent()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _b?.webView?.destroy()
        _b = null
    }
}

/** Extract a trailing numeric id from a slug like "spain-vs-austria-12345". */
fun idFromArg(arg: String?): Long {
    if (arg.isNullOrBlank()) return 0L
    arg.toLongOrNull()?.let { return it }
    val m = Regex("(\\d+)$").find(arg)
    return m?.groupValues?.get(1)?.toLongOrNull() ?: 0L
}
