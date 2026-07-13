package com.tofixtv.app.ui.home

import android.os.Bundle
import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.MatchListAdapter
import com.tofixtv.app.ui.common.groupMatches
import com.tofixtv.app.util.todayDate

/** Home = today's matches grouped by competition (favourites pinned first). */
class HomeFragment : BaseListFragment() {

    private val adapter = MatchListAdapter { match ->
        findNavController().navigate(
            R.id.matchCenterFragment, bundleOf("id" to match.identifier.toString())
        )
    }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val matches = repo.matches(todayDate(), lang)
        adapter.submit(groupMatches(matches))
        return matches.isNotEmpty()
    }
}
