package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.local.PreferencesManager
import kotlinx.coroutines.flow.first
import javax.inject.Inject
import javax.inject.Singleton

/**
 * MULTI-CLINIC (2026-11-09) — konteks klinik untuk LAPISAN UI saja.
 *
 * Booking selalu memakai klinik yang tersimpan di preferensi (lihat
 * `NetworkInterceptor` yang mengirim header `X-Clinic-Slug`). Kelas ini
 * TIDAK mengubah klinik mana pun: ia hanya menjawab "klinik mana yang
 * akan dipakai kalau user booking sekarang?" supaya layar discovery bisa
 * memperingatkan ketika user sedang melihat klinik yang BERBEDA.
 *
 * Tanpa ini, user bisa menelusuri Klinik A, menekan booking, lalu
 * transaksinya masuk ke Klinik B tanpa pernah diberi tahu.
 */
@Singleton
class ClinicContextResolver @Inject constructor(
    private val preferencesManager: PreferencesManager
) {
    /** Klinik yang saat ini aktif untuk transaksi (bisa null bila belum dipilih). */
    data class ActiveClinic(
        val id: Int? = null,
        val slug: String? = null,
        val name: String? = null,
        val address: String? = null
    ) {
        val isSet: Boolean get() = !slug.isNullOrBlank()
    }

    suspend fun activeClinic(): ActiveClinic {
        val slug = runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
        if (slug.isNullOrBlank()) return ActiveClinic()
        return ActiveClinic(
            id = runCatching { preferencesManager.clinicId.first() }.getOrNull(),
            slug = slug,
            name = runCatching { preferencesManager.clinicName.first() }.getOrNull(),
            address = runCatching { preferencesManager.clinicAddress.first() }.getOrNull()
        )
    }

    /**
     * True bila klinik `slug` BUKAN klinik aktif. Klinik aktif yang belum
     * diatur dianggap "belum cocok" — user tetap harus diberi tahu bahwa
     * booking akan memakai klinik lain, bukan diam-diam.
     */
    suspend fun isDifferentClinic(slug: String?): Boolean {
        if (slug.isNullOrBlank()) return false
        val active = activeClinic()
        return !active.isSet || active.slug != slug
    }
}
