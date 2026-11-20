package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.asPaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.Sort
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import com.christopheraldoo.petheal.ui.util.collectAsStateLifecycleAware
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.ImeAction
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.ui.components.EmptySearchState
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.shimmerEffect
import com.christopheraldoo.petheal.ui.location.rememberLocationPermission
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryDark
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.LocationHelper
import com.christopheraldoo.petheal.util.LocationResult
import com.christopheraldoo.petheal.util.formatRupiah
import com.christopheraldoo.petheal.ui.components.Dimens
import androidx.compose.foundation.layout.heightIn

@Composable
fun PetShopScreen(
    onNavigateBack: () -> Unit,
    onOpenProduct: (Int) -> Unit,
    onOpenCart: () -> Unit,
    onOpenOrders: () -> Unit,
    viewModel: PetShopViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsStateLifecycleAware()
    val snackbarHostState = remember { androidx.compose.material3.SnackbarHostState() }
    val context = LocalContext.current

    // Filter "Terdekat" memakai mekanisme izin lokasi yang sama dengan layar
    // lain. Menolak izin tidak memblokir katalog: semua klinik tetap tampil.
    val locationPermission = rememberLocationPermission()
    var locationRequest by remember { mutableIntStateOf(0) }

    // `locationRequest` sengaja TIDAK di-reset di dalam effect: mengganti nilai
    // kunci LaunchedEffect membatalkan coroutine yang sedang berjalan, sehingga
    // permintaan lokasi dulu selalu terhenti sebelum jarak didapat. Setiap
    // permintaan baru menaikkan nilai ini; `isGranted` ikut jadi kunci supaya
    // hasil dialog izin langsung memicu pengambilan lokasi.
    LaunchedEffect(locationRequest, locationPermission.isGranted) {
        if (locationRequest == 0 || !locationPermission.isGranted) return@LaunchedEffect
        when (val result = LocationHelper.currentLocation(context)) {
            is LocationResult.Success -> viewModel.enableNearby(result.latitude, result.longitude)
            else -> viewModel.onNearbyFailed()
        }
    }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeNotice()
        }
    }

    androidx.compose.material3.Scaffold(
        containerColor = Background,
        snackbarHost = {
            androidx.compose.material3.SnackbarHost(
                hostState = snackbarHostState,
                modifier = Modifier.padding(16.dp)
            )
        },
        topBar = {
            PetShopHeader(
                title = "Pet Shop",
                subtitle = "Ambil sendiri di klinik mitra",
                onBack = onNavigateBack,
                cartCount = uiState.cartCount,
                onOpenCart = onOpenCart,
                onOpenOrders = onOpenOrders,
                content = {
                    ShopSearchField(
                        value = uiState.search,
                        onValueChange = viewModel::onSearchChange
                    )
                }
            )
        },
        bottomBar = {
            if (uiState.cartCount > 0) {
                ShopCartBar(
                    count = uiState.cartCount,
                    total = uiState.cartSubtotal,
                    onClick = onOpenCart
                )
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(Background)
        ) {
            when {
                uiState.isLoading -> PetShopCatalogSkeleton()
                uiState.error != null && uiState.products.isEmpty() -> ErrorState(
                    title = "Katalog belum bisa dimuat",
                    message = uiState.error.orEmpty(),
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.refresh() }
                )
                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 24.dp)
                ) {
                    item {
                        CategoryRail(
                            state = uiState,
                            onSelect = { viewModel.onCategorySelected(it) }
                        )
                    }

                    item {
                        ClinicFilterRow(
                            state = uiState,
                            canRequestPermission = locationPermission.canRequest,
                            onSelectClinic = viewModel::onClinicSelected,
                            onShowAllClinics = {
                                if (uiState.nearbyMode) viewModel.disableNearby()
                                else viewModel.onClinicSelected(null)
                            },
                            onToggleNearby = {
                                if (uiState.nearbyMode) {
                                    viewModel.disableNearby()
                                } else {
                                    locationRequest++
                                    if (!locationPermission.isGranted) {
                                        if (locationPermission.canRequest) {
                                            locationPermission.request()
                                        } else {
                                            locationPermission.openSettings()
                                        }
                                    }
                                }
                            }
                        )
                    }

                    item {
                        ResultToolbar(
                            count = uiState.products.size,
                            hasActiveFilters = uiState.hasActiveFilters,
                            sort = uiState.sort,
                            onResetFilters = { viewModel.resetFilters() },
                            onSortSelected = viewModel::onSortSelected
                        )
                    }

                    if (uiState.products.isEmpty()) {
                        item {
                            if (uiState.hasActiveFilters) {
                                EmptySearchState(
                                    query = uiState.search.ifBlank { "filter ini" },
                                    modifier = Modifier.height(340.dp)
                                )
                            } else {
                                EmptyState(
                                    icon = Icons.Outlined.Inventory2,
                                    title = "Belum ada produk",
                                    description = "Produk dari klinik mitra akan muncul di sini setelah katalog tersedia.",
                                    modifier = Modifier.height(340.dp)
                                )
                            }
                        }
                    } else {
                        itemsIndexed(uiState.products.chunked(2)) { _, rowProducts ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = ShopTheme.gutter, vertical = 5.dp),
                                horizontalArrangement = Arrangement.spacedBy(12.dp),
                                verticalAlignment = Alignment.Top
                            ) {
                                rowProducts.forEach { product ->
                                    PetShopProductCard(
                                        product = product,
                                        onClick = { onOpenProduct(product.id) },
                                        modifier = Modifier.weight(1f)
                                    )
                                }
                                if (rowProducts.size == 1) {
                                    Spacer(Modifier.weight(1f))
                                }
                            }
                        }

                        if (uiState.hasMore) {
                            item {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(vertical = 16.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    if (uiState.isLoadingMore) {
                                        CircularProgressIndicator(
                                            color = Primary,
                                            strokeWidth = 2.dp,
                                            modifier = Modifier.size(20.dp)
                                        )
                                    } else {
                                        Surface(
                                            onClick = { viewModel.loadMore() },
                                            shape = RoundedCornerShape(PetHealRadius.pill),
                                            color = Surface,
                                            border = BorderStroke(1.dp, Outline)
                                        ) {
                                            Text(
                                                text = "Muat produk lain",
                                                modifier = Modifier.padding(horizontal = 18.dp, vertical = 10.dp),
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.SemiBold,
                                                color = Primary
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/** Kolom pencarian di dalam pita kepala layar. */
@Composable
private fun ShopSearchField(value: String, onValueChange: (String) -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(PetHealRadius.md),
        color = Surface
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Filled.Search,
                contentDescription = null,
                tint = TextTertiary,
                modifier = Modifier.size(18.dp)
            )
            Spacer(Modifier.width(9.dp))
            Box(modifier = Modifier.weight(1f)) {
                if (value.isEmpty()) {
                    Text(
                        text = "Cari makanan, obat, aksesoris…",
                        fontSize = 13.sp,
                        color = TextTertiary
                    )
                }
                BasicTextField(
                    value = value,
                    onValueChange = onValueChange,
                    singleLine = true,
                    textStyle = androidx.compose.ui.text.TextStyle(
                        color = OnBackground,
                        fontSize = 13.sp
                    ),
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                    keyboardActions = KeyboardActions(),
                    modifier = Modifier.fillMaxWidth()
                )
            }
            if (value.isNotEmpty()) {
                Box(
                    modifier = Modifier
                        .size(24.dp)
                        .clip(CircleShape)
                        .clickable { onValueChange("") },
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Close,
                        contentDescription = "Hapus pencarian",
                        tint = TextTertiary,
                        modifier = Modifier.size(15.dp)
                    )
                }
            }
        }
    }
}

/** Rel kategori berbentuk ubin ikon. */
@Composable
private fun CategoryRail(state: PetShopUiState, onSelect: (String?) -> Unit) {
    LazyRow(
        contentPadding = PaddingValues(horizontal = ShopTheme.gutter - 6.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        item {
            ShopCategoryTile(
                name = "Semua",
                slug = null,
                selected = state.selectedCategory == null,
                onClick = { onSelect(null) }
            )
        }
        items(state.categories) { category ->
            ShopCategoryTile(
                name = category.name,
                slug = category.slug,
                selected = state.selectedCategory == category.slug,
                onClick = { onSelect(category.slug) }
            )
        }
    }
}

/** Baris filter klinik: semua klinik, terdekat, lalu klinik penjual. */
@Composable
private fun ClinicFilterRow(
    state: PetShopUiState,
    canRequestPermission: Boolean,
    onSelectClinic: (Int?) -> Unit,
    onShowAllClinics: () -> Unit,
    onToggleNearby: () -> Unit
) {
    Column {
        LazyRow(
            contentPadding = PaddingValues(horizontal = ShopTheme.gutter),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            item {
                ShopFilterChip(
                    label = "Semua klinik",
                    selected = state.selectedClinicId == null && !state.nearbyMode,
                    onClick = onShowAllClinics
                )
            }
            item {
                ShopFilterChip(
                    label = if (state.isLoadingNearby) "Mencari…" else "Terdekat",
                    selected = state.nearbyMode,
                    icon = Icons.Filled.MyLocation,
                    onClick = onToggleNearby
                )
            }

            if (state.nearbyMode) {
                items(state.clinicOptions) { clinic ->
                    ShopFilterChip(
                        label = clinic.distanceLabel?.let { "${clinic.name} · $it" } ?: clinic.name,
                        selected = state.selectedClinicId == clinic.id,
                        // Di mode terdekat selalu ada satu klinik terpilih;
                        // menekan chip yang sama tidak boleh mengosongkan filter
                        // (pakai "Semua klinik" untuk itu).
                        onClick = {
                            if (state.selectedClinicId != clinic.id) onSelectClinic(clinic.id)
                        }
                    )
                }
            } else {
                items(state.sellers) { seller ->
                    ShopFilterChip(
                        label = seller.name,
                        selected = state.selectedClinicId == seller.id,
                        onClick = { onSelectClinic(seller.id) }
                    )
                }
            }
        }

        val note = when {
            state.nearbyMode ->
                "Menampilkan produk klinik terdekat (jarak garis lurus). Pilih klinik lain di atas untuk melihat produknya."
            state.locationUnavailable && canRequestPermission ->
                "Jarak belum tersedia. Aktifkan lokasi lewat tombol Terdekat."
            state.locationUnavailable ->
                "Izin lokasi dimatikan. Aktifkan lewat pengaturan aplikasi bila ingin melihat jarak."
            else -> null
        }
        if (note != null) {
            Text(
                text = note,
                modifier = Modifier.padding(horizontal = ShopTheme.gutter, vertical = 4.dp),
                fontSize = 10.sp,
                lineHeight = 14.sp,
                color = TextSecondary
            )
        }
    }
}

/** Jumlah hasil + tombol urutkan. */
@Composable
private fun ResultToolbar(
    count: Int,
    hasActiveFilters: Boolean,
    sort: PetShopSort,
    onResetFilters: () -> Unit,
    onSortSelected: (PetShopSort) -> Unit
) {
    var sortMenuOpen by remember { mutableStateOf(false) }

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(start = ShopTheme.gutter, end = ShopTheme.gutter, top = 12.dp, bottom = 4.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = "$count produk",
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = OnBackground,
            modifier = Modifier.weight(1f)
        )
        if (hasActiveFilters) {
            Text(
                text = "Reset",
                fontSize = 11.5.sp,
                fontWeight = FontWeight.SemiBold,
                color = Primary,
                modifier = Modifier
                    .clip(RoundedCornerShape(PetHealRadius.sm))
                    .clickable(onClick = onResetFilters)
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            )
            Spacer(Modifier.width(4.dp))
        }
        Box {
            Surface(
                onClick = { sortMenuOpen = true },
                shape = RoundedCornerShape(PetHealRadius.pill),
                color = Surface,
                border = BorderStroke(1.dp, Outline)
            ) {
                Row(
                    modifier = Modifier.padding(start = 10.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Filled.Sort,
                        contentDescription = null,
                        tint = TextSecondary,
                        modifier = Modifier.size(13.dp)
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        text = sort.label,
                        fontSize = 11.5.sp,
                        color = TextSecondary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Icon(
                        imageVector = Icons.Filled.ArrowDropDown,
                        contentDescription = null,
                        tint = TextSecondary,
                        modifier = Modifier.size(16.dp)
                    )
                }
            }
            DropdownMenu(expanded = sortMenuOpen, onDismissRequest = { sortMenuOpen = false }) {
                PetShopSort.entries.forEach { option ->
                    DropdownMenuItem(
                        text = {
                            Text(
                                option.label,
                                fontSize = 13.sp,
                                fontWeight = if (option == sort) FontWeight.SemiBold else FontWeight.Normal,
                                color = if (option == sort) Primary else OnBackground
                            )
                        },
                        onClick = {
                            sortMenuOpen = false
                            onSortSelected(option)
                        }
                    )
                }
            }
        }
    }
}

/** Chip filter putih dengan garis tipis; aktif = hijau penuh. */
@Composable
private fun ShopFilterChip(
    label: String,
    selected: Boolean,
    onClick: () -> Unit,
    icon: androidx.compose.ui.graphics.vector.ImageVector? = null
) {
    // Pembungkus 48dp: area sentuh memenuhi standar aksesibilitas, bentuk pil
    // tetap ramping seperti desain aslinya.
    Box(
        modifier = Modifier
            .heightIn(min = Dimens.MinTouchTarget)
            .clickable(onClick = onClick),
        contentAlignment = Alignment.Center
    ) {
        Surface(
            shape = RoundedCornerShape(PetHealRadius.pill),
            // Chip aktif memakai hijau tua: teks putih tetap terbaca di 11 sp.
            color = if (selected) PrimaryDark else Surface,
            border = if (selected) null else BorderStroke(1.dp, Outline)
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (icon != null) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = if (selected) Color.White else TextSecondary,
                        modifier = Modifier.size(13.dp)
                    )
                    Spacer(Modifier.width(5.dp))
                }
                Text(
                    text = label,
                    fontSize = 11.5.sp,
                    fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
                    color = if (selected) Color.White else OnBackground,
                    maxLines = 1
                )
            }
        }
    }
}

/**
 * Bar keranjang mengapung di atas navigasi sistem: bentuk pil, bayangan tebal,
 * dan tombol hijau penuh supaya aksi utamanya tidak mungkin terlewat.
 */
@Composable
private fun ShopCartBar(count: Int, total: Long, onClick: () -> Unit) {
    val navPadding = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(start = 16.dp, end = 16.dp, bottom = navPadding + 12.dp)
    ) {
        Surface(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            color = Surface,
            shadowElevation = 12.dp
        ) {
            Row(
                modifier = Modifier.padding(start = 12.dp, end = 8.dp, top = 8.dp, bottom = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(RoundedCornerShape(ShopTheme.innerRadius))
                        .background(PrimaryLight),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.ShoppingBag,
                        contentDescription = null,
                        tint = Primary,
                        modifier = Modifier.size(20.dp)
                    )
                }
                Spacer(Modifier.width(11.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "$count item",
                        fontSize = 10.5.sp,
                        color = TextSecondary
                    )
                    Text(
                        text = formatRupiah(total.toDouble()),
                        fontSize = 15.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                }
                Button(
                    onClick = onClick,
                    shape = RoundedCornerShape(PetHealRadius.md),
                    colors = ButtonDefaults.buttonColors(containerColor = Primary),
                    contentPadding = PaddingValues(start = 16.dp, end = 12.dp, top = 11.dp, bottom = 11.dp)
                ) {
                    Text(
                        "Keranjang",
                        fontWeight = FontWeight.SemiBold,
                        fontSize = 12.5.sp
                    )
                    Spacer(Modifier.width(4.dp))
                    Icon(
                        imageVector = Icons.Filled.ChevronRight,
                        contentDescription = null,
                        modifier = Modifier.size(11.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun PetShopCatalogSkeleton() {
    Column(modifier = Modifier.padding(horizontal = ShopTheme.gutter, vertical = 12.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            repeat(4) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        modifier = Modifier
                            .size(52.dp)
                            .clip(RoundedCornerShape(ShopTheme.innerRadius))
                            .background(SurfaceMuted)
                            .shimmerEffect(true)
                    )
                    Spacer(Modifier.height(6.dp))
                    Box(
                        modifier = Modifier
                            .width(44.dp)
                            .height(9.dp)
                            .clip(RoundedCornerShape(PetHealRadius.sm))
                            .background(SurfaceMuted)
                            .shimmerEffect(true)
                    )
                }
            }
        }
        Spacer(Modifier.height(18.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            repeat(2) {
                Column(modifier = Modifier.weight(1f)) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(150.dp)
                            .clip(RoundedCornerShape(ShopTheme.cardRadius))
                            .background(SurfaceMuted)
                            .shimmerEffect(true)
                    )
                    Spacer(Modifier.height(8.dp))
                    Box(
                        modifier = Modifier
                            .fillMaxWidth(0.9f)
                            .height(11.dp)
                            .clip(RoundedCornerShape(PetHealRadius.sm))
                            .background(SurfaceMuted)
                            .shimmerEffect(true)
                    )
                    Spacer(Modifier.height(6.dp))
                    Box(
                        modifier = Modifier
                            .fillMaxWidth(0.55f)
                            .height(11.dp)
                            .clip(RoundedCornerShape(PetHealRadius.sm))
                            .background(SurfaceMuted)
                            .shimmerEffect(true)
                    )
                }
            }
        }
        Spacer(Modifier.height(14.dp))
        Text(
            "Menyiapkan katalog dari klinik mitra…",
            fontSize = 11.sp,
            color = TextTertiary,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth()
        )
    }
}
