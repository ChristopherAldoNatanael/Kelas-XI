package com.christopheraldoo.adminwafeoffood

import android.content.Intent
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment
import androidx.lifecycle.ViewModelProvider
import com.christopheraldoo.adminwafeoffood.auth.LoginFragment
import com.christopheraldoo.adminwafeoffood.auth.RegisterFragment
import com.christopheraldoo.adminwafeoffood.auth.SplashFragment
import com.christopheraldoo.adminwafeoffood.viewmodel.AuthViewModel

class AuthActivity : AppCompatActivity() {
    
    companion object {
        private const val TAG = "AuthActivity"
    }
    
    private lateinit var authViewModel: AuthViewModel
    private var hasNavigated = false // Flag untuk mencegah double navigation
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_auth)
        
        Log.d(TAG, "AuthActivity started")
        
        // Initialize ViewModel
        initializeViewModel()
        
        // Observe authentication state
        observeAuthState()
        
        // Show splash screen initially
        if (savedInstanceState == null) {
            showSplashScreen()
        }
    }
    
    private fun initializeViewModel() {
        authViewModel = ViewModelProvider(this)[AuthViewModel::class.java]
    }
    
    private fun observeAuthState() {
        authViewModel.authState.observe(this) { state ->
            Log.d(TAG, "Auth state changed to: $state")
            
            when (state) {
                AuthViewModel.AuthState.AUTHENTICATED -> {
                    if (!hasNavigated) {
                        Log.d(TAG, "User authenticated, navigating to MainActivity")
                        onAuthenticationSuccess()
                    }
                }
                
                AuthViewModel.AuthState.UNAUTHENTICATED -> {
                    Log.d(TAG, "User not authenticated")
                    hasNavigated = false // Reset flag
                    if (getCurrentFragment() !is SplashFragment) {
                        showLoginScreen()
                    }
                }
                
                AuthViewModel.AuthState.LOADING -> {
                    Log.d(TAG, "Authentication in progress...")
                    // Loading handled by fragments
                }
                
                AuthViewModel.AuthState.ERROR -> {
                    Log.e(TAG, "Authentication error occurred")
                    hasNavigated = false // Reset flag on error
                }
                
                null -> {
                    Log.d(TAG, "Auth state is null")
                }
            }
        }
    }
    
    private fun showSplashScreen() {
        Log.d(TAG, "Showing splash screen")
        loadFragment(SplashFragment.newInstance())
    }
    
    fun showLoginScreen() {
        Log.d(TAG, "Showing login screen")
        loadFragment(LoginFragment.newInstance())
    }
    
    fun showRegisterScreen() {
        Log.d(TAG, "Showing register screen")
        loadFragment(RegisterFragment.newInstance())
    }
    
    fun onAuthenticationSuccess() {
        if (hasNavigated) {
            Log.d(TAG, "Already navigated, skipping")
            return
        }
        
        hasNavigated = true
        
        try {
            Log.d(TAG, "Authentication successful, starting MainActivity immediately")
            
            // Immediate navigation without delay
            val intent = Intent(this, MainActivity::class.java)
            intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            startActivity(intent)
            
            // Small delay untuk memastikan MainActivity sudah start
            Handler(Looper.getMainLooper()).postDelayed({
                finish()
            }, 100)
            
        } catch (e: Exception) {
            Log.e(TAG, "Error navigating to MainActivity: ${e.message}", e)
            hasNavigated = false // Reset flag on error
        }
    }
    
    private fun loadFragment(fragment: Fragment) {
        try {
            supportFragmentManager.beginTransaction()
                .replace(R.id.auth_fragment_container, fragment)
                .commitNowAllowingStateLoss() // Changed untuk immediate commit
            Log.d(TAG, "Fragment loaded: ${fragment.javaClass.simpleName}")
        } catch (e: Exception) {
            Log.e(TAG, "Error loading fragment: ${e.message}", e)
        }
    }
    
    private fun getCurrentFragment(): Fragment? {
        return supportFragmentManager.findFragmentById(R.id.auth_fragment_container)
    }
    
    override fun onStart() {
        super.onStart()
        Log.d(TAG, "AuthActivity onStart")
    }
    
    override fun onResume() {
        super.onResume()
        Log.d(TAG, "AuthActivity onResume")
    }
    
    override fun onDestroy() {
        super.onDestroy()
        Log.d(TAG, "AuthActivity destroyed")
    }
}
