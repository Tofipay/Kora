package com.tofixtv.app.ui.matchcenter

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.google.android.material.tabs.TabLayout
import com.tofixtv.app.BuildConfig
import com.tofixtv.app.R
import com.tofixtv.app.data.model.*
import com.tofixtv.app.data.repo.Repository
import com.tofixtv.app.databinding.FragmentMatchCenterBinding
import com.tofixtv.app.ui.news.idFromArg
import com.tofixtv.app.util.*
import kotlinx.coroutines.launch

/**
 * Match Center — a full match-detail screen mirroring the website: score hero,
 * live badge, and tabbed sections for الأحداث (events), التشكيلة (lineup),
 * الإحصائيات (stats), القنوات (channels) and الترتيب (standings + scorers).
 */
class MatchCenterFragment : Fragment() {

    private var _b: FragmentMatchCenterBinding? = null
    private val b get() = _b!!

    private val sections: List<LinearLayout> by lazy {
        listOf(b.secEvents, b.secLineup, b.secStats, b.secChannels, b.secStandings)
    }

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentMatchCenterBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        val id = idFromArg(arguments?.getString("id"))
        b.progress.visible()
        viewLifecycleOwner.lifecycleScope.launch {
            val full = try { Repository.get(requireContext()).matchFull(id, AppState.lang) }
            catch (e: Exception) { null }
            b.progress.gone()
            if (full?.match == null) { b.errorText.visible(); return@launch }
            render(full, id)
        }
    }

    private fun render(full: MatchFull, id: Long) {
        val m = full.match!!
        b.scroll.visible()
        bindHero(m, full)
        setupWatch(m, id)
        setupTabs()

        renderInfo(m)
        renderEvents(full.events.orEmpty())
        renderLineup(full.lineups, m)
        renderStats(full.stats.orEmpty(), m)
        renderChannels(full.channels.orEmpty())
        renderStandings(full.standings.orEmpty(), full.scorers.orEmpty(), m)
    }

    // ---------------- Hero ----------------
    private fun bindHero(m: Match, full: MatchFull) {
        b.leagueName.text = m.championship?.displayName ?: ""
        b.leagueLogo.loadImage(Media.league(m.championship?.image), R.drawable.ic_leagues)
        b.homeName.text = m.homeTeam.displayName
        b.awayName.text = m.awayTeam.displayName
        b.homeLogo.loadImage(Media.team(m.homeTeam.image))
        b.awayLogo.loadImage(Media.team(m.awayTeam.image))

        val state = MatchState.of(m, formatTime12(m.matchTime))
        val started = state.started || full.live
        b.scoreText.text = if (started) "${m.homeScores ?: 0}  -  ${m.awayScores ?: 0}" else "-  -"
        b.statusText.text = (full.status?.takeIf { it.isNotBlank() } ?: state.label)
            .ifBlank { getString(R.string.today) }
        b.liveBadge.showIf(full.live)
    }

    private fun setupWatch(m: Match, id: Long) {
        b.watchButton.setOnClickListener {
            val url = "${BuildConfig.API_BASE_URL}watch/$id"
            findNavController().navigate(
                R.id.watchFragment,
                bundleOf("url" to url, "title" to "${m.homeTeam.displayName} - ${m.awayTeam.displayName}")
            )
        }
    }

    // ---------------- Tabs ----------------
    private fun setupTabs() {
        if (b.tabs.tabCount > 0) return
        val titles = listOf(
            R.string.tab_events, R.string.tab_lineup, R.string.tab_stats,
            R.string.tab_channels, R.string.tab_standings
        )
        titles.forEach { b.tabs.addTab(b.tabs.newTab().setText(getString(it))) }
        b.tabs.addOnTabSelectedListener(object : TabLayout.OnTabSelectedListener {
            override fun onTabSelected(tab: TabLayout.Tab) = showSection(tab.position)
            override fun onTabUnselected(tab: TabLayout.Tab) {}
            override fun onTabReselected(tab: TabLayout.Tab) {}
        })
        showSection(0)
    }

    private fun showSection(index: Int) {
        sections.forEachIndexed { i, s -> s.showIf(i == index) }
    }

    // ---------------- Info ----------------
    private fun renderInfo(m: Match) {
        val rows = buildList {
            m.championship?.displayName?.takeIf { it.isNotBlank() }?.let { add(getString(R.string.mc_league) to it) }
            m.round?.takeIf { it.isNotBlank() && it != "0" }?.let { add(getString(R.string.mc_round) to it) }
            m.stadium?.takeIf { it.isNotBlank() }?.let { add(getString(R.string.mc_stadium) to it) }
            val time = formatTime12(m.matchTime)
            val dt = listOfNotNull(m.matchDate?.takeIf { it.isNotBlank() }, time.takeIf { it.isNotBlank() })
                .joinToString(" · ")
            if (dt.isNotBlank()) add(getString(R.string.mc_datetime) to dt)
        }
        b.infoContainer.removeAllViews()
        rows.forEach { (label, value) -> b.infoContainer.addView(infoRow(label, value)) }
    }

    private fun infoRow(label: String, value: String): View {
        val row = LinearLayout(requireContext()).apply {
            orientation = LinearLayout.HORIZONTAL
            setPadding(dp(14), dp(7), dp(14), dp(7))
        }
        row.addView(TextView(requireContext()).apply {
            text = label
            setTextColor(color(R.color.muted))
            textSize = 13f
            layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
        })
        row.addView(TextView(requireContext()).apply {
            text = value
            setTextColor(onSurface())
            textSize = 13f
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        })
        return row
    }

    // ---------------- Events ----------------
    private fun renderEvents(events: List<FullEvent>) {
        b.eventsContainer.removeAllViews()
        if (events.isEmpty()) { b.emptyEvents.visible(); return }
        b.emptyEvents.gone()
        events.forEach { ev ->
            val v = layoutInflater.inflate(R.layout.item_mc_event, b.eventsContainer, false)
            v.findViewById<View>(R.id.sideBar).setBackgroundColor(
                color(if (ev.isHome) R.color.brand_primary else R.color.brand_accent)
            )
            v.findViewById<TextView>(R.id.minute).text = ev.minute ?: ""
            v.findViewById<TextView>(R.id.icon).text = eventEmoji(ev.key)
            v.findViewById<TextView>(R.id.player).text = ev.player ?: ev.label ?: ""
            val detail = listOfNotNull(
                ev.label?.takeIf { it.isNotBlank() },
                ev.assist?.takeIf { it.isNotBlank() }?.let { "🅰 $it" }
            ).joinToString(" · ")
            v.findViewById<TextView>(R.id.detail).text = detail
            b.eventsContainer.addView(v)
        }
    }

    private fun eventEmoji(key: String?) = when (key) {
        "goal", "penalty", "owngoal" -> "⚽"
        "yellow" -> "🟨"
        "red", "second_yellow" -> "🟥"
        "sub" -> "🔁"
        "missed_pen" -> "❌"
        "cancelled" -> "🚫"
        else -> "•"
    }

    // ---------------- Lineup ----------------
    private fun renderLineup(lineups: Lineups?, m: Match) {
        b.lineupContainer.removeAllViews()
        val home = lineups?.home
        val away = lineups?.away
        val hasAny = (home?.starters?.isNotEmpty() == true) || (away?.starters?.isNotEmpty() == true) ||
            (home?.bench?.isNotEmpty() == true) || (away?.bench?.isNotEmpty() == true)
        if (!hasAny) { b.emptyLineup.visible(); return }
        b.emptyLineup.gone()
        addLineupSide(m.homeTeam.displayName, home)
        addLineupSide(m.awayTeam.displayName, away)
    }

    private fun addLineupSide(teamName: String, side: LineupSide?) {
        val starters = side?.starters.orEmpty()
        val bench = side?.bench.orEmpty()
        if (starters.isEmpty() && bench.isEmpty()) return

        val card = card()
        card.addView(sectionTitle(buildString {
            append(teamName.ifBlank { "—" })
            side?.formation?.takeIf { it.isNotBlank() }?.let { append("  ·  $it") }
        }))
        if (starters.isNotEmpty()) {
            card.addView(subTitle(getString(R.string.mc_starting_lineup)))
            starters.forEach { card.addView(lineupRow(it)) }
        }
        if (bench.isNotEmpty()) {
            card.addView(subTitle(getString(R.string.mc_substitutes)))
            bench.forEach { card.addView(lineupRow(it)) }
        }
        val lp = LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT
        ).apply { topMargin = dp(14) }
        b.lineupContainer.addView(card, lp)
    }

    private fun lineupRow(p: LineupPlayer): View {
        val v = layoutInflater.inflate(R.layout.item_mc_lineup, b.lineupContainer, false)
        v.findViewById<TextView>(R.id.number).text = (p.number ?: 0).takeIf { it > 0 }?.toString() ?: "-"
        v.findViewById<ImageView>(R.id.photo).loadImage(Media.player(p.image), R.drawable.ic_scorers)
        v.findViewById<TextView>(R.id.name).text = buildString {
            append(p.name ?: "—")
            if (p.captain) append("  (C)")
        }
        v.findViewById<TextView>(R.id.position).apply {
            text = p.position ?: ""
            showIf(!p.position.isNullOrBlank())
        }
        v.findViewById<TextView>(R.id.marks).text = buildString {
            if (p.goal > 0) append(if (p.goal > 1) "⚽×${p.goal}" else "⚽")
            if (p.yellow) append(" 🟨")
            if (p.red) append(" 🟥")
        }
        v.findViewById<TextView>(R.id.rating).apply {
            val r = p.rating
            if (!r.isNullOrBlank() && r != "0") { text = r; visible() } else gone()
        }
        return v
    }

    // ---------------- Stats ----------------
    private fun renderStats(stats: List<StatRow>, m: Match) {
        b.statHomeName.text = m.homeTeam.displayName
        b.statAwayName.text = m.awayTeam.displayName
        b.statsContainer.removeAllViews()
        if (stats.isEmpty()) { b.emptyStats.visible(); return }
        b.emptyStats.gone()
        stats.forEach { s ->
            val v = layoutInflater.inflate(R.layout.item_mc_stat, b.statsContainer, false)
            v.findViewById<TextView>(R.id.homeVal).text = s.home ?: "0"
            v.findViewById<TextView>(R.id.awayVal).text = s.away ?: "0"
            v.findViewById<TextView>(R.id.label).text = s.label ?: ""
            val hp = s.homePct.coerceIn(0, 100)
            v.findViewById<View>(R.id.fillHome).layoutParams =
                LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, hp.toFloat())
            v.findViewById<View>(R.id.fillAway).layoutParams =
                LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, (100 - hp).toFloat())
            b.statsContainer.addView(v)
        }
    }

    // ---------------- Channels ----------------
    private fun renderChannels(channels: List<MatchChannel>) {
        b.channelsContainer.removeAllViews()
        if (channels.isEmpty()) { b.emptyChannels.visible(); return }
        b.emptyChannels.gone()
        channels.forEach { c ->
            val v = layoutInflater.inflate(R.layout.item_mc_channel, b.channelsContainer, false)
            v.findViewById<TextView>(R.id.name).text = c.name ?: ""
            v.findViewById<TextView>(R.id.commentator).apply {
                val com = c.commentator
                if (!com.isNullOrBlank()) { text = getString(R.string.mc_commentator) + ": " + com; visible() } else gone()
            }
            b.channelsContainer.addView(v)
        }
    }

    // ---------------- Standings + scorers ----------------
    private fun renderStandings(rows: List<StandingRow>, scorers: List<Scorer>, m: Match) {
        b.standingsContainer.removeAllViews()
        if (rows.isEmpty()) {
            b.emptyStandings.visible()
        } else {
            b.emptyStandings.gone()
            val homeId = m.homeTeam.identifier
            val awayId = m.awayTeam.identifier
            rows.forEachIndexed { i, r ->
                val v = layoutInflater.inflate(R.layout.item_standing, b.standingsContainer, false)
                v.findViewById<TextView>(R.id.pos).text = (i + 1).toString()
                v.findViewById<ImageView>(R.id.logo).loadImage(Media.team(r.team?.image))
                v.findViewById<TextView>(R.id.team).text = r.team?.displayName ?: "—"
                v.findViewById<TextView>(R.id.played).text = (r.play ?: 0).toString()
                val diff = r.diff ?: ((r.forGoals ?: 0) - (r.against ?: 0))
                v.findViewById<TextView>(R.id.diff).text = if (diff > 0) "+$diff" else diff.toString()
                v.findViewById<TextView>(R.id.points).text = (r.points ?: 0).toString()
                val tid = r.teamId ?: r.team?.identifier
                if (tid != null && (tid == homeId || tid == awayId)) {
                    v.setBackgroundColor(0x1A0D9488) // subtle brand-tinted highlight
                }
                b.standingsContainer.addView(v)
            }
        }

        b.scorersContainer.removeAllViews()
        if (scorers.isEmpty()) {
            b.scorersCard.gone()
        } else {
            b.scorersCard.showIf(rows.isNotEmpty())
            scorers.take(15).forEachIndexed { i, s ->
                val v = layoutInflater.inflate(R.layout.item_scorer, b.scorersContainer, false)
                v.findViewById<TextView>(R.id.rank).text = (i + 1).toString()
                v.findViewById<ImageView>(R.id.photo).loadImage(Media.player(s.player?.image), R.drawable.ic_scorers)
                v.findViewById<TextView>(R.id.name).text = s.player?.displayName ?: "—"
                v.findViewById<TextView>(R.id.team).text = s.player?.teamName ?: ""
                v.findViewById<TextView>(R.id.goals).text = (s.goals ?: 0).toString()
                b.scorersContainer.addView(v)
            }
        }
    }

    // ---------------- Small view helpers ----------------
    private fun card(): LinearLayout = LinearLayout(requireContext()).apply {
        orientation = LinearLayout.VERTICAL
        setBackgroundResource(R.drawable.bg_section_card)
        setPadding(0, dp(6), 0, dp(8))
    }

    private fun sectionTitle(text: String) = TextView(requireContext()).apply {
        this.text = text
        setTextColor(onSurface())
        textSize = 15f
        setTypeface(typeface, android.graphics.Typeface.BOLD)
        setPadding(dp(14), dp(8), dp(14), dp(4))
    }

    private fun subTitle(text: String) = TextView(requireContext()).apply {
        this.text = text
        setTextColor(color(R.color.muted))
        textSize = 12f
        setPadding(dp(14), dp(8), dp(14), dp(2))
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()
    private fun color(res: Int) = androidx.core.content.ContextCompat.getColor(requireContext(), res)
    private fun onSurface(): Int {
        val tv = android.util.TypedValue()
        requireContext().theme.resolveAttribute(com.google.android.material.R.attr.colorOnSurface, tv, true)
        return tv.data
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
