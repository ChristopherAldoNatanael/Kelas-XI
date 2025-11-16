package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Context
import android.os.Bundle
import android.widget.Toast
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
        }
    }
}

@Composable
fun KurikulumBottomNavigationBar(navController: NavHostController) {
    val items = listOf(
        BottomNavItem("jadwal_pelajaran", "Jadwal", Icons.Default.DateRange),
        BottomNavItem("ganti_guru", "Ganti Guru", Icons.Default.SwapHoriz)
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
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // State variables
    var selectedDay by remember { mutableStateOf("Senin") }
    var selectedClass by remember { mutableStateOf("") }
    var selectedSubject by remember { mutableStateOf("") }
    var selectedSubstituteTeacher by remember { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }

    // Dropdown state
    var expandedDay by remember { mutableStateOf(false) }
    var expandedClass by remember { mutableStateOf(false) }
    var expandedSubject by remember { mutableStateOf(false) }
    var expandedTeacher by remember { mutableStateOf(false) }

    // Data from API
    var classes by remember { mutableStateOf<List<ClassroomApi>>(emptyList()) }
    var subjects by remember { mutableStateOf<List<SubjectApi>>(emptyList()) }
    var teachers by remember { mutableStateOf<List<TeacherApi>>(emptyList()) }
    var schedules by remember { mutableStateOf<List<ScheduleApi>>(emptyList()) }

    val days = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")

    // Load initial data
    LaunchedEffect(Unit) {
        loadDropdownData(context, scope) { classesList, subjectsList, teachersList, error -> 
            classes = classesList ?: emptyList()
            subjects = subjectsList ?: emptyList()
            teachers = teachersList ?: emptyList()
            errorMessage = error
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Text(
            text = "Ganti Guru Pengganti",
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

        // Day selector
        Text(
            text = "Pilih Hari:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedDay,
            onExpandedChange = { expandedDay = !expandedDay },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedDay,
                onValueChange = { selectedDay = it },
                readOnly = true,
                label = { Text("Pilih Hari") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedDay)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
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

        // Class selector
        Text(
            text = "Pilih Kelas:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedClass,
            onExpandedChange = { expandedClass = !expandedClass },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedClass,
                onValueChange = { selectedClass = it },
                readOnly = true,
                label = { Text("Pilih Kelas") },
                placeholder = { Text("Pilih kelas terlebih dahulu") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedClass)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedClass,
                onDismissRequest = { expandedClass = false }
            ) {
                classes.forEach { classItem ->
                    DropdownMenuItem(
                        text = { Text(classItem.name) },
                        onClick = {
                            selectedClass = classItem.name
                            expandedClass = false
                        }
                    )
                }
            }
        }

        // Subject selector
        Text(
            text = "Pilih Mata Pelajaran:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedSubject,
            onExpandedChange = { expandedSubject = !expandedSubject },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = selectedSubject,
                onValueChange = { selectedSubject = it },
                readOnly = true,
                label = { Text("Pilih Mata Pelajaran") },
                placeholder = { Text("Pilih mata pelajaran") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedSubject)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedSubject,
                onDismissRequest = { expandedSubject = false }
            ) {
                subjects.forEach { subject ->
                    DropdownMenuItem(
                        text = { Text(subject.name) },
                        onClick = {
                            selectedSubject = subject.name
                            expandedSubject = false
                        }
                    )
                }
            }
        }

        // Substitute teacher selector
        Text(
            text = "Pilih Guru Pengganti:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )

        ExposedDropdownMenuBox(
            expanded = expandedTeacher,
            onExpandedChange = { expandedTeacher = !expandedTeacher },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        ) {
            OutlinedTextField(
                value = selectedSubstituteTeacher,
                onValueChange = { selectedSubstituteTeacher = it },
                readOnly = true,
                label = { Text("Pilih Guru Pengganti") },
                placeholder = { Text("Pilih guru pengganti") },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedTeacher)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedTeacher,
                onDismissRequest = { expandedTeacher = false }
            ) {
                teachers.forEach { teacher ->
                    DropdownMenuItem(
                        text = {
                            Text(
                                teacher.name,
                                style = MaterialTheme.typography.bodyMedium
                            )
                        },
                        onClick = {
                            selectedSubstituteTeacher = teacher.name
                            expandedTeacher = false
                        }
                    )
                }
            }
        }

        // Action buttons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedButton(
                onClick = {
                    selectedDay = "Senin"
                    selectedClass = ""
                    selectedSubject = ""
                    selectedSubstituteTeacher = ""
                },
                modifier = Modifier.weight(1f)
            ) {
                Text("Reset")
            }

            Button(
                onClick = {
                    when {
                        selectedClass.isEmpty() -> {
                            Toast.makeText(context, "Pilih kelas terlebih dahulu", Toast.LENGTH_SHORT).show()
                        }
                        selectedSubject.isEmpty() -> {
                            Toast.makeText(context, "Pilih mata pelajaran terlebih dahulu", Toast.LENGTH_SHORT).show()
                        }
                        selectedSubstituteTeacher.isEmpty() -> {
                            Toast.makeText(context, "Pilih guru pengganti terlebih dahulu", Toast.LENGTH_SHORT).show()
                        }
                        else -> {
                            submitTeacherSubstitution(
                                context = context,
                                scope = scope,
                                day = selectedDay,
                                className = selectedClass,
                                subjectName = selectedSubject,
                                substituteTeacherName = selectedSubstituteTeacher,
                                onLoading = { isLoading = it },
                                onError = { errorMessage = it }
                            )
                        }
                    }
                },
                modifier = Modifier.weight(2f),
                enabled = !isLoading
            ) {
                if (isLoading) {
                    CircularProgressIndicator(
                        color = Color.White,
                        modifier = Modifier.size(20.dp)
                    )
                } else {
                    Icon(
                        Icons.Default.SwapHoriz,
                        contentDescription = "Ganti",
                        modifier = Modifier.padding(end = 8.dp)
                    )
                    Text("Ganti Guru")
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))        // Show current schedules for reference
        Text(
            text = "Jadwal Hari Ini (${selectedDay})",
            style = MaterialTheme.typography.titleLarge,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        if (schedules.isEmpty()) {
            Text(
                text = "Memuat jadwal...",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray
            )
        } else {
            val daySchedules = schedules.filter { it.dayOfWeek == selectedDay }
            if (daySchedules.isEmpty()) {
                Text(
                    text = "Tidak ada jadwal untuk hari ini",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.Gray
                )
            } else {
                LazyColumn {
                    items(daySchedules) { schedule ->
                        SimpleScheduleCard(schedule = schedule)
                    }
                }
            }
        }
    }
}

private fun loadDropdownData(
    context: Context,
    scope: kotlinx.coroutines.CoroutineScope,
    onResult: (List<ClassroomApi>?, List<SubjectApi>?, List<TeacherApi>?, String?) -> Unit
) {
    scope.launch {
        try {
            val token = context.getSharedPreferences("MonitoringKelasSession", Context.MODE_PRIVATE)
                .getString("token", null) ?: ""

            if (token.isEmpty()) {
                onResult(null, null, null, "Token tidak ditemukan")
                return@launch
            }

            val schedulesResponse = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                    .createApiService(context)
                    .getSchedules("Bearer $token")
            }

            val subjectsResponse = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                    .createApiService(context)
                    .getSubjects("Bearer $token")
            }

            val teachersResponse = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                    .createApiService(context)
                    .getTeachers("Bearer $token")
            }

            val classes = if (schedulesResponse.isSuccessful && schedulesResponse.body()?.success == true) {
                // schedules response no longer includes nested classData. Use classrooms endpoint instead.
                null
            } else null

            val subjects = if (subjectsResponse.isSuccessful && subjectsResponse.body()?.success == true) {
                subjectsResponse.body()?.data
            } else null

            val teachers = if (teachersResponse.isSuccessful && teachersResponse.body()?.success == true) {
                teachersResponse.body()?.data
            } else null

            val error = when {
                classes == null -> "Gagal memuat data kelas"
                subjects == null -> "Gagal memuat data mata pelajaran"
                teachers == null -> "Gagal memuat data guru"
                else -> null
            }

            onResult(classes, subjects, teachers, error)
        } catch (e: Exception) {
            onResult(null, null, null, "Error: ${e.localizedMessage}")
        }
    }
}

private fun submitTeacherSubstitution(
    context: Context,
    scope: kotlinx.coroutines.CoroutineScope,
    day: String,
    className: String,
    subjectName: String,
    substituteTeacherName: String,
    onLoading: (Boolean) -> Unit,
    onError: (String?) -> Unit
) {
    scope.launch {
        onLoading(true)
        onError(null)

        try {
            // Here you would implement the actual API call to submit teacher substitution
            // For now, we'll just show a success message
            kotlinx.coroutines.delay(1000) // Simulate API call

            Toast.makeText(
                context,
                "Guru pengganti berhasil ditetapkan untuk $subjectName di kelas $className pada hari $day",
                Toast.LENGTH_LONG
            ).show()

        } catch (e: Exception) {
            onError("Error: ${e.localizedMessage}")
        } finally {
            onLoading(false)
        }
    }
}

@Composable
fun SimpleScheduleCard(schedule: ScheduleApi) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = schedule.subjectName ?: "Unknown Subject",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "Jam ${schedule.period}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            
            Spacer(modifier = Modifier.height(4.dp))
              Text(
                text = "Guru: ${schedule.teacherName ?: "Unknown Teacher"}",
                style = MaterialTheme.typography.bodyMedium
            )
            
            Text(
                text = schedule.className ?: "Kelas tidak diketahui",
                style = MaterialTheme.typography.bodyMedium
            )
            
            Text(
                text = "Ruang: ${schedule.className ?: "Unknown Room"}",
                style = MaterialTheme.typography.bodyMedium
            )
            
            Text(
                text = "Waktu: ${schedule.startTime} - ${schedule.endTime}",
                style = MaterialTheme.typography.bodySmall,
                color = Color.Gray
            )
        }
    }
}


