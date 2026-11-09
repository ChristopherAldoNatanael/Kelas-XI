package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.ClinicCard
import com.christopheraldoo.petheal.data.model.ClinicDetail
import com.christopheraldoo.petheal.data.model.CompareItem
import com.christopheraldoo.petheal.data.model.CompareMeta
import com.christopheraldoo.petheal.data.model.ServiceCategoryDto
import com.christopheraldoo.petheal.data.model.ServiceDetailPayload
import com.christopheraldoo.petheal.data.model.UserLocation
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.remote.userFriendlyExceptionMessage
import kotlinx.coroutines.flow.first
import javax.inject.Inject
import javax.inject.Singleton

/**
 * MULTI-CLINIC DISCOVERY — sumber data layar pencarian klinik.
 *
 * Semua endpoint PUBLIK (tanpa auth), jadi discovery tetap bisa dibuka
 * sebelum login dan tidak pernah bergantung pada klinik yang dipilih.
 * Lokasi hanya dikirim sebagai parameter `lat`/`lng` untuk menghitung
 * jarak pada respons; tidak ada data pasien yang ikut terkirim.
 */
@Singleton
class DiscoveryRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager
) {
    companion object {
        private const val TAG = "DiscoveryRepository"

        // Cache singkat daftar klinik: berpindah layar Home ↔ Discovery
        // tidak boleh memicu request baru setiap kali.
        private const val CLINIC_CACHE_MS = 60_000L
    }

    data class ClinicQuery(
        val search: String? = null,
        val categorySlug: String? = null,
        val sort: DiscoverySort = DiscoverySort.NEAREST,
        val minRating: Double? = null,
        val maxDistanceKm: Double? = null,
        val openNow: Boolean? = null
    )

    enum class DiscoverySort(val apiValue: String) {
        NEAREST("distance"),
        RATING("rating"),
        NAME("name")
    }

    enum class CompareSort(val apiValue: String) {
        CHEAPEST("price_asc"),
        NEAREST("distance"),
        TOP_RATED("rating")
    }

    private var cachedClinics: List<ClinicCard> = emptyList()
    private var cachedAt = 0L
    private var cachedKey: String? = null

    suspend fun currentLocation(): UserLocation? =
        runCatching { preferencesManager.userLocation.first() }.getOrNull()

    suspend fun saveLocation(location: UserLocation) =
        preferencesManager.saveUserLocation(location)

    suspend fun clearLocation() = preferencesManager.clearUserLocation()

    /**
     * Daftar klinik. `distance_km` tetap null bila lokasi belum diatur —
     * UI wajib menyembunyikan jarak, bukan menampilkan 0 km.
     */
    suspend fun loadClinics(
        query: ClinicQuery = ClinicQuery(),
        forceRefresh: Boolean = false
    ): Result<List<ClinicCard>> {
        val location = currentLocation()
        val key = "${query.search}|${query.categorySlug}|${query.sort}|" +
            "${query.minRating}|${query.maxDistanceKm}|${query.openNow}|" +
            "${location?.latitude}|${location?.longitude}"

        val now = System.currentTimeMillis()
        if (!forceRefresh && key == cachedKey && now - cachedAt < CLINIC_CACHE_MS) {
            return Result.Success(cachedClinics)
        }

        return try {
            val response = apiService.discoverClinics(
                search = query.search?.takeIf { it.isNotBlank() },
                category = query.categorySlug,
                sort = query.sort.apiValue,
                lat = location?.latitude,
                lng = location?.longitude,
                minRating = query.minRating,
                maxDistanceKm = query.maxDistanceKm,
                openNow = query.openNow,
                limit = 50
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                val clinics = body.data ?: emptyList()
                cachedClinics = clinics
                cachedAt = now
                cachedKey = key
                Result.Success(clinics)
            } else {
                Log.e(TAG, "loadClinics failed (HTTP ${response.code()})")
                Result.Error(userFriendlyHttpMessage(response.code()))
            }
        } catch (e: Exception) {
            Log.e(TAG, "loadClinics exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun loadClinicDetail(slug: String): Result<ClinicDetail> {
        val location = currentLocation()
        return try {
            val response = apiService.getClinicDetail(
                slug = slug,
                lat = location?.latitude,
                lng = location?.longitude
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(userFriendlyHttpMessage(response.code()))
            }
        } catch (e: Exception) {
            Log.e(TAG, "loadClinicDetail exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun loadCategories(): Result<List<ServiceCategoryDto>> = try {
        val response = apiService.getServiceCategories()
        val body = response.body()
        if (response.isSuccessful && body?.success == true) {
            Result.Success(body.data ?: emptyList())
        } else {
            Result.Error(userFriendlyHttpMessage(response.code()))
        }
    } catch (e: Exception) {
        Log.e(TAG, "loadCategories exception", e)
        Result.Error(userFriendlyExceptionMessage(e))
    }

    /**
     * Perbandingan harga. `categorySlug` ATAU `serviceId` wajib: keduanya
     * membuat backend membandingkan layanan yang benar-benar setara
     * (`service_category_id` sama), bukan sekadar nama mirip.
     */
    suspend fun compare(
        categorySlug: String? = null,
        serviceId: Int? = null,
        sort: CompareSort = CompareSort.CHEAPEST,
        minRating: Double? = null,
        maxDistanceKm: Double? = null,
        openNow: Boolean? = null
    ): Result<Pair<List<CompareItem>, CompareMeta?>> {
        if (categorySlug.isNullOrBlank() && serviceId == null) {
            return Result.Error("Pilih kategori layanan terlebih dahulu untuk membandingkan harga.")
        }

        val location = currentLocation()
        return try {
            val response = apiService.compareServices(
                categorySlug = categorySlug,
                serviceId = serviceId,
                lat = location?.latitude,
                lng = location?.longitude,
                sort = sort.apiValue,
                minRating = minRating,
                maxDistanceKm = maxDistanceKm,
                openNow = openNow
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true) {
                Result.Success((body.data ?: emptyList()) to body.meta)
            } else {
                Result.Error(userFriendlyHttpMessage(response.code()))
            }
        } catch (e: Exception) {
            Log.e(TAG, "compare exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    suspend fun loadServiceDetail(serviceId: Int): Result<ServiceDetailPayload> {
        val location = currentLocation()
        return try {
            val response = apiService.getPublicServiceDetail(
                id = serviceId,
                lat = location?.latitude,
                lng = location?.longitude
            )
            val body = response.body()
            if (response.isSuccessful && body?.success == true && body.data != null) {
                Result.Success(body.data)
            } else {
                Result.Error(userFriendlyHttpMessage(response.code()))
            }
        } catch (e: Exception) {
            Log.e(TAG, "loadServiceDetail exception", e)
            Result.Error(userFriendlyExceptionMessage(e))
        }
    }

    private fun userFriendlyHttpMessage(code: Int): String = when {
        code == 404 -> "Data tidak ditemukan."
        code == 422 -> "Permintaan tidak lengkap. Pilih kategori layanan dulu."
        code == 429 -> "Terlalu banyak permintaan. Coba lagi sebentar lagi."
        code >= 500 -> "Server sedang bermasalah. Coba lagi beberapa saat lagi."
        else -> "Gagal memuat data."
    }
}
