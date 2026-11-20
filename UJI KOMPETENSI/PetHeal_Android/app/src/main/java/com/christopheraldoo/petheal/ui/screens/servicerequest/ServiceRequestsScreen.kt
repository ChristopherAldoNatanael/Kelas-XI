package com.christopheraldoo.petheal.ui.screens.servicerequest

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Pets
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.SupportAgent
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.ServiceRequest
import com.christopheraldoo.petheal.data.model.ServiceRequestOffer
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.Error
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.speciesLabelOf
import com.christopheraldoo.petheal.ui.theme.SuccessDeep
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private val SrPrimary = Primary
private val SrBg = Background
private val SrSurface = Surface
private val SrTextPrimary = OnBackground
private val SrTextSecondary = TextSecondary

private val idLocale = Locale("id", "ID")
private val shortDate = DateTimeFormatter.ofPattern("d MMM", idLocale)
private val weekdayShort = DateTimeFormatter.ofPattern("EEE", idLocale)
private val longDate = DateTimeFormatter.ofPattern("d MMMM yyyy", idLocale)

/** Format jam 24 jam yang sama dengan aturan validasi backend. */
private val TIME_PATTERN = Regex("^([01]\\d|2[0-3]):[0-5]\\d$")

/**
 * Permintaan layanan: daftar, detail penawaran, dan form pengajuan.
 *
 * Satu layar untuk tiga keadaan (daftar → detail → form) supaya pemilik tidak
 * kehilangan konteks saat menerima penawaran dari notifikasi. Tidak ada
 * tombol yang membuat booking sebelum penawaran disetujui.
 */
@Composable
fun ServiceRequestsScreen(
    onNavigateBack: () -> Unit,
    openId: Int? = null,
    petId: Int? = null,
    clinicSlug: String? = null,
    medicalRecordId: Int? = null,
    onNavigateToBooking: (Int) -> Unit = {},
    viewModel: ServiceRequestsViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.init(openId, petId, clinicSlug, medicalRecordId)
    }

    val onBack = {
        when {
            state.showForm -> viewModel.closeForm()
            state.detail != null -> viewModel.closeDetail()
            else -> onNavigateBack()
        }
    }

    Column(Modifier.fillMaxSize().background(SrBg)) {
        Surface(color = SrSurface) {
            Column {
                Row(
                    Modifier.fillMaxWidth().statusBarsPadding().padding(start = 8.dp, end = 16.dp, top = 8.dp, bottom = 8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = onBack) {
                        Icon(Icons.Filled.ArrowBack, contentDescription = "Kembali", tint = SrTextPrimary)
                    }
                    Column(Modifier.weight(1f)) {
                        Text(
                            when {
                                state.showForm -> "Ajukan Layanan Lain"
                                state.detail != null -> "Detail Permintaan"
                                else -> "Permintaan Layanan"
                            },
                            color = SrTextPrimary,
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            if (state.showForm) "Klinik akan meninjau kebutuhan Anda"
                            else "Kontrol & layanan khusus",
                            color = SrTextSecondary,
                            fontSize = 12.sp
                        )
                    }
                    if (!state.showForm && state.detail == null) {
                        TextButton(onClick = { viewModel.openForm() }) {
                            Text("Ajukan", color = SrPrimary, fontWeight = FontWeight.Bold)
                        }
                    }
                }
                Divider(color = Outline, thickness = 0.5.dp)
            }
        }

        state.submitMessage?.let { message ->
            Banner(message = message, tone = Tone.SUCCESS, onDismiss = viewModel::clearMessages)
        }
        state.actionMessage?.let { message ->
            Banner(message = message, tone = Tone.SUCCESS, onDismiss = viewModel::clearMessages)
        }
        state.actionError?.let { message ->
            Banner(message = message, tone = Tone.ERROR, onDismiss = viewModel::clearMessages)
        }

        when {
            state.showForm -> ServiceRequestForm(
                state = state,
                viewModel = viewModel,
                modifier = Modifier.weight(1f)
            )
            state.detail != null -> ServiceRequestDetail(
                request = state.detail!!,
                isDetailLoading = state.isDetailLoading,
                actionInProgressId = state.actionInProgressId,
                onAccept = viewModel::acceptOffer,
                onReject = viewModel::rejectOffer,
                onOpenBooking = onNavigateToBooking
            )
            else -> ServiceRequestList(
                state = state,
                onRefresh = viewModel::loadRequests,
                onDismissError = viewModel::clearError,
                onOpen = viewModel::loadDetail,
                onCreate = { viewModel.openForm() }
            )
        }
    }
}

private enum class Tone { SUCCESS, ERROR }

@Composable
private fun Banner(message: String, tone: Tone, onDismiss: (() -> Unit)? = null) {
    val (bg, fg) = when (tone) {
        Tone.SUCCESS -> SuccessSoft to SuccessDeep
        Tone.ERROR -> ErrorSoft to Error
    }
    Row(
        Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp)
            .clip(RoundedCornerShape(14.dp)).background(bg).padding(horizontal = 14.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(message, modifier = Modifier.weight(1f), color = fg, fontSize = 12.sp, lineHeight = 17.sp)
        if (onDismiss != null) {
            TextButton(onClick = onDismiss, contentPadding = PaddingValues(0.dp)) {
                Text("Tutup", color = fg, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun ServiceRequestList(
    state: ServiceRequestsUiState,
    onRefresh: () -> Unit,
    onDismissError: () -> Unit,
    onOpen: (Int) -> Unit,
    onCreate: () -> Unit
) {
    when {
        state.isLoading && state.requests.isEmpty() -> LoadingBlock("Memuat permintaan…")
        state.error != null && state.requests.isEmpty() -> ErrorBlock(state.error, onRefresh)
        state.requests.isEmpty() -> EmptyRequests(onCreate)
        else -> LazyColumn(
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Surface(
                    shape = RoundedCornerShape(18.dp),
                    color = PrimaryLight,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Row(Modifier.padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Icon(Icons.Filled.SupportAgent, null, tint = SrPrimary, modifier = Modifier.size(22.dp))
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text("Butuh layanan yang belum tersedia?", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = SrTextPrimary)
                            Text(
                                "Ajukan permintaan dan klinik akan meninjau, lalu mengirim penawaran biaya dan jadwal.",
                                fontSize = 12.sp,
                                color = SrTextSecondary,
                                lineHeight = 17.sp
                            )
                            TextButton(onClick = onCreate, contentPadding = PaddingValues(0.dp)) {
                                Text("Ajukan Layanan Lain", color = SrPrimary, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                        }
                    }
                }
            }

            // Daftar sudah tampil (mungkin dari cache); galat hanya perlu
            // ditutup, bukan memicu permintaan ulang diam-diam.
            if (state.error != null) {
                item { Banner(state.error, Tone.ERROR, onDismiss = onDismissError) }
            }

            items(state.requests, key = { it.id ?: it.hashCode() }) { request ->
                ServiceRequestCard(request) { request.id?.let(onOpen) }
            }
        }
    }
}

@Composable
private fun ServiceRequestCard(request: ServiceRequest, onClick: () -> Unit) {
    val (bg, fg) = statusColors(request.status)
    Surface(
        shape = RoundedCornerShape(18.dp),
        color = SrSurface,
        border = BorderStroke(1.dp, Outline),
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text(request.code ?: "Permintaan", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = SrTextPrimary)
                Surface(shape = RoundedCornerShape(999.dp), color = bg) {
                    Text(
                        request.statusLabel ?: request.status.orEmpty(),
                        color = fg,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 9.dp, vertical = 4.dp)
                    )
                }
            }
            Text(
                buildString {
                    append(request.pet?.name ?: "Hewan")
                    request.clinic?.name?.let { append(" · $it") }
                },
                fontSize = 13.sp,
                fontWeight = FontWeight.SemiBold,
                color = SrTextPrimary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(request.needTypeLabel ?: "", fontSize = 11.sp, color = SrTextSecondary)
            request.complaint?.takeIf { it.isNotBlank() }?.let {
                Text(it, fontSize = 12.sp, color = SrTextSecondary, maxLines = 2, overflow = TextOverflow.Ellipsis, lineHeight = 17.sp)
            }
            if (request.offer != null && request.canRespond) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.CheckCircle, null, tint = SrPrimary, modifier = Modifier.size(14.dp))
                    Text("Penawaran menunggu persetujuan Anda", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = SrPrimary)
                }
            }
        }
    }
}

@Composable
private fun EmptyRequests(onCreate: () -> Unit) {
    Column(
        Modifier.fillMaxSize().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(Modifier.size(72.dp).clip(CircleShape).background(PrimaryLight), contentAlignment = Alignment.Center) {
            Icon(Icons.Filled.Description, null, tint = SrPrimary, modifier = Modifier.size(34.dp))
        }
        Spacer(Modifier.height(14.dp))
        Text("Belum ada permintaan layanan", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = SrTextPrimary)
        Spacer(Modifier.height(6.dp))
        Text(
            "Ajukan layanan yang belum ada di katalog klinik — mis. pemeriksaan khusus atau kontrol lanjutan.",
            fontSize = 13.sp,
            color = SrTextSecondary,
            textAlign = TextAlign.Center,
            lineHeight = 18.sp
        )
        Spacer(Modifier.height(18.dp))
        Button(
            onClick = onCreate,
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.buttonColors(containerColor = SrPrimary)
        ) {
            Text("Ajukan Layanan Lain", fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun ServiceRequestDetail(
    request: ServiceRequest,
    isDetailLoading: Boolean,
    actionInProgressId: Int?,
    onAccept: (Int, String?) -> Unit,
    onReject: (Int, String?) -> Unit,
    onOpenBooking: (Int) -> Unit
) {
    var confirmAccept by remember { mutableStateOf(false) }
    var confirmReject by remember { mutableStateOf(false) }
    val busy = actionInProgressId != null

    LazyColumn(
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(request.code ?: "", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = SrTextSecondary)
                Text(
                    request.needTypeLabel ?: "Permintaan layanan",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = SrTextPrimary
                )
                Text(request.statusLabel ?: request.status.orEmpty(), fontSize = 12.sp, color = statusColors(request.status).second, fontWeight = FontWeight.SemiBold)
            }
        }

        item {
            Surface(shape = RoundedCornerShape(18.dp), color = SrSurface, border = BorderStroke(1.dp, Outline), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    InfoRow(Icons.Filled.Pets, request.pet?.name ?: "Hewan")
                    InfoRow(Icons.Filled.LocalHospital, request.clinic?.name ?: "Klinik")
                    request.preferredDate?.let { date ->
                        InfoRow(
                            Icons.Filled.Schedule,
                            "Preferensi: $date${request.preferredTime?.let { " · $it" } ?: ""}"
                        )
                    }
                    request.doctor?.name?.let { InfoRow(Icons.Filled.SupportAgent, "Dokter rujukan: $it") }
                }
            }
        }

        item {
            Surface(shape = RoundedCornerShape(18.dp), color = SrSurface, border = BorderStroke(1.dp, Outline), modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    Text("Keluhan / kebutuhan", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = SrTextSecondary)
                    Text(request.complaint ?: "-", fontSize = 14.sp, color = SrTextPrimary, lineHeight = 20.sp)
                }
            }
        }

        request.doctorRecommendation?.takeIf { it.isNotBlank() }?.let { recommendation ->
            item {
                Surface(shape = RoundedCornerShape(18.dp), color = InfoSoft, modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text("Evaluasi dokter", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = InfoStrong)
                        Text(recommendation, fontSize = 13.sp, color = SrTextPrimary, lineHeight = 19.sp)
                        if (request.needsFollowUp == true) {
                            Text("Kontrol lanjutan disarankan.", fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = WarningText)
                        }
                    }
                }
            }
        }

        request.offer?.let { offer ->
            item { OfferCard(offer) }
        }

        request.adminNotes?.takeIf { it.isNotBlank() }?.let { note ->
            item {
                Surface(shape = RoundedCornerShape(18.dp), color = WarningSoft, modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text("Catatan klinik", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = WarningText)
                        Text(note, fontSize = 13.sp, color = SrTextPrimary, lineHeight = 19.sp)
                    }
                }
            }
        }

        if (isDetailLoading) {
            item { LoadingBlock("Memuat detail…") }
        }

        if (request.canRespond && request.id != null) {
            item {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick = { confirmReject = true },
                        enabled = !busy,
                        modifier = Modifier.weight(1f).height(48.dp),
                        shape = RoundedCornerShape(14.dp),
                        border = BorderStroke(1.dp, Error)
                    ) {
                        Text("Tolak", color = Error, fontWeight = FontWeight.SemiBold)
                    }
                    Button(
                        onClick = { confirmAccept = true },
                        enabled = !busy,
                        modifier = Modifier.weight(1.4f).height(48.dp),
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = SrPrimary)
                    ) {
                        if (busy) {
                            CircularProgressIndicator(color = Color.White, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                        } else {
                            Text("Setujui Penawaran", fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        }

        if (request.status == "converted_to_booking" && request.bookingId != null) {
            item {
                Surface(shape = RoundedCornerShape(18.dp), color = SuccessSoft, modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Booking sudah dibuat", fontSize = 14.sp, fontWeight = FontWeight.Bold, color = SuccessDeep)
                        Text(
                            "Selesaikan pembayaran dari detail booking agar jadwal Anda terkonfirmasi.",
                            fontSize = 12.sp,
                            color = SrTextPrimary,
                            lineHeight = 17.sp
                        )
                        Button(
                            onClick = { onOpenBooking(request.bookingId) },
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SnackGreen)
                        ) {
                            Text("Buka Booking", fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        }
    }

    if (confirmAccept && request.id != null) {
        AlertDialog(
            onDismissRequest = { confirmAccept = false },
            title = { Text("Setujui penawaran?") },
            text = { Text("Booking akan dibuat dengan harga dan jadwal pada penawaran. Pembayaran tetap melalui alur pembayaran PetHeal.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmAccept = false
                    onAccept(request.id, null)
                }) { Text("Setujui", color = SrPrimary, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { confirmAccept = false }) { Text("Batal", color = SrTextSecondary) }
            }
        )
    }

    if (confirmReject && request.id != null) {
        AlertDialog(
            onDismissRequest = { confirmReject = false },
            title = { Text("Tolak penawaran?") },
            text = { Text("Tidak ada booking atau pembayaran yang dibuat. Riwayat permintaan tetap tersimpan.") },
            confirmButton = {
                TextButton(onClick = {
                    confirmReject = false
                    onReject(request.id, null)
                }) { Text("Tolak", color = Error, fontWeight = FontWeight.Bold) }
            },
            dismissButton = {
                TextButton(onClick = { confirmReject = false }) { Text("Batal", color = SrTextSecondary) }
            }
        )
    }
}

private val SnackGreen = SuccessDeep

@Composable
private fun OfferCard(offer: ServiceRequestOffer) {
    Surface(
        shape = RoundedCornerShape(18.dp),
        color = SrSurface,
        border = BorderStroke(1.dp, SrPrimary.copy(alpha = 0.35f)),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Penawaran Klinik", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = SrTextSecondary)
                Text(
                    if (offer.priceType == "estimate") "Estimasi biaya" else "Harga final",
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (offer.priceType == "estimate") WarningText else SuccessDeep
                )
            }
            Text(offer.serviceName ?: "Layanan", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = SrTextPrimary)
            offer.description?.takeIf { it.isNotBlank() }?.let {
                Text(it, fontSize = 12.sp, color = SrTextSecondary, lineHeight = 17.sp)
            }
            offer.price?.let { price ->
                Text(
                    "Rp ${"%,.0f".format(price).replace(',', '.')}",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = SrTextPrimary
                )
            }
            if (offer.priceType == "estimate") {
                Text(
                    "Biaya akhir dapat berubah setelah pemeriksaan dan akan dikomunikasikan sebelum tindakan tambahan.",
                    fontSize = 11.sp,
                    color = WarningText,
                    lineHeight = 16.sp
                )
            }
            offer.doctorName?.let { InfoRow(Icons.Filled.SupportAgent, it) }
            if (offer.scheduleDate != null) {
                InfoRow(Icons.Filled.Schedule, "${offer.scheduleDate} · ${offer.scheduleTime ?: "-"}")
            }
            offer.durationMinutes?.let { InfoRow(Icons.Filled.Schedule, "Perkiraan $it menit") }
            offer.validUntil?.take(10)?.let { InfoRow(Icons.Filled.Schedule, "Berlaku sampai $it") }
            offer.notes?.takeIf { it.isNotBlank() }?.let {
                Text(it, fontSize = 12.sp, color = SrTextSecondary, lineHeight = 17.sp)
            }
        }
    }
}

@Composable
private fun InfoRow(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(icon, null, tint = SrTextSecondary, modifier = Modifier.size(15.dp))
        Text(text, fontSize = 12.sp, color = SrTextPrimary, maxLines = 2, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun ServiceRequestForm(
    state: ServiceRequestsUiState,
    viewModel: ServiceRequestsViewModel,
    modifier: Modifier = Modifier
) {
    if (state.isLoadingForm) {
        Column(modifier.fillMaxWidth()) { LoadingBlock("Menyiapkan form…") }
        return
    }

    val dateOptions = remember {
        (0..6).map { offset ->
            val date = LocalDate.now().plusDays(offset.toLong())
            val label = when (offset) {
                0 -> "Hari ini"
                1 -> "Besok"
                else -> date.format(weekdayShort)
            }
            Triple(label, date.format(shortDate), date)
        }
    }

    val selectedClinicName = remember(state.clinics, state.selectedClinicId) {
        state.clinics.firstOrNull { it.id == state.selectedClinicId }?.name
    }
    val selectedPetName = remember(state.pets, state.selectedPetId) {
        state.pets.firstOrNull { it.id == state.selectedPetId }?.name
    }

    // Satu sumber petunjuk di action bar: user selalu tahu apa yang menahan
    // tombol kirim, bukan menebak dari tombol yang tampak mati.
    val timeInvalid = state.preferredTime.isNotBlank() &&
        !TIME_PATTERN.matches(state.preferredTime.trim())
    val submitHint = when {
        timeInvalid -> "Format jam belum tepat — gunakan HH:mm, mis. 09:30."
        state.selectedClinicId == null -> "Pilih klinik tujuan untuk melanjutkan."
        state.selectedPetId == null -> "Pilih hewan yang kebutuhannya diajukan."
        state.complaint.trim().length < 10 -> "Jelaskan kebutuhan Anda minimal 10 karakter."
        else -> listOfNotNull(selectedPetName, selectedClinicName).joinToString(" · ")
    }

    Column(modifier.fillMaxWidth()) {
        LazyColumn(
            modifier = Modifier.weight(1f).fillMaxWidth(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(22.dp)
        ) {
            item { FormIntro() }

            // ── 1. Klinik tujuan ─────────────────────────────────────────
            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    FormSectionTitle(
                        step = "1",
                        title = "Klinik tujuan",
                        hint = "Permintaan ditinjau oleh klinik ini."
                    )
                    if (state.clinics.isEmpty()) {
                        FormNote("Belum ada klinik yang bisa dipilih.")
                    } else {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            state.clinics.forEach { clinic ->
                                SelectRow(
                                    selected = state.selectedClinicId == clinic.id,
                                    title = clinic.name ?: "Klinik",
                                    subtitle = clinic.address,
                                    icon = Icons.Filled.LocalHospital,
                                    onClick = { clinic.id?.let(viewModel::selectClinic) }
                                )
                            }
                        }
                    }
                }
            }

            // ── 2. Hewan ─────────────────────────────────────────────────
            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    FormSectionTitle(
                        step = "2",
                        title = "Hewan",
                        hint = "Pilih hewan yang kebutuhannya diajukan."
                    )
                    if (state.pets.isEmpty()) {
                        FormNote("Belum ada hewan terdaftar. Tambahkan dulu di menu Hewan.")
                    } else {
                        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            state.pets.forEach { pet: Pet ->
                                SelectRow(
                                    selected = state.selectedPetId == pet.id,
                                    title = pet.name ?: "Hewan",
                                    subtitle = speciesLabelOf(pet.species),
                                    icon = Icons.Filled.Pets,
                                    onClick = { pet.id?.let(viewModel::selectPet) }
                                )
                            }
                        }
                    }
                }
            }

            // ── 3. Jenis kebutuhan ───────────────────────────────────────
            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    FormSectionTitle(step = "3", title = "Jenis kebutuhan")
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(SERVICE_REQUEST_NEED_TYPES) { (value, label) ->
                            SelectChip(
                                label = label,
                                selected = state.needType == value,
                                onClick = { viewModel.setNeedType(value) }
                            )
                        }
                    }
                }
            }

            // ── 4. Keluhan / kebutuhan ───────────────────────────────────
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    FormSectionTitle(
                        step = "4",
                        title = "Keluhan / kebutuhan",
                        hint = "Sebutkan kondisi hewan, sejak kapan, dan layanan yang diharapkan."
                    )
                    OutlinedTextField(
                        value = state.complaint,
                        onValueChange = viewModel::setComplaint,
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 4,
                        maxLines = 8,
                        placeholder = {
                            Text(
                                "Contoh: kucing tidak mau makan sejak 2 hari, ingin pemeriksaan lanjutan.",
                                fontSize = 12.sp,
                                lineHeight = 17.sp
                            )
                        },
                        shape = RoundedCornerShape(14.dp)
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.Top
                    ) {
                        val tooShort = state.complaint.isNotBlank() && state.complaint.trim().length < 10
                        Text(
                            text = if (tooShort) "Minimal 10 karakter." else "Deskripsi membantu klinik menyiapkan penawaran.",
                            color = if (tooShort) Error else SrTextSecondary,
                            fontSize = 11.sp,
                            lineHeight = 15.sp,
                            modifier = Modifier.weight(1f)
                        )
                        Text(
                            "${state.complaint.length}/$SERVICE_REQUEST_COMPLAINT_MAX",
                            color = TextTertiary,
                            fontSize = 11.sp,
                            modifier = Modifier.padding(start = 8.dp)
                        )
                    }
                    if (state.sourceMedicalRecordId != null) {
                        Row(
                            modifier = Modifier
                                .clip(RoundedCornerShape(10.dp))
                                .background(InfoSoft)
                                .padding(horizontal = 10.dp, vertical = 6.dp),
                            horizontalArrangement = Arrangement.spacedBy(6.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Filled.Description,
                                null,
                                tint = InfoStrong,
                                modifier = Modifier.size(13.dp)
                            )
                            Text(
                                "Terhubung dengan rekam medis #${state.sourceMedicalRecordId}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = InfoStrong
                            )
                        }
                    }
                }
            }

            // ── 5. Preferensi jadwal ─────────────────────────────────────
            item {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    FormSectionTitle(
                        step = "5",
                        title = "Preferensi jadwal",
                        hint = "Opsional — klinik tetap dapat menawarkan jadwal lain."
                    )
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        item {
                            DateChip(
                                label = "Kapan saja",
                                date = "Fleksibel",
                                selected = state.preferredDate == null,
                                onClick = { viewModel.setPreferredDate(null) }
                            )
                        }
                        items(dateOptions) { option ->
                            DateChip(
                                label = option.first,
                                date = option.second,
                                selected = state.preferredDate == option.third,
                                onClick = { viewModel.setPreferredDate(option.third) }
                            )
                        }
                    }
                    if (state.preferredDate != null) {
                        OutlinedTextField(
                            value = state.preferredTime,
                            onValueChange = viewModel::setPreferredTime,
                            modifier = Modifier.fillMaxWidth(),
                            singleLine = true,
                            isError = timeInvalid,
                            placeholder = { Text("mis. 10:30", fontSize = 12.sp) },
                            leadingIcon = {
                                Icon(Icons.Filled.Schedule, null, modifier = Modifier.size(18.dp))
                            },
                            shape = RoundedCornerShape(14.dp)
                        )
                        Text(
                            text = if (timeInvalid) "Format 24 jam (HH:mm), mis. 09:30 atau 16:00."
                            else "Kosongkan bila belum ada preferensi jam.",
                            color = if (timeInvalid) Error else SrTextSecondary,
                            fontSize = 11.sp,
                            lineHeight = 15.sp
                        )
                    }
                }
            }
        }

        SubmitBar(
            hint = submitHint,
            error = state.formError,
            submitting = state.isSubmitting,
            enabled = state.canSubmit && !timeInvalid,
            onDismissError = viewModel::clearMessages,
            onSubmit = viewModel::submit
        )
    }
}

/** Pengantar singkat: apa yang terjadi setelah permintaan dikirim. */
@Composable
private fun FormIntro() {
    Surface(shape = RoundedCornerShape(18.dp), color = PrimaryLight, modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(14.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalAlignment = Alignment.Top
        ) {
            Box(
                modifier = Modifier.size(36.dp).clip(CircleShape).background(SrSurface),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.SupportAgent, null, tint = SrPrimary, modifier = Modifier.size(19.dp))
            }
            Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(
                    "Bukan booking langsung",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = SrTextPrimary
                )
                Text(
                    "Klinik meninjau permintaan ini, lalu mengirim penawaran biaya dan jadwal untuk Anda setujui.",
                    fontSize = 12.sp,
                    color = SrTextSecondary,
                    lineHeight = 17.sp
                )
            }
        }
    }
}

@Composable
private fun FormSectionTitle(step: String, title: String, hint: String? = null) {
    Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(9.dp)
        ) {
            Box(
                modifier = Modifier.size(22.dp).clip(CircleShape).background(SrPrimary.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Text(step, color = SrPrimary, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
            Text(title, color = SrTextPrimary, fontSize = 15.sp, fontWeight = FontWeight.Bold)
        }
        hint?.let {
            Text(
                it,
                color = SrTextSecondary,
                fontSize = 11.sp,
                lineHeight = 15.sp,
                modifier = Modifier.padding(start = 31.dp)
            )
        }
    }
}

/** Catatan kecil untuk keadaan kosong (belum ada klinik / hewan). */
@Composable
private fun FormNote(text: String) {
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = WarningSoft,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(Icons.Filled.ErrorOutline, null, tint = WarningText, modifier = Modifier.size(15.dp))
            Text(text, color = WarningText, fontSize = 11.sp, lineHeight = 15.sp)
        }
    }
}

/**
 * Action bar tetap di bawah: satu CTA, selalu di atas navigasi sistem dan
 * keyboard, jadi tombol kirim tidak pernah tertutup gesture bar maupun IME.
 */
@Composable
private fun SubmitBar(
    hint: String,
    error: String?,
    submitting: Boolean,
    enabled: Boolean,
    onDismissError: () -> Unit,
    onSubmit: () -> Unit
) {
    Surface(color = SrSurface) {
        Column {
            Divider(color = Outline, thickness = 0.5.dp)
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .imePadding()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                error?.let { message ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(ErrorSoft)
                            .padding(start = 12.dp, end = 4.dp, top = 8.dp, bottom = 8.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Filled.ErrorOutline, null, tint = Error, modifier = Modifier.size(16.dp))
                        Text(
                            message,
                            modifier = Modifier.weight(1f),
                            color = Error,
                            fontSize = 11.sp,
                            lineHeight = 15.sp
                        )
                        TextButton(
                            onClick = onDismissError,
                            contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text("Tutup", color = Error, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
                Text(
                    hint,
                    color = SrTextSecondary,
                    fontSize = 12.sp,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Button(
                    onClick = onSubmit,
                    enabled = enabled,
                    modifier = Modifier.fillMaxWidth().height(52.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = SrPrimary,
                        contentColor = Color.White,
                        // Saat mengirim, tombol memang nonaktif tetapi harus tetap
                        // terlihat sebagai aksi utama, bukan tombol mati kelabu.
                        disabledContainerColor = if (submitting) SrPrimary.copy(alpha = 0.75f) else Outline,
                        disabledContentColor = if (submitting) Color.White else SrTextSecondary
                    )
                ) {
                    if (submitting) {
                        CircularProgressIndicator(
                            color = Color.White,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(18.dp)
                        )
                    } else {
                        Icon(Icons.Filled.Send, null, modifier = Modifier.size(17.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Kirim Permintaan", fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                    }
                }
            }
        }
    }
}

@Composable
private fun SelectRow(
    selected: Boolean,
    title: String,
    subtitle: String?,
    icon: androidx.compose.ui.graphics.vector.ImageVector? = null,
    onClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(16.dp),
        color = if (selected) PrimaryLight else SrSurface,
        border = BorderStroke(1.dp, if (selected) SrPrimary else Outline),
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).clickable(onClick = onClick)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            if (icon != null) {
                Box(
                    modifier = Modifier.size(34.dp).clip(CircleShape)
                        .background(if (selected) SrSurface else SrBg),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        icon,
                        null,
                        tint = if (selected) SrPrimary else SrTextSecondary,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(1.dp)) {
                Text(
                    title,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = SrTextPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                subtitle?.takeIf { it.isNotBlank() }?.let {
                    Text(
                        it,
                        fontSize = 11.sp,
                        color = SrTextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
            if (selected) {
                Icon(Icons.Filled.CheckCircle, null, tint = SrPrimary, modifier = Modifier.size(20.dp))
            }
        }
    }
}

@Composable
private fun SelectChip(label: String, selected: Boolean, onClick: () -> Unit) {
    Surface(
        shape = RoundedCornerShape(999.dp),
        color = if (selected) SrPrimary else SrSurface,
        border = BorderStroke(1.dp, if (selected) SrPrimary else Outline),
        modifier = Modifier.clip(RoundedCornerShape(999.dp)).clickable(onClick = onClick)
    ) {
        Text(
            label,
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = if (selected) Color.White else SrTextPrimary,
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 9.dp)
        )
    }
}

@Composable
private fun DateChip(label: String, date: String, selected: Boolean, onClick: () -> Unit) {
    Surface(
        shape = RoundedCornerShape(14.dp),
        color = if (selected) SrPrimary else SrSurface,
        border = BorderStroke(1.dp, if (selected) SrPrimary else Outline),
        modifier = Modifier
            .widthIn(min = 78.dp)
            .clip(RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
    ) {
        Column(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(1.dp)
        ) {
            Text(
                label,
                fontSize = 10.sp,
                fontWeight = FontWeight.Medium,
                color = if (selected) Color.White.copy(alpha = 0.9f) else SrTextSecondary,
                maxLines = 1
            )
            Text(
                date,
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                color = if (selected) Color.White else SrTextPrimary,
                maxLines = 1
            )
        }
    }
}

@Composable
private fun LoadingBlock(message: String) {
    Column(
        Modifier.fillMaxWidth().padding(top = 64.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        CircularProgressIndicator(color = SrPrimary, strokeWidth = 2.5.dp, modifier = Modifier.size(30.dp))
        Text(message, fontSize = 13.sp, color = SrTextSecondary)
    }
}

@Composable
private fun ErrorBlock(message: String, onRetry: () -> Unit) {
    Column(
        Modifier.fillMaxWidth().padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Icon(Icons.Filled.ErrorOutline, null, tint = Error, modifier = Modifier.size(34.dp))
        Text(message, fontSize = 13.sp, color = SrTextSecondary, textAlign = TextAlign.Center)
        OutlinedButton(onClick = onRetry, shape = RoundedCornerShape(12.dp)) {
            Icon(Icons.Filled.Refresh, null, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text("Coba lagi")
        }
    }
}

private fun statusColors(status: String?): Pair<Color, Color> = when (status) {
    "offered" -> PrimaryLight to SrPrimary
    "accepted", "converted_to_booking" -> SuccessSoft to Success
    "rejected", "cancelled", "expired" -> ErrorSoft to Error
    "waiting_doctor_review" -> WarningSoft to WarningText
    else -> InfoSoft to InfoStrong
}
