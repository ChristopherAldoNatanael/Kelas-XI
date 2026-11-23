package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.util.canonicalPhoneInput
import com.christopheraldoo.petheal.util.formatPhoneForDisplay
import com.christopheraldoo.petheal.util.isValidPhoneInput
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Nomor WhatsApp disimpan dalam satu bentuk (`08…`) supaya tautan `wa.me` di
 * sisi admin tidak perlu menebak format. Test ini mengunci bentuk masukan yang
 * diterima pasien dan yang ditolak.
 */
class PhoneNumberTest {

    @Test
    fun `local number is kept as is`() {
        assertEquals("081234567890", canonicalPhoneInput("081234567890"))
    }

    @Test
    fun `spaces dashes and dots are ignored`() {
        assertEquals("081234567890", canonicalPhoneInput("0812-3456-7890"))
        assertEquals("081234567890", canonicalPhoneInput("0812 3456 7890"))
        assertEquals("081234567890", canonicalPhoneInput(" 0812.3456.7890 "))
    }

    @Test
    fun `country code forms are converted to local`() {
        assertEquals("081234567890", canonicalPhoneInput("+6281234567890"))
        assertEquals("081234567890", canonicalPhoneInput("6281234567890"))
        assertEquals("081234567890", canonicalPhoneInput("006281234567890"))
        assertEquals("081234567890", canonicalPhoneInput("+62 812 3456 7890"))
    }

    @Test
    fun `number without leading zero is accepted`() {
        assertEquals("081234567890", canonicalPhoneInput("81234567890"))
    }

    @Test
    fun `empty input is not an error yet`() {
        assertNull(canonicalPhoneInput(""))
        assertNull(canonicalPhoneInput("   "))
        assertNull(canonicalPhoneInput(null))
    }

    @Test
    fun `too short or too long numbers are rejected`() {
        assertNull(canonicalPhoneInput("081234"))
        assertNull(canonicalPhoneInput("0812345678901234567"))
    }

    @Test
    fun `letters and non mobile numbers are rejected`() {
        assertNull(canonicalPhoneInput("0812abc4567"))
        assertNull(canonicalPhoneInput("0211234567"))   // telepon rumah
        assertNull(canonicalPhoneInput("0800123456"))   // bukan operator seluler
        assertNull(canonicalPhoneInput("+60 12 345 6789"))
    }

    @Test
    fun `validity helper matches canonical result`() {
        assertTrue(isValidPhoneInput("0812-3456-7890"))
        assertFalse(isValidPhoneInput("0812"))
        assertFalse(isValidPhoneInput(""))
    }

    @Test
    fun `display format groups the number for reading`() {
        assertEquals("0812-3456-7890", formatPhoneForDisplay("081234567890"))
        assertEquals("0812-3456-789", formatPhoneForDisplay("08123456789"))
        // Data lama yang tidak valid tetap ditampilkan apa adanya.
        assertEquals("021-555", formatPhoneForDisplay("021-555"))
    }
}
