package com.tofixtv.app.ui.videos

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.android.material.chip.Chip
import com.tofixtv.app.R
import com.tofixtv.app.data.model.VideoCategory
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentVideosBinding
import com.tofixtv.app.ui.common.VideoAdapter
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/** Videos with championship-category chips + infinite-scroll pagination. */
class VideosFragment : Fragment() {

    private var _b: FragmentVideosBinding? = null
    private val b get() = _b!!
    private val repo get() = Repository.get(requireContext())

    private val adapter = VideoAdapter { v ->
        findNavController().navigate(R.id.videoPlayerFragment, bundleOf("id" to (v.id ?: 0L).toString()))
    }

    private var champ = "all"
    private var page = 1
    private var hasNext = false
    private var loading = false
    private var chipsBuilt = false

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentVideosBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val lm = LinearLayoutManager(requireContext())
        b.listContent.recycler.layoutManager = lm
        b.listContent.recycler.adapter = adapter
        b.listContent.swipeRefresh.setOnRefreshListener { reload() }
        b.listContent.retryButton.setOnClickListener { reload() }

        // Infinite scroll: load the next page as the user nears the bottom.
        b.listContent.recycler.addOnScrollListener(object : RecyclerView.OnScrollListener() {
            override fun onScrolled(rv: RecyclerView, dx: Int, dy: Int) {
                if (dy <= 0 || loading || !hasNext) return
                if (lm.findLastVisibleItemPosition() >= adapter.itemCount - 3) loadNext()
            }
        })
        reload()
    }

    private fun reload() {
        page = 1
        b.listContent.progress.visible()
        b.listContent.emptyState.gone()
        viewLifecycleOwner.lifecycleScope.launch {
            val data = try { repo.videos(champ, 1, "", AppState.lang) } catch (e: Exception) { null }
            b.listContent.progress.gone()
            b.listContent.swipeRefresh.isRefreshing = false
            val items = data?.items.orEmpty()
            adapter.submit(items)
            hasNext = data?.hasNext ?: false
            if (!chipsBuilt) data?.categories?.let { buildChips(it) }
            b.listContent.emptyState.showIf(items.isEmpty())
        }
    }

    private fun loadNext() {
        loading = true
        page += 1
        viewLifecycleOwner.lifecycleScope.launch {
            val data = try { repo.videos(champ, page, "", AppState.lang) } catch (e: Exception) { null }
            adapter.append(data?.items.orEmpty())
            hasNext = data?.hasNext ?: false
            loading = false
        }
    }

    private fun buildChips(categories: List<VideoCategory>) {
        chipsBuilt = true
        b.champChips.removeAllViews()
        categories.forEach { cat ->
            val id = cat.id ?: return@forEach
            val chip = Chip(requireContext()).apply {
                text = cat.title ?: id
                isCheckable = true
                isChecked = id == champ
                setOnClickListener {
                    if (champ != id) { champ = id; reload() }
                }
            }
            b.champChips.addView(chip)
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
