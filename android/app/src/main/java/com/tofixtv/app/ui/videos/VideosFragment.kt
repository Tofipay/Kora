package com.tofixtv.app.ui.videos

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.VideoAdapter

/** Highlights / videos feed. */
class VideosFragment : BaseListFragment() {

    private val adapter = VideoAdapter { v ->
        findNavController().navigate(R.id.videoPlayerFragment, bundleOf("id" to (v.id ?: 0L).toString()))
    }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val data = repo.videos("all", 1, "", lang)
        val items = data?.items.orEmpty()
        adapter.submit(items)
        return items.isNotEmpty()
    }
}
