package com.christopheraldoo.adminwafeoffood.utils

import android.content.Context
import android.util.Log
import android.widget.Toast
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import com.google.firebase.database.DataSnapshot
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Utility class untuk Firebase operations dan error handling
 */
object FirebaseUtils {
    
    private const val TAG = "FirebaseUtils"
    
    /**
     * Check if user is authenticated
     */
    fun isUserAuthenticated(): Boolean {
        val currentUser = FirebaseAuth.getInstance().currentUser
        val isAuth = currentUser != null
        Log.d(TAG, "🔐 User authentication status: $isAuth")
        return isAuth
    }
    
    /**
     * Get current user ID
     */
    fun getCurrentUserId(): String? {
        val userId = FirebaseAuth.getInstance().currentUser?.uid
        Log.d(TAG, "👤 Current user ID: $userId")
        return userId
    }
    
    /**
     * Get current user email
     */
    fun getCurrentUserEmail(): String? {
        val email = FirebaseAuth.getInstance().currentUser?.email
        Log.d(TAG, "📧 Current user email: $email")
        return email
    }
      /**
     * Test database connection dengan retry mechanism
     */
    suspend fun testDatabaseConnection(): DatabaseConnectionResult {
        return suspendCancellableCoroutine { continuation ->
            try {
                Log.d(TAG, "🔍 Testing Firebase Database connection...")
                
                // Use the configured database instance
                val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
                
                // Test with a simple read operation first
                val testRef = database.getReference("test_connection")
                
                // Set a test value and then read it
                val testValue = mapOf(
                    "timestamp" to System.currentTimeMillis(),
                    "test" to true
                )
                
                testRef.setValue(testValue)
                    .addOnSuccessListener {
                        Log.d(TAG, "✅ Test write successful")
                        
                        // Now test reading
                        testRef.addListenerForSingleValueEvent(object : ValueEventListener {
                            override fun onDataChange(snapshot: DataSnapshot) {
                                if (snapshot.exists()) {
                                    Log.d(TAG, "✅ Test read successful - data exists")
                                    
                                    // Clean up test data
                                    testRef.removeValue()
                                    
                                    continuation.resume(
                                        DatabaseConnectionResult.Success("✅ Firebase Database connection successful")
                                    )
                                } else {
                                    Log.w(TAG, "⚠️ Test read - no data found")
                                    continuation.resume(
                                        DatabaseConnectionResult.Error("❌ Database read test failed")
                                    )
                                }
                            }
                            
                            override fun onCancelled(error: DatabaseError) {
                                Log.e(TAG, "❌ Database read test cancelled", error.toException())
                                continuation.resume(
                                    DatabaseConnectionResult.Error("❌ Read test failed: ${error.message}")
                                )
                            }
                        })
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "❌ Database write test failed", error)
                        continuation.resume(
                            DatabaseConnectionResult.Error("❌ Write test failed: ${error.message}")
                        )
                    }
                
            } catch (e: Exception) {
                Log.e(TAG, "💥 Error testing database connection", e)
                continuation.resume(
                    DatabaseConnectionResult.Error("❌ Connection error: ${e.message}")
                )
            }
        }
    }
    
    /**
     * Handle Firebase Database errors with user-friendly messages
     */
    fun handleDatabaseError(context: Context, error: DatabaseError) {
        val message = when (error.code) {
            DatabaseError.PERMISSION_DENIED -> {
                "🔒 Akses ditolak. Pastikan Anda sudah login dan memiliki izin untuk mengakses data ini."
            }
            DatabaseError.NETWORK_ERROR -> {
                "🌐 Masalah koneksi internet. Periksa koneksi Anda dan coba lagi."
            }
            DatabaseError.UNAVAILABLE -> {
                "🔧 Layanan Firebase sedang tidak tersedia. Coba lagi nanti."
            }
            DatabaseError.OPERATION_FAILED -> {
                "❌ Operasi gagal. Coba lagi dalam beberapa saat."
            }
            DatabaseError.USER_CODE_EXCEPTION -> {
                "🐛 Terjadi kesalahan dalam kode aplikasi. Hubungi pengembang."
            }
            DatabaseError.EXPIRED_TOKEN -> {
                "🔑 Sesi login Anda telah berakhir. Silakan login kembali."
            }
            else -> {
                "❌ Terjadi kesalahan: ${error.message}"
            }
        }
        
        Log.e(TAG, "Database Error [${error.code}]: ${error.message}")
        Toast.makeText(context, message, Toast.LENGTH_LONG).show()
    }
    
    /**
     * Show authentication error
     */
    fun showAuthenticationError(context: Context) {
        val message = "🔐 Anda perlu login terlebih dahulu untuk mengakses fitur ini."
        Log.w(TAG, message)
        Toast.makeText(context, message, Toast.LENGTH_LONG).show()
    }
    
    /**
     * Validate image URL
     */
    fun isValidImageUrl(url: String?): Boolean {
        if (url.isNullOrBlank()) return false
        
        return try {
            val validExtensions = listOf(".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp")
            val lowerUrl = url.lowercase()
            
            // Check if URL starts with http/https
            val hasValidProtocol = lowerUrl.startsWith("http://") || lowerUrl.startsWith("https://")
            
            // Check if URL has valid image extension or contains common image hosting patterns
            val hasImageExtension = validExtensions.any { lowerUrl.contains(it) }
            val hasImageHosting = listOf("imgur", "cloudinary", "firebase", "unsplash", "pixabay").any { 
                lowerUrl.contains(it) 
            }
            
            val isValid = hasValidProtocol && (hasImageExtension || hasImageHosting)
            Log.d(TAG, "🖼️ Image URL validation: $url -> $isValid")
            isValid
            
        } catch (e: Exception) {
            Log.e(TAG, "Error validating image URL: $url", e)
            false
        }
    }
    
    /**
     * Get Firebase error suggestion
     */
    fun getErrorSuggestion(error: DatabaseError): String {
        return when (error.code) {
            DatabaseError.PERMISSION_DENIED -> {
                "💡 Saran: Periksa Firebase Rules dan pastikan user sudah terautentikasi dengan benar."
            }
            DatabaseError.NETWORK_ERROR -> {
                "💡 Saran: Periksa koneksi internet dan coba refresh aplikasi."
            }
            DatabaseError.UNAVAILABLE -> {
                "💡 Saran: Tunggu beberapa menit dan coba lagi."
            }
            else -> {
                "💡 Saran: Restart aplikasi atau hubungi tim support."
            }
        }
    }
    
    /**
     * Log Firebase operation
     */
    fun logFirebaseOperation(operation: String, details: String = "") {
        Log.d(TAG, "🔥 Firebase Operation: $operation ${if (details.isNotEmpty()) "- $details" else ""}")
    }
    
    /**
     * Check if Firebase is properly configured
     */
    fun isFirebaseConfigured(): Boolean {
        return try {
            FirebaseAuth.getInstance()
            FirebaseDatabase.getInstance()
            Log.d(TAG, "✅ Firebase is properly configured")
            true
        } catch (e: Exception) {
            Log.e(TAG, "❌ Firebase configuration error", e)
            false
        }
    }
}

/**
 * Sealed class untuk hasil tes koneksi database
 */
sealed class DatabaseConnectionResult {
    data class Success(val message: String) : DatabaseConnectionResult()
    data class Error(val message: String) : DatabaseConnectionResult()
}
