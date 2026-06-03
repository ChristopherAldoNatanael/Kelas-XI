package com.christopheraldoo.petheal.data.repository

import android.util.Log
import com.christopheraldoo.petheal.data.model.*
import com.christopheraldoo.petheal.data.remote.ApiService
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class BookingRepository @Inject constructor(
    private val apiService: ApiService
) {
    companion object {
        private const val TAG = "BookingRepository"
    }

    /**
     * ✅ OPTIMIZED: No more manual token passing.
     * NetworkInterceptor handles auth automatically.
     * Improved error messages based on HTTP status codes.
     */
    private fun getErrorMessage(responseCode: Int, fallbackMessage: String?): String {
        return when (responseCode) {
            401 -> "Session expired. Please login again."
            403 -> "Access denied. You don't have permission."
            404 -> "Booking not found."
            422 -> "Validation error. Please check your input."
            500 -> "Server error. Please try again later."
            else -> fallbackMessage ?: "Failed to complete request"
        }
    }

    suspend fun getBookings(): Result<List<Booking>> {
        return try {
            val response = apiService.getBookings()

            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "getBookings failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getBookings exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getUpcomingBookings(): Result<List<Booking>> {
        return try {
            val response = apiService.getUpcomingBookings()

            if (response.isSuccessful && response.body()?.success == true) {
                Result.Success(response.body()?.data ?: emptyList())
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "getUpcomingBookings failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getUpcomingBookings exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun getBooking(id: Int): Result<Booking> {
        return try {
            val response = apiService.getBooking(id)

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Booking not found")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "getBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "getBooking($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun createBooking(booking: BookingRequest): Result<Booking> {
        return try {
            val response = apiService.createBooking(booking)

            if (response.isSuccessful && response.body()?.success == true) {
                val createdBooking = response.body()?.data
                if (createdBooking != null) {
                    Result.Success(createdBooking)
                } else {
                    Result.Error("Failed to create booking")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "createBooking failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "createBooking exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun cancelBooking(id: Int, reason: String): Result<Booking> {
        return try {
            val response = apiService.cancelBooking(id, mapOf("reason" to reason))

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Failed to cancel booking")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "cancelBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "cancelBooking($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun rescheduleBooking(id: Int, newDate: String, newTime: String): Result<Booking> {
        return try {
            val response = apiService.rescheduleBooking(id, RescheduleRequest(newDate, newTime))

            if (response.isSuccessful && response.body()?.success == true) {
                val booking = response.body()?.data
                if (booking != null) {
                    Result.Success(booking)
                } else {
                    Result.Error("Failed to reschedule booking")
                }
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "rescheduleBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "rescheduleBooking($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }

    suspend fun deleteBooking(id: Int): Result<Unit> {
        return try {
            val response = apiService.deleteBooking(id)

            if (response.isSuccessful) {
                Result.Success(Unit)
            } else {
                val error = getErrorMessage(response.code(), response.body()?.message)
                Log.e(TAG, "deleteBooking($id) failed: $error (HTTP ${response.code()})")
                Result.Error(error)
            }
        } catch (e: Exception) {
            Log.e(TAG, "deleteBooking($id) exception", e)
            Result.Error("Network error: ${e.message}")
        }
    }
}
