package com.christopheraldoo.petheal.data.remote

import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException

/**
 * Pemetaan terpusat exception jaringan -> pesan Bahasa Indonesia yang
 * ramah pengguna. EXCEPTION MENTAH (hostname ngrok, "Unable to resolve
 * host", nama class, stack trace) TIDAK BOLEH sampai ke UI — detail
 * teknis tetap tercatat lewat Log.e di tiap repository sebelum pesan
 * ini dipakai.
 *
 * Dipakai oleh semua repository (`catch (e: Exception)`), sehingga satu
 * kegagalan jaringan menampilkan pesan yang sama di layar mana pun.
 */

// Koneksi internet bermasalah (DNS gagal / host tidak dikenal).
const val MSG_NO_INTERNET = "Periksa koneksi internet Anda, lalu coba lagi."

// Server sementara tidak dapat dijangkau (koneksi ditolak / SSL gagal).
const val MSG_SERVICE_UNAVAILABLE =
    "Kami belum dapat terhubung ke layanan saat ini. Silakan coba beberapa saat lagi."

// Permintaan memakan waktu terlalu lama.
const val MSG_TIMEOUT = "Koneksi membutuhkan waktu lebih lama dari biasanya. Silakan coba lagi."

// Umum — penyebab tidak dapat dipastikan; jujur tanpa menyalahkan pengguna.
const val MSG_GENERIC = "Maaf, permintaan Anda belum dapat diproses. Silakan coba kembali."

/**
 * Kategorikan throwable jaringan menjadi satu pesan UI. Urutan when
 * mengikuti hierarki exception: SocketTimeoutException adalah subclass
 * InterruptedIOException, jadi ia harus dicek lebih dulu.
 */
fun userFriendlyExceptionMessage(e: Throwable): String = when (e) {
    is UnknownHostException -> MSG_NO_INTERNET
    is SocketTimeoutException -> MSG_TIMEOUT
    is java.io.InterruptedIOException -> MSG_TIMEOUT
    is ConnectException -> MSG_SERVICE_UNAVAILABLE
    is SSLException -> MSG_SERVICE_UNAVAILABLE
    else -> MSG_GENERIC
}
