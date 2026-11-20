package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
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
import androidx.compose.foundation.layout.navigationBarsPadding
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
import androidx.compose.material.icons.filled.QrCode2
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
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
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopOrder
import com.christopheraldoo.petheal.data.model.PetShopOrderItem
import com.christopheraldoo.petheal.data.model.PetShopPickupPass
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
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.ui.theme.WarningText
import com.christopheraldoo.petheal.util.formatDateSafe
import com.christopheraldoo.petheal.util.formatRupiah

@Composable
fun PetShopOrderDetailScreen(
    onNavigateBack: () -> Unit,
    onOpenReceipt: (Int) -> Unit,
    viewModel: PetShopOrderDetailViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    var showCancelDialog by remember { mutableStateOf(false) }

    LaunchedEffect(uiState.notice) {
        uiState.notice?.let {
            snackbarHostState.showSnackbar(it)
            viewModel.consumeNotice()
        }
    }

    // Pembayaran online dibuka IN-APP (WebView Midtrans). Setelah user selesai
    // membayar, status diverifikasi ke server lalu pesanan dimuat ulang —
    // user tetap di halaman detail pesanan dengan status pembayaran terbaru.
    val paymentUrl = uiState.paymentUrl
    if (paymentUrl != null) {
        BackHandler {
            viewModel.verifyPayment()
        }
        Box(modifier = Modifier.fillMaxSize()) {
            PetShopPaymentWebView(
                url = paymentUrl,
                isSyncing = uiState.isSyncingPayment,
                onCompleted = { viewModel.verifyPayment() },
                onClose = { viewModel.dismissPayment() }
            )
            SnackbarHost(
                hostState = snackbarHostState,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
            )
        }
        return
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
        snackbarHost = { SnackbarHost(snackbarHostState, modifier = Modifier.padding(16.dp)) },
        topBar = {
            val headerOrder = uiState.order
            PetShopHeader(
                title = "Detail Pesanan",
                subtitle = headerOrder?.orderNumber,
                onBack = onNavigateBack,
                // Status + total ditaruh di dalam pita kepala: pembeli langsung
                // tahu posisi pesanannya tanpa harus menggulir.
                content = if (headerOrder == null) null else {
                    { OrderStatusPanel(headerOrder) }
                }
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
                uiState.isLoading && order == null -> OrderDetailSkeleton()

                uiState.error != null && order == null -> ErrorState(
                    title = "Pesanan tidak dapat dibuka",
                    message = uiState.error.orEmpty(),
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.load() }
                )

                order != null -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(
                        start = ShopTheme.gutter,
                        end = ShopTheme.gutter,
                        top = 14.dp,
                        bottom = 24.dp
                    ),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    val hasActions = order.isAwaitingOnlinePayment || order.canCancel || order.isOnline
                    if (hasActions) {
                        item {
                            OrderActions(
                                order = order,
                                isBusy = uiState.isActionRunning,
                                onPay = viewModel::payNow,
                                onCheckStatus = viewModel::checkPaymentStatus,
                                onCancel = { showCancelDialog = true }
                            )
                        }
                    }

                    if (uiState.error != null) {
                        item { PetShopInlineError(uiState.error.orEmpty()) }
                    }

                    if (
                        order.status == PetShopOrder.STATUS_PENDING_PAYMENT &&
                        order.reservationExpiresAt != null
                    ) {
                        item {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(PetHealRadius.md))
                                    .background(WarningSoft)
                                    .padding(horizontal = 12.dp, vertical = 10.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Filled.Schedule,
                                    contentDescription = null,
                                    tint = WarningText,
                                    modifier = Modifier.size(15.dp)
                                )
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    text = "Batas pembayaran " +
                                        formatDateSafe(order.reservationExpiresAt, "d MMM yyyy · HH:mm"),
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Medium,
                                    color = WarningText
                                )
                            }
                        }
                    }

                    // QR Pickup Pass hanya muncul setelah pesanan siap diambil.
                    if (uiState.pickupPass != null) {
                        item { ShopSectionTitle("Kode pengambilan") }
                        item { PickupPassCard(uiState.pickupPass!!, uiState.pickupQr, uiState.isLoadingPass) }
                    }

                    item { ShopSectionTitle("Item pesanan") }
                    item { ItemsCard(order.items) }

                    item { ShopSectionTitle("Pengambilan") }
                    item { PickupCard(order) }

                    item { ShopSectionTitle("Pembayaran") }
                    item { PaymentCard(order) }

                    if (order.receiptAvailable) {
                        item {
                            OutlinedButton(
                                onClick = { onOpenReceipt(order.id) },
                                modifier = Modifier.fillMaxWidth().height(46.dp),
                                shape = RoundedCornerShape(PetHealRadius.md),
                                border = BorderStroke(1.dp, Primary.copy(alpha = 0.55f))
                            ) {
                                Icon(
                                    Icons.Filled.ReceiptLong,
                                    contentDescription = null,
                                    tint = Primary,
                                    modifier = Modifier.size(17.dp)
                                )
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    "Lihat Bukti Pembayaran",
                                    fontSize = 12.sp,
                                    color = Primary,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                    }

                    if (order.timeline.isNotEmpty()) {
                        item { ShopSectionTitle("Riwayat status") }
                        item { TimelineCard(order) }
                    }

                    if (!order.notes.isNullOrBlank()) {
                        item { ShopSectionTitle("Catatan") }
                        item {
                            ShopCard {
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

/** Panel status translusen di dalam pita kepala layar. */
@Composable
private fun OrderStatusPanel(order: PetShopOrder) {
    val style = orderStatusStyle(order.status)

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = Surface
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                PetShopStatusBadge(style)
                Spacer(Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = "Total",
                        fontSize = 9.5.sp,
                        color = TextSecondary
                    )
                    Text(
                        text = formatRupiah(order.totalAmount.toDouble()),
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                }
            }

            Spacer(Modifier.height(8.dp))

            Text(
                text = orderStatusHint(order.status),
                fontSize = 11.sp,
                lineHeight = 16.sp,
                color = TextSecondary
            )

            Spacer(Modifier.height(6.dp))
            Text(
                text = "Dibuat ${formatDateSafe(order.createdAt, "d MMM yyyy · HH:mm")}",
                fontSize = 10.sp,
                color = TextTertiary
            )
        }
    }
}

private fun orderStatusHint(status: String): String = when (status) {
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

@Composable
private fun OrderActions(
    order: PetShopOrder,
    isBusy: Boolean,
    onPay: () -> Unit,
    onCheckStatus: () -> Unit,
    onCancel: () -> Unit
) {
    ShopCard {
        Column(
            modifier = Modifier.padding(14.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
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
    ShopCard {
        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            items.forEach { item ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    PetShopImage(
                        url = item.imageUrl,
                        contentDescription = item.productName,
                        modifier = Modifier
                            .size(50.dp)
                            .clip(RoundedCornerShape(ShopTheme.innerRadius))
                    )
                    Spacer(Modifier.width(11.dp))
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = item.productName,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = OnBackground,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis,
                            lineHeight = 16.sp
                        )
                        Text(
                            text = if (item.sku.isNullOrBlank()) {
                                item.variantName
                            } else {
                                "${item.variantName} · ${item.sku}"
                            },
                            fontSize = 10.5.sp,
                            color = TextSecondary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        // Harga satuan dari snapshot pesanan, bukan harga katalog
                        // terbaru yang bisa sudah berubah.
                        Text(
                            text = "${item.quantity} × " + formatRupiah((item.discountPrice ?: item.unitPrice).toDouble()),
                            fontSize = 10.sp,
                            color = TextTertiary
                        )
                    }
                    Text(
                        text = formatRupiah(item.subtotal.toDouble()),
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                }
            }
        }
    }
}

@Composable
private fun PickupCard(order: PetShopOrder) {
    ShopCard {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(34.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(PrimaryLight),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Filled.LocalHospital,
                        contentDescription = null,
                        tint = Primary,
                        modifier = Modifier.size(17.dp)
                    )
                }
                Spacer(Modifier.width(10.dp))
                Text(
                    text = order.pickup?.clinicName ?: order.clinic?.name.orEmpty(),
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground
                )
            }

            val address = order.pickup?.address ?: order.clinic?.address
            if (!address.isNullOrBlank()) {
                Spacer(Modifier.height(8.dp))
                Text(
                    address,
                    fontSize = 11.5.sp,
                    lineHeight = 17.sp,
                    color = TextSecondary
                )
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
                    Text(phone, fontSize = 11.5.sp, color = TextSecondary)
                }
            }
        }
    }
}

@Composable
private fun PaymentCard(order: PetShopOrder) {
    ShopCard {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    if (order.isOnline) "Pembayaran online" else "Bayar di klinik",
                    fontSize = 12.sp,
                    color = TextSecondary,
                    modifier = Modifier.weight(1f)
                )
                PetShopStatusBadge(paymentStatusStyle(order.paymentStatus))
            }

            Spacer(Modifier.height(12.dp))

            PaymentDetailRow("Subtotal", formatRupiah(order.subtotal.toDouble()))

            if (order.discountTotal > 0) {
                Spacer(Modifier.height(4.dp))
                PaymentDetailRow(
                    label = "Diskon",
                    value = "-${formatRupiah(order.discountTotal.toDouble())}",
                    valueColor = Primary
                )
            }

            Spacer(Modifier.height(10.dp))
            DashedDivider()
            Spacer(Modifier.height(10.dp))

            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    "Total",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
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

            // Detail dari baris `payments`. Nominal diterima & kembalian hanya
            // dicetak untuk transaksi di klinik — transaksi online tidak punya
            // kembalian, jadi baris itu tidak pernah muncul.
            val payment = order.payment
            if (payment != null) {
                Spacer(Modifier.height(10.dp))
                DashedDivider()
                Spacer(Modifier.height(8.dp))

                payment.methodLabel?.let { PaymentDetailRow("Metode", it) }
                if (!payment.reference.isNullOrBlank()) {
                    PaymentDetailRow("No. referensi", payment.reference.orEmpty())
                }
                payment.paidAt?.let {
                    PaymentDetailRow("Waktu bayar", formatDateSafe(it, "d MMM yyyy · HH:mm"))
                }

                if (payment.isOffline) {
                    payment.amountReceived?.let {
                        PaymentDetailRow("Uang diterima", formatRupiah(it.toDouble()))
                    }
                    payment.changeAmount?.let {
                        PaymentDetailRow("Kembalian", formatRupiah(it.toDouble()))
                    }
                    payment.recordedBy?.let { PaymentDetailRow("Dicatat oleh", it) }
                }
            }
        }
    }
}

@Composable
private fun PaymentDetailRow(
    label: String,
    value: String,
    valueColor: Color = OnBackground
) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
        Text(label, fontSize = 11.sp, color = TextSecondary, modifier = Modifier.weight(1f))
        Text(
            value,
            fontSize = 11.sp,
            fontWeight = FontWeight.Medium,
            color = valueColor,
            textAlign = TextAlign.End
        )
    }
}

@Composable
private fun TimelineCard(order: PetShopOrder) {
    ShopCard {
        Column(modifier = Modifier.padding(14.dp)) {
            order.timeline.forEachIndexed { index, entry ->
                val isLatest = index == order.timeline.lastIndex
                Row {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Box(
                            modifier = Modifier
                                .padding(top = 3.dp)
                                .size(if (isLatest) 10.dp else 8.dp)
                                .clip(CircleShape)
                                .background(if (isLatest) Primary else Outline)
                        )
                        if (!isLatest) {
                            Box(
                                modifier = Modifier
                                    .width(1.5.dp)
                                    .height(32.dp)
                                    .background(Outline)
                            )
                        }
                    }
                    Spacer(Modifier.width(11.dp))
                    Column(modifier = Modifier.padding(bottom = if (isLatest) 0.dp else 10.dp)) {
                        Text(
                            text = orderStatusStyle(entry.toStatus.orEmpty()).label,
                            fontSize = 12.sp,
                            fontWeight = if (isLatest) FontWeight.Bold else FontWeight.SemiBold,
                            color = if (isLatest) Primary else OnBackground
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
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedContainerColor = Surface,
                        unfocusedContainerColor = Surface,
                        focusedBorderColor = Primary,
                        unfocusedBorderColor = Outline
                    )
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { onConfirm(reason.ifBlank { null }) }) {
                Text(
                    "Batalkan pesanan",
                    color = MaterialTheme.colorScheme.error,
                    fontWeight = FontWeight.SemiBold
                )
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Kembali", color = TextSecondary) }
        },
        containerColor = Surface
    )
}

/**
 * QR Pickup Pass + kode manual. Kode manual adalah fallback resmi saat kamera
 * klinik tidak bisa membaca QR, jadi keduanya punya bobot visual yang sama.
 */
@Composable
private fun PickupPassCard(
    pass: PetShopPickupPass,
    qr: android.graphics.Bitmap?,
    isLoading: Boolean
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(ShopTheme.cardRadius),
        color = Surface,
        border = BorderStroke(1.2.dp, Primary.copy(alpha = 0.45f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.QrCode2,
                    contentDescription = null,
                    tint = Primary,
                    modifier = Modifier.size(17.dp)
                )
                Spacer(Modifier.width(7.dp))
                Text(
                    pass.statusLabel ?: "Siap diambil",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Primary
                )
            }

            Spacer(Modifier.height(12.dp))

            if (qr != null) {
                Surface(
                    shape = RoundedCornerShape(PetHealRadius.md),
                    border = BorderStroke(1.dp, Outline),
                    color = Color.White
                ) {
                    Image(
                        bitmap = qr.asImageBitmap(),
                        contentDescription = "QR Code pengambilan",
                        modifier = Modifier.size(182.dp).padding(10.dp)
                    )
                }
            } else if (isLoading) {
                CircularProgressIndicator(
                    color = Primary,
                    strokeWidth = 2.dp,
                    modifier = Modifier.size(24.dp)
                )
            } else {
                Text(
                    "QR belum dapat dimuat. Gunakan kode pengambilan di bawah.",
                    fontSize = 11.sp,
                    color = TextSecondary,
                    textAlign = TextAlign.Center
                )
            }

            Spacer(Modifier.height(12.dp))
            DashedDivider(modifier = Modifier.padding(horizontal = 8.dp))
            Spacer(Modifier.height(12.dp))

            Text("Kode pengambilan", fontSize = 10.sp, color = TextTertiary, letterSpacing = 0.4.sp)
            Text(
                pass.manualCode ?: "-",
                fontSize = 27.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground,
                letterSpacing = 5.sp
            )

            Spacer(Modifier.height(6.dp))
            Text(
                pass.instructions
                    ?: "Datang ke klinik lalu tunjukkan QR Code atau kode pengambilan kepada petugas.",
                fontSize = 11.sp,
                lineHeight = 16.sp,
                color = TextSecondary,
                textAlign = TextAlign.Center
            )

            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    Icons.Filled.LocalHospital,
                    contentDescription = null,
                    tint = TextTertiary,
                    modifier = Modifier.size(14.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    pass.clinic?.name ?: "",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Medium,
                    color = OnBackground
                )
            }
            if (!pass.clinic?.address.isNullOrBlank()) {
                Text(
                    pass.clinic?.address.orEmpty(),
                    fontSize = 10.sp,
                    lineHeight = 14.sp,
                    color = TextSecondary,
                    textAlign = TextAlign.Center
                )
            }

            Spacer(Modifier.height(8.dp))
            Text(
                "Status pembayaran: ${pass.paymentStatusLabel ?: "-"}",
                fontSize = 10.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (pass.paymentStatus == PetShopOrder.PAYMENT_PAID) Primary else WarningText
            )
        }
    }
}

@Composable
private fun OrderDetailSkeleton() {
    Column(
        modifier = Modifier.padding(horizontal = ShopTheme.gutter, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        repeat(3) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(if (it == 0) 96.dp else 132.dp)
                    .clip(RoundedCornerShape(ShopTheme.cardRadius))
                    .background(SurfaceMuted)
                    .shimmerEffect(true)
            )
        }
    }
}
