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

    suspend fun getMedicalRecords(): Result<List<MedicalRecord>> {
        return try {
            val response = apiService.getMedicalRecords()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Log.e(TAG, "getMedicalRecords failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get medical records")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecords exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getMedicalRecord(id: Int): Result<MedicalRecord> {
        return try {
            val response = apiService.getMedicalRecord(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val record = response.body()?.data
                if (record != null) Result.Success(record) else Result.Error("Medical record not found")
            } else {
                Log.e(TAG, "getMedicalRecord($id) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get medical record")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getMedicalRecord($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getMedicalRecordsByPet(petId: Int): Result<List<MedicalRecord>> {
        return try {
            val response = apiService.getMedicalRecordsByPet(petId)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
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
