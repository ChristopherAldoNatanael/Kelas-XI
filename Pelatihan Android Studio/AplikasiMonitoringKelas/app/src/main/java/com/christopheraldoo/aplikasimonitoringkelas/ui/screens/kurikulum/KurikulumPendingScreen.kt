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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceItem
import com.christopheraldoo.aplikasimonitoringkelas.data.PendingClassGroup
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.ConfirmAttendanceUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.PendingAttendanceUiState
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumPendingScreen(
    viewModel: KurikulumViewModel,
    onNavigateBack: () -> Unit = {}
) {
    val pendingState by viewModel.pendingState.collectAsState()
    val confirmState by viewModel.confirmState.collectAsState()
    
    // Snackbar state for feedback
    val snackbarHostState = remember { SnackbarHostState() }
    
    // Track selected items for bulk confirm - now stores full items
    var selectedItems by remember { mutableStateOf(setOf<PendingAttendanceItem>()) }
    var showBulkConfirmDialog by remember { mutableStateOf(false) }
    var showSingleConfirmDialog by remember { mutableStateOf<PendingAttendanceItem?>(null) }
    
    // Real-time clock
    var currentTime by remember { mutableStateOf("") }
    
    LaunchedEffect(Unit) {
        viewModel.loadPendingAttendances()
        while (true) {
            currentTime = SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(Date())
            delay(1000)
        }
    }
    
    // Handle confirm state changes with Snackbar feedback
    LaunchedEffect(confirmState) {
        when (confirmState) {
            is ConfirmAttendanceUiState.Success -> {
                selectedItems = emptySet()
                showSingleConfirmDialog = null
                snackbarHostState.showSnackbar(
                    message = (confirmState as ConfirmAttendanceUiState.Success).message,
                    duration = SnackbarDuration.Short
                )
                viewModel.resetConfirmState()
            }
            is ConfirmAttendanceUiState.Error -> {
                snackbarHostState.showSnackbar(
                    message = "Error: ${(confirmState as ConfirmAttendanceUiState.Error).message}",
                    duration = SnackbarDuration.Long
                )
                viewModel.resetConfirmState()
            }
            else -> {}
        }
    }
    
    val gradientColors = listOf(
        Color(0xFF7C4DFF),
        Color(0xFF651FFF)
    )
    
    Scaffold(
        topBar = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(brush = Brush.horizontalGradient(gradientColors))
                    .statusBarsPadding()
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconButton(onClick = onNavigateBack) {
                                Icon(
                                    Icons.Default.ArrowBack,
                                    contentDescription = "Back",
                                    tint = Color.White
                                )
                            }
                            Spacer(modifier = Modifier.width(8.dp))
                            Column {
                                Text(
                                    "Konfirmasi Kehadiran",
                                    color = Color.White,
                                    fontSize = 20.sp,
                                    fontWeight = FontWeight.Bold
                                )
                                Text(
                                    "Pending → Hadir/Telat",
                                    color = Color.White.copy(alpha = 0.8f),
                                    fontSize = 12.sp
                                )
                            }
                        }
                        
                        // Real-time clock
                        Surface(
                            color = Color.White.copy(alpha = 0.2f),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Outlined.Schedule,
                                    contentDescription = null,
                                    tint = Color.White,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(
                                    currentTime,
                                    color = Color.White,
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Medium
                                )
                            }
                        }
                    }
                }
            }
        },
        floatingActionButton = {
            if (selectedItems.isNotEmpty()) {
                ExtendedFloatingActionButton(
                    onClick = { showBulkConfirmDialog = true },
                    containerColor = Color(0xFF4CAF50),
                    contentColor = Color.White
                ) {
                    Icon(Icons.Default.CheckCircle, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Konfirmasi ${selectedItems.size} Terpilih")
                }
            }
        },
        snackbarHost = {
            SnackbarHost(hostState = snackbarHostState) { data ->
                Snackbar(
                    snackbarData = data,
                    containerColor = if (data.visuals.message.startsWith("Error"))
                        Color(0xFFF44336) else Color(0xFF4CAF50),
                    contentColor = Color.White
                )
            }
        }
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            when (val state = pendingState) {
                is PendingAttendanceUiState.Loading -> {
                    LoadingContent()
                }
                is PendingAttendanceUiState.Success -> {
                    PendingContent(
                        date = state.date,
                        day = state.day,
                        totalPending = state.totalPending,
                        belumLaporCount = state.belumLaporCount,
                        pendingCount = state.pendingCount,
                        groupedByClass = state.groupedByClass,
                        selectedItems = selectedItems,
                        onItemSelect = { item ->
                            selectedItems = if (selectedItems.any { it.scheduleId == item.scheduleId && it.date == item.date }) {
                                selectedItems.filter { it.scheduleId != item.scheduleId || it.date != item.date }.toSet()
                            } else {
                                selectedItems + item
                            }
                        },
                        onSelectAll = { items ->
                            val allSelected = items.all { item -> selectedItems.any { it.scheduleId == item.scheduleId && it.date == item.date } }
                            selectedItems = if (allSelected) {
                                selectedItems.filter { selected -> items.none { it.scheduleId == selected.scheduleId && it.date == selected.date } }.toSet()
                            } else {
                                selectedItems + items
                            }
                        },
                        onItemClick = { item -> showSingleConfirmDialog = item },
                        onRefresh = { viewModel.loadPendingAttendances() }
                    )
                }
                is PendingAttendanceUiState.Empty -> {
                    EmptyContent(date = state.date, day = state.day)
                }
                is PendingAttendanceUiState.Error -> {
                    ErrorContent(
                        message = state.message,
                        onRetry = { viewModel.loadPendingAttendances() }
                    )
                }
            }
            
            // Confirm state overlay
            AnimatedVisibility(
                visible = confirmState is ConfirmAttendanceUiState.Confirming,
                enter = fadeIn(),
                exit = fadeOut()
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color.Black.copy(alpha = 0.5f)),
                    contentAlignment = Alignment.Center
                ) {
                    Card(
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White)
                    ) {
                        Column(
                            modifier = Modifier.padding(32.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            CircularProgressIndicator(color = Color(0xFF7C4DFF))
                            Spacer(modifier = Modifier.height(16.dp))
                            Text("Mengkonfirmasi kehadiran...")
                        }
                    }
                }
            }
            
            // Success snackbar
            AnimatedVisibility(
                visible = confirmState is ConfirmAttendanceUiState.Success,
                enter = slideInVertically { it } + fadeIn(),
                exit = slideOutVertically { it } + fadeOut(),
                modifier = Modifier.align(Alignment.BottomCenter)
            ) {
                Card(
                    modifier = Modifier
                        .padding(16.dp)
                        .fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFF4CAF50)),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.CheckCircle,
                            contentDescription = null,
                            tint = Color.White
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(
                            (confirmState as? ConfirmAttendanceUiState.Success)?.message ?: "",
                            color = Color.White,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
        }
    }
      // Bulk Confirm Dialog
    if (showBulkConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showBulkConfirmDialog = false },
            icon = { Icon(Icons.Default.CheckCircle, null, tint = Color(0xFF4CAF50)) },
            title = { Text("Konfirmasi ${selectedItems.size} Kehadiran") },
            text = { 
                Text("Pilih status kehadiran untuk ${selectedItems.size} guru yang dipilih:")
            },
            confirmButton = {
                Row {
                    TextButton(
                        onClick = {
                            viewModel.bulkConfirmAttendance(selectedItems.toList(), "hadir")
                            showBulkConfirmDialog = false
                        }
                    ) {
                        Icon(Icons.Default.Check, null, tint = Color(0xFF4CAF50))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Hadir", color = Color(0xFF4CAF50))
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    TextButton(
                        onClick = {
                            viewModel.bulkConfirmAttendance(selectedItems.toList(), "telat")
                            showBulkConfirmDialog = false
                        }
                    ) {
                        Icon(Icons.Default.Schedule, null, tint = Color(0xFFFF9800))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Telat", color = Color(0xFFFF9800))
                    }
                }
            },
            dismissButton = {
                TextButton(onClick = { showBulkConfirmDialog = false }) {
                    Text("Batal")
                }
            }
        )
    }
    
    // Single Confirm Dialog - Updated to handle both cases
    showSingleConfirmDialog?.let { item ->
        AlertDialog(
            onDismissRequest = { showSingleConfirmDialog = null },
            icon = { 
                Icon(
                    if (item.hasAttendance) Icons.Default.HowToReg else Icons.Default.PersonAdd,
                    null,
                    tint = if (item.status == "pending") Color(0xFFFF9800) else Color(0xFF7C4DFF),
                    modifier = Modifier.size(32.dp)
                )
            },
            title = { 
                Text(
                    if (item.hasAttendance) "Konfirmasi Kehadiran" else "Set Kehadiran"
                ) 
            },
            text = {
                Column {
                    Text(
                        item.teacherName,
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        "${item.className} • ${item.subjectName}",
                        color = Color.Gray,
                        fontSize = 14.sp
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Outlined.Schedule,
                            null,
                            tint = Color.Gray,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            "Jadwal: ${item.timeStart ?: "-"} - ${item.timeEnd ?: "-"}",
                            fontSize = 13.sp,
                            color = Color.Gray
                        )
                    }
                    if (item.arrivalTime != null) {
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                Icons.Default.Login,
                                null,
                                tint = Color(0xFF4CAF50),
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                "Jam masuk: ${item.arrivalTime}",
                                fontSize = 13.sp,
                                color = Color(0xFF4CAF50)
                            )
                        }
                    }
                    
                    // Status indicator
                    Spacer(modifier = Modifier.height(12.dp))
                    Surface(
                        color = when(item.status) {
                            "pending" -> Color(0xFFFF9800).copy(alpha = 0.1f)
                            else -> Color(0xFF9E9E9E).copy(alpha = 0.1f)
                        },
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                if (item.status == "pending") Icons.Default.HourglassEmpty else Icons.Default.Help,
                                null,
                                tint = if (item.status == "pending") Color(0xFFFF9800) else Color(0xFF9E9E9E),
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                when(item.status) {
                                    "pending" -> "Menunggu konfirmasi (dilaporkan siswa)"
                                    else -> "Belum ada laporan kehadiran"
                                },
                                fontSize = 12.sp,
                                color = if (item.status == "pending") Color(0xFFFF9800) else Color(0xFF9E9E9E)
                            )
                        }
                    }
                }
            },
            confirmButton = {
                Column {
                    // Row untuk Hadir dan Telat
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        FilledTonalButton(
                            onClick = {
                                viewModel.setAttendanceStatus(
                                    scheduleId = if (!item.hasAttendance) item.scheduleId else null,
                                    attendanceId = if (item.hasAttendance) item.id else null,
                                    status = "hadir",
                                    date = item.date
                                )
                                showSingleConfirmDialog = null
                            },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.filledTonalButtonColors(
                                containerColor = Color(0xFF4CAF50).copy(alpha = 0.15f),
                                contentColor = Color(0xFF4CAF50)
                            )
                        ) {
                            Icon(Icons.Default.Check, null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(4.dp))
                            Text("Hadir")
                        }
                        FilledTonalButton(
                            onClick = {
                                viewModel.setAttendanceStatus(
                                    scheduleId = if (!item.hasAttendance) item.scheduleId else null,
                                    attendanceId = if (item.hasAttendance) item.id else null,
                                    status = "telat",
                                    date = item.date
                                )
                                showSingleConfirmDialog = null
                            },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.filledTonalButtonColors(
                                containerColor = Color(0xFFFF9800).copy(alpha = 0.15f),
                                contentColor = Color(0xFFFF9800)
                            )
                        ) {
                            Icon(Icons.Default.Schedule, null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(4.dp))
                            Text("Telat")
                        }
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    // Tombol Tidak Hadir (sederhana - guru pengganti di halaman Kelas)
                    OutlinedButton(
                        onClick = {
                            viewModel.setAttendanceStatus(
                                scheduleId = if (!item.hasAttendance) item.scheduleId else null,
                                attendanceId = if (item.hasAttendance) item.id else null,
                                status = "tidak_hadir",
                                date = item.date
                            )
                            showSingleConfirmDialog = null
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.outlinedButtonColors(
                            contentColor = Color(0xFFF44336)
                        ),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFF44336).copy(alpha = 0.5f))
                    ) {
                        Icon(Icons.Default.Close, null, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Tidak Hadir")
                    }
                    
                    // Info untuk assign guru pengganti
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "💡 Untuk menugaskan guru pengganti, buka halaman Kelas",
                        fontSize = 11.sp,
                        color = Color.Gray,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            dismissButton = {
                TextButton(onClick = { showSingleConfirmDialog = null }) {
                    Text("Batal")
                }
            }
        )
    }
}

@Composable
private fun LoadingContent() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(color = Color(0xFF7C4DFF))
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                "Memuat data pending...",
                color = Color.Gray
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PendingContent(
    date: String,
    day: String,
    totalPending: Int,
    belumLaporCount: Int = 0,
    pendingCount: Int = 0,
    groupedByClass: List<PendingClassGroup>,
    selectedItems: Set<PendingAttendanceItem>,
    onItemSelect: (PendingAttendanceItem) -> Unit,
    onSelectAll: (List<PendingAttendanceItem>) -> Unit,
    onItemClick: (PendingAttendanceItem) -> Unit,
    onRefresh: () -> Unit
) {
    var isRefreshing by remember { mutableStateOf(false) }
    
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Summary Card
        item {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = Color(0xFFF3E5F5)
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(56.dp)
                                .clip(CircleShape)
                                .background(Color(0xFF7C4DFF)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Default.Assignment,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(28.dp)
                            )
                        }
                        Spacer(modifier = Modifier.width(16.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                "$totalPending Jadwal Perlu Kehadiran",
                                fontWeight = FontWeight.Bold,
                                fontSize = 18.sp,
                                color = Color(0xFF4A148C)
                            )
                            Text(
                                "$day, $date",
                                color = Color(0xFF7C4DFF),
                                fontSize = 14.sp
                            )
                        }
                        IconButton(onClick = onRefresh) {
                            Icon(
                                Icons.Default.Refresh,
                                contentDescription = "Refresh",
                                tint = Color(0xFF7C4DFF)
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    // Breakdown counts
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Belum dilaporkan
                        Surface(
                            modifier = Modifier.weight(1f),
                            color = Color(0xFF9E9E9E).copy(alpha = 0.1f),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Column(
                                modifier = Modifier.padding(12.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Text(
                                    "$belumLaporCount",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 20.sp,
                                    color = Color(0xFF616161)
                                )
                                Text(
                                    "Belum Lapor",
                                    fontSize = 11.sp,
                                    color = Color(0xFF9E9E9E)
                                )
                            }
                        }
                        
                        // Pending (dilaporkan siswa)
                        Surface(
                            modifier = Modifier.weight(1f),
                            color = Color(0xFFFF9800).copy(alpha = 0.1f),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Column(
                                modifier = Modifier.padding(12.dp),
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Text(
                                    "$pendingCount",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 20.sp,
                                    color = Color(0xFFE65100)
                                )
                                Text(
                                    "Dilaporkan Siswa",
                                    fontSize = 11.sp,
                                    color = Color(0xFFFF9800)
                                )
                            }
                        }
                    }
                }
            }
        }
        
        // Grouped by Class
        items(groupedByClass) { classGroup ->
            ClassGroupCard(
                classGroup = classGroup,
                selectedItems = selectedItems,
                onItemSelect = onItemSelect,
                onSelectAll = onSelectAll,
                onItemClick = onItemClick
            )
        }
        
        // Bottom spacing
        item {
            Spacer(modifier = Modifier.height(80.dp))
        }
    }
}

@Composable
private fun ClassGroupCard(
    classGroup: PendingClassGroup,
    selectedItems: Set<PendingAttendanceItem>,
    onItemSelect: (PendingAttendanceItem) -> Unit,
    onSelectAll: (List<PendingAttendanceItem>) -> Unit,
    onItemClick: (PendingAttendanceItem) -> Unit
) {
    var expanded by remember { mutableStateOf(true) }
    // All items are now selectable (both pending and belum_lapor)
    val selectableItems = classGroup.schedules
    val allSelected = selectableItems.isNotEmpty() && selectableItems.all { item -> 
        selectedItems.any { it.scheduleId == item.scheduleId && it.date == item.date }
    }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column {
            // Header
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { expanded = !expanded }
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (selectableItems.isNotEmpty()) {
                    Checkbox(
                        checked = allSelected,
                        onCheckedChange = { onSelectAll(selectableItems) },
                        colors = CheckboxDefaults.colors(
                            checkedColor = Color(0xFF7C4DFF)
                        )
                    )
                } else {
                    Spacer(modifier = Modifier.width(12.dp))
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        classGroup.className,
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp
                    )
                    Row {
                        if (classGroup.belumLaporCount > 0) {
                            Text(
                                "${classGroup.belumLaporCount} belum lapor",
                                color = Color(0xFF9E9E9E),
                                fontSize = 12.sp
                            )
                        }
                        if (classGroup.belumLaporCount > 0 && classGroup.pendingCount > 0) {
                            Text(" • ", color = Color.Gray, fontSize = 12.sp)
                        }
                        if (classGroup.pendingCount > 0) {
                            Text(
                                "${classGroup.pendingCount} pending",
                                color = Color(0xFFFF9800),
                                fontSize = 12.sp
                            )
                        }
                    }
                }
                Icon(
                    if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                    contentDescription = null,
                    tint = Color.Gray
                )
            }
            
            // Content
            AnimatedVisibility(visible = expanded) {
                Column(
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    classGroup.schedules.forEach { item ->
                        PendingItemRow(
                            item = item,
                            isSelected = selectedItems.any { it.scheduleId == item.scheduleId && it.date == item.date },
                            onSelect = { onItemSelect(item) },
                            onClick = { onItemClick(item) }
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun PendingItemRow(
    item: PendingAttendanceItem,
    isSelected: Boolean,
    onSelect: () -> Unit,
    onClick: () -> Unit
) {
    val statusColor = when(item.status) {
        "pending" -> Color(0xFFFF9800)
        else -> Color(0xFF9E9E9E) // belum_lapor
    }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(4.dp)
            .clickable { onClick() },
        colors = CardDefaults.cardColors(
            containerColor = if (isSelected) 
                Color(0xFF7C4DFF).copy(alpha = 0.1f) 
            else 
                Color(0xFFF5F5F5)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Always show checkbox for all items (both pending and belum_lapor)
            Checkbox(
                checked = isSelected,
                onCheckedChange = { onSelect() },
                colors = CheckboxDefaults.colors(
                    checkedColor = Color(0xFF7C4DFF)
                )
            )
            
            // Status indicator dot
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .clip(CircleShape)
                    .background(statusColor)
            )
            Spacer(modifier = Modifier.width(8.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    item.teacherName,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 14.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    item.subjectName,
                    color = Color.Gray,
                    fontSize = 12.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                // Status label
                Spacer(modifier = Modifier.height(4.dp))
                Surface(
                    color = statusColor.copy(alpha = 0.1f),
                    shape = RoundedCornerShape(4.dp)
                ) {
                    Text(
                        when(item.status) {
                            "pending" -> "Menunggu konfirmasi"
                            else -> "Belum dilaporkan"
                        },
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                        fontSize = 10.sp,
                        color = statusColor,
                        fontWeight = FontWeight.Medium
                    )
                }
            }
            
            Column(horizontalAlignment = Alignment.End) {
                Surface(
                    color = Color(0xFF2196F3).copy(alpha = 0.1f),
                    shape = RoundedCornerShape(4.dp)
                ) {
                    Text(
                        "${item.timeStart ?: ""} - ${item.timeEnd ?: ""}",
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        fontSize = 11.sp,
                        color = Color(0xFF2196F3),
                        fontWeight = FontWeight.Medium
                    )
                }
                if (item.arrivalTime != null) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Default.Login,
                            null,
                            tint = Color(0xFF4CAF50),
                            modifier = Modifier.size(12.dp)
                        )
                        Spacer(modifier = Modifier.width(2.dp))
                        Text(
                            item.arrivalTime,
                            fontSize = 10.sp,
                            color = Color(0xFF4CAF50)
                        )
                    }
                }
                if (item.isCurrentPeriod) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Surface(
                        color = Color(0xFFF44336).copy(alpha = 0.1f),
                        shape = RoundedCornerShape(4.dp)
                    ) {
                        Text(
                            "Sedang berlangsung",
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp),
                            fontSize = 9.sp,
                            color = Color(0xFFF44336),
                            fontWeight = FontWeight.Bold
                        )
                    }                }
            }
        }
    }
}

@Composable
private fun EmptyContent(date: String, day: String) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(Color(0xFF4CAF50).copy(alpha = 0.1f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Default.CheckCircle,
                    contentDescription = null,
                    tint = Color(0xFF4CAF50),
                    modifier = Modifier.size(50.dp)
                )
            }
            Spacer(modifier = Modifier.height(24.dp))
            Text(
                "Tidak Ada Pending",
                fontWeight = FontWeight.Bold,
                fontSize = 20.sp
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                "Semua kehadiran guru pada $day sudah dikonfirmasi",
                color = Color.Gray,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                date,
                color = Color(0xFF4CAF50),
                fontWeight = FontWeight.Medium
            )
        }
    }
}

@Composable
private fun ErrorContent(
    message: String,
    onRetry: () -> Unit
) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Icon(
                Icons.Default.Error,
                contentDescription = null,
                tint = Color(0xFFF44336),
                modifier = Modifier.size(64.dp)
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                "Terjadi Kesalahan",
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                message,
                color = Color.Gray,
                textAlign = TextAlign.Center
            )
            Spacer(modifier = Modifier.height(24.dp))
            Button(
                onClick = onRetry,
                colors = ButtonDefaults.buttonColors(
                    containerColor = Color(0xFF7C4DFF)
                )
            ) {
                Icon(Icons.Default.Refresh, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Coba Lagi")
            }
        }
    }
}
