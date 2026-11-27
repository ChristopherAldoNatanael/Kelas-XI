package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

data class MedicalRecordPage(
    val records: List<MedicalRecord>,
    val currentPage: Int,
    val lastPage: Int,
    val total: Int
)

@Singleton
class MedicalRecordRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "MedicalRecordRepository"

        /**
         * Batas jumlah halaman yang diambil berurutan. 20 data per halaman,
         * jadi 10 halaman = 200 riwayat terbaru. Batas ini menjaga aplikasi
         * tidak mengunduh ratusan halaman tanpa henti pada akun lama.
         */
        private const val MAX_PAGES = 10
    }

    private var recordsCache: List<MedicalRecord>? = null
    private val recordDetailCache = mutableMapOf<Int, MedicalRecord>()
    private val recordsByPetCache = mutableMapOf<Int, List<MedicalRecord>>()
    private val recordByBookingCache = mutableMapOf<Int, MedicalRecord?>()

    /** PHASE 7: user-scoped caches must not survive account switch/delete. */
    fun clearCaches() {
        recordsCache = null
        recordDetailCache.clear()
        recordsByPetCache.clear()
        recordByBookingCache.clear()
    }

    /**
     * Invalidasi cache setelah additional payment sukses — tanpa ini,
     * [getMedicalRecords]/[getMedicalRecord] mengembalikan data terkunci
     * yang lama dari RAM sehingga user harus restart aplikasi.
     */
    fun invalidateAll() {
        recordsCache = null
        recordsByPetCache.clear()
    }

    fun invalidateRecord(id: Int) {
        recordDetailCache.remove(id)
        recordByBookingCache.entries.removeAll { it.value?.id == id }
        // Daftar ikut basi karena status pembayaran item berubah.
        recordsCache = null
        recordsByPetCache.clear()
    }

    fun invalidateByBooking(bookingId: Int) {
        recordByBookingCache.remove(bookingId)
        recordsCache = null
        recordsByPetCache.clear()
    }

    suspend fun getMedicalRecordSummaries(page: Int = 1): Result<MedicalRecordPage> {
        return try {
            val response = apiService.getMedicalRecordSummaries(page = page)
            val body = response.body()

            if (response.isSuccessful && body?.success == true) {
                val records = body.data ?: emptyList()
                records.forEach { record ->
                    record.id?.let { recordDetailCache.putIfAbsent(it, record) }
                }
                Result.Success(
                    MedicalRecordPage(
                        records = records,
                        currentPage = body.pagination?.currentPage ?: page,
                        lastPage = body.pagination?.lastPage ?: page,
                        total = body.pagination?.total ?: records.size
                    )
                )
            } else {
                Log.e(TAG, "getMedicalRecordSummaries($page) failed: ${body?.message} (HTTP ${response.code()})")
                Result.Error(body?.message ?: "Gagal memuat rekam medis")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecordSummaries($page) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getMedicalRecords(forceRefresh: Boolean = false): Result<List<MedicalRecord>> {
        if (!forceRefresh) {
            recordsCache?.let { return Result.Success(it) }
        }
        return try {
            // Server memaginasi 20 per halaman; riwayat medis pasien panjang
            // dan halaman pertama saja akan menyembunyikan rekam medis lama.
            // Semua halaman digabung & dideduplikasi berdasarkan id.
            val collected = LinkedHashMap<Int, MedicalRecord>()
            var page = 1
            var lastPage = 1
            var failureMessage: String? = null

            while (page <= lastPage && page <= MAX_PAGES) {
                val response = apiService.getMedicalRecords(page = page)

                if (!response.isSuccessful || response.body()?.success != true) {
                    failureMessage = response.body()?.message
                    Log.e(TAG, "getMedicalRecords failed on page $page: $failureMessage (HTTP ${response.code()})")
                    break
                }

                val body = response.body()
                body?.data?.forEach { record ->
                    val id = record.id
                    if (id != null) collected[id] = record else collected[-(collected.size + 1)] = record
                }
                lastPage = body?.pagination?.lastPage ?: page
                page++
            }

            if (collected.isEmpty() && failureMessage != null) {
                Result.Error(failureMessage ?: "Failed to get medical records")
            } else {
                if (page > MAX_PAGES && lastPage > MAX_PAGES) {
                    Log.w(TAG, "getMedicalRecords: berhenti di batas $MAX_PAGES halaman (last_page=$lastPage)")
                }
                val records = collected.values.toList()
                recordsCache = records
                records.forEach { record ->
                    record.id?.let { recordDetailCache[it] = record }
                }
                Result.Success(records)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecords exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getMedicalRecord(id: Int, forceRefresh: Boolean = false): Result<MedicalRecord> {
        if (!forceRefresh) {
            recordDetailCache[id]?.let { return Result.Success(it) }
        }
        return try {
            val response = apiService.getMedicalRecord(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val record = response.body()?.data
                if (record != null) {
                    recordDetailCache[id] = record
                    Result.Success(record)
                } else {
                    Result.Error("Medical record not found")
                }
            } else {
                Log.e(TAG, "getMedicalRecord($id) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get medical record")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecord($id) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getBookingMedicalRecord(bookingId: Int, forceRefresh: Boolean = false): Result<MedicalRecord?> {
        if (!forceRefresh && recordByBookingCache.containsKey(bookingId)) {
            return Result.Success(recordByBookingCache[bookingId])
        }
        return try {
            val response = apiService.getBookingMedicalRecord(bookingId)
            if (response.isSuccessful && response.body()?.success == true) {
                val record = response.body()?.data
                recordByBookingCache[bookingId] = record
                record?.id?.let { recordDetailCache[it] = record }
                Result.Success(record)
            } else {
                Log.e(TAG, "getBookingMedicalRecord($bookingId) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get booking medical record")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getBookingMedicalRecord($bookingId) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getPaymentStatus(recordId: Int): Result<MedicalRecordPaymentStatus> {
        return try {
            val response = apiService.getMedicalRecordPaymentStatus(recordId)
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(response.body()?.message ?: "Failed to get medical record payment status")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getPaymentStatus($recordId) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getAmendments(recordId: Int): Result<List<MedicalRecordAmendmentSummary>> {
        return try {
            val response = apiService.getMedicalRecordAmendments(recordId)
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body.data ?: emptyList())
            } else {
                Result.Error(body?.message ?: "Gagal memuat riwayat koreksi")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getAmendments($recordId) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun createExport(recordId: Int): Result<MedicalRecordExportTicket> {
        return try {
            val response = apiService.createMedicalRecordExport(recordId)
            val body = response.body()
            val ticket = body?.data
            if (response.isSuccessful && body?.success == true && ticket?.token != null) {
                Result.Success(ticket)
            } else {
                Log.e(TAG, "createExport($recordId) failed: ${body?.message} (HTTP ${response.code()})")
                Result.Error(body?.message ?: "Gagal membuat salinan rekam medis")
            }
        } catch (e: Exception) {
            Log.e(TAG, "createExport($recordId) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun downloadExport(token: String): Result<ByteArray> {
        return try {
            val response = apiService.downloadMedicalRecordExport(token)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                Result.Success(body.bytes())
            } else {
                val message = when (response.code()) {
                    410 -> "Salinan sudah kedaluwarsa atau sudah pernah diunduh."
                    404 -> "Salinan rekam medis tidak ditemukan."
                    else -> "Gagal mengunduh salinan rekam medis"
                }
                Log.e(TAG, "downloadExport failed (HTTP ${response.code()})")
                Result.Error(message)
            }
        } catch (e: Exception) {
            Log.e(TAG, "downloadExport exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getPrivacyRequests(): Result<List<PrivacyRequest>> {
        return try {
            val response = apiService.getPrivacyRequests()
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success(body.data ?: emptyList())
            } else {
                Result.Error(body?.message ?: "Gagal memuat permintaan data")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getPrivacyRequests exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun downloadPrivacyResponse(id: Int): Result<ByteArray> {
        return try {
            val response = apiService.downloadPrivacyResponse(id)
            val body = response.body()
            if (response.isSuccessful && body != null) {
                Result.Success(body.bytes())
            } else {
                val message = when (response.code()) {
                    404 -> "Berkas salinan data belum tersedia."
                    else -> "Gagal mengunduh salinan data"
                }
                Log.e(TAG, "downloadPrivacyResponse failed (HTTP ${response.code()})")
                Result.Error(message)
            }
        } catch (e: Exception) {
            Log.e(TAG, "downloadPrivacyResponse exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun createPrivacyRequest(
        requestType: String,
        description: String,
        clinicId: Int? = null
    ): Result<PrivacyRequest> {
        return try {
            val response = apiService.createPrivacyRequest(
                PrivacyRequestCreateRequest(
                    requestType = requestType,
                    description = description,
                    clinicId = clinicId
                )
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(body?.message ?: "Gagal mengirim permintaan data")
            }
        } catch (e: Exception) {
            Log.e(TAG, "createPrivacyRequest exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun getMedicalRecordsByPet(petId: Int, forceRefresh: Boolean = false): Result<List<MedicalRecord>> {
        if (!forceRefresh) {
            recordsByPetCache[petId]?.let { return Result.Success(it) }
        }
        return try {
            val response = apiService.getMedicalRecordsByPet(petId)
            if (response.isSuccessful && response.body()?.success == true) {
                val records = response.body()?.data ?: emptyList()
                recordsByPetCache[petId] = records
                records.forEach { record ->
                    record.id?.let { recordDetailCache[it] = record }
                }
                Result.Success(records)
            } else {
                Log.e(TAG, "getMedicalRecordsByPet($petId) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get medical records")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecordsByPet($petId) exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
}
