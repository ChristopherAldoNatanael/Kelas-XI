package com.christopheraldoo.petheal.ui.screens.home

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Article
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.EventAvailable
import androidx.compose.material.icons.filled.Insights
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.PendingActions
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Vaccines
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl

private val HomePrimary = Color(0xFF18C964)
private val HomeBg = Color(0xFFF6F8F6)
private val HomeSurface = Color.White
private val HomeBorder = Color(0xFFE2E8F0)
private val HomeTextPrimary = Color(0xFF0F172A)
private val HomeTextSecondary = Color(0xFF64748B)
private val HomeSoftSurface = Color(0xFFF8FAFC)

@Composable
fun HomeScreen(
    onNavigateToPets: () -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookings: () -> Unit,
    onNavigateToBookingDetail: (Int) -> Unit = {},
    onNavigateToMedicalRecords: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToNotifications: () -> Unit = {},
    onNavigateToDoctor: (doctorId: Int, autoReview: Boolean) -> Unit = { _, _ -> },
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HomeBg)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(bottom = 104.dp)
        ) {
            HeaderSection(
                userName = uiState.userName,
                userPhoto = uiState.userPhoto,
                unreadNotificationCount = uiState.unreadNotificationCount,
                clinicName = uiState.clinicName,
                clinicLogo = uiState.clinicLogo,
                clinicColor = uiState.clinicColor,
                onNavigateToNotifications = onNavigateToNotifications
            )

            Column(modifier = Modifier.padding(horizontal = 24.dp, vertical = 12.dp)) {
                uiState.loadError?.let { loadError ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 14.dp)
                            .clip(RoundedCornerShape(16.dp))
                            .background(Color(0xFFFEF2F2))
                            .padding(horizontal = 14.dp, vertical = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Text(
                            text = loadError,
                            fontSize = 12.sp,
                            color = Color(0xFFB91C1C),
                            modifier = Modifier.weight(1f)
                        )
                        TextButton(onClick = { viewModel.refresh() }) {
                            Text(
                                "Coba Lagi",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = HomePrimary
                            )
                        }
                    }
                }
                DashboardSummaryCard(
                    summary = uiState.dashboardSummary,
                    totalPets = uiState.totalPets,
                    activePets = uiState.activePets,
                    totalVisits = uiState.totalVisits,
                    totalSpent = uiState.totalSpent,
                    unreadNotifications = uiState.unreadNotificationCount
                )
                Spacer(modifier = Modifier.height(14.dp))
                OperationalOverviewRow(
                    pendingBookings = uiState.pendingBookings,
                    paymentAttentionCount = uiState.paymentAttentionCount,
                    followUpDueCount = uiState.followUpDueCount,
                    dueVaccinationCount = uiState.dueVaccinationCount,
                    overdueVaccinationCount = uiState.overdueVaccinationCount
                )
                Spacer(modifier = Modifier.height(14.dp))
                AttentionPanel(
                    outstandingAmount = uiState.outstandingAmount,
                    confirmedBookings = uiState.confirmedBookings,
                    followUpDueCount = uiState.followUpDueCount,
                    dueVaccinationCount = uiState.dueVaccinationCount,
                    overdueVaccinationCount = uiState.overdueVaccinationCount
                )
            }

            UpcomingBookingSection(
                uiState = uiState,
                onNavigateToBookings = onNavigateToBookings,
                onNavigateToBookingDetail = onNavigateToBookingDetail,
                onNavigateToDoctors = onNavigateToDoctors
            )

            QuickActionsSection(
                onNavigateToDoctors = onNavigateToDoctors,
                onNavigateToPets = onNavigateToPets,
                onNavigateToMedicalRecords = onNavigateToMedicalRecords
            )

            RecentActivitySection(recentVisits = uiState.recentVisits)
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
                        .border(1.dp, HomeBorder, RoundedCornerShape(16.dp))
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

@Composable
private fun HeaderSection(
    userName: String,
    userPhoto: String?,
    unreadNotificationCount: Int,
    // PHASE 7: dynamic tenant branding w/ safe fallbacks (never crash).
    clinicName: String?,
    clinicLogo: String?,
    clinicColor: String?,
    onNavigateToNotifications: () -> Unit
) {
    val brandColor = com.christopheraldoo.petheal.ui.theme.ClinicTheme.parsePrimaryColor(clinicColor)
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 24.dp)
            .padding(top = 40.dp, bottom = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .border(2.dp, brandColor.copy(alpha = 0.35f), CircleShape)
                    .background(brandColor.copy(alpha = 0.15f))
            ) {
                // Tenant logo wins; user photo is the fallback; icon last.
                val logoModel = clinicLogo?.takeIf { it.isNotBlank() } ?: userPhoto
                if (!logoModel.isNullOrBlank()) {
                    ThumbnailImage(
                        model = logoModel,
                        contentDescription = clinicName ?: "Foto Profil",
                        modifier = Modifier
                            .fillMaxSize()
                            .clip(CircleShape)
                    )
                } else {
                    Icon(
                        imageVector = Icons.Filled.Person,
                        contentDescription = null,
                        tint = brandColor,
                        modifier = Modifier
                            .size(28.dp)
                            .align(Alignment.Center)
                    )
                }
            }

            Column {
                val firstName = userName.trim().split(" ").firstOrNull { it.isNotBlank() }.orEmpty()
                // PHASE 7: tenant-aware greeting — "Selamat datang di <Klinik>".
                if (!clinicName.isNullOrBlank()) {
                    Text(
                        text = "Selamat datang di",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                        color = HomeTextSecondary
                    )
                    Text(
                        text = clinicName,
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = HomeTextPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (firstName.isNotBlank()) {
                        Text(
                            text = "Halo, $firstName!",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            color = HomeTextSecondary
                        )
                    }
                } else if (firstName.isBlank()) {
                    Text(
                        text = "Selamat datang kembali,",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                        color = HomeTextSecondary
                    )
                    Box(
                        modifier = Modifier
                            .width(90.dp)
                            .height(22.dp)
                            .clip(RoundedCornerShape(6.dp))
                            .background(HomeTextPrimary.copy(alpha = 0.12f))
                    )
                } else {
                    Text(
                        text = "Selamat datang kembali,",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                        color = HomeTextSecondary
                    )
                    Text(
                        text = "$firstName!",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = HomeTextPrimary
                    )
                }
            }
        }

        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(CircleShape)
                .background(HomeSurface)
                .clickable { onNavigateToNotifications() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Filled.Notifications,
                contentDescription = "Notifikasi",
                tint = HomeTextPrimary,
                modifier = Modifier.size(24.dp)
            )
            if (unreadNotificationCount > 0) {
                Box(
                    modifier = Modifier
                        .align(Alignment.TopEnd)
                        .offset(x = 2.dp, y = (-2).dp)
                        .size(if (unreadNotificationCount > 9) 18.dp else 15.dp)
                        .clip(CircleShape)
                        .background(Color(0xFFEF4444)),
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
}

@Composable
private fun DashboardSummaryCard(
    summary: String,
    totalPets: Int,
    activePets: Int,
    totalVisits: Int,
    totalSpent: Double,
    unreadNotifications: Int
) {
    Card(
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = HomeSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "DASBOR HARI INI",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 1.sp,
                        color = HomePrimary
                    )
                    Spacer(modifier = Modifier.height(6.dp))
                    Text(
                        text = summary.ifBlank { "Pantau jadwal, pembayaran, dan tindak lanjut perawatan dalam satu tampilan." },
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        lineHeight = 22.sp,
                        color = HomeTextPrimary
                    )
                }
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(16.dp))
                        .background(HomePrimary.copy(alpha = 0.12f))
                        .padding(horizontal = 12.dp, vertical = 10.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.Insights,
                        contentDescription = null,
                        tint = HomePrimary,
                        modifier = Modifier.size(22.dp)
                    )
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                AttentionPill("Total Hewan", totalPets.toString(), HomeTextPrimary)
                AttentionPill("Sedang Dipantau", activePets.toString(), HomeTextPrimary)
                AttentionPill("Notifikasi Baru", unreadNotifications.toString(), HomeTextPrimary)
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                AttentionPill("Total Kunjungan", totalVisits.toString(), HomeTextPrimary)
                AttentionPill("Biaya Tercatat", "Rp ${formatCurrencyCompact(totalSpent)}", if (totalSpent > 0) Color(0xFF0F766E) else HomeTextPrimary)
            }
        }
    }
}

@Composable
private fun OperationalOverviewRow(
    pendingBookings: Int,
    paymentAttentionCount: Int,
    followUpDueCount: Int,
    dueVaccinationCount: Int,
    overdueVaccinationCount: Int
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        OperationalMetricCard("Booking Pending", pendingBookings.toString(), "Menunggu kepastian jadwal", Icons.Filled.PendingActions, Color(0xFFF59E0B))
        OperationalMetricCard("Perlu Pembayaran", paymentAttentionCount.toString(), "Masih perlu diselesaikan", Icons.Filled.Payments, Color(0xFF0EA5E9))
        OperationalMetricCard("Follow-up", followUpDueCount.toString(), "Perlu dijadwalkan", Icons.Filled.EventAvailable, Color(0xFF8B5CF6))
        OperationalMetricCard(
            "Vaksinasi",
            (dueVaccinationCount + overdueVaccinationCount).toString(),
            if (overdueVaccinationCount > 0) "$overdueVaccinationCount terlambat" else "Perlu dijadwalkan",
            Icons.Filled.Vaccines,
            Color(0xFF10B981)
        )
    }
}

@Composable
private fun OperationalMetricCard(
    title: String,
    value: String,
    helper: String,
    icon: ImageVector,
    tint: Color
) {
    Card(
        modifier = Modifier.width(164.dp),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = HomeSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(RoundedCornerShape(14.dp))
                    .background(tint.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(imageVector = icon, contentDescription = null, tint = tint, modifier = Modifier.size(20.dp))
            }
            Text(text = title, fontSize = 12.sp, fontWeight = FontWeight.Medium, color = HomeTextSecondary)
            Text(text = value, fontSize = 24.sp, fontWeight = FontWeight.Bold, color = HomeTextPrimary)
            Text(text = helper, fontSize = 11.sp, lineHeight = 16.sp, color = HomeTextSecondary)
        }
    }
}

@Composable
private fun AttentionPanel(
    outstandingAmount: Double,
    confirmedBookings: Int,
    followUpDueCount: Int,
    dueVaccinationCount: Int,
    overdueVaccinationCount: Int
) {
    Card(
        shape = RoundedCornerShape(22.dp),
        colors = CardDefaults.cardColors(containerColor = HomeSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder)
    ) {
        Column(
            modifier = Modifier.padding(18.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text("Prioritas Operasional", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = HomeTextPrimary)
                    Text("Ringkasan item yang paling layak Anda tindak lanjuti hari ini", fontSize = 12.sp, color = HomeTextSecondary)
                }
                Text(
                    text = "Rp ${formatCurrency(outstandingAmount)}",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (outstandingAmount > 0) Color(0xFFEA580C) else HomeTextPrimary
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                AttentionPill("Terkonfirmasi", confirmedBookings.toString(), HomeTextPrimary)
                AttentionPill("Follow-up", followUpDueCount.toString(), HomeTextPrimary)
                AttentionPill("Vaksin Jatuh Tempo", dueVaccinationCount.toString(), HomeTextPrimary)
                if (overdueVaccinationCount > 0) {
                    AttentionPill("Terlambat", overdueVaccinationCount.toString(), Color(0xFFB91C1C))
                }
            }
        }
    }
}

@Composable
private fun UpcomingBookingSection(
    uiState: HomeUiState,
    onNavigateToBookings: () -> Unit,
    onNavigateToBookingDetail: (Int) -> Unit,
    onNavigateToDoctors: () -> Unit
) {
    Column(modifier = Modifier.padding(horizontal = 24.dp, vertical = 16.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Jadwal Terdekat", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = HomeTextPrimary)
            Text(
                text = "Lihat Semua",
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = HomePrimary,
                modifier = Modifier.clickable { onNavigateToBookings() }
            )
        }
        Spacer(modifier = Modifier.height(12.dp))

        if (uiState.isBookingLoading && uiState.upcomingBooking == null) {
            BookingCardSkeleton()
        } else {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = HomeSurface),
                border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column(
                            modifier = Modifier.weight(1f),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(32.dp)
                                        .clip(CircleShape)
                                        .background(HomePrimary.copy(alpha = 0.2f)),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(imageVector = Icons.Filled.Pets, contentDescription = null, tint = HomePrimary, modifier = Modifier.size(18.dp))
                                }
                                Text(
                                    text = uiState.upcomingBooking?.let { upcoming ->
                                        "${upcoming.pet?.name ?: "Hewan"} (${upcoming.pet?.species ?: "-"})"
                                    } ?: "Belum ada jadwal aktif",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = Color(0xFF334155),
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }

                            Column {
                                Text(uiState.upcomingBooking?.doctor?.name ?: "-", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = HomeTextPrimary)
                                Text(
                                    text = uiState.upcomingBooking?.let { upcoming ->
                                        "${upcoming.doctor?.specialization ?: "Dokter Hewan"} • Konsultasi"
                                    } ?: "Buat jadwal konsultasi",
                                    fontSize = 12.sp,
                                    color = HomeTextSecondary
                                )
                            }

                            Row(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(8.dp))
                                    .background(HomeBg)
                                    .padding(horizontal = 8.dp, vertical = 6.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Icon(imageVector = Icons.Filled.Schedule, contentDescription = null, tint = HomePrimary, modifier = Modifier.size(16.dp))
                                Text(
                                    text = uiState.upcomingBooking?.let { upcoming ->
                                        "${upcoming.bookingTime ?: ""} • ${upcoming.bookingDate ?: ""}"
                                    } ?: "-",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Medium,
                                    color = Color(0xFF334155)
                                )
                            }
                        }

                        DoctorPhotoCard(photo = uiState.upcomingBooking?.doctor?.photo)
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Button(
                            onClick = { uiState.upcomingBooking?.id?.let(onNavigateToBookingDetail) ?: onNavigateToDoctors() },
                            modifier = Modifier
                                .weight(1f)
                                .height(42.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = HomePrimary, contentColor = Color.White)
                        ) {
                            Text(
                                text = if (uiState.upcomingBooking != null) "Buka Booking" else "Buat Booking",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        OutlinedButton(
                            onClick = onNavigateToDoctors,
                            modifier = Modifier.height(42.dp),
                            shape = RoundedCornerShape(8.dp),
                            border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder),
                            colors = ButtonDefaults.outlinedButtonColors(contentColor = HomeTextPrimary)
                        ) {
                            Icon(imageVector = Icons.Filled.MedicalServices, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(text = "Cari Dokter", fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun DoctorPhotoCard(photo: String?) {
    Box(
        modifier = Modifier
            .size(96.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(Color(0xFFE2E8F0))
    ) {
        val doctorPhoto = remember(photo) { buildPhotoUrl(photo) }
        if (!doctorPhoto.isNullOrBlank()) {
            ThumbnailImage(model = doctorPhoto, contentDescription = "Dokter", modifier = Modifier.fillMaxSize())
        } else {
            Icon(
                imageVector = Icons.Filled.Person,
                contentDescription = null,
                tint = Color(0xFF94A3B8),
                modifier = Modifier
                    .size(44.dp)
                    .align(Alignment.Center)
            )
        }
    }
}

@Composable
private fun QuickActionsSection(
    onNavigateToDoctors: () -> Unit,
    onNavigateToPets: () -> Unit,
    onNavigateToMedicalRecords: () -> Unit
) {
    Column(modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp)) {
        Text("Akses Cepat", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = HomeTextPrimary)
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = "Masuk ke fitur yang paling sering dipakai tanpa harus membuka banyak halaman.",
            fontSize = 12.sp,
            color = HomeTextSecondary
        )
        Spacer(modifier = Modifier.height(16.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            QuickActionItem(Icons.Filled.CalendarMonth, "Buat\nBooking", Color(0xFFDBEAFE), Color(0xFF2563EB), onNavigateToDoctors)
            QuickActionItem(Icons.Filled.Pets, "Hewan\nSaya", Color(0xFFF3E8FF), Color(0xFF9333EA), onNavigateToPets)
            QuickActionItem(Icons.Filled.Article, "Rekam\nMedis", Color(0xFFFFEDD5), Color(0xFFEA580C), onNavigateToMedicalRecords)
            QuickActionItem(Icons.Filled.MedicalServices, "Cari\nDokter", Color(0xFFFFE4E6), Color(0xFFDB2777), onNavigateToDoctors)
        }
    }
}

@Composable
private fun RecentActivitySection(recentVisits: List<MedicalRecord>) {
    Column(modifier = Modifier.padding(top = 16.dp, bottom = 8.dp)) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Aktivitas Perawatan Terbaru", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = HomeTextPrimary)
        }
        Spacer(modifier = Modifier.height(12.dp))

        if (recentVisits.isNotEmpty()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = 24.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                recentVisits.take(5).forEach { record ->
                    RecentVisitCard(record = record)
                }
            }
        } else {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 24.dp),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(containerColor = HomeSurface),
                border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text("Belum ada aktivitas medis terbaru", fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = HomeTextPrimary)
                    Text(
                        text = "Setelah booking selesai atau rekam medis dibuat, ringkasan aktivitas akan muncul di sini agar pemantauan perawatan terasa lebih jelas.",
                        fontSize = 13.sp,
                        lineHeight = 19.sp,
                        color = HomeTextSecondary
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(8.dp))
    }
}

@Composable
private fun QuickActionItem(
    icon: ImageVector,
    label: String,
    bgColor: Color,
    iconColor: Color,
    onClick: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.clickable(onClick = onClick)
    ) {
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(RoundedCornerShape(16.dp))
                .background(bgColor),
            contentAlignment = Alignment.Center
        ) {
            Icon(imageVector = icon, contentDescription = label, tint = iconColor, modifier = Modifier.size(28.dp))
        }
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
            color = Color(0xFF475569),
            lineHeight = 14.sp,
            textAlign = TextAlign.Center
        )
    }
}

@Composable
private fun RecentVisitCard(record: MedicalRecord) {
    Card(
        modifier = Modifier.width(250.dp),
        shape = RoundedCornerShape(18.dp),
        colors = CardDefaults.cardColors(containerColor = HomeSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(HomePrimary.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(imageVector = Icons.Filled.MedicalServices, contentDescription = null, tint = HomePrimary, modifier = Modifier.size(18.dp))
                }
                Column {
                    Text(text = record.pet?.name ?: "Pasien", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = HomeTextPrimary)
                    Text(text = record.doctor?.name ?: "Dokter", fontSize = 12.sp, color = HomeTextSecondary)
                }
            }
            Text(
                text = record.diagnosis?.takeIf { it.isNotBlank() }
                    ?: record.treatment?.takeIf { it.isNotBlank() }
                    ?: "Kunjungan klinis telah dicatat untuk dipantau kembali.",
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = HomeTextPrimary,
                maxLines = 3,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = listOfNotNull(record.createdAt, record.nextVisitDate).joinToString(" • ").ifBlank { "Catatan waktu belum tersedia" },
                fontSize = 11.sp,
                color = HomeTextSecondary
            )
        }
    }
}

@Composable
private fun AttentionPill(
    label: String,
    value: String,
    valueColor: Color
) {
    Column(
        modifier = Modifier
            .clip(RoundedCornerShape(16.dp))
            .background(HomeSoftSurface)
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Text(text = label, fontSize = 10.sp, color = HomeTextSecondary)
        Text(text = value, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = valueColor)
    }
}

@Composable
private fun BookingCardSkeleton() {
    val skeletonColor = Color(0xFFE2E8F0)
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .height(160.dp),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = HomeSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, HomeBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Box(modifier = Modifier.fillMaxSize().padding(16.dp)) {
            Column(
                modifier = Modifier.align(Alignment.CenterStart),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .width(120.dp)
                        .height(16.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(skeletonColor)
                )
                Box(
                    modifier = Modifier
                        .width(160.dp)
                        .height(20.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(skeletonColor)
                )
                Box(
                    modifier = Modifier
                        .width(100.dp)
                        .height(32.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(skeletonColor)
                )
            }
            Box(
                modifier = Modifier
                    .size(96.dp)
                    .align(Alignment.CenterEnd)
                    .clip(RoundedCornerShape(12.dp))
                    .background(skeletonColor)
            )
        }
    }
}

private fun formatCurrency(value: Double): String =
    String.format("%,.0f", value).replace(',', '.')

private fun formatCurrencyCompact(value: Double): String {
    val formatted = formatCurrency(value)
    return if (formatted.length > 10) "${formatted.take(10)}+" else formatted
}
