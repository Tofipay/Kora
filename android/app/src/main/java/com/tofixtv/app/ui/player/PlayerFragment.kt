package com.tofixtv.app.ui.player

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentPlayerBinding
import com.tofixtv.app.ui.news.idFromArg
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/** Player profile page (vitals scraped/served by the backend). */
class PlayerFragment : Fragment() {

    private var _b: FragmentPlayerBinding? = null
    private val b get() = _b!!

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentPlayerBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val p = try { Repository.get(requireContext()).player(id, "", AppState.lang) }
            catch (e: Exception) { null }
            b.progress.gone()
            if (p == null) return@launch
            b.scroll.visible()
            b.name.text = p.displayName
            b.team.text = p.teamName ?: ""
            b.photo.loadImage(Media.player(p.image), com.tofixtv.app.R.drawable.ic_scorers)
            b.details.text = buildString {
                p.position?.let { if (it.isNotBlank()) append("المركز: $it\n") }
                p.nationality?.let { if (it.isNotBlank()) append("الجنسية: $it\n") }
                p.age?.let { if (it.isNotBlank()) append("العمر: $it\n") }
            }.trim()
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
