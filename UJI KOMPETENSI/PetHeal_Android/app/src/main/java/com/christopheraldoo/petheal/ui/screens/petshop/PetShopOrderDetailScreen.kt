package com.christopheraldoo.petheal.ui.screens.petshop

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.LocalHospital
import androidx.compose.material.icons.filled.Payments
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopOrderItem
import com.christopheraldoo.petheal.ui.components.ErrorState
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.ErrorSoft
import com.christopheraldoo.petheal.ui.theme.Info
import com.christopheraldoo.petheal.ui.theme.InfoSoft
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.PetHealRadius
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.PrimaryLight
import com.christopheraldoo.petheal.ui.theme.Surface
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.formatDateSafe
import com.christopheraldoo.petheal.util.formatRupiah

@Composable
fun PetShopOrderDetailScreen(
    onNavigateBack: () -> Unit,
    viewModel: PetShopOrderDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val snackbarHostState = remember { SnackbarHostState() }
    var showCancelDialog by remember { mutableStateOf(false) }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeNotice()
        }
    }

    LaunchedEffect(uiState.paymentUrl) {
        uiState.paymentUrl?.let { url ->
            viewModel.consumePaymentUrl()
            val opened = runCatching {
                context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
            }.isSuccess

            snackbarHostState.showSnackbar(
                if (opened) {
                    "Selesaikan pembayaran di browser, lalu kembali dan cek status."
                } else {
                    "Browser tidak tersedia. Buka tautan pembayaran dari perangkat lain."
                }
            )
        }
    }

    if (showCancelDialog) {
        CancelOrderDialog(
            onDismiss = { showCancelDialog = false },
            onConfirm = { reason ->
                showCancelDialog = false
                viewModel.cancelOrder(reason)
            }
        )
    }

    Scaffold(
        containerColor = Background,
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            PetShopHeader(
                title = "Detail Pesanan",
                subtitle = uiState.order?.orderNumber,
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
            val order = uiState.order

            when {
                uiState.isLoading && order == null -> Box(
                    Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Primary)
                }

                uiState.error != null && order == null -> ErrorState(
                    title = "Pesanan tidak dapat dibuka",
                    message = uiState.error.orEmpty(),
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.load() }
                )

                order != null -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = 20.dp, vertical = 12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item { StatusSummary(order) }

                    item {
                        OrderActions(
                            order = order,
                            isBusy = uiState.isActionRunning,
                            onPay = viewModel::payNow,
                            onCheckStatus = viewModel::checkPaymentStatus,
                            onCancel = { showCancelDialog = true }
                        )
                    }

                    if (uiState.error != null) {
                        item { PetShopInlineError(uiState.error.orEmpty()) }
                    }

                    item { SectionTitle("Item pesanan") }
                    item { ItemsCard(order.items) }

                    item { SectionTitle("Pengambilan") }
                    item { PickupCard(order) }

                    item { SectionTitle("Pembayaran") }
                    item { PaymentCard(order) }

                    if (order.timeline.isNotEmpty()) {
                        item { SectionTitle("Riwayat status") }
                        item { TimelineCard(order) }
                    }

                    if (!order.notes.isNullOrBlank()) {
                        item { SectionTitle("Catatan") }
                        item {
                            Surface(
                                color = Surface,
                                shape = RoundedCornerShape(PetHealRadius.lg),
                                border = BorderStroke(1.dp, Outline)
                            ) {
                                Text(
                                    text = order.notes.orEmpty(),
                                    modifier = Modifier.padding(14.dp),
                                    fontSize = 12.sp,
                                    lineHeight = 18.sp,
                                    color = TextSecondary
                                )
                            }
                        }
                    }

                    item {
                        PetShopInfoBanner(
                            "Simpan nomor pesanan ini. Tunjukkan kepada petugas klinik saat mengambil produk."
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(
        text = text,
        fontSize = 13.sp,
        fontWeight = FontWeight.SemiBold,
        color = OnBackground,
        modifier = Modifier.padding(top = 4.dp)
    )
}

@Composable
private fun StatusSummary(order: PetShopOrder) {
    val style = orderStatusStyle(order.status)
    val hint = when (order.status) {
        PetShopOrder.STATUS_PENDING_PAYMENT ->
            "Selesaikan pembayaran sebelum batas waktu agar pesanan tidak otomatis dibatalkan."
        PetShopOrder.STATUS_AWAITING_CONFIRMATION ->
            "Klinik akan mengonfirmasi pesanan Anda. Pembayaran dilakukan saat pengambilan."
        PetShopOrder.STATUS_ACCEPTED, PetShopOrder.STATUS_PREPARING ->
            "Klinik sedang menyiapkan produk Anda."
        PetShopOrder.STATUS_READY_FOR_PICKUP ->
            "Produk siap diambil di klinik. Tunjukkan nomor pesanan kepada petugas."
        PetShopOrder.STATUS_COMPLETED ->
            "Pesanan selesai. Terima kasih telah berbelanja di klinik mitra."
        PetShopOrder.STATUS_REJECTED, PetShopOrder.STATUS_CANCELLED, PetShopOrder.STATUS_EXPIRED ->
            "Pesanan tidak dilanjutkan. Bila sudah dibayar, refund diproses sesuai kebijakan klinik."
        else -> ""
    }

    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .background(style.container, CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        if (order.status == PetShopOrder.STATUS_COMPLETED) Icons.Filled.CheckCircle else Icons.Filled.Schedule,
                        contentDescription = null,
                        tint = style.content,
                        modifier = Modifier.size(21.dp)
                    )
                }
                Spacer(Modifier.width(12.dp))
                Column(modifier = Modifier.weight(1f)) {
                    PetShopStatusBadge(style)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        text = formatDateSafe(order.createdAt, "d MMMM yyyy · HH:mm"),
                        fontSize = 11.sp,
                        color = TextTertiary
                    )
                }
            }

            Spacer(Modifier.height(12.dp))
            Text(text = hint, fontSize = 12.sp, lineHeight = 18.sp, color = TextSecondary)

            if (order.status == PetShopOrder.STATUS_PENDING_PAYMENT && order.reservationExpiresAt != null) {
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.Schedule,
                        contentDescription = null,
                        tint = WarningText,
                        modifier = Modifier.size(14.dp)
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        "Batas pembayaran: ${formatDateSafe(order.reservationExpiresAt, "d MMM yyyy · HH:mm")}",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Medium,
                        color = WarningText
                    )
                }
            }
        }
    }
}

@Composable
private fun OrderActions(
    order: PetShopOrder,
    isBusy: Boolean,
    onPay: () -> Unit,
    onCheckStatus: () -> Unit,
    onCancel: () -> Unit
) {
    val hasAction = order.isAwaitingOnlinePayment || order.canCancel || order.isOnline
    if (!hasAction) return

    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            if (order.isAwaitingOnlinePayment) {
                Button(
                    onClick = onPay,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth().height(46.dp),
                    shape = RoundedCornerShape(PetHealRadius.md),
                    colors = ButtonDefaults.buttonColors(containerColor = Primary)
                ) {
                    if (isBusy) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(18.dp),
                            color = Color.White,
                            strokeWidth = 2.dp
                        )
                    } else {
                        Icon(Icons.Filled.Payments, contentDescription = null, modifier = Modifier.size(17.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Bayar Sekarang", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                    }
                }
            }

            if (order.isOnline) {
                OutlinedButton(
                    onClick = onCheckStatus,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth().height(44.dp),
                    shape = RoundedCornerShape(PetHealRadius.md),
                    border = BorderStroke(1.dp, Outline)
                ) {
                    Icon(Icons.Filled.Refresh, contentDescription = null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Cek Status Pembayaran", fontSize = 12.sp, color = OnBackground)
                }
            }

            if (order.canCancel) {
                TextButton(
                    onClick = onCancel,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("Batalkan Pesanan", fontSize = 12.sp, color = MaterialTheme.colorScheme.error)
                }
            }
        }
    }
}

@Composable
private fun ItemsCard(items: List<PetShopOrderItem>) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            items.forEach { item ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    PetShopImage(
                        url = item.imageUrl,
                        contentDescription = item.productName,
                        modifier = Modifier
                            .size(48.dp)
                            .background(SurfaceMuted, RoundedCornerShape(PetHealRadius.sm))
                    )
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = item.productName,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = OnBackground,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = "${item.variantName} · ${item.quantity}×",
                            fontSize = 11.sp,
                            color = TextSecondary
                        )
                    }
                    Text(
                        text = formatRupiah(item.subtotal.toDouble()),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = OnBackground
                    )
                }
            }
        }
    }
}

@Composable
private fun PickupCard(order: PetShopOrder) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(Modifier.width(8.dp))
                Text(
                    text = order.pickup?.clinicName ?: order.clinic?.name.orEmpty(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
            }

            val address = order.pickup?.address ?: order.clinic?.address
            if (!address.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Text(address, fontSize = 11.sp, lineHeight = 17.sp, color = TextSecondary)
            }

            val phone = order.pickup?.phone ?: order.clinic?.phone
            if (!phone.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.Phone,
                        contentDescription = null,
                        tint = TextTertiary,
                        modifier = Modifier.size(13.dp)
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(phone, fontSize = 11.sp, color = TextSecondary)
                }
            }
        }
    }
}

@Composable
private fun PaymentCard(order: PetShopOrder) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(modifier = Modifier.fillMaxWidth()) {
                Text(
                    if (order.isOnline) "Pembayaran online" else "Bayar di klinik",
                    fontSize = 12.sp,
                    color = TextSecondary,
                    modifier = Modifier.weight(1f)
                )
                PetShopStatusBadge(paymentStatusStyle(order.paymentStatus))
            }

            Spacer(Modifier.height(10.dp))

            Row(modifier = Modifier.fillMaxWidth()) {
                Text("Subtotal", fontSize = 12.sp, color = TextSecondary, modifier = Modifier.weight(1f))
                Text(
                    formatRupiah(order.subtotal.toDouble()),
                    fontSize = 12.sp,
                    color = OnBackground
                )
            }

            if (order.discountTotal > 0) {
                Spacer(Modifier.height(4.dp))
                Row(modifier = Modifier.fillMaxWidth()) {
                    Text("Diskon", fontSize = 12.sp, color = TextSecondary, modifier = Modifier.weight(1f))
                    Text(
                        "-${formatRupiah(order.discountTotal.toDouble())}",
                        fontSize = 12.sp,
                        color = Primary
                    )
                }
            }

            Spacer(Modifier.height(8.dp))
            Box(Modifier.fillMaxWidth().height(1.dp).background(Outline))
            Spacer(Modifier.height(8.dp))

            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Total",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    formatRupiah(order.totalAmount.toDouble()),
                    fontSize = 17.sp,
                    fontWeight = FontWeight.Bold,
                    color = Primary
                )
            }
        }
    }
}

@Composable
private fun TimelineCard(order: PetShopOrder) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            order.timeline.forEachIndexed { index, entry ->
                Row {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Box(
                            modifier = Modifier
                                .size(9.dp)
                                .background(
                                    if (index == order.timeline.lastIndex) Primary else Outline,
                                    CircleShape
                                )
                        )
                        if (index != order.timeline.lastIndex) {
                            Box(
                                modifier = Modifier
                                    .width(1.dp)
                                    .height(34.dp)
                                    .background(Outline)
                            )
                        }
                    }
                    Spacer(Modifier.width(10.dp))
                    Column(modifier = Modifier.padding(bottom = 10.dp)) {
                        Text(
                            text = orderStatusStyle(entry.toStatus.orEmpty()).label,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = OnBackground
                        )
                        Text(
                            text = formatDateSafe(entry.createdAt, "d MMM yyyy · HH:mm"),
                            fontSize = 10.sp,
                            color = TextTertiary
                        )
                        if (!entry.note.isNullOrBlank()) {
                            Text(
                                text = entry.note,
                                fontSize = 11.sp,
                                lineHeight = 16.sp,
                                color = TextSecondary
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CancelOrderDialog(onDismiss: () -> Unit, onConfirm: (String?) -> Unit) {
    var reason by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Batalkan pesanan?", fontSize = 17.sp, fontWeight = FontWeight.Bold) },
        text = {
            Column {
                Text(
                    "Pesanan yang dibatalkan tidak dapat dikembalikan. Stok akan dilepas untuk pembeli lain.",
                    fontSize = 12.sp,
                    lineHeight = 18.sp,
                    color = TextSecondary
                )
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = reason,
                    onValueChange = { if (it.length <= 200) reason = it },
                    label = { Text("Alasan (opsional)", fontSize = 12.sp) },
                    shape = RoundedCornerShape(PetHealRadius.md),
                    colors = TextFieldDefaults.colors(
                        focusedContainerColor = Surface,
                        unfocusedContainerColor = Surface,
                        focusedIndicatorColor = Primary,
                        unfocusedIndicatorColor = Outline
                    )
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { onConfirm(reason.ifBlank { null }) }) {
                Text("Batalkan pesanan", color = MaterialTheme.colorScheme.error, fontWeight = FontWeight.SemiBold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Kembali", color = TextSecondary) }
        },
        containerColor = Surface
    )
}
