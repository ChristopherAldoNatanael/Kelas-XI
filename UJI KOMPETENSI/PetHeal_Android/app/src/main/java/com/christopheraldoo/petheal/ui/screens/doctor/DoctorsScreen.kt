package com.christopheraldoo.petheal.ui.screens.doctor

import android.util.Log
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import coil.request.CachePolicy
import coil.request.ImageRequest
import coil.size.Size
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.DoctorReview
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.util.buildPhotoUrl
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private const val TAG = "DoctorPhoto"

private val Primary     = Color(0xFF2BEE6C)
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
                    .size(Size.ORIGINAL)
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
private fun PetPhoto(url: String?, size: androidx.compose.ui.unit.Dp) {
    val context = LocalContext.current
    val fullUrl = remember(url) { buildPhotoUrl(url) }
    var hasError by remember(fullUrl) { mutableStateOf(false) }
    
    if (!fullUrl.isNullOrBlank() && !hasError) {
        AsyncImage(
            model = ImageRequest.Builder(context)
                .data(fullUrl)
                .memoryCachePolicy(CachePolicy.ENABLED)
                .diskCachePolicy(CachePolicy.ENABLED)
                .crossfade(200)
                .build(),
            contentDescription = "Pet Photo",
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize().clip(CircleShape),
            onError = { Log.e(TAG, "Failed to load pet photo: $fullUrl"); hasError = true }
        )
    } else {
        Icon(Icons.Filled.Pets, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(size * 0.5f))
    }
}

@Composable
fun DoctorsScreen(
    onNavigateBack: () -> Unit,
    onNavigateToDoctorDetail: (Int) -> Unit,
    viewModel: DoctorsViewModel = hiltViewModel()
) {
    val state by viewModel.listState.collectAsState()
    Column(modifier = Modifier.fillMaxSize().background(BgDark)) {
        Box(modifier = Modifier.fillMaxWidth().background(SurfaceDark).padding(top = 44.dp, start = 8.dp, end = 20.dp, bottom = 14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = onNavigateBack) { Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = TextPrimary) }
                Spacer(Modifier.width(4.dp))
                Column {
                    Text("Find a Doctor", color = TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    Text("${state.doctors.size} veterinarians available", color = TextSecDark, fontSize = 12.sp)
                }
            }
        }
        Box(modifier = Modifier.fillMaxWidth().background(SurfaceDark).padding(horizontal = 16.dp, vertical = 10.dp)) {
            OutlinedTextField(
                value = state.searchQuery,
                onValueChange = viewModel::onSearchChange,
                placeholder = { Text("Search by name or specialization...", color = TextSecDark, fontSize = 14.sp) },
                leadingIcon = { Icon(Icons.Outlined.Search, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(20.dp)) },
                trailingIcon = {
                    if (state.searchQuery.isNotBlank()) {
                        IconButton(onClick = { viewModel.onSearchChange("") }) { Icon(Icons.Filled.Close, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(18.dp)) }
                    }
                },
                singleLine = true,
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
        when {
            state.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Primary) }
            state.error != null -> DoctorsErrorState(message = state.error!!, onRetry = viewModel::loadDoctors)
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
    onNavigateToBooking: (Int) -> Unit,
    viewModel: DoctorsViewModel = hiltViewModel()
) {
    val state by viewModel.detailState.collectAsState()
    var showPetSheet by remember { mutableStateOf(false) }
    var showReviewDialog by remember { mutableStateOf(false) }
    LaunchedEffect(doctorId) { viewModel.loadDoctorDetail(doctorId) }
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
    if (showPetSheet) {
        PetPickerSheet(
            pets = state.pets,
            onDismiss = { showPetSheet = false },
            onSelected = { petId -> showPetSheet = false; onNavigateToBooking(petId) }
        )
    }
    Box(modifier = Modifier.fillMaxSize().background(BgDark)) {
        if (state.isLoading) {
            CircularProgressIndicator(color = Primary, modifier = Modifier.align(Alignment.Center))
        } else {
            Column(modifier = Modifier.fillMaxSize()) {
                Column(modifier = Modifier.fillMaxSize().weight(1f).verticalScroll(rememberScrollState())) {
                    Box(modifier = Modifier.fillMaxWidth().background(Brush.verticalGradient(listOf(SurfaceDark, BgDark))).padding(20.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconButton(onClick = onNavigateBack, modifier = Modifier.align(Alignment.Top)) {
                                Icon(Icons.Filled.ArrowBack, contentDescription = "Back", tint = TextPrimary)
                            }
                            Spacer(Modifier.width(8.dp))
                            Box(modifier = Modifier.size(80.dp).clip(CircleShape).border(2.dp, Primary, CircleShape).background(BorderDark), contentAlignment = Alignment.Center) {
                                DocPhoto(url = buildPhotoUrl(state.doctor?.photo), size = 80.dp, fallbackSize = 40.dp)
                            }
                            Spacer(Modifier.width(14.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(text = state.doctor?.name ?: "—", color = TextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                                Spacer(Modifier.height(3.dp))
                                Text(text = state.doctor?.specialization ?: "Veterinarian", color = Primary, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                                Spacer(Modifier.height(6.dp))
                                val days = state.doctor?.availableDays
                                if (!days.isNullOrBlank()) {
                                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.clip(RoundedCornerShape(50)).background(BorderDark).padding(horizontal = 8.dp, vertical = 3.dp)) {
                                        Icon(Icons.Outlined.CalendarMonth, contentDescription = null, tint = TextSecDark, modifier = Modifier.size(12.dp))
                                        Spacer(Modifier.width(4.dp))
                                        Text(days, color = TextSecDark, fontSize = 11.sp)
                                    }
                                }
                            }
                        }
                    }
                    Divider(color = BorderDark, thickness = 0.5.dp)
                    Spacer(Modifier.height(20.dp))
                    Text("Select Date", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(horizontal = 20.dp))
                    Spacer(Modifier.height(10.dp))
                    DatePickerRow(selectedDate = state.selectedDate, onDateSelected = { viewModel.onDateSelected(doctorId, it) })
                    Spacer(Modifier.height(20.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 20.dp)) {
                        Text("Available Slots", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
                        if (state.isSlotsLoading) CircularProgressIndicator(color = Primary, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                    }
                    Spacer(Modifier.height(10.dp))
                    TimeSlotsGrid(slots = state.slots, isSlotsLoading = state.isSlotsLoading, modifier = Modifier.padding(horizontal = 20.dp))
                    Spacer(Modifier.height(22.dp))
                    DoctorReviewsSection(
                        averageRating = state.averageRating,
                        totalReviews = state.totalReviews,
                        reviews = state.reviews,
                        canSubmitReview = state.reviewableBookings.isNotEmpty(),
                        onSubmitReview = { showReviewDialog = true },
                        modifier = Modifier.padding(horizontal = 20.dp)
                    )
                    Spacer(Modifier.height(100.dp))
                }
                Box(modifier = Modifier.fillMaxWidth().background(SurfaceDark).padding(horizontal = 20.dp, vertical = 16.dp)) {
                    Button(
                        onClick = {
                            if (state.pets.isEmpty()) return@Button
                            if (state.pets.size == 1) state.pets.first().id?.let(onNavigateToBooking) else showPetSheet = true
                        },
                        enabled = state.doctor != null,
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Primary, contentColor = PrimaryFg, disabledContainerColor = BorderDark, disabledContentColor = TextSecDark),
                        modifier = Modifier.fillMaxWidth().height(52.dp)
                    ) {
                        Icon(Icons.Outlined.CalendarMonth, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(text = if (state.pets.isEmpty()) "Add a pet first" else "Book Appointment", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    }
                }
            }
        }
        state.error?.let { err ->
            Snackbar(
                modifier = Modifier.align(Alignment.BottomCenter).padding(16.dp),
                containerColor = Color(0xFFFFEBEE), contentColor = Color(0xFFDC2626),
                dismissAction = { IconButton(onClick = viewModel::clearError) { Icon(Icons.Filled.Close, null, tint = Color(0xFFDC2626)) } }
            ) { Text(err) }
        }
    }
}

@Composable
private fun DoctorReviewsSection(
    averageRating: Double,
    totalReviews: Int,
    reviews: List<DoctorReview>,
    canSubmitReview: Boolean,
    onSubmitReview: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Patient Reviews", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            if (canSubmitReview) {
                TextButton(onClick = onSubmitReview, contentPadding = PaddingValues(horizontal = 8.dp)) {
                    Icon(Icons.Filled.RateReview, null, tint = Primary, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Review", color = Primary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
            Row(
                modifier = Modifier.clip(RoundedCornerShape(50)).background(BorderDark).padding(horizontal = 10.dp, vertical = 5.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(15.dp))
                Spacer(Modifier.width(4.dp))
                Text("%.1f".format(averageRating), color = TextPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Text(" ($totalReviews)", color = TextSecDark, fontSize = 12.sp)
            }
        }
        Spacer(Modifier.height(10.dp))

        if (reviews.isEmpty()) {
            Box(
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(SurfaceDark).border(1.dp, BorderDark, RoundedCornerShape(12.dp)).padding(18.dp),
                contentAlignment = Alignment.Center
            ) {
                Text("No reviews yet", color = TextSecDark, fontSize = 13.sp)
            }
        } else {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                reviews.take(3).forEach { review ->
                    Column(
                        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(SurfaceDark).border(1.dp, BorderDark, RoundedCornerShape(12.dp)).padding(14.dp)
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(review.user?.name ?: "PetHeal User", color = TextPrimary, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
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
        title = { Text("Review Doctor", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("Select completed booking", color = TextSecDark, fontSize = 12.sp)
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
                    label = { Text("Review notes") },
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
            ) { Text(if (isSubmitting) "Sending..." else "Submit") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Cancel", color = TextSecDark) }
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
            Text(text = doctor.name ?: "Unknown", color = TextPrimary, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(3.dp))
            Text(text = doctor.specialization ?: "Veterinarian", color = Primary, fontSize = 12.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(6.dp))
            if (doctor.averageRating != null) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("%.1f".format(doctor.averageRating), color = TextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    Text(" (${doctor.reviewsCount ?: 0})", color = TextSecDark, fontSize = 11.sp)
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
    val dayFmt  = DateTimeFormatter.ofPattern("EEE", Locale.ENGLISH)
    val dateFmt = DateTimeFormatter.ofPattern("d")
    val days    = remember { (1..14).map { today.plusDays(it.toLong()) } }
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
            Text("No slots available for this date", color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PetPickerSheet(pets: List<Pet>, onDismiss: () -> Unit, onSelected: (Int) -> Unit) {
    ModalBottomSheet(
        onDismissRequest = onDismiss, containerColor = SurfaceDark,
        dragHandle = { Box(modifier = Modifier.padding(vertical = 10.dp).width(36.dp).height(4.dp).clip(RoundedCornerShape(50)).background(BorderDark)) }
    ) {
        Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 40.dp)) {
            Text("Select a Pet", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(bottom = 16.dp))
            pets.forEach { pet ->
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { pet.id?.let(onSelected) }.padding(12.dp)) {
                    Box(modifier = Modifier.size(44.dp).clip(CircleShape).background(BorderDark), contentAlignment = Alignment.Center) {
                        PetPhoto(url = pet.photo, size = 44.dp)
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(pet.name ?: "—", color = TextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
                        Text("${pet.species ?: ""} ${if (!pet.breed.isNullOrBlank()) "• ${pet.breed}" else ""}".trim(), color = TextSecDark, fontSize = 12.sp)
                    }
                    Icon(Icons.Filled.ChevronRight, null, tint = TextSecDark, modifier = Modifier.size(18.dp))
                }
                Divider(color = BorderDark, thickness = 0.5.dp)
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
            Text(text = if (hasQuery) "No doctors found" else "No doctors available", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            Text(text = if (hasQuery) "Try a different search term" else "Check back later", color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
        }
    }
}

@Composable
private fun DoctorsErrorState(message: String, onRetry: () -> Unit) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
            Icon(Icons.Outlined.WifiOff, null, tint = TextSecDark, modifier = Modifier.size(56.dp))
            Spacer(Modifier.height(16.dp))
            Text("Something went wrong", color = TextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            Spacer(Modifier.height(6.dp))
            Text(message, color = TextSecDark, fontSize = 13.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(20.dp))
            Button(onClick = onRetry, shape = RoundedCornerShape(50), colors = ButtonDefaults.buttonColors(containerColor = Primary, contentColor = PrimaryFg)) {
                Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text("Try Again", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
