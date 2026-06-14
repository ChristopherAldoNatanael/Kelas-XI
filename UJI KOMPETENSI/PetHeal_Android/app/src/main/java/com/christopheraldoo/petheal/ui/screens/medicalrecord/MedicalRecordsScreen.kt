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
import androidx.compose.foundation.layout.defaultMinSize
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
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
import androidx.compose.material.icons.filled.Home
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
    onNavigateToHome: () -> Unit,
    onNavigateToBookings: () -> Unit,
    onNavigateToProfile: () -> Unit,
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
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = MrPrimary)
                    }
                }

                state.error != null -> {
                    MedicalRecordErrorState(
                        message = state.error ?: "Failed to load medical records",
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
                        contentPadding = PaddingValues(bottom = 106.dp)
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
                                onFilterSelected = viewModel::setFilter
                            )
                        }

                        if (upcoming.isNotEmpty()) {
                            item {
                                SectionHeader(
                                    title = "Upcoming Follow-up",
                                    subtitle = "Medical records with scheduled next visits"
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
                                    title = "Clinical History",
                                    subtitle = "Completed records ready for review"
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

            MedicalBottomNav(
                modifier = Modifier.align(Alignment.BottomCenter),
                onHome = onNavigateToHome,
                onBookings = onNavigateToBookings,
                onProfile = onNavigateToProfile
            )
        }
    }
}

@Composable
fun MedicalRecordDetailScreen(
    recordId: Int,
    onNavigateBack: () -> Unit,
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
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = MrPrimary)
                }
            }

            state.record == null -> {
                MedicalRecordErrorState(
                    message = state.error ?: "Medical record not found",
                    onRetry = { viewModel.loadRecord(recordId, forceRefresh = true) },
                    onBack = onNavigateBack
                )
            }

            else -> {
                val record = state.record!!
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
                        MedicalRecordPaymentSection(record = record)
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
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = MrTextPrimary)
                }

                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = MrSurface.copy(alpha = 0.82f),
                    border = BorderStroke(1.dp, MrBorder)
                ) {
                    Text(
                        text = "Pet Health Archive",
                        modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                        color = MrPrimaryDeep,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }

            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = "Medical records that are easier to review.",
                    color = MrTextPrimary,
                    fontSize = 28.sp,
                    lineHeight = 34.sp,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = "Track diagnosis, treatment, and extra payment status in one calm, readable workspace.",
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
                    title = "Records",
                    value = totalRecords.toString(),
                    icon = Icons.Filled.FolderShared,
                    accent = MrPrimary,
                    accentSoft = MrPrimarySoft
                )
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Need Action",
                    value = pendingExtra.toString(),
                    icon = Icons.Filled.Payments,
                    accent = MrAmber,
                    accentSoft = MrAmberSoft
                )
                HeroStatCard(
                    modifier = Modifier.weight(1f),
                    title = "Total Cost",
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
    onFilterSelected: (String) -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp)
            .padding(top = 12.dp, bottom = 6.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Text(
            text = "Filter by record type",
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
                        text = filter,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 9.dp),
                        color = if (selected) Color(0xFF052E16) else MrTextSecondary,
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
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = MrTextPrimary)
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
private fun MedicalRecordPaymentSection(record: MedicalRecord) {
    val extraStatus = record.extraPaymentStatus
    val palette = paymentPalette(extraStatus)

    DetailSectionCard(
        title = "Billing Breakdown",
        subtitle = "Consultation, treatment, and payment access"
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Consultation",
                value = formatCurrencyCompact(record.cost ?: 0.0),
                icon = Icons.Filled.HealthAndSafety,
                accent = MrPrimary,
                background = MrPrimarySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Treatment",
                value = formatCurrencyCompact(record.treatmentCost ?: 0.0),
                icon = Icons.Filled.Vaccines,
                accent = MrSky,
                background = MrSkySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Medicine",
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
                    Text("Extra payment status", color = MrTextSecondary, fontSize = 13.sp)
                    PaymentStatusChip(
                        label = paymentStatusLabel(extraStatus),
                        accent = palette.first,
                        background = palette.second
                    )
                }

                BillingRow("Total medical cost", formatCurrency(record.totalMedicalCost ?: recordTotalCost(record)))
                BillingRow("Extra payment required", formatCurrency(record.extraPaymentAmount ?: 0.0))
                BillingRow("Extra payment paid", formatCurrency(record.extraPaymentPaidAmount ?: 0.0))
                BillingRow(
                    "Full record access",
                    if (record.canViewFullRecord == true) "Available" else "Restricted until payment is complete"
                )
            }
        }
    }
}

@Composable
private fun MedicalRecordNarrativeSection(record: MedicalRecord) {
    DetailSectionCard(
        title = "Clinical Notes",
        subtitle = "Diagnosis, treatment plan, and observations"
    ) {
        NarrativeBlock(
            icon = Icons.Filled.HealthAndSafety,
            title = "Diagnosis",
            body = record.diagnosis ?: "-"
        )

        Spacer(Modifier.height(12.dp))

        NarrativeBlock(
            icon = Icons.Filled.Vaccines,
            title = "Treatment",
            body = record.treatment ?: "-"
        )

        if (!record.medicine.isNullOrBlank()) {
            Spacer(Modifier.height(12.dp))
            NarrativeBlock(
                icon = Icons.Filled.MedicalServices,
                title = "Medicine",
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
        title = "Follow-up Plan",
        subtitle = "Next visit schedule from the clinic"
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
                    Text("Next visit scheduled", color = MrTextSecondary, fontSize = 12.sp)
                    Text(
                        text = parseLocalDate(record.nextVisitDate)?.format(DateTimeFormatter.ofPattern("dd MMMM yyyy")) ?: record.nextVisitDate.orEmpty(),
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
                text = "No medical records yet",
                color = MrTextPrimary,
                fontSize = 22.sp,
                fontWeight = FontWeight.SemiBold
            )
            Text(
                text = "Your pet's treatment history will appear here after a consultation has been completed.",
                color = MrTextSecondary,
                fontSize = 14.sp,
                lineHeight = 22.sp,
                textAlign = TextAlign.Center
            )
            Button(
                onClick = onNavigateBack,
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = Color(0xFF052E16))
            ) {
                Text("Go Back", fontWeight = FontWeight.SemiBold)
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

@Composable
private fun MedicalBottomNav(
    modifier: Modifier = Modifier,
    onHome: () -> Unit,
    onBookings: () -> Unit,
    onProfile: () -> Unit
) {
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .navigationBarsPadding(),
        color = MrSurface.copy(alpha = 0.98f),
        shadowElevation = 10.dp
    ) {
        Column {
            Divider(color = MrBorder)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 22.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                BottomNavItem("Home", Icons.Filled.Home, false, onHome)
                BottomNavItem("Records", Icons.Filled.FolderShared, true, {})
                BottomNavItem("Bookings", Icons.Filled.CalendarMonth, false, onBookings)
                BottomNavItem("Profile", Icons.Filled.Person, false, onProfile)
            }
        }
    }
}

@Composable
private fun BottomNavItem(
    label: String,
    icon: ImageVector,
    selected: Boolean,
    onClick: () -> Unit
) {
    Column(
        modifier = Modifier
            .defaultMinSize(minWidth = 62.dp)
            .clip(RoundedCornerShape(18.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 8.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Box(
            modifier = Modifier
                .clip(RoundedCornerShape(14.dp))
                .background(if (selected) MrPrimarySoft else Color.Transparent)
                .padding(horizontal = 10.dp, vertical = 6.dp),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                icon,
                contentDescription = label,
                tint = if (selected) MrPrimaryDeep else MrTextSecondary,
                modifier = Modifier.size(22.dp)
            )
        }
        Text(
            text = label,
            color = if (selected) MrTextPrimary else MrTextSecondary,
            fontSize = 11.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium
        )
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
    "paid" -> "Paid"
    "partial" -> "Partial"
    "pending", "unpaid" -> "Pending"
    "failed" -> "Failed"
    "not_required", null -> "No Extra Cost"
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
    return if (formatted.length > 10) "Rp ${formatted.take(10)}" else "Rp $formatted"
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
