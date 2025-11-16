package com.christopheraldoo.aplikasimonitoringkelas.ui.screens

import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleItem
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SubmitKehadiranUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.TodayKehadiranUiState
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KehadiranScreen(viewModel: SiswaViewModel) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // Observe state from ViewModel
    val uiState by viewModel.todayKehadiranState.collectAsState()
    val submitState by viewModel.submitKehadiranState.collectAsState()

    // Load data when the screen is first launched
    LaunchedEffect(Unit) {
        viewModel.loadTodayKehadiranStatus()
    }

    // Handle submission success/error messages
    LaunchedEffect(submitState) {
        when (val state = submitState) {
            is SubmitKehadiranUiState.Success -> {
                Toast.makeText(context, state.message, Toast.LENGTH_SHORT).show()
                viewModel.resetSubmitState() // Reset state after showing message
            }
            is SubmitKehadiranUiState.Error -> {
                Toast.makeText(context, state.message, Toast.LENGTH_SHORT).show()
                viewModel.resetSubmitState()
            }
            else -> { /* Idle or Loading */ }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Kehadiran Guru Hari Ini") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = Color.White
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
        ) {
            when (val state = uiState) {
                is TodayKehadiranUiState.Loading -> {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            CircularProgressIndicator()
                            Spacer(modifier = Modifier.height(16.dp))
                            Text("Memuat jadwal hari ini...")
                        }
                    }
                }
                is TodayKehadiranUiState.Success -> {
                    val response = state.data
                    Text(
                        text = "Tanggal: ${response.tanggal}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(bottom = 16.dp)
                    )
                    if (response.schedules.isEmpty()) {
                        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text("Tidak ada jadwal untuk hari ini.")
                        }
                    } else {
                        LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            items(response.schedules) { schedule ->
                                KehadiranScheduleCard(
                                    schedule = schedule,
                                    onSubmit = { guruHadir, catatan ->
                                        viewModel.submitKehadiran(schedule.scheduleId, guruHadir, catatan)
                                    }
                                )
                            }
                        }
                    }
                }
                is TodayKehadiranUiState.Error -> {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(state.message)
                    }
                }
            }
        }
    }
}

@Composable
fun KehadiranScheduleCard(schedule: ScheduleItem, onSubmit: (Boolean, String) -> Unit) {
    var guruHadir by remember { mutableStateOf(schedule.guruHadir ?: true) }
    var catatan by remember { mutableStateOf(schedule.catatan ?: "") }
    var showDialog by remember { mutableStateOf(false) }

    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (schedule.submitted) Color(0xFFE8F5E9) else MaterialTheme.colorScheme.surface
        )
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "Periode ${schedule.period}",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = schedule.time,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.primary
                )
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text(
                text = schedule.subject,
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = "Guru: ${schedule.teacher}",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray
            )

            Spacer(modifier = Modifier.height(12.dp))

            if (schedule.submitted) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = if (schedule.guruHadir == true) 
                            Icons.Default.CheckCircle else Icons.Default.Cancel,
                        contentDescription = null,
                        tint = if (schedule.guruHadir == true) 
                            Color(0xFF4CAF50) else Color(0xFFFF5252)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = if (schedule.guruHadir == true) "Guru Hadir" else "Guru Tidak Hadir",
                        fontWeight = FontWeight.Medium
                    )
                }
                
                if (!schedule.catatan.isNullOrEmpty()) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = "Catatan: ${schedule.catatan}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.Gray
                    )
                }
            } else {
                Button(
                    onClick = { showDialog = true },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Isi Kehadiran")
                }
            }
        }
    }

    if (showDialog) {
        AlertDialog(
            onDismissRequest = { showDialog = false },
            title = { Text("Isi Kehadiran") },
            text = {
                Column {
                    Text("${schedule.subject} - ${schedule.teacher}")
                    Spacer(modifier = Modifier.height(16.dp))

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(selected = guruHadir, onClick = { guruHadir = true })
                        Text("Guru Hadir", modifier = Modifier.padding(start = 8.dp))
                    }

                    Row(verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(selected = !guruHadir, onClick = { guruHadir = false })
                        Text("Guru Tidak Hadir", modifier = Modifier.padding(start = 8.dp))
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    OutlinedTextField(
                        value = catatan,
                        onValueChange = { catatan = it },
                        label = { Text("Catatan (opsional)") },
                        modifier = Modifier.fillMaxWidth(),
                        maxLines = 3
                    )
                }
            },
            confirmButton = {
                Button(onClick = {
                    onSubmit(guruHadir, catatan)
                    showDialog = false
                }) {
                    Text("Simpan")
                }
            },
            dismissButton = {
                TextButton(onClick = { showDialog = false }) {
                    Text("Batal")
                }
            }
        )
    }
}
