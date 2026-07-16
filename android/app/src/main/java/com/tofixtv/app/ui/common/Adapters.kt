package com.tofixtv.app.ui.common

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.data.local.FavoriteEntity
import com.tofixtv.app.data.model.*
import com.tofixtv.app.databinding.*
import com.tofixtv.app.util.*

/** Minimal single-viewtype RecyclerView adapter helper. */
abstract class SimpleAdapter<T, VB : androidx.viewbinding.ViewBinding> :
    RecyclerView.Adapter<SimpleAdapter.VH<VB>>() {

    protected val items = mutableListOf<T>()

    fun submit(list: List<T>) { items.clear(); items.addAll(list); notifyDataSetChanged() }

    fun append(list: List<T>) {
        val start = items.size; items.addAll(list); notifyItemRangeInserted(start, list.size)
    }
    fun currentItems(): List<T> = items.toList()

    class VH<VB : androidx.viewbinding.ViewBinding>(val b: VB) : RecyclerView.ViewHolder(b.root)

    abstract fun inflate(inflater: LayoutInflater, parent: ViewGroup): VB
    abstract fun bind(b: VB, item: T)

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH<VB> =
        VH(inflate(LayoutInflater.from(parent.context), parent))

    override fun onBindViewHolder(holder: VH<VB>, position: Int) = bind(holder.b, items[position])
    override fun getItemCount() = items.size
}

class NewsAdapter(private val onClick: (NewsItem) -> Unit) :
    SimpleAdapter<NewsItem, ItemNewsBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemNewsBinding.inflate(i, p, false)
    override fun bind(b: ItemNewsBinding, item: NewsItem) {
        b.title.text = item.title
        b.time.text = relativeTime(item.createdAt?.raw)
        b.image.loadImage(Media.news(item.image))
        b.root.setOnClickListener { onClick(item) }
    }
}

class VideoAdapter(private val onClick: (Video) -> Unit) :
    SimpleAdapter<Video, ItemVideoBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemVideoBinding.inflate(i, p, false)
    override fun bind(b: ItemVideoBinding, item: Video) {
        b.title.text = item.title
        b.champ.text = item.champTitle
        b.thumbnail.loadImage(item.thumbnail)
        b.root.setOnClickListener { onClick(item) }
    }
}

class StandingAdapter : SimpleAdapter<StandingRow, ItemStandingBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemStandingBinding.inflate(i, p, false)
    override fun bind(b: ItemStandingBinding, item: StandingRow) {
        val pos = items.indexOf(item) + 1
        b.pos.text = pos.toString()
        b.team.text = item.team?.displayName ?: "—"
        b.logo.loadImage(Media.team(item.team?.image))
        b.played.text = (item.play ?: 0).toString()
        val diff = item.diff ?: ((item.forGoals ?: 0) - (item.against ?: 0))
        b.diff.text = if (diff > 0) "+$diff" else diff.toString()
        b.points.text = (item.points ?: 0).toString()
    }
}

class ScorerAdapter(private val onClick: (Scorer) -> Unit) :
    SimpleAdapter<Scorer, ItemScorerBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemScorerBinding.inflate(i, p, false)
    override fun bind(b: ItemScorerBinding, item: Scorer) {
        b.rank.text = (items.indexOf(item) + 1).toString()
        b.name.text = item.player?.displayName ?: "—"
        b.team.text = item.player?.teamName ?: ""
        b.photo.loadImage(Media.player(item.player?.image), R.drawable.ic_scorers)
        b.goals.text = (item.goals ?: 0).toString()
        b.root.setOnClickListener { onClick(item) }
    }
}

class LeagueAdapter(private val onClick: (League) -> Unit) :
    SimpleAdapter<League, ItemLeagueBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemLeagueBinding.inflate(i, p, false)
    override fun bind(b: ItemLeagueBinding, item: League) {
        b.name.text = item.displayName
        b.logo.loadImage(Media.league(item.image), R.drawable.ic_leagues)
        b.root.setOnClickListener { onClick(item) }
    }
}

class ChannelAdapter(private val onClick: (Channel) -> Unit) :
    SimpleAdapter<Channel, ItemChannelBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemChannelBinding.inflate(i, p, false)
    override fun bind(b: ItemChannelBinding, item: Channel) {
        b.name.text = item.name
        b.root.setOnClickListener { onClick(item) }
    }
}

class FavoriteAdapter(
    private val onClick: (FavoriteEntity) -> Unit,
    private val onRemove: (FavoriteEntity) -> Unit
) : SimpleAdapter<FavoriteEntity, ItemFavoriteBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemFavoriteBinding.inflate(i, p, false)
    override fun bind(b: ItemFavoriteBinding, item: FavoriteEntity) {
        b.title.text = item.title
        b.subtitle.text = item.subtitle ?: item.type
        b.logo.loadImage(item.image, R.drawable.ic_favorites)
        b.root.setOnClickListener { onClick(item) }
        b.removeBtn.setOnClickListener { onRemove(item) }
    }
}

class SearchAdapter(private val onClick: (String, Long) -> Unit) :
    RecyclerView.Adapter<SearchAdapter.VH>() {

    data class Row(val type: String, val id: Long, val title: String, val subtitle: String, val image: String?)
    private val items = mutableListOf<Row>()
    fun submit(list: List<Row>) { items.clear(); items.addAll(list); notifyDataSetChanged() }

    inner class VH(val b: ItemSearchBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
        VH(ItemSearchBinding.inflate(LayoutInflater.from(parent.context), parent, false))
    override fun getItemCount() = items.size
    override fun onBindViewHolder(holder: VH, position: Int) {
        val r = items[position]
        holder.b.title.text = r.title
        holder.b.subtitle.text = r.subtitle
        holder.b.logo.loadImage(r.image, R.drawable.ic_placeholder)
        holder.b.root.setOnClickListener { onClick(r.type, r.id) }
    }
}

class TopicAdapter(
    private val isSubscribed: (String) -> Boolean,
    private val onToggle: (NotifyTopic, Boolean) -> Unit
) : SimpleAdapter<NotifyTopic, ItemTopicBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemTopicBinding.inflate(i, p, false)
    override fun bind(b: ItemTopicBinding, item: NotifyTopic) {
        b.title.text = item.title
        b.logo.loadImage(Media.league(item.image), R.drawable.ic_leagues)
        b.toggle.setOnCheckedChangeListener(null)
        b.toggle.isChecked = isSubscribed(item.slug)
        b.toggle.setOnCheckedChangeListener { _, checked -> onToggle(item, checked) }
    }
}

class EventAdapter : SimpleAdapter<MatchEvent, ItemEventBinding>() {
    override fun inflate(i: LayoutInflater, p: ViewGroup) = ItemEventBinding.inflate(i, p, false)
    override fun bind(b: ItemEventBinding, item: MatchEvent) {
        val minute = item.minute ?: 0
        b.minute.text = if ((item.plus ?: 0) > 0) "$minute+${item.plus}'" else "$minute'"
        b.icon.text = eventEmoji(item.type ?: 0)
        b.player.text = item.player?.displayName ?: ""
        b.detail.text = eventLabel(item.type ?: 0) +
            (item.assist?.displayName?.let { if (it.isNotBlank()) " · $it" else "" } ?: "")
    }

    private fun eventEmoji(type: Int) = when (type) {
        1, 4 -> "⚽"       // goal
        2 -> "🟨"    // yellow
        3 -> "⚽"          // own goal
        5, 6, 7 -> "🟥" // red
        8 -> "🔁"    // sub
        21 -> "❌"         // missed pen
        else -> "•"
    }

    private fun eventLabel(type: Int) = when (type) {
        1 -> "هدف"
        2 -> "بطاقة صفراء"
        3 -> "هدف عكسي"
        4 -> "هدف من ركلة جزاء"
        5 -> "بطاقة صفراء ثانية"
        6, 7 -> "بطاقة حمراء"
        8 -> "تبديل"
        21 -> "ركلة جزاء ضائعة"
        else -> ""
    }
}
