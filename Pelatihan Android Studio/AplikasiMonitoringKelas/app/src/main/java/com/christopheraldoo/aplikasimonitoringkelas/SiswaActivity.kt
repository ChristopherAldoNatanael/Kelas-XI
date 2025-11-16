package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.JadwalScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.KehadiranScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.RiwayatScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import kotlinx.coroutines.launch

/**
 * Role Siswa Activity - Professional Jetpack Compose Implementation
 * 3 Main Screens: Jadwal, Kehadiran (Input), Riwayat
 */
class SiswaActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                SiswaApp()
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SiswaApp() {
    val context = LocalContext.current
    val sessionManager = SessionManager(context)
    val repository = NetworkRepository(context)
    val viewModel = SiswaViewModel(repository)
    val navController = rememberNavController()
    val scope = rememberCoroutineScope()    // Get user data
    val userName = sessionManager.getUserName() ?: "Siswa"
    val userClassId = sessionManager.getUserClassId()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Monitoring Kelas - $userName") },
                actions = {
                    IconButton(onClick = {
                        scope.launch {
                            sessionManager.logout()
                            context.startActivity(Intent(context, LoginActivity::class.java))
                            (context as? ComponentActivity)?.finish()
                        }
                    }) {
                        Icon(Icons.Default.Logout, "Logout")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                    titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer
                )
            )
        },
        bottomBar = {
            NavigationBar {
                val navBackStackEntry by navController.currentBackStackEntryAsState()
                val currentDestination = navBackStackEntry?.destination

                siswaNavigationItems.forEach { screen ->
                    NavigationBarItem(
                        icon = { Icon(screen.icon, contentDescription = screen.label) },
                        label = { Text(screen.label) },
                        selected = currentDestination?.hierarchy?.any { it.route == screen.route } == true,
                        onClick = {
                            navController.navigate(screen.route) {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )
                }
            }
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = "jadwal",
            modifier = Modifier.padding(paddingValues)
        ) {
            composable("jadwal") {
                JadwalScreen(
                    viewModel = viewModel
                )
            }
            composable("kehadiran") {
                KehadiranScreen(viewModel = viewModel)
            }
            composable("riwayat") {
                RiwayatScreen(viewModel = viewModel)
            }
        }
    }
}

// Navigation items
data class SiswaNavigationItem(
    val route: String,
    val label: String,
    val icon: ImageVector
)

val siswaNavigationItems = listOf(
    SiswaNavigationItem("jadwal", "Jadwal", Icons.Default.CalendarToday),
    SiswaNavigationItem("kehadiran", "Kehadiran", Icons.Default.AssignmentTurnedIn),
    SiswaNavigationItem("riwayat", "Riwayat", Icons.Default.History)
)
