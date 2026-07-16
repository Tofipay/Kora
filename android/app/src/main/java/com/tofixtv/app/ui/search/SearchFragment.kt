package com.tofixtv.app.ui.search

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.view.inputmethod.EditorInfo
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.tofixtv.app.R
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentSearchBinding
import com.tofixtv.app.ui.common.SearchAdapter
import com.tofixtv.app.util.*
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class SearchFragment : Fragment() {

    private var _b: FragmentSearchBinding? = null
    private val b get() = _b!!
    private var searchJob: Job? = null

    private val adapter = SearchAdapter { type, id ->
        val dest = when (type) {
            "team" -> R.id.teamFragment
            else -> R.id.playerFragment
        }
        findNavController().navigate(dest, bundleOf("id" to id.toString()))
    }

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentSearchBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.listContent.recycler.layoutManager = LinearLayoutManager(requireContext())
        b.listContent.recycler.adapter = adapter
        b.listContent.emptyState.visible()

        b.searchInput.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_SEARCH) {
                doSearch(b.searchInput.text?.toString().orEmpty()); true
            } else false
        }
        b.searchInput.addTextChangedListener(object : android.text.TextWatcher {
            override fun afterTextChanged(sd: android.text.Editable?) {
                val q = sd?.toString().orEmpty()
                searchJob?.cancel()
                if (q.length < 2) return
                searchJob = viewLifecycleOwner.lifecycleScope.launch {
                    delay(350); doSearch(q)
                }
            }
            override fun beforeTextChanged(p0: CharSequence?, p1: Int, p2: Int, p3: Int) {}
            override fun onTextChanged(p0: CharSequence?, p1: Int, p2: Int, p3: Int) {}
        })
    }

    private fun doSearch(query: String) {
        if (query.isBlank()) return
        b.listContent.progress.visible()
        b.listContent.emptyState.gone()
        viewLifecycleOwner.lifecycleScope.launch {
            val data = try { Repository.get(requireContext()).search(query, AppState.lang) }
            catch (e: Exception) { null }
            val rows = ArrayList<SearchAdapter.Row>()
            data?.teams?.forEach {
                rows.add(SearchAdapter.Row("team", it.identifier, it.displayName, "فريق", Media.team(it.image)))
            }
            data?.player?.forEach {
                rows.add(SearchAdapter.Row("player", it.id ?: 0L, it.displayName, it.teamName ?: "لاعب", Media.player(it.image)))
            }
            adapter.submit(rows)
            b.listContent.progress.gone()
            b.listContent.emptyState.showIf(rows.isEmpty())
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
