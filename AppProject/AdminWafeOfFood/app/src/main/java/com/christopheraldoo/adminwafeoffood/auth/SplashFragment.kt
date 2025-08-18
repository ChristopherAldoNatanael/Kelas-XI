package com.christopheraldoo.adminwafeoffood.auth

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.lifecycle.ViewModelProvider
import com.christopheraldoo.adminwafeoffood.AuthActivity
import com.christopheraldoo.adminwafeoffood.R
import com.christopheraldoo.adminwafeoffood.viewmodel.AuthViewModel

class SplashFragment : Fragment() {
    
    companion object {
        private const val TAG = "SplashFragment"
        private const val SPLASH_DELAY = 1000L // Reduced from 2000L to 1000L (1 second)
        
        fun newInstance(): SplashFragment {
            return SplashFragment()
        }
    }
    
    private lateinit var authViewModel: AuthViewModel
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View? {
        return inflater.inflate(R.layout.fragment_splash, container, false)
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        Log.d(TAG, "SplashFragment created")
        
        // Initialize ViewModel
        authViewModel = ViewModelProvider(requireActivity())[AuthViewModel::class.java]
        
        // Observe authentication state
        observeAuthState()
        
        // Start splash timer
        startSplashTimer()
    }
    
    private fun observeAuthState() {
        authViewModel.authState.observe(viewLifecycleOwner) { state ->
            Log.d(TAG, "Auth state in splash: $state")
            
            when (state) {
                AuthViewModel.AuthState.AUTHENTICATED -> {
                    Log.d(TAG, "User already authenticated, navigating immediately")
                    // Immediate navigation for already authenticated users
                    (activity as? AuthActivity)?.onAuthenticationSuccess()
                }
                AuthViewModel.AuthState.UNAUTHENTICATED -> {
                    Log.d(TAG, "User not authenticated, will show login after splash")
                    // Will be handled by splash timer
                }
                else -> {
                    // Other states handled by main activity
                }
            }
        }
    }
    
    private fun startSplashTimer() {
        Handler(Looper.getMainLooper()).postDelayed({
            if (isAdded && activity != null) {
                // Only navigate to login if user is not authenticated
                if (authViewModel.authState.value == AuthViewModel.AuthState.UNAUTHENTICATED) {
                    Log.d(TAG, "Splash timer finished, showing login")
                    (activity as? AuthActivity)?.showLoginScreen()
                }
            }
        }, SPLASH_DELAY)
    }
}
