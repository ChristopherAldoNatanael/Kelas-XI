package com.christopheraldoo.petheal.ui.screens.booking

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
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ReceiptLong
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Divider
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.ui.theme.ErrorStrong
import com.christopheraldoo.petheal.ui.theme.InfoStrong
import com.christopheraldoo.petheal.ui.theme.OnBackground
import com.christopheraldoo.petheal.ui.theme.OnPrimaryStrong
import com.christopheraldoo.petheal.ui.theme.Outline
import com.christopheraldoo.petheal.ui.theme.Primary
import com.christopheraldoo.petheal.ui.theme.Success
import com.christopheraldoo.petheal.ui.theme.SuccessSoft
import com.christopheraldoo.petheal.ui.theme.Surface as SurfaceColor
import com.christopheraldoo.petheal.ui.theme.SurfaceMuted
import com.christopheraldoo.petheal.ui.theme.Tertiary
import com.christopheraldoo.petheal.ui.theme.TextSecondary
import com.christopheraldoo.petheal.ui.theme.TextTertiary
import com.christopheraldoo.petheal.ui.theme.WarningDeep
import com.christopheraldoo.petheal.ui.theme.WarningSoft
import com.christopheraldoo.petheal.util.formatRupiah
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

private enum class InvoiceFilter(val label: String) {
    ALL("Semua"),
    OUTSTANDING("Belum lunas"),
    DP("DP aktif"),
    PAID("Lunas")
}

/**
 * Invoice center for the customer app. It is intentionally derived from the
 * existing Booking rows: the backend already stores total, paid, remaining and
 * payment_status, so the UI must never recalculate a different amount.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun InvoiceCenter(
    bookings: List<Booking>,
    isLoading: Boolean,
    error: String?,
    onOpenBooking: (Int) -> Unit,
    onPay: (Booking) -> Unit,
    onOpenReceipt: (Booking) -> Unit,
    modifier: Modifier = Modifier
) {
    var filter by remember { mutableStateOf(InvoiceFilter.ALL) }

    val invoices = remember(bookings) {
        bookings.sortedByDescending { it.bookingDate.orEmpty() }
    }
    val outstanding = remember(invoices) {
        invoices.filter { it.isOutstanding() }
    }
    val dpInvoices = remember(invoices) {
        invoices.filter { it.isDpOutstanding() }
    }
    val paid = remember(invoices) {
        invoices.filter { !it.isOutstanding() }
    }
    val visibleInvoices = remember(invoices, filter) {
        when (filter) {
            InvoiceFilter.ALL -> invoices
            InvoiceFilter.OUTSTANDING -> outstanding
            InvoiceFilter.DP -> dpInvoices
            InvoiceFilter.PAID -> paid
        }
    }
    val outstandingTotal = remember(outstanding) {
        outstanding.sumOf { it.remainingAmount ?: 0.0 }
    }

    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(start = 16.dp, top = 16.dp, end = 16.dp, bottom = 108.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        if (error != null) {
            item { ErrorBanner(error) }
        }

        item {
            InvoiceSummaryRow(
                outstandingCount = outstanding.size,
                outstandingTotal = outstandingTotal,
                dpCount = dpInvoices.size,
                paidCount = paid.size
            )
        }

        item {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(InvoiceFilter.entries) { option ->
                    FilterChip(
                        selected = filter == option,
                        onClick = { filter = option },
                        label = {
                            Text(
                                text = option.label,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = Primary.copy(alpha = 0.14f),
                            selectedLabelColor = OnPrimaryStrong,
                            containerColor = SurfaceColor,
                            labelColor = TextSecondary
                        )
                    )
                }
            }
        }

        if (isLoading && invoices.isEmpty()) {
            item {
                Column(
                    modifier = Modifier.fillMaxWidth().padding(vertical = 40.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(SurfaceMuted)
                    )
                    Text("Memuat tagihan...", fontSize = 13.sp, color = TextSecondary)
                }
            }
        } else if (visibleInvoices.isEmpty()) {
            item {
                InvoiceEmptyState(
                    hasAnyInvoice = invoices.isNotEmpty(),
                    filter = filter
                )
            }
        } else {
            items(
                items = visibleInvoices,
                key = { booking -> booking.id ?: booking.hashCode() }
            ) { booking ->
                InvoiceCard(
                    booking = booking,
                    onOpen = { booking.id?.let(onOpenBooking) },
                    onPay = { onPay(booking) },
                    onOpenReceipt = { onOpenReceipt(booking) }
                )
            }
        }
    }
}

@Composable
private fun InvoiceSummaryRow(
    outstandingCount: Int,
    outstandingTotal: Double,
    dpCount: Int,
    paidCount: Int
) {
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            InvoiceSummaryCard(
                modifier = Modifier.weight(1.35f),
                label = "Belum lunas",
                value = outstandingCount.toString(),
                detail = formatRupiah(outstandingTotal),
                accent = WarningDeep,
                background = WarningSoft
            )
            InvoiceSummaryCard(
                modifier = Modifier.weight(1f),
                label = "DP aktif",
                value = dpCount.toString(),
                detail = "Cicilan berjalan",
                accent = Tertiary,
                background = Tertiary.copy(alpha = 0.10f)
            )
            InvoiceSummaryCard(
                modifier = Modifier.weight(1f),
                label = "Lunas",
                value = paidCount.toString(),
                detail = "Selesai",
                accent = Success,
                background = SuccessSoft
            )
        }
    }
}

@Composable
private fun InvoiceSummaryCard(
    modifier: Modifier,
    label: String,
    value: String,
    detail: String,
    accent: Color,
    background: Color
) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = background),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(3.dp)
        ) {
            Text(
                text = label,
                fontSize = 10.sp,
                fontWeight = FontWeight.SemiBold,
                color = accent
            )
            Text(
                text = value,
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = OnBackground
            )
            Text(
                text = detail,
                fontSize = 10.sp,
                color = accent,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun InvoiceCard(
    booking: Booking,
    onOpen: () -> Unit,
    onPay: () -> Unit,
    onOpenReceipt: () -> Unit
) {
    val outstanding = booking.isOutstanding()
    val status = booking.invoiceStatus()
    val hasPaymentProof = (booking.paidAmount ?: 0.0) > 0.01 ||
        booking.paymentStatus?.lowercase() in setOf("paid", "dp_paid", "partial")

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(18.dp),
        color = SurfaceColor,
        border = BorderStroke(1.dp, Outline)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(
                        text = "INV-${booking.id?.toString()?.padStart(5, '0') ?: "----"}",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = OnBackground
                    )
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.CalendarMonth,
                            contentDescription = null,
                            tint = TextTertiary,
                            modifier = Modifier.size(12.dp)
                        )
                        Text(
                            text = booking.displaySchedule(),
                            fontSize = 11.sp,
                            color = TextSecondary
                        )
                    }
                }
                InvoiceStatusPill(label = status.first, color = status.second)
            }

            Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(
                    text = "${booking.pet?.name ?: "Hewan"} · ${booking.doctor?.name ?: "Dokter"}",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = OnBackground,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = booking.service?.name ?: "Layanan klinik",
                    fontSize = 12.sp,
                    color = TextSecondary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }

            Divider(color = Outline)

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                InvoiceAmount(label = "Total", value = booking.totalAmount ?: 0.0, color = OnBackground)
                InvoiceAmount(label = "Dibayar", value = booking.paidAmount ?: 0.0, color = Success)
                InvoiceAmount(
                    label = if (outstanding) "Sisa" else "Status",
                    value = if (outstanding) booking.remainingAmount ?: 0.0 else null,
                    textValue = if (outstanding) null else "Lunas",
                    color = if (outstanding) WarningDeep else Success,
                    alignEnd = true
                )
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onOpen,
                    modifier = Modifier.weight(1f).height(44.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, Outline),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = OnBackground)
                ) {
                    Text("Detail", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)
                }

                if (hasPaymentProof) {
                    OutlinedButton(
                        onClick = onOpenReceipt,
                        modifier = Modifier.weight(1f).height(44.dp),
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, Primary.copy(alpha = 0.45f)),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Primary)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.ReceiptLong,
                            contentDescription = null,
                            modifier = Modifier.size(15.dp)
                        )
                        Spacer(Modifier.width(4.dp))
                        Text("Bukti", fontWeight = FontWeight.SemiBold, fontSize = 12.sp)
                    }
                }

                if (outstanding) {
                    Button(
                        onClick = onPay,
                        modifier = Modifier.weight(if (hasPaymentProof) 1.15f else 1.25f).height(44.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Primary,
                            contentColor = OnPrimaryStrong
                        )
                    ) {
                        Text(
                            text = if (hasPaymentProof) "Lunasi" else "Bayar",
                            fontWeight = FontWeight.Bold,
                            fontSize = 13.sp
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun InvoiceAmount(
    label: String,
    value: Double? = null,
    color: Color,
    textValue: String? = null,
    alignEnd: Boolean = false
) {
    Column(
        horizontalAlignment = if (alignEnd) Alignment.End else Alignment.Start,
        verticalArrangement = Arrangement.spacedBy(2.dp)
    ) {
        Text(text = label, fontSize = 10.sp, color = TextTertiary)
        Text(
            text = textValue ?: formatRupiah(value ?: 0.0),
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = color
        )
    }
}

@Composable
private fun InvoiceStatusPill(label: String, color: Color) {
    Surface(
        shape = RoundedCornerShape(999.dp),
        color = color.copy(alpha = 0.12f)
    ) {
        Text(
            text = label,
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold,
            color = color,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp)
        )
    }
}

@Composable
private fun InvoiceEmptyState(hasAnyInvoice: Boolean, filter: InvoiceFilter) {
    Column(
        modifier = Modifier.fillMaxWidth().padding(vertical = 48.dp, horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Icon(
            imageVector = Icons.Filled.ReceiptLong,
            contentDescription = null,
            tint = TextTertiary,
            modifier = Modifier.size(40.dp)
        )
        Text(
            text = when {
                !hasAnyInvoice -> "Belum ada tagihan"
                filter == InvoiceFilter.ALL -> "Belum ada tagihan"
                else -> "Tidak ada tagihan di kategori ini"
            },
            fontSize = 15.sp,
            fontWeight = FontWeight.SemiBold,
            color = OnBackground
        )
        Text(
            text = if (hasAnyInvoice) {
                "Pilih kategori lain untuk melihat tagihan yang tersedia."
            } else {
                "Tagihan akan muncul otomatis setelah booking dibuat."
            },
            fontSize = 12.sp,
            color = TextSecondary,
            textAlign = androidx.compose.ui.text.style.TextAlign.Center
        )
    }
}

private fun Booking.isOutstanding(): Boolean {
    val remaining = remainingAmount ?: ((totalAmount ?: 0.0) - (paidAmount ?: 0.0))
    val status = paymentStatus?.lowercase()
    return remaining > 0.01 && status !in setOf("paid", "not_required")
}

private fun Booking.isDpOutstanding(): Boolean {
    return isOutstanding() && paymentType == "dp" && (paidAmount ?: 0.0) > 0.0
}

private fun Booking.invoiceStatus(): Pair<String, Color> {
    val status = paymentStatus?.lowercase()
    return when {
        !isOutstanding() -> "Lunas" to Success
        isDpOutstanding() -> "DP terbayar" to Tertiary
        status == "pending" -> "Menunggu bayar" to InfoStrong
        status == "failed" -> "Gagal" to ErrorStrong
        else -> "Belum dibayar" to WarningDeep
    }
}

private fun Booking.displaySchedule(): String {
    val rawDate = bookingDate
    if (rawDate.isNullOrBlank()) return bookingTime ?: "Jadwal belum tersedia"
    val formattedDate = runCatching {
        LocalDate.parse(rawDate).format(
            DateTimeFormatter.ofPattern("d MMM yyyy", Locale("id", "ID"))
        )
    }.getOrDefault(rawDate)
    return listOfNotNull(formattedDate, bookingTime).joinToString(" · ")
}
