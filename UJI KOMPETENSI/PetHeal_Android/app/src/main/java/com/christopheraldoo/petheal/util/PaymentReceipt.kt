package com.christopheraldoo.petheal.util

import com.christopheraldoo.petheal.data.model.Booking
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

/**
 * Satu sumber data untuk bukti pembayaran. Nilai uang SELALU diambil dari
 * Booking yang dikirim backend; layar tidak boleh menghitung ulang total.
 */
data class PaymentReceipt(
    val invoiceNumber: String,
    val clinicName: String,
    val clinicAddress: String,
    val status: ReceiptStatus,
    val petName: String,
    val doctorName: String,
    val serviceName: String,
    val schedule: String,
    val paymentMethod: String,
    val paymentType: String,
    val totalAmount: Double,
    val dpAmount: Double,
    val paidAmount: Double,
    val remainingAmount: Double,
    val reference: String?,
    val issuedAt: String
) {
    val hasOutstanding: Boolean
        get() = remainingAmount > 0.01 && status != ReceiptStatus.PAID

    fun fileName(): String =
        "Bukti-Pembayaran-${invoiceNumber.replace(Regex("[^A-Za-z0-9-]"), "-")}.png"

    fun pdfFileName(): String =
        "Bukti-Pembayaran-${invoiceNumber.replace(Regex("[^A-Za-z0-9-]"), "-")}.pdf"

    fun asText(): String = buildString {
        appendLine(clinicName.ifBlank { "Klinik" })
        if (clinicAddress.isNotBlank()) appendLine(clinicAddress)
        appendLine("BUKTI PEMBAYARAN")
        appendLine(invoiceNumber)
        appendLine()
        appendLine("Status: ${status.label}")
        appendLine("Hewan: $petName")
        appendLine("Dokter: $doctorName")
        appendLine("Layanan: $serviceName")
        appendLine("Jadwal: $schedule")
        appendLine("Metode: $paymentMethod")
        appendLine("Jenis: $paymentType")
        appendLine()
        appendLine("Total: ${formatRupiah(totalAmount)}")
        if (dpAmount > 0.01) {
            appendLine("DP awal: ${formatRupiah(dpAmount)}")
        }
        appendLine("Dibayar: ${formatRupiah(paidAmount)}")
        appendLine("Sisa: ${formatRupiah(remainingAmount)}")
        appendLine()
        reference?.takeIf { it.isNotBlank() }?.let { appendLine("Referensi: $it") }
        appendLine("Diterbitkan: $issuedAt")
        appendLine()
        appendLine("Dokumen ini dibuat otomatis oleh ${clinicName.ifBlank { "klinik" }}.")
        append("Simpan sebagai bukti pembayaran resmi.")
    }
}

enum class ReceiptStatus(val label: String) {
    PAID("LUNAS"),
    DP("DP TERBAYAR"),
    PARTIAL("DIBAYAR SEBAGIAN"),
    PENDING("MENUNGGU PEMBAYARAN")
}

private val receiptDateFormatter =
    DateTimeFormatter.ofPattern("d MMM yyyy", Locale("id", "ID"))
private val receiptTimeFormatter =
    DateTimeFormatter.ofPattern("d MMM yyyy, HH:mm", Locale("id", "ID"))

fun Booking.toPaymentReceipt(
    clinicName: String?,
    clinicAddress: String?,
    reference: String? = null
): PaymentReceipt {
    val total = totalAmount ?: 0.0
    val paid = paidAmount ?: 0.0
    val remaining = remainingAmount ?: (total - paid).coerceAtLeast(0.0)
    val normalizedStatus = paymentStatus?.lowercase()
    val outstanding = remaining > 0.01 && normalizedStatus !in setOf("paid", "not_required")

    val status = when {
        !outstanding -> ReceiptStatus.PAID
        paymentType == "dp" && paid > 0.01 -> ReceiptStatus.DP
        paid > 0.01 -> ReceiptStatus.PARTIAL
        else -> ReceiptStatus.PENDING
    }

    return PaymentReceipt(
        invoiceNumber = invoiceNumber(),
        clinicName = clinicName.orEmpty().trim(),
        clinicAddress = clinicAddress.orEmpty().trim(),
        status = status,
        petName = pet?.name?.takeIf { it.isNotBlank() } ?: "-",
        doctorName = doctor?.name?.takeIf { it.isNotBlank() } ?: "-",
        serviceName = service?.name?.takeIf { it.isNotBlank() } ?: "-",
        schedule = receiptSchedule(),
        paymentMethod = paymentMethod?.takeIf { it.isNotBlank() } ?: "-",
        paymentType = when {
            paymentType == "dp" && !outstanding -> "DP + pelunasan"
            paymentType == "dp" -> "Uang muka (DP)"
            else -> "Pembayaran penuh"
        },
        totalAmount = total,
        dpAmount = dpAmount ?: 0.0,
        paidAmount = paid,
        remainingAmount = remaining,
        reference = reference?.takeIf { it.isNotBlank() } ?: id?.let { "BOOKING-$it" },
        issuedAt = LocalDateTime.now().format(receiptTimeFormatter)
    )
}

fun Booking.invoiceNumber(): String =
    "INV-${id?.toString()?.padStart(5, '0') ?: "----"}"

private fun Booking.receiptSchedule(): String {
    val date = receiptBookingDate() ?: return bookingTime?.take(5) ?: "-"
    return listOfNotNull(date, bookingTime?.take(5)).joinToString(" · ")
}

private fun Booking.receiptBookingDate(): String? {
    val raw = bookingDate?.trim().orEmpty()
    if (raw.isBlank()) return null
    return runCatching {
        LocalDate.parse(raw.take(10)).format(receiptDateFormatter)
    }.getOrElse { raw.take(10).takeIf { it.isNotBlank() } }
}
