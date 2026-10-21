package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Clinic
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.util.ClinicContact
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

        // Kontak dipakai tombol Bantuan; cache singkat supaya membuka sheet
        // berkali-kali tidak menghantam API, tapi perubahan nomor di admin
        // tetap terasa cepat.
        private const val SUPPORT_CACHE_MS = 5 * 60 * 1000L
    }

    @Volatile
    private var cachedSupportContact: ClinicContact? = null

    @Volatile
    private var cachedSupportSlug: String? = null

    @Volatile
    private var supportCachedAt: Long = 0L

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
        // Kontak bantuan milik klinik sebelumnya — jangan sampai terbawa.
        cachedSupportContact = null
        cachedSupportSlug = null
        supportCachedAt = 0L
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

    /**
     * Kontak bantuan klinik aktif (dipakai semua tombol Bantuan).
     *
     * Sumber tunggal: `clinics.phone` / `clinics.email` lewat endpoint publik,
     * jadi tidak ada setting ganda di aplikasi dan perubahan dari web admin
     * langsung terpakai setelah cache singkat kedaluwarsa.
     */
    suspend fun getSupportContact(forceRefresh: Boolean = false): Result<ClinicContact> {
        val slug = currentSlug()
        if (slug.isNullOrBlank()) {
            return Result.Error("Klinik belum dipilih. Pilih klinik terlebih dahulu.")
        }

        val now = System.currentTimeMillis()
        val cached = cachedSupportContact
        // Cache terikat slug: ganti klinik tidak boleh menampilkan kontak lama.
        if (!forceRefresh &&
            cached != null &&
            cachedSupportSlug == slug &&
            now - supportCachedAt < SUPPORT_CACHE_MS
        ) {
            return Result.Success(cached)
        }

        return when (val result = getClinic(slug)) {
            is Result.Success -> {
                val contact = ClinicContact(
                    clinicName = result.data.name,
                    phone = result.data.phone,
                    email = result.data.email
                )
                cachedSupportContact = contact
                cachedSupportSlug = slug
                supportCachedAt = now
                Result.Success(contact)
            }
            is Result.Error -> Result.Error(result.message)
            else -> Result.Error("Kontak klinik belum bisa dimuat.")
        }
    }

    /** Safe color parse with fallback — never crash on malformed backend data. */
    fun safeColorOrFallback(raw: String?): String =
        if (raw != null && Regex("^#[0-9A-Fa-f]{6}$").matches(raw.trim())) raw.trim()
        else FALLBACK_COLOR
}
