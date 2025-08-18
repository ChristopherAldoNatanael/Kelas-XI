package com.christopheraldoo.adminwafeoffood.viewmodel

import android.app.Application
import android.util.Log
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInAccount
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.GoogleAuthProvider
import com.google.firebase.database.FirebaseDatabase
import android.os.Handler
import android.os.Looper

class AuthViewModel(application: Application) : AndroidViewModel(application) {

    companion object {
        private const val TAG = "AuthViewModel"
    }

    enum class AuthState {
        LOADING,
        AUTHENTICATED,
        UNAUTHENTICATED,
        ERROR
    }

    private val firebaseAuth = FirebaseAuth.getInstance()
    private val database = FirebaseDatabase.getInstance()

    // LiveData untuk state management
    private val _authState = MutableLiveData<AuthState>()
    val authState: LiveData<AuthState> = _authState

    private val _isLoading = MutableLiveData<Boolean>()
    val isLoading: LiveData<Boolean> = _isLoading

    private val _errorMessage = MutableLiveData<String>()
    val errorMessage: LiveData<String> = _errorMessage

    private val _successMessage = MutableLiveData<String>()
    val successMessage: LiveData<String> = _successMessage

    // Google Sign-In client - HAPUS DUPLICATE FUNCTION, PAKAI PROPERTY INI SAJA
    val googleSignInClient: GoogleSignInClient by lazy {
        val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken("83276019460-mrgdaljata2t7pkr1j8e28v7st6p9ap7.apps.googleusercontent.com")
            .requestEmail()
            .build()
        GoogleSignIn.getClient(application, gso)
    }

    init {
        // Check if user is already authenticated
        checkAuthState()
    }

    private fun checkAuthState() {
        val currentUser = firebaseAuth.currentUser
        if (currentUser != null) {
            _authState.value = AuthState.AUTHENTICATED
            Log.d(TAG, "User already authenticated: ${currentUser.email}")
        } else {
            _authState.value = AuthState.UNAUTHENTICATED
            Log.d(TAG, "No authenticated user found")
        }
    }

    fun signUp(email: String, password: String, ownerName: String, restaurantName: String, location: String) {
        Log.d(TAG, "Starting signup process for: $email")
        
        // Clear previous messages
        _errorMessage.value = ""
        _successMessage.value = ""
        
        // Set loading state
        _isLoading.value = true
        _authState.value = AuthState.LOADING

        firebaseAuth.createUserWithEmailAndPassword(email, password)
            .addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val user = firebaseAuth.currentUser
                    Log.d(TAG, "✅ Firebase Auth successful: ${user?.uid}")
                    
                    // TEMPORARY: Skip database save, direct authenticate
                    _isLoading.value = false
                    _authState.value = AuthState.AUTHENTICATED
                    _successMessage.value = "Account created successfully!"
                    
                    // Save user data in background (optional)
                    // saveUserData(user?.uid, email, ownerName, restaurantName, location)
                } else {
                    val errorMsg = task.exception?.message ?: "Registration failed"
                    Log.e(TAG, "❌ Firebase Auth failed: $errorMsg", task.exception)
                    
                    _isLoading.value = false
                    _authState.value = AuthState.ERROR
                    _errorMessage.value = getErrorMessage(task.exception)
                }
            }
            .addOnFailureListener { exception ->
                Log.e(TAG, "Firebase signup failed: ${exception.message}", exception)
                
                // IMPORTANT: Reset loading state on failure
                _isLoading.value = false
                _authState.value = AuthState.ERROR
                _errorMessage.value = getErrorMessage(exception)
            }
    }

    private fun saveUserData(uid: String?, email: String, ownerName: String, restaurantName: String, location: String) {
        if (uid == null) {
            _isLoading.value = false
            _authState.value = AuthState.ERROR
            _errorMessage.value = "User ID is null"
            return
        }

        Log.d(TAG, "Saving user data to database for UID: $uid")
        
        val userData = mapOf(
            "uid" to uid,
            "email" to email,
            "ownerName" to ownerName,
            "restaurantName" to restaurantName,
            "location" to location,
            "role" to "admin",
            "createdAt" to System.currentTimeMillis()
        )

        // Add timeout handler - if database save takes too long, proceed anyway
        val timeoutHandler = Handler(Looper.getMainLooper())
        val timeoutRunnable = Runnable {
            if (_authState.value == AuthState.LOADING) {
                Log.w(TAG, "Database save timeout - proceeding with authentication")
                _isLoading.value = false
                _authState.value = AuthState.AUTHENTICATED
                _successMessage.value = "Account created successfully!"
            }
        }
        
        // Set 10 second timeout
        timeoutHandler.postDelayed(timeoutRunnable, 10000)

        database.getReference("users").child(uid)
            .setValue(userData)
            .addOnSuccessListener {
                Log.d(TAG, "✅ SUCCESS! User data saved successfully")
                timeoutHandler.removeCallbacks(timeoutRunnable) // Cancel timeout
                _isLoading.value = false
                _authState.value = AuthState.AUTHENTICATED
                _successMessage.value = "Account created successfully!"
            }
            .addOnFailureListener { exception ->
                Log.e(TAG, "❌ Database save failed: ${exception.message}")
                timeoutHandler.removeCallbacks(timeoutRunnable) // Cancel timeout
                
                // Even if database save fails, user is still authenticated in Firebase Auth
                _isLoading.value = false
                _authState.value = AuthState.AUTHENTICATED
                _successMessage.value = "Account created successfully!"
            }
    }

    fun signIn(email: String, password: String) {
        Log.d(TAG, "Starting signin process for: $email")
        _isLoading.value = true
        _authState.value = AuthState.LOADING

        firebaseAuth.signInWithEmailAndPassword(email, password)
            .addOnCompleteListener { task ->
                _isLoading.value = false
                
                if (task.isSuccessful) {
                    val user = firebaseAuth.currentUser
                    Log.d(TAG, "Login successful: ${user?.email}")
                    _authState.value = AuthState.AUTHENTICATED
                    _successMessage.value = "Welcome back!"
                } else {
                    val errorMsg = task.exception?.message ?: "Login failed"
                    Log.e(TAG, "Login failed: $errorMsg", task.exception)
                    _authState.value = AuthState.ERROR
                    _errorMessage.value = getErrorMessage(task.exception)
                }
            }
    }

    fun signInWithGoogle(account: GoogleSignInAccount) {
        Log.d(TAG, "Starting Google Sign-In for: ${account.email}")
        _isLoading.value = true
        _authState.value = AuthState.LOADING

        val credential = GoogleAuthProvider.getCredential(account.idToken, null)
        
        firebaseAuth.signInWithCredential(credential)
            .addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val user = firebaseAuth.currentUser
                    Log.d(TAG, "Google Sign-In successful: ${user?.email}")
                    
                    // Save Google user data if it's a new user
                    if (task.result?.additionalUserInfo?.isNewUser == true) {
                        saveGoogleUserData(user?.uid, account)
                    } else {
                        _isLoading.value = false
                        _authState.value = AuthState.AUTHENTICATED
                        _successMessage.value = "Welcome back, ${account.displayName}!"
                    }
                } else {
                    val errorMsg = task.exception?.message ?: "Google Sign-In failed"
                    Log.e(TAG, "Google Sign-In failed: $errorMsg", task.exception)
                    
                    _isLoading.value = false
                    _authState.value = AuthState.ERROR
                    _errorMessage.value = getErrorMessage(task.exception)
                }
            }
    }

    private fun saveGoogleUserData(uid: String?, account: GoogleSignInAccount) {
        if (uid == null) {
            _isLoading.value = false
            _authState.value = AuthState.ERROR
            _errorMessage.value = "User ID is null"
            return
        }

        Log.d(TAG, "Saving Google user data for UID: $uid")
        
        val userData = mapOf(
            "uid" to uid,
            "email" to account.email,
            "ownerName" to (account.displayName ?: "Unknown"),
            "restaurantName" to "${account.displayName}'s Restaurant",
            "location" to "Not specified",
            "role" to "admin",
            "provider" to "google",
            "createdAt" to System.currentTimeMillis()
        )

        database.getReference("users").child(uid)
            .setValue(userData)
            .addOnCompleteListener { dbTask ->
                _isLoading.value = false
                
                if (dbTask.isSuccessful) {
                    Log.d(TAG, "Google user data saved successfully")
                    _authState.value = AuthState.AUTHENTICATED
                    _successMessage.value = "Welcome ${account.displayName}!"
                } else {
                    Log.e(TAG, "Failed to save Google user data: ${dbTask.exception?.message}", dbTask.exception)
                    _authState.value = AuthState.AUTHENTICATED // Still proceed since auth succeeded
                    _successMessage.value = "Welcome ${account.displayName}!"
                }
            }
    }

    fun signOut() {
        Log.d(TAG, "Signing out user")
        firebaseAuth.signOut()
        googleSignInClient.signOut()
        _authState.value = AuthState.UNAUTHENTICATED
        _successMessage.value = "Signed out successfully"
    }

    // HAPUS FUNCTION INI KARENA SUDAH ADA PROPERTY googleSignInClient DI ATAS
    // fun getGoogleSignInClient(): GoogleSignInClient {
    //     return googleSignInClient
    // }

    fun clearError() {
        _errorMessage.value = ""
    }

    fun clearSuccess() {
        _successMessage.value = ""
    }

    private fun getErrorMessage(exception: Exception?): String {
        return when {
            exception?.message?.contains("email address is already in use") == true -> 
                "Email sudah terdaftar. Silakan gunakan email lain atau login."
            exception?.message?.contains("password is invalid") == true -> 
                "Password salah. Silakan coba lagi."
            exception?.message?.contains("no user record") == true -> 
                "Email tidak terdaftar. Silakan daftar terlebih dahulu."
            exception?.message?.contains("network error") == true -> 
                "Koneksi internet bermasalah. Silakan coba lagi."
            exception?.message?.contains("too many requests") == true -> 
                "Terlalu banyak percobaan. Silakan tunggu beberapa saat."
            else -> exception?.message ?: "Terjadi kesalahan tidak dikenal"
        }
    }
}
