package com.christopheraldoo.petheal.util

/**
 * Nomor WhatsApp pasien saat mengisi form.
 *
 * Klinik memakai nomor ini untuk mengabari jadwal, hasil pemeriksaan, dan
 * pengembalian dana, jadi formatnya diseragamkan ke bentuk lokal `08…` sebelum
 * dikirim ke server. Server sendiri menerima format apa pun (maks 20 karakter),
 * tetapi menyimpan satu bentuk saja membuat tautan `wa.me` di sisi admin selalu
 * berhasil tanpa penebakan ulang.
 *
 * Aturan yang diterima: `0812…`, `+62812…`, `62812…`, `812…`, dengan spasi,
 * titik, atau tanda hubung di mana pun. Yang ditolak: terlalu pendek/panjang,
 * bukan nomor seluler Indonesia (tidak dimulai `08`), atau berisi huruf.
 */
private const val MIN_LOCAL_LENGTH = 10
private const val MAX_LOCAL_LENGTH = 14

/**
 * Mengubah input bebas menjadi bentuk lokal `08xxxxxxxxxx`.
 *
 * @return nomor siap simpan, atau `null` bila tidak masuk akal sebagai nomor
 *         seluler Indonesia (pemanggil menampilkan pesan kesalahan).
 */
fun canonicalPhoneInput(raw: String?): String? {
    val trimmed = raw?.trim().orEmpty()
    if (trimmed.isEmpty()) return null

    // Huruf tidak mungkin bagian nomor; "+" hanya boleh di awal.
    val withoutPlus = trimmed.removePrefix("+")
    if (withoutPlus.any { it.isLetter() }) return null
    val digits = withoutPlus.filter(Char::isDigit)
    if (digits.isEmpty()) return null

    val local = when {
        // 0062… ditulis sebagian orang sebagai bentuk internasional; buang dulu
        // awalan 00 supaya tidak dianggap nomor lokal.
        digits.startsWith("0062") -> "0" + digits.removePrefix("0062")
        // 62… → buang kode negara lalu tambahkan 0.
        digits.startsWith("62") -> "0" + digits.removePrefix("62")
        digits.startsWith("0") -> digits
        // Nomor yang ditulis tanpa 0 di depan (812…).
        digits.startsWith("8") -> "0$digits"
        else -> return null
    }

    if (local.length !in MIN_LOCAL_LENGTH..MAX_LOCAL_LENGTH) return null
    if (!local.startsWith("08")) return null
    // 080… bukan nomor seluler yang valid (operator selalu 08[1-9]).
    if (local[2] == '0') return null
    return local
}

/** `true` bila [raw] bisa dipakai sebagai nomor WhatsApp pasien. */
fun isValidPhoneInput(raw: String?): Boolean = canonicalPhoneInput(raw) != null

/**
 * Bentuk yang enak dibaca di tempat sempit: `0812-3456-7890`.
 * Nomor yang tidak valid dikembalikan apa adanya supaya data lama tetap tampil.
 */
fun formatPhoneForDisplay(raw: String?): String {
    val canonical = canonicalPhoneInput(raw) ?: return raw?.trim().orEmpty()
    val head = canonical.take(4)
    val middle = canonical.drop(4).take(4)
    val tail = canonical.drop(8)
    return if (tail.isEmpty()) "$head-$middle" else "$head-$middle-$tail"
}
