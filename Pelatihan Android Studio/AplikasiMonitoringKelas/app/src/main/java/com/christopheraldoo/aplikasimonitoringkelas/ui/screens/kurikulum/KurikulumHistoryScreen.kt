package com.christopheraldoo.aplikasimonitoringkelas.ui.screens.kurikulum

import android.content.Context
import android.content.Intent
import android.os.Environment
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.ExportUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.HistoryUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KurikulumViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.StatisticsUiState
import java.io.File
import java.io.FileWriter
import java.text.SimpleDateFormat
import java.util.*

/**
 * History Screen for Kurikulum
 * Shows attendance history with filters, statistics, and export functionality
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KurikulumHistoryScreen(
    viewModel: KurikulumViewModel
) {
    val historyState by viewModel.historyState.collectAsState()
    val statisticsState by viewModel.statisticsState.collectAsState()
    val exportState by viewModel.exportState.collectAsState()
    val filterClasses by viewModel.filterClasses.collectAsState()
    val filterTeachers by viewModel.filterTeachers.collectAsState()
    
    var showFilterSheet by remember { mutableStateOf(false) }
    var showStatisticsDialog by remember { mutableStateOf(false) }
    var showExportDialog by remember { mutableStateOf(false) }
    
    // Filter states
    var selectedDateFrom by remember { mutableStateOf<String?>(null) }
    var selectedDateTo by remember { mutableStateOf<String?>(null) }
    var selectedTeacherId by remember { mutableStateOf<Int?>(null) }
    var selectedClassId by remember { mutableStateOf<Int?>(null) }
    var selectedStatus by remember { mutableStateOf<String?>(null) }
    
    // Tab state: 0 = History, 1 = Statistics
    var selectedTab by remember { mutableStateOf(0) }
    
    val context = LocalContext.current
    
    LaunchedEffect(Unit) {
        viewModel.loadHistory(refresh = true)
        viewModel.loadStatistics()
        viewModel.loadFilterData()
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        // Header
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
                    Text(
                        text = "Riwayat Kehadiran",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                    
                    Row {
                        IconButton(onClick = { showFilterSheet = true }) {
                            Icon(
                                imageVector = Icons.Default.FilterList,
                                contentDescription = "Filter",
                                tint = MaterialTheme.colorScheme.onPrimaryContainer
                            )
                        }
                        IconButton(onClick = { showExportDialog = true }) {
                            Icon(
                                imageVector = Icons.Default.Download,
                                contentDescription = "Export",
                                tint = MaterialTheme.colorScheme.onPrimaryContainer
                            )
                        }
                    }
                }
            }
        }
        
        // Tabs
        TabRow(
            selectedTabIndex = selectedTab,
            containerColor = MaterialTheme.colorScheme.surface
        ) {
            Tab(
                selected = selectedTab == 0,
                onClick = { selectedTab = 0 },
                text = { Text("Riwayat") },
                icon = { Icon(Icons.Default.History, contentDescription = null) }
            )
            Tab(
                selected = selectedTab == 1,
                onClick = { selectedTab = 1 },
                text = { Text("Statistik") },
                icon = { Icon(Icons.Default.BarChart, contentDescription = null) }
            )
        }
        
        // Content based on selected tab
        when (selectedTab) {
            0 -> HistoryContent(
                historyState = historyState,
                onLoadMore = {
                    viewModel.loadMoreHistory(
                        dateFrom = selectedDateFrom,
                        dateTo = selectedDateTo,
                        teacherId = selectedTeacherId,
                        classId = selectedClassId,
                        status = selectedStatus
                    )
                },
                onRefresh = {
                    viewModel.loadHistory(
                        dateFrom = selectedDateFrom,
                        dateTo = selectedDateTo,
                        teacherId = selectedTeacherId,
                        classId = selectedClassId,
                        status = selectedStatus,
                        refresh = true
                    )
                }
            )
            
            1 -> StatisticsContent(
                statisticsState = statisticsState,
                onMonthChange = { month, year ->
                    viewModel.loadStatistics(month, year)
                }
            )
        }
    }
    
    // Filter Bottom Sheet
    if (showFilterSheet) {
        HistoryFilterSheet(
            classes = filterClasses,
            teachers = filterTeachers,
            selectedDateFrom = selectedDateFrom,
            selectedDateTo = selectedDateTo,
            selectedTeacherId = selectedTeacherId,
            selectedClassId = selectedClassId,
            selectedStatus = selectedStatus,
            onDateFromChange = { selectedDateFrom = it },
            onDateToChange = { selectedDateTo = it },
            onTeacherChange = { selectedTeacherId = it },
            onClassChange = { selectedClassId = it },
            onStatusChange = { selectedStatus = it },
            onDismiss = { showFilterSheet = false },
            onApply = {
                showFilterSheet = false
                viewModel.loadHistory(
                    dateFrom = selectedDateFrom,
                    dateTo = selectedDateTo,
                    teacherId = selectedTeacherId,
                    classId = selectedClassId,
                    status = selectedStatus,
                    refresh = true
                )
            },
            onReset = {
                selectedDateFrom = null
                selectedDateTo = null
                selectedTeacherId = null
                selectedClassId = null
                selectedStatus = null
            }
        )
    }
    
    // Export Dialog
    if (showExportDialog) {
        ExportDialog(
            exportState = exportState,
            onExport = { format ->
                viewModel.exportData(
                    dateFrom = selectedDateFrom,
                    dateTo = selectedDateTo,
                    teacherId = selectedTeacherId,
                    classId = selectedClassId
                )
            },
            onSaveFile = { data ->
                saveExportToFile(context, data)
            },
            onDismiss = {
                showExportDialog = false
                viewModel.resetExportState()
            }
        )
    }
}

@Composable
private fun HistoryContent(
    historyState: HistoryUiState,
    onLoadMore: () -> Unit,
    onRefresh: () -> Unit
) {
    when (historyState) {
        is HistoryUiState.Loading -> {
            Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator()
            }
        }
        
        is HistoryUiState.Error -> {
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
                Text(text = historyState.message)
                Spacer(modifier = Modifier.height(16.dp))
                Button(onClick = onRefresh) {
                    Text("Coba Lagi")
                }
            }
        }
        
        is HistoryUiState.Success -> {
            if (historyState.items.isEmpty()) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.History,
                        contentDescription = null,
                        modifier = Modifier.size(64.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        text = "Tidak ada riwayat kehadiran",
                        style = MaterialTheme.typography.bodyLarge
                    )
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Summary header
                    item {
                        HistorySummaryCard(pagination = historyState.pagination)
                    }
                    
                    items(historyState.items, key = { it.id }) { item ->
                        HistoryItemCard(item = item)
                    }
                    
                    // Load more indicator
                    if (historyState.hasMore) {
                        item {
                            Box(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(16.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                CircularProgressIndicator(modifier = Modifier.size(24.dp))
                            }
                            
                            LaunchedEffect(Unit) {
                                onLoadMore()
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun HistorySummaryCard(pagination: PaginationInfo) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Total: ${pagination.total} records",
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = "Halaman ${pagination.currentPage} dari ${pagination.lastPage}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun HistoryItemCard(item: KurikulumAttendanceHistoryItem) {
    val statusColor = when (item.status ?: "pending") {
        "hadir" -> Color(0xFF4CAF50)
        "telat" -> Color(0xFFFFC107)
        "tidak_hadir" -> Color(0xFFF44336)
        "diganti" -> Color(0xFF2196F3)
        else -> Color(0xFF9E9E9E)
    }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.Top
        ) {
            // Date column
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.width(60.dp)
            ) {
                val date = try {
                    SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).parse(item.date)
                } catch (e: Exception) {
                    null
                }
                
                Text(
                    text = date?.let { SimpleDateFormat("dd", Locale.getDefault()).format(it) } ?: "--",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Text(
                    text = date?.let { SimpleDateFormat("MMM", Locale.getDefault()).format(it) } ?: "",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            // Main content
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = item.className,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium
                )
                Text(
                    text = item.subjectName,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(top = 4.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Person,
                        contentDescription = null,
                        modifier = Modifier.size(14.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = item.teacherName,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                
                if (item.isSubstituted && item.originalTeacherName != null) {
                    Text(
                        text = "Pengganti dari: ${item.originalTeacherName}",
                        style = MaterialTheme.typography.labelSmall,
                        color = Color(0xFF2196F3),
                        modifier = Modifier.padding(top = 2.dp)
                    )
                }
                
                if (item.time != null) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(top = 2.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Schedule,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Jam ${item.period ?: "-"}: ${item.time}",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
                
                if (!item.keterangan.isNullOrEmpty()) {
                    Surface(
                        modifier = Modifier.padding(top = 4.dp),
                        shape = RoundedCornerShape(4.dp),
                        color = MaterialTheme.colorScheme.surfaceVariant
                    ) {
                        Text(
                            text = item.keterangan,
                            style = MaterialTheme.typography.labelSmall,
                            modifier = Modifier.padding(4.dp)
                        )
                    }
                }
            }
            
            // Status
            Column(
                horizontalAlignment = Alignment.End
            ) {
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = statusColor.copy(alpha = 0.15f)
                ) {                    Text(
                        text = when (item.status ?: "pending") {
                            "hadir" -> "Hadir"
                            "telat" -> "Telat"
                            "tidak_hadir" -> "Tidak Hadir"
                            "diganti" -> "Diganti"
                            else -> item.status ?: "Pending"
                        },
                        style = MaterialTheme.typography.labelSmall,
                        color = statusColor,
                        fontWeight = FontWeight.Medium,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
                
                if (item.arrivalTime != null) {
                    Text(
                        text = "Masuk: ${item.arrivalTime}",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(top = 4.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun StatisticsContent(
    statisticsState: StatisticsUiState,
    onMonthChange: (Int, Int) -> Unit
) {
    val currentDate = remember { Calendar.getInstance() }
    var selectedMonth by remember { mutableStateOf(currentDate.get(Calendar.MONTH) + 1) }
    var selectedYear by remember { mutableStateOf(currentDate.get(Calendar.YEAR)) }
    
    Column(
        modifier = Modifier.fillMaxSize()
    ) {
        // Month/Year selector
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = {
                if (selectedMonth == 1) {
                    selectedMonth = 12
                    selectedYear--
                } else {
                    selectedMonth--
                }
                onMonthChange(selectedMonth, selectedYear)
            }) {
                Icon(Icons.Default.ChevronLeft, "Previous month")
            }
            
            Text(
                text = "${getMonthName(selectedMonth)} $selectedYear",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(horizontal = 16.dp)
            )
            
            IconButton(onClick = {
                if (selectedMonth == 12) {
                    selectedMonth = 1
                    selectedYear++
                } else {
                    selectedMonth++
                }
                onMonthChange(selectedMonth, selectedYear)
            }) {
                Icon(Icons.Default.ChevronRight, "Next month")
            }
        }
        
        when (statisticsState) {
            is StatisticsUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }
            
            is StatisticsUiState.Error -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Text(text = statisticsState.message)
                }
            }
            
            is StatisticsUiState.Success -> {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    // Summary cards
                    item {
                        StatisticsSummaryCards(stats = statisticsState.statistics)
                    }
                    
                    // Percentage chart (simplified)
                    item {
                        PercentageChart(stats = statisticsState.statistics)
                    }
                    
                    // Teacher breakdown
                    if (statisticsState.teacherStats.isNotEmpty()) {
                        item {
                            Text(
                                text = "Statistik Per Guru",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        
                        items(statisticsState.teacherStats) { teacherStat ->
                            TeacherStatCard(stat = teacherStat)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StatisticsSummaryCards(stats: MonthlyStats) {
    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = "Ringkasan ${stats.monthName} ${stats.year}",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold
        )
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            StatCard(
                modifier = Modifier.weight(1f),
                value = stats.hadir.toString(),
                label = "Hadir",
                percentage = "${stats.percentage.hadir}%",
                color = Color(0xFF4CAF50)
            )
            StatCard(
                modifier = Modifier.weight(1f),
                value = stats.telat.toString(),
                label = "Telat",
                percentage = "${stats.percentage.telat}%",
                color = Color(0xFFFFC107)
            )
        }
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            StatCard(
                modifier = Modifier.weight(1f),
                value = stats.tidakHadir.toString(),
                label = "Tidak Hadir",
                percentage = "${stats.percentage.tidakHadir}%",
                color = Color(0xFFF44336)
            )
            StatCard(
                modifier = Modifier.weight(1f),
                value = stats.diganti.toString(),
                label = "Diganti",
                percentage = "${stats.percentage.diganti}%",
                color = Color(0xFF2196F3)
            )
        }
        
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer
            )
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Total Records",
                    style = MaterialTheme.typography.titleSmall
                )
                Text(
                    text = stats.totalRecords.toString(),
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }
    }
}

@Composable
private fun StatCard(
    modifier: Modifier = Modifier,
    value: String,
    label: String,
    percentage: String,
    color: Color
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = color.copy(alpha = 0.1f)
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = value,
                style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = color
            )
            Text(
                text = percentage,
                style = MaterialTheme.typography.labelSmall,
                color = color.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
private fun PercentageChart(stats: MonthlyStats) {
    Card(
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Text(
                text = "Distribusi Kehadiran",
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Horizontal bar chart
            val totalPercentage = stats.percentage.hadir + stats.percentage.telat + 
                stats.percentage.tidakHadir + stats.percentage.diganti
            
            if (totalPercentage > 0) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(24.dp)
                        .clip(RoundedCornerShape(12.dp))
                ) {
                    if (stats.percentage.hadir > 0) {
                        Box(
                            modifier = Modifier
                                .weight(stats.percentage.hadir)
                                .fillMaxHeight()
                                .background(Color(0xFF4CAF50))
                        )
                    }
                    if (stats.percentage.telat > 0) {
                        Box(
                            modifier = Modifier
                                .weight(stats.percentage.telat)
                                .fillMaxHeight()
                                .background(Color(0xFFFFC107))
                        )
                    }
                    if (stats.percentage.tidakHadir > 0) {
                        Box(
                            modifier = Modifier
                                .weight(stats.percentage.tidakHadir)
                                .fillMaxHeight()
                                .background(Color(0xFFF44336))
                        )
                    }
                    if (stats.percentage.diganti > 0) {
                        Box(
                            modifier = Modifier
                                .weight(stats.percentage.diganti)
                                .fillMaxHeight()
                                .background(Color(0xFF2196F3))
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(8.dp))
                
                // Legend
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    ChartLegendItem(color = Color(0xFF4CAF50), label = "Hadir")
                    ChartLegendItem(color = Color(0xFFFFC107), label = "Telat")
                    ChartLegendItem(color = Color(0xFFF44336), label = "Tidak Hadir")
                    ChartLegendItem(color = Color(0xFF2196F3), label = "Diganti")
                }
            } else {
                Text(
                    text = "Tidak ada data untuk ditampilkan",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        }
    }
}

@Composable
private fun ChartLegendItem(color: Color, label: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Box(
            modifier = Modifier
                .size(10.dp)
                .clip(CircleShape)
                .background(color)
        )
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall
        )
    }
}

@Composable
private fun TeacherStatCard(stat: TeacherStats) {
    Card(
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = stat.teacherName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Medium
                )
                Text(
                    text = "Total: ${stat.total} jadwal",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                StatBadge(count = stat.hadir, color = Color(0xFF4CAF50))
                StatBadge(count = stat.telat, color = Color(0xFFFFC107))
                StatBadge(count = stat.tidakHadir, color = Color(0xFFF44336))
            }
        }
    }
}

@Composable
private fun StatBadge(count: Int, color: Color) {
    Surface(
        shape = RoundedCornerShape(4.dp),
        color = color.copy(alpha = 0.15f)
    ) {
        Text(
            text = count.toString(),
            style = MaterialTheme.typography.labelSmall,
            color = color,
            fontWeight = FontWeight.Bold,
            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HistoryFilterSheet(
    classes: List<FilterClass>,
    teachers: List<FilterTeacher>,
    selectedDateFrom: String?,
    selectedDateTo: String?,
    selectedTeacherId: Int?,
    selectedClassId: Int?,
    selectedStatus: String?,
    onDateFromChange: (String?) -> Unit,
    onDateToChange: (String?) -> Unit,
    onTeacherChange: (Int?) -> Unit,
    onClassChange: (Int?) -> Unit,
    onStatusChange: (String?) -> Unit,
    onDismiss: () -> Unit,
    onApply: () -> Unit,
    onReset: () -> Unit
) {
    val sheetState = rememberModalBottomSheetState()
    
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(
                text = "Filter Riwayat",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Status filter
            Text(
                text = "Status",
                style = MaterialTheme.typography.labelMedium
            )
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.padding(vertical = 8.dp)
            ) {
                val statuses = listOf(
                    null to "Semua",
                    "hadir" to "Hadir",
                    "telat" to "Telat",
                    "tidak_hadir" to "Tidak Hadir",
                    "diganti" to "Diganti"
                )
                items(statuses) { (status, label) ->
                    FilterChip(
                        selected = selectedStatus == status,
                        onClick = { onStatusChange(status) },
                        label = { Text(label) }
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Class filter dropdown
            var classExpanded by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(
                expanded = classExpanded,
                onExpandedChange = { classExpanded = it }
            ) {                OutlinedTextField(
                    value = classes.find { it.id == selectedClassId }?.displayName ?: "Semua Kelas",
                    onValueChange = {},
                    readOnly = true,
                    label = { Text("Kelas") },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = classExpanded) },
                    modifier = Modifier
                        .fillMaxWidth()
                        .menuAnchor()
                )
                ExposedDropdownMenu(
                    expanded = classExpanded,
                    onDismissRequest = { classExpanded = false }
                ) {
                                        DropdownMenuItem(
                        text = { Text("Semua Kelas") },
                        onClick = {
                            onClassChange(null)
                            classExpanded = false
                        }
                    )
                    classes.forEach { classItem ->
                        DropdownMenuItem(
                            text = { Text(classItem.displayName) },
                            onClick = {
                                onClassChange(classItem.id)
                                classExpanded = false
                            }
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Teacher filter dropdown
            var teacherExpanded by remember { mutableStateOf(false) }
            ExposedDropdownMenuBox(
                expanded = teacherExpanded,
                onExpandedChange = { teacherExpanded = it }
            ) {
                OutlinedTextField(
                    value = teachers.find { it.id == selectedTeacherId }?.nama ?: "Semua Guru",
                    onValueChange = {},
                    readOnly = true,
                    label = { Text("Guru") },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = teacherExpanded) },
                    modifier = Modifier
                        .fillMaxWidth()
                        .menuAnchor()
                )
                ExposedDropdownMenu(
                    expanded = teacherExpanded,
                    onDismissRequest = { teacherExpanded = false }
                ) {
                    DropdownMenuItem(
                        text = { Text("Semua Guru") },
                        onClick = {
                            onTeacherChange(null)
                            teacherExpanded = false
                        }
                    )
                    teachers.forEach { teacher ->
                        DropdownMenuItem(
                            text = { Text(teacher.nama) },
                            onClick = {
                                onTeacherChange(teacher.id)
                                teacherExpanded = false
                            }
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Action buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onReset,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Reset")
                }
                Button(
                    onClick = onApply,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Terapkan")
                }
            }
            
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@Composable
private fun ExportDialog(
    exportState: ExportUiState,
    onExport: (String) -> Unit,
    onSaveFile: (List<ExportItem>) -> Unit,
    onDismiss: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Export Data") },
        text = {
            Column {
                when (exportState) {
                    is ExportUiState.Idle -> {
                        Text("Pilih format export:")
                        Spacer(modifier = Modifier.height(16.dp))
                        
                        Button(
                            onClick = { onExport("csv") },
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Icon(Icons.Default.TableChart, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Export ke CSV")
                        }
                    }
                    
                    is ExportUiState.Exporting -> {
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
                                Text("Menyiapkan data...")
                            }
                        }
                    }
                    
                    is ExportUiState.Success -> {
                        Column {
                            Icon(
                                imageVector = Icons.Default.CheckCircle,
                                contentDescription = null,
                                tint = Color(0xFF4CAF50),
                                modifier = Modifier
                                    .size(48.dp)
                                    .align(Alignment.CenterHorizontally)
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "Data siap diexport!",
                                style = MaterialTheme.typography.titleMedium,
                                textAlign = TextAlign.Center,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Text(
                                text = "${exportState.totalRecords} records",
                                style = MaterialTheme.typography.bodySmall,
                                textAlign = TextAlign.Center,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Button(
                                onClick = { onSaveFile(exportState.data) },
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Icon(Icons.Default.Save, contentDescription = null)
                                Spacer(modifier = Modifier.width(8.dp))
                                Text("Simpan File")
                            }
                        }
                    }
                    
                    is ExportUiState.Error -> {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Icon(
                                imageVector = Icons.Default.Error,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.error,
                                modifier = Modifier.size(48.dp)
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = exportState.message,
                                color = MaterialTheme.colorScheme.error,
                                textAlign = TextAlign.Center
                            )
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text("Tutup")
            }
        }
    )
}

private fun getMonthName(month: Int): String {
    return when (month) {
        1 -> "Januari"
        2 -> "Februari"
        3 -> "Maret"
        4 -> "April"
        5 -> "Mei"
        6 -> "Juni"
        7 -> "Juli"
        8 -> "Agustus"
        9 -> "September"
        10 -> "Oktober"
        11 -> "November"
        12 -> "Desember"
        else -> ""
    }
}

private fun saveExportToFile(context: Context, data: List<ExportItem>) {
    try {
        val fileName = "kehadiran_guru_${SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(Date())}.csv"
        val file = File(context.getExternalFilesDir(Environment.DIRECTORY_DOCUMENTS), fileName)
        
        FileWriter(file).use { writer ->
            // Header
            writer.append("Tanggal,Hari,Jam Ke,Waktu,Kelas,Mata Pelajaran,Guru Asli,Guru Pengganti,Status,Jam Masuk,Keterangan\n")              // Data
            data.forEach { item ->
                writer.append("${item.tanggal ?: ""},${item.hari ?: ""},${item.jamKe ?: ""},${item.waktu ?: ""},${item.kelas ?: ""},")
                writer.append("${item.mataPelajaran ?: ""},${item.guruAsli ?: ""},${item.guruPengganti ?: ""},")
                writer.append("${item.status ?: "pending"},${item.jamMasuk ?: ""},${item.keterangan ?: ""}\n")
            }
        }
        
        // Show success toast or share file
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(
                androidx.core.content.FileProvider.getUriForFile(
                    context,
                    "${context.packageName}.fileprovider",
                    file
                ),
                "text/csv"
            )
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, "Buka dengan..."))
        
    } catch (e: Exception) {
        e.printStackTrace()
    }
}
