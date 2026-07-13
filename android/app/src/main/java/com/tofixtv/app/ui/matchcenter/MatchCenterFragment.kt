package com.tofixtv.app.ui.matchcenter

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.tofixtv.app.BuildConfig
import com.tofixtv.app.R
import com.tofixtv.app.data.model.Match
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentMatchCenterBinding
import com.tofixtv.app.ui.common.EventAdapter
import com.tofixtv.app.ui.news.idFromArg
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/** Match Center — score hero, info, events, and a Watch button (web player). */
class MatchCenterFragment : Fragment() {

    private var _b: FragmentMatchCenterBinding? = null
    private val b get() = _b!!
    private val events = EventAdapter()

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentMatchCenterBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        b.eventsRecycler.layoutManager = LinearLayoutManager(requireContext())
        b.eventsRecycler.adapter = events

        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val m = try { Repository.get(requireContext()).matchInfo(id, AppState.lang) }
            catch (e: Exception) { null }
            b.progress.gone()
            if (m == null) { b.errorText.visible(); return@launch }
            render(m, id)
        }
    }

    private fun render(m: Match, id: Long) {
        b.scroll.visible()
        b.leagueName.text = m.championship?.displayName ?: ""
        b.homeName.text = m.homeTeam.displayName
        b.awayName.text = m.awayTeam.displayName
        b.homeLogo.loadImage(Media.team(m.homeTeam.image))
        b.awayLogo.loadImage(Media.team(m.awayTeam.image))

        val state = MatchState.of(m, formatTime12(m.matchTime))
        if (state.started) {
            b.scoreText.text = "${m.homeScores ?: 0}  -  ${m.awayScores ?: 0}"
        } else {
            b.scoreText.text = "-  -"
        }
        b.statusText.text = state.label.ifBlank { getString(R.string.today) }

        val info = buildString {
            m.championship?.displayName?.let { if (it.isNotBlank()) append("البطولة: $it\n") }
            m.round?.let { if (it.isNotBlank()) append("الجولة: $it\n") }
            m.stadium?.let { if (it.isNotBlank()) append("الملعب: $it\n") }
            m.matchTime?.let { append("الوقت: ${formatTime12(it)}\n") }
        }.trim()
        b.infoText.text = info

        events.submit(m.events.orEmpty().filter { (it.type ?: 0) != 100 }.take(50))

        // Watch — reuses the full web player (/watch/{id}) with all servers.
        b.watchButton.visible()
        b.watchButton.setOnClickListener {
            val url = "${BuildConfig.API_BASE_URL}watch/$id"
            findNavController().navigate(
                R.id.watchFragment,
                bundleOf("url" to url, "title" to "${m.homeTeam.displayName} - ${m.awayTeam.displayName}")
            )
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
