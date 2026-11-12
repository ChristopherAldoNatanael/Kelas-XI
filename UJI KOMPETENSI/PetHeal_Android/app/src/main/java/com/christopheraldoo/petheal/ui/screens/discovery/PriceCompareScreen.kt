package com.christopheraldoo.petheal.ui.screens.discovery

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CompareArrows
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.SearchOff
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.material3.Divider
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.compose.runtime.collectAsState
import com.christopheraldoo.petheal.data.model.CompareItem
import com.christopheraldoo.petheal.data.model.ServiceCategoryDto
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository.CompareSort
import com.christopheraldoo.petheal.ui.components.Dimens
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.PetHealTopAppBar
import com.christopheraldoo.petheal.ui.components.SkeletonCard
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Star
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.DistanceFormatter

/**
 * Perbandingan harga layanan setara antar klinik.
 *
 * Ringkasan atas (harga terendah) berasal dari `meta` backend — dihitung dari
 * baris yang sama, bukan angka karangan. Baris tanpa harga tetap ditampilkan
 * dengan label "Harga belum tersedia".
 */
@Composable
fun PriceCompareScreen(
    onNavigateBack: () -> Unit,
    onOpenClinic: (slug: String) -> Unit,
    onBookService: (clinicSlug: String, serviceId: Int) -> Unit,
    viewModel: PriceCompareViewModel = hiltViewModel()
) {
    val state by viewModel.uiState.collectAsState()

    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        PetHealTopAppBar(title = "Bandingkan Harga", onNavigateBack = onNavigateBack)

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            item {
                Column(Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)) {
                    Text(
                        "Pilih kategori layanan",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = TextSecondary
                    )
                    Spacer(Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        val categories: List<ServiceCategoryDto> = state.categories
                        categories.forEach { category ->
                            val slug: String = category.slug.orEmpty()
                            val selected = state.selectedCategorySlug == slug
                            Surface(
                                onClick = { viewModel.selectCategory(slug) },
                                shape = RoundedCornerShape(PetHealRadius.pill),
                                color = if (selected) Primary else SurfaceColor,
                                border = BorderStroke(1.dp, if (selected) Primary else Outline)
                            ) {
                                Text(
                                    text = category.name.orEmpty(),
                                    fontSize = 12.sp,
                                    fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                                    color = if (selected) MaterialTheme.colorScheme.onPrimary else TextSecondary,
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp)
                                )
                            }
                        }
                    }
                }
            }

            item {
                Row(
                    modifier = Modifier
                        .horizontalScroll(rememberScrollState())
                        .padding(horizontal = Dimens.ScreenPaddingHorizontal),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    listOf(
                        CompareSort.CHEAPEST to "Termurah",
                        CompareSort.NEAREST to "Terdekat",
                        CompareSort.TOP_RATED to "Rating"
                    ).forEach { (sort, label) ->
                        val selected = state.sort == sort
                        Surface(
                            onClick = { viewModel.setSort(sort) },
                            shape = RoundedCornerShape(PetHealRadius.pill),
                            color = if (selected) Primary.copy(alpha = 0.10f) else SurfaceVariant,
                            border = BorderStroke(1.dp, if (selected) Primary else Outline)
                        ) {
                            Text(
                                text = label,
                                fontSize = 11.5.sp,
                                fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                                color = if (selected) Primary else TextSecondary,
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                            )
                        }
                    }
                }
            }

            // Ringkasan harga nyata dari respons yang sama.
            val lowest: String? = state.meta?.lowestPriceFormatted
            if (lowest != null) {
                item {
                    Surface(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = Dimens.ScreenPaddingHorizontal),
                        shape = RoundedCornerShape(PetHealRadius.md),
                        color = SurfaceVariant,
                        border = BorderStroke(1.dp, Outline)
                    ) {
                        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text("Harga terendah", fontSize = 11.sp, color = TextTertiary)
                                Text(lowest, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Primary)
                            }
                            Text(
                                text = "${state.meta?.total ?: 0} klinik",
                                fontSize = 11.sp,
                                color = TextSecondary
                            )
                        }
                    }
                }
            }

            when {
                !state.hasSelection -> item {
                    EmptyState(
                        title = "Pilih kategori layanan",
                        description = "Perbandingan hanya dilakukan untuk layanan dengan kategori yang sama.",
                        icon = Icons.Filled.CompareArrows
                    )
                }

                state.isLoading -> items(3) { SkeletonCard() }

                state.error != null -> item {
                    ErrorState(
                        title = "Perbandingan gagal dimuat",
                        message = state.error ?: "",
                        primaryActionText = "Coba lagi",
                        onPrimaryActionClick = viewModel::retry
                    )
                }

                state.items.isEmpty() -> item {
                    EmptyState(
                        title = "Belum ada layanan sebanding",
                        description = "Belum ada klinik yang mencantumkan layanan berkategori ini.",
                        icon = Icons.Filled.SearchOff
                    )
                }

                else -> itemsIndexed(
                    items = state.items,
                    key = { _, row -> row.key }
                ) { _, row ->
                    Box(Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)) {
                        CompareRow(
                            item = row,
                            isActiveClinic = state.isActiveClinic(row.clinic?.slug),
                            activeClinicName = state.activeClinicName,
                            onOpenClinic = onOpenClinic,
                            onBook = onBookService
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun CompareRow(
    item: CompareItem,
    isActiveClinic: Boolean,
    activeClinicName: String?,
    onOpenClinic: (String) -> Unit,
    onBook: (String, Int) -> Unit
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = SurfaceColor,
        border = BorderStroke(
            width = if (isActiveClinic) 1.5.dp else 1.dp,
            color = if (isActiveClinic) Primary else Outline
        )
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            item.clinic?.name.orEmpty(),
                            fontSize = 13.5.sp,
                            fontWeight = FontWeight.Bold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f, fill = false)
                        )
                        if (isActiveClinic) {
                            Spacer(Modifier.width(6.dp))
                            // MULTI-CLINIC: bukan "klinik Anda" (pasien tidak
                            // terikat satu klinik) — ini klinik yang terakhir
                            // dipakai untuk booking.
                            Text(
                                text = "Terakhir dipakai",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = PrimaryDark,
                                modifier = Modifier
                                    .clip(RoundedCornerShape(PetHealRadius.pill))
                                    .background(PrimaryLight)
                                    .padding(horizontal = 7.dp, vertical = 2.dp)
                            )
                        }
                    }
                    Text(
                        item.serviceName.orEmpty(),
                        fontSize = 11.5.sp,
                        color = TextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        val rating = item.clinic?.rating
                        if (rating != null && rating > 0) {
                            androidx.compose.material3.Icon(
                                Icons.Filled.Star,
                                contentDescription = null,
                                tint = Star,
                                modifier = Modifier.size(12.dp)
                            )
                            Spacer(Modifier.width(2.dp))
                            Text(
                                String.format(java.util.Locale.US, "%.1f", rating),
                                fontSize = 11.sp,
                                color = TextSecondary
                            )
                        }
                        val distance = item.distanceText ?: DistanceFormatter.format(item.distanceKm).ifBlank { null }
                        if (distance != null) {
                            Spacer(Modifier.width(8.dp))
                            androidx.compose.material3.Icon(
                                Icons.Filled.LocationOn,
                                contentDescription = null,
                                tint = TextTertiary,
                                modifier = Modifier.size(12.dp)
                            )
                            Spacer(Modifier.width(1.dp))
                            Text(distance, fontSize = 11.sp, color = TextTertiary)
                        }
                    }
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = if (item.hasPrice) {
                            DistanceFormatter.price(item.price, item.priceFormatted)
                        } else {
                            "Harga belum tersedia"
                        },
                        fontSize = if (item.hasPrice) 15.sp else 11.5.sp,
                        fontWeight = if (item.hasPrice) FontWeight.Bold else FontWeight.Normal,
                        color = if (item.hasPrice) Primary else TextTertiary
                    )
                    if ((item.durationMinutes ?: 0) > 0) {
                        Text("${item.durationMinutes} menit", fontSize = 10.5.sp, color = TextTertiary)
                    }
                }
            }

            val clinicSlug = item.clinic?.slug
            val serviceId = item.serviceId
            if (!clinicSlug.isNullOrBlank()) {
                Spacer(Modifier.height(10.dp))
                Divider(color = Outline, thickness = 0.5.dp)
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        text = "Lihat klinik",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = TextSecondary,
                        modifier = Modifier
                            .clip(RoundedCornerShape(PetHealRadius.pill))
                            .clickable { onOpenClinic(clinicSlug) }
                            .padding(horizontal = 10.dp, vertical = 6.dp)
                    )
                    Spacer(Modifier.weight(1f))
                    // Klinik aktif → langsung ke pemilihan dokter.
                    // Klinik lain → buka detail klinik (satu-satunya jalan
                    // yang benar: dokter hanya dimuat untuk klinik aktif).
                    if (item.hasPrice && serviceId != null) {
                        Surface(
                            onClick = { onBook(clinicSlug, serviceId) },
                            shape = RoundedCornerShape(PetHealRadius.pill),
                            color = if (isActiveClinic) Primary else PrimaryLight
                        ) {
                            Text(
                                text = if (isActiveClinic) "Booking" else "Pilih dokter di klinik ini",
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isActiveClinic) MaterialTheme.colorScheme.onPrimary else PrimaryDark,
                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp)
                            )
                        }
                    }
                }
                if (!isActiveClinic) {
                    Spacer(Modifier.height(6.dp))
                    Text(
                        text = activeClinicName?.let {
                            "Booking terakhir memakai $it. Buka detail klinik ini untuk melihat dokter dan layanannya."
                        } ?: "Klinik booking belum dipilih. Buka detail klinik ini untuk melihat dokter dan layanannya.",
                        fontSize = 10.5.sp,
                        color = TextTertiary,
                        lineHeight = 14.sp
                    )
                }
            }
        }
    }
}
