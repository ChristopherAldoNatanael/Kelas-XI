package com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.ClassManagementUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SubstituteUiState

// Color palette
private val RedAlert = Color(0xFFE53935)
private val OrangeWarning = Color(0xFFFF9800)
private val PurpleIzin = Color(0xFF9C27B0)
private val GrayPending = Color(0xFF78909C)
private val BlueSubstitute = Color(0xFF2196F3)
private val GreenSuccess = Color(0xFF43A047)

/**
 * Professional Class Management Screen for Kurikulum
 * Shows all classes grouped, filtered to only show teachers needing attention (tidak hadir / telat)
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumClassManagementScreen(
    viewModel: KurikulumViewModel
) {
    val classManagementState by viewModel.classManagementState.collectAsState()
    val substituteState by viewModel.substituteState.collectAsState()
    
    var selectedScheduleItem by remember { mutableStateOf<ClassScheduleItem?>(null) }
    var showSubstituteBottomSheet by remember { mutableStateOf(false) }
    
    LaunchedEffect(Unit) {
        viewModel.loadClassManagement()
        viewModel.startAutoRefresh()
    }
    
    DisposableEffect(Unit) {
        onDispose {
            viewModel.stopAutoRefresh()
        }
    }
    
    // Handle substitute success
    LaunchedEffect(substituteState) {
        if (substituteState is SubstituteUiState.AssignSuccess) {
            showSubstituteBottomSheet = false
            viewModel.resetSubstituteState()
            viewModel.loadClassManagement() // Refresh data
        }
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xFFF5F7FA))
    ) {
        when (val state = classManagementState) {
            is ClassManagementUiState.Loading -> {
                LoadingState()
            }
            
            is ClassManagementUiState.Error -> {
                ErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadClassManagement() }
                )
            }
              is ClassManagementUiState.Success -> {
                Column(modifier = Modifier.fillMaxSize()) {
                    // Header with summary
                    HeaderSection(
                        day = state.day,
                        date = state.date,
                        currentTime = state.currentTime,
                        summary = state.summary,
                        statusCounts = state.statusCounts
                    )
                    
                    // Content
                    if (state.groupedByClass.isEmpty() && state.presentTeachersByPeriod.isEmpty()) {
                        EmptyState()
                    } else {
                        ClassGroupList(
                            groupedClasses = state.groupedByClass,
                            presentTeachersByPeriod = state.presentTeachersByPeriod,
                            onAssignSubstitute = { scheduleItem ->
                                selectedScheduleItem = scheduleItem
                                viewModel.loadAvailableSubstitutes(
                                    scheduleItem.period ?: 0,
                                    scheduleItem.subjectId
                                )
                                showSubstituteBottomSheet = true
                            }
                        )
                    }
                }
            }
        }
    }
    
    // Bottom Sheet for substitute teacher selection
    if (showSubstituteBottomSheet && selectedScheduleItem != null) {
        SubstituteBottomSheet(
            scheduleItem = selectedScheduleItem!!,
            substituteState = substituteState,
            onDismiss = {
                showSubstituteBottomSheet = false
                viewModel.resetSubstituteState()
            },
            onAssign = { substituteTeacherId, keterangan ->
                viewModel.assignSubstitute(
                    selectedScheduleItem!!.scheduleId,
                    substituteTeacherId,
                    keterangan
                )
            }
        )
    }
}

@Composable
private fun HeaderSection(
    day: String,
    date: String,
    currentTime: String,
    summary: ClassManagementSummary?,
    statusCounts: StatusCounts
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shadowElevation = 4.dp,
        color = Color.White
    ) {
        Column {
            // Top gradient header
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.horizontalGradient(
                            colors = listOf(
                                Color(0xFF1565C0),
                                Color(0xFF0D47A1)
                            )
                        )
                    )
                    .padding(20.dp)
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = "Manajemen Kelas",
                                style = MaterialTheme.typography.headlineSmall,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Text(
                                text = "$day, $date",
                                style = MaterialTheme.typography.bodyMedium,
                                color = Color.White.copy(alpha = 0.8f)
                            )
                        }
                        
                        // Current time badge
                        Surface(
                            shape = RoundedCornerShape(20.dp),
                            color = Color.White.copy(alpha = 0.2f)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Schedule,
                                    contentDescription = null,
                                    tint = Color.White,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(
                                    text = currentTime,
                                    style = MaterialTheme.typography.labelLarge,
                                    color = Color.White,
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Info text
                    Text(
                        text = "Menampilkan kelas yang membutuhkan penggantian guru",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }
            }
            
            // Summary cards
            if (summary != null) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    SummaryCard(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Default.Warning,
                        count = summary.tidakHadirCount,
                        label = "Tidak Hadir",
                        color = RedAlert
                    )
                    SummaryCard(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Default.AccessTime,
                        count = summary.telatCount,
                        label = "Telat",
                        color = OrangeWarning
                    )
                    SummaryCard(
                        modifier = Modifier.weight(1f),
                        icon = Icons.Outlined.EventBusy,
                        count = summary.izinCount,
                        label = "Izin",
                        color = PurpleIzin
                    )
                }
            }
        }
    }
}

@Composable
private fun SummaryCard(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    count: Int,
    label: String,
    color: Color
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(12.dp),
        color = color.copy(alpha = 0.1f),
        border = androidx.compose.foundation.BorderStroke(1.dp, color.copy(alpha = 0.3f))
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(20.dp)
            )
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                text = count.toString(),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = color.copy(alpha = 0.8f),
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun ClassGroupList(
    groupedClasses: List<ClassGroup>,
    presentTeachersByPeriod: List<PeriodTeacherInfo>,
    onAssignSubstitute: (ClassScheduleItem) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Present Teachers Section (collapsible)
        if (presentTeachersByPeriod.isNotEmpty()) {
            item {
                PresentTeachersSection(presentTeachersByPeriod = presentTeachersByPeriod)
            }
        }
        
        // Class Groups with issues
        items(groupedClasses, key = { it.className }) { classGroup ->
            ClassGroupCard(
                classGroup = classGroup,
                onAssignSubstitute = onAssignSubstitute
            )
        }
          item {
            Spacer(modifier = Modifier.height(80.dp)) // Space for bottom nav
        }
    }
}

@Composable
private fun PresentTeachersSection(
    presentTeachersByPeriod: List<PeriodTeacherInfo>
) {
    var expanded by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column {
            // Header - clickable to expand/collapse
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { expanded = !expanded },
                color = GreenSuccess.copy(alpha = 0.05f)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Surface(
                            shape = CircleShape,
                            color = GreenSuccess.copy(alpha = 0.1f)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Groups,
                                contentDescription = null,
                                tint = GreenSuccess,
                                modifier = Modifier
                                    .padding(10.dp)
                                    .size(24.dp)
                            )
                        }
                        
                        Spacer(modifier = Modifier.width(12.dp))
                        
                        Column {
                            Text(
                                text = "Guru Hadir per Jam",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF1A237E)
                            )
                            Text(
                                text = "Lihat guru yang sudah hadir",
                                style = MaterialTheme.typography.bodySmall,
                                color = Color.Gray
                            )
                        }
                    }
                    
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Total present badge
                        val totalPresent = presentTeachersByPeriod.sumOf { it.totalPresent }
                        val totalScheduled = presentTeachersByPeriod.sumOf { it.totalScheduled }
                        
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = GreenSuccess
                        ) {
                            Text(
                                text = "$totalPresent/$totalScheduled hadir",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                            )
                        }
                        
                        Icon(
                            imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                            contentDescription = if (expanded) "Collapse" else "Expand",
                            tint = Color.Gray
                        )
                    }
                }
            }
            
            // Expandable content - list of periods with present teachers
            AnimatedVisibility(
                visible = expanded,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                Column(
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    presentTeachersByPeriod.forEach { period ->
                        PeriodTeachersCard(periodInfo = period)
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
        }
    }
}

@Composable
private fun PeriodTeachersCard(
    periodInfo: PeriodTeacherInfo
) {
    val timeRange = if (periodInfo.startTime != null && periodInfo.endTime != null) {
        "${periodInfo.startTime} - ${periodInfo.endTime}"
    } else {
        "Tidak diketahui"
    }
    
    var showTeachers by remember { mutableStateOf(periodInfo.isCurrent) }
    
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { showTeachers = !showTeachers },
        shape = RoundedCornerShape(12.dp),
        color = if (periodInfo.isCurrent) GreenSuccess.copy(alpha = 0.1f) else Color(0xFFF8FAFC),
        border = if (periodInfo.isCurrent) {
            androidx.compose.foundation.BorderStroke(1.dp, GreenSuccess.copy(alpha = 0.3f))
        } else {
            androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0))
        }
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Outlined.Schedule,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp),
                        tint = if (periodInfo.isCurrent) GreenSuccess else Color(0xFF64748B)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = timeRange,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = if (periodInfo.isCurrent) FontWeight.SemiBold else FontWeight.Normal,
                        color = if (periodInfo.isCurrent) GreenSuccess else Color(0xFF1E293B)
                    )
                    
                    if (periodInfo.isCurrent) {
                        Spacer(modifier = Modifier.width(8.dp))
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = GreenSuccess
                        ) {
                            Text(
                                text = "SEKARANG",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White,
                                fontSize = 9.sp,
                                modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
                            )
                        }
                    }
                }
                
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = GreenSuccess.copy(alpha = 0.15f)
                    ) {
                        Text(
                            text = "${periodInfo.totalPresent}/${periodInfo.totalScheduled}",
                            style = MaterialTheme.typography.labelMedium,
                            color = GreenSuccess,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }
                    
                    Spacer(modifier = Modifier.width(4.dp))
                    
                    Icon(
                        imageVector = if (showTeachers) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                        contentDescription = null,
                        modifier = Modifier.size(20.dp),
                        tint = Color.Gray
                    )
                }
            }
            
            // Show teachers list when expanded
            AnimatedVisibility(
                visible = showTeachers && periodInfo.teachers.isNotEmpty(),
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                Column(
                    modifier = Modifier.padding(top = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    periodInfo.teachers.forEach { teacher ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .clip(CircleShape)
                                    .background(
                                        if (teacher.status == "hadir") GreenSuccess else OrangeWarning
                                    )
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = teacher.teacherName,
                                style = MaterialTheme.typography.bodySmall,
                                color = Color(0xFF1E293B),
                                modifier = Modifier.weight(1f)
                            )
                            Text(
                                text = "${teacher.className} - ${teacher.subjectName}",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color(0xFF64748B),
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
            }
            
            // Empty state for period
            if (showTeachers && periodInfo.teachers.isEmpty()) {
                Text(
                    text = "Belum ada guru yang hadir",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.Gray,
                    modifier = Modifier.padding(top = 8.dp)
                )
            }
        }
    }
}

@Composable
private fun ClassGroupCard(
    classGroup: ClassGroup,
    onAssignSubstitute: (ClassScheduleItem) -> Unit
) {
    var expanded by remember { mutableStateOf(true) }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column {
            // Class header
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { expanded = !expanded },
                color = if (classGroup.hasUrgent) RedAlert.copy(alpha = 0.05f) else Color.White
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Class icon with status indicator
                        Box {
                            Surface(
                                shape = CircleShape,
                                color = Color(0xFF1565C0).copy(alpha = 0.1f)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Class,
                                    contentDescription = null,
                                    tint = Color(0xFF1565C0),
                                    modifier = Modifier
                                        .padding(10.dp)
                                        .size(24.dp)
                                )
                            }
                            
                            // Urgent indicator
                            if (classGroup.hasUrgent) {
                                Box(
                                    modifier = Modifier
                                        .align(Alignment.TopEnd)
                                        .size(12.dp)
                                        .clip(CircleShape)
                                        .background(RedAlert)
                                )
                            }
                        }
                        
                        Spacer(modifier = Modifier.width(12.dp))
                        
                        Column {
                            Text(
                                text = classGroup.className,
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF1A237E)
                            )
                            if (classGroup.classMajor != null) {
                                Text(
                                    text = classGroup.classMajor,
                                    style = MaterialTheme.typography.bodySmall,
                                    color = Color.Gray
                                )
                            }
                        }
                    }
                    
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Issue count badge
                        Surface(
                            shape = RoundedCornerShape(12.dp),
                            color = if (classGroup.hasUrgent) RedAlert else OrangeWarning
                        ) {
                            Text(
                                text = "${classGroup.totalIssues} masalah",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White,
                                fontWeight = FontWeight.Medium,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                            )
                        }
                        
                        Icon(
                            imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                            contentDescription = if (expanded) "Collapse" else "Expand",
                            tint = Color.Gray
                        )
                    }
                }
            }
            
            // Schedule items
            AnimatedVisibility(
                visible = expanded,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                Column(
                    modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    classGroup.schedules.forEach { schedule ->
                        ScheduleItemCard(
                            schedule = schedule,
                            onAssignSubstitute = { onAssignSubstitute(schedule) }
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
        }
    }
}

@Composable
private fun ScheduleItemCard(
    schedule: ClassScheduleItem,
    onAssignSubstitute: () -> Unit
) {
    val statusColor = when (schedule.status) {
        "tidak_hadir" -> RedAlert
        "telat" -> OrangeWarning
        "izin" -> PurpleIzin
        "pending" -> GrayPending
        "diganti" -> BlueSubstitute
        else -> GrayPending
    }
    
    val statusText = when (schedule.status) {
        "tidak_hadir" -> "Tidak Hadir"
        "telat" -> "Telat${schedule.lateMinutes?.let { " ($it menit)" } ?: ""}"
        "izin" -> "Izin${schedule.leaveReason?.let { " - $it" } ?: ""}"
        "pending" -> "Belum Konfirmasi"
        "diganti" -> "Sudah Diganti"
        else -> schedule.status ?: "Unknown"
    }
    
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        color = Color(0xFFF8FAFC),
        border = androidx.compose.foundation.BorderStroke(
            1.dp,
            if (schedule.noTeacherAlert) RedAlert else Color(0xFFE2E8F0)
        )
    ) {
        Column(
            modifier = Modifier.padding(12.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                // Left section: Subject & Teacher
                Column(modifier = Modifier.weight(1f)) {
                    // Subject name
                    Text(
                        text = schedule.subjectName,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF1E293B)
                    )
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    // Teacher name
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Person,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = Color(0xFF64748B)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = schedule.teacherName,
                            style = MaterialTheme.typography.bodySmall,
                            color = Color(0xFF64748B)
                        )
                    }
                    
                    // Time
                    if (schedule.startTime != null) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = Icons.Outlined.Schedule,
                                contentDescription = null,
                                modifier = Modifier.size(14.dp),
                                tint = Color(0xFF64748B)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = "${schedule.startTime} - ${schedule.endTime ?: "-"}",
                                style = MaterialTheme.typography.bodySmall,
                                color = Color(0xFF64748B)
                            )
                            
                            if (schedule.isCurrentPeriod) {
                                Spacer(modifier = Modifier.width(8.dp))
                                Surface(
                                    shape = RoundedCornerShape(4.dp),
                                    color = GreenSuccess
                                ) {
                                    Text(
                                        text = "SEKARANG",
                                        style = MaterialTheme.typography.labelSmall,
                                        color = Color.White,
                                        fontSize = 9.sp,
                                        modifier = Modifier.padding(horizontal = 4.dp, vertical = 2.dp)
                                    )
                                }
                            }
                        }
                    }
                }
                
                // Right section: Status badge
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = statusColor.copy(alpha = 0.15f)
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(statusColor)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = statusText,
                            style = MaterialTheme.typography.labelSmall,
                            color = statusColor,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
            
            // Substitute info if already assigned
            if (schedule.substituteTeacherName != null) {
                Spacer(modifier = Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = BlueSubstitute.copy(alpha = 0.1f)
                ) {
                    Row(
                        modifier = Modifier.padding(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.SwapHoriz,
                            contentDescription = null,
                            tint = BlueSubstitute,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Diganti: ${schedule.substituteTeacherName}",
                            style = MaterialTheme.typography.bodySmall,
                            color = BlueSubstitute,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
            
            // Action button - show if needs substitute and not already substituted
            if (schedule.needsSubstitute && schedule.status != "diganti" && schedule.substituteTeacherName == null) {
                Spacer(modifier = Modifier.height(10.dp))
                
                Button(
                    onClick = onAssignSubstitute,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Color(0xFF1565C0)
                    ),
                    contentPadding = PaddingValues(vertical = 12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.PersonAdd,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Tugaskan Guru Pengganti",
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SubstituteBottomSheet(
    scheduleItem: ClassScheduleItem,
    substituteState: SubstituteUiState,
    onDismiss: () -> Unit,
    onAssign: (Int, String?) -> Unit
) {
    var selectedTeacherId by remember { mutableStateOf<Int?>(null) }
    var keterangan by remember { mutableStateOf("") }
    
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp),
        containerColor = Color.White,
        dragHandle = {
            Surface(
                modifier = Modifier
                    .padding(vertical = 12.dp)
                    .width(40.dp)
                    .height(4.dp),
                shape = RoundedCornerShape(2.dp),
                color = Color(0xFFE0E0E0)
            ) {}
        }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 32.dp)
        ) {
            // Header
            Text(
                text = "Pilih Guru Pengganti",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF1A237E)
            )
            
            Spacer(modifier = Modifier.height(4.dp))
            
            // Schedule info
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                color = Color(0xFFF5F7FA)
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text(
                        text = scheduleItem.className,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                    Text(
                        text = "${scheduleItem.subjectName} • ${scheduleItem.startTime ?: "-"} - ${scheduleItem.endTime ?: "-"}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.Gray
                    )
                    Text(
                        text = "Guru: ${scheduleItem.teacherName}",
                        style = MaterialTheme.typography.bodySmall,
                        color = RedAlert
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Content based on state
            when (substituteState) {
                is SubstituteUiState.Loading -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(200.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = Color(0xFF1565C0))
                    }
                }
                
                is SubstituteUiState.Success -> {
                    if (substituteState.teachers.isEmpty()) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(150.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(
                                    imageVector = Icons.Outlined.PersonOff,
                                    contentDescription = null,
                                    modifier = Modifier.size(48.dp),
                                    tint = Color.Gray
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "Tidak ada guru yang tersedia",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = Color.Gray
                                )
                            }
                        }
                    } else {
                        Text(
                            text = "Guru yang tersedia (${substituteState.teachers.size})",
                            style = MaterialTheme.typography.labelLarge,
                            color = Color(0xFF64748B),
                            fontWeight = FontWeight.Medium
                        )
                        
                        Spacer(modifier = Modifier.height(8.dp))
                        
                        LazyColumn(
                            modifier = Modifier.heightIn(max = 200.dp),
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            items(substituteState.teachers) { teacher ->
                                TeacherSelectItem(
                                    teacher = teacher,
                                    isSelected = selectedTeacherId == teacher.id,
                                    onClick = { selectedTeacherId = teacher.id }
                                )
                            }
                        }
                        
                        Spacer(modifier = Modifier.height(16.dp))
                        
                        // Keterangan field
                        OutlinedTextField(
                            value = keterangan,
                            onValueChange = { keterangan = it },
                            label = { Text("Keterangan (opsional)") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            maxLines = 2,
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Color(0xFF1565C0),
                                unfocusedBorderColor = Color(0xFFE0E0E0)
                            )
                        )
                        
                        Spacer(modifier = Modifier.height(20.dp))
                        
                        // Action buttons
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            OutlinedButton(
                                onClick = onDismiss,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE0E0E0))
                            ) {
                                Text("Batal", color = Color.Gray)
                            }
                            
                            Button(
                                onClick = {
                                    selectedTeacherId?.let { id ->
                                        onAssign(id, keterangan.ifEmpty { null })
                                    }
                                },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(12.dp),
                                enabled = selectedTeacherId != null,
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Color(0xFF1565C0)
                                )
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Check,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("Konfirmasi", fontWeight = FontWeight.SemiBold)
                            }
                        }
                    }
                }
                
                is SubstituteUiState.Assigning -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(150.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            CircularProgressIndicator(color = Color(0xFF1565C0))
                            Spacer(modifier = Modifier.height(12.dp))
                            Text(
                                text = "Menugaskan guru pengganti...",
                                style = MaterialTheme.typography.bodyMedium,
                                color = Color.Gray
                            )
                        }
                    }
                }
                
                is SubstituteUiState.Error -> {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        color = RedAlert.copy(alpha = 0.1f)
                    ) {
                        Row(
                            modifier = Modifier.padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.Error,
                                contentDescription = null,
                                tint = RedAlert
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Text(
                                text = substituteState.message,
                                style = MaterialTheme.typography.bodyMedium,
                                color = RedAlert
                            )
                        }
                    }
                }
                
                else -> {}
            }
        }
    }
}

@Composable
private fun TeacherSelectItem(
    teacher: SubstituteTeacher,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        color = if (isSelected) Color(0xFF1565C0).copy(alpha = 0.1f) else Color(0xFFF8FAFC),
        border = if (isSelected) {
            androidx.compose.foundation.BorderStroke(2.dp, Color(0xFF1565C0))
        } else {
            androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE2E8F0))
        }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Avatar
            Surface(
                shape = CircleShape,
                color = if (isSelected) Color(0xFF1565C0) else Color(0xFFE0E0E0)
            ) {
                Box(
                    modifier = Modifier.size(40.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = teacher.name.take(1).uppercase(),
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = if (isSelected) Color.White else Color(0xFF64748B)
                    )
                }
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = teacher.name,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
                    color = if (isSelected) Color(0xFF1565C0) else Color(0xFF1E293B)
                )
                if (teacher.nip != null) {
                    Text(
                        text = "NIP: ${teacher.nip}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color(0xFF64748B)
                    )
                }
            }
            
            if (isSelected) {
                Icon(
                    imageVector = Icons.Default.CheckCircle,
                    contentDescription = null,
                    tint = Color(0xFF1565C0),
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

@Composable
private fun LoadingState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(
                color = Color(0xFF1565C0),
                modifier = Modifier.size(48.dp)
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                text = "Memuat data kelas...",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray
            )
        }
    }
}

@Composable
private fun EmptyState() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Surface(
                shape = CircleShape,
                color = GreenSuccess.copy(alpha = 0.1f),
                modifier = Modifier.size(100.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Default.CheckCircle,
                        contentDescription = null,
                        tint = GreenSuccess,
                        modifier = Modifier.size(56.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Semua Guru Hadir!",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF1A237E)
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Tidak ada kelas yang membutuhkan penggantian guru saat ini",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray,
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
private fun ErrorState(
    message: String,
    onRetry: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Surface(
                shape = CircleShape,
                color = RedAlert.copy(alpha = 0.1f),
                modifier = Modifier.size(100.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Default.Error,
                        contentDescription = null,
                        tint = RedAlert,
                        modifier = Modifier.size(56.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Terjadi Kesalahan",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = Color(0xFF1A237E)
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onRetry,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF1565C0)
                )
            ) {
                Icon(
                    imageVector = Icons.Default.Refresh,
                    contentDescription = null,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Coba Lagi", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
