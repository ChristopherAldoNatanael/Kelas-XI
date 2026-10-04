package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class MedicalRecordRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "MedicalRecordRepository"
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

    suspend fun getMedicalRecords(forceRefresh: Boolean = false): Result<List<MedicalRecord>> {
        if (!forceRefresh) {
            recordsCache?.let { return Result.Success(it) }
        }
        return try {
            val response = apiService.getMedicalRecords()
            if (response.isSuccessful && response.body()?.success == true) {
                val records = response.body()?.data ?: emptyList()
                recordsCache = records
                records.forEach { record ->
                    record.id?.let { recordDetailCache[it] = record }
                }
                Result.Success(records)
            } else {
                Log.e(TAG, "getMedicalRecords failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get medical records")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecords exception", e)
            Result.Error("Network error: ${e.message}")
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
            Result.Error("Network error: ${e.message}")
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
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getPaymentStatus(recordId: Int): Result<MedicalRecordPaymentStatus> {
        return try {
            val response = apiService.getMedicalRecordPaymentStatus(recordId)
            if (response.isSuccessful && response.body()?.success == true && response.body()?.data != null) {
                Result.Success(response.body()!!.data!!)
            } else {
                Result.Error(response.body()?.message ?: "Failed to get medical record payment status")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getPaymentStatus($recordId) exception", e)
            Result.Error("Network error: ${e.message}")
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
            Result.Error("Network error: ${e.message}")
        }
    }
}
