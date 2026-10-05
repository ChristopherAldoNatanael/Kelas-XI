package com.christopheraldoo.petheal.ui.screens.doctor

import android.util.Log
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalFocusManager
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.compose.ui.platform.LocalLifecycleOwner
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.DoctorReview
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.ui.components.SkeletonDoctorList
import com.christopheraldoo.petheal.util.buildPhotoUrl
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private const val TAG = "DoctorPhoto"

/** Label jumlah ulasan konsisten di semua layar: "1 ulasan" / "3 ulasan". */
private fun reviewCountLabel(total: Int): String =
    if (total == 1) "1 ulasan" else "$total ulasan"

private fun formatAvgRating(avg: Double): String = "%.1f".format(avg)

private val Primary     = Color(0xFF18C964)
private val PrimaryFg   = Color(0xFF052E14)
private val BgDark      = Color(0xFFF6F8F6)
private val SurfaceDark = Color.White
private val BorderDark  = Color(0xFFE2E8F0)
private val TextPrimary = Color(0xFF0F172A)
private val TextSecDark = Color(0xFF64748B)

@Composable
private fun DocPhoto(
    url: String?,
    size: androidx.compose.ui.unit.Dp,
    fallbackSize: androidx.compose.ui.unit.Dp = size * 0.5f
) {
    val context = LocalContext.current
    val fullUrl = remember(url) { buildPhotoUrl(url) }
    var hasError by remember(fullUrl) { mutableStateOf(false) }
    Box(
        modifier = Modifier.size(size).clip(CircleShape).background(BorderDark),
        contentAlignment = Alignment.Center
    ) {
        if (!fullUrl.isNullOrBlank() && !hasError) {
            AsyncImage(
                model = ImageRequest.Builder(context)
                    .data(fullUrl)
                    // No extra headers needed — shared OkHttpClient already adds ngrok header
                    .memoryCachePolicy(CachePolicy.ENABLED)
                    .diskCachePolicy(CachePolicy.ENABLED)
                    .memoryCacheKey(fullUrl)
                    .diskCacheKey(fullUrl)
                    // Decode image at exact display size — avoids loading 2MB photo
                    // into memory when showing a 56dp circle
                    .size(256, 256)
                    .crossfade(200)
                    .build(),
                contentDescription = "Doctor Photo",
                contentScale = ContentScale.Crop,
                modifier = Modifier.fillMaxSize().clip(CircleShape),
                onError = { Log.e(TAG, "Failed to load: $fullUrl"); hasError = true }
            )
        } else {
            Icon(Icons.Filled.Person, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(fallbackSize))
        }
    }
}

@Composable
fun DoctorsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToDoctorDetail: (Int) -> Unit,
    viewModel: DoctorsViewModel = hiltViewModel()
) {
    val state by viewModel.listState.collectAsState()
    val focusManager = LocalFocusManager.current
    // Kembali dari detail (mis. setelah kirim review) → sinkronkan daftar
    // dari cache yang sudah di-patch. Murah: tanpa spinner bila cache ada.
    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) viewModel.loadDoctors()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }
    Column(modifier = Modifier.fillMaxSize().background(BgDark)) {
        Box(modifier = Modifier.fillMaxWidth().background(SurfaceDark).padding(top = 44.dp, start = 8.dp, end = 20.dp, bottom = 14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onNavigateBack) { Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = TextPrimary) }
                Spacer(Modifier.width(4.dp))
                Column {
                    Text("Cari Dokter", color = TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    Text(
                        if (state.doctors.isEmpty()) "Jelajahi dokter di klinik ini"
                        else "${state.doctors.size} dokter tersedia · ketuk untuk lihat profil",
                        color = TextSecDark, fontSize = 12.sp
                    )
                }
            }
        }
        Box(modifier = Modifier.fillMaxWidth().background(SurfaceDark).padding(horizontal = 16.dp, vertical = 10.dp)) {
            OutlinedTextField(
                value = state.searchQuery,
                onValueChange = viewModel::onSearchChange,
                placeholder = { Text("Cari nama atau spesialisasi...", color = TextSecDark, fontSize = 14.sp) },
                leadingIcon = { Icon(Icons.Outlined.Search, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(20.dp)) },
                trailingIcon = {
                    if (state.searchQuery.isNotBlank()) {
                        IconButton(onClick = { viewModel.onSearchChange("") }) { Icon(Icons.Filled.Close, contentDescription = "Hapus pencarian", tint = TextSecDark, modifier = Modifier.size(18.dp)) }
                    }
                },
                singleLine = true,
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(onSearch = { focusManager.clearFocus() }),
                shape = RoundedCornerShape(14.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = TextPrimary, unfocusedTextColor = TextPrimary,
                    focusedBorderColor = Primary, unfocusedBorderColor = BorderDark,
                    cursorColor = Primary, focusedContainerColor = BgDark, unfocusedContainerColor = BgDark
                ),
                modifier = Modifier.fillMaxWidth()
            )
        }
        Divider(color = BorderDark, thickness = 0.5.dp)
        val listError = state.error
        when {
            state.isLoading -> SkeletonDoctorList(count = 4)
            listError != null -> DoctorsErrorState(message = listError, onRetry = viewModel::loadDoctors)
            state.filtered.isEmpty() -> DoctorsEmptyState(hasQuery = state.searchQuery.isNotBlank())
            else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                items(state.filtered, key = { it.id ?: 0 }) { doctor ->
                    DoctorCard(doctor = doctor, onClick = { doctor.id?.let(onNavigateToDoctorDetail) })
                }
            }
        }
    }
}

@Composable
fun DoctorDetailScreen(
    doctorId: Int,
    onNavigateBack: () -> Unit,
    // PHASE 9: dibuka dari pengingat rating → dialog nilai langsung terbuka.
    autoOpenReview: Boolean = false,
    viewModel: DoctorsViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    var showReviewDialog by remember { mutableStateOf(false) }
    // Konsumsi sekali: dialog terbuka otomatis hanya setelah daftar booking
    // yang bisa dinilai termuat (tidak kosong) — rotasi tidak membuka ulang.
    var autoReviewConsumed by remember(autoOpenReview) { mutableStateOf(!autoOpenReview) }
    LaunchedEffect(doctorId) { viewModel.loadDoctorDetail(doctorId) }
    LaunchedEffect(autoOpenReview, state.reviewableBookings) {
        if (autoOpenReview && !autoReviewConsumed && state.reviewableBookings.isNotEmpty()) {
            autoReviewConsumed = true
            showReviewDialog = true
        }
    }
    if (showReviewDialog) {
        SubmitReviewDialog(
            bookings = state.reviewableBookings,
            isSubmitting = state.isSubmittingReview,
            onDismiss = { showReviewDialog = false },
            onSubmit = { bookingId, rating, review ->
                viewModel.submitReview(doctorId, bookingId, rating, review)
                showReviewDialog = false
            }
        )
    }
    Box(modifier = Modifier.fillMaxSize().background(BgDark)) {
        if (state.isLoading) {
            CircularProgressIndicator(color = Primary, modifier = Modifier.align(Alignment.Center))
        } else {
                Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
                    // ── Profil dokter ─────────────────────────────────
                    Box(modifier = Modifier.fillMaxWidth().background(Brush.verticalGradient(listOf(SurfaceDark, BgDark))).padding(20.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconButton(onClick = onNavigateBack, modifier = Modifier.align(Alignment.Top)) {
                                Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = TextPrimary)
                            }
                            Spacer(Modifier.width(8.dp))
                            Box(modifier = Modifier.size(80.dp).clip(CircleShape).border(2.dp, Primary, CircleShape).background(BorderDark), contentAlignment = Alignment.Center) {
                                DocPhoto(url = buildPhotoUrl(state.doctor?.photo), size = 80.dp, fallbackSize = 40.dp)
                            }
                            Spacer(Modifier.width(14.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(text = state.doctor?.name ?: "—", color = TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                                Spacer(Modifier.height(3.dp))
                                Text(text = state.doctor?.specialization ?: "Dokter Hewan", color = Primary, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                Spacer(Modifier.height(6.dp))
                                // Rating memakai angka authoritative dari endpoint
                                // reviews — sama dengan yang di-patch ke list,
                                // jadi konsisten dengan kartu di Cari Dokter.
                                if (state.totalReviews > 0) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(14.dp))
                                        Spacer(Modifier.width(4.dp))
                                        Text(formatAvgRating(state.averageRating), color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                        Text(" · ${reviewCountLabel(state.totalReviews)}", color = TextSecDark, fontSize = 12.sp)
                                    }
                                } else {
                                    Text("Belum ada ulasan", color = TextSecDark, fontSize = 12.sp)
                                }
                            }
                        }
                    }
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    Spacer(Modifier.height(20.dp))
                    // ── Tentang dokter ────────────────────────────────
                    Text("Tentang Dokter", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(horizontal = 20.dp))
                    Spacer(Modifier.height(10.dp))
                    DoctorAboutCard(
                        specialization = state.doctor?.specialization,
                        availableDays = state.doctor?.availableDays,
                        availableTime = state.doctor?.availableTime,
                        modifier = Modifier.padding(horizontal = 20.dp)
                    )
                    Spacer(Modifier.height(20.dp))
                    // ── Jadwal / ketersediaan (pratinjau) ─────────────
                    Text("Jadwal Praktik", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(horizontal = 20.dp))
                    Spacer(Modifier.height(10.dp))
                    DatePickerRow(selectedDate = state.selectedDate, onDateSelected = { viewModel.onDateSelected(doctorId, it) })
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 20.dp)) {
                        Text("Slot Tersedia", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                        if (state.isSlotsLoading) CircularProgressIndicator(color = Primary, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                    }
                    Text(
                        "Pratinjau ketersediaan — jam final dipilih saat booking",
                        color = TextSecDark, fontSize = 11.sp,
                        modifier = Modifier.padding(horizontal = 20.dp).padding(top = 2.dp)
                    )
                    Spacer(Modifier.height(10.dp))
                    TimeSlotsGrid(slots = state.slots, isSlotsLoading = state.isSlotsLoading, modifier = Modifier.padding(horizontal = 20.dp))
                    Spacer(Modifier.height(22.dp))
                    DoctorReviewsSection(
                        averageRating = state.averageRating,
                        totalReviews = state.totalReviews,
                        reviews = state.reviews,
                        reviewableBookingsCount = state.reviewableBookings.size,
                        onSubmitReview = { showReviewDialog = true },
                        modifier = Modifier.padding(horizontal = 20.dp)
                    )
                    // Halaman ini murni discovery: profil, jadwal, dan ulasan.
                    // Booking hanya lewat alur "Buat Booking" (transaksi).
                    Spacer(Modifier.height(32.dp))
                }
        }
        state.error?.let { err ->
            Snackbar(
                modifier = Modifier.align(Alignment.BottomCenter).padding(16.dp),
                containerColor = Color(0xFFFFEBEE), contentColor = Color(0xFFDC2626),
                dismissAction = { IconButton(onClick = viewModel::clearError) { Icon(Icons.Filled.Close, null, tint = Color(0xFFDC2626)) } }
            ) { Text(err) }
        }
        state.reviewMessage?.let { message ->
            Snackbar(
                modifier = Modifier.align(Alignment.BottomCenter).padding(16.dp),
                containerColor = Color(0xFFE8FFF1),
                contentColor = Color(0xFF047857),
                dismissAction = { IconButton(onClick = viewModel::clearError) { Icon(Icons.Filled.Close, null, tint = Color(0xFF047857)) } }
            ) { Text(message) }
        }
    }
}

@Composable
private fun DoctorAboutCard(
    specialization: String?,
    availableDays: String?,
    availableTime: String?,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(SurfaceDark)
            .border(1.dp, BorderDark, RoundedCornerShape(16.dp))
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        DoctorAboutRow(
            icon = Icons.Outlined.MedicalServices,
            label = "Spesialisasi",
            value = specialization ?: "Dokter Hewan"
        )
        DoctorAboutRow(
            icon = Icons.Outlined.CalendarMonth,
            label = "Hari praktik",
            value = availableDays?.takeIf { it.isNotBlank() } ?: "Jadwal menyusul"
        )
        DoctorAboutRow(
            icon = Icons.Outlined.Schedule,
            label = "Jam praktik",
            value = availableTime?.takeIf { it.isNotBlank() } ?: "Jadwal menyusul"
        )
    }
}

@Composable
private fun DoctorAboutRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        Box(
            modifier = Modifier.size(36.dp).clip(RoundedCornerShape(10.dp))
                .background(Primary.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(icon, contentDescription = null, tint = Primary, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Column {
            Text(label, color = TextSecDark, fontSize = 11.sp)
            Text(value, color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun DoctorReviewsSection(
    averageRating: Double,
    totalReviews: Int,
    reviews: List<DoctorReview>,
    reviewableBookingsCount: Int,
    onSubmitReview: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Ulasan Pasien", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            if (reviewableBookingsCount > 0) {
                TextButton(onClick = onSubmitReview, contentPadding = PaddingValues(horizontal = 8.dp)) {
                    Icon(Icons.Filled.RateReview, null, tint = Primary, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Beri Nilai", color = Primary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
            Row(
                modifier = Modifier.clip(RoundedCornerShape(50)).background(BorderDark).padding(horizontal = 10.dp, vertical = 5.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (totalReviews > 0) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(15.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(formatAvgRating(averageRating), color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Text(" · ${reviewCountLabel(totalReviews)}", color = TextSecDark, fontSize = 12.sp)
                } else {
                    Text("Belum ada ulasan", color = TextSecDark, fontSize = 12.sp)
                }
            }
        }
        Spacer(Modifier.height(10.dp))

        if (reviewableBookingsCount > 0) {
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                color = Primary.copy(alpha = 0.08f),
                border = BorderStroke(1.dp, Primary.copy(alpha = 0.18f))
            ) {
                Row(
                    modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Beri nilai dokter ini", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        Text(
                            "Booking yang sudah selesai bisa diberi nilai satu kali. Pilih kunjungan yang selesai lalu kirim penilaian Anda di sini.",
                            color = TextSecDark,
                            fontSize = 12.sp,
                            lineHeight = 18.sp
                        )
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
        }

        if (reviews.isEmpty()) {
            Box(
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(SurfaceDark).border(1.dp, BorderDark, RoundedCornerShape(12.dp)).padding(18.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("Belum ada ulasan untuk dokter ini", color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
                    if (reviewableBookingsCount == 0) {
                        Spacer(Modifier.height(4.dp))
                        Text(
                            "Ulasan dari pemilik hewan lain akan tampil di sini setelah kunjungan selesai.",
                            color = TextSecDark.copy(alpha = 0.8f), fontSize = 12.sp, textAlign = TextAlign.Center
                        )
                    }
                }
            }
        } else {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                reviews.take(3).forEach { review ->
                    Column(
                        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(SurfaceDark).border(1.dp, BorderDark, RoundedCornerShape(12.dp)).padding(14.dp)
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(review.user?.name ?: "Pengguna", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                repeat(review.rating ?: 0) {
                                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(13.dp))
                                }
                            }
                        }
                        if (!review.review.isNullOrBlank()) {
                            Spacer(Modifier.height(6.dp))
                            Text(review.review, color = TextSecDark, fontSize = 12.sp, lineHeight = 17.sp, maxLines = 3, overflow = TextOverflow.Ellipsis)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun SubmitReviewDialog(
    bookings: List<Booking>,
    isSubmitting: Boolean,
    onDismiss: () -> Unit,
    onSubmit: (bookingId: Int, rating: Int, review: String?) -> Unit
) {
    var selectedBookingId by remember(bookings) { mutableStateOf(bookings.firstOrNull()?.id) }
    var rating by remember { mutableStateOf(5) }
    var review by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = SurfaceDark,
        titleContentColor = TextPrimary,
        textContentColor = TextSecDark,
        title = { Text("Beri Nilai Dokter", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    "Pilih booking yang sudah selesai, lalu berikan rating dan catatan (opsional).",
                    color = TextSecDark,
                    fontSize = 12.sp,
                    lineHeight = 18.sp
                )
                Text("Pilih booking yang sudah selesai", color = TextSecDark, fontSize = 12.sp)
                bookings.forEach { booking ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (selectedBookingId == booking.id) Primary.copy(alpha = 0.14f) else BorderDark)
                            .clickable { selectedBookingId = booking.id }
                            .padding(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        RadioButton(
                            selected = selectedBookingId == booking.id,
                            onClick = { selectedBookingId = booking.id },
                            colors = RadioButtonDefaults.colors(selectedColor = Primary)
                        )
                        Column {
                            Text(booking.pet?.name ?: "Pet", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                            Text("${booking.bookingDate ?: "-"} ${booking.bookingTime ?: ""}", color = TextSecDark, fontSize = 11.sp)
                        }
                    }
                }
                Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                    (1..5).forEach { value ->
                        Icon(
                            imageVector = Icons.Filled.Star,
                            contentDescription = "$value star",
                            tint = if (value <= rating) Color(0xFFFFC857) else TextSecDark.copy(alpha = 0.35f),
                            modifier = Modifier.size(30.dp).clickable { rating = value }
                        )
                    }
                }
                OutlinedTextField(
                    value = review,
                    onValueChange = { review = it },
                    label = { Text("Catatan ulasan") },
                    minLines = 3,
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedTextColor = TextPrimary,
                        unfocusedTextColor = TextPrimary,
                        focusedBorderColor = Primary,
                        unfocusedBorderColor = BorderDark,
                        focusedLabelColor = Primary,
                        unfocusedLabelColor = TextSecDark
                    )
                )
            }
        },
        confirmButton = {
            Button(
                enabled = !isSubmitting && selectedBookingId != null,
                onClick = {
                    selectedBookingId?.let { onSubmit(it, rating, review.trim().ifBlank { null }) }
                },
                colors = ButtonDefaults.buttonColors(containerColor = Primary, contentColor = PrimaryFg)
            ) { Text(if (isSubmitting) "Mengirim..." else "Kirim") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Batal", color = TextSecDark) }
        }
    )
}

@Composable
private fun DoctorCard(doctor: Doctor, onClick: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(SurfaceDark)
            .border(1.dp, BorderDark, RoundedCornerShape(16.dp)).clickable(onClick = onClick).padding(14.dp)
    ) {
        Box(modifier = Modifier.size(64.dp).clip(CircleShape).border(2.dp, BorderDark, CircleShape).background(BgDark), contentAlignment = Alignment.Center) {
            DocPhoto(url = buildPhotoUrl(doctor.photo), size = 64.dp, fallbackSize = 32.dp)
        }
        Spacer(Modifier.width(14.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(text = doctor.name ?: "Tidak diketahui", color = TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(3.dp))
            Text(text = doctor.specialization ?: "Dokter Hewan", color = Primary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(6.dp))
            val avg = doctor.averageRating
            if (avg != null) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(formatAvgRating(avg), color = TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    Text(" · ${reviewCountLabel(doctor.reviewsCount ?: 0)}", color = TextSecDark, fontSize = 11.sp)
                }
                Spacer(Modifier.height(6.dp))
            }
            val days = doctor.availableDays
            if (!days.isNullOrBlank()) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Outlined.Schedule, null, tint = TextSecDark, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(days, color = TextSecDark, fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
        }
        Spacer(Modifier.width(8.dp))
        Icon(Icons.Filled.ChevronRight, null, tint = TextSecDark, modifier = Modifier.size(20.dp))
    }
}

@Composable
private fun DatePickerRow(selectedDate: String, onDateSelected: (String) -> Unit) {
    val today   = LocalDate.now()
    val fmt     = DateTimeFormatter.ofPattern("yyyy-MM-dd")
    val dayFmt  = DateTimeFormatter.ofPattern("EEE", Locale("id", "ID"))
    val dateFmt = DateTimeFormatter.ofPattern("d")
    // Include today (same as the booking create flow): the backend returns an
    // empty slot list when nothing is available, which the UI already handles.
    val days    = remember { (0..13).map { today.plusDays(it.toLong()) } }
    Row(modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        days.forEach { date ->
            val iso = date.format(fmt)
            val isSelected = iso == selectedDate
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.clip(RoundedCornerShape(12.dp))
                    .background(if (isSelected) Primary else SurfaceDark)
                    .border(1.dp, if (isSelected) Primary else BorderDark, RoundedCornerShape(12.dp))
                    .clickable { onDateSelected(iso) }
                    .padding(horizontal = 12.dp, vertical = 10.dp)
            ) {
                Text(text = date.format(dayFmt).uppercase(), color = if (isSelected) PrimaryFg else TextSecDark, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                Spacer(Modifier.height(4.dp))
                Text(text = date.format(dateFmt), color = if (isSelected) PrimaryFg else TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun TimeSlotsGrid(slots: List<TimeSlot>, isSlotsLoading: Boolean, modifier: Modifier = Modifier) {
    when {
        isSlotsLoading -> Box(modifier = modifier.fillMaxWidth().height(80.dp), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = Primary, strokeWidth = 2.dp, modifier = Modifier.size(24.dp))
        }
        slots.isEmpty() -> Box(modifier = modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(SurfaceDark).border(1.dp, BorderDark, RoundedCornerShape(12.dp)).padding(24.dp), contentAlignment = Alignment.Center) {
            Text("Tidak ada slot tersedia untuk tanggal ini", color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
        }
        else -> LazyRow(modifier = modifier, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            items(slots) { slot ->
                Box(
                    modifier = Modifier.clip(RoundedCornerShape(10.dp))
                        .background(if (slot.available) BorderDark else Color(0xFFF1F5F9))
                        .border(1.dp, if (slot.available) Primary.copy(alpha = 0.6f) else BorderDark, RoundedCornerShape(10.dp))
                        .padding(horizontal = 14.dp, vertical = 8.dp)
                ) {
                    Text(text = slot.time, color = if (slot.available) TextPrimary else TextSecDark.copy(alpha = 0.4f), fontSize = 13.sp, fontWeight = if (slot.available) FontWeight.Medium else FontWeight.Normal)
                }
            }
        }
    }
}

@Composable
private fun DoctorsEmptyState(hasQuery: Boolean) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
            Icon(imageVector = if (hasQuery) Icons.Outlined.SearchOff else Icons.Outlined.MedicalServices, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(56.dp))
            Spacer(Modifier.height(16.dp))
            Text(text = if (hasQuery) "Dokter tidak ditemukan" else "Belum ada dokter tersedia", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            Text(text = if (hasQuery) "Coba kata kunci lain" else "Coba lagi beberapa saat lagi", color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
        }
    }
}

@Composable
private fun DoctorsErrorState(message: String, onRetry: () -> Unit) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
            Icon(Icons.Outlined.WifiOff, null, tint = TextSecDark, modifier = Modifier.size(56.dp))
            Spacer(Modifier.height(16.dp))
            Text("Terjadi kesalahan", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            Text(message, color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(20.dp))
            Button(onClick = onRetry, shape = RoundedCornerShape(50), colors = ButtonDefaults.buttonColors(containerColor = Primary, contentColor = PrimaryFg)) {
                Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text("Coba Lagi", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
