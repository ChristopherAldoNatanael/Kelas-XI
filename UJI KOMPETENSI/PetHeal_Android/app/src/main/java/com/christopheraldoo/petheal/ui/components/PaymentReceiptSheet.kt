package com.christopheraldoo.petheal.ui.components

import android.os.Build
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material.icons.filled.Share
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.ui.theme.Background
import com.christopheraldoo.petheal.ui.theme.ClinicTheme
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.OutlineStrong
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.util.PaymentReceipt
import com.christopheraldoo.petheal.util.ReceiptStatus
import com.christopheraldoo.petheal.util.formatRupiah
import com.christopheraldoo.petheal.util.toPaymentReceipt

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PaymentReceiptSheet(
    booking: Booking,
    clinicName: String?,
    clinicAddress: String?,
    clinicColor: String?,
    orderId: String? = null,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val accent = ClinicTheme.parsePrimaryColor(clinicColor)
    val receipt = remember(booking, clinicName, clinicAddress, orderId) {
        booking.toPaymentReceipt(clinicName, clinicAddress, orderId)
    }
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    val saveLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.CreateDocument("image/png")
    ) { uri ->
        if (uri != null) {
            val bitmap = createPaymentReceiptBitmap(receipt, accent)
            val saved = writeReceiptToUri(context, bitmap, uri)
            Toast.makeText(
                context,
                if (saved) "Bukti pembayaran disimpan" else "Gagal menyimpan gambar",
                Toast.LENGTH_SHORT
            ).show()
        }
    }

    fun saveImage() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val bitmap = createPaymentReceiptBitmap(receipt, accent)
                val savedUri = saveReceiptToGallery(context, bitmap, receipt.fileName())
                Toast.makeText(
                    context,
                    if (savedUri != null) {
                        "Bukti pembayaran disimpan ke Galeri/PetHeal"
                    } else {
                        "Gagal menyimpan gambar"
                    },
                    Toast.LENGTH_SHORT
                ).show()
            } else {
                saveLauncher.launch(receipt.fileName())
            }
        } catch (_: Exception) {
            Toast.makeText(context, "Gagal membuat gambar bukti", Toast.LENGTH_SHORT).show()
        }
    }

    fun shareImage() {
        val shared = runCatching {
            val bitmap = createPaymentReceiptBitmap(receipt, accent)
            shareReceiptImage(context, bitmap, receipt)
        }.getOrDefault(false)

        if (!shared) {
            copyReceiptText(context, receipt)
            Toast.makeText(
                context,
                "Gambar tidak dapat dibagikan. Teks bukti sudah disalin.",
                Toast.LENGTH_SHORT
            ).show()
        }
    }

    fun copyText() {
        val copied = copyReceiptText(context, receipt)
        Toast.makeText(
            context,
            if (copied) "Teks bukti pembayaran disalin" else "Gagal menyalin teks",
            Toast.LENGTH_SHORT
        ).show()
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = Background,
        shape = RoundedCornerShape(topStart = 28.dp, topEnd = 28.dp),
        dragHandle = {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 10.dp, bottom = 4.dp),
                contentAlignment = Alignment.Center
            ) {
                Box(
                    Modifier
                        .width(40.dp)
                        .height(4.dp)
                        .background(OutlineStrong, RoundedCornerShape(2.dp))
                )
            }
        }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.92f)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        "Bukti pembayaran",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                    Text(
                        receipt.invoiceNumber,
                        fontSize = 12.sp,
                        color = TextSecondary
                    )
                }
                IconButton(onClick = onDismiss) {
                    Icon(Icons.Filled.Close, contentDescription = "Tutup", tint = TextSecondary)
                }
            }

            Column(
                modifier = Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp)
            ) {
                ReceiptPaper(receipt = receipt, accent = accent)
                Spacer(Modifier.height(16.dp))
            }

            Surface(
                color = SurfaceColor,
                shadowElevation = 12.dp,
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier
                        .navigationBarsPadding()
                        .padding(horizontal = 16.dp, vertical = 12.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(
                        onClick = ::saveImage,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(50.dp),
                        shape = RoundedCornerShape(14.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Primary,
                            contentColor = OnPrimaryStrong
                        )
                    ) {
                        Icon(Icons.Filled.Download, contentDescription = null, modifier = Modifier.size(19.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Simpan gambar", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(
                            onClick = ::shareImage,
                            modifier = Modifier
                                .weight(1f)
                                .height(46.dp),
                            shape = RoundedCornerShape(13.dp)
                        ) {
                            Icon(Icons.Filled.Share, contentDescription = null, modifier = Modifier.size(17.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Bagikan", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        }
                        TextButton(
                            onClick = ::copyText,
                            modifier = Modifier
                                .weight(1f)
                                .height(46.dp)
                        ) {
                            Icon(Icons.Filled.ContentCopy, contentDescription = null, modifier = Modifier.size(17.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Salin teks", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                        }
                    }
                    Text(
                        "PNG resolusi tinggi • siap disimpan atau dibagikan",
                        modifier = Modifier.fillMaxWidth(),
                        fontSize = 10.sp,
                        color = TextTertiary,
                        textAlign = TextAlign.Center
                    )
                }
            }
        }
    }
}

@Composable
fun PaymentReceiptPreviewCard(
    booking: Booking,
    clinicName: String?,
    clinicAddress: String?,
    clinicColor: String?,
    orderId: String? = null,
    onOpen: () -> Unit,
    modifier: Modifier = Modifier
) {
    val receipt = remember(booking, clinicName, clinicAddress, orderId) {
        booking.toPaymentReceipt(clinicName, clinicAddress, orderId)
    }
    val accent = ClinicTheme.parsePrimaryColor(clinicColor)
    val statusColor = receiptStatusTone(receipt.status, accent)

    Surface(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        color = SurfaceColor,
        border = androidx.compose.foundation.BorderStroke(1.dp, Outline)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = accent.copy(alpha = 0.12f)
                ) {
                    Box(Modifier.size(40.dp), contentAlignment = Alignment.Center) {
                        Icon(
                            Icons.Filled.ReceiptLong,
                            contentDescription = null,
                            tint = accent,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                }
                Spacer(Modifier.width(10.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        "Bukti pembayaran",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                    Text(
                        receipt.invoiceNumber,
                        fontSize = 11.sp,
                        color = TextSecondary
                    )
                }
                ReceiptStatusPill(receipt.status, statusColor)
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.Bottom
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("Dibayar", fontSize = 11.sp, color = TextTertiary)
                    Text(
                        formatRupiah(receipt.paidAmount),
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                }
                if (receipt.hasOutstanding) {
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Sisa", fontSize = 11.sp, color = TextTertiary)
                        Text(
                            formatRupiah(receipt.remainingAmount),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = WarningDeep
                        )
                    }
                }
            }

            Text(
                when {
                    receipt.status == ReceiptStatus.DP ->
                        "Bukti DP tersedia. Unduh atau bagikan sebagai bukti pembayaran resmi."
                    receipt.hasOutstanding ->
                        "Bukti pembayaran sebagian tersedia. Unduh atau bagikan sebagai bukti resmi."
                    else ->
                        "Pembayaran lunas. Unduh atau bagikan bukti pembayaran resmi."
                },
                fontSize = 11.sp,
                color = TextSecondary,
                lineHeight = 16.sp
            )

            OutlinedButton(
                onClick = onOpen,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(42.dp),
                shape = RoundedCornerShape(12.dp),
                border = androidx.compose.foundation.BorderStroke(1.dp, accent.copy(alpha = 0.45f)),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = accent)
            ) {
                Text("Lihat bukti pembayaran", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
            }
        }
    }
}

@Composable
private fun ReceiptPaper(receipt: PaymentReceipt, accent: Color) {
    val statusColor = receiptStatusTone(receipt.status, accent)

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        color = SurfaceColor,
        border = androidx.compose.foundation.BorderStroke(1.dp, Outline)
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        receipt.clinicName.ifBlank { "Klinik" },
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (receipt.clinicAddress.isNotBlank()) {
                        Text(
                            receipt.clinicAddress,
                            fontSize = 11.sp,
                            color = TextSecondary,
                            maxLines = 2,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "BUKTI PEMBAYARAN",
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold,
                        color = TextTertiary,
                        letterSpacing = 1.sp
                    )
                    Text(
                        receipt.invoiceNumber,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = OnBackground
                    )
                }
                ReceiptStatusPill(receipt.status, statusColor)
            }

            DashedDivider()

            Text(
                if (receipt.status == ReceiptStatus.DP) "DP DIBAYAR" else "TOTAL DIBAYAR",
                fontSize = 10.sp,
                fontWeight = FontWeight.Bold,
                color = TextTertiary,
                letterSpacing = 1.sp
            )
            Text(
                formatRupiah(receipt.paidAmount),
                fontSize = 30.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground
            )
            Text(
                if (receipt.hasOutstanding) {
                    "Sisa ${formatRupiah(receipt.remainingAmount)}"
                } else {
                    "Pembayaran lunas — terima kasih"
                },
                fontSize = 12.sp,
                color = if (receipt.hasOutstanding) WarningDeep else Success
            )

            DashedDivider()

            ReceiptDetailRow("Hewan", receipt.petName)
            ReceiptDetailRow("Dokter", receipt.doctorName)
            ReceiptDetailRow("Layanan", receipt.serviceName)
            ReceiptDetailRow("Jadwal", receipt.schedule)
            ReceiptDetailRow("Metode", receipt.paymentMethod)
            ReceiptDetailRow("Jenis", receipt.paymentType)

            DashedDivider()

            ReceiptAmountRow("Total", formatRupiah(receipt.totalAmount))
            if (receipt.dpAmount > 0.01) {
                ReceiptAmountRow("DP awal", formatRupiah(receipt.dpAmount))
            }
            ReceiptAmountRow(
                "Dibayar",
                formatRupiah(receipt.paidAmount),
                valueColor = statusColor,
                bold = true
            )
            ReceiptAmountRow(
                "Sisa",
                formatRupiah(receipt.remainingAmount),
                valueColor = if (receipt.hasOutstanding) WarningDeep else statusColor,
                bold = true
            )

            DashedDivider()

            receipt.reference?.takeIf { it.isNotBlank() }?.let {
                ReceiptDetailRow("Referensi", it)
            }
            ReceiptDetailRow("Diterbitkan", receipt.issuedAt)

            Text(
                "Dokumen ini dibuat otomatis dan tidak memerlukan tanda tangan.",
                fontSize = 10.sp,
                color = TextTertiary,
                lineHeight = 15.sp
            )
        }
    }
}

@Composable
private fun ReceiptStatusPill(status: ReceiptStatus, color: Color) {
    Surface(
        shape = RoundedCornerShape(999.dp),
        color = color.copy(alpha = 0.12f)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Icon(
                Icons.Filled.CheckCircle,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(13.dp)
            )
            Text(
                status.label,
                fontSize = 9.sp,
                fontWeight = FontWeight.Bold,
                color = color
            )
        }
    }
}

@Composable
private fun ReceiptDetailRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Text(
            label,
            modifier = Modifier.width(82.dp),
            fontSize = 11.sp,
            color = TextTertiary
        )
        Text(
            value,
            modifier = Modifier.weight(1f),
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = OnBackground,
            textAlign = TextAlign.End,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun ReceiptAmountRow(
    label: String,
    value: String,
    valueColor: Color = OnBackground,
    bold: Boolean = false
) {
    Row(modifier = Modifier.fillMaxWidth()) {
        Text(
            label,
            modifier = Modifier.weight(1f),
            fontSize = 12.sp,
            color = TextSecondary
        )
        Text(
            value,
            fontSize = 12.sp,
            fontWeight = if (bold) FontWeight.Bold else FontWeight.SemiBold,
            color = valueColor
        )
    }
}

@Composable
private fun DashedDivider() {
    Canvas(
        modifier = Modifier
            .fillMaxWidth()
            .height(1.dp)
    ) {
        drawLine(
            color = Outline,
            start = androidx.compose.ui.geometry.Offset(0f, 0f),
            end = androidx.compose.ui.geometry.Offset(size.width, 0f),
            strokeWidth = 1.dp.toPx(),
            pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 8f))
        )
    }
}

private fun receiptStatusTone(status: ReceiptStatus, accent: Color): Color = when (status) {
    ReceiptStatus.PAID -> accent
    ReceiptStatus.DP -> Tertiary
    ReceiptStatus.PARTIAL -> InfoStrong
    ReceiptStatus.PENDING -> WarningDeep
}
