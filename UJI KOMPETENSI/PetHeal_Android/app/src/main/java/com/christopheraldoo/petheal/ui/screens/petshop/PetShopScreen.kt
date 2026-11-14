package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TextFieldDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.ui.components.EmptySearchState
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.util.formatRupiah

@Composable
fun PetShopScreen(
    onNavigateBack: () -> Unit,
    onOpenProduct: (Int) -> Unit,
    onOpenCart: () -> Unit,
    onOpenOrders: () -> Unit,
    viewModel: PetShopViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeNotice()
        }
    }

    Scaffold(
        containerColor = Background,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            PetShopHeader(
                title = "Pet Shop",
                subtitle = "Produk pilihan dari klinik mitra",
                onBack = onNavigateBack,
                cartCount = uiState.cartCount,
                onOpenCart = onOpenCart,
                onOpenOrders = onOpenOrders
            )
        },
        bottomBar = {
            if (uiState.cartCount > 0) {
                CartBar(
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
                    item { SearchAndFilters(uiState, viewModel) }

                    if (uiState.products.isEmpty()) {
                        item {
                            if (uiState.hasActiveFilters) {
                                EmptySearchState(
                                    query = uiState.search.ifBlank { "filter ini" },
                                    modifier = Modifier.height(360.dp)
                                )
                            } else {
                                EmptyState(
                                    icon = Icons.Outlined.Inventory2,
                                    title = "Belum ada produk",
                                    description = "Produk dari klinik mitra akan muncul di sini setelah katalog tersedia.",
                                    modifier = Modifier.height(360.dp)
                                )
                            }
                        }
                    } else {
                        item {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = 20.dp, vertical = 10.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Text(
                                    text = "${uiState.products.size} produk",
                                    fontSize = 12.sp,
                                    color = TextSecondary
                                )
                                if (uiState.hasActiveFilters) {
                                    TextButton(onClick = { viewModel.resetFilters() }) {
                                        Text("Reset", fontSize = 12.sp, color = Primary)
                                    }
                                }
                            }
                        }

                        itemsIndexed(uiState.products.chunked(2)) { _, rowProducts ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(horizontal = 20.dp, vertical = 5.dp),
                                horizontalArrangement = Arrangement.spacedBy(12.dp)
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
                                        .padding(16.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    if (uiState.isLoadingMore) {
                                        Text("Memuat…", fontSize = 13.sp, color = TextSecondary)
                                    } else {
                                        TextButton(onClick = { viewModel.loadMore() }) {
                                            Text("Muat lebih banyak", fontSize = 13.sp, color = Primary)
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

@Composable
private fun SearchAndFilters(state: PetShopUiState, viewModel: PetShopViewModel) {
    var sortMenuOpen by remember { mutableStateOf(false) }

    Column(modifier = Modifier.padding(bottom = 4.dp)) {
        OutlinedTextField(
            value = state.search,
            onValueChange = viewModel::onSearchChange,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 6.dp),
            placeholder = { Text("Cari makanan, obat, atau aksesoris…", fontSize = 13.sp) },
            leadingIcon = {
                Icon(Icons.Filled.Search, contentDescription = null, tint = TextSecondary)
            },
            singleLine = true,
            shape = RoundedCornerShape(PetHealRadius.md),
            colors = TextFieldDefaults.colors(
                focusedContainerColor = Surface,
                unfocusedContainerColor = Surface,
                focusedIndicatorColor = Primary,
                unfocusedIndicatorColor = Outline
            )
        )

        LazyRow(
            contentPadding = PaddingValues(horizontal = 20.dp, vertical = 6.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            item {
                FilterChip(
                    label = "Semua",
                    selected = state.selectedCategory == null,
                    onClick = { viewModel.onCategorySelected(null) }
                )
            }
            items(state.categories) { category ->
                FilterChip(
                    label = category.name,
                    selected = state.selectedCategory == category.slug,
                    onClick = { viewModel.onCategorySelected(category.slug) }
                )
            }
        }

        if (state.sellers.isNotEmpty()) {
            LazyRow(
                contentPadding = PaddingValues(horizontal = 20.dp, vertical = 2.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                item {
                    FilterChip(
                        label = "Semua klinik",
                        selected = state.selectedClinicId == null,
                        onClick = { viewModel.onClinicSelected(null) }
                    )
                }
                items(state.sellers) { seller ->
                    FilterChip(
                        label = seller.name,
                        selected = state.selectedClinicId == seller.id,
                        onClick = { viewModel.onClinicSelected(seller.id) }
                    )
                }
            }
        }

        Box(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, end = 20.dp, top = 2.dp),
            contentAlignment = Alignment.CenterEnd
        ) {
            TextButton(onClick = { sortMenuOpen = true }) {
                Text(state.sort.label, fontSize = 12.sp, color = TextSecondary)
                Icon(
                    Icons.Filled.ArrowDropDown,
                    contentDescription = null,
                    tint = TextSecondary,
                    modifier = Modifier.size(18.dp)
                )
            }
            DropdownMenu(expanded = sortMenuOpen, onDismissRequest = { sortMenuOpen = false }) {
                PetShopSort.entries.forEach { sort ->
                    DropdownMenuItem(
                        text = {
                            Text(
                                sort.label,
                                fontWeight = if (sort == state.sort) FontWeight.SemiBold else FontWeight.Normal
                            )
                        },
                        onClick = {
                            sortMenuOpen = false
                            viewModel.onSortSelected(sort)
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun FilterChip(label: String, selected: Boolean, onClick: () -> Unit) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(PetHealRadius.pill),
        color = if (selected) Primary else Surface,
        border = if (selected) null else androidx.compose.foundation.BorderStroke(1.dp, Outline)
    ) {
        Text(
            text = label,
            modifier = Modifier.padding(horizontal = 13.dp, vertical = 7.dp),
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            color = if (selected) Color.White else OnBackground
        )
    }
}

@Composable
private fun CartBar(count: Int, total: Long, onClick: () -> Unit) {
    val navPadding = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()
    Surface(
        color = Surface,
        shadowElevation = 12.dp,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 20.dp, vertical = 12.dp)
                .padding(bottom = navPadding),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Filled.ShoppingBag,
                contentDescription = null,
                tint = Primary,
                modifier = Modifier.size(22.dp)
            )
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text("$count item", fontSize = 12.sp, color = TextSecondary)
                Text(
                    formatRupiah(total.toDouble()),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = OnBackground
                )
            }
            Button(
                onClick = onClick,
                shape = RoundedCornerShape(PetHealRadius.md),
                colors = ButtonDefaults.buttonColors(containerColor = Primary),
                contentPadding = PaddingValues(horizontal = 22.dp, vertical = 12.dp)
            ) {
                Text("Lihat keranjang", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
            }
        }
    }
}

@Composable
private fun PetShopCatalogSkeleton() {
    Column(modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(52.dp)
                .background(SurfaceMuted, RoundedCornerShape(PetHealRadius.md))
        )
        Spacer(Modifier.height(12.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            repeat(4) {
                Box(
                    modifier = Modifier
                        .width(80.dp)
                        .height(32.dp)
                        .background(SurfaceMuted, RoundedCornerShape(PetHealRadius.pill))
                )
            }
        }
        Spacer(Modifier.height(20.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            repeat(2) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(230.dp)
                        .background(Surface, RoundedCornerShape(PetHealRadius.lg))
                )
            }
        }
        Spacer(Modifier.height(12.dp))
        Surface(color = InfoSoft, shape = RoundedCornerShape(PetHealRadius.md)) {
            Text(
                "Menyiapkan katalog dari klinik mitra…",
                modifier = Modifier.padding(14.dp),
                fontSize = 12.sp,
                color = TextSecondary
            )
        }
    }
}
