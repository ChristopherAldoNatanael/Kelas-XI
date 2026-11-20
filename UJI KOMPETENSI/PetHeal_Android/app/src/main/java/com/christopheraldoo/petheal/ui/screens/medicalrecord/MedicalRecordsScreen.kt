package com.christopheraldoo.petheal.ui.screens.medicalrecord

import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.WarningSoft

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
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
import androidx.compose.material.icons.filled.Business
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.OpenInNew
import androidx.compose.material.icons.filled.Photo
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.FilterAlt
import androidx.compose.material.icons.filled.FolderShared
import androidx.compose.material.icons.filled.HealthAndSafety
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Vaccines
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.MedicalRecordAttachment
import com.christopheraldoo.petheal.ui.components.PaymentReceiptSheet
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.toExtraPaymentReceipt
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.util.formatDateSafe
import com.christopheraldoo.petheal.util.formatRupiah
import com.christopheraldoo.petheal.util.formatRupiahCompact
import com.christopheraldoo.petheal.util.speciesLabelOf

private val MrBackground = Background
private val MrSurface = Surface
private val MrSurfaceAlt = SurfaceVariant
private val MrBorder = Outline
private val MrTextPrimary = OnBackground
private val MrTextSecondary = TextSecondary
private val MrPrimary = Primary
private val MrPrimaryDeep = PrimaryDark
private val MrPrimarySoft = PrimaryLight
private val MrAmber = Tertiary
private val MrAmberSoft = WarningSoft
private val MrSky = Info
private val MrSkySoft = InfoSoft
private val MrRose = ErrorStrong
private val MrRoseSoft = ErrorSoft
private val MrSlateSoft = SurfaceMuted

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

                // Arsip memang KOSONG ≠ filter yang tidak ketahuan. Empty
                // state generik hanya untuk kondisi records.isEmpty(); hasil
                // filter kosong tetap menampilkan hero + chip filter agar
                // pengguna bisa kembali ke "Semua".
                state.records.isEmpty() -> {
                    MedicalRecordEmptyState(onNavigateBack = onNavigateBack)
                }

                else -> {
                    val today = LocalDate.now()
                    val filteredRecords = state.filteredRecords
                    val upcoming = state.filteredRecords.filter { record ->
                        parseLocalDate(record.nextVisitDate)?.let { !it.isBefore(today) } == true
                    }
                    val history = state.filteredRecords.filterNot { record -> upcoming.any { it.id == record.id } }
                    val pendingExtra = state.filteredRecords.count { it.extraPaymentStatus in listOf("unpaid", "pending", "partial") }
                    val totalCost = state.filteredRecords.sumOf { recordTotalCost(it) }

                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(bottom = 132.dp)
                    ) {
                        item {
                            MedicalRecordListHero(
                                totalRecords = state.filteredRecords.size,
                                pendingExtra = pendingExtra,
                                totalCost = totalCost,
                                isRefreshing = state.isLoading,
                                onNavigateBack = onNavigateBack,
                                onRefresh = { viewModel.refreshRecords() }
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

                    if (filteredRecords.isEmpty()) {
                        // Kategori terpilih tidak punya hasil, tapi arsip ada:
                        // tawarkan aksi kontekstual, bukan empty state "belum
                        // pernah ke dokter".
                        item {
                            FilteredRecordsEmptyState(
                                filterLabel = viewModel.filterLabel(state.selectedFilter),
                                onShowAll = { viewModel.setFilter("all") }
                            )
                        }
                    }

                        if (upcoming.isNotEmpty()) {
                            item {
                                SectionHeader(
                                    title = "Kontrol berikutnya",
                                    count = upcoming.size
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
                                    title = "Riwayat klinis",
                                    count = history.size
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
    onNavigateToDoctor: (doctorId: Int) -> Unit = {},
    onBookFollowUp: (petId: Int, clinicSlug: String?) -> Unit = { _, _ -> },
    viewModel: MedicalRecordsViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    val ratingReminder by viewModel.ratingReminder.collectAsState()

    LaunchedEffect(recordId) {
        viewModel.loadRecord(recordId)
    }

    // Reminder rating hanya dicek setelah record tersedia (unlocked).
    // Guard di ViewModel memastikan tidak terpicu ulang oleh recomposition.
    val loadedRecord = state.record
    LaunchedEffect(
        loadedRecord?.id,
        loadedRecord?.canViewFullRecord,
        loadedRecord?.extraPaymentStatus
    ) {
        if (loadedRecord != null && !state.isLoading) {
            viewModel.checkRatingReminder(loadedRecord)
        }
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
                    contentPadding = PaddingValues(
                        bottom = 32.dp + WindowInsets.navigationBars
                            .asPaddingValues()
                            .calculateBottomPadding()
                    )
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
                            // KlINIK milik rekam medis ini saja. Bila null,
                            // bukti memakai label netral "Klinik" — tidak
                            // pernah meminjam klinik sesi yang lain.
                            clinicName = record.clinic?.name,
                            clinicAddress = record.clinic?.address,
                            clinicColor = record.clinic?.primaryColor,
                            onPayClick = { bookingId, amount ->
                                onNavigateToExtraPayment(bookingId, record.id ?: recordId, amount)
                            }
                        )
                    }

                    // Reminder rating: inline card (tidak mengganggu), hanya saat
                    // record unlocked. CTA masuk ke dialog rating dokter.
                    val currentReminder = ratingReminder
                    if (currentReminder != null) {
                        item {
                            MedicalRatingReminderCard(
                                doctorName = currentReminder.doctorName,
                                petName = currentReminder.petName,
                                onRateClick = {
                                    viewModel.consumeRatingReminderForNavigation()?.let { reminder ->
                                        onNavigateToDoctor(reminder.doctorId)
                                    }
                                },
                                onDismiss = { viewModel.dismissRatingReminder() }
                            )
                        }
                    }

                    item {
                        MedicalRecordNarrativeSection(record = record)
                    }

                    if (!record.nextVisitDate.isNullOrBlank()) {
                        item {
                            MedicalRecordFollowUpSection(
                                record = record,
                                onBookFollowUp = onBookFollowUp
                            )
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
        contentPadding = PaddingValues(top = 36.dp, start = 20.dp, end = 20.dp, bottom = 132.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            Surface(shape = RoundedCornerShape(28.dp), color = MrPrimarySoft) {
                Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                    SkeletonBlock(0.45f, 28.dp)
                    SkeletonBlock(0.85f, 16.dp)
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        repeat(3) { SkeletonMetric(Modifier.weight(1f)) }
                    }
                }
            }
        }
        items(4) {
            Surface(shape = RoundedCornerShape(22.dp), color = MrSurface) {
                Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.size(48.dp).clip(RoundedCornerShape(15.dp)).background(MrBorder))
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
    isRefreshing: Boolean = false,
    onNavigateBack: () -> Unit,
    onRefresh: () -> Unit = {}
) {
    // Gradasi brand yang sama dengan Beranda: hijau klinik → lebih gelap.
    val heroBottom = androidx.compose.ui.graphics.lerp(MrPrimary, Color.Black, 0.22f)

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp))
            .background(Brush.verticalGradient(listOf(MrPrimary, heroBottom)))
    ) {
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(x = 64.dp, y = (-88).dp)
                .size(200.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.08f))
        )
        Box(
            modifier = Modifier
                .align(Alignment.BottomStart)
                .offset(x = (-56).dp, y = 56.dp)
                .size(160.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.06f))
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 20.dp, end = 20.dp, top = 10.dp, bottom = 20.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                HeroIconButton(
                    onClick = onNavigateBack,
                    icon = Icons.Filled.ArrowBack,
                    description = "Kembali"
                )
                // Fallback manual: muat ulang setelah payment sukses.
                // Cara utama tetap otomatis via MedicalRefreshManager.
                HeroIconButton(
                    onClick = onRefresh,
                    icon = Icons.Filled.Refresh,
                    description = "Muat ulang",
                    enabled = !isRefreshing,
                    isBusy = isRefreshing
                )
            }

            Spacer(Modifier.height(16.dp))

            Text(
                text = "Rekam Medis",
                color = Color.White,
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold
            )
            Text(
                text = "Riwayat diagnosis, tindakan, dan biaya perawatan",
                color = Color.White.copy(alpha = 0.82f),
                fontSize = 12.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(Modifier.height(16.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                HeroStatCard(
                    title = "Rekam",
                    value = totalRecords.toString(),
                    modifier = Modifier.weight(1f)
                )
                HeroStatCard(
                    title = "Perlu aksi",
                    value = pendingExtra.toString(),
                    modifier = Modifier.weight(1f)
                )
                HeroStatCard(
                    title = "Total biaya",
                    value = formatRupiahCompact(totalCost),
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

/** Tombol bulat translusen di atas gradasi hero. */
@Composable
private fun HeroIconButton(
    onClick: () -> Unit,
    icon: ImageVector,
    description: String,
    enabled: Boolean = true,
    isBusy: Boolean = false
) {
    Box(
        modifier = Modifier
            .size(42.dp)
            .clip(CircleShape)
            .background(Color.White.copy(alpha = 0.18f))
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        if (isBusy) {
            CircularProgressIndicator(
                color = Color.White,
                strokeWidth = 2.dp,
                modifier = Modifier.size(18.dp)
            )
        } else {
            Icon(icon, contentDescription = description, tint = Color.White, modifier = Modifier.size(20.dp))
        }
    }
}

/** Angka ringkas di atas hero — bentuk sama dengan Beranda. */
@Composable
private fun HeroStatCard(
    title: String,
    value: String,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(14.dp))
            .background(Color.White.copy(alpha = 0.16f))
            .padding(horizontal = 10.dp, vertical = 9.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        Text(
            text = title,
            color = Color.White.copy(alpha = 0.80f),
            fontSize = 10.sp,
            fontWeight = FontWeight.Medium,
            maxLines = 1
        )
        Text(
            text = value,
            color = Color.White,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun FilterSection(
    filters: List<String>,
    selectedFilter: String,
    onFilterSelected: (String) -> Unit,
    labelFor: (String) -> String = { it }
) {
    LazyRow(
        modifier = Modifier
            .fillMaxWidth()
            .padding(top = 14.dp, bottom = 2.dp),
        contentPadding = PaddingValues(horizontal = 20.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(filters) { filter ->
            val selected = filter == selectedFilter
            Surface(
                shape = RoundedCornerShape(999.dp),
                color = if (selected) MrPrimary else MrSurface,
                border = if (selected) null else BorderStroke(1.dp, MrBorder),
                modifier = Modifier
                    .heightIn(min = 36.dp)
                    .clip(RoundedCornerShape(999.dp))
                    .clickable { onFilterSelected(filter) }
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    if (selected) {
                        Icon(
                            imageVector = Icons.Filled.CheckCircle,
                            contentDescription = null,
                            tint = Color.White,
                            modifier = Modifier.size(13.dp)
                        )
                    }
                    Text(
                        text = labelFor(filter),
                        color = if (selected) Color.White else MrTextSecondary,
                        fontSize = 12.5.sp,
                        fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Medium,
                        maxLines = 1
                    )
                }
            }
        }
    }
}

@Composable
private fun SectionHeader(title: String, count: Int) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp)
            .padding(top = 18.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Text(
            text = title,
            color = MrTextPrimary,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold
        )
        if (count > 0) {
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(999.dp))
                    .background(MrPrimarySoft)
                    .padding(horizontal = 7.dp, vertical = 2.dp)
            ) {
                Text(
                    text = count.toString(),
                    color = MrPrimaryDeep,
                    fontSize = 10.5.sp,
                    fontWeight = FontWeight.Bold
                )
            }
        }
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
    val createdDate = formatDateSafe(record.createdAt, "dd MMM yyyy")
    val totalCost = recordTotalCost(record)
    val extraStatus = record.extraPaymentStatus ?: "not_required"
    val statusPalette = paymentPalette(extraStatus)

    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 6.dp)
            .clip(RoundedCornerShape(22.dp))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(22.dp),
        color = MrSurface,
        shadowElevation = 2.dp
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.Top
            ) {
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(RoundedCornerShape(15.dp))
                        .background(MrPrimarySoft),
                    contentAlignment = Alignment.Center
                ) {
                    if (!pet?.photo.isNullOrBlank()) {
                        ThumbnailImage(
                            model = buildPhotoUrl(pet?.photo),
                            contentDescription = pet?.name,
                            modifier = Modifier
                                .fillMaxSize()
                                .clip(RoundedCornerShape(15.dp))
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Filled.Pets,
                            contentDescription = null,
                            tint = MrPrimary,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }

                Column(
                    modifier = Modifier.weight(1f),
                    verticalArrangement = Arrangement.spacedBy(1.dp)
                ) {
                    Text(
                        text = pet?.name ?: "Rekam hewan",
                        color = MrTextPrimary,
                        fontSize = 14.5.sp,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = doctor?.name?.let { "Ditangani oleh $it" } ?: "Rekam dokter hewan",
                        color = MrTextSecondary,
                        fontSize = 11.5.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    // MULTI-CLINIC: nama klinik penerbit. Tanpa label ini
                    // pasien tidak bisa membedakan rekam dari klinik mana.
                    record.clinic?.name?.takeIf { it.isNotBlank() }?.let { clinicName ->
                        Text(
                            text = clinicName,
                            color = MrPrimaryDeep,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
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
                text = record.diagnosis?.takeIf { it.isNotBlank() } ?: "Konsultasi umum",
                color = MrTextPrimary,
                fontSize = 13.5.sp,
                lineHeight = 19.sp,
                fontWeight = FontWeight.SemiBold,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            if (!record.treatment.isNullOrBlank()) {
                Text(
                    text = record.treatment.orEmpty(),
                    color = MrTextSecondary,
                    fontSize = 12.sp,
                    lineHeight = 17.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                InlineInfo(
                    icon = Icons.Filled.ReceiptLong,
                    text = formatRupiahCompact(totalCost)
                )
                InlineInfo(
                    icon = if (nextVisit != null) Icons.Filled.CalendarMonth else Icons.Filled.Schedule,
                    text = if (nextVisit != null) {
                        "Kontrol " + nextVisit.format(DateTimeFormatter.ofPattern("d MMM", Locale("id", "ID")))
                    } else {
                        createdDate
                    }
                )
                Spacer(modifier = Modifier.weight(1f))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Lihat detail",
                        color = MrPrimaryDeep,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                    Icon(
                        imageVector = Icons.Filled.ChevronRight,
                        contentDescription = null,
                        tint = MrPrimaryDeep,
                        modifier = Modifier.size(15.dp)
                    )
                }
            }
        }
    }
}

/** Chip kecil (biaya / tanggal) di dalam kartu rekam. */
@Composable
private fun InlineInfo(icon: ImageVector, text: String) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(999.dp))
            .background(MrPrimarySoft.copy(alpha = 0.7f))
            .padding(horizontal = 9.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Icon(icon, contentDescription = null, tint = MrPrimaryDeep, modifier = Modifier.size(12.dp))
        Text(
            text = text,
            color = MrPrimaryDeep,
            fontSize = 10.5.sp,
            fontWeight = FontWeight.SemiBold,
            maxLines = 1
        )
    }
}

@Composable
private fun MedicalRecordDetailHero(
    record: MedicalRecord,
    onNavigateBack: () -> Unit
) {
    val pet = record.booking?.pet ?: record.pet
    val doctor = record.booking?.doctor ?: record.doctor
    val createdDate = formatDateSafe(record.createdAt, "dd MMM yyyy")
    val heroBottom = androidx.compose.ui.graphics.lerp(MrPrimary, Color.Black, 0.22f)

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp))
            .background(Brush.verticalGradient(listOf(MrPrimary, heroBottom)))
    ) {
        Box(
            modifier = Modifier
                .align(Alignment.TopEnd)
                .offset(x = 64.dp, y = (-88).dp)
                .size(200.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.08f))
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 20.dp, end = 20.dp, top = 10.dp, bottom = 20.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                HeroIconButton(
                    onClick = onNavigateBack,
                    icon = Icons.Filled.ArrowBack,
                    description = "Kembali"
                )
                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = Color.White.copy(alpha = 0.18f)
                ) {
                    Text(
                        text = paymentStatusLabel(record.extraPaymentStatus),
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
                        color = Color.White,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }

            Spacer(Modifier.height(16.dp))

            Text(
                text = record.diagnosis?.takeIf { it.isNotBlank() } ?: "Rekam medis",
                color = Color.White,
                fontSize = 20.sp,
                lineHeight = 26.sp,
                fontWeight = FontWeight.Bold,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = listOfNotNull(
                    pet?.name,
                    doctor?.name,
                    createdDate.takeIf { it != "-" }
                ).joinToString(" · "),
                color = Color.White.copy(alpha = 0.82f),
                fontSize = 12.sp,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            Spacer(Modifier.height(16.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                HeroStatCard(
                    title = "Total biaya",
                    value = formatRupiahCompact(record.totalMedicalCost ?: recordTotalCost(record)),
                    modifier = Modifier.weight(1f)
                )
                HeroStatCard(
                    title = "Biaya tambahan",
                    value = formatRupiahCompact(record.extraPaymentAmount ?: 0.0),
                    modifier = Modifier.weight(1f)
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
        title = "Ringkasan Kasus",
        subtitle = "Data pasien dan dokter"
    ) {
        IdentityCard(
            icon = Icons.Filled.Pets,
            title = pet?.name ?: "Hewan",
            subtitle = buildString {
                if (!pet?.species.isNullOrBlank()) append(speciesLabelOf(pet?.species))
                if (!pet?.breed.isNullOrBlank()) {
                    if (isNotBlank()) append(" • ")
                    append(pet?.breed)
                }
                if (isBlank()) append("Profil hewan")
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

        // MULTI-CLINIC: pasien bisa berobat di beberapa klinik, jadi klinik
        // penerbit rekam medis ini wajib disebut. Baris disembunyikan bila
        // server tidak mengirim klinik — tidak diganti klinik lain.
        record.clinic?.name?.takeIf { it.isNotBlank() }?.let { clinicName ->
            Spacer(Modifier.height(12.dp))
            IdentityCard(
                icon = Icons.Filled.Business,
                title = clinicName,
                subtitle = record.clinic.address?.takeIf { it.isNotBlank() } ?: "Klinik penerbit",
                accent = MrPrimaryDeep,
                background = MrPrimarySoft
            )
        }
    }
}

@Composable
private fun MedicalRecordPaymentSection(
    record: MedicalRecord,
    clinicName: String?,
    clinicAddress: String?,
    clinicColor: String?,
    onPayClick: (bookingId: Int, amount: Double) -> Unit = { _, _ -> }
) {
    val extraStatus = record.extraPaymentStatus
    val palette = paymentPalette(extraStatus)
    val outstanding = (record.extraPaymentAmount ?: 0.0) - (record.extraPaymentPaidAmount ?: 0.0)
    val payBookingId = record.bookingId
    val needsExtraPayment = extraStatus in listOf("unpaid", "pending", "partial") &&
        outstanding > 0 && payBookingId != null

    // Bukti biaya tambahan bisa dibuka KAPAN SAJA dari detail — tidak lagi
    // terbatas saat layar hasil pembayaran baru lewat. Syaratnya data nyata:
    // ada uang yang benar-benar terbayar ( Builder status PAID/PARTIAL ).
    val hasExtraProof = (record.extraPaymentPaidAmount ?: 0.0) > 0.01 ||
        extraStatus == "paid"
    val extraReceipt = if (hasExtraProof) {
        remember(record, clinicName, clinicAddress) {
            record.toExtraPaymentReceipt(
                clinicName = clinicName,
                clinicAddress = clinicAddress
            )
        }
    } else null
    var showExtraReceipt by rememberSaveable { mutableStateOf(false) }

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
                value = formatRupiahCompact(record.cost ?: 0.0),
                icon = Icons.Filled.HealthAndSafety,
                accent = MrPrimary,
                background = MrPrimarySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Tindakan",
                value = formatRupiahCompact(record.treatmentCost ?: 0.0),
                icon = Icons.Filled.Vaccines,
                accent = MrSky,
                background = MrSkySoft
            )
            MetricMiniCard(
                modifier = Modifier.weight(1f),
                title = "Obat",
                value = formatRupiahCompact(record.medicineCost ?: 0.0),
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

                BillingRow("Total biaya medis", formatRupiah(record.totalMedicalCost ?: recordTotalCost(record)))
                BillingRow("Biaya tambahan", formatRupiah(record.extraPaymentAmount ?: 0.0))
                BillingRow("Tambahan terbayar", formatRupiah(record.extraPaymentPaidAmount ?: 0.0))
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

                if (extraReceipt != null) {
                    // Sekualitas bukti booking: sheet yang sama (PNG/PDF/
                    // bagikan/salin) dibuka in-place, tanpa navigasi.
                    Spacer(Modifier.height(4.dp))
                    OutlinedButton(
                        onClick = { showExtraReceipt = true },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(52.dp),
                        shape = RoundedCornerShape(16.dp),
                        border = BorderStroke(1.dp, MrPrimary.copy(alpha = 0.45f)),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = MrPrimary)
                    ) {
                        Icon(Icons.Filled.ReceiptLong, contentDescription = null, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Lihat bukti pembayaran", fontWeight = FontWeight.SemiBold)
                    }
                }
            }
        }
    }

    if (showExtraReceipt && extraReceipt != null) {
        PaymentReceiptSheet(
            receipt = extraReceipt,
            clinicColor = clinicColor,
            onDismiss = { showExtraReceipt = false }
        )
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

        // SOAP hanya muncul bila dokter mengisinya. Rekam medis lama tidak
        // menampilkan blok kosong.
        listOf(
            Triple("S — Keluhan pemilik", record.subjective, Icons.Filled.Article),
            Triple("O — Temuan pemeriksaan", record.objective, Icons.Filled.MedicalServices),
            Triple("A — Penilaian", record.assessment, Icons.Filled.HealthAndSafety),
            Triple("P — Rencana", record.plan, Icons.Filled.Schedule)
        ).forEach { (title, body, icon) ->
            if (!body.isNullOrBlank()) {
                Spacer(Modifier.height(12.dp))
                NarrativeBlock(icon = icon, title = title, body = body)
            }
        }

        if (!record.attachments.isNullOrEmpty()) {
            Spacer(Modifier.height(12.dp))
            AttachmentList(record.attachments)
        }

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
                title = "Catatan dokter",
                body = record.notes.orEmpty()
            )
        }
    }
}

@Composable
private fun MedicalRecordFollowUpSection(
    record: MedicalRecord,
    onBookFollowUp: (petId: Int, clinicSlug: String?) -> Unit
) {
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

                Column(Modifier.weight(1f)) {
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

        Spacer(Modifier.height(12.dp))

        // Booking dibuat dari alur booking biasa (pilih dokter & jam), lalu
        // ditautkan ke rekam medis ini oleh server. Tidak ada booking otomatis
        // saat tombol ditekan.
        val followUpPetId = record.petId ?: record.pet?.id
        Button(
            onClick = { followUpPetId?.let { onBookFollowUp(it, record.clinic?.slug) } },
            enabled = followUpPetId != null,
            modifier = Modifier.fillMaxWidth().height(46.dp),
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.buttonColors(containerColor = MrPrimary)
        ) {
            Icon(Icons.Filled.CalendarMonth, contentDescription = null, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text("Booking Kontrol", fontWeight = FontWeight.SemiBold)
        }
        Spacer(Modifier.height(4.dp))
        Text(
            "Hewan dan rekam medis ini otomatis terbawa ke alur booking.",
            color = MrTextSecondary,
            fontSize = 11.sp
        )
    }
}

@Composable
private fun MedicalRatingReminderCard(
    doctorName: String,
    petName: String?,
    onRateClick: () -> Unit,
    onDismiss: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 8.dp),
        shape = RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(containerColor = MrSurface),
        border = BorderStroke(1.dp, MrPrimary.copy(alpha = 0.22f)),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(46.dp)
                        .clip(RoundedCornerShape(16.dp))
                        .background(MrPrimarySoft),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Filled.Star,
                        contentDescription = null,
                        tint = MrPrimaryDeep,
                        modifier = Modifier.size(24.dp)
                    )
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Bagaimana pengalaman Anda dengan dokter?",
                        color = MrTextPrimary,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        lineHeight = 21.sp
                    )
                    Text(
                        text = buildString {
                            append("Berikan rating dan review untuk ")
                            append(doctorName)
                            if (!petName.isNullOrBlank()) append(" atas perawatan $petName")
                            append(" untuk membantu meningkatkan kualitas layanan.")
                        },
                        color = MrTextSecondary,
                        fontSize = 13.sp,
                        lineHeight = 20.sp
                    )
                }
            }
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Button(
                    onClick = onRateClick,
                    modifier = Modifier
                        .weight(1f)
                        .height(48.dp),
                    shape = RoundedCornerShape(16.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = MrPrimary,
                        contentColor = Color.White
                    )
                ) {
                    Icon(
                        Icons.Filled.Star,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(Modifier.width(8.dp))
                    Text("Beri Rating", fontWeight = FontWeight.Bold)
                }
                OutlinedButton(
                    onClick = onDismiss,
                    modifier = Modifier.height(48.dp),
                    shape = RoundedCornerShape(16.dp),
                    border = BorderStroke(1.dp, MrBorder)
                ) {
                    Text("Nanti", color = MrTextSecondary, fontWeight = FontWeight.SemiBold)
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
            text = "Dicatat ${formatDateSafe(record.createdAt, "d MMM yyyy • HH:mm")}",
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
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 6.dp),
        shape = RoundedCornerShape(22.dp),
        color = MrSurface,
        shadowElevation = 2.dp
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Text(title, color = MrTextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(3.dp))
            Text(subtitle, color = MrTextSecondary, fontSize = 12.sp, lineHeight = 17.sp)
            Spacer(Modifier.height(14.dp))
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
        shape = RoundedCornerShape(16.dp),
        color = MrSurfaceAlt
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

/**
 * Daftar lampiran pemeriksaan (rontgen/lab/foto).
 *
 * Dibuka lewat browser/penampil bawaan sistem — tidak perlu menambah
 * dependensi penampil PDF hanya untuk ini. Entri tanpa URL tidak pernah
 * dikirim server, tetapi tetap dijaga di sini.
 */
@Composable
private fun AttachmentList(attachments: List<MedicalRecordAttachment>) {
    val uriHandler = LocalUriHandler.current
    val openable = attachments.filter { !it.url.isNullOrBlank() }
    if (openable.isEmpty()) return

    Surface(
        shape = RoundedCornerShape(16.dp),
        color = MrSurfaceAlt
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Text(
                "Lampiran pemeriksaan",
                color = MrTextPrimary,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold
            )

            openable.forEach { attachment ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(14.dp))
                        .clickable { attachment.url?.let(uriHandler::openUri) }
                        .padding(vertical = 6.dp),
                    horizontalArrangement = Arrangement.spacedBy(10.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .background(MrPrimarySoft),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            if (attachment.isImage) Icons.Filled.Photo else Icons.Filled.Description,
                            contentDescription = null,
                            tint = MrPrimary,
                            modifier = Modifier.size(19.dp)
                        )
                    }

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            attachment.label,
                            color = MrTextPrimary,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            if (attachment.isImage) "Foto · ketuk untuk membuka" else "Dokumen PDF · ketuk untuk membuka",
                            color = MrTextSecondary,
                            fontSize = 11.sp
                        )
                    }

                    Icon(
                        Icons.Filled.OpenInNew,
                        contentDescription = null,
                        tint = MrTextSecondary,
                        modifier = Modifier.size(18.dp)
                    )
                }
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
        shape = RoundedCornerShape(16.dp),
        color = MrSurfaceAlt
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
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(16.dp))
            .background(MrSurfaceAlt)
            .padding(horizontal = 10.dp, vertical = 10.dp),
        verticalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Box(
            modifier = Modifier
                .size(30.dp)
                .clip(RoundedCornerShape(10.dp))
                .background(background),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(16.dp))
        }
        Column(verticalArrangement = Arrangement.spacedBy(1.dp)) {
            Text(
                text = title,
                color = MrTextSecondary,
                fontSize = 10.5.sp,
                fontWeight = FontWeight.Medium,
                maxLines = 1
            )
            Text(
                text = value,
                color = MrTextPrimary,
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
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

/**
 * Empty state KONTEKSTUAL untuk filter yang tidak menghasilkan apa pun.
 * Arsip sebenarnya ada — chip filter tetap tampil di atas, jadi pengguna
 * bisa langsung kembali ke "Semua". Jangan pakai empty state generik
 * "Belum ada rekam medis" di sini karena itu informasi yang salah.
 */
@Composable
private fun FilteredRecordsEmptyState(
    filterLabel: String,
    onShowAll: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 32.dp, horizontal = 28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            modifier = Modifier
                .size(64.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(MrPrimarySoft),
            contentAlignment = Alignment.Center
        ) {
            Icon(Icons.Filled.FilterAlt, contentDescription = null, tint = MrPrimary, modifier = Modifier.size(30.dp))
        }
        Text(
            text = "Tidak ada rekam medis untuk \u201C$filterLabel\u201D",
            color = MrTextPrimary,
            fontSize = 16.sp,
            fontWeight = FontWeight.SemiBold,
            textAlign = TextAlign.Center
        )
        Text(
            text = "Rekam medis dengan kategori lain tetap tersimpan di arsip Anda.",
            color = MrTextSecondary,
            fontSize = 13.sp,
            lineHeight = 20.sp,
            textAlign = TextAlign.Center
        )
        OutlinedButton(
            onClick = onShowAll,
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = MrPrimary)
        ) {
            Text("Lihat Semua Rekam Medis", fontWeight = FontWeight.SemiBold)
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
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold
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
                modifier = Modifier.height(44.dp),
                shape = RoundedCornerShape(14.dp),
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
                text = "Rekam medis tidak bisa dibuka",
                color = MrTextPrimary,
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
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
                    modifier = Modifier.height(44.dp),
                    shape = RoundedCornerShape(14.dp),
                    border = BorderStroke(1.dp, MrBorder)
                ) {
                    Text("Coba lagi", color = MrTextPrimary, fontWeight = FontWeight.SemiBold)
                }
                if (onBack != null) {
                    Button(
                        onClick = onBack,
                        modifier = Modifier.height(44.dp),
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = MrPrimary, contentColor = OnPrimaryStrong)
                    ) {
                        Text("Kembali", fontWeight = FontWeight.SemiBold)
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

private fun parseLocalDate(value: String?): LocalDate? {
    if (value.isNullOrBlank()) return null
    return try {
        LocalDate.parse(value.take(10))
    } catch (_: Exception) {
        null
    }
}
