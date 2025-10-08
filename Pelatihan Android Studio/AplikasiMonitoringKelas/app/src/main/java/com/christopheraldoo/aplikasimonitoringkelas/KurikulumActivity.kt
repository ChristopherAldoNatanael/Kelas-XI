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
import androidx.compose.material.icons.filled.SwapHoriz
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

class KurikulumActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                KurikulumApp()
            }
        }
    }
}

@Composable
fun KurikulumApp() {
    val navController = rememberNavController()

    Scaffold(
        bottomBar = {
            KurikulumBottomNavigationBar(navController = navController)
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = "jadwal_pelajaran",
            modifier = Modifier.padding(paddingValues)
        ) {
            composable("jadwal_pelajaran") {
                JadwalPelajaranKurikulumPage()
            }
            composable("ganti_guru") {
                GantiGuruPage()
            }
            composable("list") {
                ListKurikulumPage()
            }
        }
    }
}

@Composable
fun KurikulumBottomNavigationBar(navController: NavHostController) {
    val items = listOf(
        BottomNavItem("jadwal_pelajaran", "Jadwal", Icons.Default.DateRange),
        BottomNavItem("ganti_guru", "Ganti Guru", Icons.Default.SwapHoriz),
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
fun JadwalPelajaranKurikulumPage() {
    // Reuse the JadwalPage with a different user role
    JadwalPage(userName = "Admin Kurikulum", userRole = "Kurikulum")
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GantiGuruPage() {
    var selectedHari by remember { mutableStateOf("Senin") }
    var selectedKelas by remember { mutableStateOf("X RPL") }
    var selectedMataPelajaran by remember { mutableStateOf("IPA") }
    var selectedGuruPengganti by remember { mutableStateOf("") }
    var expandedHari by remember { mutableStateOf(false) }
    var expandedKelas by remember { mutableStateOf(false) }
    var expandedMataPelajaran by remember { mutableStateOf(false) }
    var expandedGuruPengganti by remember { mutableStateOf(false) }

    val hariOptions = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu")
    val kelasOptions = listOf("X RPL", "XI RPL", "XII RPL")
    val mataPelajaranOptions = listOf("IPA", "IPS", "Bahasa")
    val guruPenggantiOptions = listOf("Siti", "Budi", "Adi", "Agus", "Diana", "Eko", "Fendi")

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Text(
            text = "Ganti Guru",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        )

        // Spinner Hari
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
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedHari,
                onValueChange = { selectedHari = it },
                readOnly = true,
                label = { Text("Pilih Hari") },
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

        // Spinner Kelas
        Text(
            text = "Pilih Kelas:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedKelas,
            onExpandedChange = { expandedKelas = !expandedKelas },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedKelas,
                onValueChange = { selectedKelas = it },
                readOnly = true,
                label = { Text("Pilih Kelas") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedKelas)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedKelas,
                onDismissRequest = { expandedKelas = false }
            ) {
                kelasOptions.forEach { kelas ->
                    DropdownMenuItem(
                        text = { Text(kelas) },
                        onClick = {
                            selectedKelas = kelas
                            expandedKelas = false
                        }
                    )
                }
            }
        }

        // Spinner Mata Pelajaran
        Text(
            text = "Pilih Mata Pelajaran:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedMataPelajaran,
            onExpandedChange = { expandedMataPelajaran = !expandedMataPelajaran },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedMataPelajaran,
                onValueChange = { selectedMataPelajaran = it },
                readOnly = true,
                label = { Text("Pilih Mata Pelajaran") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedMataPelajaran)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedMataPelajaran,
                onDismissRequest = { expandedMataPelajaran = false }
            ) {
                mataPelajaranOptions.forEach { mapel ->
                    DropdownMenuItem(
                        text = { Text(mapel) },
                        onClick = {
                            selectedMataPelajaran = mapel
                            expandedMataPelajaran = false
                        }
                    )
                }
            }
        }

        // Spinner Guru Pengganti
        Text(
            text = "Pilih Guru Pengganti:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedGuruPengganti,
            onExpandedChange = { expandedGuruPengganti = !expandedGuruPengganti },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        ) {
            OutlinedTextField(
                value = selectedGuruPengganti,
                onValueChange = { selectedGuruPengganti = it },
                readOnly = true,
                label = { Text("Pilih Guru Pengganti") },
                placeholder = { Text("Pilih guru pengganti") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedGuruPengganti)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedGuruPengganti,
                onDismissRequest = { expandedGuruPengganti = false }
            ) {
                guruPenggantiOptions.forEach { guru ->
                    DropdownMenuItem(
                        text = { Text(guru) },
                        onClick = {
                            selectedGuruPengganti = guru
                            expandedGuruPengganti = false
                        }
                    )
                }
            }
        }

        // Button Ganti
        Button(
            onClick = {
                // Action to replace teacher
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = Color.White
            )
        ) {
            Icon(
                Icons.Default.SwapHoriz,
                contentDescription = "Ganti",
                modifier = Modifier.padding(end = 8.dp)
            )
            Text(
                text = "Ganti Guru",
                style = MaterialTheme.typography.titleMedium
            )
        }
    }
}

@Composable
fun ListKurikulumPage() {
    // Reuse the EntriUser component
    EntriUser()
}
