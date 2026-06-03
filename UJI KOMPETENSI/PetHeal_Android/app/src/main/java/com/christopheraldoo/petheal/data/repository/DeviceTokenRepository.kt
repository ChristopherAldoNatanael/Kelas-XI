package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class DeviceTokenRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager
) {
    companion object {
        private const val TAG = "DeviceTokenRepository"
    }

    suspend fun saveDeviceToken(token: String, deviceType: String = "android"): Result<Unit> {
        return try {
            val response = apiService.saveDeviceToken(DeviceTokenRequest(token = token, deviceType = deviceType))
            if (response.isSuccessful && response.body()?.success == true) {
                preferencesManager.saveFcmToken(token)
                Result.Success(Unit)
            } else {
                Log.e(TAG, "saveDeviceToken failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to save device token")
            }
        } catch (e: Exception) {
            Log.e(TAG, "saveDeviceToken exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun removeDeviceToken(token: String): Result<Unit> {
        return try {
            val response = apiService.removeDeviceToken(DeviceTokenRequest(token = token, deviceType = "android"))
            if (response.isSuccessful) {
                Result.Success(Unit)
            } else {
                Log.e(TAG, "removeDeviceToken failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to remove device token")
            }
        } catch (e: Exception) {
            Log.e(TAG, "removeDeviceToken exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }
}
