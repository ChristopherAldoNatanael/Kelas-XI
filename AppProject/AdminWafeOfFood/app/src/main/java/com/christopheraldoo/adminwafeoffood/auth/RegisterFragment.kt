package com.christopheraldoo.adminwafeoffood.auth

import android.os.Bundle
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.Fragment
import androidx.lifecycle.ViewModelProvider
import com.christopheraldoo.adminwafeoffood.AuthActivity
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.viewmodel.AuthViewModel
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.common.api.ApiException
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout
import android.os.Handler
import android.os.Looper

class RegisterFragment : Fragment() {
    
    companion object {
        private const val TAG = "RegisterFragment"
        
        fun newInstance(): RegisterFragment {
            return RegisterFragment()
        }
    }
    
    // ViewModel
    private lateinit var authViewModel: AuthViewModel
    
    // Views
    private lateinit var ownerNameInput: TextInputEditText
    private lateinit var restaurantNameInput: TextInputEditText
    private lateinit var emailInput: TextInputEditText
    private lateinit var passwordInput: TextInputEditText
    private lateinit var confirmPasswordInput: TextInputEditText
    private lateinit var ownerNameLayout: TextInputLayout
    private lateinit var restaurantNameLayout: TextInputLayout
    private lateinit var emailLayout: TextInputLayout
    private lateinit var passwordLayout: TextInputLayout
    private lateinit var confirmPasswordLayout: TextInputLayout
    private lateinit var registerButton: Button
    private lateinit var googleSignInButton: Button
    private lateinit var loginLink: TextView
    private lateinit var progressBar: ProgressBar // Use ProgressBar instead of Lottie
    
    // Google Sign-In result launcher
    private val googleSignInLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        try {
            val task = GoogleSignIn.getSignedInAccountFromIntent(result.data)
            val account = task.getResult(ApiException::class.java)
            
            Log.d(TAG, "Google Sign-In successful: ${account.email}")
            authViewModel.signInWithGoogle(account)
            
        } catch (e: ApiException) {
            Log.e(TAG, "Google Sign-In failed: ${e.statusCode}", e)
            showErrorMessage("Google Sign-In gagal: ${e.message}")
        } catch (e: Exception) {
            Log.e(TAG, "Unexpected error during Google Sign-In", e)
            showErrorMessage("Terjadi kesalahan saat Google Sign-In")
        }
    }
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.fragment_register, container, false)
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initializeViews(view)
        initializeViewModel()
        setupClickListeners()
        observeViewModel() // HANYA panggil ini saja, jangan panggil observeAuthState()
        
        Log.d(TAG, "RegisterFragment initialized")
    }
    
    private fun initializeViews(view: View) {
        ownerNameInput = view.findViewById(R.id.et_owner_name)
        restaurantNameInput = view.findViewById(R.id.et_restaurant_name)
        emailInput = view.findViewById(R.id.et_email)
        passwordInput = view.findViewById(R.id.et_password)
        confirmPasswordInput = view.findViewById(R.id.et_confirm_password)
        ownerNameLayout = view.findViewById(R.id.til_owner_name)
        restaurantNameLayout = view.findViewById(R.id.til_restaurant_name)
        emailLayout = view.findViewById(R.id.til_email)
        passwordLayout = view.findViewById(R.id.til_password)
        confirmPasswordLayout = view.findViewById(R.id.til_confirm_password)
        registerButton = view.findViewById(R.id.btn_register)
        googleSignInButton = view.findViewById(R.id.btn_google_sign_in)
        loginLink = view.findViewById(R.id.tv_login_link)
        progressBar = view.findViewById(R.id.progress_bar)
    }
    
    private fun initializeViewModel() {
        authViewModel = ViewModelProvider(requireActivity())[AuthViewModel::class.java]
    }
    
    private fun setupClickListeners() {
        registerButton.setOnClickListener {
            performRegistration()
        }
        
        googleSignInButton.setOnClickListener {
            performGoogleSignIn()
        }
        
        loginLink.setOnClickListener {
            navigateToLogin()
        }
    }
    
    private fun observeViewModel() {
        // HANYA observer ini saja, hapus yang lain
        authViewModel.authState.observe(viewLifecycleOwner) { state ->
            Log.d(TAG, "Auth state changed: $state")
            
            when (state) {
                AuthViewModel.AuthState.AUTHENTICATED -> {
                    Log.d(TAG, "SUCCESS! Registration complete, navigating to MainActivity")
                    
                    // Stop loading immediately
                    progressBar.visibility = View.GONE
                    registerButton.isEnabled = true
                    registerButton.text = "✓ Success!"
                    googleSignInButton.isEnabled = true
                    
                    // Show success and navigate immediately
                    Toast.makeText(requireContext(), "Account created! Redirecting...", Toast.LENGTH_SHORT).show()
                    
                    // Navigate without delay
                    (activity as? AuthActivity)?.onAuthenticationSuccess()
                }
                
                AuthViewModel.AuthState.LOADING -> {
                    Log.d(TAG, "Registration in progress...")
                    progressBar.visibility = View.VISIBLE
                    registerButton.isEnabled = false
                    registerButton.text = "Creating Account..."
                    googleSignInButton.isEnabled = false
                }
                
                AuthViewModel.AuthState.ERROR -> {
                    Log.e(TAG, "Registration failed")
                    progressBar.visibility = View.GONE
                    registerButton.isEnabled = true
                    registerButton.text = "Daftar"
                    googleSignInButton.isEnabled = true
                    
                    // Show error message
                    val errorMsg = authViewModel.errorMessage.value ?: "Registration failed"
                    showErrorMessage(errorMsg)
                    authViewModel.clearError()
                }
                
                AuthViewModel.AuthState.UNAUTHENTICATED -> {
                    Log.d(TAG, "Not authenticated")
                    progressBar.visibility = View.GONE
                    registerButton.isEnabled = true
                    registerButton.text = "Daftar"
                    googleSignInButton.isEnabled = true
                }
                
                null -> {
                    Log.d(TAG, "Auth state null")
                    progressBar.visibility = View.GONE
                    registerButton.isEnabled = true
                    registerButton.text = "Daftar"
                    googleSignInButton.isEnabled = true
                }
            }
        }
    }
    
    private fun performRegistration() {
        if (!validateInput()) {
            return
        }
        
        val ownerName = ownerNameInput.text.toString().trim()
        val restaurantName = restaurantNameInput.text.toString().trim()
        val email = emailInput.text.toString().trim()
        val password = passwordInput.text.toString().trim()
        
        Log.d(TAG, "Starting registration for: $email")
        
        // Call AuthViewModel with default location
        authViewModel.signUp(email, password, ownerName, restaurantName, "Not specified")
    }
    
    private fun performGoogleSignIn() {
        try {
            Log.d(TAG, "Starting Google Sign In from Register")
            
            // Clear any previous errors
            authViewModel.clearError()
            
            // Get Google Sign-In client from ViewModel
            val googleSignInClient = authViewModel.googleSignInClient
            
            // Sign out from previous session to allow account selection
            googleSignInClient.signOut().addOnCompleteListener {
                // Start Google Sign-In intent
                val signInIntent = googleSignInClient.signInIntent
                googleSignInLauncher.launch(signInIntent)
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Error starting Google Sign In: ${e.message}", e)
            showErrorMessage("Gagal memulai Google Sign In")
        }
    }
    
    private fun validateInput(): Boolean {
        clearErrors()
        
        val ownerName = ownerNameInput.text.toString().trim()
        val restaurantName = restaurantNameInput.text.toString().trim()
        val email = emailInput.text.toString().trim()
        val password = passwordInput.text.toString().trim()
        val confirmPassword = confirmPasswordInput.text.toString().trim()
        
        // Owner name validation
        if (ownerName.isEmpty()) {
            ownerNameLayout.error = "Owner name is required"
            return false
        }
        
        // Restaurant name validation
        if (restaurantName.isEmpty()) {
            restaurantNameLayout.error = "Restaurant name is required"
            return false
        }
        
        // Email validation
        if (email.isEmpty()) {
            emailLayout.error = "Email is required"
            return false
        }
        
        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            emailLayout.error = "Please enter a valid email"
            return false
        }
        
        // Password validation
        if (password.isEmpty()) {
            passwordLayout.error = "Password is required"
            return false
        }
        
        if (password.length < 6) {
            passwordLayout.error = "Password must be at least 6 characters"
            return false
        }
        
        // Confirm password validation
        if (confirmPassword.isEmpty()) {
            confirmPasswordLayout.error = "Please confirm your password"
            return false
        }
        
        if (password != confirmPassword) {
            confirmPasswordLayout.error = "Passwords do not match"
            return false
        }
        
        return true
    }
    
    private fun navigateToLogin() {
        try {
            (activity as? AuthActivity)?.showLoginScreen()
        } catch (e: Exception) {
            Log.e(TAG, "Error navigating to login: ${e.message}", e)
        }
    }
    
    private fun onRegistrationSuccess() {
        try {
            Log.d(TAG, "Registration successful, navigating to main app")
            (activity as? AuthActivity)?.onAuthenticationSuccess()
        } catch (e: Exception) {
            Log.e(TAG, "Error handling registration success: ${e.message}", e)
        }
    }
    
    private fun showLoading() {
        progressBar.visibility = View.VISIBLE
        registerButton.isEnabled = false
        registerButton.text = "Creating Account..."
        googleSignInButton.isEnabled = false
    }
    
    private fun hideLoading() {
        progressBar.visibility = View.GONE
        registerButton.isEnabled = true
        registerButton.text = "Daftar"
        googleSignInButton.isEnabled = true
    }
    
    private fun clearErrors() {
        ownerNameLayout.error = null
        restaurantNameLayout.error = null
        emailLayout.error = null
        passwordLayout.error = null
        confirmPasswordLayout.error = null
    }
    
    private fun showErrorMessage(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_LONG).show()
        Log.e(TAG, "Error: $message")
    }
    
    private fun showSuccessMessage(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
        Log.d(TAG, "Success: $message")
    }
}
