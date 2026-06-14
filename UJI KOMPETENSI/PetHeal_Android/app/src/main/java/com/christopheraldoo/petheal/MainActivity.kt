package com.christopheraldoo.petheal

import android.Manifest
import android.content.Intent
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

    private val notificationPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestPermission()) { }

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

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            lifecycleScope.launch {
                delay(1500)
                requestNotificationPermissionIfNeeded()
            }
        }

        handleNavigationIntent(intent)
    }

    override fun onResume() {
        super.onResume()
        handleNavigationIntent(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleNavigationIntent(intent)
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    private fun handleNavigationIntent(intent: Intent?) {
        val nav = navController ?: return
        val navigateTo = intent?.getStringExtra("navigate_to") ?: return

        when (navigateTo) {
            "notification_target" -> {
                val bookingId = intent.getIntExtra("booking_id", -1)
                val medicalRecordId = intent.getIntExtra("medical_record_id", -1)
                val petId = intent.getIntExtra("pet_id", -1)
                val doctorId = intent.getIntExtra("doctor_id", -1)

                when {
                    bookingId > 0 -> nav.navigate(Screen.BookingDetail.createRoute(bookingId)) { launchSingleTop = true }
                    medicalRecordId > 0 -> nav.navigate(Screen.MedicalRecordDetail.createRoute(medicalRecordId)) { launchSingleTop = true }
                    petId > 0 -> nav.navigate(Screen.PetDetail.createRoute(petId)) { launchSingleTop = true }
                    doctorId > 0 -> nav.navigate(Screen.DoctorDetail.createRoute(doctorId)) { launchSingleTop = true }
                    else -> nav.navigate(Screen.Notifications.route) { launchSingleTop = true }
                }

                clearNotificationIntent(intent)
            }

            "notifications" -> {
                nav.navigate(Screen.Notifications.route) { launchSingleTop = true }
                clearNotificationIntent(intent)
            }
        }
    }

    private fun clearNotificationIntent(intent: Intent) {
        intent.removeExtra("navigate_to")
        intent.removeExtra("notification_type")
        intent.removeExtra("booking_id")
        intent.removeExtra("medical_record_id")
        intent.removeExtra("pet_id")
        intent.removeExtra("doctor_id")
    }
}
