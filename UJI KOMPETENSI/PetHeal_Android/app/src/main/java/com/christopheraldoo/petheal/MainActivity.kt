package com.christopheraldoo.petheal

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import androidx.navigation.NavHostController
import androidx.navigation.compose.rememberNavController
import com.christopheraldoo.petheal.ui.navigation.PetHealNavHost
import com.christopheraldoo.petheal.ui.navigation.Screen
import com.christopheraldoo.petheal.ui.theme.PetHealTheme
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    private var navController: NavHostController? = null

    // Launcher for POST_NOTIFICATIONS permission (Android 13+)
    private val notificationPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { /* granted or denied — no-op */ }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            PetHealTheme {
                val nav = rememberNavController().also { navController = it }
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    PetHealNavHost(navController = nav)
                }
            }
        }

        // Request notification permission AFTER setContent so the dialog appears
        // over the actual UI (not a blank screen) — Android 13+ (API 33+) only.
        // Delay slightly so the splash screen is visible first.
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            lifecycleScope.launch {
                delay(1500)
                requestNotificationPermissionIfNeeded()
            }
        }
    }

    override fun onResume() {
        super.onResume()
        // Handle deep-link from FCM notification tap → open Notifications screen
        val navigateTo = intent?.getStringExtra("navigate_to")
        if (navigateTo == "notifications") {
            intent.removeExtra("navigate_to")
            navController?.navigate(Screen.Notifications.route) {
                launchSingleTop = true
            }
        }
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(
                    this, Manifest.permission.POST_NOTIFICATIONS
                ) != PackageManager.PERMISSION_GRANTED
            ) {
                notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }
}