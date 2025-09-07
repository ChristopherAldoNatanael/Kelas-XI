package com.christopheraldoo.wavesoffood.ui.register

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
import com.christopheraldoo.wavesoffood.databinding.FragmentRegisterBinding
import kotlinx.coroutines.launch

/**
 * RegisterFragment dengan Firebase Authentication
 * Mendukung Email/Password Registration dan Google Sign-In
 */
class RegisterFragment : Fragment() {

    private var _binding: FragmentRegisterBinding? = null
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
        _binding = FragmentRegisterBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Initialize Firebase Auth Manager
        initializeFirebaseAuth()
        
        // Setup UI
        setupClickListeners()
        setupFormValidation()
    }
    
    /**
     * Initialize Firebase Authentication
     */
    private fun initializeFirebaseAuth() {
        try {
            authManager = FirebaseAuthManager.getInstance()
            authManager.initializeGoogleSignIn(requireContext())
            Log.d("RegisterFragment", "Firebase Auth initialized successfully")
        } catch (e: Exception) {
            Log.e("RegisterFragment", "Error initializing Firebase Auth: ${e.message}")
            showToast("Authentication service unavailable")
        }
    }

    /**
     * Setup click listeners untuk semua tombol
     */
    private fun setupClickListeners() {
        // Register button dengan Firebase
        binding.btnRegister.setOnClickListener {
            performFirebaseRegister()
        }
          // Login button (navigate to login)
        binding.tvAlreadyHaveAccount.setOnClickListener {
            navigateToLogin()
        }
        
        // Google Sign-In button
        binding.btnGoogle.setOnClickListener {
            performGoogleSignIn()
        }
        
        // Facebook button (placeholder)
        binding.btnFacebook.setOnClickListener {
            showToast("Facebook registration will be available soon!")
        }
    }

    /**
     * Setup form validation dengan real-time feedback
     */
    private fun setupFormValidation() {
        // Full Name validation
        binding.etFullName.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validateFullName(s.toString())
                updateRegisterButtonState()
            }
        })

        // Email validation
        binding.etEmail.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validateEmail(s.toString())
                updateRegisterButtonState()
            }
        })

        // Password validation
        binding.etPassword.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validatePassword(s.toString())
                updateRegisterButtonState()
            }
        })

        // Confirm Password validation
        binding.etConfirmPassword.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                validateConfirmPassword(s.toString())
                updateRegisterButtonState()
            }
        })
    }

    /**
     * Perform Firebase Email/Password Registration
     */
    private fun performFirebaseRegister() {
        val fullName = binding.etFullName.text.toString().trim()
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()
        val confirmPassword = binding.etConfirmPassword.text.toString().trim()
        
        // Validate input
        if (!isFormValid(fullName, email, password, confirmPassword)) {
            return
        }
        
        // Show loading state
        showLoading()
        
        // Perform Firebase registration
        lifecycleScope.launch {
            try {
                when (val result = authManager.registerWithEmail(email, password, fullName)) {
                    is AuthResult.Success -> {
                        hideLoading()
                        val user = result.user
                        showToast("Welcome, ${user?.displayName ?: fullName}!")
                        navigateToHome()
                    }
                    is AuthResult.Error -> {
                        hideLoading()
                        handleAuthError(result.message)
                    }
                }
            } catch (e: Exception) {
                hideLoading()
                Log.e("RegisterFragment", "Registration error: ${e.message}")
                showToast("Registration failed. Please try again.")
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
            if (signInIntent == null) {
                hideLoading()
                showToast("Google Sign-In intent is null. Cek konfigurasi Firebase dan client_id.")
                Log.e("RegisterFragment", "Google Sign-In intent is null.")
                return
            }
            Log.d("RegisterFragment", "Launching Google Sign-In intent...")
            googleSignInLauncher.launch(signInIntent)
        } catch (e: Exception) {
            hideLoading()
            Log.e("RegisterFragment", "Google Sign-In error: ${e.message}", e)
            showToast("Google Sign-In gagal: ${e.message}")
        }
    }
    
    /**
     * Handle Google Sign-In Result
     */
    private fun handleGoogleSignInResult(data: Intent?) {
        lifecycleScope.launch {
            try {
                if (data == null) {
                    hideLoading()
                    showToast("Google Sign-In gagal: data intent null.")
                    Log.e("RegisterFragment", "Google Sign-In result intent is null.")
                    return@launch
                }
                Log.d("RegisterFragment", "Handling Google Sign-In result...")
                when (val result = authManager.handleGoogleSignInResult(data)) {
                    is AuthResult.Success -> {
                        hideLoading()
                        val user = result.user
                        showToast("Welcome, ${user?.displayName ?: user?.email ?: "User"}!")
                        navigateToHome()
                    }
                    is AuthResult.Error -> {
                        hideLoading()
                        showToast("Google Sign-In gagal: ${result.message}")
                        Log.e("RegisterFragment", "Google Sign-In error: ${result.message}")
                    }
                }
            } catch (e: Exception) {
                hideLoading()
                Log.e("RegisterFragment", "Google Sign-In result error: ${e.message}", e)
                showToast("Google Sign-In gagal: ${e.message}")
            }
        }
    }

    // Validation methods (same as before but with better error handling)
    private fun validateFullName(name: String): Boolean {
        val isValid = name.length >= 2
        
        if (name.isNotEmpty() && !isValid) {
            binding.tilFullName.error = "Name must be at least 2 characters"
        } else {
            binding.tilFullName.error = null
        }
        
        return isValid || name.isEmpty()
    }

    private fun validateEmail(email: String): Boolean {
        val isValid = email.contains("@") && email.contains(".") && email.length >= 5
        
        if (email.isNotEmpty() && !isValid) {
            binding.tilEmail.error = "Please enter a valid email address"
        } else {
            binding.tilEmail.error = null
        }
        
        return isValid || email.isEmpty()
    }

    private fun validatePassword(password: String): Boolean {
        val isValid = password.length >= 6
        
        if (password.isNotEmpty() && !isValid) {
            binding.tilPassword.error = "Password must be at least 6 characters"
        } else {
            binding.tilPassword.error = null
        }
        
        return isValid || password.isEmpty()
    }

    private fun validateConfirmPassword(confirmPassword: String): Boolean {
        val password = binding.etPassword.text.toString()
        val isValid = confirmPassword == password && confirmPassword.isNotEmpty()
        
        if (confirmPassword.isNotEmpty() && !isValid) {
            binding.tilConfirmPassword.error = "Passwords do not match"
        } else {
            binding.tilConfirmPassword.error = null
        }
        
        return isValid || confirmPassword.isEmpty()
    }

    private fun isFormValid(fullName: String, email: String, password: String, confirmPassword: String): Boolean {
        var isValid = true
        
        if (fullName.isEmpty()) {
            binding.tilFullName.error = "Full name is required"
            if (isValid) binding.etFullName.requestFocus()
            isValid = false
        }
        
        if (email.isEmpty()) {
            binding.tilEmail.error = "Email is required"
            if (isValid) binding.etEmail.requestFocus()
            isValid = false
        }
        
        if (password.isEmpty()) {
            binding.tilPassword.error = "Password is required"
            if (isValid) binding.etPassword.requestFocus()
            isValid = false
        }
        
        if (confirmPassword.isEmpty()) {
            binding.tilConfirmPassword.error = "Please confirm your password"
            if (isValid) binding.etConfirmPassword.requestFocus()
            isValid = false
        }
        
        if (!validateFullName(fullName)) {
            if (isValid) binding.etFullName.requestFocus()
            isValid = false
        }
        
        if (!validateEmail(email)) {
            if (isValid) binding.etEmail.requestFocus()
            isValid = false
        }
        
        if (!validatePassword(password)) {
            if (isValid) binding.etPassword.requestFocus()
            isValid = false
        }
        
        if (!validateConfirmPassword(confirmPassword)) {
            if (isValid) binding.etConfirmPassword.requestFocus()
            isValid = false
        }
        
        return isValid
    }

    private fun updateRegisterButtonState() {
        val fullName = binding.etFullName.text.toString().trim()
        val email = binding.etEmail.text.toString().trim()
        val password = binding.etPassword.text.toString().trim()
        val confirmPassword = binding.etConfirmPassword.text.toString().trim()
        
        binding.btnRegister.isEnabled = fullName.isNotEmpty() && 
                email.isNotEmpty() && 
                password.isNotEmpty() && 
                confirmPassword.isNotEmpty()
    }

    private fun handleAuthError(errorMessage: String) {
        val userFriendlyMessage = when {
            errorMessage.contains("network", ignoreCase = true) -> 
                getString(R.string.auth_error_network)
            errorMessage.contains("invalid-email", ignoreCase = true) -> 
                getString(R.string.auth_error_invalid_email)
            errorMessage.contains("weak-password", ignoreCase = true) -> 
                getString(R.string.auth_error_weak_password)
            errorMessage.contains("email-already-in-use", ignoreCase = true) -> 
                getString(R.string.auth_error_email_already_in_use)
            else -> errorMessage
        }
        
        showToast(userFriendlyMessage)
    }

    private fun showLoading() {
        binding.btnRegister.isEnabled = false
        binding.btnRegister.text = getString(R.string.auth_loading_register)
        binding.btnGoogle.isEnabled = false
        binding.btnFacebook.isEnabled = false
    }

    private fun hideLoading() {
        binding.btnRegister.isEnabled = true
        binding.btnRegister.text = "Create Account"
        binding.btnGoogle.isEnabled = true
        binding.btnFacebook.isEnabled = true
    }    private fun navigateToHome() {
        try {
            if (isAdded && activity != null) {
                findNavController().navigate(R.id.action_registerFragment_to_mainFragment)
            }        } catch (e: Exception) {
            Log.e("RegisterFragment", "Navigation error: ${e.message}")
            activity?.finish()
            activity?.intent?.let { startActivity(it) }
        }
    }

    private fun navigateToLogin() {
        try {
            findNavController().navigateUp()
        } catch (e: Exception) {
            Log.e("RegisterFragment", "Navigation to login error: ${e.message}")
            showToast("Navigation error. Please try again.")
        }
    }

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
