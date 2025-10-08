package com.christopheraldoo.adminwafeoffood.utils

import android.app.Application
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.database.FirebaseDatabase

/**
 * Firebase Configuration class untuk setup yang tepat
 */
class FirebaseConfig : Application() {
    
    companion object {
        private const val TAG = "FirebaseConfig"
        private const val DATABASE_URL = "https://waves-of-food-9af5f-default-rtdb.asia-southeast1.firebasedatabase.app/"
    }
    
    override fun onCreate() {
        super.onCreate()
        initializeFirebase()
    }
    
    private fun initializeFirebase() {
        try {
            Log.d(TAG, "🔥 Initializing Firebase...")
            
            // Initialize Firebase App
            if (FirebaseApp.getApps(this).isEmpty()) {
                FirebaseApp.initializeApp(this)
                Log.d(TAG, "✅ Firebase App initialized")
            }
            
            // Configure Firebase Database
            val database = FirebaseDatabase.getInstance(DATABASE_URL)
            
            // Enable offline persistence
            try {
                database.setPersistenceEnabled(true)
                Log.d(TAG, "✅ Firebase offline persistence enabled")
            } catch (e: Exception) {
                Log.w(TAG, "⚠️ Offline persistence already enabled or failed: ${e.message}")
            }            // Enable logging for debugging
            try {
                database.setLogLevel(com.google.firebase.database.Logger.Level.DEBUG)
                Log.d(TAG, "✅ Firebase debug logging enabled")
            } catch (e: Exception) {
                Log.w(TAG, "⚠️ Could not enable debug logging: ${e.message}")
            }
            
            // Test connection
            testDatabaseConnection(database)
            
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error initializing Firebase", e)
        }
    }
    
    private fun testDatabaseConnection(database: FirebaseDatabase) {
        try {
            val connectedRef = database.getReference(".info/connected")
            connectedRef.addValueEventListener(object : com.google.firebase.database.ValueEventListener {
                override fun onDataChange(snapshot: com.google.firebase.database.DataSnapshot) {
                    val connected = snapshot.getValue(Boolean::class.java) ?: false
                    if (connected) {
                        Log.d(TAG, "🌐✅ Firebase Database connected successfully")
                    } else {
                        Log.w(TAG, "🌐❌ Firebase Database disconnected")
                    }
                }
                
                override fun onCancelled(error: com.google.firebase.database.DatabaseError) {
                    Log.e(TAG, "🌐💥 Firebase connection test failed", error.toException())
                }
            })
        } catch (e: Exception) {
            Log.e(TAG, "💥 Error testing database connection", e)
        }
    }
}
