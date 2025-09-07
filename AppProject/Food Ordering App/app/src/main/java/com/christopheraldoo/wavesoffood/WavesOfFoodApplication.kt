package com.christopheraldoo.wavesoffood

import android.app.Application
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.database.FirebaseDatabase

/**
 * Application class untuk initialize Firebase dan services lainnya
 */
class WavesOfFoodApplication : Application() {
    
    companion object {
        private const val TAG = "WavesOfFoodApp"
        lateinit var instance: WavesOfFoodApplication
            private set
    }
    
    override fun onCreate() {
        super.onCreate()
        instance = this
        
        // Initialize Firebase
        initializeFirebase()
        
        // Initialize other services
        initializeServices()
        
        Log.d(TAG, "Application initialized successfully")
    }
    
    /**
     * Initialize Firebase services
     */
    private fun initializeFirebase() {
        try {
            FirebaseApp.initializeApp(this)
            
            // Enable offline persistence
            FirebaseDatabase.getInstance().setPersistenceEnabled(true)
            
            Log.d(TAG, "Firebase initialized successfully with offline support")
        } catch (e: Exception) {
            Log.e(TAG, "Error initializing Firebase: ${e.message}", e)
        }
    }
    
    /**
     * Initialize other services
     */
    private fun initializeServices() {
        try {
            // Add any other service initialization here
            Log.d(TAG, "All services initialized successfully")
        } catch (e: Exception) {
            Log.e(TAG, "Error initializing services: ${e.message}", e)
        }
    }
}
