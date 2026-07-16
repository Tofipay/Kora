package com.tofixtv.app.ui.notifications

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.tofixtv.app.data.local.SettingsStore
import com.tofixtv.app.data.model.NotifyTopic
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentListBinding
import com.tofixtv.app.fcm.FcmRegistrar
import com.tofixtv.app.ui.common.TopicAdapter
import com.tofixtv.app.util.*
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

/** Notifications — subscribe to a competition's push topic (FCM topics). */
class NotificationsFragment : Fragment() {

    private var _b: FragmentListBinding? = null
    private val b get() = _b!!
    private lateinit var store: SettingsStore
    private val subscribed = HashSet<String>()

    private val adapter by lazy {
        TopicAdapter(
            isSubscribed = { slug -> subscribed.contains(slug) },
            onToggle = { topic, checked -> toggle(topic, checked) }
        )
    }

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentListBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        store = SettingsStore(requireContext())
        b.recycler.layoutManager = LinearLayoutManager(requireContext())
        b.recycler.adapter = adapter
        b.swipeRefresh.setOnRefreshListener { loadTopics() }
        b.retryButton.setOnClickListener { loadTopics() }
        loadTopics()
    }

    private fun loadTopics() {
        b.progress.visible(); b.emptyState.gone()
        viewLifecycleOwner.lifecycleScope.launch {
            subscribed.clear()
            subscribed.addAll(store.topics.first())
            val leagues = try { Repository.get(requireContext()).leagues(AppState.lang) }
            catch (e: Exception) { emptyList() }
            val topics = ArrayList<NotifyTopic>()
            topics.add(NotifyTopic("all", getString(com.tofixtv.app.R.string.notify_all), null))
            leagues.forEach { lg ->
                if (lg.identifier > 0) topics.add(
                    NotifyTopic("lg_${lg.identifier}", lg.displayName, lg.image)
                )
            }
            adapter.submit(topics)
            b.progress.gone()
            b.swipeRefresh.isRefreshing = false
            b.emptyState.showIf(topics.size <= 1)
        }
    }

    private fun toggle(topic: NotifyTopic, checked: Boolean) {
        if (checked) {
            subscribed.add(topic.slug); FcmRegistrar.subscribe(topic.slug)
        } else {
            subscribed.remove(topic.slug); FcmRegistrar.unsubscribe(topic.slug)
        }
        viewLifecycleOwner.lifecycleScope.launch {
            store.setTopics(HashSet(subscribed))
            // Mirror the updated topics to the backend subscriber row.
            FcmRegistrar.token(requireContext())?.let { token ->
                Repository.get(requireContext()).registerPush(token, HashSet(subscribed))
            }
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
