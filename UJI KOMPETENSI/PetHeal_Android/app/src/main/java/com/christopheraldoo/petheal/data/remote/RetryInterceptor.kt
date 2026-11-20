package com.christopheraldoo.petheal.data.remote

import android.util.Log
import okhttp3.Interceptor
import okhttp3.Response
import java.io.IOException

/**
 * Mencoba ulang permintaan GET yang gagal karena masalah sementara.
 *
 * Kenapa ada: server (ngrok + Laravel) kadang menjawab 429 saat banyak
 * permintaan bersamaan, atau 502/503 sesaat ketika proses di-restart. Tanpa
 * retry, layar langsung menampilkan error padahal percobaan kedua biasanya
 * berhasil.
 *
 * Batasan yang disengaja:
 * - **Hanya GET.** POST/PUT/DELETE tidak diulang karena bisa membuat transaksi
 *   ganda (mis. dua pesanan Pet Shop atau dua pembayaran).
 * - **Satu kali percobaan ulang.** Lebih dari itu hanya menambah beban.
 * - **Menghormati `Retry-After`** bila server mengirimkannya (maksimal 2 detik).
 * - **Tidak mengulang bila tidak ada jaringan**; itu bukan kesalahan server dan
 *   pesannya sudah jelas bagi pengguna.
 */
class RetryInterceptor(
    private val maxAttempts: Int = 2,
    private val defaultDelayMs: Long = 450L,
    private val maxDelayMs: Long = 2_000L
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        if (request.method != "GET") return chain.proceed(request)

        var attempt = 1
        var response: Response? = null
        var lastError: IOException? = null

        while (attempt <= maxAttempts) {
            response?.close()
            try {
                val current = chain.proceed(request)
                if (!current.isRetryable()) return current
                response = current
                lastError = null
                if (attempt == maxAttempts) return current
            } catch (e: IOException) {
                // Koneksi terputus di tengah jalan: ulangi sekali, tetapi jangan
                // mengulang bila penyebabnya memang tidak ada jaringan.
                lastError = e
                if (attempt == maxAttempts || !isConnectionDrop(e)) throw e
            }

            sleepBeforeRetry(attempt, response?.header("Retry-After"))
            attempt++
        }

        return response ?: throw (lastError ?: IOException("Permintaan gagal."))
    }

    private fun Response.isRetryable(): Boolean = when (code) {
        408, 429, 500, 502, 503, 504 -> true
        else -> false
    }

    private fun isConnectionDrop(error: IOException): Boolean {
        val message = error.message.orEmpty().lowercase()
        return message.contains("unexpected end of stream") ||
            message.contains("connection reset") ||
            message.contains("stream was reset")
    }

    private fun sleepBeforeRetry(attempt: Int, retryAfter: String?) {
        val serverDelayMs = retryAfter
            ?.trim()
            ?.toLongOrNull()            // detik
            ?.times(1_000L)
            ?.coerceAtMost(maxDelayMs)
        val delayMs = serverDelayMs ?: (defaultDelayMs * attempt).coerceAtMost(maxDelayMs)
        try {
            Thread.sleep(delayMs)
        } catch (_: InterruptedException) {
            Thread.currentThread().interrupt()
        }
        Log.d(TAG, "GET diulang setelah ${delayMs}ms")
    }

    private companion object {
        const val TAG = "RetryInterceptor"
    }
}
