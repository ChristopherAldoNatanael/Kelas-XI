package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import org.json.JSONObject
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class BookingRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "BookingRepository"

        /**
         * Batas jumlah halaman yang diambil berurutan. 20 data per halaman,
         * jadi 10 halaman = 200 riwayat terbaru. Batas ini menjaga aplikasi
         * tidak mengunduh ratusan halaman tanpa henti pada akun lama.
         */
        private const val MAX_PAGES = 10
    }

    /**
     * ✅ OPTIMIZED: No more manual token passing.
     * NetworkInterceptor handles auth automatically.
     * Improved error messages based on HTTP status codes.
     */
    private fun getErrorMessage(responseCode: Int, fallbackMessage: String?): String {
        return when (responseCode) {
            401 -> "Sesi berakhir. Silakan masuk kembali."
            403 -> "Akses ditolak. Anda tidak memiliki izin."
            404 -> "Booking tidak ditemukan."
            409 -> "Jadwal yang dipilih sudah penuh atau bertabrakan. Silakan pilih tanggal/jam lain."
            422 -> "Data tidak valid. Periksa kembali isian Anda."
            500 -> "Server sedang bermasalah. Coba lagi nanti."
            else -> fallbackMessage?.takeIf { it.isNotBlank() } ?: "Gagal memproses permintaan"
        }
    }

    /**
     * Ambil `code` + `message` dari errorBody sekali. Server mengirim envelope
     * kanonik, tetapi `response.body()` selalu null pada HTTP error — tanpa ini
     * pesan spesifik seperti "Dokter cuti pada tanggal tersebut" hilang dan
     * pengguna hanya melihat pesan generik 422.
     */
    private fun errorPayload(response: Response<*>): Pair<String?, String?> {
        val raw = runCatching { response.errorBody()?.string() }.getOrNull()
            ?: return null to null
        val json = runCatching { JSONObject(raw) }.getOrNull()
            ?: return null to null
        val code = json.optString("code").takeIf { it.isNotBlank() }
        val message = json.optString("message").takeIf { it.isNotBlank() }
        return code to message
    }

    /**
     * Kode + pesan untuk create/reschedule.
     *
     * - `slot_unavailable` (422) memakai pesan server karena menjelaskan cuti /
     *   jam khusus secara spesifik.
     * - `slot_conflict` (409) tetap memakai pesan Indonesia yang sudah dipakai
     *   UI agar tidak muncul teks Inggris dari server.
     */
    private fun bookingError(response: Response<*>): Pair<String, String> {
        val (serverCode, serverMessage) = errorPayload(response)
        val code = serverCode
            ?: if (response.code() == 409) "slot_conflict" else "http_${response.code()}"
        val message = if (serverCode == "slot_unavailable" && !serverMessage.isNullOrBlank()) {
            serverMessage
        } else {
            getErrorMessage(response.code(), serverMessage)
        }
        return code to message
    }

    /**
     * Seluruh riwayat booking milik pengguna.
     *
     * Server memaginasi 20 per halaman (`BookingController::index`). Sebelumnya
     * aplikasi hanya membaca halaman pertama, sehingga booking ke-21 dan
     * seterusnya tidak pernah terlihat pengguna — tanpa pesan apa pun.
     *
     * Halaman diambil berurutan sampai `last_page`, lalu digabung dan
     * dideduplikasi berdasarkan `id` (urutan bisa bergeser bila ada booking
     * baru di antara dua permintaan). Batas [MAX_PAGES] menjaga aplikasi tetap
     * responsif bila data pengguna sangat banyak; bila batas tercapai, hal itu
     * dicatat di log supaya ketahuan, bukan disembunyikan.
     */
    suspend fun getBookings(): Result<List<Booking>> {
        return try {
            val collected = LinkedHashMap<Int, Booking>()
            var page = 1
            var lastPage = 1

            while (page <= lastPage && page <= MAX_PAGES) {
                val response = apiService.getBookings(page = page)

                if (!response.isSuccessful || response.body()?.success != true) {
                    val error = getErrorMessage(response.code(), response.body()?.message)
                    Log.e(TAG, "getBookings failed on page $page: $error (HTTP ${response.code()})")
                    // Halaman pertama gagal = memang tidak ada data yang bisa
                    // ditampilkan. Halaman lanjutan gagal = tetap tampilkan
                    // yang sudah terkumpul, jangan buang hasil sebelumnya.
                    return if (collected.isEmpty()) {
                        Result.Error(error)
                    } else {
                        Log.w(TAG, "getBookings: menampilkan ${collected.size} data sampai halaman ${page - 1}")
                        Result.Success(collected.values.toList())
                    }
                }

                val body = response.body()
                body?.data?.forEach { booking ->
                    val id = booking.id
                    if (id != null) collected[id] = booking else collected[-(collected.size + 1)] = booking
                }
                lastPage = body?.pagination?.lastPage ?: page
                page++
            }

            if (page > MAX_PAGES && lastPage > MAX_PAGES) {
                Log.w(TAG, "getBookings: berhenti di batas $MAX_PAGES halaman (last_page=$lastPage)")
            }

            Result.Success(collected.values.toList())
        } catch (e: Exception) {
            Log.e(TAG, "getBookings exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getUpcomingBookings(): Result<List<Booking>> {
        return try {
            val response = apiService.getUpcomingBookings()

            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "getUpcomingBookings failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getUpcomingBookings exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getBooking(id: Int): Result<Booking> {
        return try {
            val response = apiService.getBooking(id)

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Booking not found")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "getBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getBooking($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun createBooking(booking: BookingRequest): Result<Booking> {
        return try {
            val response = apiService.createBooking(booking)

            if (response.isSuccessful && response.body()?.success == true) {
                val createdBooking = response.body()?.data
                if (createdBooking != null) {
                    Result.Success(createdBooking)
                } else {
                    Result.Error("Failed to create booking")
                }
            } else {
                val (code, error) = bookingError(response)
                Log.e(TAG, "createBooking failed: $error (HTTP ${response.code()})")
                Result.Error(message = error, code = code)
            }
        } catch (e: Exception) {
            Log.e(TAG, "createBooking exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun cancelBooking(id: Int, reason: String): Result<Booking> {
        return try {
            val response = apiService.cancelBooking(id, mapOf("reason" to reason))

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Failed to cancel booking")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "cancelBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "cancelBooking($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    /**
     * Check-in mandiri untuk booking hari ini.
     *
     * Server menolak (422) bila booking belum dikonfirmasi atau jadwalnya
     * bukan hari ini; pesan dari server diteruskan apa adanya agar pasien
     * tahu alasannya, bukan "gagal" tanpa keterangan.
     */
    suspend fun checkInBooking(id: Int): Result<Booking> {
        return try {
            val response = apiService.checkInBooking(id)

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Check-in gagal diproses.")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "checkInBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "checkInBooking($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun rescheduleBooking(id: Int, newDate: String, newTime: String): Result<Booking> {
        return try {
            val response = apiService.rescheduleBooking(id, RescheduleRequest(newDate, newTime))

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Failed to reschedule booking")
                }
            } else {
                val (code, error) = bookingError(response)
                Log.e(TAG, "rescheduleBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(message = error, code = code)
            }
        } catch (e: Exception) {
            Log.e(TAG, "rescheduleBooking($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun deleteBooking(id: Int): Result<Unit> {
        return try {
            val response = apiService.deleteBooking(id)

            if (response.isSuccessful) {
                Result.Success(Unit)
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "deleteBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "deleteBooking($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
}
