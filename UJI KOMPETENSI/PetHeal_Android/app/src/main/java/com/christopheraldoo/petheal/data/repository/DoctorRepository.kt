package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
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
    private var cacheTimestamp: Long = 0L
    private var cachedSlug: String? = null
    private val CACHE_TTL_MS = 5 * 60 * 1000L  // 5 menit

    private fun cacheKey(doctorId: Int, slug: String?): String = "${slug ?: "-"}:$doctorId"

    private fun isCacheValid(slug: String?) =
        cachedDoctors != null && cachedSlug == slug &&
            (System.currentTimeMillis() - cacheTimestamp) < CACHE_TTL_MS

    suspend fun getDoctors(forceRefresh: Boolean = false): Result<List<Doctor>> {
        // PHASE 7: resolve tenant first so cache + request agree on scope.
        val slug = runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
        if (!forceRefresh && isCacheValid(slug)) {
            return Result.Success(cachedDoctors!!)
        }
        if (cachedSlug != slug) {
            cachedDoctors = null
            cachedDoctorById.clear()
            cacheTimestamp = 0L
        }
        return try {
            val response = apiService.getDoctors()
            if (response.isSuccessful && response.body()?.success == true) {
                val doctors = response.body()?.data ?: emptyList()
                cachedDoctors = doctors
                cachedSlug = slug
                cacheTimestamp = System.currentTimeMillis()
                doctors.forEach { if (it.id != null) cachedDoctorById[cacheKey(it.id, slug)] = it }
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
        cachedDoctorById[cacheKey(id, slug)]?.let { return Result.Success(it) }
        return try {
            val response = apiService.getDoctor(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val doctor = response.body()?.data
                if (doctor != null) {
                    cachedDoctorById[cacheKey(id, slug)] = doctor
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
                Log.e(TAG, "submitDoctorReview($doctorId) failed: ${response.body()?.message} (HTTP ${response.code()})")
                Result.Error(response.body()?.message ?: "Failed to submit review")
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
        cacheTimestamp = 0L
    }
}
