package com.tofixtv.app.ui.channels

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.ChannelAdapter

/** TV channels list → plays the channel's first stream URL. */
class ChannelsFragment : BaseListFragment() {

    private val adapter = ChannelAdapter { ch ->
        val url = ch.urls?.firstOrNull() ?: return@ChannelAdapter
        findNavController().navigate(
            R.id.watchFragment, bundleOf("url" to url, "title" to (ch.name ?: ""))
        )
    }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val list = repo.channels(lang)
        adapter.submit(list)
        return list.isNotEmpty()
    }
}
