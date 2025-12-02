package com.christopheraldoo.aplikasimonitoringkelas.data

import com.google.gson.annotations.SerializedName

// === KEPALA SEKOLAH DASHBOARD MODELS ===

data class KepalaSekolahDashboardResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: KepalaSekolahDashboardData?
)

data class KepalaSekolahDashboardData(
    @SerializedName("week_info") val weekInfo: KepsekWeekInfo,
    @SerializedName("this_week") val thisWeek: WeekStatistics,
    @SerializedName("last_week") val lastWeek: WeekStatistics,
    @SerializedName("trends") val trends: Trends,
    @SerializedName("daily_breakdown") val dailyBreakdown: List<DailyBreakdown>,
    @SerializedName("teachers_on_leave") val teachersOnLeave: List<TeacherOnLeave>,
    @SerializedName("top_late_teachers") val topLateTeachers: List<TopLateTeacher>,
    @SerializedName("class_attendance_rates") val classAttendanceRates: List<ClassAttendanceRate>,
    @SerializedName("teachers_attendance_today") val teachersAttendanceToday: TeachersAttendanceToday? = null
)

// Renamed to avoid conflict with KurikulumModels.WeekInfo
data class KepsekWeekInfo(
    @SerializedName("week_offset") val weekOffset: Int,
    @SerializedName("week_start") val weekStart: String,
    @SerializedName("week_end") val weekEnd: String,
    @SerializedName("week_label") val weekLabel: String,
    @SerializedName("is_current_week") val isCurrentWeek: Boolean
)

data class WeekStatistics(
    @SerializedName("total") val total: Int,
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("izin") val izin: Int,
    @SerializedName("diganti") val diganti: Int,
    @SerializedName("pending") val pending: Int,
    @SerializedName("attendance_rate") val attendanceRate: Double,
    @SerializedName("on_time_rate") val onTimeRate: Double
)

data class Trends(
    @SerializedName("hadir") val hadir: TrendItem,
    @SerializedName("telat") val telat: TrendItem,
    @SerializedName("tidak_hadir") val tidakHadir: TrendItem,
    @SerializedName("attendance_rate") val attendanceRate: TrendItem
)

data class TrendItem(
    @SerializedName("value") val value: Double,
    @SerializedName("percentage") val percentage: Double,
    @SerializedName("is_positive") val isPositive: Boolean
)

data class DailyBreakdown(
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("day_full") val dayFull: String,
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("izin") val izin: Int,
    @SerializedName("total") val total: Int
)

data class TeacherOnLeave(
    @SerializedName("id") val id: Int,
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("status") val status: String,
    @SerializedName("leave_type") val leaveType: String?,
    @SerializedName("original_teacher_id") val originalTeacherId: Int,
    @SerializedName("original_teacher_name") val originalTeacherName: String,
    @SerializedName("substitute_teacher_id") val substituteTeacherId: Int?,
    @SerializedName("substitute_teacher_name") val substituteTeacherName: String?,
    @SerializedName("class_name") val className: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("time") val time: String?,
    @SerializedName("keterangan") val keterangan: String?,
    @SerializedName("source") val source: String?
)

data class TopLateTeacher(
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_nip") val teacherNip: String?,
    @SerializedName("late_count") val lateCount: Int
)

data class ClassAttendanceRate(
    @SerializedName("class_name") val className: String,
    @SerializedName("total") val total: Int,
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("attendance_rate") val attendanceRate: Double
)

// === ATTENDANCE LIST ===

data class KepsekAttendanceListResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: AttendanceListData?
)

data class AttendanceListData(
    @SerializedName("date_range") val dateRange: KepsekDateRange,
    @SerializedName("summary") val summary: AttendanceSummary,
    @SerializedName("total") val total: Int,
    @SerializedName("attendances") val attendances: List<AttendanceItem>
)

// Renamed to avoid conflict with KurikulumModels.DateRange
data class KepsekDateRange(
    @SerializedName("start") val start: String,
    @SerializedName("end") val end: String
)

data class AttendanceSummary(
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("izin") val izin: Int,
    @SerializedName("diganti") val diganti: Int
)

data class AttendanceItem(
    @SerializedName("id") val id: Int,
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("time") val time: String?,
    @SerializedName("status") val status: String,
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_nip") val teacherNip: String?,
    @SerializedName("original_teacher_id") val originalTeacherId: Int?,
    @SerializedName("original_teacher_name") val originalTeacherName: String?,
    @SerializedName("substitute_teacher_name") val substituteTeacherName: String?,
    @SerializedName("class_name") val className: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("keterangan") val keterangan: String?
)

// === TEACHER PERFORMANCE ===

data class TeacherPerformanceResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: TeacherPerformanceData?
)

data class TeacherPerformanceData(
    @SerializedName("week_info") val weekInfo: WeekInfoSimple,
    @SerializedName("sort_by") val sortBy: String,
    @SerializedName("teachers") val teachers: List<TeacherPerformance>
)

data class WeekInfoSimple(
    @SerializedName("week_start") val weekStart: String,
    @SerializedName("week_end") val weekEnd: String
)

data class TeacherPerformance(
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_nip") val teacherNip: String?,
    @SerializedName("total_schedules") val totalSchedules: Int,
    @SerializedName("hadir") val hadir: Int,
    @SerializedName("telat") val telat: Int,
    @SerializedName("tidak_hadir") val tidakHadir: Int,
    @SerializedName("izin") val izin: Int,
    @SerializedName("attendance_rate") val attendanceRate: Double,
    @SerializedName("on_time_rate") val onTimeRate: Double
)

// === SCHEDULES WITH ATTENDANCE ===

data class KepsekScheduleWithAttendanceResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("data") val data: List<KepsekScheduleAttendanceItem>?
)

data class KepsekScheduleAttendanceItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("class_id") val classId: Int,
    @SerializedName("class_name") val className: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("period") val period: Int,
    @SerializedName("time_start") val timeStart: String,
    @SerializedName("time_end") val timeEnd: String,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("attendance_status") val attendanceStatus: String?,
    @SerializedName("attendance_time") val attendanceTime: String?,
    @SerializedName("substitute_teacher") val substituteTeacher: String?
)

// === TEACHERS ATTENDANCE TODAY ===

data class TeachersAttendanceToday(
    @SerializedName("date") val date: String,
    @SerializedName("day") val day: String,
    @SerializedName("summary") val summary: AttendanceTodaySummary,
    @SerializedName("teachers_present") val teachersPresent: List<TeacherAttendanceInfo>,
    @SerializedName("teachers_late") val teachersLate: List<TeacherAttendanceInfo>,
    @SerializedName("teachers_absent") val teachersAbsent: List<TeacherAttendanceInfo>,
    @SerializedName("teachers_on_leave") val teachersOnLeaveToday: List<TeacherAttendanceInfo>,
    @SerializedName("teachers_pending") val teachersPending: List<TeacherAttendanceInfo>
)

data class AttendanceTodaySummary(
    @SerializedName("total_scheduled") val totalScheduled: Int,
    @SerializedName("present") val present: Int,
    @SerializedName("late") val late: Int,
    @SerializedName("absent") val absent: Int,
    @SerializedName("on_leave") val onLeave: Int,
    @SerializedName("pending") val pending: Int
)

data class TeacherAttendanceInfo(
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_nip") val teacherNip: String? = null,
    @SerializedName("attendance_time") val attendanceTime: String? = null,
    @SerializedName("reason") val reason: String? = null,
    @SerializedName("first_schedule") val firstSchedule: String? = null,
    @SerializedName("schedule_count") val scheduleCount: Int = 0
)
