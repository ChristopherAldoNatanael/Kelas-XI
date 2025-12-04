package com.christopheraldoo.bukuringkasapp

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.christopheraldoo.bukuringkasapp.ui.screens.AskAiScreen
import com.christopheraldoo.bukuringkasapp.ui.screens.HistoryScreen
import com.christopheraldoo.bukuringkasapp.ui.screens.HomeScreen
import com.christopheraldoo.bukuringkasapp.ui.screens.ScanScreen
import com.christopheraldoo.bukuringkasapp.ui.screens.SettingsScreen
import com.christopheraldoo.bukuringkasapp.ui.theme.BukuRingkasAppTheme
import com.christopheraldoo.bukuringkasapp.ui.theme.PrimaryBlue

/**
 * Root composable untuk BukuRingkasApp
 * Mengatur navigation dan struktur aplikasi utama
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BukuRingkasApp() {
    BukuRingkasAppTheme {
        val navController = rememberNavController()

        Scaffold(
            topBar = {
                TopAppBar(
                    title = {
                        Text(
                            text = "BukuRingkasApp",
                            style = androidx.compose.material3.MaterialTheme.typography.titleLarge
                        )
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = androidx.compose.material3.MaterialTheme.colorScheme.primary,
                        titleContentColor = androidx.compose.material3.MaterialTheme.colorScheme.onPrimary
                    )
                )
            },
            bottomBar = {
                NavigationBar(
                    containerColor = androidx.compose.material3.MaterialTheme.colorScheme.surface
                ) {
                    val navBackStackEntry by navController.currentBackStackEntryAsState()
                    val currentDestination = navBackStackEntry?.destination

                    // Home navigation item
                    NavigationBarItem(
                        icon = {
                            Icon(
                                painter = painterResource(id = android.R.drawable.ic_menu_today),
                                contentDescription = "Home"
                            )
                        },
                        label = { Text("Home") },
                        selected = currentDestination?.hierarchy?.any { it.route == "home" } == true,
                        onClick = {
                            navController.navigate("home") {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )

                    // Scan navigation item
                    NavigationBarItem(
                        icon = {
                            Icon(
                                painter = painterResource(id = android.R.drawable.ic_menu_camera),
                                contentDescription = "Scan"
                            )
                        },
                        label = { Text("Scan") },
                        selected = currentDestination?.hierarchy?.any { it.route == "scan" } == true,
                        onClick = {
                            navController.navigate("scan") {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )

                    // Ask AI navigation item
                    NavigationBarItem(
                        icon = {
                            Icon(
                                painter = painterResource(id = android.R.drawable.ic_menu_edit),
                                contentDescription = "Ask AI"
                            )
                        },
                        label = { Text("Ask AI") },
                        selected = currentDestination?.hierarchy?.any { it.route == "ask_ai" } == true,
                        onClick = {
                            navController.navigate("ask_ai") {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )                    // History navigation item
                    NavigationBarItem(
                        icon = {
                            Icon(
                                painter = painterResource(id = android.R.drawable.ic_menu_recent_history),
                                contentDescription = "History"
                            )
                        },
                        label = { Text("History") },
                        selected = currentDestination?.hierarchy?.any { it.route == "history" } == true,
                        onClick = {
                            navController.navigate("history") {
                                popUpTo(navController.graph.findStartDestination().id) {
                                    saveState = true
                                }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    )
                    
                    // Settings navigation item
                    NavigationBarItem(
                        icon = {
                            Icon(
                                painter = painterResource(id = android.R.drawable.ic_menu_manage),
                                contentDescription = "Settings"
                            )
                        },
                        label = { Text("Settings") },
                        selected = currentDestination?.hierarchy?.any { it.route == "settings" } == true,
                        onClick = {
                            navController.navigate("settings") {
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
        ) { innerPadding ->
            NavHost(
                navController = navController,
                startDestination = "home",
                modifier = Modifier.padding(innerPadding)
            ) {
                composable("home") {
                    HomeScreen(navController)
                }
                composable("scan") {
                    ScanScreen(navController)
                }
                composable("ask_ai") {
                    AskAiScreen(navController)
                }
                composable("history") {
                    HistoryScreen(navController)
                }
                composable("settings") {
                    SettingsScreen(navController)
                }
            }
        }
    }
}
