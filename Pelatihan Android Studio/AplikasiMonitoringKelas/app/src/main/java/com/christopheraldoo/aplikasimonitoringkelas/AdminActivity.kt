package com.christopheraldoo.aplikasimonitoringkelas

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.compose.*

class AdminActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                AdminApp()
            }
        }
    }
}

@Composable
fun AdminApp() {
    val navController = rememberNavController()

    Scaffold(
        bottomBar = {
            AdminBottomNavigationBar(navController = navController)
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = "entri_jadwal",
            modifier = Modifier.padding(paddingValues)
        ) {
            composable("entri_jadwal") {
                EntriJadwalPage()
            }
            composable("ubah_jadwal") {
                UbahJadwalPage()
            }
            composable("list") {
                ListAdminPage()
            }
        }
    }
}

@Composable
fun AdminBottomNavigationBar(navController: NavHostController) {
    val items = listOf(
        BottomNavItem("entri_jadwal", "Entri Jadwal", Icons.Default.Add),
        BottomNavItem("ubah_jadwal", "Ubah Jadwal", Icons.Default.Edit),
        BottomNavItem("list", "List", Icons.AutoMirrored.Filled.List)
    )

    NavigationBar {
        val currentRoute = navController.currentBackStackEntryAsState().value?.destination?.route

        items.forEach { item ->
            NavigationBarItem(
                icon = {
                    Icon(
                        item.icon,
                        contentDescription = item.label,
                        tint = if (currentRoute == item.route)
                            MaterialTheme.colorScheme.primary
                        else
                            Color.Gray
                    )
                },
                label = {
                    Text(
                        item.label,
                        color = if (currentRoute == item.route)
                            MaterialTheme.colorScheme.primary
                        else
                            Color.Gray
                    )
                },
                selected = currentRoute == item.route,
                onClick = {
                    navController.navigate(item.route) {
                        navController.graph.startDestinationRoute?.let { route ->
                            popUpTo(route) {
                                saveState = true
                            }
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )
        }
    }
}

@Composable
fun EntriJadwalPage() {
    // We'll use the implementation from EntriJadwal.kt instead
    EntriJadwal()
}

@Composable
fun UbahJadwalPage() {
    // Reuse JadwalPage
    JadwalPage(userName = "Admin", userRole = "Admin")
}

@Composable
fun ListAdminPage() {
    // Use the EntriUser implementation
    EntriUser()
}
