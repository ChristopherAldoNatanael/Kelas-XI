package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Context
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.compose.*
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ApiState
import kotlinx.coroutines.*

class MonitoringRuanganActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                MonitoringRuanganScreen()
            }
        }
    }
}

@Composable
fun EmptyClassroomCard(classroom: ClassroomApi, status: String = "KOSONG") {
    val statusColor = if (status == "DILAKSANAKAN")
        MaterialTheme.colorScheme.primaryContainer
    else
        MaterialTheme.colorScheme.errorContainer

    val statusIconColor = if (status == "DILAKSANAKAN")
        MaterialTheme.colorScheme.primary
    else
        MaterialTheme.colorScheme.error

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        colors = CardDefaults.cardColors(containerColor = statusColor)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = classroom.name,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "Kode: ${classroom.roomNumber ?: ""}",
                        style = MaterialTheme.typography.bodyMedium
                    )
                    Text(
                        text = "Kapasitas: ${classroom.capacity ?: 0} siswa",
                        style = MaterialTheme.typography.bodyMedium
                    )
                    Text(
                        text = "Lantai: ${1 /* classroom.floor */}, Gedung: ${"Gedung A" /* classroom.building */}",
                        style = MaterialTheme.typography.bodyMedium
                    )
                    if (!emptyList<String>() /* classroom.facilities */.isNullOrEmpty()) {
                        Text(
                            text = "Fasilitas: ${emptyList<String>() /* classroom.facilities */.joinToString(", ")}",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color.Gray
                        )
                    }
                }

                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        if (status == "DILAKSANAKAN") Icons.Default.People else Icons.Default.MeetingRoom,
                        contentDescription = status,
                        tint = statusIconColor,
                        modifier = Modifier.size(32.dp)
                    )
                    Text(
                        text = status,
                        style = MaterialTheme.typography.bodySmall,
                        color = statusIconColor,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }
    }
}

data class AttendanceStatus(
    val classId: Int,
    val status: String,
    val timestamp: String
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MonitoringRuanganScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // Explicit state with types
    var selectedDay by remember { mutableStateOf<String>("Senin") }
    var selectedPeriod by remember { mutableStateOf<Int?>(null) }
    var isLoading by remember { mutableStateOf<Boolean>(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var expandedDay by remember { mutableStateOf<Boolean>(false) }
    var expandedPeriod by remember { mutableStateOf<Boolean>(false) }

    val classroomsState = remember { mutableStateOf<List<ClassroomApi>>(emptyList()) }
    val schedulesState = remember { mutableStateOf<List<ScheduleApi>>(emptyList()) }
    val attendanceState = remember { mutableStateOf<List<AttendanceStatus>>(emptyList()) }

    val classrooms = classroomsState.value
    val schedules = schedulesState.value
    val attendanceData = attendanceState.value

    val days = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")
    val periods = (1..10).toList()

    LaunchedEffect(Unit) {
        // Load data here when API available
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Text(
            text = "Monitoring Ruangan Real-time",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        )

        if (errorMessage != null) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.errorContainer
                )
            ) {
                Text(
                    text = errorMessage!!,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(16.dp)
                )
            }
            Spacer(modifier = Modifier.height(16.dp))
        }

        // Filter controls
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            // Day selector
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Hari:",
                    style = MaterialTheme.typography.labelMedium,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
                ExposedDropdownMenuBox(
                    expanded = expandedDay,
                    onExpandedChange = { expandedDay = !expandedDay }
                ) {
                    OutlinedTextField(
                        value = selectedDay,
                        onValueChange = { selectedDay = it },
                        readOnly = true,
                        modifier = Modifier.menuAnchor()
                    )
                    ExposedDropdownMenu(
                        expanded = expandedDay,
                        onDismissRequest = { expandedDay = false }
                    ) {
                        days.forEach { day ->
                            DropdownMenuItem(
                                text = { Text(day) },
                                onClick = {
                                    selectedDay = day
                                    expandedDay = false
                                }
                            )
                        }
                    }
                }
            }

            // Period selector
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Jam ke:",
                    style = MaterialTheme.typography.labelMedium,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
                ExposedDropdownMenuBox(
                    expanded = expandedPeriod,
                    onExpandedChange = { expandedPeriod = !expandedPeriod }
                ) {
                    OutlinedTextField(
                        value = selectedPeriod?.toString() ?: "Semua",
                        onValueChange = {},
                        readOnly = true,
                        modifier = Modifier.menuAnchor()
                    )
                    ExposedDropdownMenu(
                        expanded = expandedPeriod,
                        onDismissRequest = { expandedPeriod = false }
                    ) {
                        DropdownMenuItem(
                            text = { Text("Semua Jam") },
                            onClick = {
                                selectedPeriod = null
                                expandedPeriod = false
                            }
                        )
                        periods.forEach { period ->
                            DropdownMenuItem(
                                text = { Text("Jam ke-$period") },
                                onClick = {
                                    selectedPeriod = period
                                    expandedPeriod = false
                                }
                            )
                        }
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Refresh button
        OutlinedButton(
            onClick = {
                // Simulate refresh
                scope.launch {
                    isLoading = true
                    delay(1000)
                    isLoading = false
                }
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Icon(Icons.Default.Refresh, contentDescription = "Refresh")
            Spacer(modifier = Modifier.width(8.dp))
            Text("Refresh Data")
        }

        Spacer(modifier = Modifier.height(16.dp))

        if (isLoading) {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        } else {
            // Filter classrooms based on selection
            val filteredSchedules = schedules.filter { schedule ->
                schedule.dayOfWeek == selectedDay && (selectedPeriod == null || schedule.period == selectedPeriod)
            }

            val activeClassrooms = filteredSchedules.filter { schedule ->
                attendanceData.any { attendance ->
                    attendance.classId == schedule.classId &&
                    attendance.status == "active"
                }
            }

            val emptyClassrooms = filteredSchedules.filter { schedule ->
                attendanceData.none { attendance ->
                    attendance.classId == schedule.classId &&
                    attendance.status == "active"
                }
            }

            // Summary cards
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Ringkasan",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = activeClassrooms.size.toString(),
                                style = MaterialTheme.typography.headlineLarge,
                                color = MaterialTheme.colorScheme.primary,
                                fontWeight = FontWeight.Bold
                            )
                            Text("Ruangan Aktif")
                        }
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = emptyClassrooms.size.toString(),
                                style = MaterialTheme.typography.headlineLarge,
                                color = MaterialTheme.colorScheme.error,
                                fontWeight = FontWeight.Bold
                            )
                            Text("Ruangan Kosong")
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Active classrooms
            if (activeClassrooms.isNotEmpty()) {
                Text(
                    text = "Ruangan Aktif",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                LazyColumn {
                    items(activeClassrooms) { schedule ->
                        EmptyClassroomCard(
                            classroom = ClassroomApi(id = schedule.classId, name = schedule.className ?: "Unknown", grade = 10),
                            status = "DILAKSANAKAN"
                        )
                    }
                }
            }

            // Empty classrooms
            if (emptyClassrooms.isNotEmpty()) {
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Ruangan Kosong",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                LazyColumn {
                    items(emptyClassrooms) { schedule ->
                        EmptyClassroomCard(
                            classroom = ClassroomApi(id = schedule.classId, name = schedule.className ?: "Unknown", grade = 10),
                            status = "KOSONG"
                        )
                    }
                }
            }

            if (activeClassrooms.isEmpty() && emptyClassrooms.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Tidak ada jadwal untuk hari $selectedDay" +
                              (selectedPeriod?.let { " jam ke-$it" } ?: ""),
                        style = MaterialTheme.typography.bodyLarge,
                        textAlign = TextAlign.Center,
                        color = Color.Gray
                    )
                }
            }
        }
    }
}




