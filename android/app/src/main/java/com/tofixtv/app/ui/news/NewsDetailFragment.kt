package com.tofixtv.app.ui.news

import android.os.Bundle
import android.text.Html
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentNewsDetailBinding
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

class NewsDetailFragment : Fragment() {

    private var _b: FragmentNewsDetailBinding? = null
    private val b get() = _b!!

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentNewsDetailBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val item = try { Repository.get(requireContext()).newsDetail(id, AppState.lang) }
            catch (e: Exception) { null }
            b.progress.gone()
            if (item == null) return@launch
            b.scroll.visible()
            b.title.text = item.title
            b.time.text = relativeTime(item.createdAt)
            b.image.loadImage(Media.news(item.image))
            val html = item.body ?: item.desc ?: ""
            b.body.text = Html.fromHtml(html, Html.FROM_HTML_MODE_COMPACT)
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}

/** Extract a trailing numeric id from a slug like "spain-vs-austria-12345". */
fun idFromArg(arg: String?): Long {
    if (arg.isNullOrBlank()) return 0L
    arg.toLongOrNull()?.let { return it }
    val m = Regex("(\\d+)$").find(arg)
    return m?.groupValues?.get(1)?.toLongOrNull() ?: 0L
}
