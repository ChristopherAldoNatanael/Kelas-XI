package com.christopheraldoo.adminwafeoffood.utils

import android.content.Context
import android.util.Log
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.database.DataSnapshot
import com.google.firebase.database.DatabaseError
import com.google.firebase.database.FirebaseDatabase
import com.google.firebase.database.ValueEventListener
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * Firebase Connection Tester untuk debugging masalah koneksi
 */
object FirebaseConnectionTester {
    
    private const val TAG = "FirebaseConnectionTester"
    
    /**
     * Test lengkap koneksi Firebase
     */
    suspend fun runCompleteConnectionTest(): FirebaseTestResult {
        return try {
            Log.d(TAG, "🔥 Starting complete Firebase connection test...")
            
            // Test 1: Authentication
            val authResult = testAuthentication()
            if (!authResult.success) {
                return FirebaseTestResult(
                    success = false,
                    message = "Authentication failed: ${authResult.message}",
                    details = listOf(authResult.message)
                )
            }
            
            // Test 2: Database connection
            val dbResult = testDatabaseConnection()
            if (!dbResult.success) {
                return FirebaseTestResult(
                    success = false,
                    message = "Database connection failed: ${dbResult.message}",
                    details = listOf(authResult.message, dbResult.message)
                )
            }
            
            // Test 3: Write/Read test
            val writeReadResult = testWriteRead()
            if (!writeReadResult.success) {
                return FirebaseTestResult(
                    success = false,
                    message = "Write/Read test failed: ${writeReadResult.message}",
                    details = listOf(authResult.message, dbResult.message, writeReadResult.message)
                )
            }
            
            // Test 4: Permissions test
            val permissionResult = testPermissions()
            
            FirebaseTestResult(
                success = true,
                message = "🎉 All Firebase tests passed successfully!",
                details = listOf(
                    authResult.message,
                    dbResult.message,
                    writeReadResult.message,
                    permissionResult.message
                )
            )
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error during connection test", e)
            FirebaseTestResult(
                success = false,
                message = "Test failed with exception: ${e.message}",
                details = listOf("Exception: ${e.message}")
            )
        }
    }
    
    private fun testAuthentication(): TestStepResult {
        return try {
            val auth = FirebaseAuth.getInstance()
            val currentUser = auth.currentUser
            
            if (currentUser != null) {
                Log.d(TAG, "✅ Authentication: User logged in - ${currentUser.email}")
                TestStepResult(true, "✅ Authentication successful - User: ${currentUser.email}")
            } else {
                Log.w(TAG, "⚠️ Authentication: No user logged in")
                TestStepResult(false, "❌ No user authenticated")
            }
        } catch (e: Exception) {
            Log.e(TAG, "❌ Authentication test failed", e)
            TestStepResult(false, "❌ Authentication error: ${e.message}")
        }
    }
    
    private suspend fun testDatabaseConnection(): TestStepResult {
        return suspendCancellableCoroutine { continuation ->
            try {
                Log.d(TAG, "🔍 Testing database connection...")
                
                val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
                val connectedRef = database.getReference(".info/connected")
                
                connectedRef.addListenerForSingleValueEvent(object : ValueEventListener {
                    override fun onDataChange(snapshot: DataSnapshot) {
                        val connected = snapshot.getValue(Boolean::class.java) ?: false
                        if (connected) {
                            Log.d(TAG, "✅ Database connection: Connected")
                            continuation.resume(TestStepResult(true, "✅ Database connection successful"))
                        } else {
                            Log.w(TAG, "❌ Database connection: Not connected")
                            continuation.resume(TestStepResult(false, "❌ Database not connected"))
                        }
                    }
                    
                    override fun onCancelled(error: DatabaseError) {
                        Log.e(TAG, "❌ Database connection test cancelled", error.toException())
                        continuation.resume(TestStepResult(false, "❌ Connection test cancelled: ${error.message}"))
                    }
                })
                
            } catch (e: Exception) {
                Log.e(TAG, "💥 Database connection test error", e)
                continuation.resume(TestStepResult(false, "❌ Connection test error: ${e.message}"))
            }
        }
    }
    
    private suspend fun testWriteRead(): TestStepResult {
        return suspendCancellableCoroutine { continuation ->
            try {
                Log.d(TAG, "📝 Testing write/read operations...")
                
                val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
                val testRef = database.getReference("connection_test")
                
                val testData = mapOf(
                    "timestamp" to System.currentTimeMillis(),
                    "message" to "Firebase connection test",
                    "success" to true
                )
                
                testRef.setValue(testData)
                    .addOnSuccessListener {
                        Log.d(TAG, "✅ Write test successful")
                        
                        // Now test read
                        testRef.addListenerForSingleValueEvent(object : ValueEventListener {
                            override fun onDataChange(snapshot: DataSnapshot) {
                                if (snapshot.exists()) {
                                    Log.d(TAG, "✅ Read test successful")
                                    
                                    // Clean up
                                    testRef.removeValue()
                                    
                                    continuation.resume(TestStepResult(true, "✅ Write/Read operations successful"))
                                } else {
                                    Log.w(TAG, "❌ Read test: No data found")
                                    continuation.resume(TestStepResult(false, "❌ Read test failed - no data"))
                                }
                            }
                            
                            override fun onCancelled(error: DatabaseError) {
                                Log.e(TAG, "❌ Read test cancelled", error.toException())
                                continuation.resume(TestStepResult(false, "❌ Read test cancelled: ${error.message}"))
                            }
                        })
                    }
                    .addOnFailureListener { error ->
                        Log.e(TAG, "❌ Write test failed", error)
                        continuation.resume(TestStepResult(false, "❌ Write test failed: ${error.message}"))
                    }
                
            } catch (e: Exception) {
                Log.e(TAG, "💥 Write/Read test error", e)
                continuation.resume(TestStepResult(false, "❌ Write/Read test error: ${e.message}"))
            }
        }
    }
    
    private suspend fun testPermissions(): TestStepResult {
        return suspendCancellableCoroutine { continuation ->
            try {
                Log.d(TAG, "🔐 Testing permissions...")
                
                val database = FirebaseDatabase.getInstance("https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/")
                val userOrdersRef = database.getReference("user_orders")
                
                userOrdersRef.addListenerForSingleValueEvent(object : ValueEventListener {
                    override fun onDataChange(snapshot: DataSnapshot) {
                        Log.d(TAG, "✅ Permissions test: Can access user_orders (${snapshot.childrenCount} users)")
                        continuation.resume(TestStepResult(true, "✅ Permissions OK - Can access user_orders"))
                    }
                    
                    override fun onCancelled(error: DatabaseError) {
                        when (error.code) {
                            DatabaseError.PERMISSION_DENIED -> {
                                Log.e(TAG, "❌ Permissions test: Access denied")
                                continuation.resume(TestStepResult(false, "❌ Permission denied - Check Firebase rules"))
                            }
                            else -> {
                                Log.e(TAG, "❌ Permissions test error", error.toException())
                                continuation.resume(TestStepResult(false, "❌ Permissions test error: ${error.message}"))
                            }
                        }
                    }
                })
                
            } catch (e: Exception) {
                Log.e(TAG, "💥 Permissions test error", e)
                continuation.resume(TestStepResult(false, "❌ Permissions test error: ${e.message}"))
            }
        }
    }
}

data class FirebaseTestResult(
    val success: Boolean,
    val message: String,
    val details: List<String>
)

data class TestStepResult(
    val success: Boolean,
    val message: String
)
