package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class DoctorRepository @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager
) {
    companion object {
        private const val TAG = "DoctorRepository"
    }

    // ── In-memory cache ──────────────────────────────────────────────────────
    // PHASE 7: tenant-aware. Backend scopes doctors by the caller's clinic,
    // so the cache key includes the clinic slug — Clinic A must never read
    // Clinic B's cached list (matches AGENTS.md key-by-slug plan).
    private var cachedDoctors: List<Doctor>? = null
    private val cachedDoctorById = mutableMapOf<String, Doctor>()
    private val cachedDoctorAt = mutableMapOf<String, Long>()
    private var cacheTimestamp: Long = 0L
    private var cachedSlug: String? = null
    private val CACHE_TTL_MS = 5 * 60 * 1000L  // 5 menit

    // ── Sinyal perubahan rating ──────────────────────────────────────────
    // Submit review terjadi di ViewModel layar detail, sedangkan daftar
    // dokter diamati ViewModel layar list (instance berbeda). Versi ini
    // naik setiap cache rating di-patch agar semua pengamat me-refresh
    // dari snapshot tanpa perlu network call baru.
    private val _ratingVersion = MutableStateFlow(0L)
    val ratingVersion: StateFlow<Long> = _ratingVersion.asStateFlow()

    private fun cacheKey(doctorId: Int, slug: String?): String = "${slug ?: "-"}:$doctorId"

    private fun isCacheValid(slug: String?) =
        cachedDoctors != null && cachedSlug == slug &&
            (System.currentTimeMillis() - cacheTimestamp) < CACHE_TTL_MS

    suspend fun getDoctors(forceRefresh: Boolean = false): Result<List<Doctor>> {
        // PHASE 7: resolve tenant first so cache + request agree on scope.
        val slug = runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
        val snapshot = cachedDoctors
        if (!forceRefresh && snapshot != null && cachedSlug == slug &&
            (System.currentTimeMillis() - cacheTimestamp) < CACHE_TTL_MS) {
            return Result.Success(snapshot)
        }
        if (cachedSlug != slug) {
            cachedDoctors = null
            cachedDoctorById.clear()
            cachedDoctorAt.clear()
            cacheTimestamp = 0L
        }
        return try {
            val response = apiService.getDoctors()
            if (response.isSuccessful && response.body()?.success == true) {
                val doctors = response.body()?.data ?: emptyList()
                cachedDoctors = doctors
                cachedSlug = slug
                cacheTimestamp = System.currentTimeMillis()
                doctors.forEach {
                    if (it.id != null) {
                        cachedDoctorById[cacheKey(it.id, slug)] = it
                        cachedDoctorAt[cacheKey(it.id, slug)] = cacheTimestamp
                    }
                }
                Result.Success(doctors)
            } else {
                Log.e(TAG, "getDoctors failed: ${response.body()?.message} (HTTP ${response.code()})")
                cachedDoctors?.let { if (cachedSlug == slug) return Result.Success(it) }
                Result.Error(response.body()?.message ?: "Failed to get doctors")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDoctors exception", e)
            cachedDoctors?.let { if (cachedSlug == slug) return Result.Success(it) }
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getDoctor(id: Int): Result<Doctor> {
        val slug = runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
        val key = cacheKey(id, slug)
        // Cache per-ID WAJIB ada TTL-nya: tanpa ini, salinan dokter yang
        // dibaca sekali tidak pernah diperbarui (stale selamanya) meski
        // data server (nama/foto/rating) sudah berubah.
        val cachedAt = cachedDoctorAt[key] ?: 0L
        if (System.currentTimeMillis() - cachedAt < CACHE_TTL_MS) {
            cachedDoctorById[key]?.let { return Result.Success(it) }
        }
        return try {
            val response = apiService.getDoctor(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val doctor = response.body()?.data
                if (doctor != null) {
                    cachedDoctorById[cacheKey(id, slug)] = doctor
                    cachedDoctorAt[cacheKey(id, slug)] = System.currentTimeMillis()
                    Result.Success(doctor)
                } else {
                    Result.Error("Doctor not found")
                }
            } else {
                Log.e(TAG, "getDoctor($id) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get doctor")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDoctor($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getDoctorSlots(doctorId: Int, date: String): Result<List<TimeSlot>> {
        return try {
            val response = apiService.getDoctorSlots(doctorId, date)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                Log.e(TAG, "getDoctorSlots($doctorId, $date) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get slots")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDoctorSlots($doctorId, $date) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getDoctorReviews(doctorId: Int): Result<DoctorReviewsData> {
        return try {
            val response = apiService.getDoctorReviews(doctorId)
            if (response.isSuccessful && response.body()?.success == true) {
                response.body()?.data?.let { Result.Success(it) }
                    ?: Result.Error("Reviews not found")
            } else {
                Log.e(TAG, "getDoctorReviews($doctorId) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to get doctor reviews")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDoctorReviews($doctorId) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun submitDoctorReview(
        doctorId: Int,
        bookingId: Int,
        rating: Int,
        review: String?
    ): Result<DoctorReview> {
        return try {
            val response = apiService.submitDoctorReview(
                doctorId,
                DoctorReviewRequest(bookingId = bookingId, rating = rating, review = review)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                response.body()?.data?.let { Result.Success(it) }
                    ?: Result.Error("Review was submitted but response was empty")
            } else {
                // Pada HTTP error, response.body() null — baca pesan asli
                // server dari errorBody (mis. 409 "already reviewed") agar
                // ViewModel bisa menanganinya dengan pesan yang ramah.
                val serverMsg = runCatching {
                    response.errorBody()?.string()?.let { body ->
                        Regex("\"message\"\\s*:\\s*\"([^\"]+)\"")
                            .find(body)?.groupValues?.getOrNull(1)
                    }
                }.getOrNull()
                Log.e(TAG, "submitDoctorReview($doctorId) failed: ${serverMsg ?: "?"} (HTTP ${response.code()})")
                Result.Error(serverMsg ?: "Failed to submit review")
            }
        } catch (e: Exception) {
            Log.e(TAG, "submitDoctorReview($doctorId) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    /** Panggil setelah booking berhasil dibuat agar cache diperbarui */
    fun invalidateCache() {
        cachedDoctors = null
        cachedDoctorById.clear()
        cachedDoctorAt.clear()
        cacheTimestamp = 0L
    }

    /**
     * Patch rating pada cache list + per-ID setelah review terkirim.
     *
     * Kenapa perlu: endpoint list dokter di-cache 5 menit di RAM (dan
     * di-cache lagi di sisi server), jadi force-refresh pun bisa
     * mengembalikan angka lama. Data authoritative-nya adalah respons
     * GET reviews/{id} yang baru saja dimuat ulang — patch cache dengan
     * angka itu agar list & detail konsisten tanpa refresh manual.
     */
    fun updateCachedRating(doctorId: Int, averageRating: Double, totalReviews: Int) {
        var changed = false
        cachedDoctors?.let { list ->
            val patched = list.map { doctor ->
                if (doctor.id == doctorId) {
                    changed = true
                    doctor.copy(averageRating = averageRating, reviewsCount = totalReviews)
                } else doctor
            }
            if (changed) cachedDoctors = patched
        }
        cachedDoctorById.keys
            .filter { it.endsWith(":$doctorId") }
            .forEach { key ->
                cachedDoctorById[key]?.let { doctor ->
                    cachedDoctorById[key] =
                        doctor.copy(averageRating = averageRating, reviewsCount = totalReviews)
                    cachedDoctorAt[key] = System.currentTimeMillis()
                    changed = true
                }
            }
        if (changed) _ratingVersion.value += 1
    }

    /** Snapshot cache list untuk sinkronisasi antar-ViewModel tanpa network. */
    fun snapshotDoctors(): List<Doctor> = cachedDoctors.orEmpty()

    /**
     * Revalidasi baris yang mencurigakan: rata-rata > 0 tapi jumlah ulasan
     * 0. Pasangan seperti itu tidak mungkin berasal dari satu respons
     * server yang utuh (keduanya dihitung dari tabel yang sama), jadi itu
     * tanda salah satu nilainya basi — ambil pasangan authoritative dari
     * endpoint reviews lalu patch cache. Diam-diam, tanpa loading UI.
     */
    suspend fun revalidateSuspiciousRatings(doctors: List<Doctor>) {
        doctors
            .filter { (it.averageRating ?: 0.0) > 0.0 && (it.reviewsCount ?: 0) <= 0 && it.id != null }
            .take(5)
            .forEach { doctor ->
                val id = doctor.id ?: return@forEach
                Log.w(TAG, "revalidateSuspiciousRatings: doctorId=$id avg=${doctor.averageRating} count=${doctor.reviewsCount}")
                when (val r = getDoctorReviews(id)) {
                    is Result.Success -> {
                        val avg = r.data.averageRating ?: 0.0
                        val total = r.data.totalReviews ?: r.data.reviews.orEmpty().size
                        updateCachedRating(id, avg, total)
                    }
                    else -> Unit
                }
            }
    }
}
