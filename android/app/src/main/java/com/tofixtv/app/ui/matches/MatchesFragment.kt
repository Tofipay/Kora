package com.tofixtv.app.ui.matches

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.android.material.chip.Chip
import com.tofixtv.app.R
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentMatchesBinding
import com.tofixtv.app.ui.common.MatchListAdapter
import com.tofixtv.app.ui.common.groupMatches
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/** Matches screen with day tabs: Live · Yesterday · Today · Tomorrow. */
class MatchesFragment : Fragment() {

    private var _b: FragmentMatchesBinding? = null
    private val b get() = _b!!
    private val repo get() = Repository.get(requireContext())

    private val adapter = MatchListAdapter { m ->
        findNavController().navigate(R.id.matchCenterFragment, bundleOf("id" to m.identifier.toString()))
    }

    private var mode = "today"

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentMatchesBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.listContent.recycler.layoutManager = LinearLayoutManager(requireContext())
        b.listContent.recycler.adapter = adapter
        b.listContent.swipeRefresh.setOnRefreshListener { load() }
        b.listContent.retryButton.setOnClickListener { load() }

        val tabs = listOf(
            "live" to getString(R.string.nav_live),
            "yesterday" to getString(R.string.yesterday),
            "today" to getString(R.string.today),
            "tomorrow" to getString(R.string.tomorrow),
        )
        tabs.forEach { (key, label) ->
            val chip = Chip(requireContext()).apply {
                text = label
                isCheckable = true
                isChecked = key == "today"
                setOnClickListener { mode = key; load() }
            }
            b.dateChips.addView(chip)
        }
        load()
    }

    private fun load() {
        b.listContent.progress.visible()
        b.listContent.emptyState.gone()
        viewLifecycleOwner.lifecycleScope.launch {
            val matches = try {
                when (mode) {
                    "live" -> repo.live(lang())
                    "yesterday" -> repo.matches(dateOffset(-1), lang())
                    "tomorrow" -> repo.matches(dateOffset(1), lang())
                    else -> repo.matches(todayDate(), lang())
                }
            } catch (e: Exception) { emptyList() }
            adapter.submit(groupMatches(matches))
            b.listContent.progress.gone()
            b.listContent.swipeRefresh.isRefreshing = false
            b.listContent.emptyState.showIf(matches.isEmpty())
        }
    }

    private fun lang() = AppState.lang

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
