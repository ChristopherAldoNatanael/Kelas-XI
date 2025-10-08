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
import com.christopheraldoo.aplikasimonitoringkelas.data.Schedule
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.ScheduleViewModel
import com.christopheraldoo.aplikasimonitoringkelas.viewmodel.SaveResult
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

    // State untuk menyimpan data yang sudah di-entri
    var showDialog by remember { mutableStateOf(false) }
    var isSuccess by remember { mutableStateOf(false) }
    var dialogMessage by remember { mutableStateOf("") }
    // Observe save status from viewModel
    val saveStatus by viewModel.saveStatus.collectAsStateWithLifecycle()

    // Observe schedules from viewModel
    val schedules by viewModel.allSchedules.observeAsState(initial = emptyList())

    // Convert schedules to savedJadwal format for backward compatibility
    val savedJadwal = schedules.map { schedule ->
        JadwalData(
            hari = schedule.day,
            kelas = schedule.classRoom,
            mataPelajaran = schedule.subject,
            namaGuru = schedule.teacherName,
            jamKe = schedule.periodNumber
        )
    }
      // Effect to handle save status changes
    var isLoading by remember { mutableStateOf(false) }

    LaunchedEffect(saveStatus) {
        when (saveStatus) {
            is SaveResult.Success -> {
                isLoading = false
                dialogMessage = "Jadwal berhasil disimpan"
                isSuccess = true
                showDialog = true
            }
            is SaveResult.Error -> {
                isLoading = false
                dialogMessage = "Gagal menyimpan jadwal: ${(saveStatus as SaveResult.Error).message}"
                isSuccess = false
                showDialog = true
            }
            else -> {} // Do nothing for Initial state
        }
    }

    // Options untuk spinners
    val hariOptions = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu")
    val kelasOptions = listOf("X RPL", "XI RPL", "XII RPL")
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
        var expandedMapel by remember { mutableStateOf(false) }
        val mataPelajaranOptions = listOf(
            "Matematika", "Bahasa Indonesia", "Bahasa Inggris", "Fisika",
            "Biologi", "Kimia", "Sejarah", "Ekonomi", "Geografi",
            "Kewarganegaraan", "Pemrograman Dasar", "Basis Data",
            "Pemrograman Web", "Pemrograman Mobile", "Rekayasa Perangkat Lunak"
        )

        ExposedDropdownMenuBox(
            expanded = expandedMapel,
            onExpandedChange = { expandedMapel = !expandedMapel },
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp)
        ) {
            OutlinedTextField(
                value = formState.selectedMataPelajaran,
                onValueChange = { formState.selectedMataPelajaran = it },
                label = { Text("Mata Pelajaran") },
                placeholder = { Text("Ketik nama mata pelajaran atau pilih dari list") },
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
                mataPelajaranOptions.forEach { mapel ->
                    DropdownMenuItem(
                        text = { Text(mapel) },
                        onClick = {
                            formState.selectedMataPelajaran = mapel
                            expandedMapel = false
                        }
                    )
                }
            }
        }        // Dropdown Nama Guru
        var expandedGuru by remember { mutableStateOf(false) }
        val namaGuruOptions = listOf(
            "Pak Budi Santoso", "Ibu Siti Nurhaliza", "Pak Adi Wijaya",
            "Ibu Maya Sari", "Mr. John Smith", "Ibu Rina Agustina",
            "Pak Doni Ramadhan", "Pak Eko Prasetyo", "Pak Ahmad Fauzi",
            "Ibu Lisa Permata", "Pak Hendra Gunawan", "Ibu Diana Putri"
        )

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
                label = { Text("Nama Guru") },
                placeholder = { Text("Pilih nama guru pengajar") },
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
                modifier = Modifier
                    .fillMaxWidth()
                    .menuAnchor()
            )

            ExposedDropdownMenu(
                expanded = expandedGuru,
                onDismissRequest = { expandedGuru = false }
            ) {
                namaGuruOptions.forEach { guru ->
                    DropdownMenuItem(
                        text = { Text(guru) },
                        onClick = {
                            formState.selectedNamaGuru = guru
                            expandedGuru = false
                        }
                    )
                }
            }
        }        // Dropdown untuk Jam Ke
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
                        }

                        // Create Schedule entity
                        val newSchedule = Schedule(
                            day = formState.selectedHari,
                            classRoom = formState.selectedKelas,
                            subject = formState.selectedMataPelajaran,
                            teacherName = formState.selectedNamaGuru,
                            teacherCode = teacherCode,
                            periodNumber = formState.jamKe
                        )

                        // Reset loading state for now - the save status will be handled by the LaunchedEffect above
                        isLoading = false

                        // Save to database using viewModel
                        viewModel.insertSchedule(newSchedule)
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
            }
        }

        Spacer(modifier = Modifier.height(32.dp))        // Section untuk menampilkan data yang sudah di-entri
        Text(
            text = "Data Jadwal yang Sudah Di-Entri",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(bottom = 16.dp)
        )

        // Dialog states for confirmation
        var showDeleteDialog by remember { mutableStateOf(false) }
        var scheduleToDelete by remember { mutableStateOf<Schedule?>(null) }
        var scheduleToEdit by remember { mutableStateOf<Schedule?>(null) }

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
                            scheduleToDelete?.let { viewModel.deleteSchedule(it) }
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
                }
            }
        }
    }    // Dialog untuk notifikasi
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
fun JadwalEntriCard(jadwalData: JadwalData) {
    // This function is kept for backward compatibility
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        JadwalCardContent(
            hari = jadwalData.hari,
            kelas = jadwalData.kelas,
            mataPelajaran = jadwalData.mataPelajaran,
            namaGuru = jadwalData.namaGuru,
            jamKe = jadwalData.jamKe,
            onEdit = { /* Not implemented */ },
            onDelete = { /* Not implemented */ }
        )
    }
}

@Composable
fun JadwalEntriCard(schedule: Schedule, onEdit: (Schedule) -> Unit, onDelete: (Schedule) -> Unit) {    Card(
        modifier = Modifier
            .fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        )
    ) {
        JadwalCardContent(
            hari = schedule.day,
            kelas = schedule.classRoom,
            mataPelajaran = schedule.subject,
            namaGuru = schedule.teacherName,
            jamKe = schedule.periodNumber,
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
