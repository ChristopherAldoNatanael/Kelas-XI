package com.christopheraldoo.petheal.util

/**
 * Konversi filter boolean untuk parameter query API.
 *
 * Backend memvalidasi `open_now` dengan rule `boolean` Laravel yang hanya
 * menerima `1`/`0`/`true`/`false` *asli*. Klien HTTP mengirim semuanya sebagai
 * teks, sehingga `true`/`false` ditolak 422 (inilah sebab filter "Buka sekarang"
 * di layar Cari Klinik gagal). Mengirim `1`/`0` diterima semua versi backend.
 *
 * `null` berarti filter tidak dipakai → parameter tidak dikirim sama sekali.
 */
internal fun Boolean?.toQueryFlag(): Int? = when (this) {
    true -> 1
    false -> 0
    null -> null
}
