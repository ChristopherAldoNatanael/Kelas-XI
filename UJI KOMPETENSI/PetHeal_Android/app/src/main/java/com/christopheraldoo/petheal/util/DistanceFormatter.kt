package com.christopheraldoo.petheal.util

import java.util.Locale

/**
 * Formatter jarak satu tempat untuk seluruh layar discovery.
 * Backend sudah mengirim `distance_text`; ini hanya fallback bila null
 * (mis. respons lama/cache) sehingga angka di UI selalu konsisten.
 */
object DistanceFormatter {
    fun format(km: Double?): String {
        if (km == null) return ""
        return if (km < 1.0) {
            "${(km * 1000).toInt()} m"
        } else {
            String.format(Locale.US, "%.1f km", km)
        }
    }

    /** Harga dengan status jujur: kosong → "Harga belum tersedia". */
    fun price(amount: Double?, formatted: String? = null): String {
        if (!formatted.isNullOrBlank()) return formatted
        if (amount == null) return "Harga belum tersedia"
        val whole = amount.toLong()
        val grouped = whole.toString()
            .reversed()
            .chunked(3)
            .joinToString(".")
            .reversed()
        return "Rp $grouped"
    }
}
