package com.tofixtv.app.ui.leagues

import androidx.core.os.bundleOf
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.ui.common.BaseListFragment
import com.tofixtv.app.ui.common.LeagueAdapter

/** Competitions list → opens a league's standings. */
class LeaguesFragment : BaseListFragment() {

    private val adapter = LeagueAdapter { league ->
        findNavController().navigate(
            R.id.standingsFragment, bundleOf("league" to league.identifier.toString())
        )
    }

    override fun setupRecycler(recycler: RecyclerView) { recycler.adapter = adapter }

    override suspend fun load(): Boolean {
        val list = repo.leagues(lang)
        adapter.submit(list)
        return list.isNotEmpty()
    }
}
