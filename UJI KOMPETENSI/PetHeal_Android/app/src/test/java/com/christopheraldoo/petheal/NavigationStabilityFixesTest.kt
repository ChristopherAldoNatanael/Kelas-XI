package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.remote.MSG_GENERIC
import com.christopheraldoo.petheal.data.remote.MSG_NO_INTERNET
import com.christopheraldoo.petheal.data.remote.MSG_SERVICE_UNAVAILABLE
import com.christopheraldoo.petheal.data.remote.MSG_TIMEOUT
import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import com.christopheraldoo.petheal.ui.components.PetHealTab
import com.christopheraldoo.petheal.ui.components.route
import com.christopheraldoo.petheal.ui.navigation.Screen
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException

/**
 * Regresi hasil testing perangkat fisik (okt 2026):
 *  1. pesan jaringan mentah ("Network error: Unable to resolve host
 *     ...ngrok-free.dev...") tidak boleh sampai ke UI — pemetaan
 *     terpusat wajib membedakan DNS / timeout / server / umum;
 *  2. konsistensi route tab yang dipakai navigateToTab (NavHost,
 *     notifikasi, deep link) — titik yang dulu dipakai tombol
 *     "View All Medical Records" lewat jalur non-tab.
 */
class NavigationStabilityFixesTest {

    // ── 1. Pemetaan error jaringan ───────────────────────────────────────────

    @Test
    fun `dns failure maps to internet message without leaking hostname`() {
        val e = UnknownHostException(
            "Unable to resolve host \"envious-reselect-darn.ngrok-free.dev\": " +
                "No address associated with hostname"
        )
        val msg = userFriendlyExceptionMessage(e)
        assertEquals(MSG_NO_INTERNET, msg)
        // Tidak ada detail teknis yang bocor ke UI.
        assertFalse(msg.contains("ngrok"))
        assertFalse(msg.contains("resolve host"))
        assertFalse(msg.contains("hostname"))
    }

    @Test
    fun `timeout maps to timeout message`() {
        assertEquals(MSG_TIMEOUT, userFriendlyExceptionMessage(SocketTimeoutException("timeout")))
    }

    @Test
    fun `connection refused maps to service unavailable`() {
        assertEquals(
            MSG_SERVICE_UNAVAILABLE,
            userFriendlyExceptionMessage(ConnectException("Failed to connect to /10.0.2.2:8000"))
        )
    }

    @Test
    fun `ssl failure maps to service unavailable`() {
        assertEquals(MSG_SERVICE_UNAVAILABLE, userFriendlyExceptionMessage(SSLException("handshake failed")))
    }

    @Test
    fun `unknown throwable falls back to honest generic message without raw text`() {
        val e = IllegalStateException("gson expect float at line 1 column 2 java.lang.IllegalStateException")
        val msg = userFriendlyExceptionMessage(e)
        assertEquals(MSG_GENERIC, msg)
        assertFalse(msg.contains("gson"))
        assertFalse(msg.contains("java.lang"))
    }

    @Test
    fun `all user messages are indonesian sentences`() {
        listOf(MSG_NO_INTERNET, MSG_SERVICE_UNAVAILABLE, MSG_TIMEOUT, MSG_GENERIC).forEach {
            assertTrue("pesan harus diakhiri tanda baca: $it", it.endsWith("."))
            // Tidak boleh memuat class/istilah teknis.
            listOf("host", "HTTP", "exception", "socket", "ngrok").forEach { term ->
                assertFalse("('$term' tidak boleh tampil di UI pada: $it)", it.contains(term, ignoreCase = true))
            }
        }
    }

    // ── 2. Konsistensi tab route (mekanisme navigateToTab) ───────────────────

    @Test
    fun `each tab maps to its expected screen destination`() {
        assertEquals(Screen.Home.route, PetHealTab.Home.route)
        assertEquals(Screen.Pets.route, PetHealTab.Pets.route)
        assertEquals(Screen.Bookings.route, PetHealTab.Bookings.route)
        assertEquals(Screen.MedicalRecords.route, PetHealTab.Records.route)
        assertEquals(Screen.Profile.route, PetHealTab.Profile.route)
    }

    @Test
    fun `tab routes are literal unique destinations without argument placeholders`() {
        val routes = PetHealTab.values().map { it.route }
        assertEquals("lima tab harus punya route unik", routes.size, routes.distinct().size)
        // Route tab literal (bukan pola ber-argumen) supaya entry point
        // non-tab tidak bisa mendarat di destination lain yang cocok
        // sebagian — dan tidak tertelan pola detail (pets/{id} dll).
        routes.forEach {
            assertFalse("route tab tidak boleh ber-argumen: $it", it.contains("{"))
        }
        // Repro spesifik: "View All Medical Records" wajib mendarat di
        // tab rekam medis literal, BUKAN pola detail medical-records/{id}.
        assertEquals("medical-records", PetHealTab.Records.route)
    }

    @Test
    fun `detail patterns cannot shadow tab literal routes`() {
        // "pets/add" terdaftar sebelum "pets/{petId}" di NavHost; pola
        // detail selalu punya segmen argumen ekstra sehingga tak pernah
        // cocok persis dengan route tab literal.
        assertFalse(Screen.PetDetail.route == Screen.Pets.route)
        assertFalse(Screen.MedicalRecordDetail.route == Screen.MedicalRecords.route)
        assertFalse(Screen.BookingDetail.route == Screen.Bookings.route)
        assertTrue(Screen.MedicalRecordDetail.route.startsWith(Screen.MedicalRecords.route + "/"))
        assertTrue(Screen.BookingDetail.route.startsWith(Screen.Bookings.route + "/"))
        assertTrue(Screen.AddPet.route.startsWith(Screen.Pets.route + "/"))
    }
}
