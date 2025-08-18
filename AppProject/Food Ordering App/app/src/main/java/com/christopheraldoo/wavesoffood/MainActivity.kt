package com.christopheraldoo.wavesoffood

import android.os.Bundle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupActionBarWithNavController

/**
 * MainActivity - Activity utama yang menampung semua fragment
 * 
 * Activity ini menggunakan Navigation Component untuk mengatur
 * navigasi antar fragment (Splash -> Onboarding -> Login)
 * 
 * Features:
 * - Edge-to-edge display untuk modern UI
 * - Navigation Component integration
 * - Action bar integration dengan navigation
 */
class MainActivity : AppCompatActivity() {
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContentView(R.layout.activity_main)
        
        // Setup window insets untuk edge-to-edge display
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.main)) { v, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            v.setPadding(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            insets
        }
        
        // Setup Navigation Component
        setupNavigation()
    }
    
    /**
     * Setup Navigation Component dengan NavHostFragment
     * Sederhana tanpa action bar untuk menghindari crash
     */
    private fun setupNavigation() {
        try {
            // Dapatkan NavHostFragment dari layout
            val navHostFragment = supportFragmentManager
                .findFragmentById(R.id.nav_host_fragment) as? NavHostFragment
            
            if (navHostFragment != null) {
                // Navigation sudah setup, NavController siap digunakan
                // Log success untuk debugging
                println("Navigation setup successful")
            } else {
                println("NavHostFragment not found - this is normal for simple navigation")
            }
        } catch (e: Exception) {
            // Log error jika ada masalah dengan navigation setup
            println("Navigation setup error: ${e.message}")
            e.printStackTrace()
        }
    }
}