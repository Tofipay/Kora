package com.tofixtv.app.ui.standings

import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.StandingAdapter
import com.tofixtv.app.ui.news.idFromArg

/** League table for a given competition (defaults to the Premier League). */
class StandingsFragment : BaseListFragment() {

    private val adapter = StandingAdapter()
    private val leagueId: Long
        get() = idFromArg(arguments?.getString("league")).let { if (it > 0) it else 900326L }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val data = repo.standings(leagueId, lang)
        val rows = data?.standings.orEmpty()
        adapter.submit(rows)
        return rows.isNotEmpty()
    }
}
