package com.tofixtv.app.ui.favorites

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.tofixtv.app.R
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentListBinding
import com.tofixtv.app.ui.common.FavoriteAdapter
import com.tofixtv.app.util.gone
import com.tofixtv.app.util.showIf
import kotlinx.coroutines.launch

/** Favorites — teams / leagues / players the user pinned (Room-backed). */
class FavoritesFragment : Fragment() {

    private var _b: FragmentListBinding? = null
    private val b get() = _b!!
    private val repo get() = Repository.get(requireContext())

    private val adapter = FavoriteAdapter(
        onClick = { fav ->
            val dest = when (fav.type) {
                "team" -> R.id.teamFragment
                "player" -> R.id.playerFragment
                "league" -> R.id.standingsFragment
                "match" -> R.id.matchCenterFragment
                else -> R.id.homeFragment
            }
            val argKey = if (fav.type == "league") "league" else "id"
            findNavController().navigate(dest, bundleOf(argKey to fav.refId.toString()))
        },
        onRemove = { fav ->
            viewLifecycleOwner.lifecycleScope.launch { repo.removeFavorite(fav.key) }
        }
    )

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentListBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.recycler.layoutManager = LinearLayoutManager(requireContext())
        b.recycler.adapter = adapter
        b.progress.gone()
        b.swipeRefresh.isEnabled = false
        b.emptyState.findViewById<View>(R.id.retryButton)?.visibility = View.GONE

        viewLifecycleOwner.lifecycleScope.launch {
            repo.favorites().collect { list ->
                adapter.submit(list)
                b.emptyState.showIf(list.isEmpty())
            }
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
