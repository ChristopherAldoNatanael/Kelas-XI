package com.christopheraldoo.aplikasimonitoringkelas.network

import android.content.Context
import android.util.Log
import com.christopheraldoo.aplikasimonitoringkelas.data.ApiResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomApi
import com.christopheraldoo.aplikasimonitoringkelas.data.CreateUserRequest
import com.christopheraldoo.aplikasimonitoringkelas.data.LoginRequest
import com.christopheraldoo.aplikasimonitoringkelas.data.LoginResponse
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkUtils
import com.christopheraldoo.aplikasimonitoringkelas.data.NotificationApi
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TeacherApi
import com.christopheraldoo.aplikasimonitoringkelas.data.UserApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TeachersBySubjectResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectsDropdownResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomsDropdownResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.AllDropdownResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.SubjectDropdown
import com.christopheraldoo.aplikasimonitoringkelas.data.TeacherDropdown
import com.christopheraldoo.aplikasimonitoringkelas.data.ClassroomDropdown
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranSubmitRequest
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranSubmitResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranHistoryResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.TodayKehadiranResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranItem
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import com.christopheraldoo.aplikasimonitoringkelas.utils.TokenManager
import com.christopheraldoo.aplikasimonitoringkelas.cache.CacheManager
import com.google.gson.JsonObject
import kotlinx.coroutines.delay
import com.google.gson.reflect.TypeToken
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.util.concurrent.ConcurrentHashMap

class NetworkRepository(private val context: Context) {

    private fun getApi() = RetrofitClient.getAuthenticatedInstance(context)

    private val apiService by lazy { RetrofitClient.createApiService(context) }
    private val cacheManager by lazy { CacheManager(context) }

    // Request deduplication and sequential execution
    private val requestMutex = Mutex()
    private val ongoingRequests = ConcurrentHashMap<String, Any>()

    // Authentication
    suspend fun login(email: String, password: String): Pair<LoginResponse?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.login(email, password)

                if (response.isSuccessful) {
                    Pair(response.body(), null)
                } else {
                    Pair(null, "Login failed: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Login error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun logout(token: String): Pair<Boolean, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.logout(token)

                if (response.isSuccessful) {
                    Pair(true, null)
                } else {
                    Pair(false, "Logout failed: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Logout error", e)
                Pair(false, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun getCurrentUser(token: String): Pair<UserApi?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getCurrentUser(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data, null)
                } else {
                    Pair(null, "Failed to get user data: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get current user error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Schedule operations - Use the optimized mobile endpoint
    suspend fun getSchedules(token: String, day: String? = null, classId: Int? = null, teacherId: Int? = null): Pair<List<ScheduleApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                Log.d("NetworkRepository", "Getting schedules with params: day=$day, classId=$classId, teacherId=$teacherId")
                // Use the mobile-optimized endpoint that should return less data
                val response = apiService.getSchedules(token, day, classId, teacherId)

                Log.d("NetworkRepository", "Response code: ${response.code()}")
                if (response.isSuccessful && response.body()?.success == true) {
                    val schedules = response.body()?.data ?: emptyList()
                    Log.d("NetworkRepository", "Successfully parsed ${schedules.size} schedules")
                    Pair(schedules, null)
                } else {
                    val errorMsg = "Failed to get schedules: ${response.message()}"
                    Log.e("NetworkRepository", errorMsg)
                    Pair(null, errorMsg)
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get schedules error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    /**
     * Overload for ViewModel - returns Result type with deduplication and sequential calls
     */
    suspend fun getSchedules(forceRefresh: Boolean = false, classId: Int? = null): Result<List<ScheduleApi>> {
        val requestKey = "getSchedules:$classId"

        // If force refresh, cancel any ongoing request
        if (forceRefresh) {
            ongoingRequests.remove(requestKey)
        }

        // Check if request is already ongoing
        ongoingRequests[requestKey]?.let { deferred ->
            return try {
                @Suppress("UNCHECKED_CAST")
                (deferred as kotlinx.coroutines.Deferred<Result<List<ScheduleApi>>>).await()
            } catch (e: Exception) {
                ongoingRequests.remove(requestKey)
                Result.failure(e)
            }
        }

        // Create new request with mutex for sequential execution
        val deferred = coroutineScope {
            requestMutex.withLock {
                async(Dispatchers.IO) {
                    try {
                        val token = SessionManager(context).getAuthToken()
                        if (token.isNullOrEmpty()) {
                            return@async Result.failure<List<ScheduleApi>>(Exception("Token tidak ditemukan"))
                        }

                        // Menggunakan endpoint baru yang lebih spesifik untuk siswa
                        val response = apiService.getMyWeeklySchedule("Bearer $token")

                        Log.d("NetworkRepository", "API Response Code: ${response.code()}")
                        Log.d("NetworkRepository", "API Response Success: ${response.isSuccessful}")
                        Log.d("NetworkRepository", "API Body Success: ${response.body()?.success}")
                        Log.d("NetworkRepository", "API Body Data: ${response.body()?.data}")

                        if (response.isSuccessful && response.body()?.success == true) {
                            // Response is now directly List<ScheduleApi>
                            val schedules: List<ScheduleApi> = response.body()?.data ?: emptyList()
                            Log.d("NetworkRepository", "Successfully parsed ${schedules.size} schedules from new endpoint")
                            
                            // Log first few schedules for debugging
                            schedules.take(3).forEach { schedule ->
                                Log.d("NetworkRepository", "Schedule: ${schedule.className} - ${schedule.subjectName} (${schedule.dayOfWeek})")
                            }
                            
                            Result.success<List<ScheduleApi>>(schedules)
                        } else {
                            val errorMsg = "HTTP ${response.code()}: ${response.message()} | Body: ${response.errorBody()?.string()}"
                            Log.e("NetworkRepository", errorMsg)
                            Result.failure<List<ScheduleApi>>(Exception(errorMsg))
                        }
                    } catch (e: Exception) {
                        Log.e("NetworkRepository", "Get schedules error", e)
                        Result.failure<List<ScheduleApi>>(e)
                    } finally {
                        ongoingRequests.remove(requestKey)
                    }
                }
            }
        }

        ongoingRequests[requestKey] = deferred
        return deferred.await()
    }

    /**
     * Get weekly schedule with teacher attendance status
     * Uses the stable endpoint and calculates today locally
     * Includes retry mechanism for handling EOFException/parsing errors
     */
    suspend fun getSchedulesWithAttendance(forceRefresh: Boolean = false): Result<Pair<List<ScheduleApi>, String?>> {
        val requestKey = "getSchedulesWithAttendance"
        val maxRetries = 3

        // If force refresh, cancel any ongoing request
        if (forceRefresh) {
            ongoingRequests.remove(requestKey)
        }

        // Check if request is already ongoing
        ongoingRequests[requestKey]?.let { deferred ->
            return try {
                @Suppress("UNCHECKED_CAST")
                (deferred as kotlinx.coroutines.Deferred<Result<Pair<List<ScheduleApi>, String?>>>).await()
            } catch (e: Exception) {
                ongoingRequests.remove(requestKey)
                Result.failure(e)
            }
        }

        // Create new request with mutex for sequential execution
        val deferred = coroutineScope {
            requestMutex.withLock {
                async(Dispatchers.IO) {
                    var lastException: Exception? = null
                    
                    for (attempt in 1..maxRetries) {
                        try {
                            val token = SessionManager(context).getAuthToken()
                            if (token.isNullOrEmpty()) {
                                return@async Result.failure<Pair<List<ScheduleApi>, String?>>(Exception("Token tidak ditemukan"))
                            }

                            // Calculate today's day name in Indonesian
                            val dayMap = mapOf(
                                java.util.Calendar.MONDAY to "Senin",
                                java.util.Calendar.TUESDAY to "Selasa",
                                java.util.Calendar.WEDNESDAY to "Rabu",
                                java.util.Calendar.THURSDAY to "Kamis",
                                java.util.Calendar.FRIDAY to "Jumat",
                                java.util.Calendar.SATURDAY to "Sabtu",
                                java.util.Calendar.SUNDAY to "Minggu"
                            )
                            val todayDay = dayMap[java.util.Calendar.getInstance().get(java.util.Calendar.DAY_OF_WEEK)] ?: "Senin"

                            // Use the endpoint WITH attendance status for JadwalScreen
                            Log.d("NetworkRepository", "Attempt $attempt/$maxRetries: Using endpoint siswa/weekly-schedule-attendance")
                            val response = apiService.getWeeklyScheduleWithAttendance("Bearer $token")

                            Log.d("NetworkRepository", "Schedule Response Code: ${response.code()}")

                            if (response.isSuccessful && response.body()?.success == true) {
                                val schedules = response.body()?.data ?: emptyList()
                                val serverToday = response.body()?.today ?: todayDay
                                Log.d("NetworkRepository", "Successfully parsed ${schedules.size} schedules with attendance, today=$serverToday")
                                
                                return@async Result.success(Pair(schedules, serverToday))
                            } else {
                                val errorMsg = "HTTP ${response.code()}: ${response.message()}"
                                Log.e("NetworkRepository", errorMsg)
                                lastException = Exception(errorMsg)
                            }
                        } catch (e: java.io.EOFException) {
                            Log.w("NetworkRepository", "EOFException on attempt $attempt/$maxRetries: ${e.message}")
                            lastException = Exception("Data tidak lengkap, mencoba lagi...", e)
                            if (attempt < maxRetries) {
                                kotlinx.coroutines.delay(1000L * attempt)
                            }
                        } catch (e: com.google.gson.JsonSyntaxException) {
                            Log.w("NetworkRepository", "JSON parsing error on attempt $attempt/$maxRetries: ${e.message}")
                            lastException = Exception("Format data tidak valid", e)
                            if (attempt < maxRetries) {
                                kotlinx.coroutines.delay(1000L * attempt)
                            }
                        } catch (e: java.net.SocketTimeoutException) {
                            Log.w("NetworkRepository", "Timeout on attempt $attempt/$maxRetries: ${e.message}")
                            lastException = Exception("Koneksi timeout", e)
                            if (attempt < maxRetries) {
                                kotlinx.coroutines.delay(500L * attempt)
                            }
                        } catch (e: Exception) {
                            Log.e("NetworkRepository", "Get schedules error on attempt $attempt/$maxRetries", e)
                            lastException = e
                            // For other exceptions, don't retry
                            break
                        }
                    }
                    
                    ongoingRequests.remove(requestKey)
                    Result.failure(lastException ?: Exception("Gagal memuat jadwal setelah $maxRetries percobaan"))
                }
            }
        }

        ongoingRequests[requestKey] = deferred
        return deferred.await()
    }

    suspend fun createSchedule(token: String, scheduleJson: JsonObject): Pair<ScheduleApi?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.createSchedule(token, scheduleJson)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data, null)
                } else {
                    Pair(null, "Failed to create schedule: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Create schedule error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun updateSchedule(token: String, scheduleId: Int, scheduleJson: JsonObject): Pair<ScheduleApi?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.updateSchedule(token, scheduleId, scheduleJson)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data, null)
                } else {
                    Pair(null, "Failed to update schedule: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Update schedule error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun deleteSchedule(token: String, scheduleId: Int): Pair<Boolean, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.deleteSchedule(token, scheduleId)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(true, null)
                } else {
                    Pair(false, "Failed to delete schedule: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Delete schedule error", e)
                Pair(false, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Classroom operations
    suspend fun getEmptyClassrooms(token: String, day: String? = null, period: Int? = null): Pair<List<ClassroomApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getEmptyClassrooms(token, day, period)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get empty classrooms: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get empty classrooms error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun getClassrooms(token: String): Pair<List<ClassroomApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getClassrooms(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get classrooms: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get classrooms error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Subject operations
    suspend fun getSubjects(token: String): Pair<List<SubjectApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getSubjects(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get subjects: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get subjects error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Teacher operations
    suspend fun getTeachers(token: String): Pair<List<TeacherApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getTeachers(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get teachers: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get teachers error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // User management (Admin only)
    suspend fun getUsers(token: String): Pair<List<UserApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getUsers(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get users: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get users error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun createUser(token: String, nama: String, email: String, password: String, role: String, classId: Int?, phone: String?, address: String?): Pair<UserApi?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val createUserRequest = CreateUserRequest(
                    nama = nama,
                    email = email,
                    password = password,
                    role = role,
                    classId = classId
                )
                val response = apiService.createUser(token, createUserRequest)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data, null)
                } else {
                    Pair(null, "Failed to create user: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Create user error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Notifications
    suspend fun getNotifications(token: String): Pair<List<NotificationApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getNotifications(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(response.body()?.data ?: emptyList(), null)
                } else {
                    Pair(null, "Failed to get notifications: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get notifications error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun markNotificationAsRead(token: String, notificationId: Int): Pair<Boolean, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.markNotificationAsRead(token, notificationId)

                if (response.isSuccessful && response.body()?.success == true) {
                    Pair(true, null)
                } else {
                    Pair(false, "Failed to mark notification as read: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Mark notification read error", e)
                Pair(false, "Network error: ${e.localizedMessage}")
            }
        }
    }

    suspend fun getUnreadNotificationCount(token: String): Pair<Int?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getUnreadNotificationCount(token)

                if (response.isSuccessful && response.body()?.success == true) {
                    // Extract count from response data (assuming it's in the data object)
                    Pair(0, null) // Placeholder - perlu disesuaikan dengan struktur response
                } else {
                    Pair(null, "Failed to get unread count: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get unread count error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // New dropdown methods that don't require authentication
    suspend fun getDropdownTeachersBySubject(subjectId: Int): Pair<List<TeacherApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getTeachersBySubject(subjectId)

                if (response.isSuccessful && response.body()?.success == true) {
                    val data = response.body()?.data?.data // unwrap TeachersBySubjectResponse.data (list)
                    val teachers: List<TeacherApi> = data?.map { td: TeacherDropdown ->
                        TeacherApi(
                            id = td.id,
                            userId = td.userId,
                            name = td.name,
                            email = "",
                            nip = null,
                            phone = null,
                            subjectId = null,
                            subjectName = null
                        )
                    } ?: emptyList()
                    Pair(teachers, null)
                } else {
                    Pair(null, "Failed to load teachers for subject: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get teachers by subject error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Get all subjects for dropdown
    suspend fun getDropdownSubjects(): Pair<List<SubjectApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getDropdownSubjects()

                if (response.isSuccessful && response.body()?.success == true) {
                    val subjectDropdowns = response.body()?.data ?: emptyList()                    // Convert SubjectDropdown to SubjectApi
                    val subjects: List<SubjectApi> = subjectDropdowns.map { sd ->
                        SubjectApi(
                            id = sd.id,
                            name = sd.name,
                            code = sd.code,
                            description = null,
                            createdAt = null
                        )
                    }
                    Pair(subjects, null)
                } else {
                    Pair(null, "Failed to get subjects: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get subjects error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Get all classrooms for dropdown
    suspend fun getDropdownClassrooms(): Pair<List<ClassroomApi>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getDropdownClassrooms()

                if (response.isSuccessful && response.body()?.success == true) {
                    val classroomDropdowns = response.body()?.data ?: emptyList()                    // Convert ClassroomDropdown to ClassroomApi
                    val classrooms: List<ClassroomApi> = classroomDropdowns.map { cd ->
                        ClassroomApi(
                            id = cd.id,
                            name = cd.name,
                            grade = cd.grade,
                            major = null,
                            roomNumber = null,
                            capacity = null,
                            studentCount = 0
                        )
                    }
                    Pair(classrooms, null)
                } else {
                    Pair(null, "Failed to get classrooms: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get classrooms error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // Get all dropdown data in one call
    suspend fun getAllDropdownData(): Pair<ApiResponse<AllDropdownResponse>?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.getAllDropdownData()

                if (response.isSuccessful) {
                    Pair(response.body(), null)
                } else {
                    Pair(null, "Failed to get dropdown data: ${response.message()}")
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Get dropdown data error", e)
                Pair(null, "Network error: ${e.localizedMessage}")
            }
        }
    }

    // ========== KEHADIRAN (ATTENDANCE) METHODS ==========

    /**
     * Submit teacher attendance
     */
    suspend fun submitKehadiran(
        scheduleId: Int,
        tanggal: String,
        status: String,
        catatan: String?
    ): Result<KehadiranSubmitResponse> {
        return withContext(Dispatchers.IO) {
            try {
                val token = SessionManager(context).getAuthToken()
                if (token.isNullOrEmpty()) {
                    return@withContext Result.failure(Exception("Token tidak ditemukan"))
                }

                val request = KehadiranSubmitRequest(
                    scheduleId = scheduleId,
                    tanggal = tanggal,
                    status = status,
                    catatan = catatan ?: ""
                )

                val response = apiService.submitKehadiran("Bearer $token", request)
                if (response.isSuccessful) {
                    val body = response.body()
                    if (body != null) {
                        Result.success(body)
                    } else {
                        Result.failure(Exception("Response body kosong"))
                    }
                } else {
                    Result.failure(Exception("HTTP ${response.code()}: ${response.message()}"))
                }
            } catch (e: Exception) {
                Log.e("NetworkRepository", "Submit kehadiran error", e)
                Result.failure(e)
            }
        }
    }

    /**
     * Get kehadiran history with pagination, caching, deduplication and sequential execution
     */
    suspend fun getKehadiranHistory(forceRefresh: Boolean = false, page: Int = 1, limit: Int = 20): Result<KehadiranHistoryResponse> {
        val cacheKey = "kehadiran_history_page_$page"

        // Check cache first (only for first page to avoid complexity)
        if (!forceRefresh && page == 1) {
            val cachedData = cacheManager.getData(
                cacheKey,
                object : TypeToken<KehadiranHistoryResponse>() {},
                CacheManager.TTL_SHORT // 5 minutes for attendance data
            )
            if (cachedData != null) {
                Log.d("NetworkRepository", "Returning cached kehadiran history")
                return Result.success(cachedData)
            }
        }

        val requestKey = "getKehadiranHistory:$page:$limit"

        // If force refresh, cancel any ongoing request
        if (forceRefresh) {
            ongoingRequests.remove(requestKey)
        }

        // Check if request is already ongoing
        ongoingRequests[requestKey]?.let { deferred ->
            return try {
                @Suppress("UNCHECKED_CAST")
                (deferred as kotlinx.coroutines.Deferred<Result<KehadiranHistoryResponse>>).await()
            } catch (e: Exception) {
                ongoingRequests.remove(requestKey)
                Result.failure(e)
            }
        }

        // Create new request with mutex for sequential execution
        val deferred = coroutineScope {
            requestMutex.withLock {
                async(Dispatchers.IO) {
                    try {
                        val token = SessionManager(context).getAuthToken()
                        if (token.isNullOrEmpty()) {
                            return@async Result.failure<KehadiranHistoryResponse>(Exception("Token tidak ditemukan"))
                        }

                        val response = apiService.getKehadiranHistory("Bearer $token", page, limit)
                        if (response.isSuccessful) {
                            val body = response.body()
                            if (body != null && body.success) {
                                // Cache the response for first page
                                if (page == 1) {
                                    cacheManager.saveData(cacheKey, body, CacheManager.TTL_SHORT)
                                }
                                Result.success(body)
                            } else {
                                // Return empty success response instead of error
                                Result.success(KehadiranHistoryResponse(
                                    success = true,
                                    data = emptyList(),
                                    total = 0,
                                    pagination = null
                                ))
                            }
                        } else {
                            // CRITICAL FIX: Return empty success instead of error to prevent crash
                            Result.success(KehadiranHistoryResponse(
                                success = true,
                                data = emptyList(),
                                total = 0,
                                pagination = null
                            ))
                        }
                    } catch (e: Exception) {
                        Log.e("NetworkRepository", "Get kehadiran history error", e)
                        // CRITICAL FIX: Return empty success instead of failure to prevent crash
                        Result.success(KehadiranHistoryResponse(
                            success = true,
                            data = emptyList(),
                            total = 0,
                            pagination = null
                        ))
                    } finally {
                        ongoingRequests.remove(requestKey)
                    }
                }
            }
        }

        ongoingRequests[requestKey] = deferred
        return deferred.await()
    }

    /**
     * Get today's kehadiran status - SIMPLIFIED dengan error handling lebih baik
     */
    suspend fun getTodayKehadiranStatus(forceRefresh: Boolean = false): Result<TodayKehadiranResponse> {
        val requestKey = "getTodayKehadiranStatus"

        // If force refresh, cancel any ongoing request
        if (forceRefresh) {
            ongoingRequests.remove(requestKey)
        }

        // Check if request is already ongoing
        ongoingRequests[requestKey]?.let { deferred ->
            return try {
                @Suppress("UNCHECKED_CAST")
                (deferred as kotlinx.coroutines.Deferred<Result<TodayKehadiranResponse>>).await()
            } catch (e: Exception) {
                ongoingRequests.remove(requestKey)
                Result.failure(e)
            }
        }

        // Create new request with mutex for sequential execution
        val deferred = coroutineScope {
            requestMutex.withLock {
                async(Dispatchers.IO) {
                    try {
                        val token = SessionManager(context).getAuthToken()
                        if (token.isNullOrEmpty()) {
                            return@async Result.failure<TodayKehadiranResponse>(Exception("Token tidak ditemukan"))
                        }

                        val response = apiService.getTodayKehadiranStatus("Bearer $token")
                        if (response.isSuccessful) {
                            val body = response.body()
                            if (body != null && body.success) {
                                Result.success(body)
                            } else {
                                // CRITICAL FIX: Return empty success instead of error
                                val today = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault()).format(java.util.Date())
                                Result.success(TodayKehadiranResponse(
                                    success = true,
                                    tanggal = today,
                                    dayOfWeek = java.text.SimpleDateFormat("EEEE", java.util.Locale.getDefault()).format(java.util.Date()).lowercase(),
                                    schedules = emptyList()
                                ))
                            }
                        } else {
                            // CRITICAL FIX: Return empty success instead of error to prevent crash
                            val today = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault()).format(java.util.Date())
                            Result.success(TodayKehadiranResponse(
                                success = true,
                                tanggal = today,
                                dayOfWeek = java.text.SimpleDateFormat("EEEE", java.util.Locale.getDefault()).format(java.util.Date()).lowercase(),
                                schedules = emptyList()
                            ))
                        }
                    } catch (e: Exception) {
                        Log.e("NetworkRepository", "Get today kehadiran status error", e)
                        // CRITICAL FIX: Return empty success instead of failure to prevent crash
                        val today = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.getDefault()).format(java.util.Date())
                        Result.success(TodayKehadiranResponse(
                            success = true,
                            tanggal = today,
                            dayOfWeek = java.text.SimpleDateFormat("EEEE", java.util.Locale.getDefault()).format(java.util.Date()).lowercase(),
                            schedules = emptyList()
                        ))
                    } finally {
                        ongoingRequests.remove(requestKey)
                    }
                }
            }
        }

        ongoingRequests[requestKey] = deferred
        return deferred.await()
    }

    // Get today's attendance status
    suspend fun getTodayKehadiran(): Result<TodayKehadiranResponse> = withContext(Dispatchers.IO) {
        return@withContext try {
            val token = TokenManager.getToken(context)
            if (token.isNullOrEmpty()) {
                return@withContext Result.failure(Exception("Token tidak ditemukan"))
            }

            val response = getApi().getTodayKehadiran()
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Gagal memuat jadwal hari ini"))
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getTodayKehadiran error: ${e.message}", e)
            Result.failure(e)
        }
    }

    // Submit kehadiran
    suspend fun submitKehadiran(request: KehadiranSubmitRequest): Result<ApiResponse<KehadiranItem>> = withContext(Dispatchers.IO) {
        return@withContext try {
            val token = TokenManager.getToken(context)
            if (token.isNullOrEmpty()) {
                return@withContext Result.failure(Exception("Token tidak ditemukan"))
            }

            val response = getApi().submitKehadiran(request)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Gagal menyimpan kehadiran"))
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "submitKehadiran error: ${e.message}", e)
            Result.failure(e)
        }
    }

    // Get riwayat kehadiran
    suspend fun getRiwayatKehadiran(): Result<KehadiranHistoryResponse> = withContext(Dispatchers.IO) {
        return@withContext try {
            val token = TokenManager.getToken(context)
            if (token.isNullOrEmpty()) {
                return@withContext Result.failure(Exception("Token tidak ditemukan"))
            }

            val response = getApi().getKehadiranHistory("Bearer $token")
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Gagal memuat riwayat"))
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getRiwayatKehadiran error: ${e.message}", e)
            Result.failure(e)
        }
    }

    // ===============================================
    // KURIKULUM API FUNCTIONS
    // ===============================================
    
    private fun getAuthToken(): String {
        val token = TokenManager.getToken(context) ?: ""
        return if (token.startsWith("Bearer ")) token else "Bearer $token"
    }

    // Get Kurikulum Dashboard Overview
    suspend fun getKurikulumDashboard(
        day: String? = null,
        classId: Int? = null,
        subjectId: Int? = null,
        weekOffset: Int? = null,
        forceRefresh: Boolean = false
    ): com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumDashboardResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKurikulumDashboard(
                getAuthToken(), 
                day, 
                classId, 
                subjectId,
                weekOffset,
                if (forceRefresh) true else null
            )
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumDashboardResponse(
                    success = false,
                    message = "Gagal memuat dashboard: ${response.message()}",
                    date = "",
                    day = "",
                    stats = com.christopheraldoo.aplikasimonitoringkelas.data.DashboardStats(),
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKurikulumDashboard error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumDashboardResponse(
                success = false,
                message = "Error: ${e.message}",
                date = "",
                day = "",
                stats = com.christopheraldoo.aplikasimonitoringkelas.data.DashboardStats(),
                data = emptyList()
            )
        }
    }

    // Get Kurikulum Class Management
    suspend fun getKurikulumClasses(
        status: String? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.ClassManagementResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKurikulumClasses(getAuthToken(), status)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.ClassManagementResponse(
                    success = false,
                    message = "Gagal memuat data kelas: ${response.message()}",
                    date = "",
                    day = "",
                    currentTime = "",
                    statusCounts = com.christopheraldoo.aplikasimonitoringkelas.data.StatusCounts(),
                    alertClasses = emptyList(),
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKurikulumClasses error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.ClassManagementResponse(
                success = false,
                message = "Error: ${e.message}",
                date = "",
                day = "",
                currentTime = "",
                statusCounts = com.christopheraldoo.aplikasimonitoringkelas.data.StatusCounts(),
                alertClasses = emptyList(),
                data = emptyList()
            )
        }
    }

    // Get Available Substitute Teachers
    suspend fun getAvailableSubstitutes(
        period: Int,
        subjectId: Int? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.SubstituteTeachersResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getAvailableSubstitutes(getAuthToken(), period, subjectId)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.SubstituteTeachersResponse(
                    success = false,
                    message = "Gagal memuat guru pengganti: ${response.message()}",
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getAvailableSubstitutes error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.SubstituteTeachersResponse(
                success = false,
                message = "Error: ${e.message}",
                data = emptyList()
            )
        }
    }

    // Assign Substitute Teacher
    suspend fun assignSubstitute(
        request: com.christopheraldoo.aplikasimonitoringkelas.data.AssignSubstituteRequest
    ): com.christopheraldoo.aplikasimonitoringkelas.data.AssignSubstituteResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().assignSubstitute(getAuthToken(), request)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.AssignSubstituteResponse(
                    success = false,
                    message = "Gagal menugaskan guru pengganti: ${response.message()}"
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "assignSubstitute error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.AssignSubstituteResponse(
                success = false,
                message = "Error: ${e.message}"
            )
        }
    }

    // Get Kurikulum Attendance History
    suspend fun getKurikulumHistory(
        page: Int = 1,
        dateFrom: String? = null,
        dateTo: String? = null,
        teacherId: Int? = null,
        classId: Int? = null,
        status: String? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumHistoryResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKurikulumHistory(
                getAuthToken(), page, 20, dateFrom, dateTo, teacherId, classId, status
            )
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumHistoryResponse(
                    success = false,
                    message = "Gagal memuat riwayat: ${response.message()}",
                    data = emptyList(),
                    pagination = com.christopheraldoo.aplikasimonitoringkelas.data.PaginationInfo(1, 20, 0, 1)
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKurikulumHistory error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.KurikulumHistoryResponse(
                success = false,
                message = "Error: ${e.message}",
                data = emptyList(),
                pagination = com.christopheraldoo.aplikasimonitoringkelas.data.PaginationInfo(1, 20, 0, 1)
            )
        }
    }

    // Get Kurikulum Statistics
    suspend fun getKurikulumStatistics(
        month: Int? = null,
        year: Int? = null,
        teacherId: Int? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.StatisticsResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKurikulumStatistics(getAuthToken(), month, year, teacherId)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.StatisticsResponse(
                    success = false,
                    message = "Gagal memuat statistik: ${response.message()}",
                    statistics = com.christopheraldoo.aplikasimonitoringkelas.data.MonthlyStats(
                        0, 0, "", 0, 0, 0, 0, 0,
                        com.christopheraldoo.aplikasimonitoringkelas.data.PercentageStats(0f, 0f, 0f, 0f)
                    )
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKurikulumStatistics error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.StatisticsResponse(
                success = false,
                message = "Error: ${e.message}",
                statistics = com.christopheraldoo.aplikasimonitoringkelas.data.MonthlyStats(
                    0, 0, "", 0, 0, 0, 0, 0,
                    com.christopheraldoo.aplikasimonitoringkelas.data.PercentageStats(0f, 0f, 0f, 0f)
                )
            )
        }
    }

    // Export Attendance Data
    suspend fun exportAttendance(
        dateFrom: String? = null,
        dateTo: String? = null,
        teacherId: Int? = null,
        classId: Int? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.ExportResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().exportAttendance(getAuthToken(), dateFrom, dateTo, teacherId, classId)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.ExportResponse(
                    success = false,
                    message = "Gagal export data: ${response.message()}",
                    totalRecords = 0,
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "exportAttendance error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.ExportResponse(
                success = false,
                message = "Error: ${e.message}",
                totalRecords = 0,
                data = emptyList()
            )
        }
    }

    // Get Filter Classes
    suspend fun getFilterClasses(): com.christopheraldoo.aplikasimonitoringkelas.data.FilterClassesResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getFilterClasses(getAuthToken())
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.FilterClassesResponse(
                    success = false,
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getFilterClasses error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.FilterClassesResponse(
                success = false,
                data = emptyList()
            )
        }
    }

    // Get Filter Teachers
    suspend fun getFilterTeachers(): com.christopheraldoo.aplikasimonitoringkelas.data.FilterTeachersResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getFilterTeachers(getAuthToken())
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.FilterTeachersResponse(
                    success = false,
                    data = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getFilterTeachers error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.FilterTeachersResponse(
                success = false,
                data = emptyList()
            )
        }
    }

    // Get Class Students
    suspend fun getClassStudents(
        classId: Int
    ): com.christopheraldoo.aplikasimonitoringkelas.data.ClassStudentsResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getClassStudents(getAuthToken(), classId)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.ClassStudentsResponse(
                    success = false,
                    message = "Gagal memuat data siswa: ${response.message()}",
                    classInfo = com.christopheraldoo.aplikasimonitoringkelas.data.ClassInfo(0, "Unknown"),
                    totalStudents = 0,
                    students = emptyList()
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getClassStudents error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.ClassStudentsResponse(
                success = false,
                message = "Error: ${e.message}",
                classInfo = com.christopheraldoo.aplikasimonitoringkelas.data.ClassInfo(0, "Unknown"),
                totalStudents = 0,
                students = emptyList()
            )
        }
    }

    // Get Pending Attendances with retry logic for JSON parsing errors
    suspend fun getPendingAttendances(
        date: String? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceResponse = withContext(Dispatchers.IO) {
        var lastException: Exception? = null
        val maxRetries = 3
        
        for (attempt in 1..maxRetries) {
            try {
                val response = getApi().getPendingAttendances(getAuthToken(), date)
                if (response.isSuccessful && response.body() != null) {
                    return@withContext response.body()!!
                } else {
                    return@withContext com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceResponse(
                        success = false,
                        message = "Gagal memuat data pending: ${response.message()}",
                        data = com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceData(
                            date = "",
                            day = "",
                            totalPending = 0,
                            groupedByClass = emptyList(),
                            allPending = emptyList()
                        )
                    )
                }
            } catch (e: Exception) {
                lastException = e
                Log.w("NetworkRepo", "getPendingAttendances attempt $attempt failed: ${e.message}")
                
                // Check if it's a JSON parsing error - retry with delay
                val isJsonError = e.message?.contains("Expected") == true || 
                                  e.message?.contains("End of input") == true ||
                                  e.message?.contains("JsonSyntax") == true
                
                if (isJsonError && attempt < maxRetries) {
                    Log.d("NetworkRepo", "JSON parsing error, retrying in ${attempt * 1000}ms...")
                    delay(attempt * 1000L) // Exponential backoff
                    continue
                }
                break
            }
        }
        
        Log.e("NetworkRepo", "getPendingAttendances failed after $maxRetries attempts", lastException)
        com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceResponse(
            success = false,
            message = "Error setelah $maxRetries percobaan: ${lastException?.message}",
            data = com.christopheraldoo.aplikasimonitoringkelas.data.PendingAttendanceData(
                date = "",
                day = "",
                totalPending = 0,
                groupedByClass = emptyList(),
                allPending = emptyList()
            )
        )
    }

    // Confirm Single Attendance
    suspend fun confirmAttendance(
        request: com.christopheraldoo.aplikasimonitoringkelas.data.ConfirmAttendanceRequest
    ): com.christopheraldoo.aplikasimonitoringkelas.data.ConfirmAttendanceResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().confirmAttendance(getAuthToken(), request)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.ConfirmAttendanceResponse(
                    success = false,
                    message = "Gagal konfirmasi kehadiran: ${response.message()}"
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "confirmAttendance error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.ConfirmAttendanceResponse(
                success = false,
                message = "Error: ${e.message}"
            )
        }
    }

    // Bulk Confirm Attendances
    suspend fun bulkConfirmAttendance(
        request: com.christopheraldoo.aplikasimonitoringkelas.data.BulkConfirmRequest
    ): com.christopheraldoo.aplikasimonitoringkelas.data.BulkConfirmResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().bulkConfirmAttendance(getAuthToken(), request)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.BulkConfirmResponse(
                    success = false,
                    message = "Gagal konfirmasi kehadiran: ${response.message()}"
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "bulkConfirmAttendance error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.BulkConfirmResponse(
                success = false,
                message = "Error: ${e.message}"
            )
        }
    }

    // ===============================================
    // KEPALA SEKOLAH API FUNCTIONS
    // ===============================================

    // Get Kepala Sekolah Dashboard Overview
    suspend fun getKepsekDashboard(
        weekOffset: Int? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.KepalaSekolahDashboardResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKepsekDashboard(getAuthToken(), weekOffset)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.KepalaSekolahDashboardResponse(
                    success = false,
                    message = "Gagal memuat dashboard: ${response.message()}",
                    data = null
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKepsekDashboard error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.KepalaSekolahDashboardResponse(
                success = false,
                message = "Error: ${e.message}",
                data = null
            )
        }
    }

    // Get Kepala Sekolah Attendance List
    suspend fun getKepsekAttendances(
        status: String? = null,
        weekOffset: Int? = null,
        className: String? = null,
        teacherId: Int? = null,
        date: String? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.KepsekAttendanceListResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKepsekAttendances(getAuthToken(), status, weekOffset, className, teacherId, date)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.KepsekAttendanceListResponse(
                    success = false,
                    message = "Gagal memuat data kehadiran: ${response.message()}",
                    data = null
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKepsekAttendances error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.KepsekAttendanceListResponse(
                success = false,
                message = "Error: ${e.message}",
                data = null
            )
        }
    }

    // Get Kepala Sekolah Teacher Performance
    suspend fun getKepsekTeacherPerformance(
        weekOffset: Int? = null,
        sortBy: String? = null
    ): com.christopheraldoo.aplikasimonitoringkelas.data.TeacherPerformanceResponse = withContext(Dispatchers.IO) {
        try {
            val response = getApi().getKepsekTeacherPerformance(getAuthToken(), weekOffset, sortBy)
            if (response.isSuccessful && response.body() != null) {
                response.body()!!
            } else {
                com.christopheraldoo.aplikasimonitoringkelas.data.TeacherPerformanceResponse(
                    success = false,
                    message = "Gagal memuat performa guru: ${response.message()}",
                    data = null
                )
            }
        } catch (e: Exception) {
            Log.e("NetworkRepo", "getKepsekTeacherPerformance error: ${e.message}", e)
            com.christopheraldoo.aplikasimonitoringkelas.data.TeacherPerformanceResponse(
                success = false,
                message = "Error: ${e.message}",
                data = null
            )
        }
    }
}
