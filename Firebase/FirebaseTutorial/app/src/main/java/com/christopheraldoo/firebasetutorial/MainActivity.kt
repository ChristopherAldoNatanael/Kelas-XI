package com.christopheraldoo.firebasetutorial

import android.os.Bundle
import android.util.Log
import android.widget.Button
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import com.google.firebase.analytics.FirebaseAnalytics
import com.google.firebase.crashlytics.FirebaseCrashlytics

class MainActivity : AppCompatActivity() {
    
    private lateinit var firebaseAnalytics: FirebaseAnalytics
    private lateinit var crashlytics: FirebaseCrashlytics
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        
        // Initialize Firebase
        initializeFirebase()
        
        // Setup UI
        setupUI()
        
        Log.d("MainActivity", "App initialized successfully")
    }
    
    private fun initializeFirebase() {
        // Initialize Firebase Analytics
        firebaseAnalytics = FirebaseAnalytics.getInstance(this)
        
        // Initialize Firebase Crashlytics
        crashlytics = FirebaseCrashlytics.getInstance()
        crashlytics.setUserId("test_user")
        crashlytics.setCustomKey("app_version", "1.0")
        
        // Log app start event
        firebaseAnalytics.logEvent("app_started", Bundle())
        crashlytics.log("App started successfully")
        
        Log.d("Firebase", "Firebase initialized")
    }
    
    private fun setupUI() {
        val textView = findViewById<TextView>(R.id.textView)
        val buttonTestCrash = findViewById<Button>(R.id.buttonTestCrash)
        
        buttonTestCrash.setOnClickListener {
            // Log event sebelum crash
            crashlytics.log("User clicked Test Crash button")
            firebaseAnalytics.logEvent("test_crash_clicked", Bundle())
            
            // Update UI untuk feedback
            textView.text = "Triggering crash..."
            
            Log.d("Crashlytics", "About to trigger test crash")
            
            // Trigger crash untuk testing Crashlytics
            triggerTestCrash()
        }
    }
    
    private fun triggerTestCrash() {
        // Menambahkan informasi tambahan untuk crash report
        crashlytics.setCustomKey("crash_triggered_by", "test_button")
        crashlytics.setCustomKey("crash_timestamp", System.currentTimeMillis())
        
        // Crash app dengan pesan yang jelas
        throw RuntimeException("Test crash triggered by user for Crashlytics testing")
    }
}