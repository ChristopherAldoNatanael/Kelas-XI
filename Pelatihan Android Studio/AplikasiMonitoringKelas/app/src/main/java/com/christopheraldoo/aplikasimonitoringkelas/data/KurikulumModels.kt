package com.christopheraldoo.aplikasimonitoringkelas.data

import com.google.gson.annotations.SerializedName

// ===============================================
// KURIKULUM DATA MODELS
// For Dashboard, Class Management, and History screens
// ===============================================

// === DASHBOARD OVERVIEW ===
data class KurikulumDashboardResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("date") val date: String,
    @SerializedName("target_date") val targetDate: String? = null,
    @SerializedName("day") val day: String,
    @SerializedName("week_info") val weekInfo: WeekInfo? = null,
    @SerializedName("is_future_date") val isFutureDate: Boolean = false,
    @SerializedName("stats") val stats: DashboardStats,
    @SerializedName("data") val data: List<ScheduleOverview>,
    @SerializedName("grouped_by_class") val groupedByClass: Map<String, List<ScheduleOverview>>? = null,
    @SerializedName("requires_class_filter") val requiresClassFilter: Boolean = false,
    @SerializedName("available_classes") val availableClasses: List<AvailableClass>? = null
)

data class WeekInfo(
    @SerializedName("week_offset") val weekOffset: Int = 0,
    @SerializedName("week_start") val weekStart: String = "",
    @SerializedName("week_end") val weekEnd: String = "",
    @SerializedName("week_label") val weekLabel: String = "Minggu Ini",
    @SerializedName("is_current_week") val isCurrentWeek: Boolean = true,
    @SerializedName("is_future_date") val isFutureDate: Boolean = false
)

data class AvailableClass(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("level") val level: Int? = null,
    @SerializedName("major") val major: String? = null,
    @SerializedName("display_name") val displayName: String
)

data class DashboardStats(
    @SerializedName("total_schedules") val totalSchedules: Int = 0,
    @SerializedName("hadir") val hadir: Int = 0,
    @SerializedName("telat") val telat: Int = 0,
    @SerializedName("tidak_hadir") val tidakHadir: Int = 0,
    @SerializedName("pending") val pending: Int = 0,
    @SerializedName("diganti") val diganti: Int = 0,
    @SerializedName("izin") val izin: Int = 0
)

data class ScheduleOverview(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("class_id") val classId: Int? = null,
    @SerializedName("class_name") val className: String = "Unknown",
    @SerializedName("class_level") val classLevel: Int? = null,
    @SerializedName("class_major") val classMajor: String? = null,
    @SerializedName("subject_id") val subjectId: Int? = null,
    @SerializedName("subject_name") val subjectName: String = "Unknown",
    @SerializedName("subject_code") val subjectCode: String? = null,
    @SerializedName("teacher_id") val teacherId: Int? = null,
    @SerializedName("teacher_name") val teacherName: String = "Unknown",
    @SerializedName("teacher_nip") val teacherNip: String? = null,
    @SerializedName("period") val period: Int? = null,
    @SerializedName("start_time") val startTime: String? = null,
    @SerializedName("end_time") val endTime: String? = null,
    @SerializedName("status") val status: String? = "pending", // hadir, telat, tidak_hadir, pending, diganti, izin, belum
    @SerializedName("status_color") val statusColor: String? = "gray", // green, yellow, red, gray, blue, purple
    @SerializedName("late_minutes") val lateMinutes: Int? = null,
    @SerializedName("substitute_teacher") val substituteTeacher: String? = null,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("attendance_id") val attendanceId: Int? = null,
    @SerializedName("last_updated") val lastUpdated: String? = null,
    @SerializedName("teacher_on_leave") val teacherOnLeave: Boolean = false,
    @SerializedName("leave_reason") val leaveReason: String? = null,
    @SerializedName("is_future") val isFuture: Boolean = false
)

// === CLASS MANAGEMENT ===
data class ClassManagementResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("current_time") val currentTime: String,
    @SerializedName("summary") val summary: ClassManagementSummary? = null,
    @SerializedName("status_counts") val statusCounts: StatusCounts,
    @SerializedName("alert_classes") val alertClasses: List<ClassScheduleItem>,
    @SerializedName("grouped_by_class") val groupedByClass: List<ClassGroup>? = null,
    @SerializedName("data") val data: List<ClassScheduleItem>
)

data class ClassManagementSummary(
    @SerializedName("total_classes_need_attention") val totalClassesNeedAttention: Int = 0,
    @SerializedName("total_schedules_need_attention") val totalSchedulesNeedAttention: Int = 0,
    @SerializedName("tidak_hadir_count") val tidakHadirCount: Int = 0,
    @SerializedName("telat_count") val telatCount: Int = 0,
    @SerializedName("pending_count") val pendingCount: Int = 0,
    @SerializedName("izin_count") val izinCount: Int = 0,
    @SerializedName("alert_count") val alertCount: Int = 0
)

data class ClassGroup(
    @SerializedName("class_id") val classId: Int? = null,
    @SerializedName("class_name") val className: String,
    @SerializedName("class_level") val classLevel: Int? = null,
    @SerializedName("class_major") val classMajor: String? = null,
    @SerializedName("total_issues") val totalIssues: Int = 0,
    @SerializedName("has_urgent") val hasUrgent: Boolean = false,
    @SerializedName("has_pending") val hasPending: Boolean = false,
    @SerializedName("schedules") val schedules: List<ClassScheduleItem> = emptyList()
)

data class StatusCounts(
    @SerializedName("hadir") val hadir: Int = 0,
    @SerializedName("telat") val telat: Int = 0,
    @SerializedName("tidak_hadir") val tidakHadir: Int = 0,
    @SerializedName("pending") val pending: Int = 0,
    @SerializedName("diganti") val diganti: Int = 0,
    @SerializedName("izin") val izin: Int = 0
)

data class ClassScheduleItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("class_id") val classId: Int? = null,
    @SerializedName("class_name") val className: String = "Unknown",
    @SerializedName("class_level") val classLevel: Int? = null,
    @SerializedName("class_major") val classMajor: String? = null,
    @SerializedName("subject_id") val subjectId: Int? = null,
    @SerializedName("subject_name") val subjectName: String = "Unknown",
    @SerializedName("teacher_id") val teacherId: Int? = null,
    @SerializedName("teacher_name") val teacherName: String = "Unknown",
    @SerializedName("teacher_nip") val teacherNip: String? = null,
    @SerializedName("period") val period: Int? = null,
    @SerializedName("start_time") val startTime: String? = null,
    @SerializedName("end_time") val endTime: String? = null,
    @SerializedName("status") val status: String? = "pending",
    @SerializedName("late_minutes") val lateMinutes: Int? = null,
    @SerializedName("substitute_teacher_id") val substituteTeacherId: Int? = null,
    @SerializedName("substitute_teacher_name") val substituteTeacherName: String? = null,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("attendance_id") val attendanceId: Int? = null,
    @SerializedName("is_current_period") val isCurrentPeriod: Boolean = false,
    @SerializedName("no_teacher_alert") val noTeacherAlert: Boolean = false,
    @SerializedName("teacher_on_leave") val teacherOnLeave: Boolean = false,
    @SerializedName("leave_reason") val leaveReason: String? = null,
    @SerializedName("needs_substitute") val needsSubstitute: Boolean = false
)

// === SUBSTITUTE TEACHERS ===
data class SubstituteTeachersResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("period") val period: Int? = null,
    @SerializedName("data") val data: List<SubstituteTeacher>
)

data class SubstituteTeacher(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("nip") val nip: String? = null,
    @SerializedName("is_subject_teacher") val isSubjectTeacher: Boolean = false
)

data class AssignSubstituteRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("substitute_teacher_id") val substituteTeacherId: Int,
    @SerializedName("keterangan") val keterangan: String? = null
)

data class AssignSubstituteResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: AssignmentResult? = null
)

data class AssignmentResult(
    @SerializedName("attendance_id") val attendanceId: Int,
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("original_teacher_id") val originalTeacherId: Int,
    @SerializedName("substitute_teacher_id") val substituteTeacherId: Int,
    @SerializedName("substitute_teacher_name") val substituteTeacherName: String,
    @SerializedName("status") val status: String,
    @SerializedName("assigned_at") val assignedAt: String
)

// === ATTENDANCE HISTORY ===
data class KurikulumHistoryResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: List<KurikulumAttendanceHistoryItem>,
    @SerializedName("pagination") val pagination: PaginationInfo
)

data class KurikulumAttendanceHistoryItem(
    @SerializedName("id") val id: Int,
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String? = null,
    @SerializedName("period") val period: Int? = null,
    @SerializedName("time") val time: String? = null,
    @SerializedName("class_name") val className: String,
    @SerializedName("class_level") val classLevel: Int? = null,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("original_teacher_id") val originalTeacherId: Int? = null,
    @SerializedName("original_teacher_name") val originalTeacherName: String? = null,
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("status") val status: String,
    @SerializedName("arrival_time") val arrivalTime: String? = null,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("is_substituted") val isSubstituted: Boolean = false,
    @SerializedName("created_at") val createdAt: String
)

data class PaginationInfo(
    @SerializedName("current_page") val currentPage: Int,
    @SerializedName("per_page") val perPage: Int,
    @SerializedName("total") val total: Int,
    @SerializedName("last_page") val lastPage: Int
)

// === STATISTICS ===
data class StatisticsResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("statistics") val statistics: MonthlyStats,
    @SerializedName("daily_breakdown") val dailyBreakdown: Map<String, DailyStats>? = null,
    @SerializedName("teacher_statistics") val teacherStatistics: List<TeacherStats>? = null
)

data class MonthlyStats(
    @SerializedName("month") val month: Int,
    @SerializedName("year") val year: Int,
    @SerializedName("month_name") val monthName: String,
    @SerializedName("total_records") val totalRecords: Int,
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("diganti") val diganti: Int,
    @SerializedName("percentage") val percentage: PercentageStats
)

data class PercentageStats(
    @SerializedName("hadir") val hadir: Float,
    @SerializedName("telat") val telat: Float,
    @SerializedName("tidak_hadir") val tidakHadir: Float,
    @SerializedName("diganti") val diganti: Float
)

data class DailyStats(
    @SerializedName("hadir") val hadir: Int = 0,
    @SerializedName("telat") val telat: Int = 0,
    @SerializedName("tidak_hadir") val tidakHadir: Int = 0,
    @SerializedName("diganti") val diganti: Int = 0,
    @SerializedName("total") val total: Int = 0
)

data class TeacherStats(
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("hadir") val hadir: Int = 0,
    @SerializedName("telat") val telat: Int = 0,
    @SerializedName("tidak_hadir") val tidakHadir: Int = 0,
    @SerializedName("total") val total: Int = 0
)

// === EXPORT DATA ===
data class ExportResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("date_range") val dateRange: DateRange? = null,
    @SerializedName("total_records") val totalRecords: Int,
    @SerializedName("data") val data: List<ExportItem>
)

data class DateRange(
    @SerializedName("from") val from: String,
    @SerializedName("to") val to: String
)

data class ExportItem(
    @SerializedName("tanggal") val tanggal: String? = null,
    @SerializedName("hari") val hari: String? = null,
    @SerializedName("jam_ke") val jamKe: String? = null,
    @SerializedName("waktu") val waktu: String? = null,
    @SerializedName("kelas") val kelas: String? = null,
    @SerializedName("mata_pelajaran") val mataPelajaran: String? = null,
    @SerializedName("guru_asli") val guruAsli: String? = null,
    @SerializedName("guru_pengganti") val guruPengganti: String? = null,
    @SerializedName("status") val status: String? = null,
    @SerializedName("jam_masuk") val jamMasuk: String? = null,
    @SerializedName("keterangan") val keterangan: String? = null
)

// === CLASS STUDENTS ===
data class ClassStudentsResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("class") val classInfo: ClassInfo,
    @SerializedName("total_students") val totalStudents: Int,
    @SerializedName("students") val students: List<StudentInfo>
)

data class ClassInfo(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("level") val level: Int? = null,
    @SerializedName("major") val major: String? = null
)

data class StudentInfo(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String
)

// === FILTER DATA ===
data class FilterClassesResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<FilterClass>
)

data class FilterClass(
    @SerializedName("id") val id: Int,
    @SerializedName("nama") val nama: String? = null,
    @SerializedName("nama_kelas") val namaKelas: String? = null,
    @SerializedName("tingkat") val tingkat: Int? = null,
    @SerializedName("jurusan") val jurusan: String? = null
) {
    // Helper to get display name (handles both 'nama' and 'nama_kelas' fields)
    val displayName: String
        get() = nama ?: namaKelas ?: "Kelas ${id}"
}

data class FilterTeachersResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<FilterTeacher>
)

data class FilterTeacher(
    @SerializedName("id") val id: Int,
    @SerializedName("nama") val nama: String,
    @SerializedName("nip") val nip: String? = null
)

// === PENDING ATTENDANCE ===
data class PendingAttendanceResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: PendingAttendanceData
)

data class PendingAttendanceData(
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("current_time") val currentTime: String? = null,
    @SerializedName("total_pending") val totalPending: Int,
    @SerializedName("belum_lapor_count") val belumLaporCount: Int = 0,
    @SerializedName("pending_count") val pendingCount: Int = 0,
    @SerializedName("grouped_by_class") val groupedByClass: List<PendingClassGroup>,
    @SerializedName("all_pending") val allPending: List<PendingAttendanceItem>
)

data class PendingClassGroup(
    @SerializedName("class_name") val className: String,
    @SerializedName("class_id") val classId: Int? = null,
    @SerializedName("total_pending") val totalPending: Int,
    @SerializedName("belum_lapor_count") val belumLaporCount: Int = 0,
    @SerializedName("pending_count") val pendingCount: Int = 0,
    @SerializedName("schedules") val schedules: List<PendingAttendanceItem>
)

data class PendingAttendanceItem(
    @SerializedName("id") val id: Int? = null, // null jika belum ada attendance
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("time_start") val timeStart: String? = null,
    @SerializedName("time_end") val timeEnd: String? = null,
    @SerializedName("arrival_time") val arrivalTime: String? = null,
    @SerializedName("class_id") val classId: Int? = null,
    @SerializedName("class_name") val className: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_nip") val teacherNip: String? = null,
    @SerializedName("status") val status: String, // "pending" atau "belum_lapor"
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("has_attendance") val hasAttendance: Boolean = false,
    @SerializedName("is_past_schedule") val isPastSchedule: Boolean = false,
    @SerializedName("is_current_period") val isCurrentPeriod: Boolean = false,
    @SerializedName("created_at") val createdAt: String? = null
)

data class ConfirmAttendanceRequest(
    @SerializedName("attendance_id") val attendanceId: Int? = null,
    @SerializedName("schedule_id") val scheduleId: Int? = null,
    @SerializedName("status") val status: String,
    @SerializedName("keterangan") val keterangan: String? = null,
    @SerializedName("date") val date: String? = null
)

data class ConfirmAttendanceResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: ConfirmAttendanceResult? = null
)

data class ConfirmAttendanceResult(
    @SerializedName("id") val id: Int,
    @SerializedName("status") val status: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("class_name") val className: String,
    @SerializedName("subject_name") val subjectName: String
)

data class BulkConfirmRequest(
    @SerializedName("attendance_ids") val attendanceIds: List<Int>,
    @SerializedName("status") val status: String
)

data class BulkConfirmResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: BulkConfirmResult? = null
)

data class BulkConfirmResult(
    @SerializedName("confirmed_count") val confirmedCount: Int,
    @SerializedName("skipped_count") val skippedCount: Int,
    @SerializedName("results") val results: List<ConfirmAttendanceResult>
)
