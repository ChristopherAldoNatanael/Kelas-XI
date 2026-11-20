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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.components.shimmerEffect
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.util.formatDateSafe
import com.christopheraldoo.petheal.util.formatRupiah

@Composable
fun PetShopOrdersScreen(
    onNavigateBack: () -> Unit,
    onOpenOrder: (Int) -> Unit,
    onBrowseProducts: () -> Unit,
    viewModel: PetShopOrdersViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Scaffold(
        containerColor = Background,
        topBar = {
            PetShopHeader(
                title = "Pesanan Saya",
                subtitle = when {
                    uiState.isLoading -> "Memuat riwayat pembelian"
                    uiState.orders.isEmpty() -> "Belum ada pesanan"
                    else -> "${uiState.orders.size} pesanan · ambil di klinik"
                },
                onBack = onNavigateBack
            )
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(Background)
        ) {
            when {
                uiState.isLoading -> OrdersSkeleton()
                uiState.error != null && uiState.orders.isEmpty() -> ErrorState(
                    title = "Pesanan belum bisa dimuat",
                    message = uiState.error.orEmpty(),
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.load() }
                )
                uiState.orders.isEmpty() -> EmptyState(
                    icon = Icons.Outlined.ReceiptLong,
                    title = "Belum ada pesanan",
                    description = "Pesanan produk dari klinik mitra akan tampil di sini beserta status pengambilannya.",
                    actionText = "Lihat katalog",
                    onActionClick = onBrowseProducts
                )
                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = ShopTheme.gutter, vertical = 14.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "${uiState.orders.size} pesanan terbaru",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = OnBackground,
                                modifier = Modifier.weight(1f)
                            )
                            if (uiState.isRefreshing) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(14.dp),
                                    color = Primary,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Surface(
                                    onClick = { viewModel.refresh() },
                                    shape = RoundedCornerShape(PetHealRadius.pill),
                                    color = Surface,
                                    border = BorderStroke(1.dp, Outline)
                                ) {
                                    Text(
                                        text = "Muat ulang",
                                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = Primary
                                    )
                                }
                            }
                        }
                    }

                    itemsIndexed(uiState.orders, key = { _, order -> order.id }) { index, order ->
                        OrderCard(order = order, onClick = { onOpenOrder(order.id) })

                        if (index == uiState.orders.lastIndex && uiState.hasMore) {
                            Box(
                                modifier = Modifier.fillMaxWidth().padding(8.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                if (uiState.isLoadingMore) {
                                    CircularProgressIndicator(
                                        modifier = Modifier.size(18.dp),
                                        color = Primary,
                                        strokeWidth = 2.dp
                                    )
                                } else {
                                    Surface(
                                        onClick = { viewModel.loadMore() },
                                        shape = RoundedCornerShape(PetHealRadius.pill),
                                        color = Surface,
                                        border = BorderStroke(1.dp, Outline)
                                    ) {
                                        Text(
                                            text = "Muat pesanan lain",
                                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                            fontSize = 11.5.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = Primary
                                        )
                                    }
                                }
                            }
                        }
                    }

                    if (uiState.error != null) {
                        item { PetShopInlineError(uiState.error.orEmpty()) }
                    }
                }
            }
        }
    }
}

/**
 * Kartu pesanan.
 *
 * Susunannya mengikuti kebiasaan aplikasi belanja: nomor pesanan + status di
 * atas, klinik penjual, potongan foto barang yang dibeli (supaya pesanan bisa
 * dikenali dari gambarnya, bukan hanya teks), lalu total & status bayar.
 */
@Composable
private fun OrderCard(order: PetShopOrder, onClick: () -> Unit) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(ShopTheme.cardRadius))
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = Surface,
        shadowElevation = 2.dp
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = order.orderNumber,
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = TextTertiary,
                    letterSpacing = 0.3.sp,
                    modifier = Modifier.weight(1f)
                )
                PetShopStatusBadge(orderStatusStyle(order.status))
            }

            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.Storefront,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(15.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = order.clinic?.name.orEmpty(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                val shown = order.items.take(3)
                shown.forEach { item ->
                    PetShopImage(
                        url = item.imageUrl,
                        contentDescription = item.productName,
                        modifier = Modifier
                            .size(46.dp)
                            .clip(RoundedCornerShape(10.dp))
                    )
                    Spacer(Modifier.width(6.dp))
                }
                if (order.items.size > shown.size) {
                    Box(
                        modifier = Modifier
                            .size(46.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(SurfaceMuted),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "+${order.items.size - shown.size}",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = TextSecondary
                        )
                    }
                    Spacer(Modifier.width(10.dp))
                } else {
                    Spacer(Modifier.width(4.dp))
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = order.items.firstOrNull()?.productName.orEmpty(),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = OnBackground,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis,
                        lineHeight = 16.sp
                    )
                    if (order.items.size > 1) {
                        Text(
                            text = "+${order.items.size - 1} produk lain",
                            fontSize = 10.sp,
                            color = TextTertiary
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Outline))
            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = formatDateSafe(order.createdAt, "d MMM yyyy · HH:mm"),
                        fontSize = 10.sp,
                        color = TextTertiary
                    )
                    Text(
                        text = formatRupiah(order.totalAmount.toDouble()),
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                }
                PetShopStatusBadge(style = paymentStatusStyle(order.paymentStatus))
                Spacer(Modifier.width(4.dp))
                Icon(
                    Icons.Filled.ChevronRight,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(18.dp)
                )
            }

            if (order.status == PetShopOrder.STATUS_READY_FOR_PICKUP) {
                Spacer(Modifier.height(10.dp))
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(PetHealRadius.md))
                        .background(PrimaryLight)
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Filled.QrCode2,
                        contentDescription = null,
                        tint = Primary,
                        modifier = Modifier.size(15.dp)
                    )
                    Spacer(Modifier.width(7.dp))
                    Text(
                        text = "Siap diambil — tunjukkan kode pengambilan di klinik",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Primary
                    )
                }
            }
        }
    }
}

@Composable
private fun OrdersSkeleton() {
    Column(
        modifier = Modifier.padding(horizontal = ShopTheme.gutter, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        repeat(3) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(148.dp)
                    .clip(RoundedCornerShape(ShopTheme.cardRadius))
                    .background(SurfaceMuted)
                    .shimmerEffect(true)
            )
        }
    }
}
