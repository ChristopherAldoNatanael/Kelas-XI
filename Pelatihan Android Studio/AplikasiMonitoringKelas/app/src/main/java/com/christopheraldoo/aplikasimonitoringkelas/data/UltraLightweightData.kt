package com.christopheraldoo.aplikasimonitoringkelas.data

import com.google.gson.annotations.SerializedName

/**
 * Data classes untuk endpoint ultra lightweight siswa
 */

// Jadwal Hari Ini Ultra Lightweight
data class JadwalHariIni(
    @SerializedName("periode") val periode: Int,
    @SerializedName("waktu") val waktu: String,
    @SerializedName("mapel") val mapel: String?,
    @SerializedName("guru") val guru: String?
)

// Riwayat Kehadiran Ultra Lightweight
data class RiwayatKehadiran(
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("mapel") val mapel: String?,
    @SerializedName("periode") val periode: Int
)

// Paginated Response untuk semua endpoint yang di-paginate
data class PaginatedResponse<T>(
    @SerializedName("data") val data: T,
    @SerializedName("pagination") val pagination: PaginationMeta
)

// Pagination Metadata
data class PaginationMeta(
    @SerializedName("current_page") val currentPage: Int,
    @SerializedName("total") val total: Int,
    @SerializedName("has_more") val hasMore: Boolean
)

// Enhanced Kehadiran Request
data class KehadiranRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("guru_hadir") val guruHadir: Boolean,
    @SerializedName("catatan") val catatan: String?
)

// Today Status Response
data class TodayStatusResponse(
    @SerializedName("tanggal") val tanggal: String,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("schedules") val schedules: List<TodaySchedule>
)

// Today Schedule Item
data class TodaySchedule(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("period") val period: Int,
    @SerializedName("time") val time: String,
    @SerializedName("subject") val subject: String,
    @SerializedName("teacher") val teacher: String,
    @SerializedName("submitted") val submitted: Boolean,
    @SerializedName("guru_hadir") val guruHadir: Boolean?,
    @SerializedName("catatan") val catatan: String
)

// Enhanced Schedule untuk my-schedule endpoint
data class Schedule(
    @SerializedName("id") val id: Int,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("period_number") val periodNumber: Int,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
    @SerializedName("subject_name") val subjectName: String?,
    @SerializedName("teacher_name") val teacherName: String?,
    @SerializedName("classroom_name") val classroomName: String?
)

// API Response untuk jadwal hari ini
data class JadwalHariIniResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("hari") val hari: String,
    @SerializedName("data") val data: List<JadwalHariIni>,
    @SerializedName("jumlah") val jumlah: Int
)

// API Response untuk riwayat kehadiran
data class RiwayatKehadiranResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<RiwayatKehadiran>,
    @SerializedName("pagination") val pagination: PaginationMeta
)
