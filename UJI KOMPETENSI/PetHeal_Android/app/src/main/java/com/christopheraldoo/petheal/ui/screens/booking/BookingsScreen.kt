package com.christopheraldoo.petheal.ui.screens.booking

import android.util.Log
import android.widget.Toast
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
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalView
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.PaymentMethod
import com.christopheraldoo.petheal.ui.components.EmptyBookingsState
import com.christopheraldoo.petheal.ui.components.SkeletonBookingCard
import com.christopheraldoo.petheal.ui.components.SkeletonBookingList
import com.christopheraldoo.petheal.util.HapticFeedback
import com.christopheraldoo.petheal.util.buildPhotoUrl
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.TextStyle
import java.util.Locale
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.BorderStroke
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.compose.material3.ExperimentalMaterial3Api

private const val TAG = "BookingPhoto"

// ── Brand tokens ───────────────────────────────────────────────────────────────
private val BkPrimary       = Color(0xFF2BEE6C)
private val BkPrimaryFg     = Color(0xFF052E14)
private val BkBgDark        = Color(0xFFF6F8F6)
private val BkBgLight       = Color(0xFFF6F8F6)
private val BkSurfaceDark   = Color.White
private val BkSurfaceLight  = Color(0xFFFFFFFF)

// ── Helper: load doctor photo with ngrok header ─────────────────────────────
@Composable
private fun BkDocPhoto(url: String?, size: androidx.compose.ui.unit.Dp) {
    val context = LocalContext.current
    var hasError by remember { mutableStateOf(false) }
    
    // Build the full URL using PhotoUtils
    val fullUrl = remember(url) { buildPhotoUrl(url) }
    
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
            contentDescription = "Doctor Photo",
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize().clip(CircleShape),
            onError = { state ->
                Log.e(TAG, "Failed to load doctor photo from: $fullUrl, error: ${state.result.throwable?.message}")
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
    onNavigateToBookingDetail: (Int) -> Unit,
    onNavigateToPayment: (Int, Boolean, Double, Boolean) -> Unit, // NEW: (bookingId, isDp, totalAmount, isRemaining)
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.listState.collectAsState()
    val isDark = false
    val bg      = if (isDark) BkBgDark      else BkBgLight
    val surface = if (isDark) BkSurfaceDark else BkSurfaceLight
    val textPrimary   = if (isDark) Color.White         else Color(0xFF0F172A)
    val context = LocalContext.current
    val textSecondary = if (isDark) Color(0xFF94A3B8)   else Color(0xFF64748B)
    val border        = if (isDark) Color(0x1AFFFFFF)   else Color(0xFFF1F5F9)

    LaunchedEffect(Unit) { viewModel.loadBookings() }

    // State for filter/sort bottom sheet
    var showFilterSheet by remember { mutableStateOf(false) }
    
    // Pull-to-refresh state
    var isRefreshing by remember { mutableStateOf(false) }
    val view = LocalView.current

    // Pull-to-refresh handler
    val onRefresh = {
        isRefreshing = true
        viewModel.loadBookings()
        HapticFeedback.performSelection(view)
        // Simulate minimum refresh time for better UX
        CoroutineScope(Dispatchers.Main).launch {
            delay(800)
            isRefreshing = false
        }
    }

    Box(Modifier.fillMaxSize().background(bg)) {
        Column(Modifier.fillMaxSize()) {
            // Header
            Surface(color = surface.copy(alpha = 0.93f), shadowElevation = 0.dp) {
                Column(Modifier.fillMaxWidth()) {
                    Row(
                        Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 8.dp)
                            .padding(top = 44.dp, bottom = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            Modifier.size(40.dp).clip(CircleShape).clickable { onNavigateBack() },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.ArrowBack, null, tint = textPrimary, modifier = Modifier.size(24.dp))
                        }
                        Text("My Bookings", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        // Filter button
                        Box(
                            Modifier.size(40.dp).clip(CircleShape).clickable { showFilterSheet = true },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Filled.FilterList, null, tint = textPrimary, modifier = Modifier.size(24.dp))
                        }
                    }
                    Divider(color = border, thickness = 1.dp)
                    
                    // Active filters indicator
                    if (state.sortOrder != BookingSortOrder.NEWEST_FIRST || state.dateFilter != BookingDateFilter.ALL) {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                            horizontalArrangement = Arrangement.spacedBy(8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text("Filters:", fontSize = 12.sp, color = textSecondary, fontWeight = FontWeight.Medium)
                            if (state.dateFilter != BookingDateFilter.ALL) {
                                AssistChip(
                                    onClick = { viewModel.setDateFilter(BookingDateFilter.ALL) },
                                    label = { Text(getDateFilterLabel(state.dateFilter), fontSize = 11.sp) },
                                    leadingIcon = {
                                        Icon(
                                            Icons.Filled.Close,
                                            contentDescription = "Remove",
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
                                            contentDescription = "Remove",
                                            modifier = Modifier.size(14.dp)
                                        )
                                    },
                                    colors = AssistChipDefaults.assistChipColors(
                                        containerColor = BkPrimary.copy(alpha = 0.15f)
                                    )
                                )
                            }
                            TextButton(onClick = { viewModel.resetFilters() }) {
                                Text("Reset", fontSize = 11.sp, color = BkPrimary)
                            }
                        }
                    }
                }
            }

            if (state.isLoading && state.bookings.isEmpty()) {
                // Show skeleton loading on initial load
                SkeletonBookingList(count = 3)
            } else {
                LazyColumn(
                    Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (state.error != null) {
                        item { ErrorBanner(state.error!!) }
                    }
                    if (state.bookings.isEmpty()) {
                        item { 
                            EmptyBookingsState(
                                onBookNow = { /* Navigate to doctor booking */ }
                            ) 
                        }
                    } else {
                        items(state.bookings) { booking ->
                            BookingCard(
                                booking = booking,
                                isDark = isDark,
                                surface = surface,
                                textPrimary = textPrimary,
                                textSecondary = textSecondary,
                                border = border,
                                onClick = { 
                                    HapticFeedback.performClick(view)
                                    booking.id?.let { onNavigateToBookingDetail(it) } 
                                },
                                onPayRemaining = { bookingId ->
                                    // Calculate remaining amount from booking data
                                    val remainingAmount = (booking.totalAmount ?: 0.0) - (booking.paidAmount ?: 0.0)
                                    onNavigateToPayment(bookingId, booking.paymentType == "dp", remainingAmount, true)
                                },
                                onBookingUpdated = { viewModel.refreshBookings() }
                            )
                        }
                    }
                }
            }
        }

        // Filter/Sort Bottom Sheet
        if (showFilterSheet) {
            BookingFilterBottomSheet(
                currentSortOrder = state.sortOrder,
                currentDateFilter = state.dateFilter,
                onSortOrderChanged = { viewModel.setSortOrder(it) },
                onDateFilterChanged = { viewModel.setDateFilter(it) },
                onResetFilters = { viewModel.resetFilters() },
                onDismiss = { showFilterSheet = false }
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
        BookingDateFilter.YESTERDAY -> "Kemarin"
        BookingDateFilter.LAST_WEEK -> "1 Minggu"
        BookingDateFilter.LAST_MONTH -> "1 Bulan"
        BookingDateFilter.LAST_3_MONTHS -> "3 Bulan"
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
    val isDark = false
    val bgColor = if (isDark) Color(0xFF1E293B) else Color.White
    val textPrimary = if (isDark) Color.White else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)

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
                            selectedContainerColor = Color(0xFF2BEE6C),
                            selectedLabelColor = Color(0xFF052E14),
                            selectedLeadingIconColor = Color(0xFF052E14)
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
                            selectedContainerColor = Color(0xFF2BEE6C),
                            selectedLabelColor = Color(0xFF052E14),
                            selectedLeadingIconColor = Color(0xFF052E14)
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
                        BookingDateFilter.YESTERDAY to "Kemarin",
                        BookingDateFilter.LAST_WEEK to "1 Minggu",
                        BookingDateFilter.LAST_MONTH to "1 Bulan",
                        BookingDateFilter.LAST_3_MONTHS to "3 Bulan"
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
                                selectedContainerColor = Color(0xFF2BEE6C),
                                selectedLabelColor = Color(0xFF052E14),
                                selectedLeadingIconColor = Color(0xFF052E14)
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
                border = BorderStroke(1.dp, Color(0xFFEF4444)),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFFEF4444))
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
    isDark: Boolean,
    surface: Color,
    textPrimary: Color,
    textSecondary: Color,
    border: Color,
    onClick: () -> Unit,
    onPayRemaining: ((Int) -> Unit)? = null, // Callback for paying remaining amount
    onBookingUpdated: () -> Unit = {} // Callback to refresh bookings after payment
) {
    val statusColor = when (booking.status?.lowercase()) {
        "confirmed"  -> Color(0xFF2BEE6C)
        "pending"    -> Color(0xFFF59E0B)
        "cancelled"  -> Color(0xFFEF4444)
        "completed"  -> Color(0xFF3B82F6)
        else         -> Color(0xFF6B7280)
    }
    Card(
        modifier = Modifier.fillMaxWidth().shadow(4.dp, RoundedCornerShape(16.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = surface),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
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
                    shape = RoundedCornerShape(20.dp),
                    color = statusColor.copy(alpha = 0.12f)
                ) {
                    Text(
                        booking.status?.replaceFirstChar { it.uppercase() } ?: "–",
                        color = statusColor, fontSize = 11.sp, fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                    )
                }
            }
            Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {                    // Doctor photo
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(if (isDark) Color(0xFF1E293B) else Color(0xFFE8F5E9)),
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
                Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
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
                            tint = if (booking.paymentType == "dp") Color(0xFFF59E0B) else BkPrimary,
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
                            "paid" -> "Lunas" to Color(0xFF10B981) // Green
                            "dp_paid" -> "DP Dibayar (50%)" to Color(0xFFF59E0B) // Amber
                            "dp_pending" -> "Menunggu DP" to Color(0xFF3B82F6) // Blue
                            "partial" -> "Sebagian Dibayar" to Color(0xFFF97316) // Orange
                            "pending" -> "Belum Dibayar" to Color(0xFFEF4444) // Red
                            "failed" -> "Gagal" to Color(0xFFDC2626) // Dark Red
                            else -> (booking.paymentStatus?.replaceFirstChar { it.uppercase() } ?: "Unknown") to Color.Gray
                        }
                        Surface(
                            shape = RoundedCornerShape(4.dp),
                            color = paymentStatusColor.copy(alpha = 0.12f)
                        ) {
                            Text(
                                paymentStatusText,
                                color = paymentStatusColor,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
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
                onPayRemaining != null
            
            if (shouldShowPayRemaining) {
                Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
                Button(
                    onClick = { onPayRemaining?.invoke(booking.id ?: 0) },
                    modifier = Modifier.fillMaxWidth().padding(top = 8.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF3B82F6)),
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
        Text("No bookings yet", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = textPrimary)
        Text("Your upcoming appointments will appear here", fontSize = 14.sp, color = textSecondary, textAlign = TextAlign.Center)
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
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    val isDark = false
    val bg      = if (isDark) BkBgDark      else BkBgLight
    val surface = if (isDark) BkSurfaceDark else BkSurfaceLight
    val textPrimary   = if (isDark) Color.White       else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val dividerColor  = if (isDark) Color(0x1AFFFFFF) else Color(0xFFE2E8F0)

    LaunchedEffect(bookingId) { viewModel.loadBookingDetail(bookingId) }
    LaunchedEffect(state.isCancelled) { if (state.isCancelled) onNavigateBack() }
    LaunchedEffect(state.isRescheduled) { if (state.isRescheduled) { viewModel.loadBookingDetail(bookingId) } }

    var showCancelDialog by remember { mutableStateOf(false) }
    var showRescheduleDialog by remember { mutableStateOf(false) }
    var cancelReason by remember { mutableStateOf("") }

    if (showCancelDialog) {
        AlertDialog(
            onDismissRequest = { showCancelDialog = false },
            title = { Text("Cancel Booking", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Are you sure you want to cancel this appointment?")
                    OutlinedTextField(
                        value = cancelReason,
                        onValueChange = { cancelReason = it },
                        placeholder = { Text("Reason (optional)") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = BkPrimary)
                    )
                }
            },
            confirmButton = {
                TextButton(
                    onClick = { viewModel.cancelBooking(bookingId, cancelReason); showCancelDialog = false },
                    colors = ButtonDefaults.textButtonColors(contentColor = Color(0xFFEF4444))
                ) { Text("Cancel Booking", fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { showCancelDialog = false }) { Text("Keep It") }
            },
            containerColor = surface
        )
    }

    // Reschedule dialog
    if (showRescheduleDialog) {
        var selectedDate by remember { mutableStateOf(LocalDate.now().plusDays(1)) }
        var selectedTime by remember { mutableStateOf("10:00") }
        
        AlertDialog(
            onDismissRequest = { showRescheduleDialog = false },
            title = { Text("Reschedule Booking", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Select new date and time for your appointment.")
                    
                    // Simple date selector
                    val dates = (1..7).map { LocalDate.now().plusDays(it.toLong()) }
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(dates) { date ->
                            val isSelected = date == selectedDate
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (isSelected) BkPrimary else surface,
                                border = if (!isSelected) androidx.compose.foundation.BorderStroke(1.dp, dividerColor) else null,
                                modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedDate = date }
                            ) {
                                Column(
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally
                                ) {
                                    Text(
                                        date.dayOfWeek.getDisplayName(TextStyle.SHORT, Locale.ENGLISH),
                                        fontSize = 10.sp,
                                        color = if (isSelected) BkPrimaryFg else textSecondary
                                    )
                                    Text(
                                        date.dayOfMonth.toString(),
                                        fontSize = 14.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = if (isSelected) BkPrimaryFg else textPrimary
                                    )
                                }
                            }
                        }
                    }
                    
                    // Simple time selector
                    val times = listOf("09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00")
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(times) { time ->
                            val isSelected = time == selectedTime
                            Surface(
                                shape = RoundedCornerShape(8.dp),
                                color = if (isSelected) BkPrimary else surface,
                                border = if (!isSelected) androidx.compose.foundation.BorderStroke(1.dp, dividerColor) else null,
                                modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedTime = time }
                            ) {
                                Text(
                                    time,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = if (isSelected) BkPrimaryFg else textPrimary,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp)
                                )
                            }
                        }
                    }
                }
            },
            confirmButton = {
                TextButton(
                    onClick = { 
                        viewModel.rescheduleBooking(
                            bookingId, 
                            selectedDate.format(DateTimeFormatter.ISO_LOCAL_DATE), 
                            selectedTime
                        )
                        showRescheduleDialog = false 
                    }
                ) { Text("Reschedule", fontWeight = FontWeight.Bold, color = BkPrimary) }
            },
            dismissButton = {
                TextButton(onClick = { showRescheduleDialog = false }) { Text("Cancel") }
            },
            containerColor = surface
        )
    }

    Box(Modifier.fillMaxSize().background(bg)) {
        if (state.isLoading) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = BkPrimary)
            }
        } else {
            val booking = state.booking
            Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(bottom = 24.dp)) {                // Hero
                Box(Modifier.fillMaxWidth().height(240.dp)) {
                    val photo = buildPhotoUrl(booking?.doctor?.photo)
                    val context = LocalContext.current
                    var hasError by remember { mutableStateOf(false) }
                    
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
                                .crossfade(200)
                                .build(),
                            contentDescription = "Doctor",
                            modifier = Modifier.fillMaxSize(),
                            contentScale = ContentScale.Crop,
                            onError = { state ->
                                Log.e(TAG, "Failed to load booking detail hero photo from: $photo, error: ${state.result.throwable?.message}")
                                hasError = true
                            },
                            onSuccess = {
                                Log.d(TAG, "Successfully loaded booking detail hero photo from: $photo")
                            }
                        )
                        Box(Modifier.fillMaxSize().background(Brush.verticalGradient(listOf(Color.Transparent, bg.copy(alpha = 0.85f)), startY = 80f)))
                    } else {
                        Box(Modifier.fillMaxSize().background(if (isDark) Color.White else Color(0xFFE8F5E9))) {
                            Icon(Icons.Filled.Person, null, tint = BkPrimary.copy(0.25f), modifier = Modifier.size(80.dp).align(Alignment.Center))
                        }
                    }
                    // Back
                    Box(
                        Modifier.padding(top = 44.dp, start = 16.dp).size(40.dp).clip(CircleShape).background(Color.Black.copy(0.3f)).clickable { onNavigateBack() },
                        contentAlignment = Alignment.Center
                    ) { Icon(Icons.Filled.ArrowBack, null, tint = Color.White, modifier = Modifier.size(22.dp)) }

                    // Status badge
                    val statusColor = when (booking?.status?.lowercase()) {
                        "confirmed" -> BkPrimary; "pending" -> Color(0xFFF59E0B)
                        "cancelled" -> Color(0xFFEF4444); "completed" -> Color(0xFF3B82F6)
                        else -> Color(0xFF6B7280)
                    }
                    Surface(
                        modifier = Modifier.align(Alignment.TopEnd).padding(top = 48.dp, end = 16.dp),
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
                            Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
                            DetailRow(Icons.Filled.CalendarMonth,"Date", booking?.bookingDate ?: "–", textPrimary, textSecondary)
                            Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
                            DetailRow(Icons.Filled.Schedule,     "Time", booking?.bookingTime ?: "–", textPrimary, textSecondary)
                            if (!booking?.notes.isNullOrBlank()) {
                                Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
                                DetailRow(Icons.Filled.Notes, "Notes", booking?.notes ?: "", textPrimary, textSecondary)
                            }
                            // Payment info
                            if (booking?.paymentMethodId != null || booking?.paymentType != null) {
                                Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
                                DetailRow(
                                    if (booking?.paymentType == "dp") Icons.Filled.Savings else Icons.Filled.Payment,
                                    "Payment",
                                    if (booking?.paymentType == "dp") "Down Payment (50%)" else "Full Payment",
                                    textPrimary,
                                    textSecondary
                                )
                            }
                            if (booking?.totalAmount != null && booking.totalAmount > 0) {
                                Divider(color = if (isDark) Color(0x1AFFFFFF) else Color(0xFFF1F5F9))
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
                                            Text("Total Amount", fontSize = 11.sp, color = textSecondary)
                                            Text(
                                                "Rp ${String.format("%,.0f", booking.totalAmount).replace(",", ".")}",
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
                                                Text("Paid", fontSize = 10.sp, color = textSecondary)
                                                Text(
                                                    "Rp ${String.format("%,.0f", booking.paidAmount).replace(",", ".")}",
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
                                colors = CardDefaults.cardColors(containerColor = Color(0xFF10B981).copy(alpha = 0.1f)),
                                border = BorderStroke(1.dp, Color(0xFF10B981).copy(alpha = 0.3f))
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth().padding(16.dp),
                                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Box(
                                        modifier = Modifier.size(40.dp).clip(CircleShape).background(Color(0xFF10B981)),
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
                                            color = Color(0xFF10B981)
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
                                onClick = { showRescheduleDialog = true },
                                modifier = Modifier.weight(1f).height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, BkPrimary.copy(0.5f)),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = BkPrimary)
                            ) {
                                Icon(Icons.Filled.Schedule, null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Reschedule", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                            }

                            // Cancel button
                            OutlinedButton(
                                onClick = { showCancelDialog = true },
                                modifier = Modifier.weight(1f).height(52.dp),
                                shape = RoundedCornerShape(12.dp),
                                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFEF4444).copy(0.5f)),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFFEF4444))
                            ) {
                                Icon(Icons.Filled.Cancel, null, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Cancel", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                            }
                        }
                    }
                }
            }
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
    onBookingCreated: (Int, Boolean, Double) -> Unit,  // (bookingId, isDp, amount)
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.createState.collectAsState()
    val isDark = false
    val bg      = if (isDark) BkBgDark      else BkBgLight
    val surface = if (isDark) BkSurfaceDark else BkSurfaceLight
    val textPrimary   = if (isDark) Color.White       else Color(0xFF0F172A)
    val textSecondary = if (isDark) Color(0xFF94A3B8) else Color(0xFF64748B)
    val dividerColor  = if (isDark) Color(0x1AFFFFFF) else Color(0xFFE2E8F0)

    LaunchedEffect(Unit) { viewModel.loadCreateBookingData(doctorId, petId) }
    LaunchedEffect(state.isCreated) {
        if (state.isCreated && state.createdBookingId != null) {
            val amount = if (state.selectedPaymentType == "dp") state.dpAmount else state.totalAmount
            val isDp = state.selectedPaymentType == "dp"
            Log.d("CreateBooking", "Booking created: id=${state.createdBookingId}, isDp=$isDp, amount=$amount")
            viewModel.clearCreateState()
            onBookingCreated(state.createdBookingId!!, isDp, amount)
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
                        Modifier.fillMaxWidth().padding(horizontal = 8.dp).padding(top = 44.dp, bottom = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(Modifier.size(40.dp).clip(CircleShape).clickable { onNavigateBack() }, contentAlignment = Alignment.Center) {
                            Icon(Icons.Filled.ArrowBack, null, tint = textPrimary, modifier = Modifier.size(24.dp))
                        }
                        Text("Book Appointment", fontSize = 17.sp, fontWeight = FontWeight.Bold, color = textPrimary, modifier = Modifier.weight(1f), textAlign = TextAlign.Center)
                        val context = LocalContext.current
                        TextButton(onClick = {
                            Toast.makeText(context, "Complete each step: service, pet, doctor, schedule, then payment.", Toast.LENGTH_LONG).show()
                        }) {
                            Text("Help", color = BkPrimary, fontWeight = FontWeight.Bold, fontSize = 14.sp)
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
                                    .background(if (active) BkPrimary else if (isDark) Color(0x33FFFFFF) else Color(0xFFCBD5E1))
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
                                    .background(if (isDark) Color.White else Color(0xFFE8F5E9)),
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
                                                Log.e(TAG, "Failed to load create booking doctor photo from: $createBookingPhoto, error: ${state.result.throwable?.message}")
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
                                doc.availableTime?.let {
                                    Text(it, fontSize = 11.sp, color = textSecondary)
                                }
                            }
                        }
                    }
                }

                // ── Section 1: Select Pet ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Who is this appointment for?", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
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
                                    .clickable { viewModel.selectPet(pet.id!!) }
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
                        // Add New chip
                        item {
                            Surface(
                                shape = CircleShape,
                                color = Color.Transparent,
                                border = androidx.compose.foundation.BorderStroke(1.5.dp, if (isDark) Color(0xFF4B5563) else Color(0xFFD1D5DB)),
                                modifier = Modifier.clip(CircleShape).clickable { }
                            ) {
                                Row(
                                    Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(Icons.Filled.Add, null, tint = textSecondary, modifier = Modifier.size(18.dp))
                                    Text("Add New", fontSize = 13.sp, fontWeight = FontWeight.Medium, color = textSecondary)
                                }
                            }
                        }
                    }
                }

                // ── Section 2: Category Grid ───────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Choose Service", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    if (state.services.isEmpty()) {
                        Box(
                            Modifier.fillMaxWidth().padding(vertical = 16.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("Loading services...", fontSize = 14.sp, color = textSecondary)
                        }
                    } else {
                        val serviceRows = state.services.chunked(2)
                        serviceRows.forEachIndexed { rowIdx, rowServices ->
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                rowServices.forEachIndexed { colIdx, service ->
                                    val selected = state.selectedServiceId == service.id
                                    val paletteIndex = (rowIdx * 2 + colIdx) % 4
                                    val (bgLight, bgDark) = when (paletteIndex) {
                                        0 -> Pair(Color(0xFFDCFCE7), Color(0x2216A34A))
                                        1 -> Pair(Color(0xFFDBEAFE), Color(0x222563EB))
                                        2 -> Pair(Color(0xFFFFEDD5), Color(0x22EA580C))
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
                                            containerColor = if (selected) BkPrimary.copy(alpha = 0.1f) else if (isDark) BkSurfaceDark else BkSurfaceLight
                                        ),
                                        elevation = CardDefaults.cardElevation(if (selected) 0.dp else 2.dp)
                                    ) {
                                        Box {
                                            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                                                Box(
                                                    Modifier.size(44.dp).clip(RoundedCornerShape(10.dp))
                                                        .background(if (isDark) bgDark else bgLight),
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
                                                    "Rp ${String.format("%,.0f", service.price ?: 0.0).replace(",", ".")}",
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
                        Text("Select Date", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text(
                                state.selectedDate.format(DateTimeFormatter.ofPattern("MMMM yyyy")),
                                fontSize = 13.sp, fontWeight = FontWeight.Bold, color = BkPrimary
                            )
                            Icon(Icons.Filled.ExpandMore, null, tint = BkPrimary, modifier = Modifier.size(16.dp))
                        }
                    }
                    // 7-day strip
                    Surface(
                        shape = RoundedCornerShape(16.dp),
                        color = surface,
                        shadowElevation = 2.dp
                    ) {
                        Row(
                            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            val today = LocalDate.now()
                            (0..6).forEach { offset ->
                                val date = today.plusDays(offset.toLong())
                                val isSelected = date == state.selectedDate
                                val isPast = date.isBefore(today)
                                DateCell(
                                    dayName = date.dayOfWeek.getDisplayName(TextStyle.SHORT, Locale.ENGLISH),
                                    dayNum  = date.dayOfMonth.toString(),
                                    isSelected = isSelected,
                                    isPast = isPast,
                                    isDark = isDark,
                                    textPrimary = textPrimary,
                                    textSecondary = textSecondary,
                                    onClick = { if (!isPast) viewModel.selectDate(date, doctorId) }
                                )
                            }
                        }
                    }
                }

                // ── Section 4: Time slots ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    Text("Available Time", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)

                    if (state.slots.isEmpty()) {
                        Box(
                            Modifier.fillMaxWidth().padding(vertical = 20.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("No slots available for this date", fontSize = 14.sp, color = textSecondary)
                        }
                    } else {
                        val morningSlots   = state.slots.filter { it.time < "12:00" }
                        val afternoonSlots = state.slots.filter { it.time >= "12:00" }

                        if (morningSlots.isNotEmpty()) {
                            TimeSlotGroup("Morning", morningSlots, state.selectedTime, surface, isDark, textPrimary, textSecondary, dividerColor) {
                                viewModel.selectTime(it)
                            }
                        }
                        if (afternoonSlots.isNotEmpty()) {
                            TimeSlotGroup("Afternoon", afternoonSlots, state.selectedTime, surface, isDark, textPrimary, textSecondary, dividerColor) {
                                viewModel.selectTime(it)
                            }
                        }
                    }
                }

                // ── Section 5: Payment Method ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Payment Method", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    
                    if (state.paymentMethods.isEmpty()) {
                        Box(
                            Modifier.fillMaxWidth().padding(vertical = 20.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Text("Loading payment methods...", fontSize = 14.sp, color = textSecondary)
                        }
                    } else {
                        // Group payment methods by type
                        val qrisMethods = state.paymentMethods.filter { it.type == "qris" }
                        val bankMethods = state.paymentMethods.filter { it.type == "bank" }
                        val ewalletMethods = state.paymentMethods.filter { it.type == "ewallet" }

                        // QRIS
                        if (qrisMethods.isNotEmpty()) {
                            PaymentMethodGroup("QRIS", qrisMethods, state.selectedPaymentMethod, surface, isDark, textPrimary, textSecondary, dividerColor, BkPrimary) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }

                        // Bank
                        if (bankMethods.isNotEmpty()) {
                            PaymentMethodGroup("Bank Transfer", bankMethods, state.selectedPaymentMethod, surface, isDark, textPrimary, textSecondary, dividerColor, Color(0xFF3B82F6)) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }

                        // E-Wallet
                        if (ewalletMethods.isNotEmpty()) {
                            PaymentMethodGroup("E-Wallet", ewalletMethods, state.selectedPaymentMethod, surface, isDark, textPrimary, textSecondary, dividerColor, Color(0xFF8B5CF6)) {
                                viewModel.selectPaymentMethod(it)
                            }
                        }
                    }
                }

                // ── Section 6: Payment Type (DP/Full) ──────────────────────────────
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("Payment Type", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = textPrimary)
                    
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
                                    "Rp ${String.format("%,.0f", state.totalAmount).replace(",", ".")}",
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
                                .then(if (isDpSelected) Modifier.border(2.dp, Color(0xFFF59E0B), RoundedCornerShape(16.dp)) else Modifier),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isDpSelected) Color(0xFFF59E0B).copy(alpha = 0.1f) else surface
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
                                    tint = if (isDpSelected) Color(0xFFF59E0B) else textSecondary,
                                    modifier = Modifier.size(32.dp)
                                )
                                Text(
                                    "Down Payment",
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = textPrimary
                                )
                                Text(
                                    "Rp ${String.format("%,.0f", state.dpAmount).replace(",", ".")}",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = if (isDpSelected) Color(0xFFF59E0B) else textPrimary
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
                                        tint = Color(0xFFF59E0B),
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
                                    "Down Payment 50% dibayar sekarang, sisa dibayar saat appointment" 
                                else 
                                    "Full payment dibayar sekarang untuk mengunci appointment",
                                fontSize = 12.sp,
                                color = textSecondary
                            )
                        }
                    }
                }

                // Error
                if (state.error != null) ErrorBanner(state.error!!)
            }
        }

        // ── Sticky bottom bar ─────────────────────────────────────────
        Surface(
            modifier = Modifier.align(Alignment.BottomCenter).fillMaxWidth(),
            color = bg.copy(alpha = 0.97f),
            shadowElevation = 0.dp
        ) {
            Column(Modifier.fillMaxWidth()) {
                Divider(color = dividerColor, thickness = 1.dp)
                Column(Modifier.padding(horizontal = 16.dp, vertical = 16.dp).padding(bottom = 8.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("Total Estimation", fontSize = 13.sp, color = textSecondary)
                            if (state.selectedPaymentType == "dp") {
                                Text(
                                    "DP 50%",
                                    fontSize = 11.sp,
                                    color = Color(0xFFF59E0B),
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                        Text(
                            if (state.selectedPaymentType == "dp") 
                                "Rp ${String.format("%,.0f", state.dpAmount).replace(",", ".")}" 
                            else 
                                "Rp ${String.format("%,.0f", state.totalAmount).replace(",", ".")}",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (state.selectedPaymentType == "dp") Color(0xFFF59E0B) else textPrimary
                        )
                    }
                    Button(
                        onClick = { viewModel.createBooking(doctorId) },
                        modifier = Modifier.fillMaxWidth().height(56.dp),
                        shape = RoundedCornerShape(16.dp),
                        enabled = !state.isLoading && state.selectedPetId != null && state.selectedServiceId != null && state.selectedTime != null && state.selectedPaymentMethod != null,
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
    }
}

// ── Date cell ──────────────────────────────────────────────────────────────────
@Composable
private fun DateCell(
    dayName: String, dayNum: String,
    isSelected: Boolean, isPast: Boolean,
    isDark: Boolean,
    textPrimary: Color, textSecondary: Color,
    onClick: () -> Unit
) {
    val bg by animateColorAsState(
        if (isSelected) BkPrimary else Color.Transparent, tween(200)
    )
    Column(
        modifier = Modifier
            .clip(RoundedCornerShape(10.dp))
            .background(bg)
            .clickable(enabled = !isPast, onClick = onClick)
            .padding(horizontal = 8.dp, vertical = 8.dp)
            .then(if (isSelected) Modifier.shadow(8.dp, RoundedCornerShape(10.dp), spotColor = BkPrimary.copy(0.3f)) else Modifier),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Text(
            dayName, fontSize = 11.sp, fontWeight = FontWeight.Medium,
            color = if (isSelected) BkPrimaryFg else if (isPast) textSecondary.copy(0.5f) else textSecondary
        )
        Text(
            dayNum, fontSize = if (isSelected) 17.sp else 14.sp,
            fontWeight = if (isSelected) FontWeight.ExtraBold else FontWeight.Bold,
            color = if (isSelected) BkPrimaryFg else if (isPast) textSecondary.copy(0.5f) else textPrimary
        )
    }
}

// ── Time slot group ────────────────────────────────────────────────────────────
@Composable
private fun TimeSlotGroup(
    title: String,
    slots: List<com.christopheraldoo.petheal.data.model.TimeSlot>,
    selectedTime: String?,
    surface: Color,
    isDark: Boolean,
    textPrimary: Color,
    textSecondary: Color,
    dividerColor: Color,
    onSelect: (String) -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(
            title.uppercase(), fontSize = 11.sp,
            fontWeight = FontWeight.Bold, letterSpacing = 1.sp,
            color = if (isDark) Color(0xFF6B7280) else Color(0xFF9CA3AF)
        )
        val cols = 4
        slots.chunked(cols).forEach { rowSlots ->
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                rowSlots.forEach { slot ->
                    val selected  = slot.time == selectedTime
                    val available = slot.available
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(10.dp))
                            .background(
                                when {
                                    selected   -> BkPrimary
                                    !available -> if (isDark) Color(0xFF1F2937) else Color(0xFFF9FAFB)
                                    else       -> surface
                                }
                            )
                            .border(
                                1.dp,
                                when {
                                    selected   -> BkPrimary
                                    !available -> Color.Transparent
                                    else       -> dividerColor
                                },
                                RoundedCornerShape(10.dp)
                            )
                            .clickable(enabled = available && !selected) { onSelect(slot.time) }
                            .padding(vertical = 10.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            slot.time,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = when {
                                selected   -> BkPrimaryFg
                                !available -> if (isDark) Color(0xFF4B5563) else Color(0xFFD1D5DB)
                                else       -> textPrimary
                            },
                            textDecoration = if (!available) TextDecoration.LineThrough else null
                        )
                    }
                }
                // Fill remaining columns in last row
                repeat(cols - rowSlots.size) { Box(modifier = Modifier.weight(1f)) }
            }
        }
    }
}

// ── Error banner ───────────────────────────────────────────────────────────────
@Composable
fun ErrorBanner(message: String) {
    Card(
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = Color(0xFFEF4444).copy(alpha = 0.1f))
    ) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(Icons.Filled.Warning, null, tint = Color(0xFFEF4444), modifier = Modifier.size(20.dp))
            Text(message, color = Color(0xFFEF4444), fontSize = 13.sp)
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
    isDark: Boolean,
    textPrimary: Color,
    textSecondary: Color,
    dividerColor: Color,
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
