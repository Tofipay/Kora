package com.tofixtv.app.ui.common

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.RecyclerView
import com.tofixtv.app.R
import com.tofixtv.app.data.model.League
import com.tofixtv.app.data.model.Match
import com.tofixtv.app.databinding.ItemHeaderBinding
import com.tofixtv.app.databinding.ItemMatchBinding
import com.tofixtv.app.util.Media
import com.tofixtv.app.util.MatchState
import com.tofixtv.app.util.formatTime12
import com.tofixtv.app.util.loadImage

/** A row in a grouped match list: a league header or a fixture. */
sealed class MatchRow {
    data class Header(val league: League) : MatchRow()
    data class Fixture(val match: Match) : MatchRow()
}

/** Groups a flat match list by championship, ready for the adapter. */
fun groupMatches(matches: List<Match>): List<MatchRow> {
    val rows = ArrayList<MatchRow>()
    matches.groupBy { it.championship?.identifier ?: 0L }
        .forEach { (_, group) ->
            group.firstOrNull()?.championship?.let { rows.add(MatchRow.Header(it)) }
            group.forEach { rows.add(MatchRow.Fixture(it)) }
        }
    return rows
}

class MatchListAdapter(
    private val onClick: (Match) -> Unit
) : RecyclerView.Adapter<RecyclerView.ViewHolder>() {

    private val items = mutableListOf<MatchRow>()

    fun submit(rows: List<MatchRow>) {
        items.clear(); items.addAll(rows); notifyDataSetChanged()
    }

    override fun getItemViewType(position: Int) =
        if (items[position] is MatchRow.Header) 0 else 1

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): RecyclerView.ViewHolder {
        val inflater = LayoutInflater.from(parent.context)
        return if (viewType == 0)
            HeaderVH(ItemHeaderBinding.inflate(inflater, parent, false))
        else
            MatchVH(ItemMatchBinding.inflate(inflater, parent, false))
    }

    override fun getItemCount() = items.size

    override fun onBindViewHolder(holder: RecyclerView.ViewHolder, position: Int) {
        when (val row = items[position]) {
            is MatchRow.Header -> (holder as HeaderVH).bind(row.league)
            is MatchRow.Fixture -> (holder as MatchVH).bind(row.match)
        }
    }

    inner class HeaderVH(private val b: ItemHeaderBinding) : RecyclerView.ViewHolder(b.root) {
        fun bind(league: League) {
            b.title.text = league.displayName
            b.logo.loadImage(Media.league(league.image), R.drawable.ic_leagues)
        }
    }

    inner class MatchVH(private val b: ItemMatchBinding) : RecyclerView.ViewHolder(b.root) {
        fun bind(m: Match) {
            b.homeName.text = m.homeTeam.displayName
            b.awayName.text = m.awayTeam.displayName
            b.homeLogo.loadImage(Media.team(m.homeTeam.image))
            b.awayLogo.loadImage(Media.team(m.awayTeam.image))

            val state = MatchState.of(m, formatTime12(m.matchTime))
            when (state.key) {
                MatchState.Key.UPCOMING -> {
                    b.centerText.text = state.label.ifBlank { "—" }
                    b.statusText.text = ""
                }
                else -> {
                    b.centerText.text = "${m.homeScores ?: 0} - ${m.awayScores ?: 0}"
                    b.statusText.text = state.label
                }
            }
            b.root.setOnClickListener { onClick(m) }
        }
    }
}
