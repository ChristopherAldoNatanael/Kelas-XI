package com.christopheraldoo.firebasetutorial

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.util.Log
import android.widget.Button
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.google.android.gms.tasks.OnCompleteListener
import com.google.firebase.messaging.FirebaseMessaging

class MainActivity : AppCompatActivity() {
    
    // Permission launcher untuk notifikasi
    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted: Boolean ->
        if (isGranted) {
            Log.d("FCM", "Notification permission granted")
            createToken()
        } else {
            Log.d("FCM", "Notification permission denied")
            Toast.makeText(this, "Notification permission diperlukan!", Toast.LENGTH_SHORT).show()
        }
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Set layout dari XML
        setContentView(R.layout.activity_main)
        
        // Ambil button dari XML
        val crashButton = findViewById<Button>(R.id.buttonTestCrash)
        
        // Set click listener untuk crash
        crashButton.setOnClickListener {
            // Langsung crash app
            throw RuntimeException("Test Crash for Crashlytics")
        }

        // Request notification permission & get FCM token
        askNotificationPermission()
    }

    private fun askNotificationPermission() {
        // Android 13+ butuh permission untuk notifikasi
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) == 
                PackageManager.PERMISSION_GRANTED) {
                // Permission sudah ada
                createToken()
            } else {
                // Minta permission
                requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        } else {
            // Android < 13 tidak butuh permission
            createToken()
        }
    }

    private fun createToken() {
        FirebaseMessaging.getInstance().token.addOnCompleteListener(OnCompleteListener { task ->
            if (!task.isSuccessful) {
                Log.w("FCM_TOKEN", "Fetching FCM token failed", task.exception)
                return@OnCompleteListener
            }

            // FCM token untuk testing
            val token = task.result
            Log.d("FCM_TOKEN", "FCM Token: $token")
            
            // Tampilkan token di Toast juga
            Toast.makeText(this, "Token ready! Check Logcat", Toast.LENGTH_LONG).show()
        })
    }
}