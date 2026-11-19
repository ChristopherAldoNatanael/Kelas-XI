package com.christopheraldoo.petheal.service

import android.content.Context
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Buku catatan peristiwa antrian yang sudah diumumkan.
 *
 * FCM dapat mengirim ulang pesan yang sama (retry, koneksi tidak stabil),
 * dan proses aplikasi bisa dibuka-tutup. Karena itu idempotensi tidak boleh
 * hanya bergantung pada variabel di memori. Setiap `queue_event_id` dari
 * backend disimpan di SharedPreferences; id yang sudah pernah dipakai
 * ditolak. Daftar dibatasi [MAX_IDS] agar tidak tumbuh tanpa batas.
 */
@Singleton
class QueueAnnouncementLedger @Inject constructor(
    @ApplicationContext context: Context
) {
    private val prefs = context.getSharedPreferences("petheal_queue_voice", Context.MODE_PRIVATE)

    /**
     * Klaim satu peristiwa. `true` hanya untuk pengiriman pertama; pemanggilan
     * berikutnya dengan id sama akan mendapat `false`.
     */
    @Synchronized
    fun claim(eventId: String?): Boolean {
        val id = eventId?.trim().orEmpty()
        if (id.isEmpty()) return false

        val known = read()
        if (id in known) return false

        val updated = (known + id).takeLast(MAX_IDS)
        prefs.edit().putString(KEY_IDS, updated.joinToString(SEPARATOR)).apply()
        return true
    }

    private fun read(): List<String> =
        prefs.getString(KEY_IDS, null)
            ?.split(SEPARATOR)
            ?.filter { it.isNotBlank() }
            ?: emptyList()

    private companion object {
        const val KEY_IDS = "announced_event_ids"
        const val SEPARATOR = "\n"
        const val MAX_IDS = 40
    }
}
