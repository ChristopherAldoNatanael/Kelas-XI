package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.util.buildClinicHelpMessage
import com.christopheraldoo.petheal.util.buildClinicReportMessage
import com.christopheraldoo.petheal.util.buildWhatsAppUrl
import com.christopheraldoo.petheal.util.normalizeWhatsAppNumber
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.time.LocalDate

class ClinicContactTest {

    @Test
    fun `indonesian numbers normalize to 62 format`() {
        val expected = "6281234567890"
        assertEquals(expected, normalizeWhatsAppNumber("0812-3456-7890"))
        assertEquals(expected, normalizeWhatsAppNumber("+62 812 3456 7890"))
        assertEquals(expected, normalizeWhatsAppNumber("62 812 3456 7890"))
        assertEquals(expected, normalizeWhatsAppNumber("0062 812 3456 7890"))
        assertEquals(expected, normalizeWhatsAppNumber("812 3456 7890"))
        assertEquals(expected, normalizeWhatsAppNumber("(0812) 3456.7890"))
    }

    @Test
    fun `invalid numbers are rejected instead of guessing`() {
        assertNull(normalizeWhatsAppNumber(null))
        assertNull(normalizeWhatsAppNumber(""))
        assertNull(normalizeWhatsAppNumber("   "))
        assertNull(normalizeWhatsAppNumber("tidak ada nomor"))
        assertNull(normalizeWhatsAppNumber("12345"))
    }

    @Test
    fun `whatsapp url carries normalized number and encoded message`() {
        val url = buildWhatsAppUrl("0812-3456-7890", "Halo Admin\nSaya mau tanya")
        assertTrue(url!!.startsWith("https://wa.me/6281234567890?text="))
        assertTrue(url.contains("%0A"))
        assertTrue(url.contains("Halo%20Admin"))

        assertNull(buildWhatsAppUrl("12345", "Halo"))
    }

    @Test
    fun `help message includes booking context`() {
        val message = buildClinicHelpMessage(
            clinicName = "PetHeal Pusat",
            userName = "Budi",
            doctorName = "drh. Sinta",
            serviceName = "Vaksinasi",
            date = LocalDate.of(2026, 10, 21),
            time = "18:30"
        )

        assertTrue(message.contains("Halo Admin PetHeal Pusat"))
        assertTrue(message.contains("saya Budi"))
        assertTrue(message.contains("Dokter: drh. Sinta"))
        assertTrue(message.contains("Layanan: Vaksinasi"))
        assertTrue(message.contains("Jadwal: Rabu, 21 Oktober 2026, 18:30"))
    }

    @Test
    fun `help topic can be generic for the help center`() {
        val message = buildClinicHelpMessage(
            clinicName = "PetHeal Pusat",
            userName = "Budi",
            doctorName = null,
            serviceName = null,
            date = null,
            time = null,
            topic = "layanan klinik"
        )
        assertTrue(message.contains("ingin bertanya tentang layanan klinik di aplikasi."))
    }

    @Test
    fun `bug report message asks admin for follow up`() {
        val message = buildClinicReportMessage("PetHeal Pusat", "Budi")
        assertEquals(
            "Halo Admin PetHeal Pusat, saya Budi ingin melaporkan masalah pada aplikasi. " +
                "Mohon dibantu untuk menindaklanjutinya.",
            message
        )
    }

    @Test
    fun `help message stays valid without optional data`() {
        val message = buildClinicHelpMessage(
            clinicName = null,
            userName = null,
            doctorName = null,
            serviceName = null,
            date = null,
            time = null
        )

        assertEquals(
            "Halo Admin, saya ingin bertanya tentang booking di aplikasi.",
            message
        )
    }
}
