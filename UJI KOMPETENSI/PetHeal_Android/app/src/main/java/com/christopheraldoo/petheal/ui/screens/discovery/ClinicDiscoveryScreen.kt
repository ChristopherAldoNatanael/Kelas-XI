package com.christopheraldoo.petheal.ui.screens.discovery

import android.Manifest
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Tune
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.compose.runtime.collectAsState
import com.christopheraldoo.petheal.data.model.ClinicCard
import com.christopheraldoo.petheal.data.model.ServiceCategoryDto
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository.DiscoverySort
import com.christopheraldoo.petheal.ui.components.ClinicDiscoveryCard
import com.christopheraldoo.petheal.ui.components.Dimens
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.PetHealTopAppBar
import com.christopheraldoo.petheal.ui.components.SkeletonCard
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.StatusCancelled
import com.christopheraldoo.petheal.ui.theme.SurfaceVariant
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft

/**
 * Pencarian & penjelajahan klinik (multi-clinic discovery).
 *
 * (doc comment lengkap ada di root file)
 *
 * Reuse: PetHealTopAppBar, ClinicDiscoveryCard, SkeletonCard, EmptyState,
 * ErrorStateView, dan token warna dari paket ui.theme. Tidak ada warna
 * hardcoded baru.
 *
 * Lokasi opsional: kartu lokasi menampilkan status jujur dan aksi manual;
 * aplikasi tetap berfungsi penuh tanpa izin lokasi.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClinicDiscoveryScreen(
    onNavigateBack: () -> Unit,
    onOpenClinic: (slug: String) -> Unit,
    onComparePrices: () -> Unit,
    viewModel: DiscoveryViewModel = hiltViewModel()
) {
    val state by viewModel.uiState.collectAsState()
    var showFilters by remember { mutableStateOf(false) }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { granted ->
        val anyGranted = granted.values.any { it }
        if (anyGranted) viewModel.requestGpsLocation() else viewModel.dismissLocationStatus()
    }

    Column(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        PetHealTopAppBar(title = "Cari Klinik", onNavigateBack = onNavigateBack)

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 32.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Column(Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)) {
                    LocationBar(
                        state = state,
                        onUseGps = {
                            permissionLauncher.launch(
                                arrayOf(
                                    Manifest.permission.ACCESS_FINE_LOCATION,
                                    Manifest.permission.ACCESS_COARSE_LOCATION
                                )
                            )
                        },
                        onManual = { showFilters = false; onComparePrices() },
                        onClear = viewModel::clearLocation
                    )
                    Spacer(Modifier.height(12.dp))
                    SearchField(
                        value = state.search,
                        onValueChange = viewModel::onSearchChange,
                        onToggleFilters = { showFilters = !showFilters }
                    )
                }
            }

            if (showFilters) {
                item {
                    FilterPanel(
                        state = state,
                        onSort = viewModel::onSortChange,
                        onMinRating = viewModel::onMinRatingChange,
                        onMaxDistance = viewModel::onMaxDistanceChange,
                        onOpenNow = viewModel::onOpenNowToggle,
                        onReset = viewModel::resetFilters
                    )
                }
            }

            item {
                CategoryRail(
                    categories = state.categories,
                    selected = state.selectedCategory,
                    onSelect = viewModel::onCategorySelected
                )
            }

            when {
                state.isLoading -> items(4) { SkeletonCard() }

                state.error != null -> item {
                    ErrorState(
                        title = "Daftar klinik gagal dimuat",
                        message = state.error ?: "",
                        primaryActionText = "Coba lagi",
                        onPrimaryActionClick = viewModel::refresh
                    )
                }

                state.clinics.isEmpty() -> item {
                    EmptyState(
                        title = "Klinik tidak ditemukan",
                        description = "Coba kata kunci lain, atau hapus filter yang aktif.",
                        icon = Icons.Filled.LocationOn
                    )
                }

                else -> {
                    item {
                        Text(
                            text = "${state.clinics.size} klinik ditemukan",
                            fontSize = 12.sp,
                            color = TextTertiary,
                            modifier = Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)
                        )
                    }
                    itemsIndexed(
                        items = state.clinics,
                        key = { _, clinic -> clinic.key }
                    ) { _, clinic ->
                        Box(Modifier.padding(horizontal = Dimens.ScreenPaddingHorizontal)) {
                            ClinicDiscoveryCard(
                                clinic = clinic,
                                onClick = { clinic.slug?.let { slug -> onOpenClinic(slug) } }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun LocationBar(
    state: DiscoveryUiState,
    onUseGps: () -> Unit,
    onManual: () -> Unit,
    onClear: () -> Unit
) {
    val (title, subtitle) = when (state.locationStatus) {
        LocationUiStatus.Loading -> "Mencari lokasi…" to "Mengambil titik GPS terakhir."
        LocationUiStatus.PermissionDenied ->
            "Izin lokasi ditolak" to "Anda tetap bisa memakai aplikasi. Aktifkan lokasi untuk melihat jarak klinik."
        LocationUiStatus.ServicesDisabled ->
            "GPS nonaktif" to "Nyalakan GPS di pengaturan HP, atau gunakan lokasi manual."
        LocationUiStatus.Failed ->
            "Lokasi tidak ditemukan" to "Coba lagi, atau isi lokasi manual."
        LocationUiStatus.Ready -> {
            val label = state.location?.label ?: "Lokasi tersimpan"
            label to "Jarak klinik dihitung dari titik ini."
        }
        LocationUiStatus.Unset -> "Lokasi belum diatur" to "Aktifkan lokasi untuk mengurutkan klinik terdekat."
    }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = SurfaceVariant,
        border = BorderStroke(1.dp, Outline)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Filled.LocationOn,
                contentDescription = null,
                tint = Primary,
                modifier = Modifier.size(18.dp)
            )
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text(title, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(subtitle, fontSize = 11.sp, color = TextSecondary, lineHeight = 15.sp)
            }
            if (state.locationStatus == LocationUiStatus.Loading) {
                CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = Primary)
            } else if (state.hasLocation) {
                IconButton(onClick = onClear, modifier = Modifier.size(Dimens.MinTouchTarget)) {
                    Icon(Icons.Filled.Close, contentDescription = "Hapus lokasi", tint = TextTertiary)
                }
            } else {
                TextButton(onClick = onUseGps) {
                    Text("Aktifkan", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Primary)
                }
            }
        }
    }

    if (state.locationStatus == LocationUiStatus.PermissionDenied ||
        state.locationStatus == LocationUiStatus.ServicesDisabled ||
        state.locationStatus == LocationUiStatus.Failed
    ) {
        Spacer(Modifier.height(6.dp))
        TextButton(onClick = onManual, contentPadding = PaddingValues(0.dp)) {
            Text("Isi lokasi manual", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Primary)
        }
    }
}

@Composable
private fun SearchField(
    value: String,
    onValueChange: (String) -> Unit,
    onToggleFilters: () -> Unit
) {
    Row(verticalAlignment = Alignment.CenterVertically) {
        OutlinedTextField(
            value = value,
            onValueChange = onValueChange,
            modifier = Modifier.weight(1f),
            singleLine = true,
            shape = RoundedCornerShape(PetHealRadius.md),
            placeholder = { Text("Cari klinik atau layanan", fontSize = 13.sp, color = TextTertiary) },
            leadingIcon = {
                Icon(Icons.Filled.Search, contentDescription = null, tint = TextTertiary, modifier = Modifier.size(18.dp))
            },
            trailingIcon = {
                if (value.isNotEmpty()) {
                    IconButton(onClick = { onValueChange("") }) {
                        Icon(Icons.Filled.Close, contentDescription = "Hapus pencarian", modifier = Modifier.size(16.dp))
                    }
                }
            }
        )
        Spacer(Modifier.width(8.dp))
        Surface(
            onClick = onToggleFilters,
            shape = RoundedCornerShape(PetHealRadius.md),
            color = SurfaceVariant,
            border = BorderStroke(1.dp, Outline),
            modifier = Modifier.size(52.dp)
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(Icons.Filled.Tune, contentDescription = "Filter", tint = TextSecondary, modifier = Modifier.size(20.dp))
            }
        }
    }
}

@Composable
private fun FilterPanel(
    state: DiscoveryUiState,
    onSort: (DiscoverySort) -> Unit,
    onMinRating: (Double?) -> Unit,
    onMaxDistance: (Double?) -> Unit,
    onOpenNow: (Boolean) -> Unit,
    onReset: () -> Unit
) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = Dimens.ScreenPaddingHorizontal),
        shape = RoundedCornerShape(PetHealRadius.lg),
        color = SurfaceVariant,
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            FilterLabel("Urutkan")
            ChipRow(
                options = listOf(
                    DiscoverySort.NEAREST to "Terdekat",
                    DiscoverySort.RATING to "Rating",
                    DiscoverySort.NAME to "Nama"
                ),
                isSelected = { state.sort == it },
                onSelect = onSort
            )

            FilterLabel("Rating minimum")
            ChipRow(
                options = listOf<Pair<Double?, String>>(
                    4.0 to "4+",
                    3.0 to "3+",
                    null to "Semua"
                ),
                isSelected = { state.minRating == it },
                onSelect = onMinRating
            )

            FilterLabel("Jarak maksimal")
            ChipRow(
                options = listOf<Pair<Double?, String>>(
                    2.0 to "2 km",
                    5.0 to "5 km",
                    10.0 to "10 km",
                    null to "Semua"
                ),
                isSelected = { state.maxDistanceKm == it },
                onSelect = onMaxDistance
            )

            FilterLabel("Status")
            ChipRow(
                options = listOf(true to "Buka sekarang", false to "Semua"),
                isSelected = { state.openNowOnly == it },
                onSelect = onOpenNow
            )

            TextButton(onClick = onReset, contentPadding = PaddingValues(0.dp)) {
                Text("Reset filter", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = Primary)
            }
        }
    }
}

@Composable
private fun FilterLabel(text: String) {
    Text(text, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
}

@Composable
private fun <T> ChipRow(
    options: List<Pair<T, String>>,
    isSelected: (T) -> Boolean,
    onSelect: (T) -> Unit
) {
    Row(
        modifier = Modifier.horizontalScroll(rememberScrollState()),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        options.forEach { (value, label) ->
            val selected = isSelected(value)
            Surface(
                onClick = { onSelect(value) },
                shape = RoundedCornerShape(PetHealRadius.pill),
                color = if (selected) Primary else MaterialTheme.colorScheme.surface,
                border = BorderStroke(1.dp, if (selected) Primary else Outline)
            ) {
                Text(
                    text = label,
                    fontSize = 12.sp,
                    fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
                    color = if (selected) MaterialTheme.colorScheme.onPrimary else TextSecondary,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp)
                )
            }
        }
    }
}

@Composable
private fun CategoryRail(
    categories: List<com.christopheraldoo.petheal.data.model.ServiceCategoryDto>,
    selected: String?,
    onSelect: (String?) -> Unit
) {
    if (categories.isEmpty()) return
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = Dimens.ScreenPaddingHorizontal),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        categories.forEach { category ->
            val isSelected = selected == category.slug
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier
                    .width(78.dp)
                    .clip(RoundedCornerShape(PetHealRadius.md))
                    .background(if (isSelected) Primary.copy(alpha = 0.10f) else SurfaceVariant)
                    .clickable { onSelect(category.slug) }
                    .padding(vertical = 10.dp, horizontal = 4.dp)
            ) {
                Text(
                    text = category.name.orEmpty(),
                    fontSize = 10.5.sp,
                    lineHeight = 13.sp,
                    textAlign = TextAlign.Center,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal,
                    color = if (isSelected) Primary else TextSecondary
                )
            }
        }
    }
}
