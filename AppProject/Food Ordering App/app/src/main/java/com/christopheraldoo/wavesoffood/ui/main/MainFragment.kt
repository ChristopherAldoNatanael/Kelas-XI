package com.christopheraldoo.wavesoffood.ui.main

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.NavHostFragment
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentMainBinding

class MainFragment : Fragment() {

    private var _binding: FragmentMainBinding? = null
    private val binding get() = _binding!!
    
    private val TAG = "MainFragment"

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        Log.d(TAG, "onCreateView called")
        try {
            _binding = FragmentMainBinding.inflate(inflater, container, false)
            Log.d(TAG, "Binding inflated successfully")
            return binding.root
        } catch (e: Exception) {
            Log.e(TAG, "Error in onCreateView: ${e.message}", e)
            throw e
        }
    }    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called")
        
        // Add delay and better error handling
        try {
            view.post {
                Log.d(TAG, "Setting up bottom navigation")
                setupBottomNavigation()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error in onViewCreated: ${e.message}", e)
            showToast("Failed to initialize navigation. Please restart app.")
        }
    }    private fun setupBottomNavigation() {
        Log.d(TAG, "setupBottomNavigation started")
        try {
            // Pastikan binding masih valid
            if (_binding == null) {
                Log.e(TAG, "Binding is null in setupBottomNavigation")
                showToast("Navigation setup failed - binding is null")
                return
            }
            Log.d(TAG, "Binding is valid")

            val navHostFragment = childFragmentManager
                .findFragmentById(R.id.nav_host_fragment_main) as? NavHostFragment
            
            if (navHostFragment == null) {
                Log.e(TAG, "NavHostFragment not found")
                showToast("Navigation setup failed - NavHostFragment not found")
                return
            }
            Log.d(TAG, "NavHostFragment found")
            
            val navController = navHostFragment.navController
            Log.d(TAG, "NavController obtained")

            // Listen to destination changes to update bottom navigation selection
            navController.addOnDestinationChangedListener { _, destination, _ ->
                Log.d(TAG, "Destination changed to: ${destination.id}")
                try {
                    // Pastikan binding masih valid
                    if (_binding == null || !isAdded) {
                        Log.w(TAG, "Fragment not added or binding null, skipping selection update")
                        return@addOnDestinationChangedListener
                    }
                    
                    // Update bottom navigation selection based on current destination
                    when (destination.id) {
                        R.id.navigation_home -> {
                            Log.d(TAG, "Setting selection to home")
                            binding.bottomNavigation.selectedItemId = R.id.navigation_home
                        }
                        R.id.navigation_menu -> {
                            Log.d(TAG, "Setting selection to menu")
                            binding.bottomNavigation.selectedItemId = R.id.navigation_menu
                        }
                        R.id.navigation_search -> {
                            Log.d(TAG, "Setting selection to search")
                            binding.bottomNavigation.selectedItemId = R.id.navigation_search
                        }
                        R.id.navigation_cart -> {
                            Log.d(TAG, "Setting selection to cart")
                            binding.bottomNavigation.selectedItemId = R.id.navigation_cart
                        }
                        R.id.navigation_profile -> {
                            Log.d(TAG, "Setting selection to profile")
                            binding.bottomNavigation.selectedItemId = R.id.navigation_profile
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error in destination change listener: ${e.message}", e)
                }
            }

            // Setup bottom navigation dengan proper navigation handling
            binding.bottomNavigation.setOnItemSelectedListener { item ->
                Log.d(TAG, "Bottom navigation item selected: ${item.itemId}")
                try {
                    // Pastikan binding dan navController masih valid
                    if (_binding == null || !isAdded) {
                        Log.w(TAG, "Fragment not added or binding null, ignoring navigation")
                        return@setOnItemSelectedListener false
                    }
                    
                    when (item.itemId) {
                        R.id.navigation_home -> {
                            Log.d(TAG, "Navigating to home")
                            if (navController.currentDestination?.id != R.id.navigation_home) {
                                navController.navigate(R.id.navigation_home)
                            }
                            true
                        }
                        R.id.navigation_menu -> {
                            Log.d(TAG, "Navigating to menu")
                            if (navController.currentDestination?.id != R.id.navigation_menu) {
                                navController.navigate(R.id.navigation_menu)
                            }
                            true
                        }
                        R.id.navigation_search -> {
                            Log.d(TAG, "Navigating to search")
                            if (navController.currentDestination?.id != R.id.navigation_search) {
                                navController.navigate(R.id.navigation_search)
                                showToast("Search activated! 🔍")
                            }
                            true
                        }
                        R.id.navigation_cart -> {
                            Log.d(TAG, "Navigating to cart")
                            if (navController.currentDestination?.id != R.id.navigation_cart) {
                                navController.navigate(R.id.navigation_cart)
                            }
                            true
                        }
                        R.id.navigation_profile -> {
                            Log.d(TAG, "Navigating to profile")
                            if (navController.currentDestination?.id != R.id.navigation_profile) {
                                navController.navigate(R.id.navigation_profile)
                            }
                            true
                        }
                        else -> {
                            Log.w(TAG, "Unknown navigation item: ${item.itemId}")
                            false
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Navigation error: ${e.message}", e)
                    showToast("Navigation error: ${e.message}")
                    false
                }
            }
            
            // Set default selection safely dengan delay
            view?.postDelayed({
                try {
                    if (_binding != null && isAdded) {
                        Log.d(TAG, "Setting default selection to home")
                        binding.bottomNavigation.selectedItemId = R.id.navigation_home
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Error setting default selection: ${e.message}", e)
                }
            }, 100)
            
            Log.d(TAG, "Bottom navigation setup completed successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Critical navigation error: ${e.message}", e)
            showToast("Critical navigation error: ${e.message}")
        }
    }
      private fun showToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            // Ignore toast errors
        }
    }    override fun onDestroyView() {
        Log.d(TAG, "onDestroyView called")
        super.onDestroyView()
        _binding = null
    }
}
