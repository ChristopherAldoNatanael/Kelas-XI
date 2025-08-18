package com.christopheraldoo.wavesoffood.ui.login

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.christopheraldoo.wavesoffood.R
import com.christopheraldoo.wavesoffood.auth.AuthResult
import com.christopheraldoo.wavesoffood.auth.FirebaseAuthManager
import com.christopheraldoo.wavesoffood.databinding.FragmentLoginBinding
import kotlinx.coroutines.launch

/**
 * LoginFragment dengan Firebase Authentication
 * Mendukung Email/Password dan Google Sign-In
 */
class LoginFragment : Fragment() {

    private var _binding: FragmentLoginBinding? = null
    private val binding get() = _binding!!
    
    // Firebase Auth Manager
    private lateinit var authManager: FirebaseAuthManager
    
    // Google Sign-In Launcher
    private val googleSignInLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            handleGoogleSignInResult(result.data)
        } else {
            hideLoading()
            showToast("Google Sign-In cancelled")
        }
    }

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentLoginBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Initialize Firebase Auth Manager
        initializeFirebaseAuth()
        
        // Setup UI
        setupClickListeners()
        setupFormValidation()
        
        // Check if user already logged in
        checkExistingLogin()
    }
    
    /**
     * Initialize Firebase Authentication
     */
    private fun initializeFirebaseAuth() {
        try {
            authManager = FirebaseAuthManager.getInstance()
            authManager.initializeGoogleSignIn(requireContext())
            Log.d("LoginFragment", "Firebase Auth initialized successfully")
        } catch (e: Exception) {
            Log.e("LoginFragment", "Error initializing Firebase Auth: ${e.message}")
            showToast("Authentication service unavailable")
        }
    }
    
    /**
     * Check if user is already logged in
     */
    private fun checkExistingLogin() {
        if (authManager.isUserLoggedIn()) {
            Log.d("LoginFragment", "User already logged in, navigating to home")
            navigateToHome()
        }
    }

    /**
     * Setup click listeners untuk semua tombol
     */
    private fun setupClickListeners() {
        // Login button dengan Firebase
        binding.btnLogin.setOnClickListener {
            performFirebaseLogin()
        }
        
        // Sign up button
        binding.btnSignUp.setOnClickListener {
            navigateToRegister()
        }
        
        // Google Sign-In button
        binding.btnGoogle.setOnClickListener {
            performGoogleSignIn()
        }
        
        // Facebook button (placeholder)
        binding.btnFacebook.setOnClickListener {
            showToast("Facebook login will be available soon!")
        }
    }

    /**
     * Setup form validation dengan real-time feedback
     */
    private fun setupFormValidation() {
        // Email validation
        binding.etEmail.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validateEmail(s.toString())
                updateLoginButtonState()
            }
        })

        // Password validation
        binding.etPassword.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validatePassword(s.toString())
                updateLoginButtonState()
            }
        })
    }

    /**
     * Perform Firebase Email/Password Login
     */
    private fun performFirebaseLogin() {
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()
        
        // Validate input
        if (!isFormValid(email, password)) {
            return
        }
        
        // Show loading state
        showLoading()
        
        // Perform Firebase login
        lifecycleScope.launch {
            try {
                when (val result = authManager.loginWithEmail(email, password)) {
                    is AuthResult.Success -> {
                        hideLoading()
                        val user = result.user
                        showToast("Welcome back, ${user?.displayName ?: user?.email ?: "User"}!")
                        navigateToHome()
                    }
                    is AuthResult.Error -> {
                        hideLoading()
                        handleAuthError(result.message)
                    }
                }
            } catch (e: Exception) {
                hideLoading()
                Log.e("LoginFragment", "Login error: ${e.message}")
                showToast("Login failed. Please try again.")
            }
        }
    }
    
    /**
     * Perform Google Sign-In
     */
    private fun performGoogleSignIn() {
        try {
            showLoading()
            val signInIntent = authManager.getGoogleSignInIntent()
            
            if (signInIntent != null) {
                googleSignInLauncher.launch(signInIntent)
            } else {
                hideLoading()
                showToast("Google Sign-In is not available")
            }
        } catch (e: Exception) {
            hideLoading()
            Log.e("LoginFragment", "Google Sign-In error: ${e.message}")
            showToast("Google Sign-In failed. Please try again.")
        }
    }
    
    /**
     * Handle Google Sign-In Result
     */
    private fun handleGoogleSignInResult(data: Intent?) {
        lifecycleScope.launch {
            try {
                when (val result = authManager.handleGoogleSignInResult(data)) {
                    is AuthResult.Success -> {
                        hideLoading()
                        val user = result.user
                        showToast("Welcome, ${user?.displayName ?: user?.email ?: "User"}!")
                        navigateToHome()
                    }
                    is AuthResult.Error -> {
                        hideLoading()
                        handleAuthError(result.message)
                    }
                }
            } catch (e: Exception) {
                hideLoading()
                Log.e("LoginFragment", "Google Sign-In result error: ${e.message}")
                showToast("Google Sign-In failed. Please try again.")
            }
        }
    }

    /**
     * Validate email format
     */
    private fun validateEmail(email: String): Boolean {
        val isValid = email.contains("@") && email.contains(".") && email.length >= 5
        
        if (email.isNotEmpty() && !isValid) {
            binding.tilEmail.error = "Please enter a valid email address"
        } else {
            binding.tilEmail.error = null
        }
        
        return isValid || email.isEmpty()
    }

    /**
     * Validate password
     */
    private fun validatePassword(password: String): Boolean {
        val isValid = password.length >= 6
        
        if (password.isNotEmpty() && !isValid) {
            binding.tilPassword.error = "Password must be at least 6 characters"
        } else {
            binding.tilPassword.error = null
        }
        
        return isValid || password.isEmpty()
    }

    /**
     * Check if form is valid
     */
    private fun isFormValid(email: String, password: String): Boolean {
        if (email.isEmpty()) {
            binding.tilEmail.error = "Email is required"
            binding.etEmail.requestFocus()
            return false
        }
        
        if (password.isEmpty()) {
            binding.tilPassword.error = "Password is required"
            binding.etPassword.requestFocus()
            return false
        }
        
        if (!validateEmail(email)) {
            binding.etEmail.requestFocus()
            return false
        }
        
        if (!validatePassword(password)) {
            binding.etPassword.requestFocus()
            return false
        }
        
        return true
    }

    /**
     * Update login button state based on form validity
     */
    private fun updateLoginButtonState() {
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()
        
        binding.btnLogin.isEnabled = email.isNotEmpty() && password.isNotEmpty()
    }

    /**
     * Handle authentication errors dengan pesan yang user-friendly
     */
    private fun handleAuthError(errorMessage: String) {
        val userFriendlyMessage = when {
            errorMessage.contains("network", ignoreCase = true) -> 
                getString(R.string.auth_error_network)
            errorMessage.contains("invalid-email", ignoreCase = true) -> 
                getString(R.string.auth_error_invalid_email)
            errorMessage.contains("user-not-found", ignoreCase = true) -> 
                getString(R.string.auth_error_user_not_found)
            errorMessage.contains("wrong-password", ignoreCase = true) -> 
                getString(R.string.auth_error_wrong_password)
            errorMessage.contains("too-many-requests", ignoreCase = true) -> 
                "Too many failed attempts. Please try again later."
            else -> errorMessage
        }
        
        showToast(userFriendlyMessage)
    }

    /**
     * Show loading state
     */
    private fun showLoading() {
        binding.btnLogin.isEnabled = false
        binding.btnLogin.text = getString(R.string.auth_loading_login)
        binding.btnGoogle.isEnabled = false
        binding.btnFacebook.isEnabled = false
    }

    /**
     * Hide loading state
     */
    private fun hideLoading() {
        binding.btnLogin.isEnabled = true
        binding.btnLogin.text = "Login"
        binding.btnGoogle.isEnabled = true
        binding.btnFacebook.isEnabled = true
    }

    /**
     * Navigate to home screen
     */    private fun navigateToHome() {
        try {
            if (isAdded && activity != null) {
                findNavController().navigate(R.id.action_loginFragment_to_mainFragment)
            }        } catch (e: Exception) {
            Log.e("LoginFragment", "Navigation error: ${e.message}")
            // Fallback: restart app atau manual navigation
            activity?.finish()
            activity?.intent?.let { startActivity(it) }
        }
    }

    /**
     * Navigate to register screen
     */    private fun navigateToRegister() {
        try {
            findNavController().navigate(R.id.action_loginFragment_to_registerFragment)
        } catch (e: Exception) {
            Log.e("LoginFragment", "Navigation to register error: ${e.message}")
            showToast("Navigation error. Please try again.")
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
