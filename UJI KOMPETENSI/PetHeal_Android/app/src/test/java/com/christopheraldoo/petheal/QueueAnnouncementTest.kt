package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.util.QueueAnnouncement
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Teks TTS tidak sama dengan teks layar: nol di depan nomor antrian harus
 * dibaca "nol satu" supaya pasien tidak mendengar "A satu" atau "A strip satu".
 */
class QueueAnnouncementTest {

    @Test
    fun `label with leading zero is spoken digit by digit`() {
        assertEquals("A, nol satu", QueueAnnouncement.spokenLabel("A-01"))
        assertEquals("A, nol sembilan", QueueAnnouncement.spokenLabel("A-09"))
        assertEquals("B, nol satu dua", QueueAnnouncement.spokenLabel("B-012"))
    }

    @Test
    fun `label without leading zero keeps the number`() {
        assertEquals("A, 12", QueueAnnouncement.spokenLabel("A-12"))
        assertEquals("A, 7", QueueAnnouncement.spokenLabel("A-7"))
    }

    @Test
    fun `blank or unusual labels are handled without crashing`() {
        assertNull(QueueAnnouncement.spokenLabel(null))
        assertNull(QueueAnnouncement.spokenLabel("   "))
        assertEquals("TanpaNomor", QueueAnnouncement.spokenLabel("TanpaNomor"))
    }

    @Test
    fun `announcement mentions number doctor and room`() {
        assertEquals(
            "Nomor antrian A, nol satu dipanggil. Silakan menuju ruang pemeriksaan dokter Christopher.",
            QueueAnnouncement.buildText("A-01", "Christopher", null)
        )
        assertEquals(
            "Nomor antrian A, nol dua dipanggil. Silakan menuju Ruang 2, dokter Christopher.",
            QueueAnnouncement.buildText("A-02", "Christopher", "Ruang 2")
        )
        assertEquals(
            "Nomor antrian A, nol tiga dipanggil. Silakan menuju Ruang 2.",
            QueueAnnouncement.buildText("A-03", null, "Ruang 2")
        )
    }

    @Test
    fun `doctor title prefix is not repeated`() {
        assertEquals(
            "Nomor antrian A, nol lima dipanggil. Silakan menuju ruang pemeriksaan dokter Emily Williams.",
            QueueAnnouncement.buildText("A-05", "Dr. Emily Williams", null)
        )
        assertEquals(
            "Nomor antrian A, nol enam dipanggil. Silakan menuju ruang pemeriksaan dokter Budi.",
            QueueAnnouncement.buildText("A-06", "dokter Budi", null)
        )
    }

    @Test
    fun `announcement stays generic when backend sends no doctor or room`() {
        assertEquals(
            "Nomor antrian A, nol empat dipanggil. Silakan menuju ruang pemeriksaan.",
            QueueAnnouncement.buildText("A-04", "  ", null)
        )
        assertEquals(
            "Nomor antrian Anda dipanggil. Silakan menuju ruang pemeriksaan.",
            QueueAnnouncement.buildText(null, null, null)
        )
    }
}
