package com.christopheraldoo.earningquizapp

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment
import com.google.android.material.bottomnavigation.BottomNavigationView
import com.christopheraldoo.earningquizapp.fragments.HomeFragment
import com.christopheraldoo.earningquizapp.fragments.LeaderboardFragment
import com.christopheraldoo.earningquizapp.fragments.ProfileFragment

/**
 * Main Activity that serves as the container for the app's main navigation.
 *
 * This activity uses a BottomNavigationView to switch between different fragments:
 * - Home: Main dashboard with points, quick actions
 * - Leaderboard: Rankings and competition
 * - Profile: User information and settings
 */
class MainActivity : AppCompatActivity() {

    private lateinit var bottomNavigation: BottomNavigationView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        initializeViews()
        setupBottomNavigation()
        
        // Load home fragment by default
        if (savedInstanceState == null) {
            loadFragment(HomeFragment())
        }
    }

    /**
     * Initialize view components
     */
    private fun initializeViews() {
        bottomNavigation = findViewById(R.id.bottom_navigation)
    }

    /**
     * Setup bottom navigation with fragment switching
     */
    private fun setupBottomNavigation() {
        bottomNavigation.setOnItemSelectedListener { item ->
            when (item.itemId) {
                R.id.nav_home -> {
                    loadFragment(HomeFragment())
                    true
                }
                R.id.nav_leaderboard -> {
                    loadFragment(LeaderboardFragment())
                    true
                }
                R.id.nav_profile -> {
                    loadFragment(ProfileFragment())
                    true
                }
                else -> false
            }
        }
    }

    /**
     * Load a fragment into the container
     */
    private fun loadFragment(fragment: Fragment) {
        supportFragmentManager.beginTransaction()
            .replace(R.id.fragment_container, fragment)
            .commit()
    }
}