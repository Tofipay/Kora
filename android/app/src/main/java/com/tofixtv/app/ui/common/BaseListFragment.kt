package com.tofixtv.app.ui.common

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentListBinding
import com.tofixtv.app.util.AppState
import com.tofixtv.app.util.gone
import com.tofixtv.app.util.showIf
import com.tofixtv.app.util.visible
import kotlinx.coroutines.launch

/**
 * Common list screen: swipe-to-refresh + progress + empty/retry states.
 * Subclasses set the adapter and implement [load].
 */
abstract class BaseListFragment : Fragment() {

    private var _binding: FragmentListBinding? = null
    protected val binding get() = _binding!!
    protected val repo get() = Repository.get(requireContext())
    protected val lang get() = AppState.lang

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, s: Bundle?): View {
        _binding = FragmentListBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        binding.recycler.layoutManager = LinearLayoutManager(requireContext())
        setupRecycler(binding.recycler)
        binding.swipeRefresh.setOnRefreshListener { refresh() }
        binding.retryButton.setOnClickListener { refresh() }
        refresh()
    }

    abstract fun setupRecycler(recycler: RecyclerView)
    abstract suspend fun load(): Boolean   // returns true if any data was shown

    protected fun refresh() {
        showLoading(true)
        viewLifecycleOwner.lifecycleScope.launch {
            val hasData = try { load() } catch (e: Exception) { false }
            showLoading(false)
            binding.emptyState.showIf(!hasData)
        }
    }

    private fun showLoading(loading: Boolean) {
        if (loading && !binding.swipeRefresh.isRefreshing) binding.progress.visible() else binding.progress.gone()
        if (!loading) binding.swipeRefresh.isRefreshing = false
        if (loading) binding.emptyState.gone()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
