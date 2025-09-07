package com.christopheraldoo.earningquizapp.activities

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import com.christopheraldoo.earningquizapp.R

/**
 * The main screen of the application after the user logs in.
 *
 * This activity hosts a BottomNavigationView and a FrameLayout to display
 * the main fragments of the app: Home, Leaderboard, and Profile.
 */
class HomeActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_home)

        val bottomNav = findViewById<com.google.android.material.bottomnavigation.BottomNavigationView>(R.id.bottom_navigation)

        // Set the initial fragment
        if (savedInstanceState == null) {
            supportFragmentManager.beginTransaction().replace(R.id.fragment_container, com.christopheraldoo.earningquizapp.fragments.HomeFragment()).commit()
            bottomNav.selectedItemId = R.id.nav_home
        }

        bottomNav.setOnItemSelectedListener { item ->
            var selectedFragment: androidx.fragment.app.Fragment? = null
            when (item.itemId) {
                R.id.nav_home -> selectedFragment = com.christopheraldoo.earningquizapp.fragments.HomeFragment()
                R.id.nav_leaderboard -> selectedFragment = com.christopheraldoo.earningquizapp.fragments.LeaderboardFragment()
                R.id.nav_profile -> selectedFragment = com.christopheraldoo.earningquizapp.fragments.ProfileFragment()
            }

            if (selectedFragment != null) {
                supportFragmentManager.beginTransaction().replace(R.id.fragment_container, selectedFragment).commit()
            }

            true
        }
    }
}
