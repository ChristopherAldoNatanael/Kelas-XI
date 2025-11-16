package com.christopheraldoo.aplikasimonitoringkelas.network

import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.google.gson.JsonObject
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    // Authentication endpoints
    @POST("auth/login")
    suspend fun login(@Body loginRequest: LoginRequest): Response<LoginResponse>

    @POST("auth/logout")
    suspend fun logout(@Header("Authorization") token: String): Response<ApiResponse<JsonObject>>

    @GET("auth/me")
    suspend fun getCurrentUser(@Header("Authorization") token: String): Response<ApiResponse<UserApi>>

    // Schedule endpoints - Using lightweight mobile version
    @GET("schedules-mobile")
    suspend fun getSchedules(
        @Header("Authorization") token: String,
        @Query("day") day: String? = null,
        @Query("class_id") classId: Int? = null,
        @Query("teacher_id") teacherId: Int? = null
    ): Response<ApiResponse<List<ScheduleApi>>>

    @POST("schedules")
    suspend fun createSchedule(
        @Header("Authorization") token: String,
        @Body schedule: JsonObject
    ): Response<ApiResponse<ScheduleApi>>

    @PUT("schedules/{id}")
    suspend fun updateSchedule(
        @Header("Authorization") token: String,
        @Path("id") scheduleId: Int,
        @Body schedule: JsonObject
    ): Response<ApiResponse<ScheduleApi>>

    @DELETE("schedules/{id}")
    suspend fun deleteSchedule(
        @Header("Authorization") token: String,
        @Path("id") scheduleId: Int
    ): Response<ApiResponse<JsonObject>>

    // Classroom monitoring endpoints
    @GET("empty-classrooms")
    suspend fun getEmptyClassrooms(
        @Header("Authorization") token: String,
        @Query("day") day: String? = null,
        @Query("period_number") periodNumber: Int? = null
    ): Response<ApiResponse<List<ClassroomApi>>>

    // Subject endpoints
    @GET("subjects")
    suspend fun getSubjects(@Header("Authorization") token: String): Response<ApiResponse<List<SubjectApi>>>

    // Classroom endpoints
    @GET("classrooms")
    suspend fun getClassrooms(@Header("Authorization") token: String): Response<ApiResponse<List<ClassroomApi>>>

    // Teacher endpoints
    @GET("teachers")
    suspend fun getTeachers(@Header("Authorization") token: String): Response<ApiResponse<List<TeacherApi>>>

    // User management endpoints (Admin only)
    @GET("users")
    suspend fun getUsers(@Header("Authorization") token: String): Response<ApiResponse<List<UserApi>>>

    @POST("users")
    suspend fun createUser(
        @Header("Authorization") token: String,
        @Body user: CreateUserRequest
    ): Response<ApiResponse<UserApi>>

    @PUT("users/{id}")
    suspend fun updateUser(
        @Header("Authorization") token: String,
        @Path("id") userId: Int,
        @Body user: UpdateUserRequest
    ): Response<ApiResponse<UserApi>>

    @DELETE("users/{id}")
    suspend fun deleteUser(
        @Header("Authorization") token: String,
        @Path("id") userId: Int
    ): Response<ApiResponse<JsonObject>>

    // Notifications endpoints
    @GET("notifications")
    suspend fun getNotifications(@Header("Authorization") token: String): Response<ApiResponse<List<NotificationApi>>>

    @PUT("notifications/{id}/read")
    suspend fun markNotificationAsRead(
        @Header("Authorization") token: String,
        @Path("id") notificationId: Int
    ): Response<ApiResponse<JsonObject>>

    @GET("notifications/unread-count")
    suspend fun getUnreadNotificationCount(@Header("Authorization") token: String): Response<ApiResponse<JsonObject>>

    // Dropdown endpoints (no auth required)
    @GET("dropdown/subjects")
    suspend fun getDropdownSubjects(): Response<ApiResponse<List<SubjectDropdown>>>

    @GET("dropdown/subjects/{id}/teachers")
    suspend fun getTeachersBySubject(
        @Path("id") subjectId: Int
    ): Response<ApiResponse<TeachersBySubjectResponse>>

    @GET("dropdown/classrooms")
    suspend fun getDropdownClassrooms(): Response<ApiResponse<List<ClassroomDropdown>>>

    @GET("dropdown/classes")
    suspend fun getClasses(
        @Query("major") major: String? = null,
        @Query("level") level: Int? = null
    ): Response<ApiResponse<List<ClassApi>>>

    @GET("dropdown/all")
    suspend fun getAllDropdownData(): Response<ApiResponse<AllDropdownResponse>>

    // Lightweight dashboard summary
    @GET("dashboard/summary")
    suspend fun getDashboardSummary(): Response<ApiResponse<DashboardSummary>>

    // Student dashboard: today schedule
    @GET("jadwal/hari-ini")
    suspend fun getTodaySchedule(
        @Header("Authorization") token: String,
        @Query("class_id") classId: Int? = null
    ): Response<ApiResponse<List<ScheduleApi>>>

    // NEW: Student weekly schedule (auto-detect from user's class_id)
    @GET("siswa/weekly-schedule")
    suspend fun getMyWeeklySchedule(
        @Header("Authorization") token: String
    ): Response<ApiResponse<StudentWeeklyScheduleResponse>>

    // Public endpoints for fallback (no auth required)
    @GET("jadwal/hari-ini-public")
    suspend fun getTodaySchedulePublic(
        @Query("class_id") classId: Int? = null
    ): Response<ApiResponse<List<ScheduleApi>>>

    @GET("dropdown/classes-public")
    suspend fun getClassesPublic(
        @Query("major") major: String? = null,
        @Query("level") level: Int? = null
    ): Response<ApiResponse<List<ClassApi>>>

    // ========== KEHADIRAN (ATTENDANCE) ENDPOINTS ==========
    
    // Submit teacher attendance
    @POST("siswa/kehadiran")
    suspend fun submitKehadiran(
        @Header("Authorization") token: String,
        @Body body: KehadiranSubmitRequest
    ): Response<KehadiranSubmitResponse>

    // Get attendance history
    @GET("siswa/kehadiran/riwayat")
    suspend fun getKehadiranHistory(
        @Header("Authorization") token: String
    ): Response<KehadiranHistoryResponse>

    // Get today's attendance status
    @GET("siswa/kehadiran/today")
    suspend fun getTodayKehadiranStatus(
        @Header("Authorization") token: String
    ): Response<TodayKehadiranResponse>

    @GET("kehadiran/today")
    suspend fun getTodayKehadiran(): Response<TodayKehadiranResponse>

    @POST("kehadiran/submit")
    suspend fun submitKehadiran(@Body request: KehadiranSubmitRequest): Response<ApiResponse<KehadiranItem>>

    @GET("kehadiran/riwayat")
    suspend fun getRiwayatKehadiran(): Response<RiwayatKehadiranResponse>

    // ========== ULTRA LIGHTWEIGHT ENDPOINTS FOR SISWA ==========
    
    // Ultra lightweight jadwal hari ini - CRITICAL FIX untuk prevent server crash
    @GET("siswa/jadwal-hari-ini")
    suspend fun siswaJadwalHariIni(): Response<ApiResponse<List<JadwalHariIni>>>

    // Ultra lightweight riwayat kehadiran with pagination
    @GET("siswa/riwayat-kehadiran")
    suspend fun siswaRiwayatKehadiran(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 10
    ): Response<ApiResponse<PaginatedResponse<List<RiwayatKehadiran>>>>

    // Optimized my schedule with timeout protection
    @GET("siswa/my-schedule")
    suspend fun getMyClassSchedule(
        @Query("page") page: Int = 1
    ): Response<ApiResponse<PaginatedResponse<List<Schedule>>>>

    // Enhanced submit kehadiran
    @POST("siswa/kehadiran")
    suspend fun submitKehadiran(@Body request: KehadiranRequest): Response<ApiResponse<Any>>

    // Enhanced today status
    @GET("siswa/kehadiran/today")
    suspend fun getTodayStatus(): Response<ApiResponse<TodayStatusResponse>>
}
