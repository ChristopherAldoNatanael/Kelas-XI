package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Clinic
import com.christopheraldoo.petheal.data.remote.ApiService
import kotlinx.coroutines.flow.first
import org.json.JSONObject
import javax.inject.Inject
import javax.inject.Singleton

/**
 * PHASE 7: multi-tenant picker + branding source.
 *
 * Public endpoints (no auth): GET /api/public/clinics(+/{slug}).
 * Selection is persisted in [PreferencesManager] and sent back as
 * X-Clinic-Slug by the interceptor; login responses refresh it from
 * `user.clinic` (backend is the authority on the binding).
 */
@Singleton
class ClinicRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager
) {
    companion object {
        private const val TAG = "ClinicRepository"
        const val FALLBACK_COLOR = "#18C964"
    }

    private fun errorMessageFrom(response: retrofit2.Response<*>?, fallback: String): String {
        val raw = runCatching { response?.errorBody()?.string() }.getOrNull()?.trim().orEmpty()
        if (raw.isNotBlank()) {
            val parsed = runCatching {
                val json = JSONObject(raw)
                when {
                    json.has("message") -> json.optString("message")
                    json.has("error") -> json.optString("error")
                    else -> null
                }
            }.getOrNull()
            if (!parsed.isNullOrBlank()) return parsed
            // Non-JSON body (ngrok error page / HTML 404 from a wrong base
            // URL) can never be a VCMS response — say so explicitly.
            val code = response?.code() ?: -1
            return "Server menjawab HTTP $code tanpa data JSON. " +
                "Periksa URL backend di pengaturan dan pastikan backend + ngrok berjalan."
        }
        val code = response?.code()
        return if (code != null && code != 200) {
            "Server menjawab HTTP $code. Periksa URL backend dan koneksi internet."
        } else {
            fallback
        }
    }

    suspend fun getClinics(): Result<List<Clinic>> {
        return try {
            val response = apiService.getPublicClinics()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Log.e(TAG, "getClinics failed (HTTP ${response.code()})")
                Result.Error(errorMessageFrom(response, "Failed to load clinics"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getClinics exception", e)
            Result.Error(
                "Tidak dapat menghubungi server. Periksa koneksi internet, " +
                    "lalu pastikan backend dan URL-nya benar."
            )
        }
    }

    suspend fun getClinic(slug: String): Result<Clinic> {
        return try {
            val response = apiService.getPublicClinic(slug)
            if (response.isSuccessful && response.body()?.success == true) {
                response.body()?.data?.let { Result.Success(it) }
                    ?: Result.Error("Clinic not found")
            } else {
                Result.Error(errorMessageFrom(response, "Clinic not found"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "getClinic exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /** Persist the user's tenant choice (picker). Never invent slugs. */
    suspend fun selectClinic(clinic: Clinic) {
        preferencesManager.saveClinic(
            id = clinic.id,
            name = clinic.name,
            slug = clinic.slug,
            logoUrl = clinic.logoUrl,
            color = clinic.primaryColor,
            address = clinic.address
        )
    }

    suspend fun currentSlug(): String? =
        preferencesManager.clinicSlug.first()

    /** Safe color parse with fallback — never crash on malformed backend data. */
    fun safeColorOrFallback(raw: String?): String =
        if (raw != null && Regex("^#[0-9A-Fa-f]{6}$").matches(raw.trim())) raw.trim()
        else FALLBACK_COLOR
}
