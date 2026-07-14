package com.tofixtv.app.ui.channels

import android.widget.Toast
import androidx.core.os.bundleOf
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.data.model.Channel
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.ChannelAdapter
import kotlinx.coroutines.launch

/**
 * TV channels list. On click the channel URL is resolved server-side (Yacine
 * links are decrypted into a first-party HLS proxy URL) and played in ExoPlayer.
 */
class ChannelsFragment : BaseListFragment() {

    private val adapter = ChannelAdapter { ch -> open(ch) }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val list = repo.channels(lang)
        adapter.submit(list)
        return list.isNotEmpty()
    }

    private fun open(ch: Channel) {
        val raw = ch.urls?.firstOrNull { it.isNotBlank() } ?: return
        Toast.makeText(requireContext(), getString(R.string.loading), Toast.LENGTH_SHORT).show()
        viewLifecycleOwner.lifecycleScope.launch {
            val sources = try { repo.resolveStream(raw, lang) } catch (e: Exception) { emptyList() }
            val source = sources.firstOrNull { !it.url.isNullOrBlank() }
            findNavController().navigate(
                R.id.watchFragment,
                bundleOf(
                    "url" to (source?.url ?: raw),
                    "title" to (ch.name ?: ""),
                    "type" to (source?.type ?: "auto")
                )
            )
        }
    }
}
