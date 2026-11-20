package com.christopheraldoo.petheal.ui.navigation

import android.os.SystemClock

/**
 * Gerbang anti "double-tap" untuk navigasi.
 *
 * MASALAH YANG DICEGAH
 * Satu tombol bisa mengirim dua perintah navigasi dalam jarak < 300 ms
 * (ketukan ganda, atau jari yang menempel sedikit lama). Akibatnya dua layar
 * identik menumpuk di back stack: pengguna menekan Back sekali dan seolah
 * "tidak terjadi apa-apa", lalu menyimpulkan tombol Back-nya rusak.
 *
 * ATURAN
 * 1. Perintah kedua dalam [windowMs] diabaikan.
 * 2. Navigasi ke route yang SAMA dengan layar saat ini diabaikan, sehingga
 *    layar statis (Notifikasi, Profil, Pengaturan) tidak bisa ditumpuk.
 *
 * Catatan penting: gerbang ini TIDAK dipakai untuk logout karena sesi
 * kedaluwarsa (401). Peristiwa itu harus selalu bisa mengakhiri sesi,
 * berapa pun cepatnya pengguna menyentuh layar.
 */
class NavGate(private val windowMs: Long = 400L) {

    // Negatif (bukan Long.MIN_VALUE) supaya pengurangan `now - lastAllowedAtMs`
    // tidak overflow di perintah navigasi pertama.
    private var lastAllowedAtMs: Long = -windowMs

    /**
     * @param targetRoute route tujuan (null untuk aksi "kembali").
     * @param currentRoute route layar yang sedang tampil.
     * @return true bila perintah boleh dijalankan.
     */
    fun allow(targetRoute: String?, currentRoute: String?): Boolean {
        if (targetRoute != null && targetRoute == currentRoute) return false
        val now = SystemClock.elapsedRealtime()
        if (now - lastAllowedAtMs < windowMs) return false
        lastAllowedAtMs = now
        return true
    }
}
