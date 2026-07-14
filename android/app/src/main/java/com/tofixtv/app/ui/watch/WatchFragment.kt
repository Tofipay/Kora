package com.tofixtv.app.ui.watch

import android.annotation.SuppressLint
import android.app.PictureInPictureParams
import android.content.pm.ActivityInfo
import android.os.Build
import android.os.Bundle
import android.util.Rational
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.webkit.WebChromeClient
import android.webkit.WebViewClient
import android.widget.PopupMenu
import androidx.appcompat.app.AlertDialog
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.fragment.app.Fragment
import androidx.media3.common.C
import androidx.media3.common.MediaItem
import androidx.media3.common.PlaybackParameters
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.exoplayer.trackselection.DefaultTrackSelector
import androidx.media3.ui.AspectRatioFrameLayout
import androidx.media3.ui.TrackSelectionDialogBuilder
import com.tofixtv.app.MainActivity
import com.tofixtv.app.R
import com.tofixtv.app.databinding.FragmentWatchBinding
import com.tofixtv.app.util.gone
import com.tofixtv.app.util.visible

/**
 * Universal watch screen. A direct stream URL (m3u8/mpd/mp4/ts…) plays in a
 * professional ExoPlayer that opens fullscreen in landscape and offers resize
 * (fit/fill/zoom), quality (track selection) and playback-speed controls plus
 * Picture-in-Picture; anything else (the site's /watch/{id} web player that
 * bundles hls.js + dash.js + server switching) loads in a WebView so no
 * streaming feature from the site is lost.
 */
class WatchFragment : Fragment(), MainActivity.PipAware {

    private var _b: FragmentWatchBinding? = null
    private val b get() = _b!!
    private var player: ExoPlayer? = null
    private var trackSelector: DefaultTrackSelector? = null
    private var isStream = false

    private val resizeModes = intArrayOf(
        AspectRatioFrameLayout.RESIZE_MODE_FIT,
        AspectRatioFrameLayout.RESIZE_MODE_FILL,
        AspectRatioFrameLayout.RESIZE_MODE_ZOOM
    )
    private var resizeIndex = 0
    private val speeds = floatArrayOf(0.5f, 0.75f, 1f, 1.25f, 1.5f, 2f)
    private var speedIndex = 2

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
        val isProxy = url.contains("/stream?") || url.contains("/api/stream.php")
        isStream = streamType || streamExt || isProxy

        if (isStream) playStream(url, type, isProxy) else playWeb(url)
    }

    private fun playStream(url: String, type: String, isProxy: Boolean) {
        b.playerView.visible(); b.progress.gone(); b.controlsOverlay.visible()
        enterFullscreenLandscape()

        val mime = when {
            type == "mpd" || url.contains(".mpd", true) -> androidx.media3.common.MimeTypes.APPLICATION_MPD
            type == "m3u8" || isProxy || url.contains(".m3u8", true) -> androidx.media3.common.MimeTypes.APPLICATION_M3U8
            else -> null
        }
        val itemBuilder = MediaItem.Builder().setUri(url)
        if (mime != null) itemBuilder.setMimeType(mime)

        val selector = DefaultTrackSelector(requireContext()).also { trackSelector = it }
        player = ExoPlayer.Builder(requireContext())
            .setTrackSelector(selector)
            .build().also {
                b.playerView.player = it
                b.playerView.resizeMode = resizeModes[resizeIndex]
                it.setMediaItem(itemBuilder.build())
                it.prepare(); it.playWhenReady = true
            }

        b.btnResize.setOnClickListener { cycleResize() }
        b.btnSettings.setOnClickListener { v -> showSettings(v) }
    }

    private fun cycleResize() {
        resizeIndex = (resizeIndex + 1) % resizeModes.size
        b.playerView.resizeMode = resizeModes[resizeIndex]
        val label = when (resizeModes[resizeIndex]) {
            AspectRatioFrameLayout.RESIZE_MODE_FILL -> R.string.player_fill
            AspectRatioFrameLayout.RESIZE_MODE_ZOOM -> R.string.player_zoom
            else -> R.string.player_fit
        }
        android.widget.Toast.makeText(requireContext(), getString(label), android.widget.Toast.LENGTH_SHORT).show()
    }

    private fun showSettings(anchor: View) {
        val menu = PopupMenu(requireContext(), anchor)
        menu.menu.add(0, 1, 0, getString(R.string.player_quality))
        menu.menu.add(0, 2, 1, getString(R.string.player_speed))
        menu.setOnMenuItemClickListener { item ->
            when (item.itemId) {
                1 -> showQualityDialog()
                2 -> showSpeedDialog()
            }
            true
        }
        menu.show()
    }

    private fun showQualityDialog() {
        val p = player ?: return
        val hasVideoTracks = p.currentTracks.groups.any {
            it.type == C.TRACK_TYPE_VIDEO && it.length > 0
        }
        if (!hasVideoTracks) {
            android.widget.Toast.makeText(requireContext(), getString(R.string.player_quality),
                android.widget.Toast.LENGTH_SHORT).show()
            return
        }
        TrackSelectionDialogBuilder(
            requireContext(), getString(R.string.player_quality), p, C.TRACK_TYPE_VIDEO
        ).setAllowAdaptiveSelections(true).build().show()
    }

    private fun showSpeedDialog() {
        val labels = speeds.map { if (it == 1f) "1.0×" else "${it}×" }.toTypedArray()
        AlertDialog.Builder(requireContext())
            .setTitle(R.string.player_speed)
            .setSingleChoiceItems(labels, speedIndex) { d, which ->
                speedIndex = which
                player?.playbackParameters = PlaybackParameters(speeds[which])
                d.dismiss()
            }
            .show()
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun playWeb(url: String) {
        b.webView.visible()
        with(b.webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            mediaPlaybackRequiresUserGesture = false
        }
        b.webView.webChromeClient = WebChromeClient()
        b.webView.webViewClient = object : WebViewClient() {
            override fun onPageFinished(view: android.webkit.WebView?, url: String?) { b.progress.gone() }
        }
        b.webView.loadUrl(url)
    }

    /** Force landscape + immersive fullscreen for the streaming experience. */
    private fun enterFullscreenLandscape() {
        val activity = activity ?: return
        (activity as? androidx.appcompat.app.AppCompatActivity)?.supportActionBar?.hide()
        activity.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE
        val window = activity.window ?: return
        WindowCompat.setDecorFitsSystemWindows(window, false)
        WindowInsetsControllerCompat(window, window.decorView).apply {
            hide(WindowInsetsCompat.Type.systemBars())
            systemBarsBehavior =
                WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
        }
    }

    private fun exitFullscreenLandscape() {
        val activity = activity ?: return
        (activity as? androidx.appcompat.app.AppCompatActivity)?.supportActionBar?.show()
        activity.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
        val window = activity.window ?: return
        WindowCompat.setDecorFitsSystemWindows(window, true)
        WindowInsetsControllerCompat(window, window.decorView)
            .show(WindowInsetsCompat.Type.systemBars())
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
        if (isStream) exitFullscreenLandscape()
        player?.release(); player = null
        trackSelector = null
        _b?.webView?.destroy()
        _b = null
    }
}
