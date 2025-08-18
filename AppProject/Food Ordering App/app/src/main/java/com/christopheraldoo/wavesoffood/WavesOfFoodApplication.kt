package com.christopheraldoo.wavesoffood

import android.app.Application
import android.util.Log
import com.google.firebase.FirebaseApp

/**
 * Application class untuk initialize Firebase dan services lainnya
 */
class WavesOfFoodApplication : Application() {
    
    override fun onCreate() {
        super.onCreate()
        
        // Initialize Firebase
        initializeFirebase()
        
        Log.d("WavesOfFoodApp", "Application initialized successfully")
    }
    
    /**
     * Initialize Firebase services
     */
    private fun initializeFirebase() {
        try {
            FirebaseApp.initializeApp(this)
            Log.d("WavesOfFoodApp", "Firebase initialized successfully")
        } catch (e: Exception) {
            Log.e("WavesOfFoodApp", "Error initializing Firebase: ${e.message}")
        }
    }
}