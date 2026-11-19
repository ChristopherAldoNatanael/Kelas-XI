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
import com.christopheraldoo.petheal.ui.components.PetHealTab
import com.christopheraldoo.petheal.ui.components.navigateToTab
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

        // petheal://open/booking/123, petheal://open/record/45, ...
        if (intent?.data != null) {
            handleDeepLink(nav, intent.data!!)
            clearNotificationIntent(intent)
            return
        }

        val navigateTo = intent?.getStringExtra("navigate_to") ?: return

        when (navigateTo) {
            "notification_target" -> {
                val bookingId = intent.intExtraCompat("booking_id")
                val medicalRecordId = intent.intExtraCompat("medical_record_id")
                val petId = intent.intExtraCompat("pet_id")
                val doctorId = intent.intExtraCompat("doctor_id")
                val serviceRequestId = intent.intExtraCompat("service_request_id")

                when {
                    serviceRequestId > 0 -> nav.navigate(
                        Screen.ServiceRequests.createRoute(openId = serviceRequestId)
                    ) { launchSingleTop = true }
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

    /**
     * FCM menaruh seluruh `data` payload sebagai extra **String** saat sistem
     * yang menampilkan notifikasi (aplikasi di background/terminated),
     * sedangkan notifikasi buatan aplikasi sendiri menyimpannya sebagai Int.
     * Keduanya harus terbaca supaya tap notifikasi tetap membuka layar detail.
     */
    private fun Intent.intExtraCompat(key: String): Int {
        val direct = getIntExtra(key, -1)
        if (direct != -1) return direct
        return getStringExtra(key)?.toIntOrNull() ?: -1
    }

    private fun handleDeepLink(nav: NavHostController, uri: android.net.Uri) {
        if (uri.scheme != "petheal") return

        val segments = uri.pathSegments
        val target = segments.firstOrNull()
        val id = segments.getOrNull(1)?.toIntOrNull()

        // Target setingkat TAB wajib lewat mekanisme tab yang sama dengan
        // bottom nav (navigateToTab) — kalau tidak, back stack menumpuk
        // entri tab ganda di atas sub-halaman lama. Target detail (entity
        // deep link) memang mendorong layar detail, dibiarkan.
        when (target) {
            "booking" -> if (id != null) {
                nav.navigate(Screen.BookingDetail.createRoute(id)) { launchSingleTop = true }
            } else {
                nav.navigateToTab(PetHealTab.Bookings)
            }

            "record", "medical-record" -> if (id != null) {
                nav.navigate(Screen.MedicalRecordDetail.createRoute(id)) { launchSingleTop = true }
            } else {
                nav.navigateToTab(PetHealTab.Records)
            }

            "pet" -> if (id != null) {
                nav.navigate(Screen.PetDetail.createRoute(id)) { launchSingleTop = true }
            } else {
                nav.navigateToTab(PetHealTab.Pets)
            }

            "doctor" -> if (id != null) {
                nav.navigate(Screen.DoctorDetail.createRoute(id)) { launchSingleTop = true }
            } else {
                nav.navigate(Screen.Doctors.route) { launchSingleTop = true }
            }

            "service-request", "service-requests" -> if (id != null) {
                nav.navigate(Screen.ServiceRequests.createRoute(openId = id)) { launchSingleTop = true }
            } else {
                nav.navigate(Screen.ServiceRequests.createRoute()) { launchSingleTop = true }
            }

            "notifications" -> nav.navigate(Screen.Notifications.route) { launchSingleTop = true }
            "bookings", "appointments" -> nav.navigateToTab(PetHealTab.Bookings)
            "profile" -> nav.navigateToTab(PetHealTab.Profile)
            else -> nav.navigateToTab(PetHealTab.Home)
        }
    }

    private fun clearNotificationIntent(intent: Intent) {
        intent.data = null
        intent.removeExtra("navigate_to")
        intent.removeExtra("notification_type")
        intent.removeExtra("booking_id")
        intent.removeExtra("medical_record_id")
        intent.removeExtra("pet_id")
        intent.removeExtra("doctor_id")
        intent.removeExtra("service_request_id")
    }
}
