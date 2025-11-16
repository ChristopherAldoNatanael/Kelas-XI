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
import com.christopheraldoo.aplikasimonitoringkelas.ui.components.BottomNavItem
import kotlinx.coroutines.*
import kotlinx.coroutines.launch

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
            composable("jadwal_pelajaran") { JadwalPelajaranKepalaPage() }
            composable("kelas_kosong") { KelasKosongPage() }
        }
    }
}

@Composable
fun KepalaSekolahBottomNavigationBar(navController: NavHostController) {
    val items = listOf(
        BottomNavItem("jadwal_pelajaran", "Jadwal", Icons.Default.DateRange),
        BottomNavItem("kelas_kosong", "Kelas Kosong", Icons.Default.MeetingRoom)
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
    // Use the new monitoring activity
    MonitoringRuanganScreen()
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MonitoringKelasKosongPage() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // State variables
    var selectedDay by remember { mutableStateOf("Senin") }
    var selectedPeriod by remember { mutableStateOf<Int?>(null) }
    var isLoading by remember { mutableStateOf(true) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var expandedDay by remember { mutableStateOf(false) }
    var expandedPeriod by remember { mutableStateOf(false) }

    // Data from API
    var classrooms by remember { mutableStateOf<List<ClassroomApi>>(emptyList()) }
    var schedules by remember { mutableStateOf<List<ScheduleApi>>(emptyList()) }
    var attendanceData by remember { mutableStateOf<List<AttendanceStatusKepala>>(emptyList()) }

    val days = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")
    val periods = (1..10).toList()

    // Load initial data
    LaunchedEffect(Unit) {
        loadMonitoringData(context, scope) { classroomsList, schedulesList, attendanceList, error ->
            classrooms = classroomsList ?: emptyList()
            schedules = schedulesList ?: emptyList()
            attendanceData = attendanceList ?: emptyList()
            errorMessage = error
            isLoading = false
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Text(
            text = "Monitoring Kelas Real-time",
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
                isLoading = true
                errorMessage = null
                loadMonitoringData(context, scope) { classroomsList, schedulesList, attendanceList, error ->
                    classrooms = classroomsList ?: emptyList()
                    schedules = schedulesList ?: emptyList()
                    attendanceData = attendanceList ?: emptyList()
                    errorMessage = error
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
            // Filter schedules based on selection
            val filteredSchedules = schedules.filter { schedule ->
                schedule.dayOfWeek == selectedDay &&
                (selectedPeriod == null || schedule.period == selectedPeriod)
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
                            Text("Kelas Aktif")
                        }
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                text = emptyClassrooms.size.toString(),
                                style = MaterialTheme.typography.headlineLarge,
                                color = MaterialTheme.colorScheme.error,
                                fontWeight = FontWeight.Bold
                            )
                            Text("Kelas Kosong")
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Active classrooms
            if (activeClassrooms.isNotEmpty()) {
                Text(
                    text = "Kelas Aktif",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                LazyColumn {
                    items(activeClassrooms) { schedule ->
                        ClassroomStatusCard(
                            schedule = schedule,
                            status = "active",
                            attendanceInfo = attendanceData.find {
                                it.classId == schedule.classId
                            }
                        )
                    }
                }
            }

            // Empty classrooms
            if (emptyClassrooms.isNotEmpty()) {
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = "Kelas Kosong",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.error,
                    modifier = Modifier.padding(bottom = 8.dp)
                )

                LazyColumn {
                    items(emptyClassrooms) { schedule ->
                        ClassroomStatusCard(
                            schedule = schedule,
                            status = "empty",
                            attendanceInfo = null
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

@Composable
fun ClassroomStatusCard(
    schedule: ScheduleApi,
    status: String,
    attendanceInfo: AttendanceStatusKepala?
) {
    val statusColor = if (status == "active")
        MaterialTheme.colorScheme.primaryContainer
    else
        MaterialTheme.colorScheme.errorContainer

    val statusIconColor = if (status == "active")
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
                    if (schedule.className != null) {
                        Text(
                            text = schedule.className,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    if (schedule.subjectName != null) {
                        Text(
                            text = "Mata Pelajaran: ${schedule.subjectName}",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                    if (schedule.teacherName != null) {
                        Text(
                            text = "Guru: ${schedule.teacherName}",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                }

                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        if (status == "active") Icons.Default.People else Icons.Default.MeetingRoom,
                        contentDescription = status,
                        tint = statusIconColor,
                        modifier = Modifier.size(32.dp)
                    )
                    Text(
                        text = if (status == "active") "Aktif" else "Kosong",
                        style = MaterialTheme.typography.bodySmall,
                        color = statusIconColor
                    )
                }
            }

            attendanceInfo?.let { attendance ->
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Status: ${attendance.status} | Waktu: ${attendance.timestamp}",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.Gray
                )
            }
        }
    }
}

data class AttendanceStatusKepala(
    val classId: Int,
    val status: String,
    val timestamp: String
)

private suspend fun loadMonitoringData(
    context: Context,
        onResult: (List<ClassroomApi>?, List<ScheduleApi>?, List<AttendanceStatusKepala>?, String?) -> Unit
) {
    try {
        val token = context.getSharedPreferences("MonitoringKelasSession", Context.MODE_PRIVATE)
            .getString("token", null) ?: ""

        if (token.isEmpty()) {
            onResult(null, null, null, "Token tidak ditemukan")
            return
        }

        val classroomsResponse = withContext(Dispatchers.IO) {
            com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                .createApiService(context)
                .getClassrooms("Bearer $token")
        }

        val schedulesResponse = withContext(Dispatchers.IO) {
            com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                .createApiService(context)
                .getSchedules("Bearer $token")
        }

        val classrooms = if (classroomsResponse.isSuccessful && classroomsResponse.body()?.success == true) {
            classroomsResponse.body()?.data
        } else null

        val schedules = if (schedulesResponse.isSuccessful && schedulesResponse.body()?.success == true) {
            schedulesResponse.body()?.data
        } else null

        // Mock attendance data for now - in real implementation, this would come from API
        val attendanceData = listOf(
            AttendanceStatusKepala(1, "active", "08:00"),
            AttendanceStatusKepala(2, "empty", "08:00"),
            AttendanceStatusKepala(3, "active", "09:00")
        )

        val error = when {
            classrooms == null -> "Gagal memuat data ruangan"
            schedules == null -> "Gagal memuat data jadwal"
            else -> null
        }

        onResult(classrooms, schedules, attendanceData, error)
    } catch (e: Exception) {
        onResult(null, null, null, "Error: ${e.localizedMessage}")
    }
}

// Updated launch to use suspend function
private fun loadMonitoringData(
    context: Context,
    scope: kotlinx.coroutines.CoroutineScope,
    onResult: (List<ClassroomApi>?, List<ScheduleApi>?, List<AttendanceStatusKepala>?, String?) -> Unit
) {
    scope.launch {
        loadMonitoringData(context, onResult)
    }
}


