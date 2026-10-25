package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.ui.navigation.Screen
import com.christopheraldoo.petheal.ui.screens.booking.CreateBookingUiState
import com.christopheraldoo.petheal.util.parseDecimalInput
import java.net.URLDecoder
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Regression tests untuk batch perbaikan pra-ujian (2026-10-25):
 * parsing berat terpusat, encoding pesan PaymentResult, default harga
 * booking non-dummy, dan pola route untuk popUpTo.
 */
class DemoFixesRegressionTest {

    // ── Bug 4: input berat "5,8" vs "5.8" ────────────────────────────────────
    @Test
    fun `parseDecimalInput menerima koma dan titik desimal`() {
        assertEquals(5.8, parseDecimalInput("5,8")!!, 1e-9)
        assertEquals(5.8, parseDecimalInput("5.8")!!, 1e-9)
        assertEquals(6.75, parseDecimalInput("  6,75  ")!!, 1e-9)
        assertEquals(58.0, parseDecimalInput("58")!!, 1e-9)
    }

    @Test
    fun `parseDecimalInput menolak nilai ambigu dan tidak valid`() {
        assertNull(parseDecimalInput(null))
        assertNull(parseDecimalInput(""))
        assertNull(parseDecimalInput("   "))
        assertNull("separator campur", parseDecimalInput("5,8.2"))
        assertNull("titik ganda", parseDecimalInput("5.8.2"))
        assertNull("koma ganda", parseDecimalInput("5,8,2"))
        assertNull("tanpa angka", parseDecimalInput("abc"))
        assertNull("koma di depan", parseDecimalInput(",5"))
        assertNull("titik di ujung", parseDecimalInput("5."))
        assertNull("negatif", parseDecimalInput("-5"))
        assertNull("nol kg", parseDecimalInput("0"))
        assertNull("di atas rentang", parseDecimalInput("600"))
    }

    @Test
    fun `contoh pada placeholder dialog benar-benar bisa disimpan`() {
        // Placeholder berbunyi "Contoh: 5,8" — contoh itu WAJIB valid.
        assertNotNull(parseDecimalInput("5,8"))
    }

    // ── Bug 2: encoding pesan PaymentResult ──────────────────────────────────
    @Test
    fun `pesan dengan spasi di-route sebagai percent-encoding bukan plus`() {
        val route = Screen.PaymentResult.createRoute(
            "ORDER-1", "success", "Pembayaran menunggu. Segera selesaikan."
        )
        val segment = route.substringAfter("payment-result/ORDER-1/success/")
            .substringBefore("?")
        assertFalse("'+' tidak boleh mewakili spasi", segment.contains('+'))
        assertTrue("spasi menjadi %20", segment.contains("%20"))
        // Decode satu kali (perilaku Navigation) mengembalikan teks utuh.
        val decoded = URLDecoder.decode(segment, "UTF-8")
        assertEquals("Pembayaran menunggu. Segera selesaikan.", decoded)
    }

    @Test
    fun `karakter plus persen unicode aman tanpa double encoding`() {
        val cases = listOf(
            "Naik 10% bulan ini",
            "A+B = C",
            "Terima kasih ❤"
        )
        for (message in cases) {
            val route = Screen.PaymentResult.createRoute("ID-9", "pending", message)
            val segment = route.substringAfter("payment-result/ID-9/pending/")
                .substringBefore("?")
            assertFalse("$message -> '+' liar", segment.contains('+'))
            // Round-trip dengan SATU kali decode mengembalikan teks persis →
            // bukti tidak ada double encoding ('%' literal = %25, bukan %2525).
            assertEquals("roundtrip: $message", message, URLDecoder.decode(segment, "UTF-8"))
            assertFalse("$message -> double encode", segment.contains("%2525"))
        }
    }

    // ── Bug 1: popUpTo butuh POLA route, bukan route terisi ──────────────────
    @Test
    fun `pola route payment terdaftar sebagai pattern untuk popUpTo`() {
        assertTrue(Screen.Payment.route.contains("{bookingId}"))
        assertTrue(Screen.MedicalExtraPayment.route.contains("{recordId}"))
        // Route terisi TIDAK sama dengan pola — pemakaian createRoute() pada
        // popUpTo tidak akan match destination mana pun (bug lama).
        assertFalse(Screen.Payment.createRoute(1, false, 100.0) == Screen.Payment.route)
        assertFalse(Screen.MedicalExtraPayment.createRoute(1, 2, 3.0) == Screen.MedicalExtraPayment.route)
    }

    // ── Bug 3: tidak ada harga dummy di state awal ───────────────────────────
    @Test
    fun `state create booking tidak membawa angka tebakan`() {
        val state = CreateBookingUiState()
        assertEquals(0.0, state.totalAmount, 1e-9)
        assertEquals(0.0, state.dpAmount, 1e-9)
        assertNull(state.servicesError)
        assertNull(state.paymentMethodsError)
    }
}
