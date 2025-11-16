package com.christopheraldoo.aplikasimonitoringkelas.repository

import android.content.Context
import android.util.Log
import com.christopheraldoo.aplikasimonitoringkelas.cache.CacheManager
import com.christopheraldoo.aplikasimonitoringkelas.data.UserApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TeacherApi
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectApi
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomApi
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.data.DashboardSummary
import com.christopheraldoo.aplikasimonitoringkelas.data.AttendanceSubmitRequest
import com.christopheraldoo.aplikasimonitoringkelas.data.AttendanceHistoryItem
import com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.google.gson.reflect.TypeToken
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.withContext
import java.io.IOException

/**
 * Repository untuk menangani semua API calls dengan caching dan error handling
 * Menggunakan pattern yang professional dan efficient
 */
class DataRepository(private val context: Context) {
    private val apiService = RetrofitClient.createApiService(context)
    private val sessionManager = SessionManager(context)
    private val cacheManager = CacheManager(context)

    companion object {
        private const val TAG = "DataRepository"
    }

    /**
     * Get token dari SessionManager dengan proper format
     */
    private fun getBearerToken(): String {
        val sharedPref = context.getSharedPreferences("MonitoringKelasSession", Context.MODE_PRIVATE)
        val token = sharedPref.getString("token", null) ?: return ""
        return if (token.isEmpty()) "" else {
            if (token.startsWith("Bearer ")) token else "Bearer $token"
        }
    }

    /**
     * Log network connection details for debugging
     */
    private fun logNetworkDebugInfo(url: String, method: String = "GET") {
        Log.d(TAG, "=== NETWORK DEBUG INFO ===")
        Log.d(TAG, "URL: $url")
        Log.d(TAG, "Method: $method")
        val resolvedBase = com.christopheraldoo.aplikasimonitoringkelas.network.NetworkConfig.BaseUrls.getDefault(context)
        Log.d(TAG, "Network Config - BASE: $resolvedBase")
        Log.d(TAG, "Network Config - CURRENT_IP: ${com.christopheraldoo.aplikasimonitoringkelas.network.NetworkConfig.BaseUrls.CURRENT_IP_URL}")
        Log.d(TAG, "=== END NETWORK DEBUG ===")
    }

    private fun isConnectivityError(e: Throwable): Boolean {
        val msg = e.localizedMessage?.lowercase() ?: ""
        return (e is IOException) ||
                msg.contains("failed to connect") ||
                msg.contains("timeout") ||
                msg.contains("unable to resolve host") ||
                msg.contains("host unreachable")
    }

    private fun friendlyNetworkMessage(e: Throwable): String = when {
        isConnectivityError(e) -> "Tidak bisa terhubung ke server. Pastikan server Laravel aktif dan jaringan sama."
        else -> e.localizedMessage ?: "Terjadi kesalahan"
    }

    private suspend fun <T> withConnectionFallback(op: suspend () -> T): T {
        return try {
            op()
        } catch (e: IOException) {
            // Kemungkinan gagal konek (timeout/host unreachable). Ganti base URL lalu coba sekali lagi
            Log.w(TAG, "Connection error detected: ${e.localizedMessage}. Flipping base URL and retrying once...")
            RetrofitClient.markConnectionFailureAndFlipBaseUrl(context)
            op()
        }
    }

    /**
     * Load users dengan caching - 5 menit TTL (lebih sering refresh)
     */
    suspend fun getUsers(forceRefresh: Boolean = false): Result<List<UserApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "users_list"

            // Gunakan cache jika valid dan bukan force refresh
            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                Log.d(TAG, "Loading users from cache")
                val cachedData = cacheManager.getData(cacheKey, object : TypeToken<List<UserApi>>() {})
                return@withContext Result.success(cachedData ?: emptyList())
            }

            val token = getBearerToken()
            if (token.isEmpty()) {
                Log.e(TAG, "Token kosong - login mungkin expired")
                return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            }

            val base = com.christopheraldoo.aplikasimonitoringkelas.network.NetworkConfig.BaseUrls.getDefault(context)
            logNetworkDebugInfo("${base}${com.christopheraldoo.aplikasimonitoringkelas.network.NetworkConfig.Endpoints.USERS}", "GET")
            Log.d(TAG, "Fetching users from API with token: ${token.substring(0, minOf(20, token.length))}...")
            val response = withConnectionFallback { apiService.getUsers(token) }

            Log.d(TAG, "Users response code: ${response.code()}")
            Log.d(TAG, "Users response message: ${response.message()}")
            Log.d(TAG, "Users response isSuccessful: ${response.isSuccessful}")
            if (!response.isSuccessful) {
                Log.e(TAG, "Users API call failed with code ${response.code()}")
                Log.e(TAG, "Error body: ${response.errorBody()?.string()}")
            }
            
            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    val data = body.data ?: emptyList()
                    Log.d(TAG, "Users loaded successfully: ${data.size} users")
                    cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                    Result.success(data)
                } else {
                    Log.e(TAG, "API error: ${body?.message}")
                    Result.failure(Exception(body?.message ?: "Gagal memuat users"))
                }
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "HTTP Error ${response.code()}: $errorBody")
                Result.failure(Exception("HTTP ${response.code()}: $errorBody"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getUsers: ${e.message}", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    /**
     * Load teachers dengan caching - 5 menit TTL (lebih sering refresh)
     */
    suspend fun getTeachers(forceRefresh: Boolean = false): Result<List<TeacherApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "teachers_list"

            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                Log.d(TAG, "Loading teachers from cache")
                val cachedData = cacheManager.getData(cacheKey, object : TypeToken<List<TeacherApi>>() {})
                return@withContext Result.success(cachedData ?: emptyList())
            }

            val token = getBearerToken()
            if (token.isEmpty()) {
                Log.e(TAG, "Token kosong")
                return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            }

            Log.d(TAG, "Fetching teachers from API")
            val response = withConnectionFallback { apiService.getTeachers(token) }
            
            Log.d(TAG, "Teachers response code: ${response.code()}")

            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    val data = body.data ?: emptyList()
                    Log.d(TAG, "Teachers loaded successfully: ${data.size} teachers")
                    cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                    Result.success(data)
                } else {
                    Log.e(TAG, "API error: ${body?.message}")
                    Result.failure(Exception(body?.message ?: "Gagal memuat guru"))
                }
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "HTTP Error ${response.code()}: $errorBody")
                Result.failure(Exception("HTTP ${response.code()}: $errorBody"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getTeachers: ${e.message}", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    /**
     * Load subjects dengan caching - 5 menit TTL (lebih sering refresh)
     */
    suspend fun getSubjects(forceRefresh: Boolean = false): Result<List<SubjectApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "subjects_list"

            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                Log.d(TAG, "Loading subjects from cache")
                val cachedData = cacheManager.getData(cacheKey, object : TypeToken<List<SubjectApi>>() {})
                return@withContext Result.success(cachedData ?: emptyList())
            }

            val token = getBearerToken()
            if (token.isEmpty()) {
                Log.e(TAG, "Token kosong")
                return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            }

            Log.d(TAG, "Fetching subjects from API")
            val response = withConnectionFallback { apiService.getSubjects(token) }
            
            Log.d(TAG, "Subjects response code: ${response.code()}")

            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    val data = body.data ?: emptyList()
                    Log.d(TAG, "Subjects loaded successfully: ${data.size} subjects")
                    cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                    Result.success(data)
                } else {
                    Log.e(TAG, "API error: ${body?.message}")
                    Result.failure(Exception(body?.message ?: "Gagal memuat mata pelajaran"))
                }
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "HTTP Error ${response.code()}: $errorBody")
                Result.failure(Exception("HTTP ${response.code()}: $errorBody"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getSubjects: ${e.message}", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    /**
     * Load classrooms dengan caching - 5 menit TTL (lebih sering refresh)
     */
    suspend fun getClassrooms(forceRefresh: Boolean = false): Result<List<ClassroomApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "classrooms_list"

            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                Log.d(TAG, "Loading classrooms from cache")
                val cachedData = cacheManager.getData(cacheKey, object : TypeToken<List<ClassroomApi>>() {})
                return@withContext Result.success(cachedData ?: emptyList())
            }

            val token = getBearerToken()
            if (token.isEmpty()) {
                Log.e(TAG, "Token kosong")
                return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            }

            Log.d(TAG, "Fetching classrooms from API")
            val response = withConnectionFallback { apiService.getClassrooms(token) }
            
            Log.d(TAG, "Classrooms response code: ${response.code()}")

            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    val data = body.data ?: emptyList()
                    Log.d(TAG, "Classrooms loaded successfully: ${data.size} classrooms")
                    cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                    Result.success(data)
                } else {
                    Log.e(TAG, "API error: ${body?.message}")
                    Result.failure(Exception(body?.message ?: "Gagal memuat ruangan"))
                }
            } else {
                val errorBody = response.errorBody()?.string()
                Log.e(TAG, "HTTP Error ${response.code()}: $errorBody")
                Result.failure(Exception("HTTP ${response.code()}: $errorBody"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getClassrooms: ${e.message}", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    /**
     * Load schedules dengan caching - 5 menit TTL (data lebih volatile)
     * Updated to use public endpoint first for better reliability
     */
    suspend fun getSchedules(
        day: String? = null,
        classId: Int? = null,
        teacherId: Int? = null,
        forceRefresh: Boolean = false
    ): Result<List<ScheduleApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "schedules_${classId}_${day}_${teacherId}"

            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                Log.d(TAG, "Loading schedules from cache")
                val cachedData = cacheManager.getData(cacheKey, object : TypeToken<List<ScheduleApi>>() {})
                return@withContext Result.success(cachedData ?: emptyList())
            }

            val token = getBearerToken()
            
            Log.d(TAG, "Fetching schedules from API (classId=$classId, day=$day, teacherId=$teacherId)")
            
            // Try authenticated endpoint first if we have a token
            var response = if (token.isNotEmpty()) {
                Log.d(TAG, "Using authenticated schedules endpoint")
                withConnectionFallback { apiService.getSchedules(token, day, classId, teacherId) }
            } else {
                null
            }

            // If auth failed or no token, try public endpoint
            if (response == null || !response.isSuccessful) {
                if (response != null) {
                    Log.w(TAG, "Auth endpoint failed (${response.code()}), trying public endpoint")
                } else {
                    Log.d(TAG, "No token available, using public endpoint")
                }
                
                // Use today's schedule public endpoint if available
                response = if (classId != null && day == null) {
                    // Get all schedules for a specific class (weekly view)
                    Log.d(TAG, "Using public schedules endpoint for class $classId")
                    withConnectionFallback { 
                        // Use the mobile endpoint which doesn't require auth
                        apiService.getSchedules("", null, classId, teacherId) 
                    }
                } else if (classId != null) {
                    Log.d(TAG, "Using public today schedule endpoint for class $classId")
                    withConnectionFallback { apiService.getTodaySchedulePublic(classId) }
                } else {
                    Log.d(TAG, "Using general public schedules endpoint")
                    withConnectionFallback { 
                        apiService.getSchedules("", day, classId, teacherId) 
                    }
                }
            }

            if (response.isSuccessful && response.body()?.success == true) {
                val data = response.body()?.data ?: emptyList()
                Log.d(TAG, "Schedules loaded successfully: ${data.size} schedules")
                cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                Result.success(data)
            } else {
                val errorMsg = response.body()?.message ?: "Gagal memuat jadwal (HTTP ${response.code()})"
                Log.e(TAG, "Schedule load failed: $errorMsg")
                Result.failure(Exception(errorMsg))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getSchedules: ${e.message}", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    /**
     * Load dashboard data dengan sequential loading dan caching yang lebih baik
     * Mengurangi beban server dengan menghindari parallel requests berlebihan
     */
    suspend fun getDashboardData(forceRefresh: Boolean = false): Result<DashboardData> = withContext(Dispatchers.IO) {
        try {
            Log.d(TAG, "Starting dashboard data load (forceRefresh=$forceRefresh)")

            // Load data secara sequential untuk mengurangi beban server
            // Prioritas: users -> teachers -> subjects -> schedules (schedules paling berat)
            val usersResult = getUsers(forceRefresh)
            if (usersResult.isFailure) {
                Log.e(TAG, "Failed to load users: ${usersResult.exceptionOrNull()?.message}")
                return@withContext Result.failure(Exception("Gagal memuat data pengguna"))
            }

            val teachersResult = getTeachers(forceRefresh)
            if (teachersResult.isFailure) {
                Log.e(TAG, "Failed to load teachers: ${teachersResult.exceptionOrNull()?.message}")
                return@withContext Result.failure(Exception("Gagal memuat data guru"))
            }

            val subjectsResult = getSubjects(forceRefresh)
            if (subjectsResult.isFailure) {
                Log.e(TAG, "Failed to load subjects: ${subjectsResult.exceptionOrNull()?.message}")
                return@withContext Result.failure(Exception("Gagal memuat data mata pelajaran"))
            }

            // Load schedules last (paling berat) - dengan limit untuk performa
            val schedulesResult = getSchedules(forceRefresh = forceRefresh)
            if (schedulesResult.isFailure) {
                Log.e(TAG, "Failed to load schedules: ${schedulesResult.exceptionOrNull()?.message}")
                return@withContext Result.failure(Exception("Gagal memuat data jadwal"))
            }

            val data = DashboardData(
                users = usersResult.getOrNull() ?: emptyList(),
                schedules = schedulesResult.getOrNull() ?: emptyList(),
                teachers = teachersResult.getOrNull() ?: emptyList(),
                subjects = subjectsResult.getOrNull() ?: emptyList()
            )

            Log.d(TAG, "Dashboard data loaded successfully: ${data.users.size} users, ${data.schedules.size} schedules, ${data.teachers.size} teachers, ${data.subjects.size} subjects")
            Result.success(data)
        } catch (e: IOException) {
            Log.w(TAG, "Connection error detected: ${e.localizedMessage}. Flipping base URL and retrying once...")
            RetrofitClient.markConnectionFailureAndFlipBaseUrl(context)
            getDashboardData(forceRefresh)
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getDashboardData: ${e.message}", e)
            Result.failure(e)
        }
    }

    /**
     * Clear cache untuk refresh manual
     */
    fun clearCache() {
        cacheManager.clearAllCache()
        Log.d(TAG, "Cache cleared")
    }

    suspend fun getDashboardSummary(): Result<DashboardSummary> = withContext(Dispatchers.IO) {
        try {
            val response = withConnectionFallback { apiService.getDashboardSummary() }
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data ?: DashboardSummary())
            } else {
                Result.failure(Exception(response.body()?.message ?: "Gagal memuat ringkasan dashboard"))
            }
        } catch (e: Exception) {
            Log.e(TAG, "Exception in getDashboardSummary", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    suspend fun getTodaySchedule(classId: Int?, forceRefresh: Boolean = false): Result<List<ScheduleApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "today_schedule_${classId ?: 0}"
            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                val cached = cacheManager.getData(cacheKey, object : TypeToken<List<ScheduleApi>>() {})
                return@withContext Result.success(cached ?: emptyList())
            }
            val token = getBearerToken()
            if (token.isEmpty()) return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            val resp = withConnectionFallback { apiService.getTodaySchedule(token, classId) }
            if (resp.isSuccessful && resp.body()?.success == true) {
                val data = resp.body()?.data ?: emptyList()
                cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                Result.success(data)
            } else Result.failure(Exception(resp.body()?.message ?: "Gagal memuat jadwal hari ini"))
        } catch (e: Exception) {
            Log.e(TAG, "getTodaySchedule error", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    suspend fun getTodaySchedulePublic(classId: Int?, forceRefresh: Boolean = false): Result<List<ScheduleApi>> = withContext(Dispatchers.IO) {
        try {
            val cacheKey = "today_schedule_public_${classId ?: 0}"
            if (!forceRefresh && cacheManager.isCacheValid(cacheKey, CacheManager.TTL_SHORT)) {
                val cached = cacheManager.getData(cacheKey, object : TypeToken<List<ScheduleApi>>() {})
                return@withContext Result.success(cached ?: emptyList())
            }
            val resp = withConnectionFallback { apiService.getTodaySchedulePublic(classId) }
            if (resp.isSuccessful && resp.body()?.success == true) {
                val data = resp.body()?.data ?: emptyList()
                cacheManager.saveData(cacheKey, data, CacheManager.TTL_SHORT)
                Result.success(data)
            } else Result.failure(Exception(resp.body()?.message ?: "Gagal memuat jadwal hari ini"))
        } catch (e: Exception) {
            Log.e(TAG, "getTodaySchedulePublic error", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    // ========== KEHADIRAN ENDPOINTS - MOVED TO NetworkRepository.kt ==========
    // These methods have been migrated to NetworkRepository for better separation of concerns
    // Please use NetworkRepository.submitKehadiran() and NetworkRepository.getKehadiranHistory()

    /*
    suspend fun submitAttendance(body: AttendanceSubmitRequest): Result<Boolean> = withContext(Dispatchers.IO) {
        try {
            val token = getBearerToken()
            if (token.isEmpty()) return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            val resp = withConnectionFallback { apiService.submitAttendance(token, body) }
            if (resp.isSuccessful && resp.body()?.success == true) Result.success(true)
            else Result.failure(Exception(resp.body()?.message ?: "Gagal mengirim kehadiran"))
        } catch (e: Exception) {
            Log.e(TAG, "submitAttendance error", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }

    suspend fun getAttendanceHistory(studentId: Int, date: String? = null, subject: String? = null, page: Int? = null, forceRefresh: Boolean = false): Result<List<AttendanceHistoryItem>> = withContext(Dispatchers.IO) {
        try {
            val key = "attendance_history_${studentId}_${date ?: "all"}_${subject ?: "all"}_${page ?: 1}"
            if (!forceRefresh && cacheManager.isCacheValid(key, CacheManager.TTL_SHORT)) {
                val cached = cacheManager.getData(key, object : TypeToken<List<AttendanceHistoryItem>>() {})
                return@withContext Result.success(cached ?: emptyList())
            }
            val token = getBearerToken()
            if (token.isEmpty()) return@withContext Result.failure(Exception("Session expired. Silakan login kembali"))
            val resp = withConnectionFallback { apiService.getAttendanceHistory(token, studentId, date, subject, page) }
            if (resp.isSuccessful && resp.body()?.success == true) {
                val data = resp.body()?.data ?: emptyList()
                cacheManager.saveData(key, data, CacheManager.TTL_SHORT)
                Result.success(data)
            } else Result.failure(Exception(resp.body()?.message ?: "Gagal memuat riwayat"))
        } catch (e: Exception) {
            Log.e(TAG, "getAttendanceHistory error", e)
            Result.failure(Exception(friendlyNetworkMessage(e)))
        }
    }
    */
}

/**
 * Data class untuk dashboard
 */
data class DashboardData(
    val users: List<UserApi>,
    val schedules: List<ScheduleApi>,
    val teachers: List<TeacherApi>,
    val subjects: List<SubjectApi>
)
