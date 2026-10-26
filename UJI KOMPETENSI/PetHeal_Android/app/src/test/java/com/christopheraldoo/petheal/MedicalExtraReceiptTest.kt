package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.ui.navigation.Screen
import com.christopheraldoo.petheal.util.ReceiptStatus
import com.christopheraldoo.petheal.util.toExtraPaymentReceipt
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Bukti pembayaran BIAYA TAMBAHAN rekam medis (permintaan setelah testing
 * fisik): sekualitas bukti booking + rincian tindakan/obat, semua angka dari
 * payload backend, tanpa data klinis.
 */
class MedicalExtraReceiptTest {

    private fun paidRecord(
        treatment: Double? = 120_000.0,
        medicine: Double? = 45_500.0,
        extra: Double? = 165_500.0,
        paid: Double? = 165_500.0,
        status: String? = "paid",
        date: String? = "2026-10-25 14:33:00"
    ) = MedicalRecord(
        id = 7,
        bookingId = 42,
        createdAt = "2026-10-24 09:15:00",
        // Data klinis tekstual sengaja diisi: bukti keuangan tidak boleh
        // pernah menampilkannya ( asserted di test di bawah ).
        diagnosis = "GANOMAN — jangan pernah muncul di bukti",
        treatment = "Tindakan textual klinis — bukan dokumen keuangan",
        medicine = "Obat textual klinis",
        treatmentCost = treatment,
        medicineCost = medicine,
        extraPaymentAmount = extra,
        extraPaymentPaidAmount = paid,
        extraPaymentStatus = status,
        extraPaymentDate = date
    )

    @Test
    fun `fully paid extra builds PAID receipt with itemized lines`() {
        val receipt = paidRecord().toExtraPaymentReceipt(
            clinicName = "Klinik Sehat",
            clinicAddress = "Jl. Melati 1",
            referenceOrderId = "MEDREC-7-abc",
            paymentMethodLabel = "gopay"
        )
        assertEquals(ReceiptStatus.PAID, receipt.status)
        assertEquals("INV-RM-00007", receipt.invoiceNumber)
        assertEquals("Total biaya tambahan", receipt.totalLabel)
        assertEquals(165_500.0, receipt.totalAmount, 0.001)
        assertEquals(165_500.0, receipt.paidAmount, 0.001)
        assertEquals(0.0, receipt.remainingAmount, 0.001)
        assertFalse(receipt.hasOutstanding)
        assertEquals(
            listOf("Biaya tindakan" to "Rp 120.000", "Biaya obat" to "Rp 45.500"),
            receipt.itemLines
        )
        assertEquals("MEDREC-7-abc", receipt.reference)
        // Tanggal terbit memakai extra_payment_date nyata, bukan now().
        assertEquals("25 Okt 2026, 14:33", receipt.issuedAt)
        assertEquals("gopay", receipt.paymentMethod)
    }

    @Test
    fun `receipt text never carries clinical data`() {
        val text = paidRecord().toExtraPaymentReceipt("Klinik", null, null, null).asText()
        listOf("GANOMAN", "Tindakan textual klinis", "Obat textual klinis").forEach {
            assertFalse("bukti keuangan tidak boleh memuat: $it", text.contains(it))
        }
        assertTrue(text.contains("Biaya tindakan: Rp 120.000"))
        assertTrue(text.contains("Total biaya tambahan: Rp 165.500"))
    }

    @Test
    fun `partial payment maps PARTIAL with honest remaining`() {
        val receipt = paidRecord(paid = 60_000.0, status = "partial")
            .toExtraPaymentReceipt("Klinik", null, null, null)
        assertEquals(ReceiptStatus.PARTIAL, receipt.status)
        assertEquals(105_500.0, receipt.remainingAmount, 0.001)
        assertTrue(receipt.hasOutstanding)
    }

    @Test
    fun `paid status without explicit paid amount falls back to owed amount`() {
        val receipt = paidRecord(paid = null, status = "paid")
            .toExtraPaymentReceipt("Klinik", null, null, null)
        assertEquals(165_500.0, receipt.paidAmount, 0.001)
        assertEquals(ReceiptStatus.PAID, receipt.status)
    }

    @Test
    fun `zero cost components produce no fabricated detail lines`() {
        val receipt = paidRecord(treatment = 0.0, medicine = 0.0)
            .toExtraPaymentReceipt("Klinik", null, null, null)
        assertTrue(receipt.itemLines.isEmpty())
    }

    @Test
    fun `missing payment date falls back to current time without crashing`() {
        val receipt = paidRecord(date = null)
            .toExtraPaymentReceipt("Klinik", null, null, null)
        assertTrue(receipt.issuedAt.isNotBlank())
    }

    // ── Route result: parameter bukti tambahan ───────────────────────────────

    @Test
    fun `createRoute appends recordId and method only when present`() {
        val bookingRoute = Screen.PaymentResult.createRoute("o/1", "success", "Pembayaran berhasil!", 42)
        assertTrue(bookingRoute.contains("?bookingId=42"))
        assertFalse(bookingRoute.contains("recordId"))
        assertFalse(bookingRoute.contains("method"))

        val extraRoute = Screen.PaymentResult.createRoute(
            "MEDREC-7", "success", "Pembayaran tambahan rekam medis berhasil!",
            bookingId = 42, recordId = 7, method = "gopay"
        )
        assertTrue(extraRoute.contains("bookingId=42"))
        assertTrue(extraRoute.contains("&recordId=7"))
        assertTrue(extraRoute.contains("&method=gopay"))

        val extraNoBooking = Screen.PaymentResult.createRoute(
            "MEDREC-7", "success", "Berhasil", recordId = 7, method = "QRIS"
        )
        assertTrue(extraNoBooking.contains("?recordId=7"))
        assertTrue(extraNoBooking.contains("&method=QRIS"))
    }

    @Test
    fun `method parameter is sanitized to url-safe token`() {
        val route = Screen.PaymentResult.createRoute(
            "o", "success", "ok", recordId = 7, method = "bad method/with spaces"
        )
        assertFalse(route.contains("method"))
    }
}
