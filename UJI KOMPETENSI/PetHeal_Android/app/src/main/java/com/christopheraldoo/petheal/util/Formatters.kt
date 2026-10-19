package com.christopheraldoo.petheal.util

import java.time.LocalDate
import java.time.LocalDateTime
import java.time.format.DateTimeFormatter

/**
 * Helper tampilan bersama.
 *
 * Dulu setiap layar menulis versinya sendiri sehingga nilai yang sama tampil
 * berbeda (mis. Home memotong "Rp 12.345.678+" sementara Rekam Medis menulis
 * "Rp 12,3 jt"). Semua layar WAJIB memakai helper di sini.
 */

/** Label jumlah ulasan: "1 ulasan" / "3 ulasan". */
fun reviewCountLabel(total: Int): String =
    if (total == 1) "1 ulasan" else "$total ulasan"

/** Rating satu desimal, mis. 4.8 */
fun formatAvgRating(avg: Double): String = "%.1f".format(avg)

/** Uang penuh: "Rp 1.234.567" */
fun formatRupiah(value: Double): String =
    "Rp " + String.format("%,.0f", value).replace(',', '.')

/**
 * Uang ringkas untuk kartu metrik: nilai pendek tetap penuh ("Rp 1.234.567"),
 * nilai panjang diringkas ("Rp 1,2 jt" / "Rp 1,5 M") supaya tidak terpotong
 * di tengah angka.
 */
fun formatRupiahCompact(value: Double): String {
    val plain = String.format("%,.0f", value).replace(',', '.')
    if (plain.length <= 10) return "Rp $plain"
    return when {
        value >= 1_000_000_000 -> "Rp ${String.format("%.1f", value / 1_000_000_000)} M"
        value >= 1_000_000 -> "Rp ${String.format("%.1f", value / 1_000_000)} jt"
        else -> "Rp $plain"
    }
}

/**
 * Tanggal/waktu ISO dari backend → pola tampilan. Input null atau rusak tidak
 * pernah melempar exception: jatuh ke "-" atau potongan tanggalnya.
 */
fun formatDateSafe(value: String?, pattern: String): String {
    if (value.isNullOrBlank()) return "-"
    return try {
        LocalDateTime.parse(value.replace(" ", "T").take(19))
            .format(DateTimeFormatter.ofPattern(pattern))
    } catch (_: Exception) {
        try {
            LocalDate.parse(value.take(10)).format(DateTimeFormatter.ofPattern(pattern))
        } catch (_: Exception) {
            value.take(10)
        }
    }
}
