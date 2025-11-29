package com.christopheraldoo.aplikasimonitoringkelas.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
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
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SchedulesUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel

// ============================================================================
// PROFESSIONAL COLOR PALETTE FOR JADWAL SCREEN
// ============================================================================
private object JadwalColors {
    // Primary gradient colors - Blue theme
    val GradientStart = Color(0xFF2563EB)
    val GradientMid = Color(0xFF3B82F6)
    val GradientEnd = Color(0xFF60A5FA)
    
    // Day-specific colors
    val MondayBlue = Color(0xFF3B82F6)
    val TuesdayGreen = Color(0xFF10B981)
    val WednesdayOrange = Color(0xFFF59E0B)
    val ThursdayPurple = Color(0xFF8B5CF6)
    val FridayPink = Color(0xFFEC4899)
    val SaturdayTeal = Color(0xFF14B8A6)
    
    // Status colors
    val SuccessGreen = Color(0xFF4CAF50)
    val SuccessGreenLight = Color(0xFFE8F5E9)
    val WarningOrange = Color(0xFFFF9800)
    val WarningOrangeLight = Color(0xFFFFF3E0)
    
    // Teacher Attendance Status Colors
    val StatusHadir = Color(0xFF4CAF50)        // Green - Present
    val StatusHadirLight = Color(0xFFE8F5E9)
    val StatusTelat = Color(0xFFFF9800)         // Orange - Late
    val StatusTelatLight = Color(0xFFFFF3E0)
    val StatusTidakHadir = Color(0xFFE53935)    // Red - Absent
    val StatusTidakHadirLight = Color(0xFFFFEBEE)
    val StatusDiganti = Color(0xFF9C27B0)       // Purple - Substituted
    val StatusDigantiLight = Color(0xFFF3E5F5)
    val StatusMenunggu = Color(0xFF9E9E9E)      // Gray - Waiting for report
    val StatusMenungguLight = Color(0xFFF5F5F5)
    
    // Neutral colors
    val DarkText = Color(0xFF1A1A2E)
    val MediumText = Color(0xFF4A4A68)
    val LightText = Color(0xFF8E8E9A)
    val CardBackground = Color(0xFFFFFFFF)
    val ScreenBackground = Color(0xFFF5F7FA)
    val DividerColor = Color(0xFFE8EAF0)
    
    val dayColors = mapOf(
        "senin" to MondayBlue,
        "selasa" to TuesdayGreen,
        "rabu" to WednesdayOrange,
        "kamis" to ThursdayPurple,
        "jumat" to FridayPink,
        "sabtu" to SaturdayTeal
    )
}

// ============================================================================
// JADWAL SCREEN MAIN COMPOSABLE
// ============================================================================
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun JadwalScreen(
    viewModel: SiswaViewModel,
    modifier: Modifier = Modifier
) {
    val schedulesState by viewModel.schedulesState.collectAsState()
    var selectedDay by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        viewModel.loadSchedules()
    }

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(JadwalColors.ScreenBackground)
    ) {
        when (val state = schedulesState) {
            is SchedulesUiState.Loading -> JadwalLoadingState()
            is SchedulesUiState.Success -> {
                if (state.groupedByDay.isEmpty()) {
                    JadwalEmptyState()
                } else {
                    val className = state.schedules.firstOrNull()?.className ?: "Kelas Anda"
                    JadwalContent(
                        className = className,
                        todayDay = state.todayDay,
                        groupedSchedules = state.groupedByDay,
                        totalSchedules = state.schedules.size,
                        selectedDay = selectedDay,
                        onDaySelected = { selectedDay = it },
                        onRefresh = { viewModel.loadSchedules(forceRefresh = true) }
                    )
                }
            }
            is SchedulesUiState.Error -> {
                JadwalErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadSchedules(forceRefresh = true) }
                )
            }
        }
    }
}

// ============================================================================
// JADWAL CONTENT
// ============================================================================
@Composable
private fun JadwalContent(
    className: String,
    todayDay: String,
    groupedSchedules: Map<String, List<ScheduleApi>>,
    totalSchedules: Int,
    selectedDay: String?,
    onDaySelected: (String?) -> Unit,
    onRefresh: () -> Unit
) {
    val days = groupedSchedules.keys.toList()
    val displayedSchedules = if (selectedDay != null) {
        mapOf(selectedDay to (groupedSchedules[selectedDay] ?: emptyList()))
    } else {
        groupedSchedules
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp)
    ) {
        // Header Card
        item {
            JadwalHeaderCard(
                className = className,
                totalSchedules = totalSchedules,
                totalDays = days.size,
                onRefresh = onRefresh
            )
        }
        
        // Today Info Card
        val todaySchedules = groupedSchedules.entries.firstOrNull { 
            it.key.equals(todayDay, ignoreCase = true) 
        }?.value
        
        if (todaySchedules != null && todaySchedules.isNotEmpty()) {
            item {
                TodayInfoCard(
                    todayDay = todayDay,
                    scheduleCount = todaySchedules.size
                )
            }
        }
        
        // Day Filter Chips
        item {
            DayFilterChips(
                days = days,
                selectedDay = selectedDay,
                todayDay = todayDay,
                onDaySelected = onDaySelected
            )
        }
        
        // Schedule Cards by Day
        displayedSchedules.forEach { (day, schedules) ->
            // Day Header
            item {
                DayHeader(
                    day = day,
                    count = schedules.size,
                    isToday = day.equals(todayDay, ignoreCase = true)
                )
            }
            
            // Schedule Items
            itemsIndexed(
                schedules.sortedBy { it.period },
                key = { _, schedule -> "${day}_${schedule.id}" }
            ) { index, schedule ->
                ScheduleCard(
                    schedule = schedule,
                    index = index + 1,
                    isToday = day.equals(todayDay, ignoreCase = true)
                )
            }
        }
        
        item { Spacer(Modifier.height(20.dp)) }
    }
}

// ============================================================================
// HEADER CARD WITH GRADIENT
// ============================================================================
@Composable
private fun JadwalHeaderCard(
    className: String,
    totalSchedules: Int,
    totalDays: Int,
    onRefresh: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                brush = Brush.linearGradient(
                    colors = listOf(
                        JadwalColors.GradientStart,
                        JadwalColors.GradientMid,
                        JadwalColors.GradientEnd
                    )
                )
            )
            .padding(20.dp)
    ) {
        Column {
            // Title Row with Refresh Button
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Jadwal Pelajaran",
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.School,
                            contentDescription = null,
                            tint = Color.White.copy(alpha = 0.9f),
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = className,
                            fontSize = 14.sp,
                            color = Color.White.copy(alpha = 0.9f)
                        )
                    }
                }
                
                // Refresh Button
                Surface(
                    shape = CircleShape,
                    color = Color.White.copy(alpha = 0.2f),
                    modifier = Modifier.clickable { onRefresh() }
                ) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = "Refresh",
                        tint = Color.White,
                        modifier = Modifier.padding(12.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Stats Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                HeaderStatItem(
                    value = totalSchedules.toString(),
                    label = "Total Jadwal",
                    icon = Icons.Outlined.List
                )
                HeaderStatItem(
                    value = totalDays.toString(),
                    label = "Hari Aktif",
                    icon = Icons.Outlined.CalendarToday
                )
                HeaderStatItem(
                    value = if (totalDays > 0) "${totalSchedules / totalDays}" else "0",
                    label = "Rata-rata/Hari",
                    icon = Icons.Outlined.Assessment
                )
            }
        }
    }
}

@Composable
private fun HeaderStatItem(
    value: String,
    label: String,
    icon: ImageVector
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Surface(
            shape = CircleShape,
            color = Color.White.copy(alpha = 0.2f)
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Color.White,
                modifier = Modifier.padding(10.dp)
            )
        }
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            text = value,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White
        )
        Text(
            text = label,
            fontSize = 12.sp,
            color = Color.White.copy(alpha = 0.85f)
        )
    }
}

// ============================================================================
// TODAY INFO CARD
// ============================================================================
@Composable
private fun TodayInfoCard(
    todayDay: String,
    scheduleCount: Int
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(containerColor = JadwalColors.CardBackground)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = JadwalColors.SuccessGreenLight
            ) {
                Icon(
                    imageVector = Icons.Filled.Today,
                    contentDescription = null,
                    tint = JadwalColors.SuccessGreen,
                    modifier = Modifier.padding(12.dp)
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Hari Ini - ${todayDay.replaceFirstChar { it.uppercase() }}",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = JadwalColors.DarkText
                )
                Text(
                    text = "$scheduleCount jadwal pelajaran",
                    fontSize = 13.sp,
                    color = JadwalColors.MediumText
                )
            }
            
            Surface(
                shape = RoundedCornerShape(20.dp),
                color = JadwalColors.SuccessGreenLight
            ) {
                Text(
                    text = "$scheduleCount",
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 6.dp),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = JadwalColors.SuccessGreen
                )
            }
        }
    }
}

// ============================================================================
// DAY FILTER CHIPS
// ============================================================================
@Composable
private fun DayFilterChips(
    days: List<String>,
    selectedDay: String?,
    todayDay: String,
    onDaySelected: (String?) -> Unit
) {
    LazyRow(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        contentPadding = PaddingValues(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // "All" chip
        item {
            FilterChip(
                selected = selectedDay == null,
                onClick = { onDaySelected(null) },
                label = {
                    Text(
                        "Semua",
                        fontWeight = if (selectedDay == null) FontWeight.Bold else FontWeight.Normal
                    )
                },
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = JadwalColors.GradientStart.copy(alpha = 0.15f),
                    selectedLabelColor = JadwalColors.GradientStart
                ),
                border = FilterChipDefaults.filterChipBorder(
                    enabled = true,
                    selected = selectedDay == null,
                    borderColor = JadwalColors.DividerColor,
                    selectedBorderColor = JadwalColors.GradientStart
                )
            )
        }
        
        // Day chips
        items(days) { day ->
            val isSelected = selectedDay == day
            val isToday = day.equals(todayDay, ignoreCase = true)
            val dayColor = JadwalColors.dayColors[day.lowercase()] ?: JadwalColors.GradientStart
            
            FilterChip(
                selected = isSelected,
                onClick = { onDaySelected(if (isSelected) null else day) },
                label = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            day.replaceFirstChar { it.uppercase() },
                            fontWeight = if (isSelected || isToday) FontWeight.Bold else FontWeight.Normal
                        )
                        if (isToday) {
                            Spacer(modifier = Modifier.width(4.dp))
                            Surface(
                                shape = CircleShape,
                                color = JadwalColors.SuccessGreen,
                                modifier = Modifier.size(8.dp)
                            ) {}
                        }
                    }
                },
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = dayColor.copy(alpha = 0.15f),
                    selectedLabelColor = dayColor
                ),
                border = FilterChipDefaults.filterChipBorder(
                    enabled = true,
                    selected = isSelected,
                    borderColor = if (isToday) JadwalColors.SuccessGreen else JadwalColors.DividerColor,
                    selectedBorderColor = dayColor
                )
            )
        }
    }
}

// ============================================================================
// DAY HEADER
// ============================================================================
@Composable
private fun DayHeader(
    day: String,
    count: Int,
    isToday: Boolean
) {
    val dayColor = JadwalColors.dayColors[day.lowercase()] ?: JadwalColors.GradientStart
    
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Surface(
            shape = RoundedCornerShape(8.dp),
            color = dayColor.copy(alpha = 0.1f)
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Outlined.CalendarToday,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                    tint = dayColor
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = day.replaceFirstChar { it.uppercase() },
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = dayColor
                )
            }
        }
        
        if (isToday) {
            Spacer(modifier = Modifier.width(8.dp))
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = JadwalColors.SuccessGreenLight
            ) {
                Text(
                    text = "HARI INI",
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp),
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    color = JadwalColors.SuccessGreen
                )
            }
        }
        
        Spacer(modifier = Modifier.weight(1f))
        
        Text(
            text = "$count jadwal",
            fontSize = 12.sp,
            color = JadwalColors.LightText
        )
    }
}

// ============================================================================
// SCHEDULE CARD - With Teacher Attendance Status
// ============================================================================
@Composable
private fun ScheduleCard(
    schedule: ScheduleApi,
    index: Int,
    isToday: Boolean
) {
    val dayColor = JadwalColors.dayColors[schedule.dayOfWeek.lowercase()] ?: JadwalColors.GradientStart
    
    // Determine attendance status colors and text
    val attendanceStatusInfo = when (schedule.attendanceStatus?.lowercase()) {
        "hadir" -> Triple(JadwalColors.StatusHadir, JadwalColors.StatusHadirLight, "HADIR")
        "telat" -> Triple(JadwalColors.StatusTelat, JadwalColors.StatusTelatLight, "TELAT")
        "tidak_hadir" -> Triple(JadwalColors.StatusTidakHadir, JadwalColors.StatusTidakHadirLight, "TIDAK HADIR")
        "diganti" -> Triple(JadwalColors.StatusDiganti, JadwalColors.StatusDigantiLight, "DIGANTI")
        else -> if (schedule.isToday) {
            Triple(JadwalColors.StatusMenunggu, JadwalColors.StatusMenungguLight, "MENUNGGU")
        } else null
    }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp)
            .then(
                if (isToday) {
                    Modifier.border(1.5.dp, dayColor.copy(alpha = 0.3f), RoundedCornerShape(16.dp))
                } else Modifier
            ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(
            containerColor = if (isToday) dayColor.copy(alpha = 0.05f) else JadwalColors.CardBackground
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Period Number Badge
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = dayColor.copy(alpha = 0.1f),
                    modifier = Modifier.size(48.dp)
                ) {
                    Box(contentAlignment = Alignment.Center) {
                        Text(
                            text = "${schedule.period}",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = dayColor
                        )
                    }
                }
                
                Spacer(modifier = Modifier.width(14.dp))
                
                // Schedule Info
                Column(
                    modifier = Modifier.weight(1f)
                ) {
                    // Subject Name
                    Text(
                        text = schedule.subjectName ?: "Mata Pelajaran",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = JadwalColors.DarkText,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    // Teacher Name
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Person,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = JadwalColors.MediumText
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = schedule.teacherName ?: "Guru",
                            fontSize = 13.sp,
                            color = JadwalColors.MediumText,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(2.dp))
                    
                    // Time
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Schedule,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = JadwalColors.LightText
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "${schedule.startTime} - ${schedule.endTime}",
                            fontSize = 12.sp,
                            color = JadwalColors.LightText
                        )
                    }
                }
                
                // Attendance Status Badge (only for today's schedules)
                if (attendanceStatusInfo != null) {
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = attendanceStatusInfo.second
                    ) {
                        Text(
                            text = attendanceStatusInfo.third,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = attendanceStatusInfo.first
                        )
                    }
                } else {
                    // Period indicator on the right (for non-today schedules)
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            text = "P${schedule.period}",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                            color = JadwalColors.LightText
                        )
                    }
                }
            }
            
            // Show substitute teacher info if status is "diganti"
            if (schedule.attendanceStatus?.lowercase() == "diganti" && !schedule.substituteTeacherName.isNullOrEmpty()) {
                Spacer(modifier = Modifier.height(8.dp))
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = JadwalColors.StatusDigantiLight
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.SwapHoriz,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = JadwalColors.StatusDiganti
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Diganti oleh: ${schedule.substituteTeacherName}",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = JadwalColors.StatusDiganti
                        )
                    }
                }
            }
            
            // Show note if available
            if (!schedule.attendanceCatatan.isNullOrEmpty()) {
                Spacer(modifier = Modifier.height(6.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            color = Color(0xFFFAFAFA),
                            shape = RoundedCornerShape(6.dp)
                        )
                        .padding(horizontal = 8.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Icon(
                        imageVector = Icons.Outlined.Notes,
                        contentDescription = null,
                        modifier = Modifier.size(12.dp),
                        tint = JadwalColors.LightText
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = schedule.attendanceCatatan,
                        fontSize = 11.sp,
                        color = JadwalColors.MediumText,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
        }
    }
}

// ============================================================================
// STATE SCREENS
// ============================================================================
@Composable
private fun JadwalLoadingState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(56.dp),
                color = JadwalColors.GradientStart,
                strokeWidth = 4.dp
            )
            Spacer(modifier = Modifier.height(20.dp))
            Text(
                text = "Memuat jadwal pelajaran...",
                fontSize = 16.sp,
                color = JadwalColors.MediumText
            )
        }
    }
}

@Composable
private fun JadwalErrorState(
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
                color = Color(0xFFFFEBEE),
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.ErrorOutline,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = Color(0xFFE53935)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = "Terjadi Kesalahan",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = JadwalColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                fontSize = 14.sp,
                color = JadwalColors.MediumText,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onRetry,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = JadwalColors.GradientStart
                )
            ) {
                Icon(
                    imageVector = Icons.Default.Refresh,
                    contentDescription = null,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Coba Lagi", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun JadwalEmptyState() {
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
                color = Color(0xFFE3F2FD),
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.EventBusy,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = Color(0xFF2196F3)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = "Tidak Ada Jadwal",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = JadwalColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Belum ada jadwal pelajaran yang terdaftar",
                fontSize = 14.sp,
                color = JadwalColors.MediumText,
                textAlign = TextAlign.Center
            )
        }
    }
}


