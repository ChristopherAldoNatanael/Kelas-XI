package com.christopheraldoo.aplikasimonitoringkelas.ui.screens

import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.aplikasimonitoringkelas.data.RiwayatItem
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.RiwayatUiState
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.SiswaViewModel

// ============================================================================
// PROFESSIONAL COLOR PALETTE FOR RIWAYAT SCREEN
// ============================================================================
private object RiwayatColors {
    // Primary gradient colors - Purple/Indigo theme for history
    val GradientStart = Color(0xFF5C6BC0)
    val GradientMid = Color(0xFF7986CB)
    val GradientEnd = Color(0xFF9FA8DA)
    
    // Accent colors
    val AccentIndigo = Color(0xFF3F51B5)
    val AccentDeepPurple = Color(0xFF673AB7)
    
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
}

// Filter options for history - support both "telat" and "terlambat" from server
private enum class RiwayatFilter(val label: String, val values: List<String>) {
    ALL("Semua", emptyList()),
    HADIR("Hadir", listOf("hadir")),
    TERLAMBAT("Terlambat", listOf("telat", "terlambat")), // Support both variants
    TIDAK_HADIR("Tidak Hadir", listOf("tidak_hadir")),
    DIGANTI("Diganti", listOf("diganti"))
}

// ============================================================================
// RIWAYAT SCREEN MAIN COMPOSABLE
// ============================================================================
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RiwayatScreen(viewModel: SiswaViewModel) {
    val context = LocalContext.current
    
    // Observe state from ViewModel
    val uiState by viewModel.riwayatState.collectAsState()

    // Filter state
    var selectedFilter by remember { mutableStateOf(RiwayatFilter.ALL) }

    // Load data when the screen is first launched
    LaunchedEffect(Unit) {
        viewModel.loadRiwayat()
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(RiwayatColors.ScreenBackground)
    ) {
        when (val state = uiState) {
            is RiwayatUiState.Loading -> {
                RiwayatLoadingState()
            }
            is RiwayatUiState.Error -> {
                RiwayatErrorState(
                    message = state.message,
                    onRetry = { viewModel.loadRiwayat(forceRefresh = true) }
                )
            }            is RiwayatUiState.Success -> {
                val filteredData = if (selectedFilter == RiwayatFilter.ALL) {
                    state.data
                } else {
                    state.data.filter { selectedFilter.values.contains(it.status) }
                }
                
                RiwayatContent(
                    allData = state.data,
                    filteredData = filteredData,
                    selectedFilter = selectedFilter,
                    onFilterChanged = { selectedFilter = it },
                    onRefresh = { viewModel.loadRiwayat(forceRefresh = true) }
                )
            }
        }
    }
}

// ============================================================================
// RIWAYAT CONTENT
// ============================================================================
@Composable
private fun RiwayatContent(
    allData: List<RiwayatItem>,
    filteredData: List<RiwayatItem>,
    selectedFilter: RiwayatFilter,
    onFilterChanged: (RiwayatFilter) -> Unit,
    onRefresh: () -> Unit
) {    // Calculate stats from all data - support both "telat" and "terlambat"
    val stats = RiwayatStats(
        total = allData.size,
        hadir = allData.count { it.status == "hadir" },
        terlambat = allData.count { it.status == "terlambat" || it.status == "telat" },
        tidakHadir = allData.count { it.status == "tidak_hadir" },
        diganti = allData.count { it.status == "diganti" }
    )
    
    // Group filtered data by date
    val groupedData = filteredData.groupBy { it.tanggal }
    
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 24.dp)
    ) {
        // Header Card
        item {
            RiwayatHeaderCard(
                stats = stats,
                onRefresh = onRefresh
            )
        }
        
        // Stats Summary
        item {
            RiwayatStatsSummary(stats = stats)
        }
        
        // Filter Chips
        item {
            RiwayatFilterChips(
                selectedFilter = selectedFilter,
                onFilterChanged = onFilterChanged,
                stats = stats
            )
        }
        
        // Content Title
        item {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Riwayat Kehadiran",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = RiwayatColors.DarkText
                )
                
                Text(
                    text = "${filteredData.size} catatan",
                    fontSize = 13.sp,
                    color = RiwayatColors.LightText
                )
            }
        }
          // Empty State or Grouped Cards
        if (filteredData.isEmpty()) {
            item {
                RiwayatEmptyState(selectedFilter = selectedFilter)
            }
        } else {
            groupedData.forEach { (tanggal, items) ->
                // Date Header
                item(key = "header_$tanggal") {
                    RiwayatDateHeader(tanggal = tanggal, itemCount = items.size)
                }
                
                // Cards for this date - use unique ID as key
                items(items, key = { "riwayat_${it.id}" }) { item ->
                    RiwayatCard(item = item)
                }
            }
        }

    }
}

// ============================================================================
// HEADER CARD WITH GRADIENT
// ============================================================================
@Composable
private fun RiwayatHeaderCard(
    stats: RiwayatStats,
    onRefresh: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                brush = Brush.linearGradient(
                    colors = listOf(
                        RiwayatColors.GradientStart,
                        RiwayatColors.GradientMid,
                        RiwayatColors.GradientEnd
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
                        text = "Riwayat Kehadiran",
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.History,
                            contentDescription = null,
                            tint = Color.White.copy(alpha = 0.9f),
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Laporan kehadiran guru",
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
            
            // Quick Stats
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                HeaderQuickStat(
                    value = stats.total.toString(),
                    label = "Total",
                    icon = Icons.Outlined.Assessment
                )
                
                // Attendance rate
                val attendanceRate = if (stats.total > 0) {
                    ((stats.hadir.toFloat() / stats.total) * 100).toInt()
                } else 0
                
                HeaderQuickStat(
                    value = "$attendanceRate%",
                    label = "Kehadiran",
                    icon = Icons.Outlined.TrendingUp
                )
                
                HeaderQuickStat(
                    value = stats.tidakHadir.toString(),
                    label = "Absen",
                    icon = Icons.Outlined.Warning
                )
            }
        }
    }
}

@Composable
private fun HeaderQuickStat(
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
// STATS SUMMARY SECTION
// ============================================================================
private data class RiwayatStats(
    val total: Int,
    val hadir: Int,
    val terlambat: Int,
    val tidakHadir: Int,
    val diganti: Int
)

@Composable
private fun RiwayatStatsSummary(stats: RiwayatStats) {
    if (stats.total > 0) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 12.dp),
            shape = RoundedCornerShape(16.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
            colors = CardDefaults.cardColors(containerColor = RiwayatColors.CardBackground)
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Text(
                    text = "Statistik Kehadiran Guru",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = RiwayatColors.DarkText
                )
                
                Spacer(modifier = Modifier.height(12.dp))
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    StatsSummaryItem(
                        count = stats.hadir,
                        label = "Hadir",
                        color = RiwayatColors.SuccessGreen,
                        backgroundColor = RiwayatColors.SuccessGreenLight
                    )
                    StatsSummaryItem(
                        count = stats.terlambat,
                        label = "Telat",
                        color = RiwayatColors.WarningOrange,
                        backgroundColor = RiwayatColors.WarningOrangeLight
                    )
                    StatsSummaryItem(
                        count = stats.tidakHadir,
                        label = "Absen",
                        color = RiwayatColors.ErrorRed,
                        backgroundColor = RiwayatColors.ErrorRedLight
                    )
                    StatsSummaryItem(
                        count = stats.diganti,
                        label = "Diganti",
                        color = RiwayatColors.PurpleAccent,
                        backgroundColor = RiwayatColors.PurpleLight
                    )
                }
            }
        }
    }
}

@Composable
private fun StatsSummaryItem(
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
            color = RiwayatColors.MediumText
        )
    }
}

// ============================================================================
// FILTER CHIPS
// ============================================================================
@Composable
private fun RiwayatFilterChips(
    selectedFilter: RiwayatFilter,
    onFilterChanged: (RiwayatFilter) -> Unit,
    stats: RiwayatStats
) {
    LazyRow(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        contentPadding = PaddingValues(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(RiwayatFilter.values().toList()) { filter ->
            val count = when (filter) {
                RiwayatFilter.ALL -> stats.total
                RiwayatFilter.HADIR -> stats.hadir
                RiwayatFilter.TERLAMBAT -> stats.terlambat
                RiwayatFilter.TIDAK_HADIR -> stats.tidakHadir
                RiwayatFilter.DIGANTI -> stats.diganti
            }
            
            val chipColor = when (filter) {
                RiwayatFilter.ALL -> RiwayatColors.AccentIndigo
                RiwayatFilter.HADIR -> RiwayatColors.SuccessGreen
                RiwayatFilter.TERLAMBAT -> RiwayatColors.WarningOrange
                RiwayatFilter.TIDAK_HADIR -> RiwayatColors.ErrorRed
                RiwayatFilter.DIGANTI -> RiwayatColors.PurpleAccent
            }
            
            val isSelected = selectedFilter == filter
            
            FilterChip(
                selected = isSelected,
                onClick = { onFilterChanged(filter) },
                label = {
                    Text(
                        text = "${filter.label} ($count)",
                        fontWeight = if (isSelected) FontWeight.SemiBold else FontWeight.Normal
                    )
                },
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = chipColor.copy(alpha = 0.15f),
                    selectedLabelColor = chipColor
                ),
                border = FilterChipDefaults.filterChipBorder(
                    enabled = true,
                    selected = isSelected,
                    borderColor = if (isSelected) chipColor else RiwayatColors.DividerColor,
                    selectedBorderColor = chipColor
                )
            )
        }
    }
}

// ============================================================================
// DATE HEADER
// ============================================================================
@Composable
private fun RiwayatDateHeader(tanggal: String, itemCount: Int) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Surface(
                shape = RoundedCornerShape(8.dp),
                color = RiwayatColors.GradientStart.copy(alpha = 0.1f)
            ) {
                Icon(
                    imageVector = Icons.Outlined.CalendarToday,
                    contentDescription = null,
                    modifier = Modifier.padding(8.dp),
                    tint = RiwayatColors.GradientStart
                )
            }
            Spacer(modifier = Modifier.width(12.dp))
            Text(
                text = tanggal,
                fontSize = 15.sp,
                fontWeight = FontWeight.SemiBold,
                color = RiwayatColors.DarkText
            )
        }
        
        Surface(
            shape = RoundedCornerShape(12.dp),
            color = RiwayatColors.DividerColor
        ) {
            Text(
                text = "$itemCount jam",
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                fontSize = 12.sp,
                color = RiwayatColors.MediumText
            )
        }
    }
}

// ============================================================================
// RIWAYAT CARD - MODERN DESIGN
// ============================================================================
@Composable
private fun RiwayatCard(item: RiwayatItem) {
    var isExpanded by remember { mutableStateOf(false) }
    
    val borderColor = when (item.status) {
        "hadir" -> RiwayatColors.SuccessGreen
        "terlambat" -> RiwayatColors.WarningOrange
        "tidak_hadir" -> RiwayatColors.ErrorRed
        "diganti" -> RiwayatColors.PurpleAccent
        else -> RiwayatColors.DividerColor
    }
    
    val backgroundColor = when (item.status) {
        "hadir" -> RiwayatColors.SuccessGreenLight
        "terlambat" -> RiwayatColors.WarningOrangeLight
        "tidak_hadir" -> RiwayatColors.ErrorRedLight
        "diganti" -> RiwayatColors.PurpleLight
        else -> RiwayatColors.CardBackground
    }
    
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp)
            .border(
                width = 1.5.dp,
                color = borderColor,
                shape = RoundedCornerShape(14.dp)
            )
            .animateContentSize()
            .clickable { isExpanded = !isExpanded },
        shape = RoundedCornerShape(14.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = backgroundColor)
    ) {
        Column(
            modifier = Modifier.padding(14.dp)
        ) {
            // Main Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                // Left side - Subject and info
                Column(
                    modifier = Modifier.weight(1f)
                ) {
                    // Subject
                    Text(
                        text = item.subject,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = RiwayatColors.DarkText,
                        maxLines = if (isExpanded) Int.MAX_VALUE else 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    // Teacher
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Person,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = RiwayatColors.MediumText
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = item.teacher,
                            fontSize = 13.sp,
                            color = RiwayatColors.MediumText,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(4.dp))
                      // Time and Period
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Outlined.Schedule,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = RiwayatColors.LightText
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "${item.day} • P${item.period} • ${item.time}",
                            fontSize = 12.sp,
                            color = RiwayatColors.LightText
                        )
                    }
                    
                    // Show jam masuk for terlambat/telat status
                    if ((item.status == "telat" || item.status == "terlambat") && !item.jamMasuk.isNullOrBlank()) {
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = Icons.Default.WatchLater,
                                contentDescription = null,
                                modifier = Modifier.size(14.dp),
                                tint = RiwayatColors.WarningOrange
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = "Masuk: ${item.jamMasuk}",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = RiwayatColors.WarningOrange
                            )
                        }
                    }
                }
                
                Spacer(modifier = Modifier.width(12.dp))
                
                // Right side - Status Badge
                RiwayatStatusBadge(status = item.status)
            }
              // Expanded content - Catatan
            if (isExpanded && item.catatan.isNotBlank()) {
                Spacer(modifier = Modifier.height(10.dp))
                Divider(color = RiwayatColors.DividerColor.copy(alpha = 0.5f))
                Spacer(modifier = Modifier.height(10.dp))
                
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = Color.White.copy(alpha = 0.7f)
                ) {
                    Row(
                        modifier = Modifier.padding(10.dp),
                        verticalAlignment = Alignment.Top
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Notes,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp),
                            tint = RiwayatColors.MediumText
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = item.catatan,
                            fontSize = 13.sp,
                            color = RiwayatColors.MediumText,
                            lineHeight = 18.sp
                        )
                    }
                }
            }
            
            // Show expand hint for items with catatan
            if (!isExpanded && item.catatan.isNotBlank()) {
                Spacer(modifier = Modifier.height(6.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.ExpandMore,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp),
                        tint = RiwayatColors.LightText
                    )
                    Text(
                        text = "Lihat catatan",
                        fontSize = 11.sp,
                        color = RiwayatColors.LightText
                    )
                }
            }
        }
    }
}

@Composable
private fun RiwayatStatusBadge(status: String) {
    val (text, bgColor, textColor, icon) = when (status) {
        "hadir" -> RiwayatStatusConfig(
            "HADIR",
            RiwayatColors.SuccessGreen.copy(alpha = 0.2f),
            RiwayatColors.SuccessGreen,
            Icons.Default.CheckCircle
        )
        "telat", "terlambat" -> RiwayatStatusConfig(
            "TELAT",
            RiwayatColors.WarningOrange.copy(alpha = 0.2f),
            RiwayatColors.WarningOrange,
            Icons.Default.WatchLater
        )
        "tidak_hadir" -> RiwayatStatusConfig(
            "ABSEN",
            RiwayatColors.ErrorRed.copy(alpha = 0.2f),
            RiwayatColors.ErrorRed,
            Icons.Default.Cancel
        )
        "diganti" -> RiwayatStatusConfig(
            "DIGANTI",
            RiwayatColors.PurpleAccent.copy(alpha = 0.2f),
            RiwayatColors.PurpleAccent,
            Icons.Default.SwapHoriz
        )
        else -> RiwayatStatusConfig(
            "?",
            RiwayatColors.DividerColor,
            RiwayatColors.MediumText,
            Icons.Outlined.Help
        )
    }
    
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = bgColor
    ) {
        Column(
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(18.dp),
                tint = textColor
            )
            Spacer(modifier = Modifier.height(2.dp))
            Text(
                text = text,
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                color = textColor
            )
        }
    }
}

private data class RiwayatStatusConfig(
    val text: String,
    val bgColor: Color,
    val textColor: Color,
    val icon: ImageVector
)

// ============================================================================
// STATE SCREENS
// ============================================================================
@Composable
private fun RiwayatLoadingState() {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(56.dp),
                color = RiwayatColors.GradientStart,
                strokeWidth = 4.dp
            )
            Spacer(modifier = Modifier.height(20.dp))
            Text(
                text = "Memuat riwayat kehadiran...",
                fontSize = 16.sp,
                color = RiwayatColors.MediumText
            )
        }
    }
}

@Composable
private fun RiwayatErrorState(
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
                color = RiwayatColors.ErrorRedLight,
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.ErrorOutline,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = RiwayatColors.ErrorRed
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = "Terjadi Kesalahan",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = RiwayatColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = message,
                fontSize = 14.sp,
                color = RiwayatColors.MediumText,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Button(
                onClick = onRetry,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = RiwayatColors.GradientStart
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
private fun RiwayatEmptyState(selectedFilter: RiwayatFilter) {
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
                color = RiwayatColors.InfoBlueLight,
                modifier = Modifier.size(80.dp)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        imageVector = Icons.Outlined.History,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = RiwayatColors.InfoBlue
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            Text(
                text = if (selectedFilter == RiwayatFilter.ALL) 
                    "Belum Ada Riwayat" 
                else 
                    "Tidak Ada Data",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = RiwayatColors.DarkText
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = if (selectedFilter == RiwayatFilter.ALL)
                    "Belum ada riwayat kehadiran guru yang tercatat"
                else
                    "Tidak ada riwayat dengan status \"${selectedFilter.label}\"",
                fontSize = 14.sp,
                color = RiwayatColors.MediumText,
                textAlign = TextAlign.Center
            )
        }
    }
}
