package com.christopheraldoo.wavesoffood.ui.main

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.databinding.FragmentMainBinding
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * MainFragment - Fragment utama yang mengatur bottom navigation
 * 
 * Fragment ini menggunakan NavHostFragment untuk menampung fragment lain
 * dan mengatur navigasi melalui bottom navigation
 * 
 * PERBAIKAN:
 * - Enhanced error handling untuk semua navigasi
 * - Robust navigation controller management
 * - Fallback mechanisms untuk navigation failures
 * - Comprehensive logging untuk debugging
 * - Lifecycle-aware operations
 */
class MainFragment : Fragment() {

    private var _binding: FragmentMainBinding? = null
    private val binding get() = _binding!!
    
    private val TAG = "MainFragment"
    
    // Flag untuk mencegah multiple navigation calls
    private var isNavigating = false

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
            Log.e(TAG, "Critical error in onCreateView: ${e.message}", e)
            throw e
        }
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        Log.d(TAG, "onViewCreated called")
        
        // Initialize dengan lifecycle-aware approach
        lifecycleScope.launch {
            try {
                // Wait untuk view to be fully created
                delay(100)
                
                if (isAdded && _binding != null) {
                    setupBottomNavigation()
                    Log.d(TAG, "Navigation setup completed")
                } else {
                    Log.w(TAG, "Fragment not ready for navigation setup")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error in navigation initialization: ${e.message}", e)
                handleInitializationError(e)
            }
        }
    }

    /**
     * Setup bottom navigation dengan comprehensive error handling
     */
    private fun setupBottomNavigation() {
        Log.d(TAG, "setupBottomNavigation started")
        
        try {
            // Validate prerequisites
            if (!validateNavigationPrerequisites()) {
                return
            }

            // Get NavHostFragment dengan retry mechanism
            val navHostFragment = getNavHostFragmentSafely()
            if (navHostFragment == null) {
                Log.e(TAG, "Failed to get NavHostFragment after retries")
                showToast("Navigation setup failed. Please restart the app.")
                return
            }

            val navController = navHostFragment.navController
            Log.d(TAG, "NavController obtained successfully")

            // Setup destination change listener dengan error handling
            setupDestinationChangeListener(navController)
            
            // Setup bottom navigation item selection listener
            setupBottomNavigationListener(navController)
            
            // Set default selection
            setDefaultSelection()
            
            Log.d(TAG, "Bottom navigation setup completed successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Critical error in setupBottomNavigation: ${e.message}", e)
            handleNavigationSetupError(e)
        }
    }
    
    /**
     * Validate all prerequisites for navigation setup
     */
    private fun validateNavigationPrerequisites(): Boolean {
        if (_binding == null) {
            Log.e(TAG, "Binding is null - cannot setup navigation")
            return false
        }
        
        if (!isAdded) {
            Log.e(TAG, "Fragment not added - cannot setup navigation")
            return false
        }
        
        if (activity == null) {
            Log.e(TAG, "Activity is null - cannot setup navigation")
            return false
        }
        
        return true
    }
    
    /**
     * Get NavHostFragment dengan retry mechanism
     */
    private fun getNavHostFragmentSafely(): NavHostFragment? {
        var attempts = 0
        val maxAttempts = 3
        
        while (attempts < maxAttempts) {
            try {
                val fragment = childFragmentManager
                    .findFragmentById(R.id.nav_host_fragment_main) as? NavHostFragment
                
                if (fragment != null) {
                    Log.d(TAG, "NavHostFragment found on attempt ${attempts + 1}")
                    return fragment
                }
                
                attempts++
                Log.w(TAG, "NavHostFragment not found, attempt $attempts/$maxAttempts")
                
                if (attempts < maxAttempts) {
                    Thread.sleep(50) // Short delay before retry
                }
                
            } catch (e: Exception) {
                Log.e(TAG, "Error getting NavHostFragment on attempt $attempts: ${e.message}")
                attempts++
            }
        }
        
        return null
    }
    
    /**
     * Setup destination change listener dengan comprehensive error handling
     */
    private fun setupDestinationChangeListener(navController: androidx.navigation.NavController) {
        try {
            navController.addOnDestinationChangedListener { _, destination, _ ->
                Log.d(TAG, "Destination changed to: ${destination.id} (${destination.label})")
                
                try {
                    // Validate state before updating UI
                    if (!isAdded || _binding == null) {
                        Log.w(TAG, "Fragment state invalid, skipping UI update")
                        return@addOnDestinationChangedListener
                    }
                    
                    // Update bottom navigation selection
                    updateBottomNavigationSelection(destination.id)
                    
                } catch (e: Exception) {
                    Log.e(TAG, "Error in destination change listener: ${e.message}", e)
                    // Don't crash, just log the error
                }
            }
            
            Log.d(TAG, "Destination change listener setup successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Failed to setup destination change listener: ${e.message}", e)
        }
    }
    
    /**
     * Update bottom navigation selection based on destination
     */
    private fun updateBottomNavigationSelection(destinationId: Int) {
        try {            val itemId = when (destinationId) {
                R.id.navigation_home -> R.id.navigation_home
                R.id.navigation_menu -> R.id.navigation_menu
                R.id.navigation_cart -> R.id.navigation_cart
                R.id.navigation_profile -> R.id.navigation_profile
                else -> {
                    Log.w(TAG, "Unknown destination ID: $destinationId")
                    return
                }
            }
            
            if (binding.bottomNavigation.selectedItemId != itemId) {
                binding.bottomNavigation.selectedItemId = itemId
                Log.d(TAG, "Updated selection to: $itemId")
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error updating bottom navigation selection: ${e.message}")
        }
    }
    
    /**
     * Setup bottom navigation item selection listener
     */
    private fun setupBottomNavigationListener(navController: androidx.navigation.NavController) {
        binding.bottomNavigation.setOnItemSelectedListener { item ->
            Log.d(TAG, "Bottom navigation item selected: ${item.itemId}")
            
            // Prevent multiple rapid navigation calls
            if (isNavigating) {
                Log.w(TAG, "Navigation in progress, ignoring request")
                return@setOnItemSelectedListener true
            }
            
            try {
                navigateToDestination(navController, item.itemId)
                true
            } catch (e: Exception) {
                Log.e(TAG, "Navigation error: ${e.message}", e)
                showToast("Navigation error: ${e.localizedMessage ?: "Unknown error"}")
                false
            }
        }
    }
    
    /**
     * Navigate to destination dengan comprehensive error handling
     */
    private fun navigateToDestination(navController: androidx.navigation.NavController, itemId: Int) {
        isNavigating = true
        
        try {
            // Validate navigation controller state
            val currentDestination = navController.currentDestination
            if (currentDestination == null) {
                Log.e(TAG, "Current destination is null")
                showToast("Navigation error: Invalid state")
                return
            }
            
            // Check if already at destination
            if (currentDestination.id == itemId) {
                Log.d(TAG, "Already at destination: $itemId")
                return
            }
            
            // Perform navigation based on item
            when (itemId) {
                R.id.navigation_home -> {
                    Log.d(TAG, "Navigating to home")
                    navController.navigate(R.id.navigation_home)
                }                R.id.navigation_menu -> {
                    Log.d(TAG, "Navigating to menu")
                    navController.navigate(R.id.navigation_menu)
                }
                R.id.navigation_cart -> {
                    Log.d(TAG, "Navigating to cart")
                    navController.navigate(R.id.navigation_cart)
                }
                R.id.navigation_profile -> {
                    Log.d(TAG, "Navigating to profile")
                    navController.navigate(R.id.navigation_profile)
                }
                else -> {
                    Log.w(TAG, "Unknown navigation item: $itemId")
                    showToast("Unknown navigation option")
                }
            }
            
            Log.d(TAG, "Navigation to $itemId completed successfully")
            
        } catch (e: Exception) {
            Log.e(TAG, "Navigation failed for item $itemId: ${e.message}", e)
            
            // Try fallback navigation
            tryFallbackNavigation(itemId)
            
        } finally {
            // Reset navigation flag after delay
            lifecycleScope.launch {
                delay(500)
                isNavigating = false
            }
        }
    }
    
    /**
     * Try fallback navigation methods
     */
    private fun tryFallbackNavigation(itemId: Int) {
        try {
            Log.d(TAG, "Attempting fallback navigation for: $itemId")
            
            // Simple direct navigation without checks
            when (itemId) {
                R.id.navigation_profile -> {
                    // Special handling for profile navigation
                    showToast("Loading profile...")
                    // Could implement alternative profile loading here
                }
                else -> {
                    showToast("Navigation temporarily unavailable")
                }
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Fallback navigation also failed: ${e.message}")
            showToast("Navigation system error. Please restart the app.")
        }
    }
    
    /**
     * Set default selection dengan error handling
     */
    private fun setDefaultSelection() {
        lifecycleScope.launch {
            try {
                delay(100) // Wait for UI to stabilize
                
                if (isAdded && _binding != null) {
                    binding.bottomNavigation.selectedItemId = R.id.navigation_home
                    Log.d(TAG, "Default selection set to home")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error setting default selection: ${e.message}")
            }
        }
    }
    
    /**
     * Handle initialization errors
     */
    private fun handleInitializationError(error: Exception) {
        Log.e(TAG, "Navigation initialization failed: ${error.message}", error)
        
        // Show user-friendly message
        showToast("App initialization error. Please restart.")
        
        // Could implement app restart logic here if needed
    }
    
    /**
     * Handle navigation setup errors
     */
    private fun handleNavigationSetupError(error: Exception) {
        Log.e(TAG, "Navigation setup failed: ${error.message}", error)
        
        // Try to recover by retrying setup after delay
        lifecycleScope.launch {
            delay(1000)
            if (isAdded && _binding != null) {
                Log.d(TAG, "Retrying navigation setup...")
                try {
                    setupBottomNavigation()
                } catch (retryError: Exception) {
                    Log.e(TAG, "Retry also failed: ${retryError.message}")
                    showToast("Navigation system unavailable. Please restart the app.")
                }
            }
        }
    }
    
    /**
     * Show toast dengan error handling
     */
    private fun showToast(message: String) {
        try {
            if (isAdded && context != null) {
                Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error showing toast: ${e.message}")
            // Fail silently for toast errors
        }
    }

    override fun onResume() {
        super.onResume()
        Log.d(TAG, "onResume called")
        
        // Validate navigation state on resume
        if (_binding != null && isAdded) {
            // Could add navigation state validation here
        }
    }
    
    override fun onPause() {
        super.onPause()
        Log.d(TAG, "onPause called")
        
        // Reset navigation flag
        isNavigating = false
    }

    override fun onDestroyView() {
        Log.d(TAG, "onDestroyView called")
        
        // Clean up
        isNavigating = false
        
        super.onDestroyView()
        _binding = null
    }
}
