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

class LoginFragment : Fragment() {
    
    companion object {
        private const val TAG = "LoginFragment"
        
        fun newInstance(): LoginFragment {
            return LoginFragment()
        }
    }
    
    private lateinit var authViewModel: AuthViewModel
    
    // UI Components
    private lateinit var emailInputLayout: TextInputLayout
    private lateinit var passwordInputLayout: TextInputLayout
    private lateinit var emailInput: TextInputEditText
    private lateinit var passwordInput: TextInputEditText
    private lateinit var loginButton: Button
    private lateinit var googleSignInButton: Button
    private lateinit var registerLink: TextView
    private lateinit var forgotPasswordButton: TextView
    private lateinit var progressBar: ProgressBar
    
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
        return inflater.inflate(R.layout.fragment_login, container, false)
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        initializeViews(view)
        initializeViewModel()
        setupClickListeners()
        observeViewModel()
        
        Log.d(TAG, "LoginFragment initialized")
    }
    
    private fun initializeViews(view: View) {
        emailInputLayout = view.findViewById(R.id.til_email)
        passwordInputLayout = view.findViewById(R.id.til_password)
        emailInput = view.findViewById(R.id.et_email)
        passwordInput = view.findViewById(R.id.et_password)
        loginButton = view.findViewById(R.id.btn_login)
        googleSignInButton = view.findViewById(R.id.btn_google_sign_in)
        registerLink = view.findViewById(R.id.tv_register_link)
        forgotPasswordButton = view.findViewById(R.id.tv_forgot_password)
        progressBar = view.findViewById(R.id.progress_bar)
    }
    
    private fun initializeViewModel() {
        authViewModel = ViewModelProvider(requireActivity())[AuthViewModel::class.java]
    }
    
    private fun setupClickListeners() {
        loginButton.setOnClickListener {
            performLogin()
        }
        
        googleSignInButton.setOnClickListener {
            performGoogleSignIn()
        }
        
        registerLink.setOnClickListener {
            navigateToRegister()
        }
        
        forgotPasswordButton.setOnClickListener {
            // TODO: Implement forgot password functionality
            Toast.makeText(context, "Fitur lupa password akan segera tersedia", Toast.LENGTH_SHORT).show()
        }
    }
    
    private fun observeViewModel() {
        authViewModel.authState.observe(viewLifecycleOwner) { state ->
            when (state) {
                AuthViewModel.AuthState.LOADING -> {
                    showLoading(true)
                }
                AuthViewModel.AuthState.AUTHENTICATED -> {
                    showLoading(false)
                    (activity as? AuthActivity)?.onAuthenticationSuccess()
                }
                AuthViewModel.AuthState.UNAUTHENTICATED -> {
                    showLoading(false)
                }
                AuthViewModel.AuthState.ERROR -> {
                    showLoading(false)
                    // Error message will be handled by errorMessage observer
                }
                null -> {
                    showLoading(false)
                }
            }
        }
        
        authViewModel.errorMessage.observe(viewLifecycleOwner) { errorMessage ->
            if (errorMessage.isNotEmpty()) {
                showErrorMessage(errorMessage)
                authViewModel.clearError()
            }
        }
        
        authViewModel.successMessage.observe(viewLifecycleOwner) { successMessage ->
            if (successMessage.isNotEmpty()) {
                Toast.makeText(context, successMessage, Toast.LENGTH_SHORT).show()
                authViewModel.clearSuccess()
            }
        }
    }
    
    private fun performLogin() {
        val email = emailInput.text.toString().trim()
        val password = passwordInput.text.toString().trim()
        
        Log.d(TAG, "Attempting login for email: $email")
        
        // Clear previous errors
        clearErrors()
        
        // Validate inputs
        if (!validateInputs(email, password)) {
            return
        }
        
        // Perform login via ViewModel
        authViewModel.signIn(email, password)
    }
    
    private fun performGoogleSignIn() {
        try {
            Log.d(TAG, "Starting Google Sign In from Login")
            
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
    
    private fun validateInputs(email: String, password: String): Boolean {
        var isValid = true
        
        if (email.isEmpty()) {
            emailInputLayout.error = "Email tidak boleh kosong"
            isValid = false
        } else if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            emailInputLayout.error = "Format email tidak valid"
            isValid = false
        }
        
        if (password.isEmpty()) {
            passwordInputLayout.error = "Password tidak boleh kosong"
            isValid = false
        }
        
        return isValid
    }
    
    private fun clearErrors() {
        emailInputLayout.error = null
        passwordInputLayout.error = null
    }
    
    private fun showLoading(show: Boolean) {
        try {
            if (show) {
                progressBar.visibility = View.VISIBLE
                loginButton.isEnabled = false
                googleSignInButton.isEnabled = false
                loginButton.text = "Logging in..."
            } else {
                progressBar.visibility = View.GONE
                loginButton.isEnabled = true
                googleSignInButton.isEnabled = true
                loginButton.text = "Login"
            }
        } catch (e: Exception) {
            Log.e(TAG, "Error in showLoading: ${e.message}")
        }
    }
    
    private fun showErrorMessage(message: String) {
        Toast.makeText(context, message, Toast.LENGTH_LONG).show()
        Log.e(TAG, "Error: $message")
    }
    
    private fun navigateToRegister() {
        try {
            (activity as? AuthActivity)?.showRegisterScreen()
        } catch (e: Exception) {
            Log.e(TAG, "Error navigating to register: ${e.message}", e)
        }
    }
}
