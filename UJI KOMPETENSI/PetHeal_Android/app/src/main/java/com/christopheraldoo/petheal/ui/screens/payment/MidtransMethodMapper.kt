package com.christopheraldoo.petheal.ui.screens.payment

import com.christopheraldoo.petheal.data.model.PaymentMethod

/**
 * Memetakan metode pembayaran klinik ke kode `enabled_payments` Midtrans Snap.
 *
 * Memilih metode di Buat Booking akan diteruskan ke Snap token, sehingga
 * halaman Midtrans langsung membuka metode tersebut (mis. QRIS → halaman
 * QRIS, BCA → Virtual Account BCA) — bukan daftar semua metode.
 *
 * Mengembalikan null bila tidak ada kode Snap yang pasti untuk metode
 * tersebut (mis. OVO yang hanya tersedia via Core API) — caller memakai
 * daftar lengkap default agar pembayaran tetap bisa jalan.
 */
fun PaymentMethod.toMidtransCodes(): List<String>? {
    val type = type?.lowercase().orEmpty()
    val name = name?.lowercase().orEmpty()
    val icon = icon?.lowercase().orEmpty()
    fun has(vararg keys: String) = keys.any { name.contains(it) || icon.contains(it) }

    // QRIS di Snap berbasis acquirer (GoPay QRIS / ShopeePay QRIS /
    // Other QRIS). "qris" saja tidak selalu resolve ke kanal di semua akun,
    // sedangkan "other_qris" adalah kode resmi QRIS generik. Kirim keduanya:
    // kode yang tidak dikenal diabaikan Midtrans, yang didukung tampil.
    if (type == "qris" || has("qris")) return listOf("qris", "other_qris")
    if (has("bca")) return listOf("bca_va")
    if (has("bri")) return listOf("bri_va")
    if (has("mandiri", "mandi")) return listOf("echannel")
    if (has("bni")) return listOf("bni_va")
    if (has("permata")) return listOf("permata_va")
    if (has("cimb", "niaga")) return listOf("cimb_va")
    if (has("gopay", "go-pay")) return listOf("gopay")
    if (has("shopeepay", "shopee")) return listOf("shopeepay")
    if (has("dana")) return listOf("dana")
    // Kartu kredit bila klinik menyediakannya.
    if (has("kartu kredit", "credit_card", "credit card", "kartu")) return listOf("credit_card")
    // OVO & lainnya: tidak ada kode Snap yang pasti → daftar lengkap.
    return null
}

/** Label ringkas untuk satu set kode — ditampilkan di layar pembayaran. */
fun midtransCodesLabel(codes: List<String>?): String? {
    if (codes.isNullOrEmpty()) return null
    val set = codes.toSet()
    if (set == setOf("qris", "other_qris")) return "QRIS"
    if (codes.size > 1) return null
    return when (codes.first()) {
        "qris", "other_qris" -> "QRIS"
        "bca_va" -> "BCA Virtual Account"
        "bri_va" -> "BRI Virtual Account"
        "bni_va" -> "BNI Virtual Account"
        "mandiri_va" -> "Mandiri Virtual Account"
        "echannel" -> "Mandiri Bill Payment"
        "permata_va" -> "Permata Virtual Account"
        "cimb_va" -> "CIMB Virtual Account"
        "gopay" -> "GoPay"
        "shopeepay" -> "ShopeePay"
        "dana" -> "DANA"
        "credit_card" -> "Kartu Kredit"
        else -> null
    }
}
