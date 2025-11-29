package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import com.christopheraldoo.aplikasimonitoringkelas.ui.AppTheme
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum.KurikulumClassManagementScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum.KurikulumDashboardScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum.KurikulumHistoryScreen
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import kotlinx.coroutines.launch

/**
 * KurikulumActivity - Main Activity for Kurikulum Role
 * 
 * Features:
 * - Dashboard: Real-time schedule monitoring with teacher attendance status
 * - Class Management: Sort classes by status, assign substitute teachers
 * - History: Attendance history with statistics and export functionality
 */
class KurikulumActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            var isDarkMode by remember { mutableStateOf(false) }
            AppTheme(darkTheme = isDarkMode) {
                KurikulumMainApp(
                    isDarkMode = isDarkMode,
                    onDarkModeToggle = { isDarkMode = !isDarkMode }
                )
            }
        }
    }
}

// Navigation items for Kurikulum
sealed class KurikulumNavItem(
    val route: String,
    val label: String,
    val icon: ImageVector
) {
    object Dashboard : KurikulumNavItem("dashboard", "Dashboard", Icons.Default.Dashboard)
    object ClassManagement : KurikulumNavItem("class_management", "Kelas", Icons.Default.Class)
    object History : KurikulumNavItem("history", "Riwayat", Icons.Default.History)
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumMainApp(
    isDarkMode: Boolean,
    onDarkModeToggle: () -> Unit
) {
    val context = LocalContext.current
    val sessionManager = remember { SessionManager(context) }
    val repository = remember { NetworkRepository(context) }
    val viewModel = remember { KurikulumViewModel(repository) }
    val navController = rememberNavController()
    val scope = rememberCoroutineScope()
    
    // Get user info
    val userName = sessionManager.getUserName() ?: "Kurikulum"
    
    // Navigation items
    val navigationItems = listOf(
        KurikulumNavItem.Dashboard,
        KurikulumNavItem.ClassManagement,
        KurikulumNavItem.History
    )
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { 
                    Text("Kurikulum - $userName")
                },
                actions = {
                    // Dark mode toggle
                    IconButton(onClick = onDarkModeToggle) {
                        Icon(
                            imageVector = if (isDarkMode) Icons.Default.LightMode else Icons.Default.DarkMode,
                            contentDescription = "Toggle dark mode"
                        )
                    }
                    
                    // Refresh button
                    IconButton(onClick = {
                        when (navController.currentDestination?.route) {
                            KurikulumNavItem.Dashboard.route -> viewModel.loadDashboard()
                            KurikulumNavItem.ClassManagement.route -> viewModel.loadClassManagement()
                            KurikulumNavItem.History.route -> viewModel.loadHistory(refresh = true)
                        }
                    }) {
                        Icon(Icons.Default.Refresh, "Refresh")
                    }
                    
                    // Logout
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
            NavigationBar(
                containerColor = MaterialTheme.colorScheme.surface,
                tonalElevation = 8.dp
            ) {
                val navBackStackEntry by navController.currentBackStackEntryAsState()
                val currentDestination = navBackStackEntry?.destination
                
                navigationItems.forEach { screen ->
                    NavigationBarItem(
                        icon = { 
                            Icon(
                                imageVector = screen.icon, 
                                contentDescription = screen.label
                            ) 
                        },
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
                        },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = MaterialTheme.colorScheme.primary,
                            selectedTextColor = MaterialTheme.colorScheme.primary,
                            indicatorColor = MaterialTheme.colorScheme.primaryContainer
                        )
                    )
                }
            }
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = KurikulumNavItem.Dashboard.route,
            modifier = Modifier.padding(paddingValues)
        ) {
            composable(KurikulumNavItem.Dashboard.route) {
                KurikulumDashboardScreen(
                    viewModel = viewModel,
                    onClassClick = { classItem ->
                        // Navigate to class management with pre-selected class
                        navController.navigate(KurikulumNavItem.ClassManagement.route)
                    }
                )
            }
            
            composable(KurikulumNavItem.ClassManagement.route) {
                KurikulumClassManagementScreen(
                    viewModel = viewModel
                )
            }
            
            composable(KurikulumNavItem.History.route) {
                KurikulumHistoryScreen(
                    viewModel = viewModel
                )
            }
        }
    }
}


