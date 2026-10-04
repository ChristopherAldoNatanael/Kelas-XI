package com.christopheraldoo.petheal.ui.screens.medicalrecord

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Article
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.FolderShared
import androidx.compose.material.icons.filled.HealthAndSafety
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Shield
import androidx.compose.material.icons.filled.Vaccines
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.ElevatedCard
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
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
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

private val MrBackground = Color(0xFFF4F8F5)
private val MrSurface = Color(0xFFFFFFFF)
private val MrSurfaceAlt = Color(0xFFF8FBF9)
private val MrBorder = Color(0xFFE2E8F0)
private val MrTextPrimary = Color(0xFF0F172A)
private val MrTextSecondary = Color(0xFF64748B)
private val MrPrimary = Color(0xFF10B981)
private val MrPrimaryDeep = Color(0xFF047857)
private val MrPrimarySoft = Color(0xFFDDF8EA)
private val MrAmber = Color(0xFFF59E0B)
private val MrAmberSoft = Color(0xFFFEF3C7)
private val MrSky = Color(0xFF0EA5E9)
private val MrSkySoft = Color(0xFFE0F2FE)
private val MrRose = Color(0xFFE11D48)
private val MrRoseSoft = Color(0xFFFFE4E6)
private val MrSlateSoft = Color(0xFFF1F5F9)

@Composable
fun MedicalRecordsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToRecordDetail: (Int) -> Unit,
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: MedicalRecordsViewModel = hiltViewModel()
) {
    val state by viewModel.listState.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.loadRecords()
    }

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MrBackground
    ) {
        Box {
            when {
                state.isLoading -> {
                    MedicalRecordSkeletonList()
                }

                state.error != null -> {
                    MedicalRecordErrorState(
                        message = state.error ?: "Gagal memuat rekam medis",
                        onRetry = { viewModel.loadRecords(forceRefresh = true) }
                    )
                }

                state.filteredRecords.isEmpty() -> {
                    MedicalRecordEmptyState(onNavigateBack = onNavigateBack)
                }

                else -> {
                    val today = LocalDate.now()
                    val upcoming = state.filteredRecords.filter { record ->
                        parseLocalDate(record.nextVisitDate)?.let { !it.isBefore(today) } == true
                    }
                    val history = state.filteredRecords.filterNot { record -> upcoming.any { it.id == record.id } }
                    val pendingExtra = state.filteredRecords.count { it.extraPaymentStatus in listOf("unpaid", "pending", "partial") }
                    val totalCost = state.filteredRecords.sumOf { recordTotalCost(it) }

                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 112.dp)
                    ) {
                        item {
                            MedicalRecordListHero(
                                totalRecords = state.filteredRecords.size,
                                pendingExtra = pendingExtra,
                                totalCost = totalCost,
                                onNavigateBack = onNavigateBack
                            )
                        }

                    item {
                        FilterSection(
                            filters = viewModel.filterCategories,
                            selectedFilter = state.selectedFilter,
                            onFilterSelected = viewModel::setFilter,
                            labelFor = viewModel::filterLabel
                        )
                    }

                        if (upcoming.isNotEmpty()) {
                            item {
                                SectionHeader(
                                    title = "Kontrol Berikutnya",
                                    subtitle = "Rekam medis dengan jadwal kunjungan"
                                )
                            }

                            items(
                                items = upcoming,
                                key = { record -> record.id ?: record.hashCode() }
                            ) { record ->
                                MedicalRecordListCard(
                                    record = record,
                                    onClick = { record.id?.let(onNavigateToRecordDetail) }
                                )
                            }
                        }

                        if (history.isNotEmpty()) {
                            item {
                                SectionHeader(
                                    title = "Riwayat Klinis",
                                    subtitle = "Rekam yang selesai dan siap dibaca"
                                )
                            }

                            items(
                                items = history,
                                key = { record -> record.id ?: record.hashCode() }
                            ) { record ->
                                MedicalRecordListCard(
                                    record = record,
                                    onClick = { record.id?.let(onNavigateToRecordDetail) }
                                )
                            }
                        }
                    }
                }
            }

            // ── Unified floating bottom nav (PHASE 9) ──────────────────
            com.christopheraldoo.petheal.ui.components.PetHealFloatingBottomNav(
                selected = com.christopheraldoo.petheal.ui.components.PetHealTab.Records,
                onSelect = onTabSelected,
                modifier = Modifier.align(Alignment.BottomCenter)
            )
        }
    }
}

@Composable
fun MedicalRecordDetailScreen(
    recordId: Int,
    onNavigateBack: () -> Unit,
    onNavigateToExtraPayment: (bookingId: Int, recordId: Int, amount: Double) -> Unit = { _, _, _ -> },
    viewModel: MedicalRecordsViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()

    LaunchedEffect(recordId) {
        viewModel.loadRecord(recordId)
    }

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MrBackground
    ) {
        when {
            state.isLoading -> {
                MedicalRecordDetailSkeleton()
            }

            state.record == null -> {
                MedicalRecordErrorState(
                    message = state.error ?: "Rekam medis tidak ditemukan",
                    onRetry = { viewModel.loadRecord(recordId, forceRefresh = true) },
                    onBack = onNavigateBack
                )
            }

            else -> {
                val record = state.record
                if (record == null) {
                    MedicalRecordErrorState(
                        message = state.error ?: "Rekam medis tidak ditemukan",
                        onRetry = { viewModel.loadRecord(recordId, forceRefresh = true) },
                        onBack = onNavigateBack
                    )
                } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 32.dp)
                ) {
                    item {
                        MedicalRecordDetailHero(
                            record = record,
                            onNavigateBack = onNavigateBack
                        )
                    }

                    item {
                        MedicalRecordIdentitySection(record = record)
                    }

                    item {
                        MedicalRecordPaymentSection(
                            record = record,
                            onPayClick = { bookingId, amount ->
                                onNavigateToExtraPayment(bookingId, record.id ?: recordId, amount)
                            }
                        )
                    }

                    item {
                        MedicalRecordNarrativeSection(record = record)
                    }

                    if (!record.nextVisitDate.isNullOrBlank()) {
                        item {
                            MedicalRecordFollowUpSection(record = record)
                        }
                    }

                    item {
                        RecordMetaFooter(record = record)
                    }
                }
                }
            }
        }
    }
}

@Composable
private fun MedicalRecordSkeletonList() {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(top = 36.dp, start = 20.dp, end = 20.dp, bottom = 106.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            Surface(shape = RoundedCornerShape(30.dp), color = MrSurface) {
                Column(Modifier.padding(22.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    SkeletonBlock(0.45f, 28.dp)
                    SkeletonBlock(0.85f, 16.dp)
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        repeat(3) { SkeletonMetric(Modifier.weight(1f)) }
                    }
                }
            }
        }
        items(4) {
            Surface(shape = RoundedCornerShape(24.dp), color = MrSurface) {
                Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(58.dp).clip(RoundedCornerShape(18.dp)).background(MrBorder))
                    Spacer(Modifier.size(12.dp))
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        SkeletonBlock(0.60f, 18.dp)
                        SkeletonBlock(0.90f, 13.dp)
                        SkeletonBlock(0.36f, 13.dp)
                    }
                }
            }
        }
    }
}

@Composable
private fun MedicalRecordDetailSkeleton() {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        Spacer(Modifier.height(28.dp))
        Surface(shape = RoundedCornerShape(30.dp), color = MrSurface) {
            Column(Modifier.padding(22.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                SkeletonBlock(0.50f, 30.dp)
                SkeletonBlock(0.80f, 16.dp)
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    SkeletonMetric(Modifier.weight(1f))
                    SkeletonMetric(Modifier.weight(1f))
                }
            }
        }
        repeat(3) {
            Surface(shape = RoundedCornerShape(24.dp), color = MrSurface) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    SkeletonBlock(0.42f, 18.dp)
                    SkeletonBlock(1f, 13.dp)
                    SkeletonBlock(0.78f, 13.dp)
                }
            }
        }
    }
}

@Composable
private fun SkeletonMetric(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .height(72.dp)
            .clip(RoundedCornerShape(18.dp))
            .background(MrBorder.copy(alpha = 0.65f))
    )
}

@Composable
private fun SkeletonBlock(widthFraction: Float, height: Dp) {
    Box(
        modifier = Modifier
            .fillMaxWidth(widthFraction)
            .height(height)
            .clip(RoundedCornerShape(999.dp))
            .background(MrBorder.copy(alpha = 0.75f))
    )
}

@Composable
private fun MedicalRecordListHero(
    totalRecords: Int,
    pendingExtra: Int,
    totalCost: Double,
    onNavigateBack: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFFDFF8EA), MrBackground)
                )
            )
            .statusBarsPadding()
            .padding(horizontal = 20.dp, vertical = 18.dp)
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(18.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                IconButton(
                    onClick = onNavigateBack,
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(MrSurface.copy(alpha = 0.88f))
                ) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = MrTextPrimary)
                }

                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = MrSurface.copy(alpha = 0.82f),
                    border = BorderStroke(1.dp, MrBorder)
                ) {
                    Text(
                        text = "Arsip Kesehatan Hewan",
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                        color = MrPrimaryDeep,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }

            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "Rekam medis yang lebih mudah dibaca.",
                    color = MrTextPrimary,
                    fontSize = 28.sp,
                    lineHeight = 34.sp,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = "Pantau diagnosis, tindakan, dan status pembayaran tambahan dalam satu tempat yang rapi.",
                    color = MrTextSecondary,
                    fontSize = 14.sp,
                    lineHeight = 21.sp
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Rekam",
                    value = totalRecords.toString(),
                    icon = Icons.Filled.FolderShared,
                    accent = MrPrimary,
                    accentSoft = MrPrimarySoft
                )
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Perlu Aksi",
                    value = pendingExtra.toString(),
                    icon = Icons.Filled.Payments,
                    accent = MrAmber,
                    accentSoft = MrAmberSoft
                )
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Total Biaya",
                    value = formatCurrencyCompact(totalCost),
                    icon = Icons.Filled.ReceiptLong,
                    accent = MrSky,
                    accentSoft = MrSkySoft
                )
            }
        }
    }
}

@Composable
private fun HeroStatCard(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    icon: ImageVector,
    accent: Color,
    accentSoft: Color
) {
    ElevatedCard(
        modifier = modifier,
        colors = CardDefaults.elevatedCardColors(containerColor = MrSurface.copy(alpha = 0.92f)),
        elevation = CardDefaults.elevatedCardElevation(defaultElevation = 0.dp),
        shape = RoundedCornerShape(22.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 14.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.Top
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(title, color = MrTextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                Text(value, color = MrTextPrimary, fontSize = 18.sp, fontWeight = FontWeight.SemiBold)
            }

            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(accentSoft),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(20.dp))
            }
        }
    }
}

@Composable
private fun FilterSection(
    filters: List<String>,
    selectedFilter: String,
    onFilterSelected: (String) -> Unit,
    labelFor: (String) -> String = { it }
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp)
            .padding(top = 12.dp, bottom = 6.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Text(
            text = "Saring berdasarkan jenis rekam",
            color = MrTextSecondary,
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium
        )

        LazyRow(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            items(filters) { filter ->
                val selected = filter == selectedFilter
                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = if (selected) MrPrimary else MrSurface,
                    border = BorderStroke(1.dp, if (selected) MrPrimary else MrBorder),
                    modifier = Modifier.clickable { onFilterSelected(filter) }
                ) {
                    Text(
                        text = labelFor(filter),
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 9.dp),
                        color = if (selected) Color.White else MrTextSecondary,
                        fontSize = 13.sp,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium
                    )
                }
            }
        }
    }
}

@Composable
private fun SectionHeader(
    title: String,
    subtitle: String
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(3.dp)
    ) {
        Text(title, color = MrTextPrimary, fontSize = 18.sp, fontWeight = FontWeight.SemiBold)
        Text(subtitle, color = MrTextSecondary, fontSize = 13.sp)
    }
}

@Composable
private fun MedicalRecordListCard(
    record: MedicalRecord,
    onClick: () -> Unit
) {
    val pet = record.booking?.pet ?: record.pet
    val doctor = record.booking?.doctor ?: record.doctor
    val nextVisit = parseLocalDate(record.nextVisitDate)
    val createdDate = formatDate(record.createdAt, "dd MMM yyyy")
    val totalCost = recordTotalCost(record)
    val extraStatus = record.extraPaymentStatus ?: "not_required"
    val statusPalette = paymentPalette(extraStatus)

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 7.dp)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = MrSurface),
        border = BorderStroke(1.dp, MrBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Row(
                    modifier = Modifier.weight(1f),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(54.dp)
                            .clip(RoundedCornerShape(18.dp))
                            .background(MrPrimarySoft),
                        contentAlignment = Alignment.Center
                    ) {
                        if (!pet?.photo.isNullOrBlank()) {
                            ThumbnailImage(
                                model = buildPhotoUrl(pet?.photo),
                                contentDescription = pet?.name,
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(RoundedCornerShape(18.dp))
                            )
                        } else {
                            Icon(Icons.Filled.Pets, contentDescription = null, tint = MrPrimary, modifier = Modifier.size(26.dp))
                        }
                    }

                    Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                        Text(
                            text = pet?.name ?: "Pet record",
                            color = MrTextPrimary,
                            fontSize = 17.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                        Text(
                            text = doctor?.name?.let { "Handled by $it" } ?: "Veterinary record",
                            color = MrTextSecondary,
                            fontSize = 13.sp
                        )
                    }
                }

                PaymentStatusChip(
                    label = paymentStatusLabel(extraStatus),
                    accent = statusPalette.first,
                    background = statusPalette.second
                )
            }

            Text(
                text = record.diagnosis ?: "General consultation",
                color = MrTextPrimary,
                fontSize = 16.sp,
                lineHeight = 24.sp,
                fontWeight = FontWeight.Medium
            )

            if (!record.treatment.isNullOrBlank()) {
                Text(
                    text = record.treatment.orEmpty(),
                    color = MrTextSecondary,
                    fontSize = 13.sp,
                    lineHeight = 20.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                MetricMiniCard(
                    modifier = Modifier.weight(1f),
                    title = "Total Cost",
                    value = formatCurrencyCompact(totalCost),
                    icon = Icons.Filled.ReceiptLong,
                    accent = MrPrimary,
                    background = MrPrimarySoft
                )
                MetricMiniCard(
                    modifier = Modifier.weight(1f),
                    title = if (nextVisit != null) "Next Visit" else "Created",
                    value = if (nextVisit != null) nextVisit.format(DateTimeFormatter.ofPattern("dd MMM")) else createdDate,
                    icon = if (nextVisit != null) Icons.Filled.CalendarMonth else Icons.Filled.Schedule,
                    accent = if (nextVisit != null) MrSky else Color(0xFF475569),
                    background = if (nextVisit != null) MrSkySoft else MrSlateSoft
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = record.notes?.takeIf { it.isNotBlank() } ?: "Tap to review full medical record",
                    color = MrTextSecondary,
                    fontSize = 12.sp,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    text = "View details",
                    color = MrPrimaryDeep,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun MedicalRecordDetailHero(
    record: MedicalRecord,
    onNavigateBack: () -> Unit
) {
    val pet = record.booking?.pet ?: record.pet
    val doctor = record.booking?.doctor ?: record.doctor
    val createdDate = formatDate(record.createdAt, "dd MMM yyyy")
    val paymentPalette = paymentPalette(record.extraPaymentStatus)

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFFDFF8EA), MrBackground)
                )
            )
            .statusBarsPadding()
            .padding(horizontal = 20.dp, vertical = 18.dp)
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                IconButton(
                    onClick = onNavigateBack,
                    modifier = Modifier
                        .size(44.dp)
                        .clip(CircleShape)
                        .background(MrSurface.copy(alpha = 0.9f))
                ) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = MrTextPrimary)
                }

                PaymentStatusChip(
                    label = paymentStatusLabel(record.extraPaymentStatus),
                    accent = paymentPalette.first,
                    background = paymentPalette.second
                )
            }

            Text(
                text = record.diagnosis ?: "Medical record",
                color = MrTextPrimary,
                fontSize = 28.sp,
                lineHeight = 34.sp,
                fontWeight = FontWeight.SemiBold
            )

            Text(
                text = "Clinical summary for ${pet?.name ?: "your pet"}${doctor?.name?.let { " with $it" } ?: ""}.",
                color = MrTextSecondary,
                fontSize = 14.sp,
                lineHeight = 21.sp
            )

            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                InfoPill(Icons.Filled.CalendarToday, createdDate)
                doctor?.name?.takeIf { it.isNotBlank() }?.let { InfoPill(Icons.Filled.Person, it) }
                pet?.species?.takeIf { it.isNotBlank() }?.let { InfoPill(Icons.Filled.Pets, it) }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Total Cost",
                    value = formatCurrencyCompact(record.totalMedicalCost ?: recordTotalCost(record)),
                    icon = Icons.Filled.Payments,
                    accent = MrPrimary,
                    accentSoft = MrPrimarySoft
                )
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Extra Payment",
                    value = formatCurrencyCompact(record.extraPaymentAmount ?: 0.0),
                    icon = Icons.Filled.ReceiptLong,
                    accent = MrAmber,
                    accentSoft = MrAmberSoft
                )
            }
        }
    }
}

@Composable
private fun MedicalRecordIdentitySection(record: MedicalRecord) {
    val pet = record.booking?.pet ?: record.pet
    val doctor = record.booking?.doctor ?: record.doctor

    DetailSectionCard(
        title = "Case Overview",
        subtitle = "Patient and veterinarian information"
    ) {
        IdentityCard(
            icon = Icons.Filled.Pets,
            title = pet?.name ?: "Pet",
            subtitle = buildString {
                if (!pet?.species.isNullOrBlank()) append(pet?.species)
                if (!pet?.breed.isNullOrBlank()) {
                    if (isNotBlank()) append(" • ")
                    append(pet?.breed)
                }
                if (isBlank()) append("Pet profile")
            },
            accent = MrPrimary,
            background = MrPrimarySoft
        )

        Spacer(Modifier.height(12.dp))

        IdentityCard(
            icon = Icons.Filled.LocalHospital,
            title = doctor?.name ?: "Veterinarian",
            subtitle = doctor?.specialization ?: "Doctor on duty",
            accent = MrSky,
            background = MrSkySoft
        )
    }
}

@Composable
private fun MedicalRecordPaymentSection(
    record: MedicalRecord,
    onPayClick: (bookingId: Int, amount: Double) -> Unit = { _, _ -> }
) {
    val extraStatus = record.extraPaymentStatus
    val palette = paymentPalette(extraStatus)
    val outstanding = (record.extraPaymentAmount ?: 0.0) - (record.extraPaymentPaidAmount ?: 0.0)
    val payBookingId = record.bookingId
    val needsExtraPayment = extraStatus in listOf("unpaid", "pending", "partial") &&
        outstanding > 0 && payBookingId != null

    DetailSectionCard(
        title = "Rincian Biaya",
        subtitle = "Konsultasi, tindakan, dan akses pembayaran"
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Konsultasi",
                value = formatCurrencyCompact(record.cost ?: 0.0),
                icon = Icons.Filled.HealthAndSafety,
                accent = MrPrimary,
                background = MrPrimarySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Tindakan",
                value = formatCurrencyCompact(record.treatmentCost ?: 0.0),
                icon = Icons.Filled.Vaccines,
                accent = MrSky,
                background = MrSkySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Obat",
                value = formatCurrencyCompact(record.medicineCost ?: 0.0),
                icon = Icons.Filled.MedicalServices,
                accent = MrAmber,
                background = MrAmberSoft
            )
        }

        Spacer(Modifier.height(14.dp))

        Surface(
            shape = RoundedCornerShape(20.dp),
            color = palette.second.copy(alpha = 0.65f),
            border = BorderStroke(1.dp, palette.first.copy(alpha = 0.28f))
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text("Status pembayaran tambahan", color = MrTextSecondary, fontSize = 13.sp)
                    PaymentStatusChip(
                        label = paymentStatusLabel(extraStatus),
                        accent = palette.first,
                        background = palette.second
                    )
                }

                BillingRow("Total biaya medis", formatCurrency(record.totalMedicalCost ?: recordTotalCost(record)))
                BillingRow("Biaya tambahan", formatCurrency(record.extraPaymentAmount ?: 0.0))
                BillingRow("Tambahan terbayar", formatCurrency(record.extraPaymentPaidAmount ?: 0.0))
                BillingRow(
                    "Akses rekam lengkap",
                    if (record.canViewFullRecord == true) "Tersedia" else "Terkunci sampai pembayaran lunas"
                )

                if (needsExtraPayment) {
                    Spacer(Modifier.height(4.dp))
                    Button(
                        onClick = { payBookingId?.let { onPayClick(it, outstanding) } },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(52.dp),
                        shape = RoundedCornerShape(16.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MrAmber,
                            contentColor = Color.White
                        )
                    ) {
                        Icon(Icons.Filled.Payments, contentDescription = null, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Bayar Sekarang", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}

@Composable
private fun MedicalRecordNarrativeSection(record: MedicalRecord) {
    DetailSectionCard(
        title = "Catatan Klinis",
        subtitle = "Diagnosis, rencana tindakan, dan observasi"
    ) {
        NarrativeBlock(
            icon = Icons.Filled.HealthAndSafety,
            title = "Diagnosis",
            body = record.diagnosis ?: "-"
        )

        Spacer(Modifier.height(12.dp))

        NarrativeBlock(
            icon = Icons.Filled.Vaccines,
            title = "Tindakan",
            body = record.treatment ?: "-"
        )

        if (!record.medicine.isNullOrBlank()) {
            Spacer(Modifier.height(12.dp))
            NarrativeBlock(
                icon = Icons.Filled.MedicalServices,
                title = "Obat",
                body = record.medicine.orEmpty()
            )
        }

        if (!record.notes.isNullOrBlank()) {
            Spacer(Modifier.height(12.dp))
            NarrativeBlock(
                icon = Icons.Filled.Article,
                title = "Doctor Notes",
                body = record.notes.orEmpty()
            )
        }
    }
}

@Composable
private fun MedicalRecordFollowUpSection(record: MedicalRecord) {
    DetailSectionCard(
        title = "Rencana Kontrol",
        subtitle = "Jadwal kunjungan berikutnya dari klinik"
    ) {
        Surface(
            shape = RoundedCornerShape(20.dp),
            color = MrAmberSoft.copy(alpha = 0.6f),
            border = BorderStroke(1.dp, MrAmber.copy(alpha = 0.25f))
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.spacedBy(14.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(46.dp)
                        .clip(RoundedCornerShape(16.dp))
                        .background(MrAmber.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(Icons.Filled.CalendarMonth, contentDescription = null, tint = MrAmber, modifier = Modifier.size(24.dp))
                }

                Column {
                    Text("Kunjungan berikutnya", color = MrTextSecondary, fontSize = 12.sp)
                    Text(
                        text = parseLocalDate(record.nextVisitDate)?.format(DateTimeFormatter.ofPattern("dd MMMM yyyy", Locale("id", "ID"))) ?: record.nextVisitDate.orEmpty(),
                        color = MrTextPrimary,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
    }
}

@Composable
private fun RecordMetaFooter(record: MedicalRecord) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 18.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Divider(color = MrBorder)
        Text(
            text = "Recorded ${formatDate(record.createdAt, "dd MMM yyyy • HH:mm")}",
            color = MrTextSecondary,
            fontSize = 12.sp
        )
    }
}

@Composable
private fun DetailSectionCard(
    title: String,
    subtitle: String,
    content: @Composable () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 8.dp),
        colors = CardDefaults.cardColors(containerColor = MrSurface),
        shape = RoundedCornerShape(26.dp),
        border = BorderStroke(1.dp, MrBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(18.dp)
        ) {
            Text(title, color = MrTextPrimary, fontSize = 18.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(4.dp))
            Text(subtitle, color = MrTextSecondary, fontSize = 13.sp, lineHeight = 20.sp)
            Spacer(Modifier.height(16.dp))
            content()
        }
    }
}

@Composable
private fun IdentityCard(
    icon: ImageVector,
    title: String,
    subtitle: String,
    accent: Color,
    background: Color
) {
    Surface(
        shape = RoundedCornerShape(20.dp),
        color = MrSurfaceAlt,
        border = BorderStroke(1.dp, MrBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(background),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(24.dp))
            }

            Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(title, color = MrTextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
                Text(subtitle, color = MrTextSecondary, fontSize = 13.sp, lineHeight = 19.sp)
            }
        }
    }
}

@Composable
private fun NarrativeBlock(
    icon: ImageVector,
    title: String,
    body: String
) {
    Surface(
        shape = RoundedCornerShape(20.dp),
        color = MrSurfaceAlt,
        border = BorderStroke(1.dp, MrBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.Top
        ) {
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(MrPrimarySoft),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = MrPrimary, modifier = Modifier.size(21.dp))
            }

            Column(verticalArrangement = Arrangement.spacedBy(5.dp)) {
                Text(title, color = MrTextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                Text(body, color = MrTextSecondary, fontSize = 13.sp, lineHeight = 21.sp)
            }
        }
    }
}

@Composable
private fun BillingRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, color = MrTextSecondary, fontSize = 13.sp)
        Text(
            text = value,
            color = MrTextPrimary,
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            textAlign = TextAlign.End
        )
    }
}

@Composable
private fun MetricMiniCard(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    icon: ImageVector,
    accent: Color,
    background: Color
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(18.dp),
        color = MrSurfaceAlt,
        border = BorderStroke(1.dp, MrBorder)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(34.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(background),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(18.dp))
            }

            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(title, color = MrTextSecondary, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                Text(
                    text = value,
                    color = MrTextPrimary,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

@Composable
private fun PaymentStatusChip(
    label: String,
    accent: Color,
    background: Color
) {
    Surface(
        shape = RoundedCornerShape(999.dp),
        color = background,
        border = BorderStroke(1.dp, accent.copy(alpha = 0.18f))
    ) {
        Text(
            text = label,
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
            color = accent,
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold
        )
    }
}

@Composable
private fun InfoPill(icon: ImageVector, label: String) {
    Surface(
        shape = RoundedCornerShape(999.dp),
        color = MrSurface.copy(alpha = 0.86f),
        border = BorderStroke(1.dp, MrBorder)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(icon, contentDescription = null, tint = MrTextSecondary, modifier = Modifier.size(15.dp))
            Text(label, color = MrTextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun MedicalRecordEmptyState(onNavigateBack: () -> Unit) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 28.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(88.dp)
                    .clip(RoundedCornerShape(30.dp))
                    .background(MrPrimarySoft),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.FolderShared, contentDescription = null, tint = MrPrimary, modifier = Modifier.size(42.dp))
            }

            Text(
                text = "Belum ada rekam medis",
                color = MrTextPrimary,
                fontSize = 22.sp,
                fontWeight = FontWeight.SemiBold
            )
            Text(
                text = "Riwayat perawatan hewan akan tampil di sini setelah konsultasi selesai.",
                color = MrTextSecondary,
                fontSize = 14.sp,
                lineHeight = 22.sp,
                textAlign = TextAlign.Center
            )
            Button(
                onClick = onNavigateBack,
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = Color.White)
            ) {
                Text("Kembali", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun MedicalRecordErrorState(
    message: String,
    onRetry: () -> Unit,
    onBack: (() -> Unit)? = null
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(horizontal = 28.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(88.dp)
                    .clip(RoundedCornerShape(30.dp))
                    .background(MrRoseSoft),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.ErrorOutline, contentDescription = null, tint = MrRose, modifier = Modifier.size(42.dp))
            }

            Text(
                text = "Unable to open medical record",
                color = MrTextPrimary,
                fontSize = 22.sp,
                fontWeight = FontWeight.SemiBold,
                textAlign = TextAlign.Center
            )
            Text(
                text = message,
                color = MrTextSecondary,
                fontSize = 14.sp,
                lineHeight = 22.sp,
                textAlign = TextAlign.Center
            )

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedButton(
                    onClick = onRetry,
                    shape = RoundedCornerShape(16.dp),
                    border = BorderStroke(1.dp, MrBorder)
                ) {
                    Text("Retry", color = MrTextPrimary, fontWeight = FontWeight.SemiBold)
                }
                if (onBack != null) {
                    Button(
                        onClick = onBack,
                        shape = RoundedCornerShape(16.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = Color(0xFF052E16))
                    ) {
                        Text("Back", fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }
}

private fun paymentPalette(status: String?): Pair<Color, Color> = when (status) {
    "paid" -> MrPrimaryDeep to MrPrimarySoft
    "partial" -> MrSky to MrSkySoft
    "pending", "unpaid" -> MrAmber to MrAmberSoft
    "failed" -> MrRose to MrRoseSoft
    else -> MrTextSecondary to MrSlateSoft
}

private fun paymentStatusLabel(status: String?): String = when (status) {
    "paid" -> "Lunas"
    "partial" -> "Sebagian"
    "pending", "unpaid" -> "Menunggu"
    "failed" -> "Gagal"
    "not_required", null -> "Tanpa Biaya Tambahan"
    else -> status.replaceFirstChar { it.uppercase() }
}

private fun recordTotalCost(record: MedicalRecord): Double {
    return record.totalMedicalCost
        ?: ((record.cost ?: 0.0) + (record.treatmentCost ?: 0.0) + (record.medicineCost ?: 0.0))
}

private fun formatCurrency(value: Double): String {
    return "Rp ${String.format("%,.0f", value).replace(",", ".")}"
}

private fun formatCurrencyCompact(value: Double): String {
    val formatted = String.format("%,.0f", value).replace(",", ".")
    if (formatted.length <= 10) return "Rp $formatted"
    // Compact large amounts with jt/M suffixes instead of mid-digit truncation.
    return when {
        value >= 1_000_000_000 -> "Rp ${String.format("%.1f", value / 1_000_000_000)} M"
        value >= 1_000_000 -> "Rp ${String.format("%.1f", value / 1_000_000)} jt"
        else -> "Rp ${formatted.take(10)}…"
    }
}

private fun formatDate(value: String?, pattern: String): String {
    if (value.isNullOrBlank()) return "-"
    return try {
        LocalDateTime.parse(value.replace(" ", "T").take(19)).format(DateTimeFormatter.ofPattern(pattern))
    } catch (_: Exception) {
        try {
            LocalDate.parse(value.take(10)).format(DateTimeFormatter.ofPattern(pattern))
        } catch (_: Exception) {
            value.take(10)
        }
    }
}

private fun parseLocalDate(value: String?): LocalDate? {
    if (value.isNullOrBlank()) return null
    return try {
        LocalDate.parse(value.take(10))
    } catch (_: Exception) {
        null
    }
}
