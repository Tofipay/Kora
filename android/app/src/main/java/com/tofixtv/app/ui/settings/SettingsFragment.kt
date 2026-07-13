package com.tofixtv.app.ui.settings

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.appcompat.app.AppCompatDelegate
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.tofixtv.app.BuildConfig
import com.tofixtv.app.data.local.SettingsStore
import com.tofixtv.app.databinding.FragmentSettingsBinding
import com.tofixtv.app.util.AppState
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch

class SettingsFragment : Fragment() {

    private var _b: FragmentSettingsBinding? = null
    private val b get() = _b!!
    private lateinit var store: SettingsStore

    override fun onCreateView(inflater: LayoutInflater, c: ViewGroup?, s: Bundle?): View {
        _b = FragmentSettingsBinding.inflate(inflater, c, false)
        return b.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        store = SettingsStore(requireContext())
        b.versionText.text = "الإصدار ${BuildConfig.VERSION_NAME}"

        viewLifecycleOwner.lifecycleScope.launch {
            when (store.lang.first()) {
                "en" -> b.langEn.isChecked = true
                else -> b.langAr.isChecked = true
            }
            when (store.theme.first()) {
                1 -> b.themeLight.isChecked = true
                2 -> b.themeDark.isChecked = true
                else -> b.themeSystem.isChecked = true
            }
            b.notifySwitch.isChecked = store.notify.first()
        }

        b.langGroup.setOnCheckedChangeListener { _, checkedId ->
            val lang = if (checkedId == b.langEn.id) "en" else "ar"
            AppState.lang = lang
            viewLifecycleOwner.lifecycleScope.launch { store.setLang(lang) }
        }

        b.themeGroup.setOnCheckedChangeListener { _, checkedId ->
            val mode = when (checkedId) {
                b.themeLight.id -> 1
                b.themeDark.id -> 2
                else -> 0
            }
            AppCompatDelegate.setDefaultNightMode(
                when (mode) {
                    1 -> AppCompatDelegate.MODE_NIGHT_NO
                    2 -> AppCompatDelegate.MODE_NIGHT_YES
                    else -> AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM
                }
            )
            viewLifecycleOwner.lifecycleScope.launch { store.setTheme(mode) }
        }

        b.notifySwitch.setOnCheckedChangeListener { _, checked ->
            viewLifecycleOwner.lifecycleScope.launch { store.setNotify(checked) }
        }
    }

    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
