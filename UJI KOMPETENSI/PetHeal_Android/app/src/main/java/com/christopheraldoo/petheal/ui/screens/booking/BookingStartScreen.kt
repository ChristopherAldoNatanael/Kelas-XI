package com.christopheraldoo.petheal.ui.screens.booking

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.outlined.CalendarMonth
import androidx.compose.material.icons.outlined.Search
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.util.ThumbnailImage
import com.christopheraldoo.petheal.util.buildPhotoUrl

private val BsPrimary = Color(0xFF18C964)
private val BsPrimaryFg = Color(0xFF052E14)
private val BsBg = Color(0xFFF6F8F6)
private val BsSurface = Color.White
private val BsBorder = Color(0xFFE2E8F0)
private val BsTextPrimary = Color(0xFF0F172A)
private val BsTextSecondary = Color(0xFF64748B)

private fun bsReviewLabel(total: Int): String =
    if (total == 1) "1 ulasan" else "$total ulasan"

// ── Entry point alur transaksi booking ──────────────────────────────────────
// Berbeda dari Cari Dokter (discovery): layar ini tidak menampilkan profil
// panjang, ulasan lengkap, atau jadwal. Hanya dua pilihan ringkas — hewan
// dan dokter — lalu diteruskan ke CreateBooking (layanan → tanggal → jam →
// pembayaran → konfirmasi) dengan keduanya sudah terisi.
@Composable
fun BookingStartScreen(
    onNavigateBack: () -> Unit,
    onNavigateToAddPet: () -> Unit = {},
    onContinue: (doctorId: Int, petId: Int) -> Unit,
    viewModel: BookingViewModel = hiltViewModel()
) {
    val state by viewModel.startState.collectAsState()
    val focusManager = LocalFocusManager.current

    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        viewModel.loadBookingStartData()
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) viewModel.loadBookingStartData()
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    val selectedDoctor = remember(state.doctors, state.selectedDoctorId) {
        state.doctors.firstOrNull { it.id == state.selectedDoctorId }
    }
    val selectedPet = remember(state.pets, state.selectedPetId) {
        state.pets.firstOrNull { it.id == state.selectedPetId }
    }

    Column(modifier = Modifier.fillMaxSize().background(BsBg)) {
        // ── Header: posisi jelas dalam alur ──
        Surface(color = BsSurface, shadowElevation = 0.dp) {
            Column {
                Row(
                    modifier = Modifier.fillMaxWidth()
                        .statusBarsPadding()
                        .padding(start = 8.dp, end = 20.dp, top = 8.dp, bottom = 4.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = BsTextPrimary)
                    }
                    Spacer(Modifier.width(4.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text("Buat Booking", color = BsTextPrimary, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Text(
                            "Langkah 1 dari 2 · pilih hewan & dokter",
                            color = BsTextSecondary, fontSize = 12.sp
                        )
                    }
                }
                BookingStartSteps(currentStep = if (state.selectedPetId == null) 1 else 2)
                Divider(color = BsBorder, thickness = 0.5.dp)
            }
        }

        when {
            state.isLoading -> BookingStartLoading(modifier = Modifier.weight(1f))
            state.error != null && state.pets.isEmpty() && state.doctors.isEmpty() ->
                BookingStartError(
                    message = state.error ?: "Gagal memuat",
                    onRetry = viewModel::loadBookingStartData,
                    modifier = Modifier.weight(1f)
                )
            else -> LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 16.dp),
                verticalArrangement = Arrangement.spacedBy(22.dp),
                modifier = Modifier.weight(1f).fillMaxWidth()
            ) {
                // ── Langkah 1: hewan ──
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        BookingStartSectionTitle(
                            step = "1",
                            title = "Hewan siapa yang akan diperiksa?"
                        )
                        if (state.pets.isEmpty()) {
                            Surface(
                                shape = RoundedCornerShape(16.dp),
                                color = BsSurface,
                                border = androidx.compose.foundation.BorderStroke(1.dp, BsBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Column(
                                    modifier = Modifier.padding(18.dp),
                                    horizontalAlignment = Alignment.CenterHorizontally,
                                    verticalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Text(
                                        "Belum ada hewan terdaftar",
                                        color = BsTextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold
                                    )
                                    Text(
                                        "Daftarkan hewan dulu agar booking bisa dibuat untuknya.",
                                        color = BsTextSecondary, fontSize = 12.sp, textAlign = TextAlign.Center
                                    )
                                    Spacer(Modifier.height(4.dp))
                                    Button(
                                        onClick = onNavigateToAddPet,
                                        shape = RoundedCornerShape(12.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = BsPrimary, contentColor = BsPrimaryFg
                                        )
                                    ) {
                                        Icon(Icons.Filled.Pets, null, modifier = Modifier.size(18.dp))
                                        Spacer(Modifier.width(8.dp))
                                        Text("Tambah Hewan", fontWeight = FontWeight.SemiBold)
                                    }
                                }
                            }
                        } else {
                            LazyRow(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                                items(state.pets, key = { it.id ?: it.name.orEmpty() }) { pet ->
                                    StartPetChip(
                                        pet = pet,
                                        selected = pet.id == state.selectedPetId,
                                        onClick = { pet.id?.let(viewModel::selectStartPet) }
                                    )
                                }
                            }
                        }
                    }
                }
                // ── Langkah 2: dokter (ringkas, tanpa profil panjang) ──
                item {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        BookingStartSectionTitle(
                            step = "2",
                            title = "Dokter mana yang menangani?"
                        )
                        OutlinedTextField(
                            value = state.searchQuery,
                            onValueChange = viewModel::onStartSearch,
                            placeholder = { Text("Cari nama atau spesialisasi...", color = BsTextSecondary, fontSize = 14.sp) },
                            leadingIcon = { Icon(Icons.Outlined.Search, null, tint = BsTextSecondary, modifier = Modifier.size(20.dp)) },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                            keyboardActions = KeyboardActions(onSearch = { focusManager.clearFocus() }),
                            shape = RoundedCornerShape(14.dp),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedTextColor = BsTextPrimary, unfocusedTextColor = BsTextPrimary,
                                focusedBorderColor = BsPrimary, unfocusedBorderColor = BsBorder,
                                cursorColor = BsPrimary,
                                focusedContainerColor = BsSurface, unfocusedContainerColor = BsSurface
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )
                        if (state.filteredDoctors.isEmpty()) {
                            Surface(
                                shape = RoundedCornerShape(14.dp),
                                color = BsSurface,
                                border = androidx.compose.foundation.BorderStroke(1.dp, BsBorder),
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                Text(
                                    if (state.searchQuery.isNotBlank()) "Tidak ada dokter yang cocok dengan pencarian."
                                    else "Belum ada dokter tersedia saat ini.",
                                    color = BsTextSecondary, fontSize = 13.sp,
                                    textAlign = TextAlign.Center,
                                    modifier = Modifier.padding(20.dp)
                                )
                            }
                        }
                    }
                }
                items(state.filteredDoctors, key = { it.id ?: 0 }) { doctor ->
                    StartDoctorRow(
                        doctor = doctor,
                        selected = doctor.id == state.selectedDoctorId,
                        onClick = { doctor.id?.let(viewModel::selectStartDoctor) }
                    )
                }
                item { Spacer(Modifier.height(84.dp)) }
            }
        }

        // ── Bottom bar: ringkasan + satu CTA ──
        Surface(color = BsSurface, shadowElevation = 0.dp, modifier = Modifier.navigationBarsPadding()) {
            Column {
                Divider(color = BsBorder, thickness = 0.5.dp)
                Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 14.dp)) {
                    Text(
                        when {
                            selectedPet == null -> "Pilih hewan dulu untuk lanjut"
                            selectedDoctor == null -> "Pilih dokter untuk ${selectedPet.name ?: "hewanmu"}"
                            else -> "${selectedPet.name} • ${selectedDoctor.name}"
                        },
                        color = BsTextSecondary, fontSize = 12.sp,
                        maxLines = 1, overflow = TextOverflow.Ellipsis
                    )
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = {
                            val d = state.selectedDoctorId
                            val p = state.selectedPetId
                            if (d != null && p != null) onContinue(d, p)
                        },
                        enabled = state.canContinue,
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = BsPrimary, contentColor = BsPrimaryFg,
                            disabledContainerColor = BsBorder, disabledContentColor = BsTextSecondary
                        ),
                        modifier = Modifier.fillMaxWidth().height(52.dp)
                    ) {
                        Icon(Icons.Outlined.CalendarMonth, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Lanjutkan", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}

@Composable
private fun BookingStartSteps(currentStep: Int) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        BookingStartStepPill(number = "1", label = "Hewan", active = currentStep >= 1, done = currentStep > 1)
        Box(modifier = Modifier.weight(1f).height(1.dp).background(BsBorder))
        BookingStartStepPill(number = "2", label = "Dokter", active = currentStep >= 2, done = false)
        Box(modifier = Modifier.weight(1f).height(1.dp).background(BsBorder))
        BookingStartStepPill(number = "3", label = "Jadwal", active = false, done = false)
    }
}

@Composable
private fun BookingStartStepPill(number: String, label: String, active: Boolean, done: Boolean) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Box(
            modifier = Modifier.size(22.dp).clip(CircleShape)
                .background(if (active || done) BsPrimary else BsBorder),
            contentAlignment = Alignment.Center
        ) {
            if (done) {
                Icon(Icons.Filled.CheckCircle, null, tint = BsPrimaryFg, modifier = Modifier.size(14.dp))
            } else {
                Text(
                    number,
                    color = if (active) BsPrimaryFg else BsTextSecondary,
                    fontSize = 11.sp, fontWeight = FontWeight.Bold
                )
            }
        }
        Text(
            label,
            color = if (active) BsTextPrimary else BsTextSecondary,
            fontSize = 12.sp, fontWeight = if (active) FontWeight.SemiBold else FontWeight.Normal
        )
    }
}

@Composable
private fun BookingStartSectionTitle(step: String, title: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(
            modifier = Modifier.size(24.dp).clip(CircleShape).background(BsPrimary.copy(alpha = 0.14f)),
            contentAlignment = Alignment.Center
        ) {
            Text(step, color = BsPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        }
        Text(title, color = BsTextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun StartPetChip(pet: Pet, selected: Boolean, onClick: () -> Unit) {
    Surface(
        shape = CircleShape,
        color = if (selected) BsPrimary else BsSurface,
        border = if (selected) null else androidx.compose.foundation.BorderStroke(1.dp, BsBorder),
        modifier = Modifier.clip(CircleShape).clickable(onClick = onClick)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Box(
                modifier = Modifier.size(32.dp).clip(CircleShape)
                    .background(if (selected) BsPrimaryFg.copy(alpha = 0.15f) else BsBg),
                contentAlignment = Alignment.Center
            ) {
                val photo = remember(pet.photo) { buildPhotoUrl(pet.photo) }
                if (!photo.isNullOrBlank()) {
                    ThumbnailImage(
                        model = photo,
                        contentDescription = pet.name,
                        modifier = Modifier.fillMaxSize().clip(CircleShape)
                    )
                } else {
                    Icon(
                        Icons.Filled.Pets, null,
                        tint = if (selected) BsPrimaryFg else BsTextSecondary,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
            Column {
                Text(
                    pet.name ?: "—",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (selected) BsPrimaryFg else BsTextPrimary
                )
                Text(
                    pet.species ?: "",
                    fontSize = 11.sp,
                    color = if (selected) BsPrimaryFg.copy(alpha = 0.8f) else BsTextSecondary
                )
            }
            if (selected) {
                Icon(Icons.Filled.CheckCircle, null, tint = BsPrimaryFg, modifier = Modifier.size(18.dp))
            }
        }
    }
}

@Composable
private fun StartDoctorRow(doctor: Doctor, selected: Boolean, onClick: () -> Unit) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier.fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(if (selected) BsPrimary.copy(alpha = 0.08f) else BsSurface)
            .border(
                1.dp,
                if (selected) BsPrimary else BsBorder,
                RoundedCornerShape(16.dp)
            )
            .clickable(onClick = onClick)
            .padding(12.dp)
    ) {
        Box(
            modifier = Modifier.size(48.dp).clip(CircleShape).background(BsBg),
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
                Icon(Icons.Filled.Person, null, tint = BsTextSecondary, modifier = Modifier.size(24.dp))
            }
        }
        Spacer(Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                doctor.name ?: "Tidak diketahui",
                color = BsTextPrimary, fontSize = 14.sp, fontWeight = FontWeight.SemiBold,
                maxLines = 1, overflow = TextOverflow.Ellipsis
            )
            Text(
                doctor.specialization ?: "Dokter Hewan",
                color = BsPrimary, fontSize = 12.sp, fontWeight = FontWeight.Medium,
                maxLines = 1, overflow = TextOverflow.Ellipsis
            )
            Spacer(Modifier.height(2.dp))
            val avg = doctor.averageRating
            val startReviewTotal = doctor.reviewsCount ?: 0
            if (avg != null && avg > 0) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Star, null, tint = Color(0xFFFFC857), modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        "%.1f".format(avg),
                        color = BsTextPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold
                    )
                    if (startReviewTotal > 0) {
                        Text(
                            " · ${bsReviewLabel(startReviewTotal)}",
                            color = BsTextSecondary, fontSize = 11.sp
                        )
                    }
                }
            } else {
                Text("Belum ada ulasan", color = BsTextSecondary, fontSize = 11.sp)
            }
        }
        Spacer(Modifier.width(8.dp))
        if (selected) {
            Icon(Icons.Filled.CheckCircle, null, tint = BsPrimary, modifier = Modifier.size(22.dp))
        } else {
            Icon(Icons.Filled.ChevronRight, null, tint = BsTextSecondary, modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun BookingStartLoading(modifier: Modifier = Modifier) {
    Box(modifier = modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
            CircularProgressIndicator(color = BsPrimary)
            Text("Menyiapkan pilihan booking...", color = BsTextSecondary, fontSize = 13.sp)
        }
    }
}

@Composable
private fun BookingStartError(message: String, onRetry: () -> Unit, modifier: Modifier = Modifier) {
    Box(modifier = modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier.padding(32.dp)
        ) {
            Icon(Icons.Filled.MedicalServices, null, tint = BsTextSecondary, modifier = Modifier.size(48.dp))
            Text("Tidak bisa memuat pilihan", color = BsTextPrimary, fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
            Text(message, color = BsTextSecondary, fontSize = 13.sp, textAlign = TextAlign.Center)
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = onRetry,
                shape = RoundedCornerShape(50),
                border = androidx.compose.foundation.BorderStroke(1.dp, BsPrimary)
            ) {
                Icon(Icons.Filled.Refresh, null, tint = BsPrimary, modifier = Modifier.size(16.dp))
                Spacer(Modifier.width(6.dp))
                Text("Coba Lagi", color = BsPrimary, fontWeight = FontWeight.SemiBold)
            }
        }
    }
}
