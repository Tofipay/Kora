package com.tofixtv.app.ui.scorers

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.ScorerAdapter
import com.tofixtv.app.ui.news.idFromArg

/** Top scorers for a given competition (defaults to the Premier League). */
class ScorersFragment : BaseListFragment() {

    private val adapter = ScorerAdapter { s ->
        val id = s.player?.id ?: s.playerId ?: 0L
        if (id > 0) findNavController().navigate(R.id.playerFragment, bundleOf("id" to id.toString()))
    }
    private val leagueId: Long
        get() = idFromArg(arguments?.getString("league")).let { if (it > 0) it else 900326L }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val data = repo.standings(leagueId, lang)
        val rows = data?.scorers.orEmpty()
        adapter.submit(rows)
        return rows.isNotEmpty()
    }
}
