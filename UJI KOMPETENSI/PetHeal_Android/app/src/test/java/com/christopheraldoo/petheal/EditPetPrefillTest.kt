package com.christopheraldoo.petheal

import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.ui.screens.pet.resolveEditablePet
import com.christopheraldoo.petheal.ui.screens.pet.shouldWaitForPet
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

/**
 * Regresi bug "klik Edit Hewan → form kosong seperti tambah hewan baru".
 *
 * Penyebab yang dijaga di sini: form edit hanya membaca `detailState.pet`
 * (satu-satunya sumber), sehingga ketika pemuatan detail belum selesai atau
 * gagal, semua isian jatuh ke nilai default kosong. Fungsi di
 * `ui/screens/pet/EditPetPrefill.kt` memilih sumber data yang benar, dan
 * layar edit dilarang merender form selama belum ada data.
 */
class EditPetPrefillTest {

    private fun pet(id: Int, name: String) = Pet(id = id, name = name, species = "Cat")

    @Test
    fun `detail pet is used when id matches`() {
        val detail = pet(7, "Milo")
        val picked = resolveEditablePet(7, detail, listOf(pet(7, "Milo lama")))

        assertEquals("Milo", picked?.name)
    }

    @Test
    fun `list cache is used when detail is not loaded yet`() {
        // Ini kasus yang dulu menghasilkan form kosong: detail belum turun,
        // padahal daftar hewan sudah ada di memori.
        val list = listOf(pet(7, "Milo"), pet(9, "Bruno"))
        val picked = resolveEditablePet(7, null, list)

        assertEquals(7, picked?.id)
        assertEquals("Milo", picked?.name)
    }

    @Test
    fun `detail of another pet is never used`() {
        // State detail bisa masih milik hewan lain (kunjungan sebelumnya).
        // Memakainya akan menampilkan data hewan yang salah di form edit.
        val picked = resolveEditablePet(7, pet(99, "Kucing Lain"), emptyList())

        assertNull(picked)
    }

    @Test
    fun `unknown pet without any source stays empty`() {
        assertNull(resolveEditablePet(7, null, listOf(pet(9, "Bruno"))))
    }

    @Test
    fun `form must wait while data is still loading`() {
        assertTrue(shouldWaitForPet(pet = null, detailLoading = true, listLoading = false))
        assertTrue(shouldWaitForPet(pet = null, detailLoading = false, listLoading = true))
    }

    @Test
    fun `form is ready as soon as any source has the pet`() {
        assertFalse(shouldWaitForPet(pet = pet(7, "Milo"), detailLoading = true, listLoading = true))
    }

    @Test
    fun `no data and no loading means error state not spinner`() {
        assertFalse(shouldWaitForPet(pet = null, detailLoading = false, listLoading = false))
    }
}
