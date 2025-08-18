package com.christopheraldoo.wavesoffood.ui.profile

import android.app.AlertDialog
import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.NavOptions
import androidx.navigation.Navigation
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.auth.FirebaseAuthManager
import com.christopheraldoo.wavesoffood.databinding.FragmentProfileBinding
import kotlinx.coroutines.launch

class ProfileFragment : Fragment() {
    
    private var _binding: FragmentProfileBinding? = null
    private val binding get() = _binding!!
    
    // Firebase Auth Manager
    private lateinit var authManager: FirebaseAuthManager

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentProfileBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Initialize Firebase Auth Manager
        authManager = FirebaseAuthManager.getInstance()
        
        // Setup UI
        setupUserInfo()
        setupClickListeners()
    }
    
    /**
     * Setup user information display
     */
    private fun setupUserInfo() {
        val currentUser = authManager.getCurrentUser()
        
        if (currentUser != null) {
            // Display user information
            binding.tvUserName.text = currentUser.displayName ?: "User"
            binding.tvUserEmail.text = currentUser.email ?: "No email"
            
            // Set profile image if available
            val photoUrl = currentUser.photoUrl
            if (photoUrl != null) {
                // Load image using your preferred image loading library
                // For now, we'll just show the default avatar
                Log.d("ProfileFragment", "User photo URL: $photoUrl")
            }
        } else {
            // User not logged in, redirect to login
            navigateToLogin()
        }
    }
    
    /**
     * Setup click listeners
     */
    private fun setupClickListeners() {
        // Logout button
        binding.btnLogout.setOnClickListener {
            showLogoutConfirmation()
        }
        
        // Edit Profile button
        binding.btnEditProfile.setOnClickListener {
            showToast("Edit profile feature coming soon!")
        }
          // Settings button
        binding.menuNotifications.setOnClickListener {
            showToast("Settings feature coming soon!")
        }
        
        // Help & Support button
        binding.menuHelp.setOnClickListener {
            showToast("Help & Support feature coming soon!")
        }
    }
    
    /**
     * Show logout confirmation dialog
     */
    private fun showLogoutConfirmation() {
        AlertDialog.Builder(requireContext())
            .setTitle("Logout")
            .setMessage("Are you sure you want to logout?")
            .setPositiveButton("Logout") { _, _ ->
                performLogout()
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Perform logout operation
     */
    private fun performLogout() {
        lifecycleScope.launch {
            try {
                // Show loading
                binding.btnLogout.isEnabled = false
                binding.btnLogout.text = "Logging out..."
                
                // Perform logout
                val success = authManager.logout()
                
                if (success) {
                    showToast("Logged out successfully")
                    navigateToLogin()
                } else {
                    showToast("Logout failed. Please try again.")
                    // Reset button state
                    binding.btnLogout.isEnabled = true
                    binding.btnLogout.text = "Logout"
                }
            } catch (e: Exception) {
                Log.e("ProfileFragment", "Logout error: ${e.message}")
                showToast("Logout failed. Please try again.")
                // Reset button state
                binding.btnLogout.isEnabled = true
                binding.btnLogout.text = "Logout"
            }
        }
    }
      /**
     * Navigate to login screen
     */
    private fun navigateToLogin() {
        try {
            // Since ProfileFragment is in main_nav_graph and login is in nav_graph,
            // we need to navigate through the activity's main navigation controller
            val mainNavController = activity?.let { 
                findNavController().popBackStack()
                Navigation.findNavController(it, R.id.nav_host_fragment) 
            }
            
            if (mainNavController != null) {
                // Clear the back stack and navigate to login
                mainNavController.navigate(R.id.loginFragment, null, NavOptions.Builder()
                    .setPopUpTo(R.id.nav_graph, true)
                    .build())
            } else {
                // Fallback: restart activity to go back to login flow
                restartApp()
            }
        } catch (e: Exception) {
            Log.e("ProfileFragment", "Navigation error: ${e.message}")
            // Fallback: restart the app
            restartApp()
        }
    }
    
    /**
     * Restart the app to go back to the beginning
     */
    private fun restartApp() {
        try {
            activity?.finish()
            activity?.intent?.let { startActivity(it) }
        } catch (e: Exception) {
            Log.e("ProfileFragment", "Error restarting app: ${e.message}")
            // Last resort: finish the activity
            activity?.finishAffinity()
        }
    }
    
    /**
     * Show toast message
     */
    private fun showToast(message: String) {
        if (isAdded && context != null) {
            Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}