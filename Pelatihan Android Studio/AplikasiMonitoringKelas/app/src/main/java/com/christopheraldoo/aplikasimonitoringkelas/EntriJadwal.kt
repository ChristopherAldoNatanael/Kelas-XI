package com.christopheraldoo.aplikasimonitoringkelas

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Save
import androidx.compose.material.icons.filled.School
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.livedata.observeAsState
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.GlobalScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ScheduleViewModel
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.SaveResult
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ScheduleNetworkViewModel
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.MasterDataNetworkViewModel
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ClassroomNetworkViewModel
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ApiState
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkUtils
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TeacherApi
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomApi
import com.google.gson.JsonObject
import androidx.lifecycle.viewmodel.compose.viewModel

// Data class untuk menyimpan data jadwal yang di-entri
data class JadwalData(
    val hari: String,
    val kelas: String,
    val mataPelajaran: String,
    val namaGuru: String,
    val jamKe: String
)

// State untuk form input jadwal
data class JadwalFormState(
    var selectedHari: String = "",
    var selectedKelas: String = "",
    var selectedMataPelajaran: String = "",
    var selectedNamaGuru: String = "",
    var jamKe: String = ""
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EntriJadwal() {
    val formState = remember { JadwalFormState() }
    val viewModel: ScheduleViewModel = viewModel()
    
    // Add network ViewModels for dropdown data
    val masterDataViewModel: MasterDataNetworkViewModel = viewModel()
    val classroomViewModel: ClassroomNetworkViewModel = viewModel()

    // Observe API states
    val subjectsState by masterDataViewModel.subjectsState.collectAsStateWithLifecycle()
    val teachersState by masterDataViewModel.filteredTeachersState.collectAsStateWithLifecycle()
    val classroomsState by classroomViewModel.classroomsState.collectAsStateWithLifecycle()    // Load data when component mounts
    LaunchedEffect(Unit) {
        masterDataViewModel.getSubjects()
        classroomViewModel.getClassrooms()
        viewModel.getAllSchedules() // Load existing schedules
    }

    // Load teachers when subject is selected
    LaunchedEffect(formState.selectedMataPelajaran) {
        if (formState.selectedMataPelajaran.isNotEmpty()) {
            when (val state = subjectsState) {
                is ApiState.Success -> {
                    val selectedSubject = state.data.find { it.name == formState.selectedMataPelajaran }
                    selectedSubject?.let { subject ->
                        masterDataViewModel.getFilteredTeachersBySubject(subject.id)
                    }
                }
                else -> {}
            }
        }
    }

    // State untuk menyimpan data yang sudah di-entri
    var showDialog by remember { mutableStateOf(false) }
    var isSuccess by remember { mutableStateOf(false) }
    var dialogMessage by remember { mutableStateOf("") }
    // Observe save status from viewModel
    val saveStatus by viewModel.saveStatus.collectAsStateWithLifecycle()    // Observe schedules from viewModel
    val schedules by viewModel.allSchedules.observeAsState(initial = emptyList())

      // Effect to handle save status changes
    var isLoading by remember { mutableStateOf(false) }

    LaunchedEffect(saveStatus) {
        when (val currentStatus = saveStatus) {            is SaveResult.Success -> {
                isLoading = false
                val schedule = currentStatus.data
                dialogMessage = "🎉 Jadwal berhasil disimpan ke MySQL Database!\n\n" +
                    "📝 Detail:\n" +
                    "• ID: ${schedule.id}\n" +
                    "• Hari: ${formState.selectedHari}\n" +
                    "• Mata Pelajaran: ${formState.selectedMataPelajaran}\n" +
                    "• Guru: ${formState.selectedNamaGuru}\n" +
                    "• Ruangan: ${formState.selectedKelas}\n" +
                    "• Jam: ${formState.jamKe}\n" +
                    "• Status: Berhasil tersimpan"
                isSuccess = true
                showDialog = true
            }
            is SaveResult.Error -> {
                isLoading = false
                dialogMessage = "Gagal menyimpan jadwal: ${currentStatus.message}"
                isSuccess = false
                showDialog = true
            }
            else -> {} // Do nothing for Initial state
        }
    }

    // Options untuk spinners
    val hariOptions = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu")
      // Get classroom options from API
    val kelasOptions = when (val state = classroomsState) {
        is ApiState.Success -> state.data.map { it.name }
        else -> emptyList()
    }
    
    // Get subject options from API
    val mataPelajaranOptions = when (val state = subjectsState) {
        is ApiState.Success -> state.data.map { it.name }
        else -> emptyList()
    }
      // Get teacher options from API (filtered by subject)
    val namaGuruOptions = when (val state = teachersState) {
        is ApiState.Success -> state.data.map { it.name }
        else -> emptyList()
    }
    
    val scrollState = rememberScrollState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
            .verticalScroll(scrollState)
    ) {
        // Header
        Text(
            text = "Entri Jadwal Pelajaran",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
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

        var expandedHari by remember { mutableStateOf(false) }

        ExposedDropdownMenuBox(
            expanded = expandedHari,
            onExpandedChange = { expandedHari = !expandedHari },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedHari,
                onValueChange = { formState.selectedHari = it },
                readOnly = true,
                label = { Text("Pilih Hari") },
                placeholder = { Text("Pilih hari dalam seminggu") },
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
                            formState.selectedHari = hari
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

        var expandedKelas by remember { mutableStateOf(false) }

        ExposedDropdownMenuBox(
            expanded = expandedKelas,
            onExpandedChange = { expandedKelas = !expandedKelas },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedKelas,
                onValueChange = { formState.selectedKelas = it },
                readOnly = true,
                label = { Text("Pilih Kelas") },
                placeholder = { Text("Pilih kelas yang diinginkan") },
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
                            formState.selectedKelas = kelas
                            expandedKelas = false
                        }
                    )
                }
            }
        }        // Text Field Mata Pelajaran
        Text(
            text = "Pilih Mata Pelajaran:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        var expandedMapel by remember { mutableStateOf(false) }

        ExposedDropdownMenuBox(
            expanded = expandedMapel,
            onExpandedChange = { expandedMapel = !expandedMapel },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedMataPelajaran,
                onValueChange = { 
                    formState.selectedMataPelajaran = it
                    // Clear teacher selection when subject changes
                    formState.selectedNamaGuru = ""
                },
                readOnly = true,
                label = { Text("Mata Pelajaran") },
                placeholder = { Text("Pilih mata pelajaran") },
                leadingIcon = {
                    Icon(
                        Icons.Default.School,
                        contentDescription = "Mata Pelajaran",
                        tint = MaterialTheme.colorScheme.primary
                    )
                },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedMapel)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedMapel,
                onDismissRequest = { expandedMapel = false }
            ) {
                when (val state = subjectsState) {
                    is ApiState.Loading -> {
                        DropdownMenuItem(
                            text = { 
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    CircularProgressIndicator(modifier = Modifier.size(16.dp))
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Loading subjects...")
                                }
                            },
                            onClick = { }
                        )
                    }
                    is ApiState.Success -> {                        state.data.forEach { subject ->
                            DropdownMenuItem(
                                text = { Text(subject.name) },
                                onClick = {
                                    formState.selectedMataPelajaran = subject.name
                                    formState.selectedNamaGuru = "" // Clear teacher selection
                                    expandedMapel = false
                                }
                            )
                        }
                    }
                    is ApiState.Error -> {
                        DropdownMenuItem(
                            text = { Text("Error: ${state.message}") },
                            onClick = { }
                        )
                    }
                    else -> {}
                }
            }
        }        // Dropdown Nama Guru
        Text(
            text = "Pilih Nama Guru:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        var expandedGuru by remember { mutableStateOf(false) }

        ExposedDropdownMenuBox(
            expanded = expandedGuru,
            onExpandedChange = { expandedGuru = !expandedGuru },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedNamaGuru,
                onValueChange = { formState.selectedNamaGuru = it },
                readOnly = true,
                label = { Text("Nama Guru") },                placeholder = { 
                    Text(
                        if (formState.selectedMataPelajaran.isEmpty()) {
                            "Pilih mata pelajaran terlebih dahulu"
                        } else {
                            "Pilih nama guru pengajar"
                        }
                    )
                },
                leadingIcon = {
                    Icon(
                        Icons.Default.Person,
                        contentDescription = "Nama Guru",
                        tint = MaterialTheme.colorScheme.primary
                    )
                },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedGuru)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                enabled = formState.selectedMataPelajaran.isNotEmpty(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedGuru,
                onDismissRequest = { expandedGuru = false }
            ) {
                when (val state = teachersState) {
                    is ApiState.Loading -> {
                        DropdownMenuItem(
                            text = { 
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    CircularProgressIndicator(modifier = Modifier.size(16.dp))
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text("Loading teachers...")
                                }
                            },
                            onClick = { }
                        )
                    }
                    is ApiState.Success -> {
                        if (state.data.isEmpty()) {
                            DropdownMenuItem(
                                text = { Text("No teachers available for this subject") },
                                onClick = { }
                            )
                        } else {
                            state.data.forEach { teacher ->
                                DropdownMenuItem(
                                    text = { Text(teacher.name ?: "Unknown Teacher") },
                                    onClick = {
                                        formState.selectedNamaGuru = teacher.name ?: "Unknown Teacher"
                                        expandedGuru = false
                                    }
                                )
                            }
                        }
                    }
                    is ApiState.Error -> {
                        DropdownMenuItem(
                            text = { Text("Error: ${state.message}") },
                            onClick = { }
                        )
                    }
                    else -> {
                        if (formState.selectedMataPelajaran.isNotEmpty()) {
                            DropdownMenuItem(
                                text = { Text("Select a subject first") },
                                onClick = { }
                            )
                        }
                    }
                }
            }
        }

        // Dropdown untuk Jam Ke
        Text(
            text = "Pilih Jam Ke:",
            style = MaterialTheme.typography.titleMedium,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        var expandedJam by remember { mutableStateOf(false) }
        val jamOptions = listOf(
            "Jam ke-1", "Jam ke-2", "Jam ke-3", "Jam ke-4", "Jam ke-5",
            "Jam ke-6", "Jam ke-7", "Jam ke-8", "Jam ke-9", "Jam ke-10"
        )

        ExposedDropdownMenuBox(
            expanded = expandedJam,
            onExpandedChange = { expandedJam = !expandedJam },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 24.dp)
        ) {
            OutlinedTextField(
                value = formState.jamKe,
                onValueChange = { formState.jamKe = it },
                readOnly = true,
                label = { Text("Jam Ke") },
                placeholder = { Text("Pilih jam pelajaran") },
                leadingIcon = {
                    Icon(
                        Icons.Default.Schedule,
                        contentDescription = "Jam",
                        tint = MaterialTheme.colorScheme.primary
                    )
                },
                trailingIcon = {
                    ExposedDropdownMenuDefaults.TrailingIcon(expanded = expandedJam)
                },
                colors = ExposedDropdownMenuDefaults.outlinedTextFieldColors(),
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedJam,
                onDismissRequest = { expandedJam = false }
            ) {
                jamOptions.forEach { jam ->
                    DropdownMenuItem(
                        text = { Text(jam) },
                        onClick = {
                            formState.jamKe = jam
                            expandedJam = false
                        }
                    )
                }
            }        }        // Save Button with loading indicator
          Button(
            onClick = {
                when {
                    formState.selectedHari.isEmpty() -> {
                        dialogMessage = "Mohon pilih hari terlebih dahulu"
                        isSuccess = false
                        showDialog = true
                    }
                    formState.selectedKelas.isEmpty() -> {
                        dialogMessage = "Mohon pilih kelas terlebih dahulu"
                        isSuccess = false
                        showDialog = true
                    }
                    formState.selectedMataPelajaran.isEmpty() -> {
                        dialogMessage = "Mohon pilih mata pelajaran terlebih dahulu"
                        isSuccess = false
                        showDialog = true
                    }
                    formState.selectedNamaGuru.isEmpty() -> {
                        dialogMessage = "Mohon pilih guru pengajar terlebih dahulu"
                        isSuccess = false
                        showDialog = true
                    }
                    formState.jamKe.isEmpty() -> {
                        dialogMessage = "Mohon pilih jam pelajaran terlebih dahulu"
                        isSuccess = false
                        showDialog = true
                    }
                    else -> {
                        // Show loading indicator
                        isLoading = true

                        // Extract teacher code from the name (example: "Pak Budi Santoso" -> "BS001")
                        val nameParts = formState.selectedNamaGuru.split(" ")
                        val teacherCode = if (nameParts.size >= 2) {
                            (nameParts[0].first().toString() + nameParts[1].first().toString() + "001")
                                .uppercase()
                        } else {
                            "TCH001"
                        }                        // Get selected IDs from the API data
                        var selectedSubjectId: Int? = null
                        var selectedTeacherId: Int? = null
                        var selectedClassroomId: Int? = null
                          // Find selected subject ID
                        val currentSubjectsState = subjectsState
                        if (currentSubjectsState is ApiState.Success) {
                            selectedSubjectId = currentSubjectsState.data.find { it.name == formState.selectedMataPelajaran }?.id
                        }
                        
                        // Find selected teacher ID
                        val currentTeachersState = teachersState
                        if (currentTeachersState is ApiState.Success) {
                            selectedTeacherId = currentTeachersState.data.find { 
                                it.name == formState.selectedNamaGuru 
                            }?.id
                        }
                        
                        // Find selected classroom ID
                        val currentClassroomsState = classroomsState
                        if (currentClassroomsState is ApiState.Success) {
                            selectedClassroomId = currentClassroomsState.data.find { it.name == formState.selectedKelas }?.id
                        }                        // Create JsonObject for API request with proper data
                        val scheduleData = JsonObject().apply {
                            addProperty("day", formState.selectedHari)
                            addProperty("classroom_id", selectedClassroomId ?: 8) // Default to Lab1
                            addProperty("subject_id", selectedSubjectId ?: 3) // Default to Bahasa Inggris
                            addProperty("teacher_id", selectedTeacherId ?: 4) // Default to Adi Wijaya
                            
                            // Parse jam ke untuk mendapatkan waktu yang tepat
                            val periodNumber = formState.jamKe.replace("Jam ke-", "").toIntOrNull() ?: 1
                            addProperty("period_number", periodNumber)
                            
                            // Mapping jam ke ke waktu yang sesuai
                            val timeMapping = mapOf(
                                1 to Pair("07:00:00", "08:40:00"),
                                2 to Pair("08:40:00", "10:20:00"), 
                                3 to Pair("10:20:00", "12:00:00"),
                                4 to Pair("13:00:00", "14:40:00"),
                                5 to Pair("14:40:00", "16:20:00")
                            )
                            
                            val (startTime, endTime) = timeMapping[periodNumber] ?: Pair("07:00:00", "08:40:00")
                            addProperty("start_time", startTime)
                            addProperty("end_time", endTime)
                              // Tambahkan notes yang informatif
                            val teacherName = if (currentTeachersState is ApiState.Success) {
                                currentTeachersState.data.find { it.id == selectedTeacherId }?.name ?: "Guru"
                            } else "Guru"
                            
                            val subjectName = if (currentSubjectsState is ApiState.Success) {
                                currentSubjectsState.data.find { it.id == selectedSubjectId }?.name ?: "Mata Pelajaran"
                            } else "Mata Pelajaran"
                            
                            addProperty("notes", "Jadwal $subjectName dengan $teacherName - Periode $periodNumber pada hari ${formState.selectedHari}")
                        }// Send to API via ViewModel
                        viewModel.createSchedule(scheduleData)

                        // Reset loading state for now - the save status will be handled by the LaunchedEffect above
                        isLoading = false
                    }
                }
            },
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.primary,
                contentColor = Color.White
            ),
            enabled = !isLoading
        ) {
            if (isLoading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(24.dp),
                    color = Color.White,
                    strokeWidth = 2.dp
                )
            } else {
                Icon(
                    Icons.Default.Save,
                    contentDescription = "Save",
                    modifier = Modifier.padding(end = 8.dp)
                )
                Text(
                    text = "Simpan Jadwal",
                    style = MaterialTheme.typography.titleMedium
                )
            }        }

        Spacer(modifier = Modifier.height(32.dp))
        
        // Section untuk menampilkan data yang sudah di-entri
        Text(
            text = "Data Jadwal yang Sudah Di-Entri",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 16.dp)
        )        // Dialog states for confirmation
        var showDeleteDialog by remember { mutableStateOf(false) }
        var scheduleToDelete by remember { mutableStateOf<ScheduleApi?>(null) }
        var scheduleToEdit by remember { mutableStateOf<ScheduleApi?>(null) }

        // Show edit dialog if we have a schedule to edit
        scheduleToEdit?.let { schedule ->
            // This is a placeholder for the edit functionality
            // In a real app, you'd show a dialog or navigate to an edit screen
            AlertDialog(
                onDismissRequest = { scheduleToEdit = null },
                title = { Text("Edit Jadwal") },
                text = { Text("Fitur edit jadwal akan segera hadir.") },
                confirmButton = {
                    FilledTonalButton(onClick = { scheduleToEdit = null }) {
                        Text("OK")
                    }
                }
            )
        }

        // Confirmation dialog for deletion
        if (showDeleteDialog && scheduleToDelete != null) {
            AlertDialog(
                onDismissRequest = {
                    showDeleteDialog = false
                    scheduleToDelete = null
                },
                title = { Text("Konfirmasi Hapus") },
                icon = { Icon(Icons.Default.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error) },
                text = { Text("Apakah Anda yakin ingin menghapus jadwal ini?") },
                confirmButton = {
                    FilledTonalButton(
                        onClick = {
                            scheduleToDelete?.let { viewModel.deleteSchedule(it.id) }
                            showDeleteDialog = false
                            scheduleToDelete = null
                        },
                        colors = ButtonDefaults.filledTonalButtonColors(
                            containerColor = MaterialTheme.colorScheme.error,
                            contentColor = MaterialTheme.colorScheme.onError
                        )
                    ) {
                        Text("Hapus")
                    }
                },
                dismissButton = {
                    OutlinedButton(onClick = {
                        showDeleteDialog = false
                        scheduleToDelete = null
                    }) {
                        Text("Batal")
                    }
                }
            )
        }

        if (schedules.isEmpty()) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant
                )
            ) {
                Text(
                    text = "Belum ada data jadwal yang di-entri",
                    style = MaterialTheme.typography.bodyLarge,
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                    modifier = Modifier.padding(24.dp),
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        } else {
            // LazyColumn untuk cards yang scrollable
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                contentPadding = PaddingValues(bottom = 16.dp)
            ) {                items(schedules) { schedule ->
                    JadwalEntriCard(
                        schedule = schedule,
                        onEdit = { scheduleToEdit = it },
                        onDelete = {
                            scheduleToDelete = it
                            showDeleteDialog = true
                        }
                    )
                }            }
        }
    }

    // Dialog untuk notifikasi
    if (showDialog) {
        AlertDialog(
            onDismissRequest = { showDialog = false },
            title = {
                Text(
                    if (isSuccess) "Sukses" else "Peringatan"
                )
            },
            icon = {
                Icon(
                    if (isSuccess) Icons.Default.CheckCircle else Icons.Default.Warning,
                    contentDescription = null,
                    tint = if (isSuccess) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error
                )
            },
            text = {
                Text(dialogMessage)
            },
            confirmButton = {
                FilledTonalButton(onClick = { showDialog = false }) {
                    Text("OK")
                }
            }
        )
    }
}

@Composable
@Suppress("UNUSED_PARAMETER")
fun JadwalEntriCard(unusedData: JadwalData) {
    // This function is kept for backward compatibility but parameter is unused
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        JadwalCardContent(
            hari = "Senin", // Default values since parameter is unused
            kelas = "X RPL",
            mataPelajaran = "Sample",
            namaGuru = "Default Teacher",
            jamKe = "Jam ke-1",
            onEdit = { /* Not implemented */ },
            onDelete = { /* Not implemented */ }
        )
    }
}

@Composable
fun JadwalEntriCard(schedule: ScheduleApi, onEdit: (ScheduleApi) -> Unit, onDelete: (ScheduleApi) -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        )
    ) {        JadwalCardContent(            hari = schedule.dayOfWeek,
            kelas = schedule.className ?: "Unknown Class",
            mataPelajaran = schedule.subjectName ?: "Unknown Subject",
            namaGuru = schedule.teacherName ?: "Unknown Teacher",
            jamKe = "Jam ke-${schedule.period}",
            onEdit = { onEdit(schedule) },
            onDelete = { onDelete(schedule) }
        )
    }
}

@Composable
fun JadwalCardContent(
    hari: String,
    kelas: String,
    mataPelajaran: String,
    namaGuru: String,
    jamKe: String,
    onEdit: () -> Unit,
    onDelete: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Header dengan hari dan kelas
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 12.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = hari,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = kelas,
                    style = MaterialTheme.typography.titleMedium,
                    color = MaterialTheme.colorScheme.secondary
                )
            }

            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.tertiaryContainer
                )
            ) {
                Text(
                    text = jamKe,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                    color = MaterialTheme.colorScheme.onTertiaryContainer
                )
            }
        }

        HorizontalDivider(
            modifier = Modifier.padding(vertical = 8.dp),
            color = MaterialTheme.colorScheme.outlineVariant
        )

        // Mata Pelajaran
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Default.School,
                contentDescription = "Mata Pelajaran",
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier
                    .size(20.dp)
                    .padding(end = 8.dp)
            )
            Text(
                text = "Mata Pelajaran: $mataPelajaran",
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
        }

        // Nama Guru
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Default.Person,
                contentDescription = "Nama Guru",
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier
                    .size(20.dp)
                    .padding(end = 8.dp)
            )
            Text(
                text = "Guru Pengajar: $namaGuru",
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
        }

        // Action buttons
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 12.dp),
            horizontalArrangement = Arrangement.End
        ) {
            FilledTonalButton(
                onClick = onEdit,
                colors = ButtonDefaults.filledTonalButtonColors(
                    containerColor = MaterialTheme.colorScheme.secondaryContainer
                ),
                modifier = Modifier.height(36.dp)
            ) {
                Icon(
                    Icons.Default.Edit,
                    contentDescription = "Edit",
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text("Edit")
            }

            Spacer(modifier = Modifier.width(8.dp))

            OutlinedButton(
                onClick = onDelete,
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = MaterialTheme.colorScheme.error
                ),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.error),
                modifier = Modifier.height(36.dp)
            ) {
                Icon(
                    Icons.Default.Delete,
                    contentDescription = "Delete",
                    modifier = Modifier.size(16.dp),
                    tint = MaterialTheme.colorScheme.error
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text("Hapus")
            }
        }
    }
}

// Preview untuk testing
@androidx.compose.ui.tooling.preview.Preview(showBackground = true)
@Composable
fun EntriJadwalPreview() {
    MaterialTheme {
        EntriJadwal()
    }
}


