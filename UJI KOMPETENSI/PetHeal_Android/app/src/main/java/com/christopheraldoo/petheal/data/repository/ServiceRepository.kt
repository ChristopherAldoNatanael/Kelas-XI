package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.Service
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class ServiceRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "ServiceRepository"
    }

    suspend fun getServices(): Result<List<Service>> {
        return try {
            val response = apiService.getServices()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Log.e(TAG, "getServices failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to load services")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getServices exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }
}
