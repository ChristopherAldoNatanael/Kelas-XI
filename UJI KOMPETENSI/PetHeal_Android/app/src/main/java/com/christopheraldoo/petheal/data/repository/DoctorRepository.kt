package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class DoctorRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "DoctorRepository"
    }

    // ── In-memory cache ──────────────────────────────────────────────────────
    // Simpan hasil fetch di RAM. Selama sesi berjalan, buka halaman berulang
    // kali langsung pakai cache — tidak perlu network sama sekali.
    private var cachedDoctors: List<Doctor>? = null
    private val cachedDoctorById = mutableMapOf<Int, Doctor>()
    private var cacheTimestamp: Long = 0L
    private val CACHE_TTL_MS = 5 * 60 * 1000L  // 5 menit

    private fun isCacheValid() =
        cachedDoctors != null && (System.currentTimeMillis() - cacheTimestamp) < CACHE_TTL_MS

    suspend fun getDoctors(forceRefresh: Boolean = false): Result<List<Doctor>> {
        if (!forceRefresh && isCacheValid()) {
            return Result.Success(cachedDoctors!!)
        }
        return try {
            val response = apiService.getDoctors()
            if (response.isSuccessful && response.body()?.success == true) {
                val doctors = response.body()?.data ?: emptyList()
                cachedDoctors = doctors
                cacheTimestamp = System.currentTimeMillis()
                doctors.forEach { if (it.id != null) cachedDoctorById[it.id] = it }
                Result.Success(doctors)
            } else {
                Log.e(TAG, "getDoctors failed: ${response.body()?.message} (HTTP ${response.code()})")
                cachedDoctors?.let { return Result.Success(it) }
                Result.Error(response.body()?.message ?: "Failed to get doctors")
            }
        } catch (e: Exception) {
            Log.e(TAG, "getDoctors exception", e)
            cachedDoctors?.let { return Result.Success(it) }
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getDoctor(id: Int): Result<Doctor> {
        cachedDoctorById[id]?.let { return Result.Success(it) }
        return try {
            val response = apiService.getDoctor(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val doctor = response.body()?.data
                if (doctor != null) {
                    cachedDoctorById[id] = doctor
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
        cacheTimestamp = 0L
    }
}
