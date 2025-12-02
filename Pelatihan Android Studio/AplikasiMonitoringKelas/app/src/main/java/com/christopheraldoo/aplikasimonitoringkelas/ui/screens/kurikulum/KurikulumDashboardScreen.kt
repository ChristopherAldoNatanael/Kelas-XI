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
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
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
import androidx.compose.ui.unit.sp
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.DashboardUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import kotlinx.coroutines.delay

/**
 * Kurikulum Dashboard Screen
 * Shows complete schedule with real-time teacher attendance status
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumDashboardScreen(
    viewModel: KurikulumViewModel,
    onClassClick: (ClassScheduleItem) -> Unit = {}
) {
    val dashboardState by viewModel.dashboardState.collectAsState()
    val selectedDay by viewModel.selectedDay.collectAsState()
    val filterClasses by viewModel.filterClasses.collectAsState()
    val selectedClassId by viewModel.selectedClassId.collectAsState()
    val weekOffset by viewModel.weekOffset.collectAsState()
    
    var showFilterDialog by remember { mutableStateOf(false) }
    var selectedFilterClass by remember { mutableStateOf<Int?>(null) }
    
    // Days of the week
    val days = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")
    
    // Get current day of week from device
    val currentDayOfWeek = remember {
        val calendar = java.util.Calendar.getInstance()
        when (calendar.get(java.util.Calendar.DAY_OF_WEEK)) {
            java.util.Calendar.MONDAY -> "Senin"
            java.util.Calendar.TUESDAY -> "Selasa"
            java.util.Calendar.WEDNESDAY -> "Rabu"
            java.util.Calendar.THURSDAY -> "Kamis"
            java.util.Calendar.FRIDAY -> "Jumat"
            java.util.Calendar.SATURDAY -> "Sabtu"
            java.util.Calendar.SUNDAY -> "Minggu"
            else -> "Senin"
        }
    }
      // Get current day number (1=Monday...7=Sunday)
    val currentDayNumber = remember {
        val calendar = java.util.Calendar.getInstance()
        val dow = calendar.get(java.util.Calendar.DAY_OF_WEEK)
        // Convert Java's Sunday=1...Saturday=7 to Monday=1...Sunday=7
        if (dow == java.util.Calendar.SUNDAY) 7 else dow - 1
    }
    
    // Show ALL days (Senin-Sabtu) for kurikulum to see full schedule
    // The backend will mark future dates appropriately
    val availableDays = days
    
    // Effective selected day (use current day if none selected)
    val effectiveDay = selectedDay ?: currentDayOfWeek
    
    // Load initial data with current day
    LaunchedEffect(Unit) {
        viewModel.setSelectedDay(currentDayOfWeek)
        viewModel.loadDashboard(day = currentDayOfWeek)
        viewModel.loadFilterData()
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
        // Header with stats
        when (val state = dashboardState) {
            is DashboardUiState.Success -> {
                DashboardHeader(
                    date = state.date,
                    day = state.day,
                    stats = state.stats,
                    weekInfo = state.weekInfo,
                    isFutureDate = state.isFutureDate
                )
            }
            is DashboardUiState.RequiresClassFilter -> {
                DashboardHeader(
                    date = state.date,
                    day = state.day,
                    stats = DashboardStats(),
                    weekInfo = state.weekInfo
                )
            }
            else -> {
                DashboardHeader(
                    date = "",
                    day = effectiveDay,
                    stats = DashboardStats()
                )
            }
        }
        
        // Week selector
        WeekSelector(
            weekOffset = weekOffset,
            onWeekChange = { newOffset ->
                viewModel.setWeekOffset(newOffset)
                // Reset to first available day when changing week
                val dayToSelect = if (newOffset == 0) currentDayOfWeek else "Senin"
                viewModel.setSelectedDay(dayToSelect)
                viewModel.loadDashboard(day = dayToSelect, classId = selectedFilterClass ?: selectedClassId, forceRefresh = true)
            }
        )
        
        // Day filter chips
        LazyRow(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(availableDays) { day ->
                FilterChip(
                    selected = effectiveDay == day,
                    onClick = {
                        viewModel.setSelectedDay(day)
                        viewModel.loadDashboard(day = day, classId = selectedFilterClass ?: selectedClassId, forceRefresh = true)
                    },
                    label = { Text(day) },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = MaterialTheme.colorScheme.primary,
                        selectedLabelColor = MaterialTheme.colorScheme.onPrimary
                    )
                )
            }
            
            item {
                FilterChip(
                    selected = false,
                    onClick = { showFilterDialog = true },
                    label = { Text("Filter") },
                    leadingIcon = { Icon(Icons.Default.FilterList, "Filter") }
                )
            }
        }
        
        // Legend (only show when we have schedules)
        if (dashboardState is DashboardUiState.Success) {
            StatusLegend()
        }
        
        // Content
        when (val state = dashboardState) {
            is DashboardUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }
            
            is DashboardUiState.Error -> {
                ErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadDashboard() }
                )
            }
              is DashboardUiState.RequiresClassFilter -> {
                ClassSelectionScreen(
                    date = state.date,
                    day = effectiveDay,
                    availableClasses = state.availableClasses,
                    onClassSelected = { classId ->
                        selectedFilterClass = classId
                        viewModel.setSelectedClassId(classId)
                        viewModel.loadDashboard(day = effectiveDay, classId = classId)
                    }
                )
            }
            
            is DashboardUiState.Success -> {
                // Show selected class info and back button
                SelectedClassHeader(
                    selectedClassId = selectedFilterClass ?: selectedClassId,
                    availableClasses = filterClasses,
                    onClearFilter = {
                        selectedFilterClass = null
                        viewModel.setSelectedClassId(null)
                        viewModel.loadDashboard(day = effectiveDay, classId = null)
                    }
                )
                
                if (state.schedules.isEmpty()) {
                    EmptyState(message = "Tidak ada jadwal untuk hari ini")
                } else {
                    ScheduleGrid(
                        schedules = state.schedules,
                        groupedByClass = state.groupedByClass,
                        onItemClick = { schedule ->
                            // Convert to ClassScheduleItem for navigation
                        }
                    )
                }
            }
        }
    }
    
    // Filter Dialog
    if (showFilterDialog) {
        FilterDialog(
            classes = filterClasses,
            selectedClassId = selectedFilterClass,
            onClassSelected = { classId ->
                selectedFilterClass = classId
                viewModel.setSelectedClassId(classId)
            },
            onDismiss = { showFilterDialog = false },
            onApply = {
                showFilterDialog = false
                viewModel.loadDashboard(day = selectedDay, classId = selectedFilterClass)
            }
        )
    }
}

@Composable
private fun WeekSelector(
    weekOffset: Int,
    onWeekChange: (Int) -> Unit
) {
    val weekLabel = when (weekOffset) {
        0 -> "Minggu Ini"
        -1 -> "Minggu Lalu"
        else -> "Minggu ${kotlin.math.abs(weekOffset)} yang lalu"
    }
    
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp),
        color = MaterialTheme.colorScheme.surfaceVariant,
        shape = RoundedCornerShape(8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {            // Previous week button
            IconButton(
                onClick = { onWeekChange(weekOffset - 1) }
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.KeyboardArrowLeft,
                    contentDescription = "Minggu Sebelumnya"
                )
            }
            
            // Week label
            Text(
                text = weekLabel,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
            
            // Next week button (disabled if already current week)
            IconButton(
                onClick = { if (weekOffset < 0) onWeekChange(weekOffset + 1) },
                enabled = weekOffset < 0
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.KeyboardArrowRight,
                    contentDescription = "Minggu Berikutnya",
                    tint = if (weekOffset < 0) 
                        MaterialTheme.colorScheme.onSurfaceVariant 
                    else 
                        MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.3f)
                )
            }
        }
    }
}

@Composable
private fun DashboardHeader(
    date: String,
    day: String,
    stats: DashboardStats,
    weekInfo: WeekInfo? = null,
    isFutureDate: Boolean = false
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.primaryContainer,
        tonalElevation = 2.dp
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Dashboard Jadwal",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                    if (date.isNotEmpty()) {
                        Text(
                            text = "$day, $date",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.7f)
                        )
                    }
                    // Show week info
                    if (weekInfo != null) {
                        Text(
                            text = weekInfo.weekLabel,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.6f)
                        )
                    }
                    // Show future date warning
                    if (isFutureDate) {
                        Text(
                            text = "⚠️ Tanggal ini belum terjadi",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFFFF9800)
                        )
                    }
                }
                
                // Refresh indicator
                var isRefreshing by remember { mutableStateOf(false) }
                LaunchedEffect(Unit) {
                    while (true) {
                        delay(30000)
                        isRefreshing = true
                        delay(1000)
                        isRefreshing = false
                    }
                }
                
                if (isRefreshing) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(24.dp),
                        strokeWidth = 2.dp
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
              // Stats cards
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                StatCard(
                    modifier = Modifier.weight(1f),
                    count = stats.hadir,
                    label = "Hadir",
                    color = Color(0xFF4CAF50)
                )
                StatCard(
                    modifier = Modifier.weight(1f),
                    count = stats.telat,
                    label = "Telat",
                    color = Color(0xFFFFC107)
                )
                StatCard(
                    modifier = Modifier.weight(1f),
                    count = stats.tidakHadir,
                    label = "Tidak Hadir",
                    color = Color(0xFFF44336)
                )
                StatCard(
                    modifier = Modifier.weight(1f),
                    count = stats.izin,
                    label = "Izin",
                    color = Color(0xFF9C27B0)
                )
                StatCard(
                    modifier = Modifier.weight(1f),
                    count = stats.pending,
                    label = "Pending",
                    color = Color(0xFF9E9E9E)
                )
            }
        }
    }
}

@Composable
private fun StatCard(
    modifier: Modifier = Modifier,
    count: Int,
    label: String,
    color: Color
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = color.copy(alpha = 0.15f))
    ) {
        Column(
            modifier = Modifier
                .padding(8.dp)
                .fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = count.toString(),
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = color,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun StatusLegend() {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        LegendItem(color = Color(0xFF4CAF50), label = "Hadir")
        LegendItem(color = Color(0xFFFFC107), label = "Telat")
        LegendItem(color = Color(0xFFF44336), label = "Tidak Hadir")
        LegendItem(color = Color(0xFF9C27B0), label = "Izin")
        LegendItem(color = Color(0xFF9E9E9E), label = "Pending")
        LegendItem(color = Color(0xFF2196F3), label = "Diganti")
    }
}

@Composable
private fun LegendItem(color: Color, label: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Box(
            modifier = Modifier
                .size(12.dp)
                .clip(CircleShape)
                .background(color)
        )
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun ScheduleGrid(
    schedules: List<ScheduleOverview>,
    groupedByClass: Map<String, List<ScheduleOverview>>,
    onItemClick: (ScheduleOverview) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        groupedByClass.forEach { (className, classSchedules) ->
            item {
                ClassScheduleCard(
                    className = className,
                    schedules = classSchedules,
                    onItemClick = onItemClick
                )
            }
        }
    }
}

@Composable
private fun ClassScheduleCard(
    className: String,
    schedules: List<ScheduleOverview>,
    onItemClick: (ScheduleOverview) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(12.dp)
        ) {
            // Class header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = className,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                  // Status summary for this class
                val hadirCount = schedules.count { it.status == "hadir" }
                val telatCount = schedules.count { it.status == "telat" }
                val tidakHadirCount = schedules.count { it.status == "tidak_hadir" }
                val izinCount = schedules.count { it.status == "izin" || it.teacherOnLeave }
                
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (hadirCount > 0) {
                        StatusBadge(count = hadirCount, color = Color(0xFF4CAF50))
                    }
                    if (telatCount > 0) {
                        StatusBadge(count = telatCount, color = Color(0xFFFFC107))
                    }
                    if (tidakHadirCount > 0) {
                        StatusBadge(count = tidakHadirCount, color = Color(0xFFF44336))
                    }
                    if (izinCount > 0) {
                        StatusBadge(count = izinCount, color = Color(0xFF9C27B0))
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
              // Schedule items
            schedules.sortedBy { it.period ?: 0 }.forEach { schedule ->
                ScheduleItem(
                    schedule = schedule,
                    onClick = { onItemClick(schedule) }
                )
                if (schedule != schedules.last()) {
                    Divider(modifier = Modifier.padding(vertical = 4.dp))
                }
            }
        }
    }
}

@Composable
private fun StatusBadge(count: Int, color: Color) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(color.copy(alpha = 0.2f))
            .padding(horizontal = 6.dp, vertical = 2.dp)
    ) {
        Text(
            text = count.toString(),
            style = MaterialTheme.typography.labelSmall,
            color = color,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
private fun ScheduleItem(
    schedule: ScheduleOverview,
    onClick: () -> Unit
) {
    // Check if teacher is on leave
    val isTeacherOnLeave = schedule.teacherOnLeave || schedule.status == "izin"
    
    val statusColor = when {
        isTeacherOnLeave -> Color(0xFF9C27B0) // Purple for leave
        else -> when (schedule.statusColor ?: "gray") {
            "green" -> Color(0xFF4CAF50)
            "yellow" -> Color(0xFFFFC107)
            "red" -> Color(0xFFF44336)
            "blue" -> Color(0xFF2196F3)
            "purple" -> Color(0xFF9C27B0)
            else -> Color(0xFF9E9E9E)
        }
    }
    
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Period indicator
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(statusColor.copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center        ) {
            Text(
                text = (schedule.period ?: 0).toString(),
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold,
                color = statusColor
            )
        }
        
        Spacer(modifier = Modifier.width(12.dp))
        
        // Subject and teacher info
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = schedule.subjectName,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Medium,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = schedule.teacherName,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            
            // Time
            if (schedule.startTime != null && schedule.endTime != null) {
                Text(
                    text = "${schedule.startTime} - ${schedule.endTime}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        
        // Status indicator with details
        Column(
            horizontalAlignment = Alignment.End
        ) {
            // Status chip
            Surface(
                shape = RoundedCornerShape(16.dp),
                color = statusColor.copy(alpha = 0.15f)
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)                ) {
                    Box(
                        modifier = Modifier
                            .size(8.dp)
                            .clip(CircleShape)
                            .background(statusColor)
                    )
                    Text(
                        text = when {
                            isTeacherOnLeave -> "Izin"
                            else -> when (schedule.status ?: "pending") {
                                "hadir" -> "Hadir"
                                "telat" -> "Telat"
                                "tidak_hadir" -> "Tidak Hadir"
                                "diganti" -> "Diganti"
                                "izin" -> "Izin"
                                else -> "Pending"
                            }
                        },
                        style = MaterialTheme.typography.labelSmall,
                        color = statusColor,
                        fontWeight = FontWeight.Medium
                    )
                }
            }
            
            // Late minutes or substitute info
            if (schedule.lateMinutes != null && schedule.lateMinutes > 0) {
                Text(
                    text = "+${schedule.lateMinutes} menit",
                    style = MaterialTheme.typography.labelSmall,
                    color = Color(0xFFFFC107)
                )
            }
            
            // Show leave reason if teacher is on leave
            if (isTeacherOnLeave && schedule.leaveReason != null) {
                Text(
                    text = schedule.leaveReason,
                    style = MaterialTheme.typography.labelSmall,
                    color = Color(0xFF9C27B0),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
            
            if (schedule.substituteTeacher != null) {
                Text(
                    text = "→ ${schedule.substituteTeacher}",
                    style = MaterialTheme.typography.labelSmall,
                    color = if (isTeacherOnLeave) Color(0xFF9C27B0) else Color(0xFF2196F3),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

@Composable
private fun FilterDialog(
    classes: List<FilterClass>,
    selectedClassId: Int?,
    onClassSelected: (Int?) -> Unit,
    onDismiss: () -> Unit,
    onApply: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Filter Jadwal") },
        text = {
            Column {
                Text(
                    text = "Pilih Kelas",
                    style = MaterialTheme.typography.labelMedium,
                    modifier = Modifier.padding(bottom = 8.dp)
                )
                
                LazyColumn(
                    modifier = Modifier.heightIn(max = 300.dp)
                ) {
                    item {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onClassSelected(null) }
                                .padding(vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(
                                selected = selectedClassId == null,
                                onClick = { onClassSelected(null) }
                            )
                            Text(
                                text = "Semua Kelas",
                                modifier = Modifier.padding(start = 8.dp)
                            )
                        }
                    }
                      items(classes) { classItem ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onClassSelected(classItem.id) }
                                .padding(vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(
                                selected = selectedClassId == classItem.id,
                                onClick = { onClassSelected(classItem.id) }
                            )
                            Text(
                                text = classItem.displayName,
                                modifier = Modifier.padding(start = 8.dp)
                            )
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onApply) {
                Text("Terapkan")
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
            imageVector = Icons.Default.EventBusy,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.onSurfaceVariant
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

@Composable
private fun ClassSelectionScreen(
    date: String,
    day: String,
    availableClasses: List<AvailableClass>,
    onClassSelected: (Int) -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Header
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer
            )
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    imageVector = Icons.Default.Class,
                    contentDescription = null,
                    modifier = Modifier.size(48.dp),
                    tint = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Pilih Kelas",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "Pilih kelas untuk melihat jadwal dan status kehadiran guru",
                    style = MaterialTheme.typography.bodyMedium,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.7f)
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Class Grid
        LazyColumn(
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(availableClasses) { classItem ->
                ClassSelectionCard(
                    classItem = classItem,
                    onClick = { onClassSelected(classItem.id) }
                )
            }
        }
    }
}

@Composable
private fun ClassSelectionCard(
    classItem: AvailableClass,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Surface(
                    shape = CircleShape,
                    color = MaterialTheme.colorScheme.primary.copy(alpha = 0.15f),
                    modifier = Modifier.size(48.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Text(
                            text = classItem.level?.toString() ?: "?",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                }
                Column {
                    Text(
                        text = classItem.displayName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                    if (classItem.major != null) {
                        Text(
                            text = classItem.major,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = "Lihat Jadwal",
                tint = MaterialTheme.colorScheme.primary
            )
        }
    }
}

@Composable
private fun SelectedClassHeader(
    selectedClassId: Int?,
    availableClasses: List<FilterClass>,
    onClearFilter: () -> Unit
) {
    if (selectedClassId != null) {
        val className = availableClasses.find { it.id == selectedClassId }?.displayName ?: "Kelas"
        
        Surface(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            shape = RoundedCornerShape(8.dp),
            color = MaterialTheme.colorScheme.secondaryContainer
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Class,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.onSecondaryContainer
                    )
                    Text(
                        text = "Jadwal: $className",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSecondaryContainer
                    )
                }
                TextButton(
                    onClick = onClearFilter,
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = MaterialTheme.colorScheme.primary
                    )
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Ganti Kelas")
                }
            }
        }
        
        // Show legend after selecting class
        StatusLegend()
    }
}
