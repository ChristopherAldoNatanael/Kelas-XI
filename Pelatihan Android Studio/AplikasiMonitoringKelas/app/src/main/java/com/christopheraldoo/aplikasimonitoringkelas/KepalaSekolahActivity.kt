package com.christopheraldoo.aplikasimonitoringkelas

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.MeetingRoom
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.compose.*

class KepalaSekolahActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                KepalaSekolahApp()
            }
        }
    }
}

@Composable
fun KepalaSekolahApp() {
    val navController = rememberNavController()

    Scaffold(
        bottomBar = {
            KepalaSekolahBottomNavigationBar(navController = navController)
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = "jadwal_pelajaran",
            modifier = Modifier.padding(paddingValues)
        ) {
            composable("jadwal_pelajaran") {
                JadwalPelajaranKepalaPage()
            }
            composable("kelas_kosong") {
                KelasKosongPage()
            }
            composable("list") {
                ListKepalaPage()
            }
        }
    }
}

@Composable
fun KepalaSekolahBottomNavigationBar(navController: NavHostController) {
    val items = listOf(
        BottomNavItem("jadwal_pelajaran", "Jadwal", Icons.Default.DateRange),
        BottomNavItem("kelas_kosong", "Kelas Kosong", Icons.Default.MeetingRoom),
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
fun JadwalPelajaranKepalaPage() {
    // Reuse the JadwalPage with a different user role
    JadwalPage(userName = "Kepala Sekolah", userRole = "Kepala Sekolah")
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KelasKosongPage() {
    var selectedHari by remember { mutableStateOf("Senin") }
    var expandedHari by remember { mutableStateOf(false) }

    val hariOptions = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")
    
    // Sample data for empty classrooms
    val kelasKosong = mapOf(
        "Senin" to listOf(
            "Lab Komputer 1 (Jam ke-1 hingga Jam ke-3)",
            "Ruang Musik (Jam ke-4 hingga Jam ke-6)",
            "Aula (Jam ke-7 hingga Jam ke-10)"
        ),
        "Selasa" to listOf(
            "Lab Bahasa (Jam ke-1 hingga Jam ke-4)",
            "Perpustakaan (Jam ke-5 hingga Jam ke-7)"
        ),
        "Rabu" to listOf(
            "Lab Komputer 2 (Jam ke-3 hingga Jam ke-5)",
            "Ruang Seni (Jam ke-6 hingga Jam ke-8)"
        ),
        "Kamis" to listOf(
            "Ruang Musik (Jam ke-1 hingga Jam ke-3)",
            "Lab IPA (Jam ke-7 hingga Jam ke-10)"
        ),
        "Jumat" to listOf(
            "Aula (Jam ke-1 hingga Jam ke-3)",
            "Lab Komputer 1 (Jam ke-4 hingga Jam ke-6)"
        ),
        "Sabtu" to listOf(
            "Lab Bahasa (Jam ke-1 hingga Jam ke-5)",
            "Perpustakaan (Jam ke-6 hingga Jam ke-10)"
        )
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Header
        Text(
            text = "Monitoring Kelas Kosong",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        )

        // Hari Spinner
        Text(
            text = "Pilih Hari:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedHari,
            onExpandedChange = { expandedHari = !expandedHari },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        ) {
            OutlinedTextField(
                value = selectedHari,
                onValueChange = { selectedHari = it },
                readOnly = true,
                label = { Text("Hari") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedHari)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedHari,
                onDismissRequest = { expandedHari = false }
            ) {
                hariOptions.forEach { hari ->
                    DropdownMenuItem(
                        text = { Text(hari) },
                        onClick = {
                            selectedHari = hari
                            expandedHari = false
                        }
                    )
                }
            }
        }

        // Display empty classrooms for selected day
        Text(
            text = "Kelas Kosong pada Hari $selectedHari:",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 16.dp)
        )

        kelasKosong[selectedHari]?.forEach { kelas ->
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 8.dp),
                elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.MeetingRoom,
                        contentDescription = "Kelas Kosong",
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier
                            .size(32.dp)
                            .padding(end = 16.dp)
                    )
                    Text(
                        text = kelas,
                        style = MaterialTheme.typography.bodyLarge
                    )
                }
            }
        }
    }
}

@Composable
fun ListKepalaPage() {
    // Use EntriUser as an example
    EntriUser()
}
