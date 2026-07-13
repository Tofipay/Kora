package com.tofixtv.app.ui.news

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.NewsAdapter

/** Latest news list. */
class NewsFragment : BaseListFragment() {

    private val adapter = NewsAdapter { item ->
        findNavController().navigate(R.id.newsDetailFragment, bundleOf("id" to (item.id ?: 0L).toString()))
    }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val list = repo.news(1, lang)
        adapter.submit(list)
        return list.isNotEmpty()
    }
}
