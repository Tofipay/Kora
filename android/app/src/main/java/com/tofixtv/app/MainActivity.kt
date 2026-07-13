package com.tofixtv.app

import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import androidx.appcompat.app.AppCompatActivity
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.core.view.GravityCompat
import androidx.drawerlayout.widget.DrawerLayout
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import androidx.navigation.NavController
import androidx.navigation.findNavController
import androidx.navigation.ui.*
import com.google.android.material.bottomnavigation.BottomNavigationView
import com.google.android.material.navigation.NavigationView
import com.tofixtv.app.databinding.ActivityMainBinding

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var navController: NavController
    private lateinit var appBarConfig: AppBarConfiguration

    /** A fragment hosting a player can opt into auto-PiP when the user leaves. */
    interface PipAware { fun shouldEnterPip(): Boolean; fun onEnterPip() }

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setSupportActionBar(binding.toolbar)

        val navHost = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as androidx.navigation.fragment.NavHostFragment
        navController = navHost.navController

        val drawer: DrawerLayout = binding.drawerLayout
        val navView: NavigationView = binding.navView
        val bottomNav: BottomNavigationView = binding.bottomNav

        // Top-level destinations that show a hamburger (no up-arrow) + no back.
        appBarConfig = AppBarConfiguration(
            setOf(
                R.id.homeFragment, R.id.matchesFragment, R.id.newsFragment,
                R.id.videosFragment, R.id.favoritesFragment,
                R.id.leaguesFragment, R.id.standingsFragment, R.id.scorersFragment,
                R.id.channelsFragment, R.id.settingsFragment, R.id.notificationsFragment
            ),
            drawer
        )

        setupActionBarWithNavController(navController, appBarConfig)
        bottomNav.setupWithNavController(navController)
        navView.setupWithNavController(navController)

        // Hide the floating bottom nav on detail / player screens for immersion.
        navController.addOnDestinationChangedListener { _, dest, _ ->
            val topLevel = dest.id in setOf(
                R.id.homeFragment, R.id.matchesFragment, R.id.newsFragment,
                R.id.videosFragment, R.id.favoritesFragment
            )
            binding.bottomNavCard.visibility =
                if (topLevel) android.view.View.VISIBLE else android.view.View.GONE
        }

        handleIntentLink(intent)
        setupPushNotifications()
    }

    private val notifPermissionLauncher =
        registerForActivityResult(androidx.activity.result.contract.ActivityResultContracts.RequestPermission()) { }

    /** Ask for POST_NOTIFICATIONS (Android 13+) and subscribe to the default topic. */
    private fun setupPushNotifications() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val granted = checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS) ==
                android.content.pm.PackageManager.PERMISSION_GRANTED
            if (!granted) notifPermissionLauncher.launch(android.Manifest.permission.POST_NOTIFICATIONS)
        }
        com.tofixtv.app.fcm.FcmRegistrar.subscribe("all")
        com.google.firebase.messaging.FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                com.tofixtv.app.fcm.FcmRegistrar.saveToken(applicationContext, token)
                // Register the token + subscribed topics with the backend so
                // cron/admin match notifications have a subscriber row to target.
                lifecycleScope.launch {
                    val topics = com.tofixtv.app.data.local.SettingsStore(applicationContext)
                        .topics.first()
                    com.tofixtv.app.data.repo.Repository.get(applicationContext)
                        .registerPush(token, topics)
                }
            }
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.top_app_bar, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_search -> { navController.navigate(R.id.searchFragment); true }
            else -> super.onOptionsItemSelected(item)
        }
    }

    override fun onSupportNavigateUp(): Boolean {
        return navController.navigateUp(appBarConfig) || super.onSupportNavigateUp()
    }

    override fun onBackPressed() {
        if (binding.drawerLayout.isDrawerOpen(GravityCompat.START)) {
            binding.drawerLayout.closeDrawer(GravityCompat.START)
        } else {
            super.onBackPressed()
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntentLink(intent)
    }

    /** Route deep links / app links through the nav graph's <deepLink> table. */
    private fun handleIntentLink(intent: Intent?) {
        intent?.data ?: return
        // NavHostFragment auto-handles the launch intent; this covers onNewIntent
        // (singleTask re-launches from FCM taps / external links).
        runCatching { navController.handleDeepLink(intent) }
    }

    // ---------- Picture-in-Picture ----------
    override fun onUserLeaveHint() {
        super.onUserLeaveHint()
        maybeEnterPip()
    }

    private fun maybeEnterPip() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val host = supportFragmentManager.findFragmentById(R.id.nav_host_fragment)
        val current = host?.childFragmentManager?.fragments?.firstOrNull()
        if (current is PipAware && current.shouldEnterPip()) {
            current.onEnterPip()
        }
    }
}
