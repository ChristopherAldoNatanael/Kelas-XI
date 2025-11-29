package com.christopheraldoo.aplikasimonitoringkelas.ui.screens

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material.ripple.rememberRipple
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleItem
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SubmitKehadiranUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.TodayKehadiranUiState

// ============================================================================
// PROFESSIONAL COLOR PALETTE FOR KEHADIRAN SCREEN
// ============================================================================
private object KehadiranColors {
    // Primary gradient colors - Green theme for attendance
    val GradientStart = Color(0xFF00897B)
    val GradientMid = Color(0xFF26A69A)
    val GradientEnd = Color(0xFF4DB6AC)
    
    // Accent colors
    val AccentTeal = Color(0xFF00BFA5)
    val AccentGreen = Color(0xFF00C853)
    
    // Status colors
    val SuccessGreen = Color(0xFF4CAF50)
    val SuccessGreenLight = Color(0xFFE8F5E9)
    val WarningOrange = Color(0xFFFF9800)
    val WarningOrangeLight = Color(0xFFFFF3E0)
    val ErrorRed = Color(0xFFE53935)
    val ErrorRedLight = Color(0xFFFFEBEE)
    val InfoBlue = Color(0xFF2196F3)
    val InfoBlueLight = Color(0xFFE3F2FD)
    val PurpleAccent = Color(0xFF7C4DFF)
    val PurpleLight = Color(0xFFEDE7F6)
    
    // Neutral colors
    val DarkText = Color(0xFF1A1A2E)
    val MediumText = Color(0xFF4A4A68)
    val LightText = Color(0xFF8E8E9A)
    val CardBackground = Color(0xFFFFFFFF)
    val ScreenBackground = Color(0xFFF5F7FA)
    val DividerColor = Color(0xFFE8EAF0)
    
    // Waiting/Pending
    val WaitingYellow = Color(0xFFFFC107)
    val WaitingYellowLight = Color(0xFFFFF8E1)
}

// ============================================================================
// KEHADIRAN SCREEN MAIN COMPOSABLE
// ============================================================================
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KehadiranScreen(viewModel: SiswaViewModel) {
    val context = LocalContext.current

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
                viewModel.resetSubmitState()
            }
            is SubmitKehadiranUiState.Error -> {
                Toast.makeText(context, state.message, Toast.LENGTH_SHORT).show()
                viewModel.resetSubmitState()
            }
            else -> { /* Idle or Loading */ }
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(KehadiranColors.ScreenBackground)
    ) {
        when (val state = uiState) {
            is TodayKehadiranUiState.Loading -> {
                KehadiranLoadingState()
            }
            is TodayKehadiranUiState.Success -> {
                val response = state.data
                val isSubmitting = submitState is SubmitKehadiranUiState.Loading
                
                KehadiranContent(
                    tanggal = response.tanggal,
                    schedules = response.schedules,
                    isSubmitting = isSubmitting,
                    onRefresh = { viewModel.loadTodayKehadiranStatus(forceRefresh = true) },
                    onSubmit = { scheduleId, status, catatan ->
                        viewModel.submitKehadiran(scheduleId, status, catatan)
                    }
                )
            }
            is TodayKehadiranUiState.Error -> {
                KehadiranErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadTodayKehadiranStatus(forceRefresh = true) }
                )
            }
        }
    }
}

// ============================================================================
// KEHADIRAN CONTENT
// ============================================================================
@Composable
private fun KehadiranContent(
    tanggal: String,
    schedules: List<ScheduleItem>,
    isSubmitting: Boolean,
    onRefresh: () -> Unit,
    onSubmit: (Int, String, String) -> Unit
) {
    // Calculate stats
    val totalSchedules = schedules.size
    val submittedCount = schedules.count { it.submitted }
    val pendingCount = totalSchedules - submittedCount
    
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp)
    ) {
        // Header Card
        item {
            KehadiranHeaderCard(
                tanggal = tanggal,
                totalSchedules = totalSchedules,
                submittedCount = submittedCount,
                pendingCount = pendingCount,
                onRefresh = onRefresh
            )
        }
        
        // Status Summary Section
        item {
            KehadiranStatusSummary(schedules = schedules)
        }
        
        // Section Title
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Jadwal Hari Ini",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = KehadiranColors.DarkText
                )
                
                if (pendingCount > 0) {
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = KehadiranColors.WaitingYellowLight
                    ) {
                        Text(
                            text = "$pendingCount Menunggu",
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = KehadiranColors.WarningOrange
                        )
                    }
                }
            }
        }
          // Schedule Cards or Empty State
        if (schedules.isEmpty()) {
            item {
                KehadiranEmptyState()
            }        } else {
            // Use unique key combining scheduleId and period to prevent duplicate key crash
            items(schedules, key = { "${it.getActualScheduleId()}_${it.period}" }) { schedule ->
                KehadiranScheduleCard(
                    schedule = schedule,
                    isSubmitting = isSubmitting,
                    onSubmit = { status, catatan ->
                        onSubmit(schedule.getActualScheduleId(), status, catatan)
                    }
                )
            }
        }
    }
}

// ============================================================================
// HEADER CARD WITH GRADIENT
// ============================================================================
@Composable
private fun KehadiranHeaderCard(
    tanggal: String,
    totalSchedules: Int,
    submittedCount: Int,
    pendingCount: Int,
    onRefresh: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                brush = Brush.linearGradient(
                    colors = listOf(
                        KehadiranColors.GradientStart,
                        KehadiranColors.GradientMid,
                        KehadiranColors.GradientEnd
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
                        text = "Kehadiran Guru",
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.CalendarToday,
                            contentDescription = null,
                            tint = Color.White.copy(alpha = 0.9f),
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = tanggal,
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
                    label = "Total Jadwal",
                    value = totalSchedules.toString(),
                    icon = Icons.Outlined.List
                )
                HeaderStatItem(
                    label = "Terisi",
                    value = submittedCount.toString(),
                    icon = Icons.Default.CheckCircle
                )
                HeaderStatItem(
                    label = "Menunggu",
                    value = pendingCount.toString(),
                    icon = Icons.Outlined.Pending
                )
            }
        }
    }
}

@Composable
private fun HeaderStatItem(
    label: String,
    value: String,
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
// STATUS SUMMARY SECTION
// ============================================================================
@Composable
private fun KehadiranStatusSummary(schedules: List<ScheduleItem>) {
    val hadirCount = schedules.count { it.submitted && it.status == "hadir" }
    val terlambatCount = schedules.count { it.submitted && (it.status == "terlambat" || it.status == "telat") }
    val tidakHadirCount = schedules.count { it.submitted && it.status == "tidak_hadir" }
    
    if (schedules.any { it.submitted }) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
            shape = RoundedCornerShape(16.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
            colors = CardDefaults.cardColors(containerColor = KehadiranColors.CardBackground)
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Text(
                    text = "Ringkasan Status Guru",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = KehadiranColors.DarkText
                )
                
                Spacer(modifier = Modifier.height(12.dp))
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    StatusSummaryItem(
                        count = hadirCount,
                        label = "Hadir",
                        color = KehadiranColors.SuccessGreen,
                        backgroundColor = KehadiranColors.SuccessGreenLight
                    )
                    StatusSummaryItem(
                        count = terlambatCount,
                        label = "Telat",
                        color = KehadiranColors.WarningOrange,
                        backgroundColor = KehadiranColors.WarningOrangeLight
                    )
                    StatusSummaryItem(
                        count = tidakHadirCount,
                        label = "Absen",
                        color = KehadiranColors.ErrorRed,
                        backgroundColor = KehadiranColors.ErrorRedLight
                    )
                }
            }
        }
    }
}

@Composable
private fun StatusSummaryItem(
    count: Int,
    label: String,
    color: Color,
    backgroundColor: Color
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Surface(
            shape = CircleShape,
            color = backgroundColor,
            modifier = Modifier.size(48.dp)
        ) {
            Box(contentAlignment = Alignment.Center) {
                Text(
                    text = count.toString(),
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = color
                )
            }
        }
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
            color = KehadiranColors.MediumText
        )
    }
}

// ============================================================================
// SCHEDULE CARD - MODERN DESIGN
// ============================================================================
@Composable
private fun KehadiranScheduleCard(
    schedule: ScheduleItem,
    isSubmitting: Boolean,
    onSubmit: (String, String) -> Unit
) {
    var showDialog by remember { mutableStateOf(false) }
    
    val borderColor = when {
        !schedule.submitted -> KehadiranColors.WaitingYellow
        schedule.status == "hadir" -> KehadiranColors.SuccessGreen
        schedule.status == "terlambat" || schedule.status == "telat" -> KehadiranColors.WarningOrange
        schedule.status == "tidak_hadir" -> KehadiranColors.ErrorRed
        else -> KehadiranColors.DividerColor
    }
    
    val backgroundColor = when {
        !schedule.submitted -> KehadiranColors.CardBackground
        schedule.status == "hadir" -> KehadiranColors.SuccessGreenLight
        schedule.status == "terlambat" || schedule.status == "telat" -> KehadiranColors.WarningOrangeLight
        schedule.status == "tidak_hadir" -> KehadiranColors.ErrorRedLight
        else -> KehadiranColors.CardBackground
    }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 6.dp)
            .border(
                width = 2.dp,
                color = borderColor,
                shape = RoundedCornerShape(16.dp)
            ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = backgroundColor)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            // Period & Time Row with Status Badge
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    // Period Badge
                    Surface(
                        shape = RoundedCornerShape(8.dp),
                        color = borderColor.copy(alpha = 0.15f)
                    ) {
                        Text(
                            text = "P${schedule.period}",
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = borderColor
                        )
                    }
                    
                    Spacer(modifier = Modifier.width(10.dp))
                    
                    // Time
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Schedule,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = KehadiranColors.MediumText
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = schedule.time,
                            fontSize = 13.sp,
                            color = KehadiranColors.MediumText
                        )
                    }
                }
                
                // Status Badge
                KehadiranStatusBadge(schedule = schedule)
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Subject
            Text(
                text = schedule.subject,
                fontSize = 17.sp,
                fontWeight = FontWeight.Bold,
                color = KehadiranColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(6.dp))
            
            // Teacher Info
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Outlined.Person,
                    contentDescription = null,
                    modifier = Modifier.size(18.dp),
                    tint = KehadiranColors.MediumText
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = schedule.teacher,
                    fontSize = 14.sp,
                    color = KehadiranColors.MediumText
                )
            }
            
            // Catatan if exists
            if (schedule.submitted && !schedule.catatan.isNullOrEmpty()) {
                Spacer(modifier = Modifier.height(10.dp))
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = KehadiranColors.DividerColor.copy(alpha = 0.5f)
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.Top
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Notes,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = KehadiranColors.MediumText
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = schedule.catatan,
                            fontSize = 13.sp,
                            color = KehadiranColors.MediumText,
                            lineHeight = 18.sp
                        )
                    }
                }
            }
            
            // Action Button if not submitted
            if (!schedule.submitted) {
                Spacer(modifier = Modifier.height(14.dp))
                
                Button(
                    onClick = { showDialog = true },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = KehadiranColors.GradientStart
                    ),
                    enabled = !isSubmitting
                ) {
                    if (isSubmitting) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            color = Color.White,
                            strokeWidth = 2.dp
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                    }
                    Icon(
                        imageVector = Icons.Outlined.HowToReg,
                        contentDescription = null,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = if (isSubmitting) "Menyimpan..." else "Isi Kehadiran Guru",
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
    }
    
    // Modern Dialog
    if (showDialog) {
        KehadiranInputDialog(
            schedule = schedule,
            onDismiss = { showDialog = false },
            onConfirm = { status, catatan ->
                onSubmit(status, catatan)
                showDialog = false
            }
        )
    }
}

@Composable
private fun KehadiranStatusBadge(schedule: ScheduleItem) {
    val (text, bgColor, textColor, icon) = when {
        !schedule.submitted -> StatusBadgeConfig(
            "MENUNGGU",
            KehadiranColors.WaitingYellowLight,
            KehadiranColors.WaitingYellow,
            Icons.Outlined.Pending
        )
        schedule.status == "hadir" -> StatusBadgeConfig(
            "HADIR",
            KehadiranColors.SuccessGreenLight,
            KehadiranColors.SuccessGreen,
            Icons.Default.CheckCircle
        )
        schedule.status == "terlambat" || schedule.status == "telat" -> StatusBadgeConfig(
            "TERLAMBAT",
            KehadiranColors.WarningOrangeLight,
            KehadiranColors.WarningOrange,
            Icons.Default.WatchLater
        )
        schedule.status == "tidak_hadir" -> StatusBadgeConfig(
            "TIDAK HADIR",
            KehadiranColors.ErrorRedLight,
            KehadiranColors.ErrorRed,
            Icons.Default.Cancel
        )
        else -> StatusBadgeConfig(
            "UNKNOWN",
            KehadiranColors.DividerColor,
            KehadiranColors.MediumText,
            Icons.Outlined.Help
        )
    }
    
    Surface(
        shape = RoundedCornerShape(20.dp),
        color = bgColor
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(14.dp),
                tint = textColor
            )
            Spacer(modifier = Modifier.width(4.dp))
            Text(
                text = text,
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                color = textColor
            )
        }
    }
}

private data class StatusBadgeConfig(
    val text: String,
    val bgColor: Color,
    val textColor: Color,
    val icon: ImageVector
)

// ============================================================================
// MODERN INPUT DIALOG
// ============================================================================
@Composable
private fun KehadiranInputDialog(
    schedule: ScheduleItem,
    onDismiss: () -> Unit,
    onConfirm: (String, String) -> Unit
) {
    var selectedStatus by remember { mutableStateOf("hadir") }
    var catatan by remember { mutableStateOf("") }
    var jamMasuk by remember { mutableStateOf("") }
    
    // Status options untuk siswa (TANPA diganti - itu tugas kurikulum)
    val statusOptions = listOf(
        StatusOption("hadir", "Guru Hadir", Icons.Default.CheckCircle, KehadiranColors.SuccessGreen),
        StatusOption("telat", "Guru Terlambat", Icons.Default.WatchLater, KehadiranColors.WarningOrange),
        StatusOption("tidak_hadir", "Guru Tidak Hadir", Icons.Default.Cancel, KehadiranColors.ErrorRed)
    )
    
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = Color.White),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Column(
                modifier = Modifier.padding(24.dp)
            ) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = "Isi Kehadiran Guru",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = KehadiranColors.DarkText
                    )
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Tutup",
                            tint = KehadiranColors.LightText
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(8.dp))
                
                // Schedule Info
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = KehadiranColors.InfoBlueLight
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.MenuBook,
                            contentDescription = null,
                            tint = KehadiranColors.InfoBlue,
                            modifier = Modifier.size(24.dp)
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Column {
                            Text(
                                text = schedule.subject,
                                fontSize = 15.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = KehadiranColors.DarkText
                            )
                            Text(
                                text = schedule.teacher,
                                fontSize = 13.sp,
                                color = KehadiranColors.MediumText
                            )
                        }
                    }
                }
                
                Spacer(modifier = Modifier.height(20.dp))
                
                // Status Selection
                Text(
                    text = "Pilih Status Kehadiran",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = KehadiranColors.DarkText
                )
                
                Spacer(modifier = Modifier.height(12.dp))
                
                // Status Options Grid
                Column(
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    statusOptions.chunked(2).forEach { rowOptions ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            rowOptions.forEach { option ->
                                StatusOptionCard(
                                    option = option,
                                    isSelected = selectedStatus == option.value,
                                    onClick = { selectedStatus = option.value },
                                    modifier = Modifier.weight(1f)
                                )
                            }
                            // Fill empty space if odd number
                            if (rowOptions.size == 1) {
                                Spacer(modifier = Modifier.weight(1f))
                            }
                        }
                    }
                }
                  Spacer(modifier = Modifier.height(20.dp))
                
                // Jam Masuk Field - hanya tampil ketika status "telat"
                AnimatedVisibility(
                    visible = selectedStatus == "telat",
                    enter = fadeIn(),
                    exit = fadeOut()
                ) {
                    Column {
                        OutlinedTextField(
                            value = jamMasuk,
                            onValueChange = { jamMasuk = it },
                            label = { Text("Jam Masuk Guru *") },
                            placeholder = { Text("Contoh: 08:15") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = KehadiranColors.WarningOrange,
                                focusedLabelColor = KehadiranColors.WarningOrange
                            ),
                            singleLine = true,
                            leadingIcon = {
                                Icon(
                                    imageVector = Icons.Default.AccessTime,
                                    contentDescription = null,
                                    tint = KehadiranColors.WarningOrange
                                )
                            }
                        )
                        
                        Spacer(modifier = Modifier.height(4.dp))
                        
                        Text(
                            text = "Masukkan jam kedatangan guru (wajib untuk status terlambat)",
                            fontSize = 11.sp,
                            color = KehadiranColors.MediumText,
                            modifier = Modifier.padding(start = 4.dp)
                        )
                        
                        Spacer(modifier = Modifier.height(16.dp))
                    }
                }
                
                // Catatan Field
                OutlinedTextField(
                    value = catatan,
                    onValueChange = { catatan = it },
                    label = { Text("Catatan (opsional)") },
                    placeholder = { Text("Tambahkan catatan jika perlu...") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = KehadiranColors.GradientStart,
                        focusedLabelColor = KehadiranColors.GradientStart
                    ),
                    maxLines = 3,
                    leadingIcon = {
                        Icon(
                            imageVector = Icons.Outlined.Notes,
                            contentDescription = null,
                            tint = KehadiranColors.LightText
                        )
                    }
                )
                
                Spacer(modifier = Modifier.height(24.dp))
                
                // Action Buttons
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    OutlinedButton(
                        onClick = onDismiss,
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(
                            contentColor = KehadiranColors.MediumText
                        )
                    ) {
                        Text("Batal", fontWeight = FontWeight.SemiBold)
                    }
                    
                    // Validasi: jika status telat, jam masuk wajib diisi
                    val isValidSubmission = selectedStatus != "telat" || jamMasuk.isNotBlank()
                    
                    Button(
                        onClick = { 
                            // Gabungkan jam masuk ke catatan jika status telat
                            val finalCatatan = if (selectedStatus == "telat" && jamMasuk.isNotBlank()) {
                                val jamMasukInfo = "Jam masuk: $jamMasuk"
                                if (catatan.isNotBlank()) "$jamMasukInfo. $catatan" else jamMasukInfo
                            } else {
                                catatan
                            }
                            onConfirm(selectedStatus, finalCatatan) 
                        },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (isValidSubmission) KehadiranColors.GradientStart else KehadiranColors.LightText
                        ),
                        enabled = isValidSubmission
                    ) {
                        Icon(
                            imageVector = Icons.Default.Check,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Simpan", fontWeight = FontWeight.SemiBold)
                    }
                }
                
                // Pesan error jika jam masuk belum diisi untuk status telat
                AnimatedVisibility(
                    visible = selectedStatus == "telat" && jamMasuk.isBlank(),
                    enter = fadeIn(),
                    exit = fadeOut()
                ) {
                    Text(
                        text = "* Harap isi jam masuk guru terlebih dahulu",
                        fontSize = 12.sp,
                        color = KehadiranColors.ErrorRed,
                        modifier = Modifier.padding(top = 8.dp),
                        textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}

private data class StatusOption(
    val value: String,
    val label: String,
    val icon: ImageVector,
    val color: Color
)

@Composable
private fun StatusOptionCard(
    option: StatusOption,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    // Simplified: Remove complex animations for better performance
    Surface(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .clickable { onClick() },
        shape = RoundedCornerShape(12.dp),
        color = if (isSelected) option.color.copy(alpha = 0.15f) else Color.Transparent,
        border = BorderStroke(
            width = if (isSelected) 2.dp else 1.dp,
            color = if (isSelected) option.color else KehadiranColors.DividerColor
        )
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = option.icon,
                contentDescription = null,
                modifier = Modifier.size(20.dp),
                tint = if (isSelected) option.color else KehadiranColors.LightText
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = option.label.replace("Guru ", ""),
                fontSize = 12.sp,
                fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal,
                color = if (isSelected) option.color else KehadiranColors.MediumText,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

// ============================================================================
// STATE SCREENS
// ============================================================================
@Composable
private fun KehadiranLoadingState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(56.dp),
                color = KehadiranColors.GradientStart,
                strokeWidth = 4.dp
            )
            Spacer(modifier = Modifier.height(20.dp))
            Text(
                text = "Memuat jadwal hari ini...",
                fontSize = 16.sp,
                color = KehadiranColors.MediumText
            )
        }
    }
}

@Composable
private fun KehadiranErrorState(
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
                color = KehadiranColors.ErrorRedLight,
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.ErrorOutline,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = KehadiranColors.ErrorRed
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = "Terjadi Kesalahan",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = KehadiranColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                fontSize = 14.sp,
                color = KehadiranColors.MediumText,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onRetry,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = KehadiranColors.GradientStart
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
private fun KehadiranEmptyState() {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(48.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Surface(
                shape = CircleShape,
                color = KehadiranColors.InfoBlueLight,
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.EventBusy,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = KehadiranColors.InfoBlue
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = "Tidak Ada Jadwal",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = KehadiranColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Tidak ada jadwal pelajaran untuk hari ini",
                fontSize = 14.sp,
                color = KehadiranColors.MediumText,
                textAlign = TextAlign.Center
            )
        }
    }
}
