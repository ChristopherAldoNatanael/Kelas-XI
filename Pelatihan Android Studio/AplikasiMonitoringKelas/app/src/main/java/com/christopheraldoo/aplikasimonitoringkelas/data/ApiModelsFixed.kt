package com.christopheraldoo.aplikasimonitoringkelas.data

import com.google.gson.annotations.SerializedName

// === LOGIN & AUTH ===
data class LoginRequest(
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String
)

data class LoginData(
    @SerializedName("user") val user: User,
    @SerializedName("token") val token: String
)

data class LoginResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: LoginData?
)

data class User(
    @SerializedName("id") val id: Int,
    @SerializedName("nama") val nama: String,
    @SerializedName("email") val email: String,
    @SerializedName("role") val role: String,
    @SerializedName("class_id") val classId: Int?,
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
)

// === KEHADIRAN MODELS ===
data class KehadiranSubmitRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String = ""
)

data class KehadiranSubmitResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: KehadiranItem? = null
)

data class KehadiranItem(
    @SerializedName("id") val id: Int,
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String = "",
    @SerializedName("submitted_by") val submittedBy: Int
)

data class TodayKehadiranResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("schedules") val schedules: List<ScheduleItem>,
    @SerializedName("message") val message: String? = null
)

data class ScheduleItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("period") val period: Int,
    @SerializedName("time") val time: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("teacher") val teacher: String,
    @SerializedName("submitted") val submitted: Boolean,
    @SerializedName("guru_hadir") val guruHadir: Boolean?,
    @SerializedName("catatan") val catatan: String = ""
)

data class RiwayatKehadiranResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<RiwayatItem>,
    @SerializedName("total") val total: Int,
    @SerializedName("message") val message: String? = null
)

data class KehadiranHistoryResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<RiwayatItem>,
    @SerializedName("total") val total: Int,
    @SerializedName("message") val message: String? = null
)

data class RiwayatItem(
    @SerializedName("id") val id: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String = "",
    @SerializedName("day") val day: String,
    @SerializedName("period") val period: Int,
    @SerializedName("time") val time: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("teacher") val teacher: String
)

// === USER MANAGEMENT ===
data class UserApi(
    @SerializedName("id") val id: Int,
    @SerializedName("nama") val nama: String,
    @SerializedName("email") val email: String,
    @SerializedName("role") val role: String,
    @SerializedName("class_id") val classId: Int?,
    @SerializedName("class_name") val className: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class CreateUserRequest(
    @SerializedName("nama") val nama: String,
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String,
    @SerializedName("role") val role: String,
    @SerializedName("class_id") val classId: Int? = null
)

data class UpdateUserRequest(
    @SerializedName("nama") val nama: String?,
    @SerializedName("email") val email: String?,
    @SerializedName("password") val password: String?,
    @SerializedName("role") val role: String?,
    @SerializedName("class_id") val classId: Int?
)

// === SCHEDULE MANAGEMENT ===
data class ScheduleApi(
    @SerializedName("id") val id: Int,
    @SerializedName("class_id") val classId: Int,
    @SerializedName("subject_id") val subjectId: Int,
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("period") val period: Int,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
    @SerializedName("status") val status: String = "active",
    @SerializedName("class_name") val className: String? = null,
    @SerializedName("subject_name") val subjectName: String? = null,
    @SerializedName("teacher_name") val teacherName: String? = null
)

data class StudentWeeklyScheduleResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<ScheduleApi>,
    @SerializedName("message") val message: String? = null
)

// === TEACHER MANAGEMENT ===
data class TeacherApi(
    @SerializedName("id") val id: Int,
    @SerializedName("user_id") val userId: Int,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("nip") val nip: String? = null,
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("subject_id") val subjectId: Int? = null,
    @SerializedName("subject_name") val subjectName: String? = null
)

data class TeachersBySubjectResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<TeacherDropdown>,
    @SerializedName("message") val message: String? = null
)

data class TeacherDropdown(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("user_id") val userId: Int
)

// === SUBJECT MANAGEMENT ===
data class SubjectApi(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("code") val code: String,
    @SerializedName("description") val description: String? = null,
    @SerializedName("created_at") val createdAt: String? = null
)

data class SubjectsDropdownResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<SubjectDropdown>,
    @SerializedName("message") val message: String? = null
)

data class SubjectDropdown(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("code") val code: String
)

// === CLASSROOM MANAGEMENT ===
data class ClassroomApi(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("grade") val grade: Int,
    @SerializedName("major") val major: String? = null,
    @SerializedName("room_number") val roomNumber: String? = null,
    @SerializedName("capacity") val capacity: Int? = null,
    @SerializedName("student_count") val studentCount: Int? = 0
)

data class ClassroomsDropdownResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<ClassroomDropdown>,
    @SerializedName("message") val message: String? = null
)

data class ClassroomDropdown(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("grade") val grade: Int
)

// ClassApi is just an alias for ClassroomApi for backward compatibility
typealias ClassApi = ClassroomApi

// === DROPDOWN RESPONSES ===
data class AllDropdownResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("subjects") val subjects: List<SubjectDropdown>,
    @SerializedName("teachers") val teachers: List<TeacherDropdown>,
    @SerializedName("classrooms") val classrooms: List<ClassroomDropdown>,
    @SerializedName("message") val message: String? = null
)

// === NOTIFICATIONS ===
data class NotificationApi(
    @SerializedName("id") val id: Int,
    @SerializedName("title") val title: String,
    @SerializedName("message") val message: String,
    @SerializedName("type") val type: String,
    @SerializedName("is_read") val isRead: Boolean,
    @SerializedName("created_at") val createdAt: String
)

// === DASHBOARD ===
data class DashboardSummary(
    @SerializedName("total_students") val totalStudents: Int = 0,
    @SerializedName("total_teachers") val totalTeachers: Int = 0,
    @SerializedName("total_classes") val totalClasses: Int = 0,
    @SerializedName("total_schedules") val totalSchedules: Int = 0,
    @SerializedName("attendance_today") val attendanceToday: Int? = 0,
    @SerializedName("users_count") val usersCount: Int = totalStudents,
    @SerializedName("schedules_count") val schedulesCount: Int = totalSchedules,
    @SerializedName("teachers_count") val teachersCount: Int = totalTeachers,
    @SerializedName("subjects_count") val subjectsCount: Int? = 0
)

// === ATTENDANCE (Alternative naming) ===
data class AttendanceSubmitRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String = ""
)

data class AttendanceHistoryItem(
    @SerializedName("id") val id: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String = "",
    @SerializedName("day") val day: String,
    @SerializedName("period") val period: Int,
    @SerializedName("time") val time: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("teacher") val teacher: String
)

// === GENERIC RESPONSE ===
data class ApiResponse<T>(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: T? = null
)
