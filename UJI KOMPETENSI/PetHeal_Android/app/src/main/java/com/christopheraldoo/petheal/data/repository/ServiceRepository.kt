package com.christopheraldoo.petheal.data.repository

import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Service
import com.christopheraldoo.petheal.data.remote.ApiService
import kotlinx.coroutines.flow.first
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class ServiceRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager
) {
    companion object {
        private const val TAG = "ServiceRepository"
    }

    /**
     * Katalog layanan.
     *
     * `clinicId` (MULTI-CLINIC, 2026-11-09) memaksa satu klinik. WAJIB dipakai
     * alur booking: tanpa ini pasien menerima layanan SEMUA klinik, lalu
     * memilih layanan klinik lain dari dokter yang sudah dipilih — pasangan
     * yang ditolak backend ("Dokter dan layanan harus berasal dari klinik
     * yang sama").
     */
    suspend fun getServices(clinicId: Int? = null): Result<List<Service>> {
        return try {
            // PHASE 7: anonymous catalog requires explicit ?clinic_slug=.
            // Authenticated calls are scoped server-side; the param is
            // harmless then and required when logged out. Saat clinicId
            // dikirim, server mengabaikan slug dan memakai klinik tersebut.
            val slug = if (clinicId == null) {
                runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
            } else null
            val response = apiService.getServices(slug, clinicId)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Log.e(TAG, "getServices failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to load services")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getServices exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }
}
