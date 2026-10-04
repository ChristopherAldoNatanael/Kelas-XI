package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.DashboardData
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

/**
 * PHASE 7: dashboard behind the repository layer (was called straight from
 * HomeViewModel). Same envelope discipline as the other repositories.
 */
@Singleton
class DashboardRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "DashboardRepository"
    }

    suspend fun getDashboard(): Result<DashboardData> {
        return try {
            val response = apiService.getDashboard()
            if (response.isSuccessful && response.body()?.success == true) {
                response.body()?.data?.let { Result.Success(it) }
                    ?: Result.Error("Invalid response")
            } else {
                Log.e(TAG, "getDashboard failed (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to load dashboard")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDashboard exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }
}
