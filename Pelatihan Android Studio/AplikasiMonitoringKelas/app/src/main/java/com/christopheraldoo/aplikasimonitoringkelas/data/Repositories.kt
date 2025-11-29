package com.christopheraldoo.aplikasimonitoringkelas.data

import android.content.Context
import com.christopheraldoo.aplikasimonitoringkelas.network.ApiService
import com.christopheraldoo.aplikasimonitoringkelas.network.RetrofitClient
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkUtils
import retrofit2.Response

/**
 * Repository for User-related operations
 * All data comes from MySQL database via Laravel API
 */
class UserRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun login(email: String, password: String): Response<LoginResponse> {
        return apiService.login(email, password)
    }
    
    suspend fun logout(): Response<ApiResponse<com.google.gson.JsonObject>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.logout(token)
    }
    
    suspend fun getCurrentUser(): Response<ApiResponse<UserApi>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getCurrentUser(token)
    }
    
    suspend fun getAllUsers(): Response<ApiResponse<List<UserApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getUsers(token)
    }
}

/**
 * Repository for Schedule-related operations
 * All data comes from MySQL database via Laravel API
 */
class ScheduleRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun getAllSchedules(): Response<ApiResponse<List<ScheduleApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getSchedules(token)
    }
    
    suspend fun getSchedulesByDay(day: String): Response<ApiResponse<List<ScheduleApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getSchedules(token, day = day)
    }
    
    suspend fun createSchedule(schedule: com.google.gson.JsonObject): Response<ApiResponse<ScheduleApi>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.createSchedule(token, schedule)
    }
    
    suspend fun updateSchedule(scheduleId: Int, schedule: com.google.gson.JsonObject): Response<ApiResponse<ScheduleApi>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.updateSchedule(token, scheduleId, schedule)
    }
    
    suspend fun deleteSchedule(scheduleId: Int): Response<ApiResponse<com.google.gson.JsonObject>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.deleteSchedule(token, scheduleId)
    }
}

/**
 * Repository for Classroom monitoring operations
 * All data comes from MySQL database via Laravel API
 */
class ClassroomRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun getAllClassrooms(): Response<ApiResponse<List<ClassroomApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getClassrooms(token)
    }
    
    suspend fun getEmptyClassrooms(day: String? = null, periodNumber: Int? = null): Response<ApiResponse<List<ClassroomApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getEmptyClassrooms(token, day, periodNumber)
    }
}

/**
 * Repository for Subject-related operations
 * All data comes from MySQL database via Laravel API
 */
class SubjectRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun getAllSubjects(): Response<ApiResponse<List<SubjectApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getSubjects(token)
    }
    
    // No auth required for dropdown endpoints
    suspend fun getDropdownSubjects(): Response<ApiResponse<List<SubjectDropdown>>> {
        return apiService.getDropdownSubjects()
    }
    
    suspend fun getTeachersBySubject(subjectId: Int): Response<ApiResponse<TeachersBySubjectResponse>> {
        return apiService.getTeachersBySubject(subjectId)
    }
}

/**
 * Repository for Teacher-related operations
 * All data comes from MySQL database via Laravel API
 */
class TeacherRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun getAllTeachers(): Response<ApiResponse<List<TeacherApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getTeachers(token)
    }
}

/**
 * Repository for Notification-related operations
 * All data comes from MySQL database via Laravel API
 */
class NotificationRepository(private val context: Context) {
    private val apiService: ApiService = RetrofitClient.createApiService(context)
    
    suspend fun getAllNotifications(): Response<ApiResponse<List<NotificationApi>>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getNotifications(token)
    }
    
    suspend fun markAsRead(notificationId: Int): Response<ApiResponse<com.google.gson.JsonObject>> {
        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.markNotificationAsRead(token, notificationId)
    }
    
    suspend fun getUnreadCount(): Response<ApiResponse<com.google.gson.JsonObject>> {        val token = NetworkUtils.getAuthToken(context) ?: return Response.error(401, okhttp3.ResponseBody.create(null, ""))
        return apiService.getUnreadNotificationCount(token)
    }
}