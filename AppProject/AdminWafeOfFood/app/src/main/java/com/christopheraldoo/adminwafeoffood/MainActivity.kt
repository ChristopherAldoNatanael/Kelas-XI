package com.christopheraldoo.adminwafeoffood

import android.content.Intent
import android.os.Bundle
import android.util.Log
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment
import com.christopheraldoo.adminwafeoffood.databinding.ActivityMainBinding
import com.christopheraldoo.adminwafeoffood.fragments.DashboardFragment
import com.christopheraldoo.adminwafeoffood.fragments.MenuFragment
import com.christopheraldoo.adminwafeoffood.fragments.OrdersFragment
import com.christopheraldoo.adminwafeoffood.fragments.ProfileFragment
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.firebase.auth.FirebaseAuth

class MainActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityMainBinding
    private lateinit var auth: FirebaseAuth
    private lateinit var googleSignInClient: GoogleSignInClient
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        try {
            binding = ActivityMainBinding.inflate(layoutInflater)
            setContentView(binding.root)
            
            // Initialize Firebase Auth
            auth = FirebaseAuth.getInstance()
            
            // Initialize Google Sign-In
            val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
                .requestIdToken(getString(R.string.default_web_client_id))
                .requestEmail()
                .build()
            googleSignInClient = GoogleSignIn.getClient(this, gso)
            
            // Check if user is logged in
            if (auth.currentUser == null) {
                startActivity(Intent(this, AuthActivity::class.java))
                finish()
                return
            }
            
            // Setup bottom navigation
            setupBottomNavigation()
            
            // Load default fragment
            if (savedInstanceState == null) {
                loadFragment(DashboardFragment())
            }
            
        } catch (e: Exception) {
            Log.e("MainActivity", "Error in onCreate", e)
            finish()
        }
    }
    
    private fun setupBottomNavigation() {
        binding.bottomNavigation.setOnItemSelectedListener { item ->
            try {
                when (item.itemId) {
                    R.id.navigation_dashboard -> {
                        loadFragment(DashboardFragment())
                        true
                    }
                    R.id.navigation_menu -> {
                        Log.d("MainActivity", "User clicked menu navigation")
                        loadFragment(MenuFragment())
                        true
                    }
                    R.id.navigation_orders -> {
                        loadFragment(OrdersFragment())
                        true
                    }
                    R.id.navigation_profile -> {
                        loadFragment(ProfileFragment())
                        true
                    }
                    else -> false
                }
            } catch (e: Exception) {
                Log.e("MainActivity", "Navigation error", e)
                false
            }
        }
    }
    
    private fun loadFragment(fragment: Fragment) {
        try {
            Log.d("MainActivity", "Loading fragment: ${fragment::class.java.simpleName}")
            if (!isFinishing && !isDestroyed) {
                supportFragmentManager.beginTransaction()
                    .replace(R.id.container, fragment)
                    .commitAllowingStateLoss()
                Log.d("MainActivity", "Fragment loaded successfully: ${fragment::class.java.simpleName}")
            } else {
                Log.w("MainActivity", "Activity finishing/destroyed, cannot load fragment")
            }
        } catch (e: Exception) {
            Log.e("MainActivity", "Fragment load error for ${fragment::class.java.simpleName}", e)
        }
    }
    
    fun getCurrentUserEmail(): String? {
        return auth.currentUser?.email
    }
    
    fun navigateToOrders() {
        try {
            binding.bottomNavigation.selectedItemId = R.id.navigation_orders
        } catch (e: Exception) {
            Log.e("MainActivity", "Navigation error", e)
        }
    }
    
    fun performLogout() {
        auth.signOut()
        googleSignInClient.signOut()
        
        // Navigate back to AuthActivity
        startActivity(Intent(this, AuthActivity::class.java))
        finish()
    }
}