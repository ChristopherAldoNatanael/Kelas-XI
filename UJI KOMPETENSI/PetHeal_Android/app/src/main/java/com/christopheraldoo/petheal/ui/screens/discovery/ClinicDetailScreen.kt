package com.christopheraldoo.petheal.ui.screens.discovery

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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MedicalServices
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.compose.runtime.collectAsState
import com.christopheraldoo.petheal.data.model.ClinicDetail
import com.christopheraldoo.petheal.data.model.ClinicServiceDto
import com.christopheraldoo.petheal.ui.components.ClinicAvatar
import com.christopheraldoo.petheal.ui.components.Dimens
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.PetHealTopAppBar
import com.christopheraldoo.petheal.ui.components.PrimaryButton
import com.christopheraldoo.petheal.ui.components.SkeletonCard
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.DistanceFormatter

/**
 * Detail klinik: identitas, jarak, rating, jam operasional, dan katalog
 * layanan beserta harga nyata dari database klinik tersebut.
 *
 * Data berasal dari `GET /api/public/clinics/{slug}`; tidak ada nilai
 * hardcoded. Harga kosong ditampilkan "Harga belum tersedia".
 */
@Composable
fun ClinicDetailScreen(
    onNavigateBack: () -> Unit,
    onCompareService: (serviceId: Int) -> Unit,
    onBookAtClinic: (clinicSlug: String, serviceId: Int?) -> Unit = { _, _ -> },
    viewModel: ClinicDetailViewModel = hiltViewModel()
) {
    val state by viewModel.uiState.collectAsState()

    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        PetHealTopAppBar(
            title = state.clinic?.name ?: "Detail Klinik",
            onNavigateBack = onNavigateBack
        )

        when {
            state.isLoading -> Column(
                modifier = Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) { repeat(3) { SkeletonCard() } }

            state.error != null -> ErrorState(
                title = "Detail klinik gagal dimuat",
                message = state.error ?: "",
                primaryActionText = "Coba lagi",
                onPrimaryActionClick = viewModel::load
            )

            state.clinic == null -> EmptyState(
                title = "Klinik tidak ditemukan",
                description = "Klinik mungkin sudah tidak aktif. Coba cari klinik lain.",
                icon = Icons.Filled.LocationOn
            )

            else -> ClinicDetailContent(
                clinic = state.clinic!!,
                state = state,
                onCompareService = onCompareService,
                onBookAtClinic = onBookAtClinic
            )
        }
    }
}

@Composable
private fun ClinicDetailContent(
    clinic: ClinicDetail,
    state: ClinicDetailUiState,
    onCompareService: (Int) -> Unit,
    onBookAtClinic: (String, Int?) -> Unit
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(bottom = 32.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Surface(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = Dimens.ScreenPaddingHorizontal),
                shape = RoundedCornerShape(PetHealRadius.xl),
                color = SurfaceColor,
                border = BorderStroke(1.dp, Outline)
            ) {
                Column(Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        ClinicAvatar(
                            clinic = com.christopheraldoo.petheal.data.model.ClinicCard(
                                name = clinic.name,
                                logoUrl = clinic.logoUrl
                            ),
                            size = 64
                        )
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text(
                                clinic.name.orEmpty(),
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                maxLines = 2,
                                overflow = TextOverflow.Ellipsis
                            )
                            Text(
                                buildString {
                                    val rating = clinic.rating
                                    if (rating != null && rating > 0) {
                                        append("★ ")
                                        append(String.format(java.util.Locale.US, "%.1f", rating))
                                        append(" (${clinic.reviewsCount ?: 0} ulasan)")
                                    } else {
                                        append("Belum ada rating")
                                    }
                                },
                                fontSize = 12.sp,
                                color = TextSecondary
                            )
                        }
                    }

                    Spacer(Modifier.height(12.dp))
                    InfoLine(
                        icon = Icons.Filled.LocationOn,
                        text = buildString {
                            append(clinic.address.orEmpty())
                            val distance = clinic.distanceText
                                ?: DistanceFormatter.format(clinic.distanceKm).ifBlank { null }
                            if (distance != null) {
                                append(" • ")
                                append(distance)
                            }
                        }
                    )
                    if (!clinic.phone.isNullOrBlank()) {
                        Spacer(Modifier.height(6.dp))
                        InfoLine(icon = Icons.Filled.Phone, text = clinic.phone!!)
                    }
                    Spacer(Modifier.height(6.dp))
                    InfoLine(
                        icon = Icons.Filled.AccessTime,
                        text = operatingText(clinic)
                    )

                    if (!clinic.description.isNullOrBlank()) {
                        Spacer(Modifier.height(10.dp))
                        Text(clinic.description!!, fontSize = 12.5.sp, lineHeight = 18.sp, color = TextSecondary)
                    }
                }
            }
        }

        // Booking memakai klinik yang tersimpan (header X-Clinic-Slug), bukan
        // klinik yang sedang dibuka. Bila keduanya berbeda, katakan terus
        // terang sebelum user menekan tombol booking.
        if (state.isDifferentClinic) {
            item {
                ActiveClinicWarning(
                    activeClinicName = state.activeClinicName,
                    hasActiveClinic = state.hasActiveClinic
                )
            }
        }

        item {
            PrimaryButton(
                text = "Pilih dokter & jadwal",
                onClick = {
                    clinic.slug?.let { onBookAtClinic(it, null) }
                },
                leadingIcon = Icons.Filled.CalendarMonth,
                enabled = !clinic.slug.isNullOrBlank(),
                modifier = Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)
            )
        }

        item {
            Row(
                modifier = Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                StatChip("${clinic.servicesCount ?: 0} layanan", Modifier.weight(1f))
                StatChip("${clinic.doctorsCount ?: 0} dokter", Modifier.weight(1f))
                StatChip(
                    when (clinic.operatingStatus) {
                        "open" -> "Buka"
                        "closed" -> "Tutup"
                        else -> "Jam belum diisi"
                    },
                    Modifier.weight(1f)
                )
            }
        }

        item {
            Text(
                "Layanan & harga",
                fontSize = 15.sp,
                fontWeight = FontWeight.Bold,
                modifier = Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal, vertical = 4.dp)
            )
        }

        val services = clinic.services.orEmpty()
        if (services.isEmpty()) {
            item {
                EmptyState(
                    title = "Belum ada layanan",
                    description = "Klinik ini belum menambahkan layanan ke katalog.",
                    icon = Icons.Filled.MedicalServices
                )
            }
        } else {
            items(services, key = { it.id ?: 0 }) { service ->
                Box(Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)) {
                    ServicePriceRow(
                        service = service,
                        onCompare = onCompareService,
                        onBook = { selectedId ->
                            clinic.slug?.let { onBookAtClinic(it, selectedId) }
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun InfoLine(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(verticalAlignment = Alignment.Top) {
        Icon(icon, contentDescription = null, tint = TextTertiary, modifier = Modifier.size(15.dp))
        Spacer(Modifier.width(6.dp))
        Text(text, fontSize = 12.sp, color = TextSecondary, lineHeight = 17.sp)
    }
}

@Composable
private fun StatChip(text: String, modifier: Modifier = Modifier) {
    Surface(
        modifier = modifier,
        shape = RoundedCornerShape(PetHealRadius.md),
        color = SurfaceVariant,
        border = BorderStroke(1.dp, Outline)
    ) {
        Text(
            text = text,
            fontSize = 11.5.sp,
            fontWeight = FontWeight.SemiBold,
            color = TextSecondary,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 10.dp)
        )
    }
}

/**
 * Baris layanan + harga. Tombol "Bandingkan" hanya aktif bila layanan
 * punya kategori standar (tanpa itu backend tidak bisa membandingkan
 * secara setara).
 */
@Composable
private fun ServicePriceRow(
    service: ClinicServiceDto,
    onCompare: (Int) -> Unit,
    onBook: (Int?) -> Unit
) {
    val comparable = service.serviceCategory?.id != null && service.id != null
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = SurfaceColor,
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(
                        service.name.orEmpty(),
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        buildString {
                            append(service.serviceCategory?.name ?: "Tanpa kategori")
                            val duration = service.durationMinutes
                            if (duration != null && duration > 0) {
                                append(" • $duration menit")
                            }
                        },
                        fontSize = 11.sp,
                        color = TextTertiary
                    )
                }
                Text(
                    text = DistanceFormatter.price(service.price, service.priceFormatted),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (service.price == null) TextTertiary else Primary
                )
            }

            // Dua aksi dengan bobot berbeda: booking adalah aksi utama,
            // perbandingan adalah aksi sekunder. Sebelumnya hanya ada
            // "bandingkan" sehingga layar ini terasa buntu.
            Spacer(Modifier.height(8.dp))
            Divider(color = Outline, thickness = 0.5.dp)
            Spacer(Modifier.height(8.dp))
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                if (comparable) {
                    Text(
                        text = "Bandingkan",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = TextSecondary,
                        modifier = Modifier
                            .clip(RoundedCornerShape(PetHealRadius.pill))
                            .clickable { onCompare(service.id!!) }
                            .padding(horizontal = 10.dp, vertical = 6.dp)
                    )
                }
                Spacer(Modifier.weight(1f))
                Surface(
                    onClick = { onBook(service.id) },
                    shape = RoundedCornerShape(PetHealRadius.pill),
                    color = Primary
                ) {
                    Text(
                        text = "Booking",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimary,
                        modifier = Modifier.padding(horizontal = 16.dp, vertical = 7.dp)
                    )
                }
            }
        }
    }
}

/**
 * Peringatan jujur: booking akan memakai klinik aktif, bukan klinik yang
 * sedang dilihat. Tidak mengubah logika apa pun — hanya memberi tahu.
 */
@Composable
private fun ActiveClinicWarning(
    activeClinicName: String?,
    hasActiveClinic: Boolean
) {
    // MULTI-CLINIC: menekan tombol booking di halaman ini SELECTS klinik ini
    // (diteruskan ke langkah 2 alur booking), jadi peringatannya bukan lagi
    // "transaksi akan masuk ke klinik lain" — melainkan "klinik favoritmu
    // akan ikut berganti". Satu kalimat, tanpa menyembunyikan konsekuensinya.
    val title = if (hasActiveClinic) {
        "Booking akan di klinik ini, bukan ${activeClinicName ?: "klinik favoritmu"}"
    } else {
        "Booking akan di klinik ini"
    }
    val message = if (hasActiveClinic) {
        "Klinik favorit untuk booking berikutnya ikut diganti ke klinik ini. Bisa diganti lagi saat booking."
    } else {
        "Belum ada klinik favorit. Setelah memilih dokter di sini, klinik ini menjadi favoritmu."
    }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = Dimens.ScreenPaddingHorizontal)
            .clip(RoundedCornerShape(PetHealRadius.md))
            .background(WarningSoft)
            .padding(12.dp),
        verticalAlignment = Alignment.Top
    ) {
        Icon(
            Icons.Filled.Info,
            contentDescription = null,
            tint = WarningDeep,
            modifier = Modifier.size(16.dp)
        )
        Spacer(Modifier.width(8.dp))
        Column {
            Text(
                text = title,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Bold,
                color = WarningText,
                lineHeight = 17.sp
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = message,
                fontSize = 11.5.sp,
                color = WarningText,
                lineHeight = 16.sp
            )
        }
    }
}

private fun operatingText(clinic: ClinicDetail): String {
    val open = clinic.openingTime?.take(5)
    val close = clinic.closingTime?.take(5)
    if (open == null && close == null) return "Jam operasional belum diisi"
    return "Buka ${open ?: "-"} - ${close ?: "-"}"
}
