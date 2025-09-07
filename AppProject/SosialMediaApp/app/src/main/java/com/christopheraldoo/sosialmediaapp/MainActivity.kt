package com.christopheraldoo.sosialmediaapp

import android.content.Context
import android.content.res.Configuration
import android.os.Bundle
import android.view.MenuItem
import android.widget.TextView
import androidx.appcompat.app.ActionBarDrawerToggle
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.GravityCompat
import androidx.drawerlayout.widget.DrawerLayout
import androidx.navigation.NavController
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.AppBarConfiguration
import androidx.navigation.ui.navigateUp
import androidx.navigation.ui.setupActionBarWithNavController
import androidx.navigation.ui.setupWithNavController
import com.christopheraldoo.sosialmediaapp.databinding.ActivityMainBinding
import com.christopheraldoo.sosialmediaapp.ui.*
import com.christopheraldoo.sosialmediaapp.utils.DummyData
import com.christopheraldoo.sosialmediaapp.utils.Utils
import com.christopheraldoo.sosialmediaapp.utils.PreferenceManager
import com.google.android.material.navigation.NavigationView
import java.util.Locale

/**
 * Activity utama yang menangani navigation drawer dan fragment management
 * Menggunakan ViewBinding untuk UI binding
 */
class MainActivity : AppCompatActivity(), NavigationView.OnNavigationItemSelectedListener {

    private lateinit var binding: ActivityMainBinding
    private lateinit var toggle: ActionBarDrawerToggle
    private lateinit var preferenceManager: PreferenceManager
    private lateinit var navController: NavController
    private lateinit var appBarConfiguration: AppBarConfiguration

    override fun attachBaseContext(newBase: Context) {
        val preferenceManager = PreferenceManager(newBase)
        val contextWithLanguage = updateBaseContextLocale(newBase, preferenceManager.getLanguage())
        super.attachBaseContext(contextWithLanguage)
    }

    private fun updateBaseContextLocale(context: Context, language: String): Context {
        val locale = Locale(language)
        Locale.setDefault(locale)
        
        val config = Configuration(context.resources.configuration)
        config.setLocale(locale)
        
        return context.createConfigurationContext(config)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        // Initialize preference manager first
        preferenceManager = PreferenceManager(this)
        
        // Apply theme before calling super.onCreate()
        preferenceManager.applyTheme(preferenceManager.getThemeMode())
        
        super.onCreate(savedInstanceState)
        
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setupNavigation()
        setupToolbar()
        setupNavigationDrawer()
        setupNavigationHeader()
        setupBottomNavigation()
    }

    private fun setupNavigation() {
        try {
            val navHostFragment = supportFragmentManager.findFragmentById(R.id.nav_host_fragment) as? NavHostFragment
            if (navHostFragment != null) {
                navController = navHostFragment.navController
                
                // Setup app bar configuration
                appBarConfiguration = AppBarConfiguration(
                    setOf(
                        R.id.feedFragment,
                        R.id.searchFragment,
                        R.id.notificationsFragment,
                        R.id.messagesFragment
                    ),
                    binding.drawerLayout
                )
                
                setupActionBarWithNavController(navController, appBarConfiguration)
            } else {
                // Fallback to fragment transactions if NavHostFragment is not found
                android.util.Log.e("MainActivity", "NavHostFragment not found, using fallback")
                setupFallbackNavigation()
            }
        } catch (e: Exception) {
            android.util.Log.e("MainActivity", "Error setting up navigation: ${e.message}")
            setupFallbackNavigation()
        }
    }
    
    private fun setupFallbackNavigation() {
        // Load the initial fragment manually
        if (supportFragmentManager.findFragmentById(R.id.nav_host_fragment) == null) {
            supportFragmentManager.beginTransaction()
                .replace(R.id.nav_host_fragment, com.christopheraldoo.sosialmediaapp.ui.FeedFragment())
                .commit()
        }
    }

    private fun setupToolbar() {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)
        supportActionBar?.setHomeButtonEnabled(true)
    }

    private fun setupNavigationDrawer() {
        toggle = ActionBarDrawerToggle(
            this,
            binding.drawerLayout,
            binding.toolbar,
            R.string.nav_drawer_open,
            R.string.nav_drawer_close
        )
        
        binding.drawerLayout.addDrawerListener(toggle)
        toggle.syncState()
        
        binding.navView.setNavigationItemSelectedListener(this)
    }

    private fun setupNavigationHeader() {
        val headerView = binding.navView.getHeaderView(0)
        val currentUser = DummyData.users[0] // Simulasi current user
        
        // Update header dengan data current user
        headerView.findViewById<TextView>(R.id.tv_nav_display_name).text = currentUser.displayName
        headerView.findViewById<TextView>(R.id.tv_nav_username).text = currentUser.username
        headerView.findViewById<TextView>(R.id.tv_nav_following_count).text = Utils.formatCount(currentUser.following)
        headerView.findViewById<TextView>(R.id.tv_nav_followers_count).text = Utils.formatCount(currentUser.followers)
        
        // Set click listener untuk header (navigate ke profile)
        headerView.setOnClickListener {
            navController.navigate(R.id.profileFragment)
            binding.navView.setCheckedItem(R.id.profileFragment)
            binding.drawerLayout.closeDrawer(GravityCompat.START)
        }
    }

    private fun setupBottomNavigation() {
        try {
            if (::navController.isInitialized) {
                binding.bottomNavigation.setupWithNavController(navController)
            } else {
                // Fallback manual navigation
                binding.bottomNavigation.setOnItemSelectedListener { item ->
                    when (item.itemId) {
                        R.id.feedFragment -> {
                            loadFragment(com.christopheraldoo.sosialmediaapp.ui.FeedFragment())
                            true
                        }
                        R.id.searchFragment -> {
                            loadFragment(com.christopheraldoo.sosialmediaapp.ui.SearchFragment())
                            true
                        }
                        R.id.notificationsFragment -> {
                            loadFragment(com.christopheraldoo.sosialmediaapp.ui.NotificationsFragment())
                            true
                        }
                        R.id.messagesFragment -> {
                            loadFragment(com.christopheraldoo.sosialmediaapp.ui.MessagesFragment())
                            true
                        }
                        R.id.profileFragment -> {
                            loadFragment(com.christopheraldoo.sosialmediaapp.ui.ProfileFragment())
                            true
                        }
                        else -> false
                    }
                }
            }
        } catch (e: Exception) {
            android.util.Log.e("MainActivity", "Error setting up bottom navigation: ${e.message}")
        }
    }

    override fun onNavigationItemSelected(item: MenuItem): Boolean {
        try {
            when (item.itemId) {
                R.id.feedFragment -> {
                    if (::navController.isInitialized) {
                        navController.navigate(R.id.feedFragment)
                    } else {
                        loadFragment(com.christopheraldoo.sosialmediaapp.ui.FeedFragment())
                    }
                    binding.bottomNavigation.selectedItemId = R.id.feedFragment
                }
                R.id.profileFragment -> {
                    if (::navController.isInitialized) {
                        navController.navigate(R.id.profileFragment)
                    } else {
                        loadFragment(com.christopheraldoo.sosialmediaapp.ui.ProfileFragment())
                    }
                    binding.bottomNavigation.selectedItemId = R.id.profileFragment
                }
                R.id.settingsFragment -> {
                    if (::navController.isInitialized) {
                        navController.navigate(R.id.settingsFragment)
                    } else {
                        loadFragment(com.christopheraldoo.sosialmediaapp.ui.SettingsFragment())
                    }
                }
                R.id.nav_logout -> {
                    showLogoutConfirmation()
                    return true // Don't close drawer immediately
                }
            }
        } catch (e: Exception) {
            android.util.Log.e("MainActivity", "Navigation error: ${e.message}")
        }
        
        binding.drawerLayout.closeDrawer(GravityCompat.START)
        return true
    }

    override fun onSupportNavigateUp(): Boolean {
        return if (::navController.isInitialized) {
            navController.navigateUp(appBarConfiguration) || super.onSupportNavigateUp()
        } else {
            super.onSupportNavigateUp()
        }
    }
    
    private fun loadFragment(fragment: androidx.fragment.app.Fragment) {
        supportFragmentManager.beginTransaction()
            .replace(R.id.nav_host_fragment, fragment)
            .commit()
    }

    private fun showLogoutConfirmation() {
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("Logout")
            .setMessage("Are you sure you want to logout?")
            .setPositiveButton("Logout") { dialog, _ ->
                // Clear any stored user session data
                val sharedPrefs = getSharedPreferences("app_prefs", MODE_PRIVATE)
                sharedPrefs.edit().clear().apply()
                
                // For now, just show a toast and go to home
                if (::navController.isInitialized) {
                    navController.navigate(R.id.feedFragment)
                } else {
                    loadFragment(com.christopheraldoo.sosialmediaapp.ui.FeedFragment())
                }
                binding.navView.setCheckedItem(R.id.feedFragment)
                
                android.widget.Toast.makeText(this@MainActivity, "Logged out successfully", android.widget.Toast.LENGTH_SHORT).show()
                dialog.dismiss()
                binding.drawerLayout.closeDrawer(GravityCompat.START)
            }
            .setNegativeButton("Cancel") { dialog, _ ->
                dialog.dismiss()
                binding.drawerLayout.closeDrawer(GravityCompat.START)
            }
            .show()
    }

    override fun onBackPressed() {
        if (binding.drawerLayout.isDrawerOpen(GravityCompat.START)) {
            binding.drawerLayout.closeDrawer(GravityCompat.START)
        } else {
            super.onBackPressed()
        }
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (toggle.onOptionsItemSelected(item)) {
            return true
        }
        return super.onOptionsItemSelected(item)
    }
}