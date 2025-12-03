package com.christopheraldoo.aplikasimonitoringkelas

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.List
import androidx.compose.material.icons.automirrored.filled.TrendingDown
import androidx.compose.material.icons.automirrored.filled.TrendingUp
import androidx.compose.material.icons.filled.*
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
import androidx.navigation.NavHostController
import androidx.navigation.compose.*
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import com.christopheraldoo.aplikasimonitoringkelas.ui.AppTheme
import com.christopheraldoo.aplikasimonitoringkelas.ui.components.BottomNavItem
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KepalaSekolahViewModel
import com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel.KepsekDashboardUiState
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import kotlinx.coroutines.*
import kotlinx.coroutines.launch

// Color constants - Izin is now Purple
private val ColorHadir = Color(0xFF4CAF50)      // Green
private val ColorTelat = Color(0xFFFF9800)       // Orange/Yellow
private val ColorTidakHadir = Color(0xFFF44336)  // Red
private val ColorIzin = Color(0xFF9C27B0)        // Purple

class KepalaSekolahActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            var isDarkMode by remember { mutableStateOf(false) }
            AppTheme(darkTheme = isDarkMode) {
                KepalaSekolahApp(
                    isDarkMode = isDarkMode,
                    onDarkModeToggle = { isDarkMode = !isDarkMode }
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KepalaSekolahApp(
    isDarkMode: Boolean = false,
    onDarkModeToggle: () -> Unit = {}
) {
    val context = LocalContext.current
    val navController = rememberNavController()
    val sessionManager = remember { SessionManager(context) }
    val repository = remember { NetworkRepository(context) }
    val viewModel = remember { KepalaSekolahViewModel(repository) }
    val scope = rememberCoroutineScope()

    // Start auto-refresh
    LaunchedEffect(Unit) {
        viewModel.startAutoRefresh()
    }

    DisposableEffect(Unit) {
        onDispose {
            viewModel.stopAutoRefresh()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Dashboard Kepala Sekolah") },
                actions = {
                    IconButton(onClick = onDarkModeToggle) {
                        Icon(
                            imageVector = if (isDarkMode) Icons.Default.LightMode else Icons.Default.DarkMode,
                            contentDescription = "Toggle dark mode"
                        )
                    }
                    IconButton(onClick = {
                        when (navController.currentDestination?.route) {
                            "dashboard" -> viewModel.loadDashboard()
                            "jadwal_pelajaran" -> { /* Will be handled in jadwal page */ }
                        }
                    }) {
                        Icon(Icons.Default.Refresh, "Refresh")
                    }
                    IconButton(onClick = {
                        scope.launch {
                            sessionManager.logout()
                            context.startActivity(Intent(context, LoginActivity::class.java))
                            (context as? ComponentActivity)?.finish()
                        }
                    }) {
                        Icon(Icons.Default.Logout, "Logout")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer,
                    titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer
                )
            )
        },
        bottomBar = {
            KepalaSekolahBottomNavigationBar(navController = navController)
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = "dashboard",
            modifier = Modifier.padding(paddingValues)
        ) {
            composable("dashboard") { 
                KepsekDashboardContent(viewModel = viewModel) 
            }
            composable("jadwal_pelajaran") { 
                KepsekJadwalPage() 
            }
        }
    }
}

@Composable
fun KepalaSekolahBottomNavigationBar(navController: NavHostController) {
    // Only 2 items now - removed Kelas Kosong
    val items = listOf(
        BottomNavItem("dashboard", "Dashboard", Icons.Default.Dashboard),
        BottomNavItem("jadwal_pelajaran", "Jadwal", Icons.Default.DateRange)
    )

    NavigationBar {
        val currentRoute = navController.currentBackStackEntryAsState().value?.destination?.route

        items.forEach { item ->
            NavigationBarItem(
                icon = {
                    Icon(
                        item.icon,
                        contentDescription = item.label,
                        tint = if (currentRoute == item.route)
                            MaterialTheme.colorScheme.primary
                        else
                            Color.Gray
                    )
                },
                label = {
                    Text(
                        item.label,
                        color = if (currentRoute == item.route)
                            MaterialTheme.colorScheme.primary
                        else
                            Color.Gray
                    )
                },
                selected = currentRoute == item.route,
                onClick = {
                    navController.navigate(item.route) {
                        navController.graph.startDestinationRoute?.let { route ->
                            popUpTo(route) {
                                saveState = true
                            }
                        }
                        launchSingleTop = true
                        restoreState = true
                    }
                }
            )
        }
    }
}

// ========== DASHBOARD CONTENT ==========

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KepsekDashboardContent(viewModel: KepalaSekolahViewModel) {
    val dashboardState by viewModel.dashboardState.collectAsState()
    val weekOffset by viewModel.weekOffset.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Week Selector
        WeekSelector(
            weekOffset = weekOffset,
            onWeekChange = { viewModel.setWeekOffset(it) }
        )

        Spacer(modifier = Modifier.height(16.dp))

        when (val state = dashboardState) {
            is KepsekDashboardUiState.Loading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator()
                        Spacer(modifier = Modifier.height(16.dp))
                        Text("Memuat data dashboard...")
                    }
                }
            }

            is KepsekDashboardUiState.Error -> {
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
                        Button(onClick = { viewModel.loadDashboard() }) {
                            Text("Coba Lagi")
                        }
                    }
                }
            }

            is KepsekDashboardUiState.Success -> {
                val data = state.data
                
                LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    // Week Info Header
                    item {
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
                                Column {
                                    Text(
                                        text = data.weekInfo.weekLabel,
                                        style = MaterialTheme.typography.titleLarge,
                                        fontWeight = FontWeight.Bold
                                    )
                                    Text(
                                        text = "${data.weekInfo.weekStart} - ${data.weekInfo.weekEnd}",
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.7f)
                                    )
                                }
                                Icon(
                                    Icons.Default.CalendarMonth,
                                    contentDescription = null,
                                    modifier = Modifier.size(32.dp)
                                )
                            }
                        }
                    }

                    // Statistics Cards (4 cards in 2x2 grid) - NO MORE ATTENDANCE RATE CARD WITH TREND
                    item {
                        Text(
                            text = "Statistik Kehadiran",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                    }

                    item {
                        StatisticsGridNew(stats = data.thisWeek)
                    }

                    // === NEW: Teachers Attendance Today Section ===
                    data.teachersAttendanceToday?.let { attendanceToday ->
                        item {
                            TeachersAttendanceTodaySection(attendanceToday = attendanceToday)
                        }
                    }

                    // Daily Breakdown Chart
                    item {
                        Text(
                            text = "Breakdown Harian",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                    }

                    item {
                        DailyBreakdownChartNew(dailyBreakdown = data.dailyBreakdown)
                    }

                    // Teachers on Leave - now with Purple color
                    if (data.teachersOnLeave.isNotEmpty()) {
                        item {
                            Text(
                                text = "Guru Izin/Diganti (${data.teachersOnLeave.size})",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }

                        items(data.teachersOnLeave.take(5)) { teacher ->
                            TeacherOnLeaveCardNew(teacher = teacher)
                        }
                    }

                    // Top Late Teachers
                    if (data.topLateTeachers.isNotEmpty()) {
                        item {
                            Text(
                                text = "Guru Sering Terlambat",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }

                        item {
                            TopLateTeachersCardNew(teachers = data.topLateTeachers)
                        }
                    }

                    item {
                        Spacer(modifier = Modifier.height(16.dp))
                    }
                }
            }
        }
    }
}

// === NEW: Teachers Attendance Today Section ===
@Composable
fun TeachersAttendanceTodaySection(attendanceToday: com.christopheraldoo.aplikasimonitoringkelas.data.TeachersAttendanceToday) {    var selectedTab by remember { mutableIntStateOf(0) }
    val tabs = listOf(
        Triple("Hadir", attendanceToday.summary.present, ColorHadir),
        Triple("Terlambat", attendanceToday.summary.late, ColorTelat),
        Triple("Tidak Hadir", attendanceToday.summary.absent, ColorTidakHadir),
        Triple("Izin", attendanceToday.summary.onLeave, ColorIzin)
    )
    
    Column(modifier = Modifier.fillMaxWidth()) {
        // Header with date
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.secondaryContainer
            )
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Kehadiran Guru Hari Ini",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "${attendanceToday.day}, ${attendanceToday.date}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSecondaryContainer.copy(alpha = 0.7f)
                    )
                }
                Text(
                    text = "${attendanceToday.summary.totalScheduled} Guru",
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        // Summary Pills
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            tabs.forEachIndexed { index, (label, count, color) ->
                AttendancePillButton(
                    label = label,
                    count = count,
                    color = color,
                    isSelected = selectedTab == index,
                    onClick = { selectedTab = index }
                )
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
          // Teacher List based on selected tab
        val teachersList = when (selectedTab) {
            0 -> attendanceToday.teachersPresent
            1 -> attendanceToday.teachersLate
            2 -> attendanceToday.teachersAbsent
            3 -> attendanceToday.teachersOnLeaveToday
            else -> emptyList()
        }
        
        val statusColor = tabs[selectedTab].third
        val statusLabel = tabs[selectedTab].first
        
        if (teachersList.isEmpty()) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
                )
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(24.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Tidak ada guru dengan status $statusLabel",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        } else {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = statusColor.copy(alpha = 0.08f)
                )
            ) {
                Column(
                    modifier = Modifier.padding(12.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    teachersList.take(5).forEach { teacher ->
                        TeacherAttendanceItemCard(
                            teacher = teacher,
                            statusColor = statusColor,
                            statusLabel = statusLabel
                        )
                    }
                    
                    if (teachersList.size > 5) {
                        Text(
                            text = "... dan ${teachersList.size - 5} guru lainnya",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            modifier = Modifier.padding(top = 4.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun AttendancePillButton(
    label: String,
    count: Int,
    color: Color,
    isSelected: Boolean,
    onClick: () -> Unit
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(20.dp),
        color = if (isSelected) color else color.copy(alpha = 0.15f),
        border = if (!isSelected) androidx.compose.foundation.BorderStroke(1.dp, color.copy(alpha = 0.3f)) else null
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                color = if (isSelected) Color.White else color
            )
            Surface(
                shape = CircleShape,
                color = if (isSelected) Color.White.copy(alpha = 0.3f) else color.copy(alpha = 0.2f)
            ) {
                Text(
                    text = count.toString(),
                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp),
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.Bold,
                    color = if (isSelected) Color.White else color
                )
            }
        }
    }
}

@Composable
fun TeacherAttendanceItemCard(
    teacher: com.christopheraldoo.aplikasimonitoringkelas.data.TeacherAttendanceInfo,
    statusColor: Color,
    statusLabel: String
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Avatar
            Surface(
                modifier = Modifier.size(44.dp),
                shape = CircleShape,
                color = statusColor.copy(alpha = 0.15f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Text(
                        text = teacher.teacherName.take(2).uppercase(),
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold,
                        color = statusColor
                    )
                }
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            // Teacher Info
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = teacher.teacherName,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                
                if (!teacher.teacherNip.isNullOrBlank()) {
                    Text(
                        text = "NIP: ${teacher.teacherNip}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                    )
                }
                
                // Show attendance time if available
                teacher.attendanceTime?.let { time ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            Icons.Default.AccessTime,
                            contentDescription = null,
                            modifier = Modifier.size(12.dp),
                            tint = statusColor
                        )
                        Text(
                            text = time,
                            style = MaterialTheme.typography.bodySmall,
                            color = statusColor,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
                
                // Show reason if on leave
                teacher.reason?.let { reason ->
                    Text(
                        text = reason,
                        style = MaterialTheme.typography.bodySmall,
                        color = ColorIzin,
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic
                    )
                }
            }
            
            // Schedule Count Badge
            Column(horizontalAlignment = Alignment.End) {
                Surface(
                    shape = RoundedCornerShape(8.dp),
                    color = MaterialTheme.colorScheme.primaryContainer
                ) {
                    Text(
                        text = "${teacher.scheduleCount} JP",
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                        style = MaterialTheme.typography.labelSmall,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                }
                
                // Show first schedule info
                if (!teacher.firstSchedule.isNullOrBlank()) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = teacher.firstSchedule,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f),
                        maxLines = 1
                    )
                }
            }
        }
    }
}

@Composable
fun WeekSelector(
    weekOffset: Int,
    onWeekChange: (Int) -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(MaterialTheme.colorScheme.surfaceVariant)
            .padding(4.dp),
        horizontalArrangement = Arrangement.SpaceEvenly
    ) {
        listOf(
            0 to "Minggu Ini",
            -1 to "Minggu Lalu"
        ).forEach { (offset, label) ->
            val isSelected = weekOffset == offset
            Surface(
                onClick = { onWeekChange(offset) },
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(8.dp),
                color = if (isSelected) MaterialTheme.colorScheme.primary else Color.Transparent
            ) {
                Text(
                    text = label,
                    modifier = Modifier.padding(12.dp),
                    textAlign = TextAlign.Center,
                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                    color = if (isSelected) MaterialTheme.colorScheme.onPrimary 
                            else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

// New Statistics Grid with 4 cards - Izin now purple
@Composable
fun StatisticsGridNew(stats: WeekStatistics) {
    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            StatCardSimple(
                modifier = Modifier.weight(1f),
                title = "Hadir",
                value = stats.hadir.toString(),
                icon = Icons.Default.CheckCircle,
                color = ColorHadir
            )
            StatCardSimple(
                modifier = Modifier.weight(1f),
                title = "Telat",
                value = stats.telat.toString(),
                icon = Icons.Default.AccessTime,
                color = ColorTelat
            )
        }
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            StatCardSimple(
                modifier = Modifier.weight(1f),
                title = "Tidak Hadir",
                value = stats.tidakHadir.toString(),
                icon = Icons.Default.Cancel,
                color = ColorTidakHadir
            )
            StatCardSimple(
                modifier = Modifier.weight(1f),
                title = "Izin",
                value = stats.izin.toString(),
                icon = Icons.Default.EventBusy,
                color = ColorIzin  // Purple
            )
        }
    }
}

@Composable
fun StatCardSimple(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    icon: ImageVector,
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
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = color,
                    modifier = Modifier.size(24.dp)
                )
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = value,
                style = MaterialTheme.typography.headlineMedium,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                text = title,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
fun DailyBreakdownChartNew(dailyBreakdown: List<DailyBreakdown>) {
    Card(
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            LazyRow(
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                items(dailyBreakdown) { day ->
                    DayColumnNew(day = day)
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Legend with updated colors
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                LegendItemNew(color = ColorHadir, label = "Hadir")
                LegendItemNew(color = ColorTelat, label = "Telat")
                LegendItemNew(color = ColorTidakHadir, label = "Absen")
                LegendItemNew(color = ColorIzin, label = "Izin")
            }
        }
    }
}

@Composable
fun DayColumnNew(day: DailyBreakdown) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.width(50.dp)
    ) {
        val maxHeight = 80.dp
        val total = (day.hadir + day.telat + day.tidakHadir + day.izin).coerceAtLeast(1)
        
        Box(
            modifier = Modifier
                .width(30.dp)
                .height(maxHeight)
                .clip(RoundedCornerShape(4.dp))
                .background(MaterialTheme.colorScheme.surfaceVariant)
        ) {
            Column(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.Bottom
            ) {
                // Izin - Purple (top)
                if (day.izin > 0) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(maxHeight * (day.izin.toFloat() / total))
                            .background(ColorIzin)
                    )
                }
                // Tidak Hadir - Red
                if (day.tidakHadir > 0) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(maxHeight * (day.tidakHadir.toFloat() / total))
                            .background(ColorTidakHadir)
                    )
                }
                // Telat - Orange
                if (day.telat > 0) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(maxHeight * (day.telat.toFloat() / total))
                            .background(ColorTelat)
                    )
                }
                // Hadir - Green (bottom)
                if (day.hadir > 0) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(maxHeight * (day.hadir.toFloat() / total))
                            .background(ColorHadir)
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(4.dp))
        
        Text(
            text = day.day,
            style = MaterialTheme.typography.labelSmall,
            fontWeight = FontWeight.Medium
        )
        
        Text(
            text = day.total.toString(),
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
        )
    }
}

@Composable
fun LegendItemNew(color: Color, label: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(10.dp)
                .clip(CircleShape)
                .background(color)
        )
        Spacer(modifier = Modifier.width(4.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall
        )
    }
}

@Composable
fun TeacherOnLeaveCardNew(teacher: TeacherOnLeave) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = ColorIzin.copy(alpha = 0.08f)
        )
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
                    text = teacher.originalTeacherName,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${teacher.subjectName} - ${teacher.className}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.CalendarToday,
                        contentDescription = null,
                        modifier = Modifier.size(12.dp),
                        tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = "${teacher.day}, ${teacher.date}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                    )
                }
                // Show keterangan if available
                teacher.keterangan?.let { reason ->
                    if (reason.isNotBlank()) {
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = reason,
                            style = MaterialTheme.typography.bodySmall,
                            color = ColorIzin,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
            
            Column(horizontalAlignment = Alignment.End) {
                // Status badge - Izin is purple, Diganti is green
                Surface(
                    color = if (teacher.status == "diganti") ColorHadir.copy(alpha = 0.15f)
                            else ColorIzin.copy(alpha = 0.15f),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(
                        text = if (teacher.status == "diganti") "Diganti" else "Izin",
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp),
                        style = MaterialTheme.typography.labelSmall,
                        color = if (teacher.status == "diganti") ColorHadir else ColorIzin,
                        fontWeight = FontWeight.Bold
                    )
                }
                
                teacher.substituteTeacherName?.let { substitute ->
                    Spacer(modifier = Modifier.height(6.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.SwapHoriz,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = ColorHadir
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = substitute,
                            style = MaterialTheme.typography.bodySmall,
                            color = ColorHadir,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun TopLateTeachersCardNew(teachers: List<TopLateTeacher>) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = ColorTelat.copy(alpha = 0.1f)
        )
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            teachers.forEachIndexed { index, teacher ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "${index + 1}.",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = ColorTelat
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Column {
                            Text(
                                text = teacher.teacherName,
                                style = MaterialTheme.typography.bodyMedium,
                                fontWeight = FontWeight.Medium
                            )
                            teacher.teacherNip?.let { nip ->
                                Text(
                                    text = "NIP: $nip",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                                )
                            }
                        }
                    }
                    
                    Surface(
                        color = ColorTelat,
                        shape = CircleShape
                    ) {
                        Text(
                            text = "${teacher.lateCount}x",
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                            style = MaterialTheme.typography.labelMedium,
                            color = Color.White,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
                
                if (index < teachers.lastIndex) {
                    HorizontalDivider(
                        modifier = Modifier.padding(vertical = 4.dp),
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.1f)
                    )
                }
            }
        }
    }
}

// ========== JADWAL PAGE - COMPLETELY REDESIGNED ==========

data class KepsekScheduleWithAttendance(
    val scheduleId: Int,
    val classId: Int,
    val className: String,
    val subjectName: String,
    val teacherName: String,
    val period: Int,
    val timeStart: String,
    val timeEnd: String,
    val dayOfWeek: String,
    val attendanceStatus: String?, // hadir, telat, tidak_hadir, izin, null if no attendance
    val attendanceTime: String?,
    val substituteTeacher: String?
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun KepsekJadwalPage() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    
    var selectedWeekOffset by remember { mutableStateOf(0) }
    var selectedDay by remember { mutableStateOf(getCurrentDayName()) }
    var isLoading by remember { mutableStateOf(true) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    var schedulesWithAttendance by remember { mutableStateOf<List<KepsekScheduleWithAttendance>>(emptyList()) }
    var allClasses by remember { mutableStateOf<List<String>>(emptyList()) }
    
    val days = listOf("Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu")
    
    // Load data when week or day changes
    LaunchedEffect(selectedWeekOffset, selectedDay) {
        isLoading = true
        errorMessage = null
        loadSchedulesWithAttendance(
            context = context,
            weekOffset = selectedWeekOffset,
            day = selectedDay
        ) { schedules, classes, error ->
            schedulesWithAttendance = schedules
            allClasses = classes
            errorMessage = error
            isLoading = false
        }
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Week Selector
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(MaterialTheme.colorScheme.surfaceVariant)
                .padding(4.dp),
            horizontalArrangement = Arrangement.SpaceEvenly
        ) {
            listOf(
                0 to "Minggu Ini",
                -1 to "Minggu Lalu"
            ).forEach { (offset, label) ->
                val isSelected = selectedWeekOffset == offset
                Surface(
                    onClick = { selectedWeekOffset = offset },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(8.dp),
                    color = if (isSelected) MaterialTheme.colorScheme.primary else Color.Transparent
                ) {
                    Text(
                        text = label,
                        modifier = Modifier.padding(10.dp),
                        textAlign = TextAlign.Center,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                        color = if (isSelected) MaterialTheme.colorScheme.onPrimary 
                                else MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        // Day Selector - Horizontal scroll
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            days.forEach { day ->
                val isSelected = selectedDay == day
                FilterChip(
                    selected = isSelected,
                    onClick = { selectedDay = day },
                    label = { 
                        Text(
                            day,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                        ) 
                    },
                    colors = FilterChipDefaults.filterChipColors(
                        selectedContainerColor = MaterialTheme.colorScheme.primary,
                        selectedLabelColor = MaterialTheme.colorScheme.onPrimary
                    )
                )
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        // Legend
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
            )
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                AttendanceLegendItem(ColorHadir, "Hadir")
                AttendanceLegendItem(ColorTelat, "Telat")
                AttendanceLegendItem(ColorTidakHadir, "Absen")
                AttendanceLegendItem(ColorIzin, "Izin")
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        // Content
        when {
            isLoading -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator()
                        Spacer(modifier = Modifier.height(12.dp))
                        Text("Memuat jadwal...")
                    }
                }
            }
            errorMessage != null -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            Icons.Default.Error,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(48.dp)
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = errorMessage ?: "Terjadi kesalahan",
                            color = MaterialTheme.colorScheme.error,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = {
                            isLoading = true
                            scope.launch {
                                loadSchedulesWithAttendance(
                                    context = context,
                                    weekOffset = selectedWeekOffset,
                                    day = selectedDay
                                ) { schedules, classes, error ->
                                    schedulesWithAttendance = schedules
                                    allClasses = classes
                                    errorMessage = error
                                    isLoading = false
                                }
                            }
                        }) {
                            Text("Coba Lagi")
                        }
                    }
                }
            }
            schedulesWithAttendance.isEmpty() -> {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            Icons.Default.EventBusy,
                            contentDescription = null,
                            tint = Color.Gray,
                            modifier = Modifier.size(64.dp)
                        )
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = "Tidak ada jadwal untuk hari $selectedDay",
                            style = MaterialTheme.typography.bodyLarge,
                            color = Color.Gray,
                            textAlign = TextAlign.Center
                        )
                    }
                }
            }
            else -> {
                // Group schedules by class
                val groupedByClass = schedulesWithAttendance.groupBy { it.className }
                    .toSortedMap()
                
                LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    groupedByClass.forEach { (className, schedules) ->
                        item {
                            ClassScheduleCard(
                                className = className,
                                schedules = schedules.sortedBy { it.period }
                            )
                        }
                    }
                    
                    item {
                        Spacer(modifier = Modifier.height(16.dp))
                    }
                }
            }
        }
    }
}

@Composable
fun AttendanceLegendItem(color: Color, label: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(14.dp)
                .clip(RoundedCornerShape(4.dp))
                .background(color)
        )
        Spacer(modifier = Modifier.width(6.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.Medium
        )
    }
}

@Composable
fun ClassScheduleCard(
    className: String,
    schedules: List<KepsekScheduleWithAttendance>
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            // Class Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.School,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = className,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                }
                
                // Quick summary badges
                val hadirCount = schedules.count { it.attendanceStatus == "hadir" }
                val telatCount = schedules.count { it.attendanceStatus == "telat" }
                val absenCount = schedules.count { it.attendanceStatus == "tidak_hadir" }
                val izinCount = schedules.count { it.attendanceStatus == "izin" || it.attendanceStatus == "diganti" }
                
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    if (hadirCount > 0) StatusBadgeMini(hadirCount, ColorHadir)
                    if (telatCount > 0) StatusBadgeMini(telatCount, ColorTelat)
                    if (absenCount > 0) StatusBadgeMini(absenCount, ColorTidakHadir)
                    if (izinCount > 0) StatusBadgeMini(izinCount, ColorIzin)
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Period list
            schedules.forEach { schedule ->
                PeriodAttendanceRow(schedule = schedule)
                if (schedule != schedules.last()) {
                    Spacer(modifier = Modifier.height(8.dp))
                }
            }
        }
    }
}

@Composable
fun StatusBadgeMini(count: Int, color: Color) {
    Box(
        modifier = Modifier
            .size(22.dp)
            .clip(CircleShape)
            .background(color),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = count.toString(),
            style = MaterialTheme.typography.labelSmall,
            color = Color.White,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
fun PeriodAttendanceRow(schedule: KepsekScheduleWithAttendance) {
    val statusColor = when (schedule.attendanceStatus) {
        "hadir" -> ColorHadir
        "telat" -> ColorTelat
        "tidak_hadir" -> ColorTidakHadir
        "izin", "diganti" -> ColorIzin
        else -> Color.Gray
    }
    
    val statusText = when (schedule.attendanceStatus) {
        "hadir" -> "Hadir"
        "telat" -> "Telat"
        "tidak_hadir" -> "Absen"
        "izin" -> "Izin"
        "diganti" -> "Diganti"
        else -> "Belum"
    }
    
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(
                if (schedule.attendanceStatus != null) 
                    statusColor.copy(alpha = 0.08f) 
                else 
                    MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f)
            )
            .border(
                width = 1.dp,
                color = if (schedule.attendanceStatus != null) 
                    statusColor.copy(alpha = 0.3f) 
                else 
                    Color.Transparent,
                shape = RoundedCornerShape(8.dp)
            )
            .padding(12.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        // Left side - Period info
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.weight(1f)
        ) {
            // Period number badge
            Box(
                modifier = Modifier
                    .size(32.dp)
                    .clip(CircleShape)
                    .background(MaterialTheme.colorScheme.primaryContainer),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = schedule.period.toString(),
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onPrimaryContainer
                )
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column {
                Text(
                    text = schedule.subjectName,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = schedule.substituteTeacher ?: schedule.teacherName,
                    style = MaterialTheme.typography.bodySmall,
                    color = if (schedule.substituteTeacher != null) ColorIzin else MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = "${schedule.timeStart} - ${schedule.timeEnd}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }
        }
        
        // Right side - Status
        Column(
            horizontalAlignment = Alignment.End
        ) {
            Surface(
                color = statusColor.copy(alpha = 0.15f),
                shape = RoundedCornerShape(6.dp)
            ) {
                Text(
                    text = statusText,
                    modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.Bold,
                    color = statusColor
                )
            }
            
            schedule.attendanceTime?.let { time ->
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = time,
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }
        }
    }
}

// Helper function
private fun getCurrentDayName(): String {
    return when (java.util.Calendar.getInstance().get(java.util.Calendar.DAY_OF_WEEK)) {
        java.util.Calendar.MONDAY -> "Senin"
        java.util.Calendar.TUESDAY -> "Selasa"
        java.util.Calendar.WEDNESDAY -> "Rabu"
        java.util.Calendar.THURSDAY -> "Kamis"
        java.util.Calendar.FRIDAY -> "Jumat"
        java.util.Calendar.SATURDAY -> "Sabtu"
        else -> "Senin"
    }
}

// API call to load schedules with attendance with retry for network errors
private suspend fun loadSchedulesWithAttendance(
    context: Context,
    weekOffset: Int,
    day: String,
    onResult: (List<KepsekScheduleWithAttendance>, List<String>, String?) -> Unit
) {
    val maxRetries = 3
    var lastException: Exception? = null
    
    for (attempt in 1..maxRetries) {
        try {
            val token = context.getSharedPreferences("MonitoringKelasSession", Context.MODE_PRIVATE)
                .getString("authToken", null) ?: ""

            if (token.isEmpty()) {
                onResult(emptyList(), emptyList(), "Token tidak ditemukan")
                return
            }

            val apiService = com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
                .createApiService(context)
            
            // Call schedules with attendance endpoint
            val response = withContext(Dispatchers.IO) {
                apiService.getKepsekSchedulesWithAttendance(
                    token = "Bearer $token",
                    weekOffset = weekOffset,
                    day = day
                )
            }
            
            if (response.isSuccessful && response.body()?.success == true) {
                val data = response.body()?.data ?: emptyList()
                val schedules = data.map { item ->
                    KepsekScheduleWithAttendance(
                        scheduleId = item.scheduleId,
                        classId = item.classId,
                        className = item.className,
                        subjectName = item.subjectName,
                        teacherName = item.teacherName,
                        period = item.period,
                        timeStart = item.timeStart,
                        timeEnd = item.timeEnd,
                        dayOfWeek = item.dayOfWeek,
                        attendanceStatus = item.attendanceStatus,
                        attendanceTime = item.attendanceTime,
                        substituteTeacher = item.substituteTeacher
                    )
                }
                val classes = schedules.map { it.className }.distinct().sorted()
                onResult(schedules, classes, null)
                return // Success, exit
            } else {
                onResult(emptyList(), emptyList(), response.body()?.message ?: "Gagal memuat jadwal")
                return
            }
        } catch (e: com.google.gson.JsonSyntaxException) {
            // JSON parsing error - likely truncated response, retry
            android.util.Log.w("KepsekSchedules", "JSON parse error on attempt $attempt: ${e.message}")
            lastException = e
            if (attempt < maxRetries) {
                kotlinx.coroutines.delay(500L * attempt) // Backoff
            }
        } catch (e: java.io.EOFException) {
            // End of file error - connection was cut, retry
            android.util.Log.w("KepsekSchedules", "EOF error on attempt $attempt: ${e.message}")
            lastException = e
            if (attempt < maxRetries) {
                kotlinx.coroutines.delay(500L * attempt)
            }
        } catch (e: Exception) {
            // Other errors - report immediately
            android.util.Log.e("KepsekSchedules", "Error loading schedules: ${e.message}", e)
            onResult(emptyList(), emptyList(), "Error: ${e.localizedMessage}")
            return
        }
    }
    
    // All retries failed
    onResult(emptyList(), emptyList(), "Gagal memuat data setelah $maxRetries percobaan. Periksa koneksi jaringan.")
}

// Compatibility wrapper
private fun loadSchedulesWithAttendance(
    context: Context,
    weekOffset: Int,
    day: String,
    scope: CoroutineScope = CoroutineScope(Dispatchers.Main),
    onResult: (List<KepsekScheduleWithAttendance>, List<String>, String?) -> Unit
) {
    scope.launch {
        loadSchedulesWithAttendance(context, weekOffset, day, onResult)
    }
}


