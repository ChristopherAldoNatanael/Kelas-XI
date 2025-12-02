package com.christopheraldoo.aplikasimonitoringkelas.network

import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.google.gson.JsonObject
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    // Authentication endpoints
    @FormUrlEncoded
    @POST("auth/login")
    suspend fun login(
        @Field("email") email: String,
        @Field("password") password: String
    ): Response<LoginResponse>

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

    // NEW: Student weekly schedule - FIXED endpoint (no middleware issues)
    @GET("jadwal-siswa")
    suspend fun getMyWeeklySchedule(
        @Header("Authorization") token: String
    ): Response<ApiResponse<List<ScheduleApi>>>

    // NEW: Weekly schedule with teacher attendance status (for JadwalScreen)
    @GET("siswa/weekly-schedule-attendance")
    suspend fun getWeeklyScheduleWithAttendance(
        @Header("Authorization") token: String
    ): Response<WeeklyScheduleWithAttendanceResponse>

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
    
    // Submit teacher attendance (siswa melaporkan kehadiran guru)
    @POST("siswa/kehadiran-guru/submit")
    suspend fun submitKehadiran(
        @Header("Authorization") token: String,
        @Body body: KehadiranSubmitRequest
    ): Response<KehadiranSubmitResponse>

    // Get attendance history with pagination
    @GET("siswa/kehadiran-guru/riwayat")
    suspend fun getKehadiranHistory(
        @Header("Authorization") token: String,
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20
    ): Response<KehadiranHistoryResponse>

    // Get today's GURU attendance status (siswa melaporkan kehadiran guru)
    @GET("siswa/kehadiran-guru/today")
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

    // ========== KURIKULUM ENDPOINTS ==========
    
    // Dashboard overview - All classes with teacher attendance status
    @GET("kurikulum/dashboard")
    suspend fun getKurikulumDashboard(
        @Header("Authorization") token: String,
        @Query("day") day: String? = null,
        @Query("class_id") classId: Int? = null,
        @Query("subject_id") subjectId: Int? = null,
        @Query("week_offset") weekOffset: Int? = null,
        @Query("refresh") refresh: Boolean? = null
    ): Response<KurikulumDashboardResponse>
    
    // Class management - Sort and filter classes by teacher status
    @GET("kurikulum/classes")
    suspend fun getKurikulumClasses(
        @Header("Authorization") token: String,
        @Query("status") status: String? = null
    ): Response<ClassManagementResponse>
    
    // Get available substitute teachers
    @GET("kurikulum/substitutes")
    suspend fun getAvailableSubstitutes(
        @Header("Authorization") token: String,
        @Query("period") period: Int? = null,
        @Query("subject_id") subjectId: Int? = null
    ): Response<SubstituteTeachersResponse>
    
    // Assign substitute teacher
    @POST("kurikulum/assign-substitute")
    suspend fun assignSubstitute(
        @Header("Authorization") token: String,
        @Body request: AssignSubstituteRequest
    ): Response<AssignSubstituteResponse>
    
    // Attendance history with filters and pagination
    @GET("kurikulum/history")
    suspend fun getKurikulumHistory(
        @Header("Authorization") token: String,
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("date_from") dateFrom: String? = null,
        @Query("date_to") dateTo: String? = null,
        @Query("teacher_id") teacherId: Int? = null,
        @Query("class_id") classId: Int? = null,
        @Query("status") status: String? = null
    ): Response<KurikulumHistoryResponse>
    
    // Attendance statistics for reports
    @GET("kurikulum/statistics")
    suspend fun getKurikulumStatistics(
        @Header("Authorization") token: String,
        @Query("month") month: Int? = null,
        @Query("year") year: Int? = null,
        @Query("teacher_id") teacherId: Int? = null
    ): Response<StatisticsResponse>
    
    // Export attendance data
    @GET("kurikulum/export")
    suspend fun exportAttendance(
        @Header("Authorization") token: String,
        @Query("date_from") dateFrom: String? = null,
        @Query("date_to") dateTo: String? = null,
        @Query("teacher_id") teacherId: Int? = null,
        @Query("class_id") classId: Int? = null
    ): Response<ExportResponse>
    
    // Get students in a class
    @GET("kurikulum/class/{classId}/students")
    suspend fun getClassStudents(
        @Header("Authorization") token: String,
        @Path("classId") classId: Int
    ): Response<ClassStudentsResponse>
    
    // Filter data - classes list
    @GET("kurikulum/filter/classes")
    suspend fun getFilterClasses(
        @Header("Authorization") token: String
    ): Response<FilterClassesResponse>
    
    // Filter data - teachers list
    @GET("kurikulum/filter/teachers")
    suspend fun getFilterTeachers(
        @Header("Authorization") token: String
    ): Response<FilterTeachersResponse>
    
    // Get pending attendances for confirmation
    @GET("kurikulum/pending")
    suspend fun getPendingAttendances(
        @Header("Authorization") token: String,
        @Query("date") date: String? = null
    ): Response<PendingAttendanceResponse>
    
    // Confirm single pending attendance
    @POST("kurikulum/confirm-attendance")
    suspend fun confirmAttendance(
        @Header("Authorization") token: String,
        @Body request: ConfirmAttendanceRequest
    ): Response<ConfirmAttendanceResponse>
    
    // Bulk confirm pending attendances
    @POST("kurikulum/bulk-confirm")
    suspend fun bulkConfirmAttendance(
        @Header("Authorization") token: String,
        @Body request: BulkConfirmRequest
    ): Response<BulkConfirmResponse>
}
