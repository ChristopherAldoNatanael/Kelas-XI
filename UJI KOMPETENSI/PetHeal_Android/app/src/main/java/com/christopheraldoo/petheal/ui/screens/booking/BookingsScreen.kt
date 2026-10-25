package com.christopheraldoo.petheal.ui.screens.booking

import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface

import android.util.Log
import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalView
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.PaymentMethod
import com.christopheraldoo.petheal.ui.components.BookingDateRail
import com.christopheraldoo.petheal.ui.components.BookingTimeSlots
import com.christopheraldoo.petheal.ui.components.ClinicHelpSheet
import com.christopheraldoo.petheal.ui.components.EmptyBookingsState
import com.christopheraldoo.petheal.ui.components.PaymentReceiptSheet
import com.christopheraldoo.petheal.ui.screens.payment.toMidtransCodes
import com.christopheraldoo.petheal.ui.components.SkeletonBookingCard
import com.christopheraldoo.petheal.ui.components.SkeletonBookingList
import com.christopheraldoo.petheal.util.HapticFeedback
import com.christopheraldoo.petheal.util.buildPhotoUrl
import com.christopheraldoo.petheal.util.firstDoctorWorkingDate
import com.christopheraldoo.petheal.util.isDoctorWorkingDay
import com.christopheraldoo.petheal.util.toCustomHoursDates
import com.christopheraldoo.petheal.util.toLeaveDates
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.BorderStroke
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.compose.material3.ExperimentalMaterial3Api
import com.christopheraldoo.petheal.ui.theme.AccentViolet
import com.christopheraldoo.petheal.ui.theme.Error
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.Neutral
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.Star
import com.christopheraldoo.petheal.ui.theme.StatusCompleted
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.formatRupiah

private const val TAG = "BookingPhoto"

private enum class BookingTab { SCHEDULE, INVOICES }

// ── Brand tokens ───────────────────────────────────────────────────────────────
private val BkPrimary       = Primary
private val BkPrimaryFg     = OnPrimaryStrong
private val BkBg       = Background
private val BkSurface  = Surface

// ── Helper: load doctor photo with ngrok header ─────────────────────────────
@Composable
private fun BkDocPhoto(url: String?, size: androidx.compose.ui.unit.Dp) {
    val context = LocalContext.current

    // Build the full URL using PhotoUtils
    val fullUrl = remember(url) { buildPhotoUrl(url) }

    // Keyed by URL so a recycled composable never shows another doctor's
    // error state when the photo changes.
    var hasError by remember(fullUrl) { mutableStateOf(false) }
    
    // Log URL for debugging
    LaunchedEffect(fullUrl) {
        if (!fullUrl.isNullOrBlank()) {
            Log.d(TAG, "Loading doctor photo from URL: $fullUrl")
        }
    }
      if (!fullUrl.isNullOrBlank() && !hasError) {
        AsyncImage(
            model = ImageRequest.Builder(context)
                .data(fullUrl)
                .memoryCachePolicy(CachePolicy.ENABLED)
                .diskCachePolicy(CachePolicy.ENABLED)
                .memoryCacheKey(fullUrl)
                .diskCacheKey(fullUrl)
                .crossfade(200)
                .build(),
            contentDescription = "Foto dokter",
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize().clip(CircleShape),
            onError = { state ->
                Log.e(TAG, "Failed to load doctor photo from: $fullUrl, error: ${state.result.throwable.message}")
                hasError = true
            },
            onSuccess = {
                Log.d(TAG, "Successfully loaded doctor photo from: $fullUrl")
            }
        )
    } else {
        Icon(Icons.Filled.Person, null, tint = BkPrimary, modifier = Modifier.size(size * 0.5f))
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  BOOKINGS LIST SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToDoctors: () -> Unit,
    onNavigateToBookingDetail: (Int) -> Unit,
    onNavigateToPayment: (Int, Boolean, Double, Boolean) -> Unit, // NEW: (bookingId, isDp, totalAmount, isRemaining)
    onNavigateToBookingStart: (() -> Unit)? = null,
    onTabSelected: (com.christopheraldoo.petheal.ui.components.PetHealTab) -> Unit = {},
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.listState.collectAsState()
    val bg      = BkBg
    val surface = BkSurface
    val textPrimary   = OnBackground
    val textSecondary = TextSecondary
    val border        = SurfaceMuted

    LaunchedEffect(Unit) { viewModel.loadBookings() }

    // State for filter/sort bottom sheet
    var showFilterSheet by remember { mutableStateOf(false) }
    var receiptBooking by remember { mutableStateOf<Booking?>(null) }
    var selectedTab by rememberSaveable { mutableStateOf(BookingTab.SCHEDULE) }
    val view = LocalView.current
    // Ringkasan dihitung dari SEMUA booking (bukan hasil filter) agar angka
    // tidak melompat dan section tidak hilang saat filter menghasilkan kosong.
    val activeBookingsCount = remember(state.allBookings) {
        state.allBookings.count { it.status == "pending" || it.status == "confirmed" }
    }
    val completedBookingsCount = remember(state.allBookings) {
        state.allBookings.count { it.status == "completed" }
    }
    val paymentAttentionCount = remember(state.allBookings) {
        state.allBookings.count {
            val paymentStatus = it.paymentStatus?.lowercase()
            paymentStatus != null && paymentStatus !in setOf("paid", "not_required")
        }
    }
    val outstandingInvoiceCount = remember(state.allBookings) {
        state.allBookings.count { booking ->
            val remaining = booking.remainingAmount
                ?: ((booking.totalAmount ?: 0.0) - (booking.paidAmount ?: 0.0))
            val status = booking.paymentStatus?.lowercase()
            remaining > 0.01 && status !in setOf("paid", "not_required")
        }
    }
    // Filter aktif (dipakai empty-state agar pesan + reset kontekstual).
    val isDateFiltered = state.dateFilter != BookingDateFilter.ALL

    Box(Modifier.fillMaxSize().background(bg)) {
        Column(Modifier.fillMaxSize()) {
            // Header
            Surface(color = surface.copy(alpha = 0.93f), shadowElevation = 0.dp) {
                Column(Modifier.fillMaxWidth()) {
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .statusBarsPadding()
                            .padding(horizontal = 8.dp)
                            .padding(top = 8.dp, bottom = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            Modifier.size(40.dp).clip(CircleShape).clickable { onNavigateBack() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.ArrowBack, "Kembali", tint = textPrimary, modifier = Modifier.size(24.dp))
                        }
                        Text("Booking Saya", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        if (selectedTab == BookingTab.SCHEDULE) {
                            Box(
                                Modifier.size(40.dp).clip(CircleShape).clickable { showFilterSheet = true },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(Icons.Filled.FilterList, "Filter dan urutan", tint = textPrimary, modifier = Modifier.size(24.dp))
                            }
                        } else {
                            Spacer(Modifier.size(40.dp))
                        }
                    }
                    Divider(color = border, thickness = 1.dp)

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 16.dp, vertical = 14.dp)
                    ) {
                        Text(
                            text = if (selectedTab == BookingTab.SCHEDULE) {
                                "Pantau kunjungan, progres pembayaran, dan tindak lanjut dalam satu tempat."
                            } else {
                                "Satu tempat untuk melihat tagihan booking, DP, dan riwayat pembayaran."
                            },
                            fontSize = 13.sp,
                            lineHeight = 19.sp,
                            color = textSecondary
                        )
                        Spacer(modifier = Modifier.height(14.dp))
                        BookingTabRow(
                            selectedTab = selectedTab,
                            outstandingCount = outstandingInvoiceCount,
                            onSelect = { selectedTab = it }
                        )
                        if (selectedTab == BookingTab.SCHEDULE && state.allBookings.isNotEmpty()) {
                            Spacer(modifier = Modifier.height(14.dp))
                            BookingOverviewRow(
                                activeCount = activeBookingsCount,
                                completedCount = completedBookingsCount,
                                paymentAttentionCount = paymentAttentionCount
                            )
                        }
                    }
                    
                    // Active filters indicator
                    if (selectedTab == BookingTab.SCHEDULE &&
                        (state.sortOrder != BookingSortOrder.NEWEST_FIRST || state.dateFilter != BookingDateFilter.ALL)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Filter:", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.Medium)
                            if (state.dateFilter != BookingDateFilter.ALL) {
                                AssistChip(
                                    onClick = { viewModel.setDateFilter(BookingDateFilter.ALL) },
                                    label = { Text(getDateFilterLabel(state.dateFilter), fontSize = 11.sp) },
                                    leadingIcon = {
                                        Icon(
                                            Icons.Filled.Close,
                                            contentDescription = "Hapus filter tanggal",
                                            modifier = Modifier.size(14.dp)
                                        )
                                    },
                                    colors = AssistChipDefaults.assistChipColors(
                                        containerColor = BkPrimary.copy(alpha = 0.15f)
                                    )
                                )
                            }
                            if (state.sortOrder == BookingSortOrder.OLDEST_FIRST) {
                                AssistChip(
                                    onClick = { viewModel.setSortOrder(BookingSortOrder.NEWEST_FIRST) },
                                    label = { Text("Terlama", fontSize = 11.sp) },
                                    leadingIcon = {
                                        Icon(
                                            Icons.Filled.Close,
                                            contentDescription = "Hapus urutan",
                                            modifier = Modifier.size(14.dp)
                                        )
                                    },
                                    colors = AssistChipDefaults.assistChipColors(
                                        containerColor = BkPrimary.copy(alpha = 0.15f)
                                    )
                                )
                            }
                            TextButton(onClick = { viewModel.resetFilters() }) {
                                Text("Atur Ulang", fontSize = 11.sp, color = BkPrimary)
                            }
                        }
                    }
                }
            }

            if (state.isLoading && state.allBookings.isEmpty()) {
                // Show skeleton loading on initial load
                SkeletonBookingList(count = 3)
            } else if (selectedTab == BookingTab.SCHEDULE) {
                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 108.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (state.error != null) {
                        val listError = state.error
                        item {
                            ErrorBanner(
                                listError ?: "Gagal memuat booking",
                                onRetry = { viewModel.loadBookings(forceRefresh = true) }
                            )
                        }
                    }
                    // Error TANPA data ≠ akun kosong: empty state tidak boleh
                    // ikut tampil saat muat gagal (koneksi putus akan terlihat
                    // seperti "belum pernah booking").
                    if (state.bookings.isEmpty() && state.error == null) {
                        item {
                            if (isDateFiltered && state.allBookings.isNotEmpty()) {
                                FilteredBookingsEmptyState(
                                    filterLabel = getDateFilterLabel(state.dateFilter),
                                    onClearFilter = { viewModel.setDateFilter(BookingDateFilter.ALL) },
                                    onResetAll = { viewModel.resetFilters() }
                                )
                            } else {
                                EmptyBookingsState(
                                    // Alur transaksi langsung; discovery tetap
                                    // tersedia via tab/beranda bila dibutuhkan.
                                    onBookNow = { onNavigateToBookingStart?.invoke() ?: onNavigateToDoctors() }
                                )
                            }
                        }
                    } else {
                        items(
                            items = state.bookings,
                            key = { booking -> booking.id ?: booking.hashCode() }
                        ) { booking ->
                            BookingCard(
                                booking = booking,
                                surface = surface,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                onClick = { 
                                    HapticFeedback.performClick(view)
                                    booking.id?.let { onNavigateToBookingDetail(it) } 
                                },
                                onPayRemaining = { bookingId ->
                                    val remainingAmount = booking.remainingAmount
                                        ?: ((booking.totalAmount ?: 0.0) - (booking.paidAmount ?: 0.0))
                                    val totalAmount = booking.totalAmount ?: 0.0
                                    val paidAmount = booking.paidAmount ?: 0.0
                                    val isRemaining = booking.paymentStatus in setOf("dp_paid", "partial") ||
                                        (paidAmount > 0.0 && remainingAmount < totalAmount - 0.01)
                                    onNavigateToPayment(
                                        bookingId,
                                        booking.paymentType == "dp",
                                        remainingAmount,
                                        isRemaining
                                    )
                                }
                            )
                        }
                    }
                }
            } else {
                InvoiceCenter(
                    bookings = state.allBookings,
                    isLoading = state.isLoading,
                    error = state.error,
                    onRetry = { viewModel.loadBookings(forceRefresh = true) },
                    onOpenBooking = { bookingId ->
                        HapticFeedback.performClick(view)
                        onNavigateToBookingDetail(bookingId)
                    },
                    onPay = { booking ->
                        booking.id?.let { bookingId ->
                            val remainingAmount = booking.remainingAmount
                                ?: ((booking.totalAmount ?: 0.0) - (booking.paidAmount ?: 0.0))
                            val isRemaining = booking.paymentStatus in setOf("dp_paid", "partial")
                            onNavigateToPayment(
                                bookingId,
                                booking.paymentType == "dp",
                                remainingAmount,
                                isRemaining
                            )
                        }
                    },
                    onOpenReceipt = { booking ->
                        HapticFeedback.performClick(view)
                        receiptBooking = booking
                    }
                )
            }
        }

        // Filter/Sort Bottom Sheet — memilih tanggal langsung menutup sheet
        // agar daftar yang terfilter segera terlihat (UI langsung memperbarui).
        if (showFilterSheet) {
            BookingFilterBottomSheet(
                currentSortOrder = state.sortOrder,
                currentDateFilter = state.dateFilter,
                onSortOrderChanged = { viewModel.setSortOrder(it) },
                onDateFilterChanged = {
                    viewModel.setDateFilter(it)
                    showFilterSheet = false
                },
                onResetFilters = { viewModel.resetFilters() },
                onDismiss = { showFilterSheet = false }
            )
        }

        receiptBooking?.let { booking ->
            PaymentReceiptSheet(
                booking = booking,
                clinicName = state.clinicName,
                clinicAddress = state.clinicAddress,
                clinicColor = state.clinicColor,
                onDismiss = { receiptBooking = null }
            )
        }

        // ── Unified floating bottom nav (PHASE 9) ──────────────────────
        com.christopheraldoo.petheal.ui.components.PetHealFloatingBottomNav(
            selected = com.christopheraldoo.petheal.ui.components.PetHealTab.Bookings,
            onSelect = onTabSelected,
            modifier = Modifier.align(Alignment.BottomCenter)
        )
    }
}

@Composable
private fun BookingTabRow(
    selectedTab: BookingTab,
    outstandingCount: Int,
    onSelect: (BookingTab) -> Unit
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = SurfaceMuted
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(4.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            BookingTabItem(
                label = "Jadwal",
                selected = selectedTab == BookingTab.SCHEDULE,
                badgeCount = null,
                onClick = { onSelect(BookingTab.SCHEDULE) },
                modifier = Modifier.weight(1f)
            )
            BookingTabItem(
                label = "Tagihan",
                selected = selectedTab == BookingTab.INVOICES,
                badgeCount = outstandingCount.takeIf { it > 0 },
                onClick = { onSelect(BookingTab.INVOICES) },
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
private fun BookingTabItem(
    label: String,
    selected: Boolean,
    badgeCount: Int?,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val background by animateColorAsState(
        targetValue = if (selected) BkSurface else Color.Transparent,
        animationSpec = tween(180)
    )
    val contentColor = if (selected) BkPrimary else TextSecondary

    Surface(
        modifier = modifier
            .clip(RoundedCornerShape(11.dp))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(11.dp),
        color = background
    ) {
        Row(
            modifier = Modifier.padding(vertical = 10.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = label,
                fontSize = 13.sp,
                fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                color = contentColor
            )
            badgeCount?.let { count ->
                Spacer(Modifier.width(6.dp))
                Box(
                    modifier = Modifier
                        .size(18.dp)
                        .clip(CircleShape)
                        .background(WarningSoft),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = if (count > 9) "9+" else count.toString(),
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        color = WarningDeep
                    )
                }
            }
        }
    }
}

@Composable
private fun BookingOverviewRow(
    activeCount: Int,
    completedCount: Int,
    paymentAttentionCount: Int
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        BookingOverviewCard(
            modifier = Modifier.weight(1f),
            label = "Aktif",
            value = activeCount.toString(),
            accent = StatusCompleted
        )
        BookingOverviewCard(
            modifier = Modifier.weight(1f),
            label = "Selesai",
            value = completedCount.toString(),
            accent = Color(0xFF16A34A)
        )
        BookingOverviewCard(
            modifier = Modifier.weight(1f),
            label = "Perlu Bayar",
            value = paymentAttentionCount.toString(),
            accent = WarningDeep
        )
    }
}

@Composable
private fun BookingOverviewCard(
    modifier: Modifier = Modifier,
    label: String,
    value: String,
    accent: Color
) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(16.dp),
        color = Color.White,
        border = BorderStroke(1.dp, accent.copy(alpha = 0.14f))
    ) {
        Column(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .clip(CircleShape)
                    .background(accent)
            )
            Text(
                text = value,
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground
            )
            Text(
                text = label,
                fontSize = 12.sp,
                color = TextSecondary
            )
        }
    }
}

/**
 * Get display label for date filter
 */
private fun getDateFilterLabel(filter: BookingDateFilter): String {
    return when (filter) {
        BookingDateFilter.ALL -> "Semua"
        BookingDateFilter.TODAY -> "Hari Ini"
        BookingDateFilter.TOMORROW -> "Besok"
        BookingDateFilter.THIS_WEEK -> "Minggu Ini"
        BookingDateFilter.THIS_MONTH -> "Bulan Ini"
    }
}

/**
 * Empty state khusus saat filter tanggal menghasilkan nol booking:
 * menegaskan filter yang aktif + selected date terlihat + reset jelas.
 * Mengikuti token warna/huruf layar ini (tanpa style baru).
 */
@Composable
private fun FilteredBookingsEmptyState(
    filterLabel: String,
    onClearFilter: () -> Unit,
    onResetAll: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .clip(CircleShape)
                    .background(BkPrimary.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.CalendarMonth,
                    contentDescription = null,
                    tint = BkPrimary,
                    modifier = Modifier.size(28.dp)
                )
            }
            Text(
                text = "Tidak ada booking ($filterLabel)",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground,
                textAlign = TextAlign.Center
            )
            Text(
                text = "Coba pilih tanggal lain atau atur ulang filter untuk melihat semua booking.",
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = TextSecondary,
                textAlign = TextAlign.Center
            )
            Button(
                onClick = onClearFilter,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = BkPrimary,
                    contentColor = OnPrimaryStrong
                )
            ) {
                Text("Tampilkan Semua", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
            }
            TextButton(onClick = onResetAll) {
                Text("Atur Ulang Filter", fontSize = 12.sp, color = BkPrimary)
            }
        }
    }
}

/**
 * Bottom sheet for filter and sort options
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalLayoutApi::class)
@Composable
private fun BookingFilterBottomSheet(
    currentSortOrder: BookingSortOrder,
    currentDateFilter: BookingDateFilter,
    onSortOrderChanged: (BookingSortOrder) -> Unit,
    onDateFilterChanged: (BookingDateFilter) -> Unit,
    onResetFilters: () -> Unit,
    onDismiss: () -> Unit
) {
    val bgColor = Color.White
    val textPrimary = OnBackground
    val textSecondary = TextSecondary

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        containerColor = bgColor
    ) {
        Column(
            modifier = Modifier.fillMaxWidth().padding(24.dp).padding(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(24.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Filter & Urutkan", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Filled.Close, contentDescription = "Tutup", tint = textSecondary)
                }
            }

            // Sort Options
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Urutkan", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = textSecondary)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Terbaru button
                    FilterChip(
                        selected = currentSortOrder == BookingSortOrder.NEWEST_FIRST,
                        onClick = { onSortOrderChanged(BookingSortOrder.NEWEST_FIRST) },
                        label = { Text("Terbaru", fontSize = 13.sp) },
                        leadingIcon = if (currentSortOrder == BookingSortOrder.NEWEST_FIRST) {
                            { Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(16.dp)) }
                        } else null,
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = BkPrimary,
                            selectedLabelColor = OnPrimaryStrong,
                            selectedLeadingIconColor = OnPrimaryStrong
                        )
                    )
                    // Terlama button
                    FilterChip(
                        selected = currentSortOrder == BookingSortOrder.OLDEST_FIRST,
                        onClick = { onSortOrderChanged(BookingSortOrder.OLDEST_FIRST) },
                        label = { Text("Terlama", fontSize = 13.sp) },
                        leadingIcon = if (currentSortOrder == BookingSortOrder.OLDEST_FIRST) {
                            { Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(16.dp)) }
                        } else null,
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = BkPrimary,
                            selectedLabelColor = OnPrimaryStrong,
                            selectedLeadingIconColor = OnPrimaryStrong
                        )
                    )
                }
            }

            // Date Filter Options
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Filter Tanggal", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = textSecondary)
                FlowRow(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    val dateFilters = listOf(
                        BookingDateFilter.ALL to "Semua",
                        BookingDateFilter.TODAY to "Hari Ini",
                        BookingDateFilter.TOMORROW to "Besok",
                        BookingDateFilter.THIS_WEEK to "Minggu Ini",
                        BookingDateFilter.THIS_MONTH to "Bulan Ini"
                    )
                    dateFilters.forEach { (filter, label) ->
                        FilterChip(
                            selected = currentDateFilter == filter,
                            onClick = { onDateFilterChanged(filter) },
                            label = { Text(label, fontSize = 13.sp) },
                            leadingIcon = if (currentDateFilter == filter) {
                                { Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(16.dp)) }
                            } else null,
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = BkPrimary,
                                selectedLabelColor = OnPrimaryStrong,
                                selectedLeadingIconColor = OnPrimaryStrong
                            )
                        )
                    }
                }
            }

            // Reset Button
            OutlinedButton(
                onClick = {
                    onResetFilters()
                    onDismiss()
                },
                modifier = Modifier.fillMaxWidth().height(48.dp),
                shape = RoundedCornerShape(12.dp),
                border = BorderStroke(1.dp, ErrorStrong),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = ErrorStrong)
            ) {
                Icon(Icons.Filled.Refresh, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text("Reset Filter", fontWeight = FontWeight.Medium)
            }
        }
    }
}

@Composable
private fun BookingCard(
    booking: Booking,
    surface: Color,
    textPrimary: Color,
    textSecondary: Color,
    onClick: () -> Unit,
    onPayRemaining: ((Int) -> Unit)? = null // Callback for paying remaining amount
) {
    val statusColor = when (booking.status?.lowercase()) {
        "confirmed"  -> BkPrimary
        "pending"    -> Tertiary
        "cancelled"  -> ErrorStrong
        "completed"  -> InfoStrong
        else         -> Neutral
    }
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(2.dp, RoundedCornerShape(24.dp), ambientColor = Color.Black.copy(alpha = 0.04f))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(containerColor = surface),
        elevation = CardDefaults.cardElevation(0.dp),
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        Modifier.size(36.dp).clip(CircleShape).background(BkPrimary.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) { Icon(Icons.Filled.Pets, null, tint = BkPrimary, modifier = Modifier.size(20.dp)) }
                    Text(
                        "${booking.pet?.name ?: "–"} (${booking.pet?.species ?: "–"})",
                        fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = textPrimary,
                        maxLines = 1, overflow = TextOverflow.Ellipsis
                    )
                }
                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = statusColor.copy(alpha = 0.12f)
                ) {
                    Text(
                        booking.status?.replaceFirstChar { it.uppercase() } ?: "–",
                        color = statusColor, fontSize = 11.sp, fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            }
            Divider(color = SurfaceMuted)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {                    // Doctor photo
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(SuccessSoft),
                        contentAlignment = Alignment.Center
                    ) {
                        BkDocPhoto(
                            url = buildPhotoUrl(booking.doctor?.photo),
                            size = 44.dp
                        )
                    }
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(booking.doctor?.name ?: "–", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        Text(booking.doctor?.specialization ?: "Veterinarian", fontSize = 12.sp, color = textSecondary)
                    }
                }
                Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.CalendarMonth, null, tint = BkPrimary, modifier = Modifier.size(14.dp))
                        Text(booking.bookingDate ?: "–", fontSize = 12.sp, color = textSecondary)
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Schedule, null, tint = BkPrimary, modifier = Modifier.size(14.dp))
                        Text(booking.bookingTime ?: "–", fontSize = 12.sp, color = textSecondary)
                    }
                }
            }
            // Payment info
            if (booking.paymentMethodId != null || booking.paymentStatus != null) {
                Divider(color = SurfaceMuted)
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            if (booking.paymentType == "dp") Icons.Filled.Savings else Icons.Filled.Payment,
                            null,
                            tint = if (booking.paymentType == "dp") Tertiary else BkPrimary,
                            modifier = Modifier.size(14.dp)
                        )
                        Text(
                            if (booking.paymentType == "dp") "Down Payment" else "Full Payment",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = textSecondary
                        )
                    }
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(6.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Payment status badge with DP support
                        val (paymentStatusText, paymentStatusColor) = when (booking.paymentStatus) {
                            "paid" -> "Lunas" to Success // Green
                            "dp_paid" -> "DP Dibayar (50%)" to Tertiary // Amber
                            "dp_pending" -> "Menunggu DP" to InfoStrong // Blue
                            "partial" -> "Sebagian Dibayar" to Color(0xFFF97316) // Orange
                            "pending" -> "Belum Dibayar" to ErrorStrong // Red
                            "failed" -> "Gagal" to Error // Dark Red
                            else -> (booking.paymentStatus?.replaceFirstChar { it.uppercase() } ?: "Unknown") to Color.Gray
                        }
                        Surface(
                            shape = RoundedCornerShape(999.dp),
                            color = paymentStatusColor.copy(alpha = 0.12f)
                        ) {
                            Text(
                                paymentStatusText,
                                color = paymentStatusColor,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
                            )
                        }
                    }
                }
            }
            
            // Pay Remaining button for DP bookings with outstanding balance
            val hasRemainingBalance = (booking.totalAmount ?: 0.0) > (booking.paidAmount ?: 0.0)
            val shouldShowPayRemaining = booking.paymentType == "dp" &&
                (booking.paymentStatus == "dp_paid" || booking.paymentStatus == "partial") &&
                hasRemainingBalance &&
                booking.id != null &&
                onPayRemaining != null

            if (shouldShowPayRemaining) {
                Divider(color = SurfaceMuted)
                Button(
                    onClick = { booking.id?.let { id -> onPayRemaining?.invoke(id) } },
                    modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = InfoStrong),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Icon(Icons.Default.Payment, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Bayar Sisa (Lunas)", fontWeight = FontWeight.Medium, fontSize = 12.sp)
                }
            }
        }
    }
}

@Composable
private fun BookingsEmptyState(textPrimary: Color, textSecondary: Color) {
    Column(
        Modifier.fillMaxWidth().padding(top = 64.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            Modifier.size(80.dp).clip(CircleShape).background(BkPrimary.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) { Icon(Icons.Filled.CalendarMonth, null, tint = BkPrimary, modifier = Modifier.size(40.dp)) }
        Text("Belum ada booking", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = textPrimary)
        Text("Jadwal konsultasi Anda akan tampil di sini", fontSize = 14.sp, color = textSecondary, textAlign = TextAlign.Center)
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  BOOKING DETAIL SCREEN
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun BookingDetailScreen(
    bookingId: Int,
    onNavigateBack: () -> Unit,
    onNavigateToPayment: ((Int, Boolean, Double, Boolean) -> Unit)? = null, // NEW: For DP/Full payment
    onNavigateToMedicalRecord: ((Int) -> Unit)? = null,
    onNavigateToMedicalExtraPayment: ((Int, Int, Double) -> Unit)? = null,
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    val bg      = BkBg
    val surface = BkSurface
    val textPrimary   = OnBackground
    val textSecondary = TextSecondary
    val dividerColor  = Outline

    LaunchedEffect(bookingId) { viewModel.loadBookingDetail(bookingId) }
    LaunchedEffect(state.isCancelled) { if (state.isCancelled) onNavigateBack() }
    LaunchedEffect(state.isRescheduled) { if (state.isRescheduled) { viewModel.loadBookingDetail(bookingId, forceRefresh = true) } }

    var showCancelDialog by remember { mutableStateOf(false) }
    var showRescheduleDialog by remember { mutableStateOf(false) }
    var cancelReason by remember { mutableStateOf("") }
    val focusManager = LocalFocusManager.current

    // Jangan tutup dialog sebelum request benar-benar sukses. Kalau API
    // menolak (mis. 422/409), pilihan tanggal/jam pengguna tetap terlihat.
    LaunchedEffect(state.isRescheduled) {
        if (state.isRescheduled) showRescheduleDialog = false
    }

    if (showCancelDialog) {
        AlertDialog(
            onDismissRequest = { showCancelDialog = false },
            title = { Text("Batalkan Booking", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Yakin ingin membatalkan janji temu ini?")
                    OutlinedTextField(
                        value = cancelReason,
                        onValueChange = { cancelReason = it },
                        placeholder = { Text("Alasan (opsional)") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                        keyboardActions = KeyboardActions(onDone = { focusManager.clearFocus() }),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = BkPrimary)
                    )
                }
            },
            confirmButton = {
                TextButton(
                    enabled = !state.isLoading,
                    onClick = { viewModel.cancelBooking(bookingId, cancelReason); showCancelDialog = false },
                    colors = ButtonDefaults.textButtonColors(contentColor = ErrorStrong)
                ) { Text("Batalkan Booking", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { showCancelDialog = false }) { Text("Tetap Booking") }
            },
            containerColor = surface
        )
    }

    // Reschedule dialog
    if (showRescheduleDialog) {
        val rescheduleDoctorId = state.booking?.doctorId
        val initialDate = remember(rescheduleDoctorId) {
            firstDoctorWorkingDate(
                availableDays = state.booking?.doctor?.availableDays,
                from = LocalDate.now()
            ) ?: LocalDate.now()
        }
        var selectedDate by remember { mutableStateOf(initialDate) }
        var selectedTime by remember { mutableStateOf<String?>(null) }

        LaunchedEffect(selectedDate, rescheduleDoctorId) {
            selectedTime = null
            if (rescheduleDoctorId != null) {
                viewModel.loadRescheduleSlots(rescheduleDoctorId, selectedDate)
            }
        }

        // 409 artinya slot baru saja diambil orang lain. Muat ulang
        // ketersediaan tanpa menutup dialog agar pengguna bisa langsung
        // memilih jam pengganti.
        LaunchedEffect(state.rescheduleConflict, state.rescheduleUnavailable) {
            if ((state.rescheduleConflict || state.rescheduleUnavailable) && rescheduleDoctorId != null) {
                viewModel.loadRescheduleSlots(rescheduleDoctorId, selectedDate)
            }
        }

        val selectedTimeAvailable = selectedTime == null || state.rescheduleSlots.any {
            it.time == selectedTime && it.available
        }

        AlertDialog(
            onDismissRequest = { showRescheduleDialog = false },
            title = { Text("Jadwalkan Ulang Booking", fontWeight = FontWeight.Bold) },
            text = {
                Column(
                    modifier = Modifier
                        .heightIn(max = 440.dp)
                        .verticalScroll(rememberScrollState()),
                    verticalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    Text(
                        "Pilih tanggal dan jam baru. Slot yang tampil adalah ketersediaan terbaru dokter.",
                        fontSize = 12.sp,
                        color = textSecondary
                    )

                    BookingDateRail(
                        selectedDate = selectedDate,
                        availableDays = state.booking?.doctor?.availableDays,
                        onDateSelected = { selectedDate = it },
                        dayCount = 14,
                        leaveDates = state.booking?.doctor?.scheduleExceptions.toLeaveDates(),
                        extraWorkingDates = state.booking?.doctor?.scheduleExceptions.toCustomHoursDates()
                    )

                    BookingTimeSlots(
                        slots = state.rescheduleSlots,
                        selectedTime = selectedTime,
                        isLoading = state.isSlotsLoading,
                        error = state.rescheduleSlotsError,
                        isWorkingDay = isDoctorWorkingDay(
                            availableDays = state.booking?.doctor?.availableDays,
                            date = selectedDate
                        ),
                        onRetry = {
                            rescheduleDoctorId?.let {
                                viewModel.loadRescheduleSlots(it, selectedDate)
                            }
                        },
                        onSelect = {
                            selectedTime = it
                            viewModel.clearDetailError()
                        },
                        date = selectedDate,
                        slotMeta = state.rescheduleSlotMeta
                    )

                    state.error?.let { rescheduleError ->
                        Surface(
                            color = ErrorSoft,
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Row(
                                modifier = Modifier.padding(10.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Icon(
                                    Icons.Filled.Warning,
                                    contentDescription = null,
                                    tint = ErrorStrong,
                                    modifier = Modifier.size(18.dp)
                                )
                                Text(rescheduleError, color = ErrorStrong, fontSize = 12.sp)
                            }
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(
                    enabled = !state.isLoading && !state.isSlotsLoading &&
                        selectedTime != null && selectedTimeAvailable,
                    onClick = {
                        viewModel.rescheduleBooking(
                            bookingId,
                            selectedDate.format(DateTimeFormatter.ISO_LOCAL_DATE),
                            selectedTime ?: return@TextButton
                        )
                    }
                ) { Text("Jadwalkan Ulang", fontWeight = FontWeight.Bold, color = BkPrimary) }
            },
            dismissButton = {
                TextButton(onClick = { showRescheduleDialog = false }) { Text("Batal") }
            },
            containerColor = surface
        )
    }

    Box(Modifier.fillMaxSize().background(bg)) {
        if (state.isLoading) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = BkPrimary)
            }
        } else if (state.booking == null) {
            // Error (or missing data) state with retry — never a blank screen.
            Column(
                Modifier.fillMaxSize().padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center
            ) {
                Text(
                    text = state.error ?: "Data booking tidak ditemukan.",
                    fontSize = 14.sp,
                    color = textSecondary,
                    textAlign = TextAlign.Center
                )
                Spacer(Modifier.height(16.dp))
                Button(
                    onClick = { viewModel.loadBookingDetail(bookingId, forceRefresh = true) },
                    colors = ButtonDefaults.buttonColors(containerColor = BkPrimary)
                ) { Text("Coba Lagi") }
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = onNavigateBack) { Text("Kembali", color = textSecondary) }
            }
        } else {
            val booking = state.booking
            Column(Modifier.fillMaxSize().navigationBarsPadding().verticalScroll(rememberScrollState()).padding(bottom = 24.dp)) {                // Hero
                Box(Modifier.fillMaxWidth().height(240.dp)) {
                    val photo = buildPhotoUrl(booking?.doctor?.photo)
                    val context = LocalContext.current
                    var hasError by remember(photo) { mutableStateOf(false) }
                    
                    // Log URL for debugging
                    LaunchedEffect(photo) {
                        if (!photo.isNullOrBlank()) {
                            Log.d(TAG, "Loading booking detail hero photo from URL: $photo")
                        }
                    }
                      if (!photo.isNullOrBlank() && !hasError) {
                        AsyncImage(
                            model = ImageRequest.Builder(context)
                                .data(photo)
                                .memoryCachePolicy(CachePolicy.ENABLED)
                                .diskCachePolicy(CachePolicy.ENABLED)
                                .memoryCacheKey(photo)
                                .diskCacheKey(photo)
                                .size(800)
                                .crossfade(200)
                                .build(),
                            contentDescription = "Foto dokter",
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop,
                            onError = { state ->
                                Log.e(TAG, "Failed to load booking detail hero photo from: $photo, error: ${state.result.throwable.message}")
                                hasError = true
                            },
                            onSuccess = {
                                Log.d(TAG, "Successfully loaded booking detail hero photo from: $photo")
                            }
                        )
                        Box(Modifier.fillMaxSize().background(Brush.verticalGradient(listOf(Color.Transparent, bg.copy(alpha = 0.85f)), startY = 80f)))
                    } else {
                        Box(Modifier.fillMaxSize().background(SuccessSoft)) {
                            Icon(Icons.Filled.Person, null, tint = BkPrimary.copy(0.25f), modifier = Modifier.size(80.dp).align(Alignment.Center))
                        }
                    }
                    // Back
                    Box(
                        Modifier.statusBarsPadding().padding(top = 8.dp, start = 16.dp).size(40.dp).clip(CircleShape).background(Color.Black.copy(0.3f)).clickable { onNavigateBack() },
                        contentAlignment = Alignment.Center
                    ) { Icon(Icons.Filled.ArrowBack, "Kembali", tint = Color.White, modifier = Modifier.size(22.dp)) }

                    // Status badge
                    val statusColor = when (booking?.status?.lowercase()) {
                        "confirmed" -> BkPrimary; "pending" -> Tertiary
                        "cancelled" -> ErrorStrong; "completed" -> InfoStrong
                        else -> Neutral
                    }
                    Surface(
                        modifier = Modifier.align(Alignment.TopEnd).statusBarsPadding().padding(top = 12.dp, end = 16.dp),
                        shape = RoundedCornerShape(20.dp), color = statusColor.copy(alpha = 0.9f)
                    ) {
                        Text(
                            booking?.status?.replaceFirstChar { it.uppercase() } ?: "–",
                            color = Color.White, fontWeight = FontWeight.Bold, fontSize = 12.sp,
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 5.dp)
                        )
                    }
                }

                Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
                    // Doctor info
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(booking?.doctor?.name ?: "–", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        Text(booking?.doctor?.specialization ?: "Veterinarian", fontSize = 15.sp, color = textSecondary)
                    }

                    // Detail cards
                    Surface(shape = RoundedCornerShape(16.dp), color = surface, tonalElevation = 2.dp) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                            DetailRow(Icons.Filled.Pets,         "Pet",  "${booking?.pet?.name} (${booking?.pet?.species})", textPrimary, textSecondary)
                            Divider(color = SurfaceMuted)
                            DetailRow(Icons.Filled.CalendarMonth,"Date", booking?.bookingDate ?: "–", textPrimary, textSecondary)
                            Divider(color = SurfaceMuted)
                            DetailRow(Icons.Filled.Schedule,     "Time", booking?.bookingTime ?: "–", textPrimary, textSecondary)
                            if (!booking?.notes.isNullOrBlank()) {
                                Divider(color = SurfaceMuted)
                                DetailRow(Icons.Filled.Notes, "Notes", booking?.notes ?: "", textPrimary, textSecondary)
                            }
                            // Payment info
                            if (booking?.paymentMethodId != null || booking?.paymentType != null) {
                                Divider(color = SurfaceMuted)
                                DetailRow(
                                    if (booking.paymentType == "dp") Icons.Filled.Savings else Icons.Filled.Payment,
                                    "Payment",
                                    if (booking.paymentType == "dp") "Down Payment (50%)" else "Full Payment",
                                    textPrimary,
                                    textSecondary
                                )
                            }
                            if (booking?.totalAmount != null && booking.totalAmount > 0) {
                                Divider(color = SurfaceMuted)
                                Row(
                                    Modifier.fillMaxWidth().padding(vertical = 4.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Box(Modifier.size(36.dp).clip(RoundedCornerShape(10.dp)).background(BkPrimary.copy(alpha = 0.1f)), contentAlignment = Alignment.Center) {
                                            Icon(Icons.Filled.AttachMoney, null, tint = BkPrimary, modifier = Modifier.size(20.dp))
                                        }
                                        Column {
                                            Text("Total Tagihan", fontSize = 11.sp, color = textSecondary)
                                            Text(
                                                formatRupiah(booking.totalAmount),
                                                fontSize = 14.sp,
                                                fontWeight = FontWeight.SemiBold,
                                                color = textPrimary
                                            )
                                        }
                                    }
                                    if (booking.paidAmount != null && booking.paidAmount > 0) {
                                        Surface(
                                            shape = RoundedCornerShape(8.dp),
                                            color = BkPrimary.copy(alpha = 0.1f)
                                        ) {
                                            Column(
                                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                                horizontalAlignment = Alignment.End
                                            ) {
                                                Text("Terbayar", fontSize = 10.sp, color = textSecondary)
                                                Text(
                                                    formatRupiah(booking.paidAmount),
                                                    fontSize = 14.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = BkPrimary
                                                )
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(22.dp),
                        colors = CardDefaults.cardColors(containerColor = surface),
                        border = BorderStroke(1.dp, dividerColor)
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
                                Column(
                                    modifier = Modifier.weight(1f),
                                    verticalArrangement = Arrangement.spacedBy(4.dp)
                                ) {
                                    Text("Rekam Medis", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                    Text(
                                        when {
                                            state.isMedicalRecordLoading -> "Checking diagnosis, treatment, and billing details..."
                                            state.medicalRecord == null -> "The medical record is not available for this booking yet."
                                            state.medicalRecord?.canViewFullRecord == true -> "Record is ready to review."
                                            else -> "Extra medical cost still needs to be settled."
                                        },
                                        fontSize = 12.sp,
                                        lineHeight = 18.sp,
                                        color = textSecondary
                                    )
                                }

                                val recordStatusColor = when {
                                    state.medicalRecord == null -> TextTertiary
                                    state.medicalRecord?.canViewFullRecord == true -> Success
                                    else -> Tertiary
                                }
                                Surface(
                                    shape = RoundedCornerShape(999.dp),
                                    color = recordStatusColor.copy(alpha = 0.12f),
                                    border = BorderStroke(1.dp, recordStatusColor.copy(alpha = 0.18f))
                                ) {
                                    Text(
                                        when {
                                            state.medicalRecord == null -> "Not Ready"
                                            state.medicalRecord?.canViewFullRecord == true -> "Available"
                                            else -> "Payment Needed"
                                        },
                                        color = recordStatusColor,
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                                    )
                                }
                            }

                            state.medicalRecord?.let { record ->
                                val extraStatus = record.extraPaymentStatus ?: "not_required"
                                val extraAmount = record.extraPaymentAmount ?: 0.0
                                val treatmentCost = record.treatmentCost ?: 0.0
                                val medicineCost = record.medicineCost ?: 0.0
                                val bookingPaymentLabel = if (booking?.paymentStatus == "paid") "Paid" else (booking?.paymentStatus ?: "Pending")
                                val extraPaymentLabel = when (extraStatus) {
                                    "not_required" -> "Not Required"
                                    "paid" -> "Paid"
                                    "partial" -> "Partial"
                                    "pending", "unpaid" -> "Pending"
                                    else -> extraStatus.replaceFirstChar { it.uppercase() }
                                }

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                                ) {
                                    Surface(
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(18.dp),
                                        color = BkPrimary.copy(alpha = 0.08f),
                                        border = BorderStroke(1.dp, BkPrimary.copy(alpha = 0.12f))
                                    ) {
                                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Text("Pembayaran Booking", fontSize = 11.sp, color = textSecondary, fontWeight = FontWeight.Medium)
                                            Text(bookingPaymentLabel, fontSize = 15.sp, color = textPrimary, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                    Surface(
                                        modifier = Modifier.weight(1f),
                                        shape = RoundedCornerShape(18.dp),
                                        color = when (extraStatus) {
                                            "paid", "not_required" -> Color(0xFFECFDF5)
                                            "partial", "pending", "unpaid" -> Color(0xFFFFFBEB)
                                            else -> SurfaceVariant
                                        },
                                        border = BorderStroke(
                                            1.dp,
                                            when (extraStatus) {
                                                "paid", "not_required" -> Color(0xFFA7F3D0)
                                                "partial", "pending", "unpaid" -> WarningSoft
                                                else -> dividerColor
                                            }
                                        )
                                    ) {
                                        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                            Text("Biaya Medis Tambahan", fontSize = 11.sp, color = textSecondary, fontWeight = FontWeight.Medium)
                                            Text(extraPaymentLabel, fontSize = 15.sp, color = textPrimary, fontWeight = FontWeight.Bold)
                                        }
                                    }
                                }

                                if (extraAmount > 0 && record.canViewFullRecord != true) {
                                    Surface(
                                        shape = RoundedCornerShape(18.dp),
                                        color = Color(0xFFFFFBEB),
                                        border = BorderStroke(1.dp, WarningSoft)
                                    ) {
                                        Column(
                                            modifier = Modifier.padding(16.dp),
                                            verticalArrangement = Arrangement.spacedBy(10.dp)
                                        ) {
                                            Row(
                                                modifier = Modifier.fillMaxWidth(),
                                                horizontalArrangement = Arrangement.spacedBy(10.dp),
                                                verticalAlignment = Alignment.CenterVertically
                                            ) {
                                                Box(
                                                    modifier = Modifier
                                                        .size(38.dp)
                                                        .clip(RoundedCornerShape(14.dp))
                                                        .background(Tertiary.copy(alpha = 0.12f)),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(Icons.Filled.Payments, null, tint = Tertiary, modifier = Modifier.size(20.dp))
                                                }
                                                Column {
                                                    Text("Perlu pembayaran tambahan", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = WarningText)
                                                    Text("Rekam medis lengkap akan terbuka setelah biaya tambahan dilunasi.", fontSize = 12.sp, lineHeight = 18.sp, color = WarningText)
                                                }
                                            }

                                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                Text("Biaya tindakan", fontSize = 12.sp, color = textSecondary)
                                                Text(formatRupiah(treatmentCost), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                                            }
                                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                Text("Biaya obat", fontSize = 12.sp, color = textSecondary)
                                                Text(formatRupiah(medicineCost), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
                                            }
                                            Divider(color = WarningSoft)
                                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                                Text("Total tambahan", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = WarningText)
                                                Text(formatRupiah(extraAmount), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = WarningText)
                                            }
                                        }
                                    }

                                    if (record.id != null && onNavigateToMedicalExtraPayment != null) {
                                        Button(
                                            onClick = { onNavigateToMedicalExtraPayment(record.id, bookingId, extraAmount) },
                                            modifier = Modifier.fillMaxWidth().height(52.dp),
                                            shape = RoundedCornerShape(16.dp),
                                            colors = ButtonDefaults.buttonColors(containerColor = Tertiary)
                                        ) {
                                            Icon(Icons.Filled.Payment, null, modifier = Modifier.size(20.dp))
                                            Spacer(Modifier.width(8.dp))
                                            Text("Bayar Biaya Tambahan", fontWeight = FontWeight.Bold)
                                        }
                                    }
                                } else if (record.id != null && onNavigateToMedicalRecord != null) {
                                    Button(
                                        onClick = { onNavigateToMedicalRecord(record.id) },
                                        modifier = Modifier.fillMaxWidth().height(52.dp),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = BkPrimary)
                                    ) {
                                        Icon(Icons.Filled.Article, null, modifier = Modifier.size(20.dp))
                                        Spacer(Modifier.width(8.dp))
                                        Text("Lihat Rekam Medis", fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }

                    // Payment buttons based on payment status
                    if (booking?.status?.lowercase() in listOf("pending", "confirmed")) {
                        // Trust backend payment status as primary condition
                        val paymentStatus = booking?.paymentStatus ?: "pending"
                        val isFullyPaid = paymentStatus == "paid"
                        val hasRemainingBalance = (booking?.totalAmount ?: 0.0) > (booking?.paidAmount ?: 0.0)
                        val isDPPayment = booking?.paymentType == "dp"

                        // Show payment status indicator if fully paid (trust backend status)
                        if (isFullyPaid) {
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = Success.copy(alpha = 0.1f)),
                                border = BorderStroke(1.dp, Success.copy(alpha = 0.3f))
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier.size(40.dp).clip(CircleShape).background(Success),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            Icons.Filled.CheckCircle,
                                            contentDescription = "Lunas",
                                            tint = Color.White,
                                            modifier = Modifier.size(24.dp)
                                        )
                                    }
                                    Column {
                                        Text(
                                            if (isDPPayment) "DP Lunas" else "Lunas",
                                            fontSize = 16.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Success
                                        )
                                        Text(
                                            if (isDPPayment) "Down Payment telah dilunasi" else "Pembayaran penuh telah selesai",
                                            fontSize = 12.sp,
                                            color = textSecondary
                                        )
                                    }
                                }
                            }
                            Spacer(Modifier.height(16.dp))
                        }

                        // Show payment button ONLY if NOT fully paid AND has remaining balance
                        // Trust backend paymentStatus - if it says "paid", hide payment buttons
                        if (!isFullyPaid && hasRemainingBalance && onNavigateToPayment != null) {
                            Button(
                                onClick = {
                                    val remainingAmount = (booking?.totalAmount ?: 0.0) - (booking?.paidAmount ?: 0.0)
                                    val isRemainingPayment = paymentStatus == "dp_paid" || paymentStatus == "partial"
                                    onNavigateToPayment(bookingId, isDPPayment, remainingAmount, isRemainingPayment)
                                },
                                modifier = Modifier.fillMaxWidth().height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = BkPrimary)
                            ) {
                                Icon(Icons.Default.Payment, null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    when {
                                        paymentStatus == "dp_paid" || paymentStatus == "partial" -> "Bayar Sisa (Lunas)"
                                        isDPPayment -> "Bayar DP (50%)"
                                        else -> "Bayar Sekarang"
                                    },
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 15.sp
                                )
                            }
                            Spacer(Modifier.height(12.dp))
                        }
                        
                        // Cancel button (only if pending/confirmed)
                        Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            // Reschedule button
                            OutlinedButton(
                                onClick = {
                                    viewModel.clearDetailError()
                                    showRescheduleDialog = true
                                },
                                modifier = Modifier.weight(1f).height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, BkPrimary.copy(0.5f)),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = BkPrimary)
                            ) {
                                Icon(Icons.Filled.Schedule, null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Jadwalkan Ulang", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                            }

                            // Cancel button
                            OutlinedButton(
                                onClick = { showCancelDialog = true },
                                modifier = Modifier.weight(1f).height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, ErrorStrong.copy(0.5f)),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = ErrorStrong)
                            ) {
                                Icon(Icons.Filled.Cancel, null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Batalkan", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                            }
                        }
                    }
                }
            }
        }

        state.error?.let { detailError ->
            Snackbar(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(16.dp),
                containerColor = ErrorSoft,
                contentColor = ErrorStrong,
                dismissAction = {
                    IconButton(onClick = viewModel::clearDetailError) {
                        Icon(Icons.Filled.Close, contentDescription = "Tutup pesan", tint = ErrorStrong)
                    }
                }
            ) { Text(detailError) }
        }
    }
}

@Composable
private fun DetailRow(icon: androidx.compose.ui.graphics.vector.ImageVector, label: String, value: String, textPrimary: Color, textSecondary: Color) {
    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(36.dp).clip(RoundedCornerShape(10.dp)).background(BkPrimary.copy(alpha = 0.1f)), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = BkPrimary, modifier = Modifier.size(20.dp))
        }
        Column {
            Text(label, fontSize = 11.sp, color = textSecondary)
            Text(value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = textPrimary)
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  CREATE BOOKING SCREEN  ← matches the HTML design exactly
// ══════════════════════════════════════════════════════════════════════════════
@Composable
fun CreateBookingScreen(
    doctorId: Int,
    petId: Int,
    onNavigateBack: () -> Unit,
    onBookingCreated: (Int, Boolean, Double, String) -> Unit,  // (bookingId, isDp, amount, midtransMethodsCsv)
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.createState.collectAsState()
    val supportState by viewModel.supportState.collectAsState()
    var showHelpSheet by remember { mutableStateOf(false) }
    val bg      = BkBg
    val surface = BkSurface
    val textPrimary   = OnBackground
    val textSecondary = TextSecondary
    val dividerColor  = Outline

    LaunchedEffect(Unit) { viewModel.loadCreateBookingData(doctorId, petId) }
    LaunchedEffect(state.isCreated) {
        val createdId = state.createdBookingId
        if (state.isCreated && createdId != null) {
            val amount = if (state.selectedPaymentType == "dp") state.dpAmount else state.totalAmount
            val isDp = state.selectedPaymentType == "dp"
            // Teruskan pilihan metode ke Midtrans agar Snap langsung membuka
            // metode tersebut. "" = daftar lengkap (perilaku lama).
            val methodsCsv = state.selectedPaymentMethod?.toMidtransCodes()?.joinToString(",").orEmpty()
            Log.d("CreateBooking", "Booking created: id=$createdId, isDp=$isDp, amount=$amount, methods=$methodsCsv")
            viewModel.clearCreateState()
            onBookingCreated(createdId, isDp, amount, methodsCsv)
        }
    }

    // Progress step
    val step = when {
        state.selectedPetId == null                          -> 1
        state.selectedServiceId == null                      -> 2
        state.selectedTime  == null                          -> 3
        state.selectedPaymentMethod == null                  -> 4
        else                                                 -> 5
    }

    Box(Modifier.fillMaxSize().background(bg)) {
        Column(Modifier.fillMaxSize()) {

            // ── Sticky header + progress ──────────────────────────────
            Surface(
                color = bg.copy(alpha = 0.95f),
                shadowElevation = 0.dp,
                tonalElevation = 0.dp
            ) {
                Column(Modifier.fillMaxWidth()) {
                    Row(
                        Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 8.dp).padding(top = 8.dp, bottom = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(Modifier.size(40.dp).clip(CircleShape).clickable { onNavigateBack() }, contentAlignment = Alignment.Center) {
                            Icon(Icons.Filled.ArrowBack, "Kembali", tint = textPrimary, modifier = Modifier.size(24.dp))
                        }
                        Text("Buat Booking", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary, modifier = Modifier.weight(1f), textAlign = TextAlign.Center)
                        TextButton(onClick = {
                            showHelpSheet = true
                            viewModel.loadSupportContact()
                        }) {
                            Text("Bantuan", color = BkPrimary, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }
                    // Progress dots
                    Row(
                        Modifier.fillMaxWidth().padding(vertical = 12.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        repeat(5) { i ->
                            val active = (i + 1) <= step
                            val current = (i + 1) == step
                            Box(
                                modifier = Modifier
                                    .padding(horizontal = 6.dp)
                                    .size(if (current) 10.dp else 8.dp)
                                    .clip(CircleShape)
                                    .background(if (active) BkPrimary else OutlineStrong)
                            )
                        }
                    }
                    Divider(color = dividerColor, thickness = 1.dp)
                }
            }

            // ── Scrollable content ────────────────────────────────────
            Column(
                Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp)
                    .padding(top = 16.dp, bottom = 140.dp),
                verticalArrangement = Arrangement.spacedBy(28.dp)
            ) {                // ── Doctor Info Card ───────────────────────────────────
                state.doctor?.let { doc ->
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = surface,
                        border = androidx.compose.foundation.BorderStroke(1.dp, dividerColor),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Row(
                            Modifier.padding(14.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(56.dp)
                                    .clip(CircleShape)
                                    .background(SuccessSoft),
                                contentAlignment = Alignment.Center
                            ) {
                                val createBookingPhoto = buildPhotoUrl(doc.photo)
                                if (!createBookingPhoto.isNullOrBlank()) {
                                    val context = LocalContext.current
                                    var hasError by remember { mutableStateOf(false) }

                                    LaunchedEffect(createBookingPhoto) {
                                        Log.d(TAG, "Loading create booking doctor photo from URL: $createBookingPhoto")
                                    }
                                    if (!hasError) {
                                        AsyncImage(
                                            model = ImageRequest.Builder(context)
                                                .data(createBookingPhoto)
                                                .memoryCachePolicy(CachePolicy.ENABLED)
                                                .diskCachePolicy(CachePolicy.ENABLED)
                                                .memoryCacheKey(createBookingPhoto)
                                                .diskCacheKey(createBookingPhoto)
                                                .crossfade(200)
                                                .build(),
                                            contentDescription = "Doctor",
                                            contentScale = ContentScale.Crop,
                                            modifier = Modifier.fillMaxSize().clip(CircleShape),
                                            onError = { state ->
                                                Log.e(TAG, "Failed to load create booking doctor photo from: $createBookingPhoto, error: ${state.result.throwable.message}")
                                                hasError = true
                                            },
                                            onSuccess = {
                                                Log.d(TAG, "Successfully loaded create booking doctor photo from: $createBookingPhoto")
                                            }
                                        )
                                    } else {
                                        Icon(Icons.Filled.Person, null, tint = BkPrimary, modifier = Modifier.size(28.dp))
                                    }
                                } else {
                                    Icon(Icons.Filled.Person, null, tint = BkPrimary, modifier = Modifier.size(28.dp))
                                }
                            }
                            Column(modifier = Modifier.weight(1f)) {
                                Text(doc.name ?: "–", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                Text(doc.specialization ?: "Veterinarian", fontSize = 13.sp, color = BkPrimary, fontWeight = FontWeight.Medium)
                                // Ringkas saja: rating + jam praktik. Profil lengkap
                                // (ulasan, tentang) ada di alur Cari Dokter.
                                val createAvg = doc.averageRating
                                val createReviewTotal = doc.reviewsCount ?: 0
                                if (createAvg != null && createAvg > 0) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Filled.Star, null, tint = Star, modifier = Modifier.size(12.dp))
                                        Spacer(Modifier.width(4.dp))
                                        Text(
                                            "%.1f".format(createAvg) +
                                                if (createReviewTotal > 0) " · " + if (createReviewTotal == 1) "1 ulasan" else "$createReviewTotal ulasan" else "",
                                            fontSize = 11.sp, color = textSecondary, fontWeight = FontWeight.Medium
                                        )
                                    }
                                }
                                doc.availableTime?.let {
                                    Text(it, fontSize = 11.sp, color = textSecondary)
                                }
                            }
                        }
                    }
                }

                // ── Section 1: Select Pet ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Untuk hewan siapa janji temu ini?", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(10.dp), contentPadding = PaddingValues(horizontal = 2.dp)) {
                        items(state.pets) { pet ->
                            val selected = pet.id == state.selectedPetId
                            val chipBg by animateColorAsState(if (selected) BkPrimary else surface, tween(200))
                            val chipText by animateColorAsState(if (selected) BkPrimaryFg else textPrimary, tween(200))
                            Surface(
                                shape = CircleShape,
                                color = chipBg,
                                border = if (selected) null else androidx.compose.foundation.BorderStroke(1.dp, dividerColor),
                                modifier = Modifier
                                    .clip(CircleShape)
                                    .clickable { pet.id?.let { viewModel.selectPet(it) } }
                                    .then(if (selected) Modifier.shadow(6.dp, CircleShape, spotColor = BkPrimary.copy(0.4f)) else Modifier)
                            ) {
                                Row(
                                    Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(Icons.Filled.Pets, null, tint = chipText, modifier = Modifier.size(18.dp))
                                    Text("${pet.name} (${pet.species})", fontSize = 13.sp, fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium, color = chipText)
                                }
                            }
                        }
                        // Back chip — exits the create flow (adding a pet
                        // mid-booking is handled from the doctor detail CTA).
                        item {
                            Surface(
                                shape = CircleShape,
                                color = Color.Transparent,
                                border = androidx.compose.foundation.BorderStroke(1.5.dp, OutlineStrong),
                                modifier = Modifier.clip(CircleShape).clickable { onNavigateBack() }
                            ) {
                                Row(
                                    Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(Icons.Filled.ArrowBack, null, tint = textSecondary, modifier = Modifier.size(18.dp))
                                    Text("Batal", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textSecondary)
                                }
                            }
                        }
                    }
                }

                // ── Section 2: Category Grid ───────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Pilih Layanan", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    if (state.services.isEmpty()) {
                        // Bedakan loading / gagal muat / memang kosong — dulu
                        // SEMUA kondisi tampil "Loading services..." sehingga
                        // kegagalan tidak pernah terlihat.
                        Column(
                            Modifier.fillMaxWidth().padding(vertical = 16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                text = if (state.isLoading) "Memuat layanan..."
                                else state.servicesError
                                    ?: "Belum ada layanan yang tersedia di klinik ini.",
                                fontSize = 14.sp,
                                color = textSecondary,
                                textAlign = TextAlign.Center
                            )
                            if (!state.isLoading && state.servicesError != null) {
                                TextButton(onClick = { viewModel.loadCreateBookingData(doctorId, petId) }) {
                                    Text("Coba Lagi", color = BkPrimary, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                    } else {
                        val serviceRows = state.services.chunked(2)
                        serviceRows.forEachIndexed { rowIdx, rowServices ->
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                rowServices.forEachIndexed { colIdx, service ->
                                    val selected = state.selectedServiceId == service.id
                                    val paletteIndex = (rowIdx * 2 + colIdx) % 4
                                    val (bgLight, _) = when (paletteIndex) {
                                        0 -> Pair(Color(0xFFDCFCE7), Color(0x2216A34A))
                                        1 -> Pair(InfoSoft, Color(0x222563EB))
                                        2 -> Pair(WarningSoft, Color(0x22EA580C))
                                        else -> Pair(Color(0xFFFEE2E2), Color(0x22DC2626))
                                    }
                                    Card(
                                        modifier = Modifier
                                            .weight(1f)
                                            .clip(RoundedCornerShape(16.dp))
                                            .clickable { viewModel.selectService(service) }
                                            .then(if (selected) Modifier.border(2.dp, BkPrimary, RoundedCornerShape(16.dp)) else Modifier),
                                        shape = RoundedCornerShape(16.dp),
                                        colors = CardDefaults.cardColors(
                                            containerColor = if (selected) BkPrimary.copy(alpha = 0.1f) else BkSurface
                                        ),
                                        elevation = CardDefaults.cardElevation(if (selected) 0.dp else 2.dp)
                                    ) {
                                        Box {
                                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                Box(
                                                    Modifier.size(44.dp).clip(RoundedCornerShape(10.dp))
                                                        .background(bgLight),
                                                    contentAlignment = Alignment.Center
                                                ) {
                                                    Icon(Icons.Filled.MedicalServices, null, tint = BkPrimary, modifier = Modifier.size(24.dp))
                                                }
                                                Text(service.name ?: "Service", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                                                Text(
                                                    service.description ?: (service.category ?: "Pet care service"),
                                                    fontSize = 11.sp,
                                                    color = textSecondary,
                                                    maxLines = 2,
                                                    overflow = TextOverflow.Ellipsis
                                                )
                                                Text(
                                                    formatRupiah(service.price ?: 0.0),
                                                    fontSize = 12.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = if (selected) BkPrimary else textPrimary
                                                )
                                            }
                                            if (selected) {
                                                Icon(
                                                    Icons.Filled.CheckCircle,
                                                    null,
                                                    tint = BkPrimary,
                                                    modifier = Modifier.align(Alignment.TopEnd).padding(10.dp).size(20.dp)
                                                )
                                            }
                                        }
                                    }
                                }
                                repeat(2 - rowServices.size) { Box(modifier = Modifier.weight(1f)) }
                            }
                        }
                    }
                }

                // ── Section 3: Date picker ─────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Text("Pilih Tanggal", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        Text(
                            state.selectedDate.format(DateTimeFormatter.ofPattern("MMMM yyyy", Locale("id", "ID"))),
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold,
                            color = BkPrimary
                        )
                    }
                    BookingDateRail(
                        selectedDate = state.selectedDate,
                        availableDays = state.doctor?.availableDays,
                        onDateSelected = { viewModel.selectDate(it, doctorId) },
                        leaveDates = state.doctor?.scheduleExceptions.toLeaveDates(),
                        extraWorkingDates = state.doctor?.scheduleExceptions.toCustomHoursDates()
                    )
                }

                // ── Section 4: Time slots ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    Text("Waktu Tersedia", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)

                    BookingTimeSlots(
                        slots = state.slots,
                        selectedTime = state.selectedTime,
                        isLoading = state.isSlotsLoading,
                        error = state.slotsError,
                        isWorkingDay = isDoctorWorkingDay(
                            availableDays = state.doctor?.availableDays,
                            date = state.selectedDate
                        ),
                        onRetry = { viewModel.retrySlots(doctorId) },
                        onSelect = { viewModel.selectTime(it) },
                        date = state.selectedDate,
                        slotMeta = state.slotMeta
                    )
                }

                // ── Section 5: Payment Method ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Metode Pembayaran", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)

                    if (state.paymentMethods.isEmpty()) {
                        // Sama seperti layanan: gagal muat ≠ memuat selamanya.
                        Column(
                            Modifier.fillMaxWidth().padding(vertical = 20.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                text = if (state.isLoading) "Memuat metode pembayaran..."
                                else state.paymentMethodsError
                                    ?: "Metode pembayaran belum tersedia. Hubungi klinik Anda.",
                                fontSize = 14.sp,
                                color = textSecondary,
                                textAlign = TextAlign.Center
                            )
                            if (!state.isLoading && state.paymentMethodsError != null) {
                                TextButton(onClick = { viewModel.loadCreateBookingData(doctorId, petId) }) {
                                    Text("Coba Lagi", color = BkPrimary, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                    } else {
                        // Group payment methods by type
                        val qrisMethods = state.paymentMethods.filter { it.type == "qris" }
                        val bankMethods = state.paymentMethods.filter { it.type == "bank" }
                        val ewalletMethods = state.paymentMethods.filter { it.type == "ewallet" }

                        // QRIS
                        if (qrisMethods.isNotEmpty()) {
                            PaymentMethodGroup("QRIS", qrisMethods, state.selectedPaymentMethod, surface, textPrimary, textSecondary, BkPrimary) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }

                        // Bank
                        if (bankMethods.isNotEmpty()) {
                            PaymentMethodGroup("Bank Transfer", bankMethods, state.selectedPaymentMethod, surface, textPrimary, textSecondary, InfoStrong) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }

                        // E-Wallet
                        if (ewalletMethods.isNotEmpty()) {
                            PaymentMethodGroup("E-Wallet", ewalletMethods, state.selectedPaymentMethod, surface, textPrimary, textSecondary, AccentViolet) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }
                    }
                    // Konfirmasi: Midtrans langsung membuka metode terpilih.
                    state.selectedPaymentMethod?.let { chosen ->
                        Card(
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(containerColor = BkPrimary.copy(alpha = 0.08f))
                        ) {
                            Row(
                                Modifier.padding(12.dp),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Filled.CheckCircle, null, tint = BkPrimary, modifier = Modifier.size(18.dp))
                                Text(
                                    "Saat bayar, Midtrans langsung membuka ${chosen.name ?: "metode ini"}.",
                                    fontSize = 12.sp, color = textSecondary
                                )
                            }
                        }
                    }
                }

                // ── Section 6: Payment Type (DP/Full) ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Tipe Pembayaran", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        // Full Payment Option
                        val isFullSelected = state.selectedPaymentType == "full"
                        Card(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(16.dp))
                                .clickable { viewModel.selectPaymentType("full") }
                                .then(if (isFullSelected) Modifier.border(2.dp, BkPrimary, RoundedCornerShape(16.dp)) else Modifier),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isFullSelected) BkPrimary.copy(alpha = 0.1f) else surface
                            ),
                            elevation = CardDefaults.cardElevation(if (isFullSelected) 0.dp else 2.dp)
                        ) {
                            Column(
                                Modifier.padding(16.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Icon(
                                    Icons.Filled.Payment,
                                    contentDescription = null,
                                    tint = if (isFullSelected) BkPrimary else textSecondary,
                                    modifier = Modifier.size(32.dp)
                                )
                                Text(
                                    "Full Payment",
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Text(
                                    formatRupiah(state.totalAmount),
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = if (isFullSelected) BkPrimary else textPrimary
                                )
                                if (isFullSelected) {
                                    Icon(
                                        Icons.Filled.CheckCircle,
                                        contentDescription = null,
                                        tint = BkPrimary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                            }
                        }
                        
                        // DP Option
                        val isDpSelected = state.selectedPaymentType == "dp"
                        Card(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(16.dp))
                                .clickable { viewModel.selectPaymentType("dp") }
                                .then(if (isDpSelected) Modifier.border(2.dp, Tertiary, RoundedCornerShape(16.dp)) else Modifier),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isDpSelected) Tertiary.copy(alpha = 0.1f) else surface
                            ),
                            elevation = CardDefaults.cardElevation(if (isDpSelected) 0.dp else 2.dp)
                        ) {
                            Column(
                                Modifier.padding(16.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Icon(
                                    Icons.Filled.Savings,
                                    contentDescription = null,
                                    tint = if (isDpSelected) Tertiary else textSecondary,
                                    modifier = Modifier.size(32.dp)
                                )
                                Text(
                                    "Down Payment",
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Text(
                                    formatRupiah(state.dpAmount),
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = if (isDpSelected) Tertiary else textPrimary
                                )
                                Text(
                                    "(50% DP)",
                                    fontSize = 11.sp,
                                    color = textSecondary
                                )
                                if (isDpSelected) {
                                    Icon(
                                        Icons.Filled.CheckCircle,
                                        contentDescription = null,
                                        tint = Tertiary,
                                        modifier = Modifier.size(20.dp)
                                    )
                                }
                            }
                        }
                    }
                    
                    // Info text
                    Card(
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = BkPrimary.copy(alpha = 0.08f))
                    ) {
                        Row(
                            Modifier.padding(12.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Filled.Info,
                                contentDescription = null,
                                tint = BkPrimary,
                                modifier = Modifier.size(18.dp)
                            )
                            Text(
                                if (state.selectedPaymentType == "dp")
                                    "Uang muka 50% dibayar sekarang, sisanya dibayar kemudian"
                                else
                                    "Pelunasan dibayar sekarang untuk mengunci jadwal",
                                fontSize = 12.sp,
                                color = textSecondary
                            )
                        }
                    }
                }

                // Error
                state.error?.let { createError -> ErrorBanner(createError) }
            }
        }

        // ── Sticky bottom bar ─────────────────────────────────────────
        Surface(
            modifier = Modifier.align(Alignment.BottomCenter).fillMaxWidth().navigationBarsPadding(),
            color = bg.copy(alpha = 0.97f),
            shadowElevation = 0.dp
        ) {
            Column(Modifier.fillMaxWidth()) {
                Divider(color = dividerColor, thickness = 1.dp)
                Column(Modifier.padding(horizontal = 16.dp, vertical = 16.dp).padding(bottom = 8.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("Estimasi Total", fontSize = 13.sp, color = textSecondary)
                            if (state.selectedPaymentType == "dp") {
                                Text(
                                    "DP 50%",
                                    fontSize = 11.sp,
                                    color = Tertiary,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                        Text(
                            // Nominal 0 = belum tervalidasi; jangan pernah
                            // tampil sebagai angka rupiah.
                            if (state.totalAmount <= 0.0) "—"
                            else if (state.selectedPaymentType == "dp")
                                formatRupiah(state.dpAmount)
                            else
                                formatRupiah(state.totalAmount),
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (state.selectedPaymentType == "dp") Tertiary else textPrimary
                        )
                    }
                    Button(
                        onClick = { viewModel.createBooking(doctorId) },
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                        shape = RoundedCornerShape(16.dp),
                        enabled = !state.isLoading && state.selectedPetId != null && state.selectedServiceId != null &&
                            state.selectedTime != null && state.selectedPaymentMethod != null &&
                            // Bayar hanya dengan nominal tervalidasi dari backend.
                            state.totalAmount > 0.0,
                        colors = ButtonDefaults.buttonColors(containerColor = BkPrimary, contentColor = BkPrimaryFg),
                        elevation = ButtonDefaults.buttonElevation(6.dp)
                    ) {
                        if (state.isLoading) {
                            CircularProgressIndicator(modifier = Modifier.size(22.dp), color = BkPrimaryFg, strokeWidth = 2.5.dp)
                        } else {
                            Text(
                                if (state.selectedPaymentType == "dp") "Book with DP" else "Confirm Booking",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Spacer(Modifier.width(8.dp))
                            Icon(Icons.Filled.ArrowForward, null, modifier = Modifier.size(20.dp))
                        }
                    }
                }
            }
        }

        if (showHelpSheet) {
            ClinicHelpSheet(
                contact = supportState.contact,
                message = supportState.message,
                isLoading = supportState.isLoading,
                error = supportState.error,
                onRetry = { viewModel.loadSupportContact(forceRefresh = true) },
                onDismiss = { showHelpSheet = false }
            )
        }
    }
}

// ── Error banner ───────────────────────────────────────────────────────────────
@Composable
fun ErrorBanner(message: String, onRetry: (() -> Unit)? = null) {
    Card(
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = ErrorStrong.copy(alpha = 0.1f))
    ) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(Icons.Filled.Warning, null, tint = ErrorStrong, modifier = Modifier.size(20.dp))
            Text(message, color = ErrorStrong, fontSize = 13.sp, modifier = Modifier.weight(1f))
            if (onRetry != null) {
                // Retry memakai mekanisme load yang sudah ada di ViewModel.
                TextButton(onClick = onRetry) {
                    Text("Coba Lagi", color = ErrorStrong, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

// ── Payment method group ────────────────────────────────────────────────────────────
@Composable
private fun PaymentMethodGroup(
    title: String,
    methods: List<PaymentMethod>,
    selectedMethod: PaymentMethod?,
    surface: Color,
    textPrimary: Color,
    textSecondary: Color,
    accentColor: Color,
    onSelect: (PaymentMethod) -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            title, fontSize = 12.sp,
            fontWeight = FontWeight.Bold, letterSpacing = 0.5.sp,
            color = accentColor
        )
        methods.forEach { method ->
            val selected = method.id == selectedMethod?.id
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .clickable { onSelect(method) }
                    .then(if (selected) Modifier.border(2.dp, accentColor, RoundedCornerShape(12.dp)) else Modifier),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(
                    containerColor = if (selected) accentColor.copy(alpha = 0.1f) else surface
                ),
                elevation = CardDefaults.cardElevation(if (selected) 0.dp else 1.dp)
            ) {
                Row(
                    Modifier
                        .fillMaxWidth()
                        .padding(12.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Icon based on type
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(accentColor.copy(alpha = 0.15f)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = when (method.type) {
                                    "qris" -> Icons.Filled.QrCode
                                    "bank" -> Icons.Filled.AccountBalance
                                    "ewallet" -> Icons.Filled.AccountBalanceWallet
                                    else -> Icons.Filled.Payment
                                },
                                contentDescription = null,
                                tint = accentColor,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                        Column {
                            Text(
                                method.name ?: "",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = textPrimary
                            )
                            method.description?.let {
                                Text(
                                    it,
                                    fontSize = 11.sp,
                                    color = textSecondary
                                )
                            }
                        }
                    }
                    if (selected) {
                        Icon(
                            Icons.Filled.CheckCircle,
                            contentDescription = null,
                            tint = accentColor,
                            modifier = Modifier.size(24.dp)
                        )
                    }
                }
            }
        }
    }
}
