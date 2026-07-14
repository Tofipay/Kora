package com.tofixtv.app.ui.videos

import android.annotation.SuppressLint
import android.app.PictureInPictureParams
import android.content.pm.ActivityInfo
import android.os.Build
import android.os.Bundle
import android.util.Rational
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.webkit.WebSettings
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.media3.common.MediaItem
import androidx.media3.exoplayer.ExoPlayer
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.core.os.bundleOf
import com.tofixtv.app.MainActivity
import com.tofixtv.app.R
import com.tofixtv.app.data.model.Video
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentVideoPlayerBinding
import com.tofixtv.app.ui.common.VideoAdapter
import com.tofixtv.app.ui.news.idFromArg
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

class VideoPlayerFragment : Fragment(), MainActivity.PipAware {

    private var _b: FragmentVideoPlayerBinding? = null
    private val b get() = _b!!
    private var player: ExoPlayer? = null
    private var playingDirect = false
    private var isFull = false
    private var collapsedHeight = 0

    private val related = VideoAdapter { v ->
        findNavController().navigate(R.id.videoPlayerFragment, bundleOf("id" to (v.id ?: 0L).toString()))
    }

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentVideoPlayerBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.relatedRecycler.layoutManager = LinearLayoutManager(requireContext())
        b.relatedRecycler.adapter = related

        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val repo = Repository.get(requireContext())
            // Fetch the requested video directly by id (deep links / notifications
            // may reference an item not on page 1), then load the feed for related.
            val byId = try { repo.videoById(id, AppState.lang) } catch (e: Exception) { null }
            val data = try { repo.videos("all", 1, "", AppState.lang) } catch (e: Exception) { null }
            b.progress.gone()
            val items = data?.items.orEmpty()
            val video = byId ?: items.firstOrNull { it.id == id } ?: items.firstOrNull()
            related.submit(items.filter { it.id != video?.id }.take(10))
            video?.let { render(it) }
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun render(v: Video) {
        b.videoTitle.text = v.title
        val direct = v.mediaUrl ?: v.videoUrl?.takeIf { it.endsWith(".mp4") || it.contains(".m3u8") }
        if (!direct.isNullOrBlank()) {
            playingDirect = true
            b.webView.gone(); b.playerView.visible(); b.btnVideoFull.visible()
            player = ExoPlayer.Builder(requireContext()).build().also {
                b.playerView.player = it
                it.setMediaItem(MediaItem.fromUri(direct))
                it.prepare(); it.playWhenReady = true
            }
            b.btnVideoFull.setOnClickListener { toggleFullscreen() }
        } else {
            // Embed (YouTube / X) → WebView.
            val embed = when {
                !v.youtubeId.isNullOrBlank() -> "https://www.youtube.com/embed/${v.youtubeId}?autoplay=1&playsinline=1"
                !v.embedIframe.isNullOrBlank() -> v.embedIframe
                !v.tweetId.isNullOrBlank() -> "https://platform.twitter.com/embed/Tweet.html?id=${v.tweetId}"
                // Btolat and other providers expose a mobile web player page.
                !v.videoUrl.isNullOrBlank() -> v.videoUrl
                else -> null
            }
            b.playerView.gone(); b.webView.visible()
            b.webView.settings.javaScriptEnabled = true
            b.webView.settings.domStorageEnabled = true
            b.webView.settings.mediaPlaybackRequiresUserGesture = false
            b.webView.settings.cacheMode = WebSettings.LOAD_DEFAULT
            b.webView.webChromeClient = android.webkit.WebChromeClient()
            if (embed != null) b.webView.loadUrl(embed) else b.progress.gone()
        }
    }

    /** Toggle an immersive landscape fullscreen for the inline video player. */
    private fun toggleFullscreen() {
        val activity = activity ?: return
        val window = activity.window ?: return
        val container = b.playerContainer
        if (!isFull) {
            isFull = true
            collapsedHeight = container.height.takeIf { it > 0 } ?: collapsedHeight
            (activity as? androidx.appcompat.app.AppCompatActivity)?.supportActionBar?.hide()
            activity.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE
            WindowCompat.setDecorFitsSystemWindows(window, false)
            WindowInsetsControllerCompat(window, window.decorView).apply {
                hide(WindowInsetsCompat.Type.systemBars())
                systemBarsBehavior = WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
            }
            container.updateHeight(ViewGroup.LayoutParams.MATCH_PARENT)
            b.playerView.layoutParams = b.playerView.layoutParams.apply {
                height = ViewGroup.LayoutParams.MATCH_PARENT
            }
        } else {
            isFull = false
            (activity as? androidx.appcompat.app.AppCompatActivity)?.supportActionBar?.show()
            activity.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
            WindowCompat.setDecorFitsSystemWindows(window, true)
            WindowInsetsControllerCompat(window, window.decorView)
                .show(WindowInsetsCompat.Type.systemBars())
            val h = if (collapsedHeight > 0) collapsedHeight else dp(220)
            container.updateHeight(ViewGroup.LayoutParams.WRAP_CONTENT)
            b.playerView.layoutParams = b.playerView.layoutParams.apply { height = h }
        }
    }

    private fun View.updateHeight(h: Int) {
        layoutParams = layoutParams.apply { height = h }
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    // ---- Picture in Picture (direct playback only) ----
    override fun shouldEnterPip() = playingDirect && player?.isPlaying == true

    override fun onEnterPip() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val params = PictureInPictureParams.Builder()
                .setAspectRatio(Rational(16, 9))
                .build()
            requireActivity().enterPictureInPictureMode(params)
        }
    }

    override fun onPause() {
        super.onPause()
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.N || !requireActivity().isInPictureInPictureMode) {
            player?.pause()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        if (isFull) {
            activity?.let { act ->
                (act as? androidx.appcompat.app.AppCompatActivity)?.supportActionBar?.show()
                act.requestedOrientation = ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED
                act.window?.let { w ->
                    WindowCompat.setDecorFitsSystemWindows(w, true)
                    WindowInsetsControllerCompat(w, w.decorView).show(WindowInsetsCompat.Type.systemBars())
                }
            }
            isFull = false
        }
        player?.release(); player = null
        _b = null
    }
}
