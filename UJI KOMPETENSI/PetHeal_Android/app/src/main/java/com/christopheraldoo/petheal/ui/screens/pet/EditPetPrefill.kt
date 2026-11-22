package com.christopheraldoo.petheal.ui.screens.pet

import com.christopheraldoo.petheal.data.model.Pet

/**
 * Menentukan hewan mana yang dipakai sebagai isi awal form **Edit Hewan**.
 *
 * Kenapa dipisah jadi fungsi murni: layar edit dulu hanya membaca
 * `detailState.pet`. Kalau pemuatan detail terlambat atau gagal, form dirender
 * dengan nilai default kosong sehingga terlihat seperti tambah hewan baru.
 *
 * Urutan sumber (paling murah lebih dulu):
 *  1. detail yang baru dimuat (`detailState.pet`) — paling lengkap,
 *  2. daftar hewan yang sudah ada di memori (`uiState.pets`) — sudah dimuat oleh
 *     `PetsViewModel.init`, jadi form tetap terisi walau detail belum/gagal turun.
 *
 * Detail hanya dipakai bila id-nya cocok; id yang tidak cocok berarti state
 * masih milik hewan lain (sisa kunjungan sebelumnya) dan TIDAK boleh dipakai,
 * karena akan menampilkan data hewan yang salah di form edit.
 */
internal fun resolveEditablePet(petId: Int, detail: Pet?, listed: List<Pet>): Pet? {
    return detail?.takeIf { it.id == petId } ?: listed.firstOrNull { it.id == petId }
}

/**
 * `true` bila layar edit masih harus menampilkan indikator memuat: belum ada
 * data hewan dan salah satu sumbernya masih dimuat. Form kosong tidak boleh
 * dirender dalam kondisi ini.
 */
internal fun shouldWaitForPet(
    pet: Pet?,
    detailLoading: Boolean,
    listLoading: Boolean
): Boolean = pet == null && (detailLoading || listLoading)
