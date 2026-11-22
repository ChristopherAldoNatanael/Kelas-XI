package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.util.toQueryFlag
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Regresi filter "Buka sekarang" di layar Cari Klinik.
 *
 * Sebelum perbaikan, `open_now` dikirim sebagai teks `true`/`false`. Backend
 * memvalidasi dengan rule `boolean` Laravel yang hanya menerima 1/0/true/false
 * asli sehingga setiap perubahan filter Status dijawab 422 dan layar berakhir
 * di keadaan error. Sekarang nilainya selalu 1/0.
 */
class QueryFlagTest {

    @Test
    fun `true menjadi 1`() {
        assertEquals(1, true.toQueryFlag())
    }

    @Test
    fun `false menjadi 0`() {
        assertEquals(0, false.toQueryFlag())
    }

    @Test
    fun `null tetap null supaya parameter tidak dikirim`() {
        assertNull(null.toQueryFlag())
    }
}
