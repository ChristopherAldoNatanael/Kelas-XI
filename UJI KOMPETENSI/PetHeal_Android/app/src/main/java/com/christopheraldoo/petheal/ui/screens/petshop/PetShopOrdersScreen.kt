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
import androidx.compose.material.icons.filled.Storefront
import androidx.compose.material.icons.outlined.ReceiptLong
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.ui.components.EmptyState
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Surface
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
                subtitle = "Riwayat pembelian produk",
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
                uiState.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = Primary)
                }
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
                    contentPadding = PaddingValues(horizontal = 20.dp, vertical = 12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                "${uiState.orders.size} pesanan terbaru",
                                fontSize = 12.sp,
                                color = TextSecondary,
                                modifier = Modifier.weight(1f)
                            )
                            if (uiState.isRefreshing) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(14.dp),
                                    color = Primary,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                TextButton(onClick = { viewModel.refresh() }) {
                                    Text("Muat ulang", fontSize = 12.sp, color = Primary)
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
                                    TextButton(onClick = { viewModel.loadMore() }) {
                                        Text("Muat pesanan lain", fontSize = 12.sp, color = Primary)
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

@Composable
private fun OrderCard(order: PetShopOrder, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = order.orderNumber,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = TextSecondary,
                    modifier = Modifier.weight(1f)
                )
                PetShopStatusBadge(orderStatusStyle(order.status))
            }

            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.Storefront,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(15.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = order.clinic?.name.orEmpty(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
            }

            Spacer(Modifier.height(4.dp))

            Text(
                text = order.items.joinToString(", ") { "${it.productName} ×${it.quantity}" },
                fontSize = 11.sp,
                color = TextSecondary,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
                lineHeight = 16.sp
            )

            Spacer(Modifier.height(10.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        formatDateSafe(order.createdAt, "d MMM yyyy · HH:mm"),
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
                PetShopStatusBadge(
                    style = paymentStatusStyle(order.paymentStatus),
                    modifier = Modifier.padding(end = 6.dp)
                )
                Icon(
                    Icons.Filled.ChevronRight,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(18.dp)
                )
            }
        }
    }
}
