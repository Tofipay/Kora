package com.tofixtv.app.ui.watch

import android.annotation.SuppressLint
import android.app.PictureInPictureParams
import android.os.Build
import android.os.Bundle
import android.util.Rational
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.webkit.WebChromeClient
import android.webkit.WebViewClient
import androidx.fragment.app.Fragment
import androidx.media3.common.MediaItem
import androidx.media3.exoplayer.ExoPlayer
import com.tofixtv.app.MainActivity
import com.tofixtv.app.databinding.FragmentWatchBinding
import com.tofixtv.app.util.gone
import com.tofixtv.app.util.visible

/**
 * Universal watch screen. A direct stream URL (m3u8/mpd/mp4/ts…) plays in
 * ExoPlayer with PiP; anything else (e.g. the site's /watch/{id} web player
 * that bundles hls.js + dash.js + server switching) loads in a WebView so no
 * streaming feature from the site is lost.
 */
class WatchFragment : Fragment(), MainActivity.PipAware {

    private var _b: FragmentWatchBinding? = null
    private val b get() = _b!!
    private var player: ExoPlayer? = null
    private var isStream = false

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentWatchBinding.inflate(inflater, c, false)
        return b.root
    }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val url = arguments?.getString("url").orEmpty()
        val type = arguments?.getString("type").orEmpty().lowercase()
        if (url.isBlank()) { b.progress.gone(); b.errorText.visible(); return }

        val streamType = type in listOf("m3u8", "mpd", "mp4", "ts", "webm", "mkv", "auto")
        val streamExt = Regex("\\.(m3u8|mpd|mp4|ts|webm|mkv)(\\?|$)", RegexOption.IGNORE_CASE)
            .containsMatchIn(url)
        // The first-party proxy URL (/stream?...) carries no file extension.
        val isProxy = url.contains("/stream?") || url.contains("/api/stream.php")
        isStream = streamType || streamExt || isProxy

        if (isStream) {
            b.playerView.visible(); b.progress.gone()
            val mime = when {
                type == "mpd" || url.contains(".mpd", true) -> androidx.media3.common.MimeTypes.APPLICATION_MPD
                type == "m3u8" || isProxy || url.contains(".m3u8", true) -> androidx.media3.common.MimeTypes.APPLICATION_M3U8
                else -> null
            }
            val itemBuilder = MediaItem.Builder().setUri(url)
            if (mime != null) itemBuilder.setMimeType(mime)
            player = ExoPlayer.Builder(requireContext()).build().also {
                b.playerView.player = it
                it.setMediaItem(itemBuilder.build())
                it.prepare(); it.playWhenReady = true
            }
        } else {
            b.webView.visible()
            with(b.webView.settings) {
                javaScriptEnabled = true
                domStorageEnabled = true
                mediaPlaybackRequiresUserGesture = false
            }
            b.webView.webViewClient = WebViewClient()
            b.webView.webChromeClient = WebChromeClient()
            b.webView.webViewClient = object : WebViewClient() {
                override fun onPageFinished(view: android.webkit.WebView?, url: String?) {
                    b.progress.gone()
                }
            }
            b.webView.loadUrl(url)
        }
    }

    override fun shouldEnterPip() = isStream && player?.isPlaying == true

    override fun onEnterPip() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            requireActivity().enterPictureInPictureMode(
                PictureInPictureParams.Builder().setAspectRatio(Rational(16, 9)).build()
            )
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        player?.release(); player = null
        _b?.webView?.destroy()
        _b = null
    }
}
