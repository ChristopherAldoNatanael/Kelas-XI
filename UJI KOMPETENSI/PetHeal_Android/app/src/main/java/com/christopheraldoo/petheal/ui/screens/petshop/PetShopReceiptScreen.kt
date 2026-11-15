package com.christopheraldoo.petheal.ui.screens.petshop

import androidx.compose.foundation.BorderStroke
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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.christopheraldoo.petheal.data.model.PetShopReceipt
import com.christopheraldoo.petheal.ui.components.ErrorState
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
import com.christopheraldoo.petheal.util.formatRupiah

/**
 * Bukti pembayaran Pet Shop.
 *
 * Semua angka & status berasal dari backend (snapshot transaksi). Layar ini
 * tidak pernah menyimpulkan "lunas" sendiri; badge lunas hanya muncul dari
 * `receipt.isSettled`.
 */
@Composable
fun PetShopReceiptScreen(
    onNavigateBack: () -> Unit,
    viewModel: PetShopReceiptViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val navPadding = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()

    LaunchedEffect(uiState.message) {
        uiState.message?.let { viewModel.consumeMessage() }
    }

    Scaffold(
        containerColor = Background,
        topBar = {
            PetShopHeader(
                title = "Bukti Pembayaran",
                subtitle = uiState.receipt?.orderNumber,
                onBack = onNavigateBack
            )
        },
        bottomBar = {
            if (uiState.receipt != null) {
                Surface(color = Surface, shadowElevation = 12.dp) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 20.dp, vertical = 12.dp)
                            .padding(bottom = navPadding),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(
                                onClick = viewModel::sharePng,
                                enabled = !uiState.isBusy,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(PetHealRadius.md),
                                border = BorderStroke(1.dp, Outline)
                            ) {
                                Icon(Icons.Filled.Share, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Bagikan PNG", fontSize = 11.sp)
                            }
                            OutlinedButton(
                                onClick = viewModel::savePng,
                                enabled = !uiState.isBusy,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(PetHealRadius.md),
                                border = BorderStroke(1.dp, Outline)
                            ) {
                                Icon(Icons.Filled.Download, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Simpan PNG", fontSize = 11.sp)
                            }
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(
                                onClick = viewModel::sharePdf,
                                enabled = !uiState.isBusy,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(PetHealRadius.md),
                                border = BorderStroke(1.dp, Outline)
                            ) {
                                Icon(Icons.Filled.Share, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Bagikan PDF", fontSize = 11.sp)
                            }
                            Button(
                                onClick = viewModel::downloadPdf,
                                enabled = !uiState.isBusy,
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(PetHealRadius.md),
                                colors = ButtonDefaults.buttonColors(containerColor = Primary)
                            ) {
                                Icon(Icons.Filled.Download, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Unduh PDF", fontSize = 11.sp)
                            }
                        }
                        if (uiState.receipt?.isSettled == false) {
                            Text(
                                "PDF bukti pembayaran hanya tersedia setelah pembayaran lunas.",
                                fontSize = 10.sp,
                                color = WarningText,
                                textAlign = TextAlign.Center,
                                modifier = Modifier.fillMaxWidth()
                            )
                        }
                    }
                }
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
                uiState.isLoading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = Primary)
                }
                uiState.receipt == null -> ErrorState(
                    title = "Bukti pembayaran tidak tersedia",
                    message = uiState.error ?: "Bukti pembayaran hanya dapat dibuka setelah pembayaran diterima.",
                    primaryActionText = "Coba lagi",
                    onPrimaryActionClick = { viewModel.load() }
                )
                else -> Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState())
                        .padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    uiState.receipt?.let { ReceiptCard(it) }
                    if (uiState.isBusy) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            CircularProgressIndicator(color = Primary, strokeWidth = 2.dp, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Memproses…", fontSize = 11.sp, color = TextSecondary)
                        }
                    }
                    uiState.error?.let { PetShopInlineError(it) }
                    Text(
                        "Bukti pembayaran memakai data transaksi saat pesanan dibuat. Perubahan harga katalog tidak mengubah struk lama.",
                        fontSize = 10.sp,
                        lineHeight = 15.sp,
                        color = TextTertiary
                    )
                }
            }
        }
    }
}

@Composable
private fun ReceiptCard(receipt: PetShopReceipt) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Surface),
        shape = RoundedCornerShape(PetHealRadius.lg),
        border = BorderStroke(1.dp, Outline),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("PetHeal", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Primary)
                    Text("Bukti pembayaran Pet Shop", fontSize = 10.sp, color = TextSecondary)
                }
                Surface(
                    shape = RoundedCornerShape(PetHealRadius.pill),
                    color = if (receipt.isSettled) PrimaryLight else WarningSoft
                ) {
                    Row(
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (receipt.isSettled) {
                            Icon(
                                Icons.Filled.CheckCircle,
                                contentDescription = null,
                                tint = Primary,
                                modifier = Modifier.size(13.dp)
                            )
                            Spacer(Modifier.width(4.dp))
                        }
                        Text(
                            receipt.payment?.receiptStatusLabel ?: if (receipt.isSettled) "LUNAS" else "MENUNGGU",
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (receipt.isSettled) Primary else WarningText
                        )
                    }
                }
            }

            Spacer(Modifier.height(6.dp))
            Text(receipt.receiptNumber, fontSize = 10.sp, color = TextTertiary)

            Spacer(Modifier.height(14.dp))
            ReceiptDivider()
            Spacer(Modifier.height(12.dp))

            Text(receipt.clinic?.name.orEmpty(), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = OnBackground)
            Text(receipt.clinic?.address.orEmpty(), fontSize = 11.sp, lineHeight = 16.sp, color = TextSecondary)
            Spacer(Modifier.height(10.dp))
            ReceiptMetaRow("No. pesanan", receipt.orderNumber)
            ReceiptMetaRow("Pelanggan", receipt.customer?.name ?: "-")
            ReceiptMetaRow("Tanggal", formatDate(receipt.timeline?.createdAt))
            ReceiptMetaRow("Pembayaran", receipt.payment?.methodLabel ?: receipt.payment?.method ?: "-")
            ReceiptMetaRow("Status", receipt.statusLabel ?: "-")

            Spacer(Modifier.height(14.dp))
            ReceiptDivider()
            Spacer(Modifier.height(12.dp))

            Text("PRODUK", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = TextTertiary)
            Spacer(Modifier.height(6.dp))

            receipt.items.forEach { item ->
                Column(modifier = Modifier.padding(vertical = 6.dp)) {
                    Text(
                        listOfNotNull(item.productName, item.variantName?.takeIf { it.isNotBlank() }).joinToString(" — "),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = OnBackground
                    )
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            "${item.quantity} × ${formatRupiah(item.unitPrice.toDouble())}",
                            fontSize = 10.sp,
                            color = TextSecondary
                        )
                        Spacer(Modifier.weight(1f))
                        Text(
                            formatRupiah(item.subtotal.toDouble()),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = OnBackground
                        )
                    }
                }
            }

            Spacer(Modifier.height(12.dp))
            ReceiptDivider()
            Spacer(Modifier.height(12.dp))

            val totals = receipt.totals
            if (totals != null) {
                ReceiptTotalRow("Subtotal", totals.subtotal)
                if (totals.discountTotal > 0) ReceiptTotalRow("Diskon", -totals.discountTotal)
                ReceiptTotalRow("Total", totals.totalAmount, emphasize = true)
                if (totals.paidAmount > 0) ReceiptTotalRow("Diterima", totals.paidAmount)
            }

            Spacer(Modifier.height(14.dp))
            Surface(color = SurfaceMuted, shape = RoundedCornerShape(PetHealRadius.md)) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("PENGAMBILAN", fontSize = 9.sp, fontWeight = FontWeight.Bold, color = TextTertiary)
                    Spacer(Modifier.height(4.dp))
                    Text(receipt.pickup?.clinicName.orEmpty(), fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = OnBackground)
                    Text(receipt.pickup?.clinicAddress.orEmpty(), fontSize = 10.sp, lineHeight = 15.sp, color = TextSecondary)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Produk diambil langsung di klinik. Tunjukkan QR Pickup Pass atau kode pengambilan kepada petugas.",
                        fontSize = 10.sp,
                        lineHeight = 15.sp,
                        color = TextSecondary
                    )
                }
            }

            if (!receipt.isSettled) {
                Spacer(Modifier.height(10.dp))
                Surface(color = WarningSoft, shape = RoundedCornerShape(PetHealRadius.md)) {
                    Text(
                        "Pembayaran belum diterima. Bukti ini bukan tanda lunas.",
                        modifier = Modifier.padding(10.dp),
                        fontSize = 10.sp,
                        lineHeight = 14.sp,
                        color = WarningText
                    )
                }
            }
        }
    }
}

@Composable
private fun ReceiptDivider() {
    Surface(color = Outline, modifier = Modifier.fillMaxWidth().height(1.dp)) {}
}

@Composable
private fun ReceiptMetaRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
        Text(label, fontSize = 10.sp, color = TextSecondary, modifier = Modifier.width(96.dp))
        Text(value, fontSize = 11.sp, color = OnBackground, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun ReceiptTotalRow(label: String, amount: Long, emphasize: Boolean = false) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            label,
            fontSize = if (emphasize) 12.sp else 11.sp,
            fontWeight = if (emphasize) FontWeight.Bold else FontWeight.Normal,
            color = if (emphasize) OnBackground else TextSecondary
        )
        Spacer(Modifier.weight(1f))
        Text(
            formatRupiah(amount.toDouble()),
            fontSize = if (emphasize) 14.sp else 12.sp,
            fontWeight = if (emphasize) FontWeight.Bold else FontWeight.SemiBold,
            color = if (emphasize) Primary else OnBackground
        )
    }
}

private fun formatDate(iso: String?): String {
    if (iso.isNullOrBlank()) return "-"
    return runCatching {
        val parsed = java.time.OffsetDateTime.parse(iso)
            .atZoneSameInstant(java.time.ZoneId.of("Asia/Jakarta"))
        parsed.format(java.time.format.DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm"))
    }.getOrDefault(iso)
}
