package com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kepalasekolah

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.unit.dp
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KepalaSekolahViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KepsekAttendanceListUiState

/**
 * KepsekAttendanceListScreen - Detailed attendance list for Kepala Sekolah
 * Shows all attendance records with filters
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KepsekAttendanceListScreen(viewModel: KepalaSekolahViewModel) {
    val attendanceState by viewModel.attendanceListState.collectAsState()
    val selectedStatus by viewModel.selectedStatus.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.loadAttendanceList()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Status Filter Chips
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            FilterChip(
                onClick = { viewModel.setSelectedStatus(null) },
                label = { Text("Semua") },
                selected = selectedStatus == null
            )
            FilterChip(
                onClick = { viewModel.setSelectedStatus("hadir") },
                label = { Text("Hadir") },
                selected = selectedStatus == "hadir",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = Color(0xFF4CAF50).copy(alpha = 0.2f)
                )
            )
            FilterChip(
                onClick = { viewModel.setSelectedStatus("telat") },
                label = { Text("Telat") },
                selected = selectedStatus == "telat",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = Color(0xFFFF9800).copy(alpha = 0.2f)
                )
            )
            FilterChip(
                onClick = { viewModel.setSelectedStatus("tidak_hadir") },
                label = { Text("Tidak Hadir") },
                selected = selectedStatus == "tidak_hadir",
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = Color(0xFFF44336).copy(alpha = 0.2f)
                )
            )
        }

        when (val state = attendanceState) {
            is KepsekAttendanceListUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }

            is KepsekAttendanceListUiState.Error -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            Icons.Default.Error,
                            contentDescription = "Error",
                            tint = MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(48.dp)
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = state.message,
                            color = MaterialTheme.colorScheme.error,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadAttendanceList() }) {
                            Text("Coba Lagi")
                        }
                    }
                }
            }

            is KepsekAttendanceListUiState.Success -> {
                val data = state.data

                // Summary
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 16.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceEvenly
                    ) {
                        SummaryItem(count = data.summary.hadir, label = "Hadir", color = Color(0xFF4CAF50))
                        SummaryItem(count = data.summary.telat, label = "Telat", color = Color(0xFFFF9800))
                        SummaryItem(count = data.summary.tidakHadir, label = "Tidak Hadir", color = Color(0xFFF44336))
                        SummaryItem(count = data.summary.izin, label = "Izin", color = Color(0xFF2196F3))
                    }
                }

                if (data.attendances.isEmpty()) {
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "Tidak ada data kehadiran",
                            style = MaterialTheme.typography.bodyLarge,
                            color = Color.Gray
                        )
                    }
                } else {
                    LazyColumn(
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(data.attendances) { attendance ->
                            AttendanceCard(attendance = attendance)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SummaryItem(count: Int, label: String, color: Color) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            text = count.toString(),
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = color
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall
        )
    }
}

@Composable
private fun AttendanceCard(attendance: AttendanceItem) {
    val statusColor = when (attendance.status) {
        "hadir" -> Color(0xFF4CAF50)
        "telat" -> Color(0xFFFF9800)
        "tidak_hadir" -> Color(0xFFF44336)
        "izin" -> Color(0xFF2196F3)
        "diganti" -> Color(0xFF9C27B0)
        else -> Color.Gray
    }

    Card(
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = attendance.teacherName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${attendance.subjectName} - ${attendance.className}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
                Text(
                    text = "${attendance.day}, ${attendance.date}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }

            Surface(
                color = statusColor.copy(alpha = 0.1f),
                shape = RoundedCornerShape(8.dp)
            ) {
                Text(
                    text = attendance.status.replace("_", " ").replaceFirstChar { it.uppercase() },
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                    style = MaterialTheme.typography.labelMedium,
                    color = statusColor,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}
