package com.christopheraldoo.petheal.ui.screens.home

import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.PrimaryWash
import com.christopheraldoo.petheal.ui.theme.StatusRejected
import com.christopheraldoo.petheal.ui.theme.SuccessDeep
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.TextMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Article
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.PendingActions
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material.icons.filled.Vaccines
import androidx.compose.material.icons.outlined.MyLocation
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.ui.theme.ClinicTheme
import com.christopheraldoo.petheal.util.DoctorListing
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.formatDateSafe
import com.christopheraldoo.petheal.util.formatRupiahCompact
import com.christopheraldoo.petheal.util.speciesLabelOf

// Alias lokal → satu sumber warna di ui/theme/Color.kt.
private val HomePrimary = Primary
private val HomePrimaryDark = PrimaryDark
private val HomeBg = Background
private val HomeSurface = Surface
private val HomeTextPrimary = OnBackground
private val HomeTextSecondary = TextSecondary
private val HomeSoftSurface = SurfaceVariant

/** Padding horizontal Beranda — ritme khas aplikasi mobile (bukan web 24dp). */
private val HomeGutter = 20.dp

/**
 * BERANDA — kartu identitas aplikasi.
 *
 * Struktur mengikuti pola aplikasi mobile (bukan dasbor web):
 *  hero bergradasi warna klinik + sapaan + 3 angka penting,
 *  deretan aksi cepat, strip "perlu perhatian", kartu jadwal berikutnya,
 *  dokter terdekat (kartu geser), banner Pet Shop, lalu aktivitas terbaru.
 *
 * Semua angka berasal dari `HomeViewModel` (API nyata). Tidak ada metrik
 * karangan dan tidak ada tombol tanpa aksi.
 */
@Composable
fun HomeScreen(
    onNavigateToPets: () -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookings: () -> Unit,
    onNavigateToBookingStart: () -> Unit = {},
    onNavigateToBookingDetail: (Int) -> Unit = {},
    onNavigateToMedicalRecords: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit = {},
    // MULTI-CLINIC DISCOVERY: masuk ke pencarian/penjelajahan klinik.
    onNavigateToDiscovery: () -> Unit = {},
    // Pet Shop: katalog produk lintas klinik mitra.
    onNavigateToPetShop: () -> Unit = {},
    onNavigateToDoctor: (doctorId: Int, autoReview: Boolean) -> Unit = { _, _ -> },
    // MULTI-CLINIC: baris "Dokter terdekat" membawa kliniknya sendiri.
    onNavigateToDoctorInClinic: (doctorId: Int, clinicId: Int?) -> Unit = { _, _ -> },
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    // Kembali dari layar lain (review/booking) → revalidasi diam-diam bila basi.
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) viewModel.refreshIfStale()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HomeBg)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .verticalScroll(rememberScrollState())
                .padding(bottom = 132.dp)
        ) {
            HomeHero(
                userName = uiState.userName,
                userPhoto = uiState.userPhoto,
                clinicName = uiState.clinicName,
                clinicLogo = uiState.clinicLogo,
                clinicColor = uiState.clinicColor,
                summary = uiState.dashboardSummary,
                totalPets = uiState.totalPets,
                totalVisits = uiState.totalVisits,
                outstandingAmount = uiState.outstandingAmount,
                unreadNotificationCount = uiState.unreadNotificationCount,
                onNavigateToNotifications = onNavigateToNotifications,
                onNavigateToProfile = onNavigateToProfile
            )

            QuickActionsCard(
                modifier = Modifier.padding(horizontal = HomeGutter).padding(top = 16.dp),
                onNavigateToDiscovery = onNavigateToDiscovery,
                onNavigateToDoctors = onNavigateToDoctors,
                onNavigateToBookingStart = onNavigateToBookingStart,
                onNavigateToPets = onNavigateToPets,
                onNavigateToMedicalRecords = onNavigateToMedicalRecords
            )

            uiState.loadError?.let { loadError ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = HomeGutter)
                        .padding(top = 12.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .background(ErrorSoft)
                        .padding(horizontal = 14.dp, vertical = 10.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text(
                        text = loadError,
                        fontSize = 12.sp,
                        lineHeight = 17.sp,
                        color = StatusRejected,
                        modifier = Modifier.weight(1f)
                    )
                    TextButton(onClick = { viewModel.refresh() }) {
                        Text(
                            "Coba lagi",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = HomePrimaryDark
                        )
                    }
                }
            }

            AttentionSection(
                pendingBookings = uiState.pendingBookings,
                paymentAttentionCount = uiState.paymentAttentionCount,
                followUpDueCount = uiState.followUpDueCount,
                dueVaccinationCount = uiState.dueVaccinationCount,
                overdueVaccinationCount = uiState.overdueVaccinationCount
            )

            UpcomingBookingSection(
                uiState = uiState,
                onNavigateToBookings = onNavigateToBookings,
                onNavigateToBookingDetail = onNavigateToBookingDetail,
                onNavigateToDoctors = onNavigateToDoctors,
                onNavigateToBookingStart = onNavigateToBookingStart
            )

            NearbyDoctorsSection(
                state = uiState,
                onDoctorClick = { doctorId, clinicId -> onNavigateToDoctorInClinic(doctorId, clinicId) },
                onSeeAll = onNavigateToDoctors,
                onEnableLocation = { viewModel.loadNearbyDoctors(forceGps = true) }
            )

            PetShopBanner(
                modifier = Modifier.padding(horizontal = HomeGutter).padding(top = 20.dp),
                onClick = onNavigateToPetShop
            )

            RecentActivitySection(
                recentVisits = uiState.recentVisits,
                onSeeAll = onNavigateToMedicalRecords
            )
        }

        com.christopheraldoo.petheal.ui.components.PetHealFloatingBottomNav(
            selected = com.christopheraldoo.petheal.ui.components.PetHealTab.Home,
            onSelect = onTabSelected,
            modifier = Modifier.align(Alignment.BottomCenter)
        )

        // PHASE 9: one-time gentle nudge to rate finished visits.
        if (uiState.pendingReviews.isNotEmpty()) {
            RatingReminderSheet(
                pending = uiState.pendingReviews,
                onRateDoctor = { review ->
                    viewModel.markReviewsPrompted()
                    onNavigateToDoctor(review.doctorId, true)
                },
                onDismiss = { viewModel.markReviewsPrompted() }
            )
        }
    }
}

// ── Hero ────────────────────────────────────────────────────────────────────

/**
 * Kepala Beranda: gradasi warna klinik, sapaan, dan tiga angka yang paling
 * sering dicari pemilik hewan (jumlah hewan, kunjungan, sisa tagihan).
 *
 * Warna teks dihitung dari luminance warna klinik supaya tenant dengan warna
 * terang tetap terbaca (bukan putih di atas kuning).
 */
@Composable
private fun HomeHero(
    userName: String,
    userPhoto: String?,
    clinicName: String?,
    clinicLogo: String?,
    clinicColor: String?,
    summary: String,
    totalPets: Int,
    totalVisits: Int,
    outstandingAmount: Double,
    unreadNotificationCount: Int,
    onNavigateToNotifications: () -> Unit,
    onNavigateToProfile: () -> Unit
) {
    val brand = ClinicTheme.parsePrimaryColor(clinicColor)
    val onHero = if (brand.luminance() > 0.55f) OnPrimaryStrong else Color.White
    val firstName = userName.trim().split(" ").firstOrNull { it.isNotBlank() }.orEmpty()

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp))
            .background(brand)
    ) {

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = HomeGutter, end = HomeGutter, top = 10.dp, bottom = 20.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(onHero.copy(alpha = 0.18f))
                        .border(1.5.dp, onHero.copy(alpha = 0.45f), CircleShape)
                        .clickable(onClick = onNavigateToProfile)
                        .padding(2.dp),
                    contentAlignment = Alignment.Center
                ) {
                    val avatarModel = userPhoto?.takeIf { it.isNotBlank() }
                        ?: clinicLogo?.takeIf { it.isNotBlank() }
                    if (!avatarModel.isNullOrBlank()) {
                        ThumbnailImage(
                            model = avatarModel,
                            contentDescription = "Foto profil",
                            modifier = Modifier
                                .fillMaxSize()
                                .clip(CircleShape)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Filled.Person,
                            contentDescription = null,
                            tint = onHero,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = if (firstName.isNotBlank()) "Halo, $firstName" else "Selamat datang",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = onHero,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = clinicName?.takeIf { it.isNotBlank() }?.let { "Klinik terakhir · $it" }
                            ?: "Pantau kesehatan hewan Anda",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = onHero.copy(alpha = 0.82f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }

                Box(
                    modifier = Modifier
                        .size(42.dp)
                        .clip(CircleShape)
                        .background(onHero.copy(alpha = 0.16f))
                        .clickable(onClick = onNavigateToNotifications),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Notifications,
                        contentDescription = "Notifikasi",
                        tint = onHero,
                        modifier = Modifier.size(21.dp)
                    )
                    if (unreadNotificationCount > 0) {
                        Box(
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .offset(x = 1.dp, y = (-1).dp)
                                .size(if (unreadNotificationCount > 9) 18.dp else 15.dp)
                                .clip(CircleShape)
                                .background(ErrorStrong)
                                .border(1.5.dp, brand, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = if (unreadNotificationCount > 99) "99+" else unreadNotificationCount.toString(),
                                color = Color.White,
                                fontSize = 8.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                }
            }

            if (summary.isNotBlank()) {
                Spacer(modifier = Modifier.height(14.dp))
                Text(
                    text = summary,
                    fontSize = 12.5.sp,
                    lineHeight = 18.sp,
                    color = onHero.copy(alpha = 0.86f),
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                HeroStat(
                    label = "Hewan",
                    value = totalPets.toString(),
                    tint = onHero,
                    modifier = Modifier.weight(1f)
                )
                HeroStat(
                    label = "Kunjungan",
                    value = totalVisits.toString(),
                    tint = onHero,
                    modifier = Modifier.weight(1f)
                )
                HeroStat(
                    label = "Tagihan",
                    value = formatRupiahCompact(outstandingAmount),
                    tint = onHero,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
private fun HeroStat(
    label: String,
    value: String,
    tint: Color,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .clip(RoundedCornerShape(14.dp))
            .background(tint.copy(alpha = 0.14f))
            .padding(horizontal = 10.dp, vertical = 9.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        Text(
            text = label,
            fontSize = 10.sp,
            fontWeight = FontWeight.Medium,
            color = tint.copy(alpha = 0.80f),
            maxLines = 1
        )
        Text(
            text = value,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = tint,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

// ── Aksi cepat ──────────────────────────────────────────────────────────────

@Composable
private fun QuickActionsCard(
    modifier: Modifier = Modifier,
    onNavigateToDiscovery: () -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookingStart: () -> Unit,
    onNavigateToPets: () -> Unit,
    onNavigateToMedicalRecords: () -> Unit
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(22.dp),
        color = HomeSurface,
        shadowElevation = 2.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 6.dp, vertical = 12.dp)
        ) {
            QuickActionTile(
                icon = Icons.Filled.LocalHospital,
                label = "Klinik",
                iconBg = PrimaryLight,
                iconTint = PrimaryDark,
                onClick = onNavigateToDiscovery,
                modifier = Modifier.weight(1f)
            )
            QuickActionTile(
                icon = Icons.Filled.MedicalServices,
                label = "Dokter",
                iconBg = InfoSoft,
                iconTint = InfoStrong,
                onClick = onNavigateToDoctors,
                modifier = Modifier.weight(1f)
            )
            QuickActionTile(
                icon = Icons.Filled.CalendarMonth,
                label = "Booking",
                iconBg = WarningSoft,
                iconTint = WarningText,
                onClick = onNavigateToBookingStart,
                modifier = Modifier.weight(1f)
            )
            QuickActionTile(
                icon = Icons.Filled.Pets,
                label = "Hewan",
                iconBg = SuccessSoft,
                iconTint = SuccessDeep,
                onClick = onNavigateToPets,
                modifier = Modifier.weight(1f)
            )
            QuickActionTile(
                icon = Icons.Filled.Article,
                label = "Rekam",
                iconBg = HomeSoftSurface,
                iconTint = TextMuted,
                onClick = onNavigateToMedicalRecords,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
private fun QuickActionTile(
    icon: ImageVector,
    label: String,
    iconBg: Color,
    iconTint: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .heightIn(min = 76.dp)
            .clip(RoundedCornerShape(16.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 6.dp, horizontal = 2.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(iconBg),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = iconTint,
                modifier = Modifier.size(23.dp)
            )
        }
        Text(
            text = label,
            fontSize = 10.5.sp,
            fontWeight = FontWeight.Medium,
            color = TextMuted,
            lineHeight = 13.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            textAlign = TextAlign.Center
        )
    }
}

// ── Perlu perhatian ─────────────────────────────────────────────────────────

/**
 * Strip ringkas hal-hal yang menunggu tindakan. Hanya tampil bila ada angka
 * > 0 — strip kosong lebih baik daripada lima "0" yang tidak berarti.
 */
@Composable
private fun AttentionSection(
    pendingBookings: Int,
    paymentAttentionCount: Int,
    followUpDueCount: Int,
    dueVaccinationCount: Int,
    overdueVaccinationCount: Int
) {
    val alerts = buildList {
        if (pendingBookings > 0) add(
            AlertSpec("$pendingBookings booking menunggu", Icons.Filled.PendingActions, WarningSoft, WarningText)
        )
        if (paymentAttentionCount > 0) add(
            AlertSpec("$paymentAttentionCount pembayaran", Icons.Filled.Payments, InfoSoft, InfoStrong)
        )
        if (overdueVaccinationCount > 0) add(
            AlertSpec("$overdueVaccinationCount vaksin terlambat", Icons.Filled.Vaccines, ErrorSoft, StatusRejected)
        )
        if (dueVaccinationCount > 0) add(
            AlertSpec("$dueVaccinationCount vaksin jatuh tempo", Icons.Filled.Vaccines, WarningSoft, WarningText)
        )
        if (followUpDueCount > 0) add(
            AlertSpec("$followUpDueCount follow-up", Icons.Filled.EventAvailable, PrimaryLight, PrimaryDark)
        )
    }
    if (alerts.isEmpty()) return

    Column(modifier = Modifier.padding(top = 20.dp)) {
        SectionTitle("Perlu perhatian")
        Spacer(modifier = Modifier.height(10.dp))
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .horizontalScroll(rememberScrollState())
                .padding(horizontal = HomeGutter),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            alerts.forEach { alert ->
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(999.dp))
                        .background(alert.background)
                        .padding(horizontal = 12.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = alert.icon,
                        contentDescription = null,
                        tint = alert.tint,
                        modifier = Modifier.size(15.dp)
                    )
                    Text(
                        text = alert.label,
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = alert.tint,
                        maxLines = 1
                    )
                }
            }
        }
    }
}

private data class AlertSpec(
    val label: String,
    val icon: ImageVector,
    val background: Color,
    val tint: Color
)

// ── Judul seksi bersama ─────────────────────────────────────────────────────

@Composable
private fun SectionTitle(
    title: String,
    actionLabel: String? = null,
    onAction: (() -> Unit)? = null
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = HomeGutter),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(
            text = title,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = HomeTextPrimary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f)
        )
        if (actionLabel != null && onAction != null) {
            Row(
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .clickable(onClick = onAction)
                    .padding(start = 8.dp, end = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(2.dp)
            ) {
                Text(
                    text = actionLabel,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = HomePrimaryDark,
                    maxLines = 1
                )
                Icon(
                    imageVector = Icons.Filled.ChevronRight,
                    contentDescription = null,
                    tint = HomePrimaryDark,
                    modifier = Modifier.size(15.dp)
                )
            }
        }
    }
}

// ── Jadwal terdekat ─────────────────────────────────────────────────────────

@Composable
private fun UpcomingBookingSection(
    uiState: HomeUiState,
    onNavigateToBookings: () -> Unit,
    onNavigateToBookingDetail: (Int) -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookingStart: () -> Unit
) {
    Column(modifier = Modifier.padding(top = 20.dp)) {
        SectionTitle(
            title = "Jadwal terdekat",
            actionLabel = "Lihat semua",
            onAction = onNavigateToBookings
        )
        Spacer(modifier = Modifier.height(10.dp))

        val booking = uiState.upcomingBooking
        when {
            uiState.isBookingLoading && booking == null -> BookingCardSkeleton(
                modifier = Modifier.padding(horizontal = HomeGutter)
            )

            booking == null -> EmptyAppointmentCard(
                modifier = Modifier.padding(horizontal = HomeGutter),
                onBook = onNavigateToBookingStart,
                onFindDoctor = onNavigateToDoctors
            )

            else -> NextAppointmentCard(
                modifier = Modifier.padding(horizontal = HomeGutter),
                booking = booking,
                onOpen = { booking.id?.let(onNavigateToBookingDetail) ?: onNavigateToBookingStart() },
                onFindDoctor = onNavigateToDoctors
            )
        }
    }
}

@Composable
private fun NextAppointmentCard(
    booking: Booking,
    onOpen: () -> Unit,
    onFindDoctor: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(22.dp),
        color = HomeSurface,
        shadowElevation = 2.dp
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 12.dp),
                verticalAlignment = Alignment.Top,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                DateTile(isoDate = booking.bookingDate)

                Column(
                    modifier = Modifier.weight(1f),
                    verticalArrangement = Arrangement.spacedBy(3.dp)
                ) {
                    Text(
                        text = "Konsultasi hewan",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = HomePrimaryDark,
                        letterSpacing = 0.6.sp
                    )
                    Text(
                        text = booking.doctor?.name ?: "Dokter hewan",
                        fontSize = 15.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = HomeTextPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = booking.doctor?.specialization ?: "Dokter Hewan",
                        fontSize = 12.sp,
                        color = HomeTextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Schedule,
                            contentDescription = null,
                            tint = HomePrimaryDark,
                            modifier = Modifier.size(14.dp)
                        )
                        Text(
                            text = listOfNotNull(
                                booking.bookingTime?.takeIf { it.isNotBlank() },
                                formatDateSafe(booking.bookingDate, "EEE, d MMM")
                            ).joinToString(" · ").ifBlank { "Jadwal belum tersedia" },
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = TextMuted,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    PetAndClinicRow(booking = booking)
                }

                BookingAvatar(photo = booking.doctor?.photo)
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp)
                    .padding(bottom = 14.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Button(
                    onClick = onOpen,
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = HomePrimary,
                        contentColor = Color.White
                    ),
                    contentPadding = PaddingValues(horizontal = 12.dp)
                ) {
                    Text("Buka jadwal", fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
                }
                OutlinedButton(
                    onClick = onFindDoctor,
                    modifier = Modifier.height(44.dp),
                    shape = RoundedCornerShape(14.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Outline),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = HomeTextPrimary),
                    contentPadding = PaddingValues(horizontal = 14.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.MedicalServices,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Cari dokter", fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

/** Baris hewan + klinik yang ikut dijadwalkan (bukan klinik terakhir dipakai). */
@Composable
private fun PetAndClinicRow(booking: Booking) {
    val petLabel = booking.pet?.let { pet ->
        listOfNotNull(pet.name, speciesLabelOf(pet.species).takeIf { it.isNotBlank() })
            .joinToString(" · ")
            .ifBlank { null }
    }
    if (petLabel == null && booking.clinic?.name.isNullOrBlank()) return

    Row(
        modifier = Modifier.padding(top = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(
            imageVector = Icons.Filled.Pets,
            contentDescription = null,
            tint = TextTertiary,
            modifier = Modifier.size(13.dp)
        )
        Text(
            text = listOfNotNull(petLabel, booking.clinic?.name?.takeIf { it.isNotBlank() })
                .joinToString(" · "),
            fontSize = 11.sp,
            color = HomeTextSecondary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

/** Blok tanggal ringkas: angka hari + bulan singkat. */
@Composable
private fun DateTile(isoDate: String?) {
    val parts = parseBookingDate(isoDate)
    Column(
        modifier = Modifier
            .width(54.dp)
            .height(60.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(PrimaryLight),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = parts?.day ?: "—",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = PrimaryDark,
            maxLines = 1
        )
        Text(
            text = parts?.month ?: "tanggal",
            fontSize = 10.sp,
            fontWeight = FontWeight.SemiBold,
            color = PrimaryDark.copy(alpha = 0.85f),
            maxLines = 1
        )
    }
}

@Composable
private fun BookingAvatar(photo: String?) {
    val doctorPhoto = remember(photo) { buildPhotoUrl(photo) }
    Box(
        modifier = Modifier
            .size(52.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(HomeSoftSurface),
        contentAlignment = Alignment.Center
    ) {
        if (!doctorPhoto.isNullOrBlank()) {
            ThumbnailImage(
                model = doctorPhoto,
                contentDescription = "Foto dokter",
                modifier = Modifier.fillMaxSize()
            )
        } else {
            Icon(
                imageVector = Icons.Filled.Person,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.size(24.dp)
            )
        }
    }
}

@Composable
private fun EmptyAppointmentCard(
    onBook: () -> Unit,
    onFindDoctor: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(22.dp),
        color = HomeSurface,
        border = androidx.compose.foundation.BorderStroke(1.dp, Outline),
        shadowElevation = 0.dp
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(44.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .background(PrimaryWash),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.CalendarMonth,
                        contentDescription = null,
                        tint = HomePrimaryDark,
                        modifier = Modifier.size(22.dp)
                    )
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Belum ada jadwal aktif",
                        fontSize = 14.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = HomeTextPrimary
                    )
                    Text(
                        text = "Jadwalkan konsultasi agar kondisi hewan tetap terpantau.",
                        fontSize = 11.5.sp,
                        lineHeight = 16.sp,
                        color = HomeTextSecondary
                    )
                }
            }
            Spacer(modifier = Modifier.height(14.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Button(
                    onClick = onBook,
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = HomePrimary,
                        contentColor = Color.White
                    ),
                    contentPadding = PaddingValues(horizontal = 12.dp)
                ) {
                    Text("Buat jadwal", fontSize = 13.5.sp, fontWeight = FontWeight.Bold)
                }
                OutlinedButton(
                    onClick = onFindDoctor,
                    modifier = Modifier.height(44.dp),
                    shape = RoundedCornerShape(14.dp),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Outline),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = HomeTextPrimary),
                    contentPadding = PaddingValues(horizontal = 14.dp)
                ) {
                    Text("Lihat dokter", fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun BookingCardSkeleton(modifier: Modifier = Modifier) {
    val skeletonColor = Outline.copy(alpha = 0.5f)
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .height(150.dp),
        shape = RoundedCornerShape(22.dp),
        color = HomeSurface,
        shadowElevation = 1.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .width(54.dp)
                    .height(60.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(skeletonColor)
            )
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                SkeletonBar(width = 80.dp, height = 10.dp, color = skeletonColor)
                SkeletonBar(width = 150.dp, height = 16.dp, color = skeletonColor)
                SkeletonBar(width = 110.dp, height = 12.dp, color = skeletonColor)
                SkeletonBar(width = 130.dp, height = 12.dp, color = skeletonColor)
            }
        }
    }
}

@Composable
private fun SkeletonBar(
    width: androidx.compose.ui.unit.Dp,
    height: androidx.compose.ui.unit.Dp,
    color: Color
) {
    Box(
        modifier = Modifier
            .width(width)
            .height(height)
            .clip(RoundedCornerShape(6.dp))
            .background(color)
    )
}

private data class DateParts(val day: String, val month: String)

private fun parseBookingDate(iso: String?): DateParts? {
    val raw = iso?.takeIf { it.isNotBlank() } ?: return null
    return try {
        val date = java.time.LocalDate.parse(raw.take(10))
        val locale = java.util.Locale("id", "ID")
        DateParts(
            day = date.dayOfMonth.toString(),
            month = date.format(java.time.format.DateTimeFormatter.ofPattern("MMM", locale))
        )
    } catch (_: Exception) {
        null
    }
}

// ── Dokter terdekat ──

/**
 * MULTI-CLINIC: dokter terdekat + kliniknya.
 *
 * Dua keadaan jujur:
 *  - lokasi diketahui → maksimal 3 kartu dokter terdekat, tiap kartu
 *    menyebut klinik dan jaraknya;
 *  - lokasi belum ada → satu ajakan mengaktifkan lokasi. Tidak ada angka
 *    jarak karangan dan tidak ada izin yang diminta otomatis dari Beranda.
 */
@Composable
private fun NearbyDoctorsSection(
    state: HomeUiState,
    onDoctorClick: (doctorId: Int, clinicId: Int?) -> Unit,
    onSeeAll: () -> Unit,
    onEnableLocation: () -> Unit
) {
    val hasDoctors = state.nearbyDoctors.isNotEmpty()
    // Tanpa lokasi dan tanpa dokter, seksi tetap tampil sebagai ajakan —
    // kecuali memang gagal memuat dokter (sudah ada pesan retry di Cari Dokter).
    if (!hasDoctors && state.nearbyHasLocation) return
    if (!hasDoctors && state.nearbyLoadError != null) return

    Column(modifier = Modifier.padding(top = 20.dp)) {
        SectionTitle(
            title = "Dokter terdekat",
            actionLabel = "Lihat semua",
            onAction = onSeeAll
        )
        Spacer(modifier = Modifier.height(10.dp))

        when {
            state.isNearbyLoading && !hasDoctors -> Row(
                modifier = Modifier.padding(horizontal = HomeGutter),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Text(
                    text = "Memuat dokter terdekat…",
                    fontSize = 12.sp,
                    color = HomeTextSecondary
                )
            }

            !state.nearbyHasLocation -> LocationPromptCard(
                modifier = Modifier.padding(horizontal = HomeGutter),
                onEnableLocation = onEnableLocation
            )

            else -> Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = HomeGutter),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                state.nearbyDoctors.forEach { listing ->
                    NearbyDoctorCard(
                        listing = listing,
                        onClick = { listing.id?.let { onDoctorClick(it, listing.clinicId) } }
                    )
                }
            }
        }
    }
}

@Composable
private fun LocationPromptCard(
    onEnableLocation: () -> Unit,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .clickable(onClick = onEnableLocation),
        shape = RoundedCornerShape(20.dp),
        color = HomeSoftSurface
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(38.dp)
                    .clip(CircleShape)
                    .background(PrimaryLight),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Outlined.MyLocation,
                    contentDescription = null,
                    tint = HomePrimaryDark,
                    modifier = Modifier.size(19.dp)
                )
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Aktifkan lokasi",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = HomeTextPrimary
                )
                Text(
                    text = "Supaya jarak ke tiap klinik terlihat.",
                    fontSize = 11.5.sp,
                    color = HomeTextSecondary
                )
            }
            Icon(
                imageVector = Icons.Filled.ChevronRight,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.size(18.dp)
            )
        }
    }
}

@Composable
private fun NearbyDoctorCard(listing: DoctorListing, onClick: () -> Unit) {
    val doctor = listing.doctor
    Surface(
        modifier = Modifier
            .width(196.dp)
            .clip(RoundedCornerShape(20.dp))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(20.dp),
        color = HomeSurface,
        shadowElevation = 2.dp
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(42.dp)
                        .clip(CircleShape)
                        .background(HomeBg),
                    contentAlignment = Alignment.Center
                ) {
                    val photo = remember(doctor.photo) { buildPhotoUrl(doctor.photo) }
                    if (!photo.isNullOrBlank()) {
                        ThumbnailImage(
                            model = photo,
                            contentDescription = doctor.name,
                            modifier = Modifier.fillMaxSize().clip(CircleShape)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Filled.Person,
                            contentDescription = null,
                            tint = TextTertiary,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
                listing.distanceLabel?.let { distance ->
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(999.dp))
                            .background(PrimaryWash)
                            .padding(horizontal = 8.dp, vertical = 4.dp)
                    ) {
                        Text(
                            text = distance,
                            fontSize = 10.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryDark,
                            maxLines = 1
                        )
                    }
                }
            }

            Column(verticalArrangement = Arrangement.spacedBy(1.dp)) {
                Text(
                    text = doctor.name ?: "Dokter",
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = HomeTextPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = doctor.specialization ?: "Dokter Hewan",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                    color = HomePrimaryDark,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Icon(
                    imageVector = Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(12.dp)
                )
                Text(
                    text = listing.clinicLabel ?: "Klinik belum diisi",
                    fontSize = 10.5.sp,
                    color = HomeTextSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
    }
}

// ── Pet Shop ──

@Composable
private fun PetShopBanner(onClick: () -> Unit, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(22.dp))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(22.dp),
        color = Color.Transparent
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(PrimaryDark)
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(Color.White.copy(alpha = 0.20f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.Storefront,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(21.dp)
                )
            }
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Pet Shop",
                    fontSize = 14.5.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Text(
                    text = "Makanan, obat, dan aksesoris dari klinik mitra",
                    fontSize = 11.sp,
                    color = Color.White.copy(alpha = 0.85f),
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
            Icon(
                imageVector = Icons.Filled.ChevronRight,
                contentDescription = null,
                tint = Color.White,
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

// ── Aktivitas perawatan ──

@Composable
private fun RecentActivitySection(recentVisits: List<MedicalRecord>, onSeeAll: () -> Unit) {
    Column(modifier = Modifier.padding(top = 20.dp, bottom = 8.dp)) {
        SectionTitle(
            title = "Aktivitas terbaru",
            actionLabel = if (recentVisits.isNotEmpty()) "Semua rekam medis" else null,
            onAction = onSeeAll
        )
        Spacer(modifier = Modifier.height(10.dp))

        if (recentVisits.isNotEmpty()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = HomeGutter),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                recentVisits.take(5).forEach { record ->
                    RecentVisitCard(record = record)
                }
            }
        } else {
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = HomeGutter),
                shape = RoundedCornerShape(20.dp),
                color = HomeSurface,
                border = androidx.compose.foundation.BorderStroke(1.dp, Outline)
            ) {
                Column(
                    modifier = Modifier.padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = "Belum ada aktivitas medis",
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = HomeTextPrimary
                    )
                    Text(
                        text = "Ringkasan kunjungan muncul setelah konsultasi selesai.",
                        fontSize = 11.5.sp,
                        lineHeight = 16.sp,
                        color = HomeTextSecondary
                    )
                }
            }
        }
    }
}

@Composable
private fun RecentVisitCard(record: MedicalRecord) {
    Surface(
        modifier = Modifier.width(238.dp),
        shape = RoundedCornerShape(20.dp),
        color = HomeSurface,
        shadowElevation = 2.dp
    ) {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(7.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(34.dp)
                        .clip(RoundedCornerShape(11.dp))
                        .background(PrimaryLight),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.MedicalServices,
                        contentDescription = null,
                        tint = PrimaryDark,
                        modifier = Modifier.size(17.dp)
                    )
                }
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = record.pet?.name ?: "Pasien",
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = HomeTextPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = record.doctor?.name ?: "Dokter",
                        fontSize = 11.sp,
                        color = HomeTextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }

            Text(
                text = record.diagnosis?.takeIf { it.isNotBlank() }
                    ?: record.treatment?.takeIf { it.isNotBlank() }
                    ?: "Kunjungan klinis telah dicatat.",
                fontSize = 12.sp,
                lineHeight = 17.sp,
                color = TextMuted,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )

            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    text = formatDateSafe(record.createdAt, "d MMM yyyy"),
                    fontSize = 10.5.sp,
                    color = TextTertiary,
                    maxLines = 1
                )
                record.clinic?.name?.takeIf { it.isNotBlank() }?.let { clinicName ->
                    Text(
                        text = "· $clinicName",
                        fontSize = 10.5.sp,
                        color = TextTertiary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
        }
    }
}

// ── Pengingat penilaian ──

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RatingReminderSheet(
    pending: List<PendingReview>,
    onRateDoctor: (PendingReview) -> Unit,
    onDismiss: () -> Unit
) {
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = HomeSurface
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp)
                .padding(bottom = 32.dp)
        ) {
            Text(
                text = "Kunjungan selesai",
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                color = HomePrimary,
                letterSpacing = 1.2.sp
            )
            Spacer(Modifier.height(6.dp))
            Text(
                text = "Bagaimana pengalaman konsultasinya?",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = HomeTextPrimary,
                lineHeight = 26.sp
            )
            Spacer(Modifier.height(6.dp))
            Text(
                text = "Penilaian Anda membantu dokter meningkatkan pelayanan dan membantu pemilik hewan lain memilih dengan yakin.",
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = HomeTextSecondary
            )
            Spacer(Modifier.height(16.dp))
            pending.forEach { review ->
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(16.dp))
                        .background(HomeSoftSurface)
                        .border(1.dp, Outline, RoundedCornerShape(16.dp))
                        .clickable { onRateDoctor(review) }
                        .padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    val photoModel = remember(review.doctorPhoto) { buildPhotoUrl(review.doctorPhoto) }
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(HomePrimary.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        if (!photoModel.isNullOrBlank()) {
                            ThumbnailImage(
                                model = photoModel,
                                contentDescription = review.doctorName,
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(CircleShape)
                            )
                        } else {
                            Icon(
                                imageVector = Icons.Filled.Person,
                                contentDescription = null,
                                tint = HomePrimary,
                                modifier = Modifier.size(26.dp)
                            )
                        }
                    }
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = review.doctorName,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = HomeTextPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = listOfNotNull(
                                review.specialization,
                                review.visitDate?.let { formatVisitDate(it) }
                            ).joinToString(" • "),
                            fontSize = 12.sp,
                            color = HomeTextSecondary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    Button(
                        onClick = { onRateDoctor(review) },
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = HomePrimary,
                            contentColor = Color.White
                        ),
                        contentPadding = PaddingValues(horizontal = 14.dp, vertical = 8.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Star,
                            contentDescription = null,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(Modifier.width(6.dp))
                        Text("Nilai", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    }
                }
                Spacer(Modifier.height(10.dp))
            }
            TextButton(
                onClick = onDismiss,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    "Nanti saja",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = HomeTextSecondary
                )
            }
        }
    }
}

private fun formatVisitDate(iso: String): String {
    return try {
        java.time.LocalDate.parse(iso.take(10)).format(
            java.time.format.DateTimeFormatter.ofPattern("d MMM yyyy", java.util.Locale("id", "ID"))
        )
    } catch (_: Exception) {
        iso.take(10)
    }
}
