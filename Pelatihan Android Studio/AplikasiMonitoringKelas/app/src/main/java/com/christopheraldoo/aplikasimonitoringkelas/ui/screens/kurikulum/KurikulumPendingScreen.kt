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
    
    // Track selected items for bulk confirm
    var selectedItems by remember { mutableStateOf(setOf<Int>()) }
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
    
    // Handle confirm state changes
    LaunchedEffect(confirmState) {
        when (confirmState) {
            is ConfirmAttendanceUiState.Success -> {
                selectedItems = emptySet()
                delay(1500)
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
                        groupedByClass = state.groupedByClass,
                        selectedItems = selectedItems,
                        onItemSelect = { id ->
                            selectedItems = if (selectedItems.contains(id)) {
                                selectedItems - id
                            } else {
                                selectedItems + id
                            }
                        },
                        onSelectAll = { items ->
                            selectedItems = if (selectedItems.containsAll(items)) {
                                selectedItems - items.toSet()
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
    
    // Single Confirm Dialog
    showSingleConfirmDialog?.let { item ->
        AlertDialog(
            onDismissRequest = { showSingleConfirmDialog = null },
            icon = { 
                Icon(
                    Icons.Default.Person,
                    null,
                    tint = Color(0xFF7C4DFF),
                    modifier = Modifier.size(32.dp)
                )
            },
            title = { Text("Konfirmasi Kehadiran") },
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
                    if (item.arrivalTime != null) {
                        Spacer(modifier = Modifier.height(8.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                Icons.Outlined.Schedule,
                                null,
                                tint = Color(0xFF7C4DFF),
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                "Jam masuk: ${item.arrivalTime}",
                                fontSize = 14.sp,
                                color = Color(0xFF7C4DFF)
                            )
                        }
                    }
                }
            },
            confirmButton = {
                Row {
                    FilledTonalButton(
                        onClick = {
                            viewModel.confirmAttendance(item.id, "hadir")
                            showSingleConfirmDialog = null
                        },
                        colors = ButtonDefaults.filledTonalButtonColors(
                            containerColor = Color(0xFF4CAF50).copy(alpha = 0.1f),
                            contentColor = Color(0xFF4CAF50)
                        )
                    ) {
                        Icon(Icons.Default.Check, null, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Hadir")
                    }
                    Spacer(modifier = Modifier.width(8.dp))
                    FilledTonalButton(
                        onClick = {
                            viewModel.confirmAttendance(item.id, "telat")
                            showSingleConfirmDialog = null
                        },
                        colors = ButtonDefaults.filledTonalButtonColors(
                            containerColor = Color(0xFFFF9800).copy(alpha = 0.1f),
                            contentColor = Color(0xFFFF9800)
                        )
                    ) {
                        Icon(Icons.Default.Schedule, null, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Telat")
                    }
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
    groupedByClass: List<PendingClassGroup>,
    selectedItems: Set<Int>,
    onItemSelect: (Int) -> Unit,
    onSelectAll: (List<Int>) -> Unit,
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
                    containerColor = Color(0xFFFFF3E0)
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(56.dp)
                            .clip(CircleShape)
                            .background(Color(0xFFFF9800)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.HourglassEmpty,
                            contentDescription = null,
                            tint = Color.White,
                            modifier = Modifier.size(28.dp)
                        )
                    }
                    Spacer(modifier = Modifier.width(16.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            "$totalPending Menunggu Konfirmasi",
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            color = Color(0xFFE65100)
                        )
                        Text(
                            "$day, $date",
                            color = Color(0xFFFF9800),
                            fontSize = 14.sp
                        )
                    }
                    IconButton(onClick = onRefresh) {
                        Icon(
                            Icons.Default.Refresh,
                            contentDescription = "Refresh",
                            tint = Color(0xFFFF9800)
                        )
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
    selectedItems: Set<Int>,
    onItemSelect: (Int) -> Unit,
    onSelectAll: (List<Int>) -> Unit,
    onItemClick: (PendingAttendanceItem) -> Unit
) {
    var expanded by remember { mutableStateOf(true) }
    val allIds = classGroup.schedules.map { it.id }
    val allSelected = allIds.all { selectedItems.contains(it) }
    
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
                Checkbox(
                    checked = allSelected,
                    onCheckedChange = { onSelectAll(allIds) },
                    colors = CheckboxDefaults.colors(
                        checkedColor = Color(0xFF7C4DFF)
                    )
                )
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        classGroup.className,
                        fontWeight = FontWeight.Bold,
                        fontSize = 16.sp
                    )
                    Text(
                        "${classGroup.totalPending} guru menunggu konfirmasi",
                        color = Color.Gray,
                        fontSize = 12.sp
                    )
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
                            isSelected = selectedItems.contains(item.id),
                            onSelect = { onItemSelect(item.id) },
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
            Checkbox(
                checked = isSelected,
                onCheckedChange = { onSelect() },
                colors = CheckboxDefaults.colors(
                    checkedColor = Color(0xFF7C4DFF)
                )
            )
            
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
            }
            
            Column(horizontalAlignment = Alignment.End) {
                Surface(
                    color = Color(0xFFFF9800).copy(alpha = 0.1f),
                    shape = RoundedCornerShape(4.dp)
                ) {
                    Text(
                        "${item.timeStart ?: ""} - ${item.timeEnd ?: ""}",
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        fontSize = 11.sp,
                        color = Color(0xFFFF9800),
                        fontWeight = FontWeight.Medium
                    )
                }
                if (item.arrivalTime != null) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        "Masuk: ${item.arrivalTime}",
                        fontSize = 10.sp,
                        color = Color(0xFF7C4DFF)
                    )
                }
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
