package com.christopheraldoo.wavesoffood.auth

import android.content.Context
import android.util.Log
import com.christopheraldoo.wavesoffood.R
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.android.gms.common.api.ApiException
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.FirebaseUser
import com.google.firebase.auth.GoogleAuthProvider
import com.google.firebase.auth.ktx.auth
import com.google.firebase.ktx.Firebase
import kotlinx.coroutines.tasks.await

/**
 * Firebase Authentication Manager
 * Mengelola semua operasi autentikasi untuk aplikasi user
 */
class FirebaseAuthManager private constructor() {
      companion object {
        private const val TAG = "FirebaseAuthManager"
        
        @Volatile
        private var INSTANCE: FirebaseAuthManager? = null
        
        fun getInstance(): FirebaseAuthManager {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: FirebaseAuthManager().also { INSTANCE = it }
            }
        }
    }
    
    // Firebase Auth instance
    private val auth: FirebaseAuth = Firebase.auth
    
    // Google Sign-In Client
    private var googleSignInClient: GoogleSignInClient? = null
      /**
     * Initialize Google Sign-In
     */
    fun initializeGoogleSignIn(context: Context) {
        try {
            val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
                .requestIdToken(context.getString(R.string.default_web_client_id))
                .requestEmail()
                .build()
            
            googleSignInClient = GoogleSignIn.getClient(context, gso)
            Log.d(TAG, "Google Sign-In initialized successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error initializing Google Sign-In: ${e.message}")
        }
    }
    
    /**
     * Get current authenticated user
     */
    fun getCurrentUser(): FirebaseUser? = auth.currentUser
    
    /**
     * Check if user is logged in
     */
    fun isUserLoggedIn(): Boolean = getCurrentUser() != null
    
    /**
     * Login dengan Email dan Password
     */
    suspend fun loginWithEmail(email: String, password: String): AuthResult {
        return try {
            Log.d(TAG, "Attempting email login for: $email")
            
            val result = auth.signInWithEmailAndPassword(email, password).await()
            val user = result.user
            
            if (user != null) {
                Log.d(TAG, "Email login successful for: ${user.email}")
                AuthResult.Success(user)
            } else {
                Log.e(TAG, "Login failed: User is null")
                AuthResult.Error("Login failed. Please try again.")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Email login error: ${e.message}")
            AuthResult.Error(e.message ?: "Login failed. Please check your credentials.")
        }
    }
    
    /**
     * Register dengan Email dan Password
     */
    suspend fun registerWithEmail(email: String, password: String, fullName: String): AuthResult {
        return try {
            Log.d(TAG, "Attempting email registration for: $email")
            
            val result = auth.createUserWithEmailAndPassword(email, password).await()
            val user = result.user
            
            if (user != null) {
                // Update profile dengan nama lengkap
                val profileUpdates = com.google.firebase.auth.UserProfileChangeRequest.Builder()
                    .setDisplayName(fullName)
                    .build()
                
                user.updateProfile(profileUpdates).await()
                Log.d(TAG, "Email registration successful for: ${user.email}")
                AuthResult.Success(user)
            } else {
                Log.e(TAG, "Registration failed: User is null")
                AuthResult.Error("Registration failed. Please try again.")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Email registration error: ${e.message}")
            AuthResult.Error(e.message ?: "Registration failed. Please try again.")
        }
    }
    
    /**
     * Get Google Sign-In Intent
     */
    fun getGoogleSignInIntent() = googleSignInClient?.signInIntent
    
    /**
     * Handle Google Sign-In Result
     */
    suspend fun handleGoogleSignInResult(data: android.content.Intent?): AuthResult {
        return try {
            Log.d(TAG, "Handling Google Sign-In result")
            
            val task = GoogleSignIn.getSignedInAccountFromIntent(data)
            val account = task.getResult(ApiException::class.java)
            
            if (account?.idToken != null) {
                val credential = GoogleAuthProvider.getCredential(account.idToken, null)
                val result = auth.signInWithCredential(credential).await()
                val user = result.user
                
                if (user != null) {
                    Log.d(TAG, "Google Sign-In successful for: ${user.email}")
                    AuthResult.Success(user)
                } else {
                    Log.e(TAG, "Google Sign-In failed: User is null")
                    AuthResult.Error("Google Sign-In failed. Please try again.")
                }
            } else {
                Log.e(TAG, "Google Sign-In failed: No ID token")
                AuthResult.Error("Google Sign-In failed. Please try again.")
            }
        } catch (e: ApiException) {
            Log.e(TAG, "Google Sign-In error: ${e.message}")
            AuthResult.Error("Google Sign-In failed: ${e.message}")
        } catch (e: Exception) {
            Log.e(TAG, "Google Sign-In unexpected error: ${e.message}")
            AuthResult.Error("Google Sign-In failed. Please try again.")
        }
    }
    
    /**
     * Logout user
     */
    suspend fun logout(): Boolean {
        return try {
            auth.signOut()
            googleSignInClient?.signOut()?.await()
            Log.d(TAG, "User logged out successfully")
            true
        } catch (e: Exception) {
            Log.e(TAG, "Logout error: ${e.message}")
            false
        }
    }
    
    /**
     * Reset Password
     */
    suspend fun resetPassword(email: String): AuthResult {
        return try {
            auth.sendPasswordResetEmail(email).await()
            Log.d(TAG, "Password reset email sent to: $email")
            AuthResult.Success(null, "Password reset email sent successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Password reset error: ${e.message}")
            AuthResult.Error(e.message ?: "Failed to send password reset email")
        }
    }
}

/**
 * Sealed class untuk hasil autentikasi
 */
sealed class AuthResult {
    data class Success(val user: FirebaseUser?, val message: String = "") : AuthResult()
    data class Error(val message: String) : AuthResult()
}