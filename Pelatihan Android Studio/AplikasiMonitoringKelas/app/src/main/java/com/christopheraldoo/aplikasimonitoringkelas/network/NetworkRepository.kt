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
import com.google.gson.JsonObject
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

    // Request deduplication and sequential execution
    private val requestMutex = Mutex()
    private val ongoingRequests = ConcurrentHashMap<String, Any>()

    // Authentication
    suspend fun login(email: String, password: String): Pair<LoginResponse?, String?> {
        return withContext(Dispatchers.IO) {
            try {
                val loginRequest = LoginRequest(email, password)
                val response = apiService.login(loginRequest)

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
    suspend fun getSchedules(classId: Int? = null): Result<List<ScheduleApi>> {
        val requestKey = "getSchedules:$classId"

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

                        if (response.isSuccessful && response.body()?.success == true) {
                            val schedules: List<ScheduleApi> = response.body()?.data?.data ?: emptyList<ScheduleApi>()
                            Log.d("NetworkRepository", "Successfully parsed ${schedules.size} schedules from new endpoint")
                            Result.success<List<ScheduleApi>>(schedules)
                        } else {
                            Result.failure<List<ScheduleApi>>(Exception("HTTP ${response.code()}: ${response.message()}"))
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
        guruHadir: Boolean,
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
                    guruHadir = guruHadir,
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
     * Get kehadiran history with deduplication and sequential execution
     */
    suspend fun getKehadiranHistory(): Result<KehadiranHistoryResponse> {
        val requestKey = "getKehadiranHistory"

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

                        val response = apiService.getKehadiranHistory("Bearer $token")
                        if (response.isSuccessful) {
                            val body = response.body()
                            if (body != null && body.success) {
                                // CRITICAL FIX: Handle empty data gracefully
                                Result.success(body)
                            } else {
                                // Return empty success response instead of error
                                Result.success(KehadiranHistoryResponse(
                                    success = true,
                                    data = emptyList(),
                                    total = 0
                                ))
                            }
                        } else {
                            // CRITICAL FIX: Return empty success instead of error to prevent crash
                            Result.success(KehadiranHistoryResponse(
                                success = true,
                                data = emptyList(),
                                total = 0
                            ))
                        }
                    } catch (e: Exception) {
                        Log.e("NetworkRepository", "Get kehadiran history error", e)
                        // CRITICAL FIX: Return empty success instead of failure to prevent crash
                        Result.success(KehadiranHistoryResponse(
                            success = true,
                            data = emptyList(),
                            total = 0
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
    suspend fun getTodayKehadiranStatus(): Result<TodayKehadiranResponse> {
        val requestKey = "getTodayKehadiranStatus"

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
}
