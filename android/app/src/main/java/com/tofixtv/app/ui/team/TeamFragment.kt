package com.tofixtv.app.ui.team

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.MatchListAdapter
import com.tofixtv.app.ui.common.groupMatches
import com.tofixtv.app.ui.news.idFromArg

/** Team page — recent results + upcoming fixtures. */
class TeamFragment : BaseListFragment() {

    private val adapter = MatchListAdapter { m ->
        findNavController().navigate(R.id.matchCenterFragment, bundleOf("id" to m.identifier.toString()))
    }
    private val teamId get() = idFromArg(arguments?.getString("id"))

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val data = repo.team(teamId, lang)
        val all = (data?.results.orEmpty()) + (data?.fixtures.orEmpty())
        adapter.submit(groupMatches(all))
        return all.isNotEmpty()
    }
}
