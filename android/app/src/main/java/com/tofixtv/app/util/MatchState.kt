package com.tofixtv.app.util

import com.tofixtv.app.data.model.Match

/** Match status descriptor, ported from the PHP match_state()/live_clock() helpers. */
object MatchState {

    enum class Key { LIVE, FINISHED, UPCOMING }

    data class State(val key: Key, val label: String, val live: Boolean, val started: Boolean)

    /** Derived live minute for an in-play match, using ht_time as period start. */
    fun liveLabel(m: Match): String {
        val status = m.status ?: 0
        if (status == 2) return "HT"
        if (status in listOf(7, 8, 13)) return "PENS"

        val (base, cap) = when (status) {
            1 -> 0 to 45
            3 -> 45 to 90
            5 -> 90 to 105
            6 -> 105 to 120
            else -> 0 to 45
        }
        val raw = m.htTime ?: 0L
        val start = if (raw > 1_000_000_000L) raw else 0L
        val minute = when {
            start > 0 -> base + ((System.currentTimeMillis() / 1000 - start).coerceAtLeast(0) / 60).toInt() + 1
            (m.minutes ?: 0) in 1..130 -> m.minutes!!
            (raw in 1..130) -> raw.toInt()
            status !in listOf(1, 3, 5, 6) -> return "LIVE"
            else -> base + 1
        }
        return if (minute > cap) "$cap+${minute - cap}'" else "$minute'"
    }

    fun of(m: Match, upcomingLabel: String = ""): State {
        val status = m.status ?: 0
        val live = m.live ?: 0
        return when {
            status == 4 -> State(Key.FINISHED, "انتهت", live = false, started = true)
            live == 1 || status in listOf(1, 2, 3, 5, 6, 7, 8, 13) ->
                State(Key.LIVE, liveLabel(m), live = true, started = true)
            else -> State(Key.UPCOMING, upcomingLabel, live = false, started = false)
        }
    }
}
