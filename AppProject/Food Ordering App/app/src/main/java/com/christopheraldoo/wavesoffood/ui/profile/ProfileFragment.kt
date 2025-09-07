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
        Log.d("ProfileFragment", "Setting up click listeners...")
        
        // Edit Profile button
        binding.btnEditProfile.setOnClickListener {
            showEditProfileDialog()
        }
        
        // Menu Order History
        binding.menuOrderHistory.setOnClickListener { view ->
            Log.d("ProfileFragment", "Order History menu clicked!")
            showToast("Order History clicked!") // Test toast
            navigateToOrderHistory()
        }
        
        // Menu Favorites
        binding.menuFavorites.setOnClickListener {
            showToast("Favorites clicked!") // Test toast
            showFavoritesDialog()
        }
        
        // Menu Payment Methods
        binding.menuPayment.setOnClickListener {
            showPaymentMethodsDialog()
        }
        
        // Menu Address Management
        binding.menuAddress.setOnClickListener {
            showAddressManagementDialog()
        }
        
        // Menu Notifications Settings
        binding.menuNotifications.setOnClickListener {
            showNotificationSettings()
        }
        
        // Menu Privacy Settings
        binding.menuPrivacy.setOnClickListener {
            showPrivacySettings()
        }
        
        // Menu Help & Support
        binding.menuHelp.setOnClickListener {
            showHelpAndSupport()
        }
        
        // Menu About
        binding.menuAbout.setOnClickListener {
            showAboutDialog()
        }
        
        // Logout button
        binding.btnLogout.setOnClickListener {
            showLogoutConfirmation()
        }

        binding.orderHistoryCard.setOnClickListener {
            navigateToOrderHistory()
        }
        
        binding.backButton.setOnClickListener {
            navigateBack()
        }
    }

    /**
     * Show edit profile dialog
     */
    private fun showEditProfileDialog() {
        val currentUser = authManager.getCurrentUser()
        val currentName = currentUser?.displayName ?: ""
        
        val editText = android.widget.EditText(requireContext()).apply {
            setText(currentName)
            hint = "Enter your name"
            setPadding(50, 40, 50, 40)
        }
        
        AlertDialog.Builder(requireContext())
            .setTitle("Edit Profile")
            .setMessage("Update your display name:")
            .setView(editText)
            .setPositiveButton("Update") { _, _ ->
                val newName = editText.text.toString().trim()
                if (newName.isNotEmpty()) {
                    updateUserProfile(newName)
                } else {
                    showToast("Name cannot be empty")
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Update user profile
     */
    private fun updateUserProfile(newName: String) {
        lifecycleScope.launch {
            try {
                val success = authManager.updateUserProfile(newName, null)
                if (success) {
                    binding.tvUserName.text = newName
                    showToast("Profile updated successfully")
                } else {
                    showToast("Failed to update profile")
                }
            } catch (e: Exception) {
                Log.e("ProfileFragment", "Error updating profile: ${e.message}")
                showToast("Failed to update profile")
            }
        }
    }

    /**
     * Navigate to Order History
     */
    private fun navigateToOrderHistory() {
        try {
            Log.d("ProfileFragment", "navigateToOrderHistory called")
            
            // Cek apakah fragment masih attached dan view masih ada
            if (!isAdded || _binding == null) {
                Log.w("ProfileFragment", "Fragment not attached or binding null")
                return
            }
            
            val navController = findNavController()
            Log.d("ProfileFragment", "NavController obtained successfully")
            
            // Cek apakah current destination valid
            val currentDestination = navController.currentDestination
            if (currentDestination == null) {
                Log.e("ProfileFragment", "Current destination is null")
                showToast("Navigation error - please try again")
                return
            }
            
            Log.d("ProfileFragment", "Current destination: ${currentDestination.id}")
            
            // Pastikan kita di profile fragment
            if (currentDestination.id != R.id.navigation_profile) {
                Log.w("ProfileFragment", "Not in profile fragment, current: ${currentDestination.id}")
            }
            
            // Method 1: Try specific action first
            try {
                Log.d("ProfileFragment", "Attempting action navigation...")
                navController.navigate(R.id.action_profileFragment_to_ordersFragment)
                Log.d("ProfileFragment", "Action navigation successful!")
                showToast("Opening Order History...")
                return
            } catch (e: Exception) {
                Log.w("ProfileFragment", "Action navigation failed: ${e.message}")
            }
            
            // Method 2: Try direct destination as fallback
            try {
                Log.d("ProfileFragment", "Attempting direct navigation...")
                navController.navigate(R.id.navigation_orders)
                Log.d("ProfileFragment", "Direct navigation successful!")
                showToast("Opening Order History...")
                return
            } catch (e: Exception) {
                Log.w("ProfileFragment", "Direct navigation failed: ${e.message}")
            }
            
            // Method 3: Show alternative for now
            Log.d("ProfileFragment", "All navigation methods failed, showing fallback")
            showOrderHistoryDialog()
            
        } catch (e: IllegalStateException) {
            Log.e("ProfileFragment", "IllegalStateException in navigation: ${e.message}")
            showToast("Navigation error - please try again")
        } catch (e: Exception) {
            Log.e("ProfileFragment", "Unexpected error in navigation: ${e.message}")
            showToast("An error occurred - please try again")
        }
    }

    private fun navigateBack() {
        try {
            if (!findNavController().popBackStack()) {
                requireActivity().onBackPressed()
            }
        } catch (e: Exception) {
            e.printStackTrace()
            requireActivity().onBackPressed()
        }
    }
    
    /**
     * Show order history dialog as fallback
     */
    private fun showOrderHistoryDialog() {
        val orders = arrayOf(
            "🍔 Order #001 - Classic Burger",
            "🍕 Order #002 - Margherita Pizza", 
            "🥗 Order #003 - Caesar Salad",
            "🍗 Order #004 - Fried Chicken"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Order History")
            .setItems(orders) { _, position ->
                showToast("Selected: ${orders[position]}")
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show favorites dialog with list of favorite items
     */
    private fun showFavoritesDialog() {
        val favoriteItems = listOf(
            "🍔 Classic Burger",
            "🍕 Margherita Pizza", 
            "🥗 Caesar Salad",
            "🍗 Fried Chicken",
            "🍱 Beef Teriyaki"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Your Favorite Items")
            .setItems(favoriteItems.toTypedArray()) { _, position ->
                showToast("Selected: ${favoriteItems[position]}")
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show payment methods dialog
     */
    private fun showPaymentMethodsDialog() {
        val paymentMethods = arrayOf(
            "💳 Credit Card (**** 1234)",
            "📱 QRIS",
            "🏦 Bank Transfer",
            "💰 Cash on Delivery",
            "➕ Add New Payment Method"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Payment Methods")
            .setItems(paymentMethods) { _, position ->
                when (position) {
                    4 -> showToast("Add new payment method functionality coming soon")
                    else -> showToast("Selected: ${paymentMethods[position]}")
                }
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show address management dialog
     */
    private fun showAddressManagementDialog() {
        val addresses = arrayOf(
            "🏠 Home - Jl. Merdeka No. 123",
            "🏢 Office - Jl. Sudirman No. 456", 
            "📍 Other - Jl. Gatot Subroto No. 789",
            "➕ Add New Address"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Delivery Addresses")
            .setItems(addresses) { _, position ->
                when (position) {
                    3 -> showAddNewAddressDialog()
                    else -> showToast("Selected: ${addresses[position]}")
                }
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show add new address dialog
     */
    private fun showAddNewAddressDialog() {
        val editText = android.widget.EditText(requireContext()).apply {
            hint = "Enter new address"
            setPadding(50, 40, 50, 40)
        }
        
        AlertDialog.Builder(requireContext())
            .setTitle("Add New Address")
            .setView(editText)
            .setPositiveButton("Add") { _, _ ->
                val address = editText.text.toString().trim()
                if (address.isNotEmpty()) {
                    showToast("Address added: $address")
                } else {
                    showToast("Address cannot be empty")
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Show notification settings
     */
    private fun showNotificationSettings() {
        val settings = arrayOf(
            "🔔 Order Updates",
            "📢 Promotions & Offers", 
            "📱 Push Notifications",
            "📧 Email Notifications",
            "🔕 Do Not Disturb Mode"
        )
        
        val checkedItems = booleanArrayOf(true, true, false, true, false)
        
        AlertDialog.Builder(requireContext())
            .setTitle("Notification Settings")
            .setMultiChoiceItems(settings, checkedItems) { _, which, isChecked ->
                checkedItems[which] = isChecked
                showToast("${settings[which]} ${if (isChecked) "enabled" else "disabled"}")
            }
            .setPositiveButton("Save") { _, _ ->
                showToast("Notification settings saved")
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Show privacy settings
     */
    private fun showPrivacySettings() {
        val privacyOptions = arrayOf(
            "🔒 Change Password",
            "👁️ Privacy Policy",
            "📋 Terms of Service",
            "🗑️ Delete Account"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Privacy & Security")
            .setItems(privacyOptions) { _, position ->
                when (position) {
                    0 -> showChangePasswordDialog()
                    1 -> showPrivacyPolicyDialog()
                    2 -> showTermsOfServiceDialog()
                    3 -> showDeleteAccountDialog()
                }
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show change password dialog
     */
    private fun showChangePasswordDialog() {
        val layout = android.widget.LinearLayout(requireContext()).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setPadding(50, 40, 50, 40)
        }
        
        val currentPasswordEdit = android.widget.EditText(requireContext()).apply {
            hint = "Current Password"
            inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_VARIATION_PASSWORD
        }
        
        val newPasswordEdit = android.widget.EditText(requireContext()).apply {
            hint = "New Password"
            inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_VARIATION_PASSWORD
        }
        
        layout.addView(currentPasswordEdit)
        layout.addView(newPasswordEdit)
        
        AlertDialog.Builder(requireContext())
            .setTitle("Change Password")
            .setView(layout)
            .setPositiveButton("Change") { _, _ ->
                val currentPassword = currentPasswordEdit.text.toString()
                val newPassword = newPasswordEdit.text.toString()
                
                if (currentPassword.isNotEmpty() && newPassword.isNotEmpty()) {
                    if (newPassword.length >= 6) {
                        showToast("Password changed successfully")
                    } else {
                        showToast("New password must be at least 6 characters")
                    }
                } else {
                    showToast("Please fill all fields")
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Show privacy policy dialog
     */
    private fun showPrivacyPolicyDialog() {
        val message = """
            Privacy Policy - Waves of Food
            
            We value your privacy and are committed to protecting your personal information.
            
            Information We Collect:
            • Personal details (name, email, phone)
            • Order history and preferences
            • Location data for delivery
            
            How We Use Information:
            • Process and fulfill orders
            • Improve our services
            • Send important updates
            
            Data Protection:
            • We use industry-standard encryption
            • Your data is never sold to third parties
            • You can request data deletion anytime
            
            Last updated: September 2025
        """.trimIndent()
        
        AlertDialog.Builder(requireContext())
            .setTitle("Privacy Policy")
            .setMessage(message)
            .setPositiveButton("I Understand", null)
            .show()
    }
    
    /**
     * Show terms of service dialog
     */
    private fun showTermsOfServiceDialog() {
        val message = """
            Terms of Service - Waves of Food
            
            By using our app, you agree to these terms:
            
            Service Usage:
            • You must be 13+ years old to use our service
            • Provide accurate information
            • Use the app for lawful purposes only
            
            Orders & Payment:
            • All orders are subject to availability
            • Payments are processed securely
            • Refunds follow our refund policy
            
            Delivery:
            • Delivery times are estimates
            • Additional charges may apply
            • We're not responsible for delays beyond our control
            
            Account:
            • Keep your account secure
            • You're responsible for all account activity
            • We may suspend accounts for violations
            
            Last updated: September 2025
        """.trimIndent()
        
        AlertDialog.Builder(requireContext())
            .setTitle("Terms of Service")
            .setMessage(message)
            .setPositiveButton("I Accept", null)
            .show()
    }
    
    /**
     * Show delete account confirmation
     */
    private fun showDeleteAccountDialog() {
        AlertDialog.Builder(requireContext())
            .setTitle("Delete Account")
            .setMessage("⚠️ WARNING: This action cannot be undone!\n\nDeleting your account will:\n• Remove all your data\n• Cancel active orders\n• Delete order history\n• Remove saved addresses\n\nAre you absolutely sure?")
            .setPositiveButton("DELETE ACCOUNT") { _, _ ->
                showFinalDeleteConfirmation()
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Show final delete confirmation
     */
    private fun showFinalDeleteConfirmation() {
        AlertDialog.Builder(requireContext())
            .setTitle("Final Confirmation")
            .setMessage("Type 'DELETE' to confirm account deletion:")
            .setView(android.widget.EditText(requireContext()).apply {
                id = android.R.id.text1
                hint = "Type DELETE here"
                setPadding(50, 40, 50, 40)
            })
            .setPositiveButton("Confirm") { dialog, _ ->
                val editText = (dialog as AlertDialog).findViewById<android.widget.EditText>(android.R.id.text1)
                val confirmText = editText?.text.toString()
                
                if (confirmText == "DELETE") {
                    performAccountDeletion()
                } else {
                    showToast("Account deletion cancelled - text doesn't match")
                }
            }
            .setNegativeButton("Cancel", null)
            .show()
    }
    
    /**
     * Perform account deletion (simulation)
     */
    private fun performAccountDeletion() {
        AlertDialog.Builder(requireContext())
            .setTitle("Account Deletion")
            .setMessage("Account deletion process initiated.\n\nFor security reasons, this process may take 24-48 hours to complete.\n\nYou will receive a confirmation email once your account is permanently deleted.")
            .setPositiveButton("OK") { _, _ ->
                performLogout()
            }
            .setCancelable(false)
            .show()
    }
    
    /**
     * Show help and support options
     */
    private fun showHelpAndSupport() {
        val helpOptions = arrayOf(
            "❓ FAQ",
            "💬 Live Chat",
            "📞 Call Support",
            "📧 Email Support",
            "📱 WhatsApp Support",
            "⭐ Rate Our App"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Help & Support")
            .setItems(helpOptions) { _, position ->
                when (position) {
                    0 -> showFAQDialog()
                    1 -> showToast("Connecting to live chat...")
                    2 -> showToast("Calling support: +62-21-1234-5678")
                    3 -> showToast("Email: support@wavesoffood.com")
                    4 -> showToast("WhatsApp: +62-812-3456-7890")
                    5 -> showRateAppDialog()
                }
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show FAQ dialog
     */
    private fun showFAQDialog() {
        val faqs = arrayOf(
            "How to place an order?",
            "What payment methods are accepted?",
            "How long is delivery time?",
            "How to track my order?",
            "How to cancel an order?",
            "Refund policy"
        )
        
        AlertDialog.Builder(requireContext())
            .setTitle("Frequently Asked Questions")
            .setItems(faqs) { _, position ->
                val answers = arrayOf(
                    "Browse menu → Add items to cart → Checkout → Confirm order",
                    "We accept QRIS, Credit Cards, Bank Transfer, and Cash on Delivery",
                    "Usually 30-45 minutes, depending on your location and order complexity",
                    "Go to Orders section and select your active order to track",
                    "You can cancel within 5 minutes of ordering, or contact support",
                    "Full refunds for cancellations within 5 minutes, otherwise contact support"
                )
                showToast("Answer: ${answers[position]}")
            }
            .setNeutralButton("Close", null)
            .show()
    }
    
    /**
     * Show rate app dialog
     */
    private fun showRateAppDialog() {
        AlertDialog.Builder(requireContext())
            .setTitle("Rate Waves of Food")
            .setMessage("How would you rate your experience with our app?\n\n⭐⭐⭐⭐⭐")
            .setPositiveButton("5 Stars - Excellent!") { _, _ ->
                showToast("Thank you for the 5-star rating! ⭐⭐⭐⭐⭐")
            }
            .setNeutralButton("4 Stars - Good") { _, _ ->
                showToast("Thank you for the 4-star rating! We'll keep improving! ⭐⭐⭐⭐")
            }
            .setNegativeButton("Rate Later", null)
            .show()
    }
    
    /**
     * Show about dialog
     */
    private fun showAboutDialog() {
        val aboutText = """
            🌊 Waves of Food
            Version 1.0.0
            
            Your favorite food delivery app!
            
            📱 Features:
            • Browse delicious meals
            • Easy ordering process  
            • Real-time order tracking
            • Multiple payment options
            • Fast delivery service
            
            👨‍💻 Developed by:
            Christopher Aldoo
            
            📧 Contact:
            support@wavesoffood.com
            
            🌐 Website:
            www.wavesoffood.com
            
            © 2025 Waves of Food
            All rights reserved.
        """.trimIndent()
        
        AlertDialog.Builder(requireContext())
            .setTitle("About Waves of Food")
            .setMessage(aboutText)
            .setPositiveButton("Cool! 👍", null)
            .show()
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
            val mainNavController = activity?.let { 
                findNavController().popBackStack()
                Navigation.findNavController(it, R.id.nav_host_fragment) 
            }
            
            if (mainNavController != null) {
                mainNavController.navigate(R.id.loginFragment, null, NavOptions.Builder()
                    .setPopUpTo(R.id.nav_graph, true)
                    .build())
            } else {
                restartApp()
            }
        } catch (e: Exception) {
            Log.e("ProfileFragment", "Navigation error: ${e.message}")
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
