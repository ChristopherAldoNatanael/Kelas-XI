package com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.ClassManagementUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SubstituteUiState

/**
 * Class Management Screen for Kurikulum
 * Sort classes by teacher status, assign substitute teachers
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumClassManagementScreen(
    viewModel: KurikulumViewModel
) {
    val classManagementState by viewModel.classManagementState.collectAsState()
    val substituteState by viewModel.substituteState.collectAsState()
    val selectedStatus by viewModel.selectedStatus.collectAsState()
    
    var selectedClassItem by remember { mutableStateOf<ClassScheduleItem?>(null) }
    var showSubstituteDialog by remember { mutableStateOf(false) }
    var showStudentsDialog by remember { mutableStateOf(false) }
    
    // Status filters
    val statusFilters = listOf(
        null to "Semua",
        "hadir" to "Hadir",
        "telat" to "Telat",
        "tidak_hadir" to "Tidak Hadir",
        "pending" to "Pending"
    )
    
    LaunchedEffect(Unit) {
        viewModel.loadClassManagement()
        viewModel.startAutoRefresh()
    }
    
    DisposableEffect(Unit) {
        onDispose {
            viewModel.stopAutoRefresh()
        }
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Header
        ClassManagementHeader(
            state = classManagementState
        )
        
        // Status filter chips
        LazyRow(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(statusFilters) { (status, label) ->
                FilterChip(
                    selected = selectedStatus == status,
                    onClick = {
                        viewModel.setSelectedStatus(status)
                        viewModel.loadClassManagement(status)
                    },
                    label = { Text(label) },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = when (status) {
                            "hadir" -> Color(0xFF4CAF50)
                            "telat" -> Color(0xFFFFC107)
                            "tidak_hadir" -> Color(0xFFF44336)
                            "pending" -> Color(0xFF9E9E9E)
                            else -> MaterialTheme.colorScheme.primary
                        },
                        selectedLabelColor = Color.White
                    )
                )
            }
        }
        
        // Alert banner for classes without teacher > 15 minutes
        when (val state = classManagementState) {
            is ClassManagementUiState.Success -> {
                if (state.alertClasses.isNotEmpty()) {
                    AlertBanner(
                        alertCount = state.alertClasses.size,
                        onViewClick = {
                            viewModel.setSelectedStatus("tidak_hadir")
                            viewModel.loadClassManagement("tidak_hadir")
                        }
                    )
                }
            }
            else -> {}
        }
        
        // Content
        when (val state = classManagementState) {
            is ClassManagementUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }
            
            is ClassManagementUiState.Error -> {
                ErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadClassManagement(selectedStatus) }
                )
            }
            
            is ClassManagementUiState.Success -> {
                if (state.classes.isEmpty()) {
                    EmptyState(message = "Tidak ada kelas dengan status ini")
                } else {
                    ClassList(
                        classes = state.classes,                        onAssignSubstitute = { classItem ->
                            selectedClassItem = classItem
                            viewModel.loadAvailableSubstitutes(classItem.period ?: 0, classItem.subjectId ?: 0)
                            showSubstituteDialog = true
                        },
                        onViewStudents = { classItem ->
                            selectedClassItem = classItem
                            showStudentsDialog = true
                        }
                    )
                }
            }
        }
    }
    
    // Substitute Teacher Dialog
    if (showSubstituteDialog && selectedClassItem != null) {
        SubstituteTeacherDialog(
            classItem = selectedClassItem!!,
            substituteState = substituteState,
            onDismiss = {
                showSubstituteDialog = false
                viewModel.resetSubstituteState()
            },
            onAssign = { substituteTeacherId, keterangan ->
                viewModel.assignSubstitute(
                    selectedClassItem!!.scheduleId,
                    substituteTeacherId,
                    keterangan
                )
            }
        )
    }
    
    // Students Dialog
    if (showStudentsDialog && selectedClassItem != null) {
        StudentsDialog(
            classItem = selectedClassItem!!,
            onDismiss = { showStudentsDialog = false }
        )
    }
    
    // Show success message when substitute is assigned
    LaunchedEffect(substituteState) {
        if (substituteState is SubstituteUiState.AssignSuccess) {
            showSubstituteDialog = false
            viewModel.resetSubstituteState()
        }
    }
}

@Composable
private fun ClassManagementHeader(
    state: ClassManagementUiState
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.primaryContainer,
        tonalElevation = 2.dp
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Text(
                text = "Manajemen Kelas",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onPrimaryContainer
            )
            
            when (state) {
                is ClassManagementUiState.Success -> {
                    Text(
                        text = "${state.day}, ${state.date} • ${state.currentTime}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.7f)
                    )
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    // Status counts
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        StatusCountChip(
                            modifier = Modifier.weight(1f),
                            count = state.statusCounts.hadir,
                            label = "Hadir",
                            color = Color(0xFF4CAF50)
                        )
                        StatusCountChip(
                            modifier = Modifier.weight(1f),
                            count = state.statusCounts.telat,
                            label = "Telat",
                            color = Color(0xFFFFC107)
                        )
                        StatusCountChip(
                            modifier = Modifier.weight(1f),
                            count = state.statusCounts.tidakHadir,
                            label = "Absen",
                            color = Color(0xFFF44336)
                        )
                        StatusCountChip(
                            modifier = Modifier.weight(1f),
                            count = state.statusCounts.pending,
                            label = "Pending",
                            color = Color(0xFF9E9E9E)
                        )
                    }
                }
                else -> {}
            }
        }
    }
}

@Composable
private fun StatusCountChip(
    modifier: Modifier = Modifier,
    count: Int,
    label: String,
    color: Color
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(8.dp),
        color = color.copy(alpha = 0.15f)
    ) {
        Column(
            modifier = Modifier.padding(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = count.toString(),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = color
            )
        }
    }
}

@Composable
private fun AlertBanner(
    alertCount: Int,
    onViewClick: () -> Unit
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        shape = RoundedCornerShape(8.dp),
        color = Color(0xFFF44336).copy(alpha = 0.1f),
        border = ButtonDefaults.outlinedButtonBorder
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Default.Warning,
                contentDescription = null,
                tint = Color(0xFFF44336),
                modifier = Modifier.size(24.dp)
            )
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Peringatan!",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = Color(0xFFF44336)
                )
                Text(
                    text = "$alertCount kelas tanpa guru >15 menit",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color(0xFFF44336).copy(alpha = 0.8f)
                )
            }
            
            TextButton(onClick = onViewClick) {
                Text("Lihat", color = Color(0xFFF44336))
            }
        }
    }
}

@Composable
private fun ClassList(
    classes: List<ClassScheduleItem>,
    onAssignSubstitute: (ClassScheduleItem) -> Unit,
    onViewStudents: (ClassScheduleItem) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(classes, key = { it.scheduleId }) { classItem ->
            ClassCard(
                classItem = classItem,
                onAssignSubstitute = { onAssignSubstitute(classItem) },
                onViewStudents = { onViewStudents(classItem) }
            )
        }
    }
}

@Composable
private fun ClassCard(
    classItem: ClassScheduleItem,
    onAssignSubstitute: () -> Unit,
    onViewStudents: () -> Unit
) {
    val statusColor = when (classItem.status ?: "pending") {
        "hadir" -> Color(0xFF4CAF50)
        "telat" -> Color(0xFFFFC107)
        "tidak_hadir" -> Color(0xFFF44336)
        "diganti" -> Color(0xFF2196F3)
        else -> Color(0xFF9E9E9E)
    }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(
            defaultElevation = if (classItem.noTeacherAlert) 4.dp else 2.dp
        ),
        border = if (classItem.noTeacherAlert) {
            ButtonDefaults.outlinedButtonBorder.copy(
                brush = androidx.compose.ui.graphics.SolidColor(Color(0xFFF44336))
            )
        } else null
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    // Class name with current period indicator
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = classItem.className,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        
                        if (classItem.isCurrentPeriod) {
                            Spacer(modifier = Modifier.width(8.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = MaterialTheme.colorScheme.primary
                            ) {
                                Text(
                                    text = "SEKARANG",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = MaterialTheme.colorScheme.onPrimary,
                                    modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                                )
                            }
                        }
                    }
                    
                    // Subject
                    Text(
                        text = classItem.subjectName,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    
                    // Teacher
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.Person,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = classItem.teacherName,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    
                    // Time
                    if (classItem.startTime != null && classItem.endTime != null) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically
                        ) {                            Icon(
                                imageVector = Icons.Default.Schedule,
                                contentDescription = null,
                                modifier = Modifier.size(16.dp),
                                tint = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = "Jam ${classItem.period ?: "-"}: ${classItem.startTime ?: "-"} - ${classItem.endTime ?: "-"}",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                }
                
                // Status indicator
                Column(
                    horizontalAlignment = Alignment.End
                ) {
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = statusColor.copy(alpha = 0.15f)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {                            Box(
                                modifier = Modifier
                                    .size(10.dp)
                                    .clip(CircleShape)
                                    .background(statusColor)
                            )
                            Text(
                                text = when (classItem.status ?: "pending") {
                                    "hadir" -> "Hadir"
                                    "telat" -> "Telat"
                                    "tidak_hadir" -> "Tidak Hadir"
                                    "diganti" -> "Diganti"
                                    else -> "Pending"
                                },
                                style = MaterialTheme.typography.labelMedium,
                                fontWeight = FontWeight.Medium,
                                color = statusColor
                            )
                        }
                    }
                    
                    // Late minutes
                    if (classItem.lateMinutes != null && classItem.lateMinutes > 0) {
                        Text(
                            text = "Telat ${classItem.lateMinutes} menit",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color(0xFFFFC107),
                            modifier = Modifier.padding(top = 4.dp)
                        )
                    }
                    
                    // Substitute teacher info
                    if (classItem.substituteTeacherName != null) {
                        Text(
                            text = "→ ${classItem.substituteTeacherName}",
                            style = MaterialTheme.typography.labelSmall,
                            color = Color(0xFF2196F3),
                            modifier = Modifier.padding(top = 4.dp)
                        )
                    }
                }
            }
            
            // Keterangan if any
            if (!classItem.keterangan.isNullOrEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(4.dp),
                    color = MaterialTheme.colorScheme.surfaceVariant
                ) {
                    Text(
                        text = classItem.keterangan,
                        style = MaterialTheme.typography.bodySmall,
                        modifier = Modifier.padding(8.dp)
                    )
                }
            }
              // Action buttons for classes without teacher
            val currentStatus = classItem.status ?: "pending"
            if (currentStatus == "tidak_hadir" || currentStatus == "pending") {
                Spacer(modifier = Modifier.height(12.dp))
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = onViewStudents,
                        modifier = Modifier.weight(1f)
                    ) {
                        Icon(
                            imageVector = Icons.Default.People,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Lihat Siswa")
                    }
                    
                    Button(
                        onClick = onAssignSubstitute,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color(0xFF2196F3)
                        )
                    ) {
                        Icon(
                            imageVector = Icons.Default.PersonAdd,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Pilih Pengganti")
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SubstituteTeacherDialog(
    classItem: ClassScheduleItem,
    substituteState: SubstituteUiState,
    onDismiss: () -> Unit,
    onAssign: (Int, String?) -> Unit
) {
    var selectedTeacherId by remember { mutableStateOf<Int?>(null) }
    var keterangan by remember { mutableStateOf("") }
    
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Column {
                Text("Pilih Guru Pengganti")
                Text(
                    text = "${classItem.className} - ${classItem.subjectName}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        },
        text = {
            Column {
                when (substituteState) {
                    is SubstituteUiState.Loading -> {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(200.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            CircularProgressIndicator()
                        }
                    }
                    
                    is SubstituteUiState.Success -> {
                        if (substituteState.teachers.isEmpty()) {
                            Text(
                                text = "Tidak ada guru yang tersedia untuk jam ini",
                                style = MaterialTheme.typography.bodyMedium,
                                textAlign = TextAlign.Center,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(16.dp)
                            )
                        } else {
                            Text(
                                text = "Guru yang tersedia:",
                                style = MaterialTheme.typography.labelMedium,
                                modifier = Modifier.padding(bottom = 8.dp)
                            )
                            
                            LazyColumn(
                                modifier = Modifier.heightIn(max = 200.dp)
                            ) {
                                items(substituteState.teachers) { teacher ->
                                    Row(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clickable { selectedTeacherId = teacher.id }
                                            .padding(vertical = 8.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        RadioButton(
                                            selected = selectedTeacherId == teacher.id,
                                            onClick = { selectedTeacherId = teacher.id }
                                        )
                                        Column(modifier = Modifier.padding(start = 8.dp)) {
                                            Text(text = teacher.name)
                                            if (teacher.nip != null) {
                                                Text(
                                                    text = "NIP: ${teacher.nip}",
                                                    style = MaterialTheme.typography.bodySmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                            
                            Spacer(modifier = Modifier.height(12.dp))
                            
                            OutlinedTextField(
                                value = keterangan,
                                onValueChange = { keterangan = it },
                                label = { Text("Keterangan (opsional)") },
                                modifier = Modifier.fillMaxWidth(),
                                maxLines = 2
                            )
                        }
                    }
                    
                    is SubstituteUiState.Assigning -> {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(100.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                CircularProgressIndicator()
                                Spacer(modifier = Modifier.height(8.dp))
                                Text("Menugaskan guru pengganti...")
                            }
                        }
                    }
                    
                    is SubstituteUiState.Error -> {
                        Text(
                            text = substituteState.message,
                            color = MaterialTheme.colorScheme.error,
                            textAlign = TextAlign.Center,
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp)
                        )
                    }
                    
                    else -> {}
                }
            }
        },
        confirmButton = {
            val canAssign = substituteState is SubstituteUiState.Success && selectedTeacherId != null
            Button(
                onClick = {
                    selectedTeacherId?.let { id ->
                        onAssign(id, keterangan.ifEmpty { null })
                    }
                },
                enabled = canAssign
            ) {
                Text("Konfirmasi Pengganti")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Batal")
            }
        }
    )
}

@Composable
private fun StudentsDialog(
    classItem: ClassScheduleItem,
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Column {
                Text("Daftar Siswa")
                Text(
                    text = classItem.className,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        },
        text = {
            // TODO: Load students from API
            Column {
                Text(
                    text = "Fitur ini akan menampilkan daftar siswa di kelas ${classItem.className}",
                    style = MaterialTheme.typography.bodyMedium
                )
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text("Tutup")
            }
        }
    )
}

@Composable
private fun ErrorState(message: String, onRetry: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            imageVector = Icons.Default.Error,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.error
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            text = message,
            style = MaterialTheme.typography.bodyLarge,
            textAlign = TextAlign.Center
        )
        Spacer(modifier = Modifier.height(16.dp))
        Button(onClick = onRetry) {
            Text("Coba Lagi")
        }
    }
}

@Composable
private fun EmptyState(message: String) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            imageVector = Icons.Default.CheckCircle,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = Color(0xFF4CAF50)
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            text = message,
            style = MaterialTheme.typography.bodyLarge,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}
