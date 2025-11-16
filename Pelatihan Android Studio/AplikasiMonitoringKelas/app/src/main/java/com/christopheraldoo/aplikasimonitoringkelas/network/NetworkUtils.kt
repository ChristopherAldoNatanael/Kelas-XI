package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.util.Log
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.google.gson.JsonObject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import retrofit2.Response

object NetworkUtils {

    fun isNetworkAvailable(context: Context): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val network = connectivityManager.activeNetwork ?: return false
        val networkCapabilities = connectivityManager.getNetworkCapabilities(network) ?: return false

        return networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
                networkCapabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
    }

    fun getAuthToken(context: Context): String? {
        val sessionManager = SessionManager(context)
        val token = sessionManager.getAuthToken()
        return if (token != null && token.isNotEmpty()) {
            if (token.startsWith("Bearer ")) token else "Bearer $token"
        } else {
            null
        }
    }

    suspend fun <T> safeApiCall(
        context: Context,
        apiCall: suspend () -> Response<T>,
        onError: (String) -> Unit
    ): T? {
        return try {
            if (!isNetworkAvailable(context)) {
                onError("No internet connection")
                return null
            }

            val response = withContext(Dispatchers.IO) {
                apiCall()
            }

            if (response.isSuccessful) {
                response.body()
            } else {
                val errorMessage = when (response.code()) {
                    401 -> "Unauthorized - Please login again"
                    403 -> "Forbidden - Insufficient permissions"
                    404 -> "Data not found"
                    422 -> "Validation error"
                    500 -> "Server error - Please try again later"
                    else -> "Unknown error occurred"
                }
                Log.e("NetworkUtils", "API Error: ${response.code()} - ${response.message()}")
                onError(errorMessage)
                null
            }
        } catch (e: Exception) {
            Log.e("NetworkUtils", "Network error", e)
            onError("Network error: ${e.localizedMessage}")
            null
        }
    }

    fun createJsonSchedule(
        classId: Int,
        subjectId: Int,
        teacherId: Int,
        classroomId: Int?,
        dayOfWeek: String,
        periodNumber: Int,
        startTime: String,
        endTime: String,
        academicYear: String = "2024/2025",
        semester: String = "ganjil",
        notes: String? = null
    ): JsonObject {
        val dayMapping = mapOf(
            "senin" to "monday",
            "selasa" to "tuesday", 
            "rabu" to "wednesday",
            "kamis" to "thursday",
            "jumat" to "friday",
            "sabtu" to "saturday",
            "minggu" to "sunday"
        )
        
        val englishDayOfWeek = dayMapping[dayOfWeek.lowercase()] ?: dayOfWeek.lowercase()
        
        return JsonObject().apply {
            addProperty("class_id", classId)
            addProperty("subject_id", subjectId)
            addProperty("teacher_id", teacherId)
            if (classroomId != null) {
                addProperty("classroom_id", classroomId)
            }
            addProperty("day_of_week", englishDayOfWeek)
            addProperty("period_number", periodNumber)
            addProperty("start_time", startTime)
            addProperty("end_time", endTime)
            addProperty("academic_year", academicYear)
            addProperty("semester", semester)
            if (notes != null) {
                addProperty("notes", notes)
            }
        }
    }
}
