package com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleApi
import com.christopheraldoo.aplikasimonitoringkelas.data.TodayKehadiranResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.ScheduleItem
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranHistoryResponse
import com.christopheraldoo.aplikasimonitoringkelas.data.RiwayatItem
import com.christopheraldoo.aplikasimonitoringkelas.data.KehadiranSubmitResponse
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*
import kotlin.concurrent.timer

/**
 * ViewModel untuk Role Siswa
 * Mengelola state untuk 3 screen: Jadwal, Kehadiran, Riwayat
 */
class SiswaViewModel(private val repository: NetworkRepository) : ViewModel() {

    // ========== DEBOUNCING ==========
    private var schedulesDebounceJob: Job? = null
    private var todayKehadiranDebounceJob: Job? = null
    private var riwayatDebounceJob: Job? = null

    companion object {
        private const val DEBOUNCE_DELAY_MS = 500L
    }

    // ========== JADWAL STATE ==========
    private val _schedulesState = MutableStateFlow<SchedulesUiState>(SchedulesUiState.Loading)
    val schedulesState: StateFlow<SchedulesUiState> = _schedulesState.asStateFlow()

    // ========== KEHADIRAN STATE ==========
    private val _todayKehadiranState = MutableStateFlow<TodayKehadiranUiState>(TodayKehadiranUiState.Loading)
    val todayKehadiranState: StateFlow<TodayKehadiranUiState> = _todayKehadiranState.asStateFlow()

    // Test data for when API fails
    private fun getTestSchedules(): List<ScheduleApi> {
        return listOf(
            ScheduleApi(
                id = 1,
                classId = 1,
                subjectId = 1,
                teacherId = 1,
                dayOfWeek = "Senin",
                period = 1,
                startTime = "07:00",
                endTime = "08:30",
                status = "active",
                className = "X RPL 1",
                subjectName = "Matematika Dasar",
                teacherName = "Budi Santoso"
            ),
            ScheduleApi(
                id = 2,
                classId = 1,
                subjectId = 2,
                teacherId = 2,
                dayOfWeek = "Senin",
                period = 2,
                startTime = "08:45",
                endTime = "10:15",
                status = "active",
                className = "X RPL 1",
                subjectName = "Bahasa Indonesia",
                teacherName = "Siti Nurhaliza"
            ),
            ScheduleApi(
                id = 3,
                classId = 1,
                subjectId = 3,
                teacherId = 3,
                dayOfWeek = "Selasa",
                period = 1,
                startTime = "07:00",
                endTime = "09:30",
                status = "active",
                className = "X RPL 1",
                subjectName = "Algoritma dan Pemrograman Dasar",
                teacherName = "Rizki Ramadhan"
            )
        )
    }

    private val _submitKehadiranState = MutableStateFlow<SubmitKehadiranUiState>(SubmitKehadiranUiState.Idle)
    val submitKehadiranState: StateFlow<SubmitKehadiranUiState> = _submitKehadiranState.asStateFlow()    // ========== RIWAYAT STATE ==========
    private val _riwayatState = MutableStateFlow<RiwayatUiState>(RiwayatUiState.Loading)
    val riwayatState: StateFlow<RiwayatUiState> = _riwayatState.asStateFlow()
    
    fun loadSchedules(forceRefresh: Boolean = false) {
        // Cancel previous job if exists
        schedulesDebounceJob?.cancel()

        schedulesDebounceJob = viewModelScope.launch {
            delay(DEBOUNCE_DELAY_MS)
            _schedulesState.value = SchedulesUiState.Loading
            try {
                // Get today's day name
                val todayDayName = SimpleDateFormat("EEEE", Locale("id", "ID")).format(Date())
                
                // Try new endpoint with attendance first, fallback to old endpoint
                val result = try {
                    repository.getSchedulesWithAttendance(forceRefresh)
                } catch (e: Exception) {
                    // Fallback to old endpoint without attendance
                    val fallbackResult = repository.getSchedules(forceRefresh)
                    if (fallbackResult.isSuccess) {
                        Result.success(Pair(fallbackResult.getOrNull() ?: emptyList(), todayDayName))
                    } else {
                        Result.failure(fallbackResult.exceptionOrNull() ?: Exception("Gagal memuat jadwal"))
                    }
                }
                
                if (result.isSuccess) {
                    val (schedules, serverTodayDay) = result.getOrNull() ?: Pair(emptyList(), todayDayName)
                    val finalTodayDay = if (serverTodayDay.isNullOrEmpty()) todayDayName else serverTodayDay
                    if (schedules.isNotEmpty()) {
                        val groupedByDay = schedules.groupBy { it.dayOfWeek }
                            .toSortedMap(compareBy { dayOrder(it) })
                        _schedulesState.value = SchedulesUiState.Success(schedules, groupedByDay, finalTodayDay)
                    } else {
                        _schedulesState.value = SchedulesUiState.Success(emptyList(), emptyMap(), finalTodayDay)
                    }
                } else {
                    val errorMessage = result.exceptionOrNull()?.message ?: "Gagal memuat jadwal"
                    _schedulesState.value = SchedulesUiState.Error(errorMessage)
                }
            } catch (e: Exception) {
                _schedulesState.value = SchedulesUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    // ========== LOAD TODAY KEHADIRAN STATUS ==========
    /**
     * Load jadwal hari ini berdasarkan waktu HP user
     * Mengambil dari jadwal mingguan dan filter berdasarkan hari ini
     */
    fun loadTodayKehadiranStatus(forceRefresh: Boolean = false) {
        // Cancel previous job if exists
        todayKehadiranDebounceJob?.cancel()

        todayKehadiranDebounceJob = viewModelScope.launch {
            _todayKehadiranState.value = TodayKehadiranUiState.Loading
            try {
                // Get today's day name from user's phone (Indonesian format)
                val today = Date()
                val todayFormatted = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(today)
                
                // Get Indonesian day name from phone's current date
                val englishDayName = SimpleDateFormat("EEEE", Locale.ENGLISH).format(today)
                val dayNameIndonesian = when (englishDayName.lowercase()) {
                    "monday" -> "Senin"
                    "tuesday" -> "Selasa"
                    "wednesday" -> "Rabu"
                    "thursday" -> "Kamis"
                    "friday" -> "Jumat"
                    "saturday" -> "Sabtu"
                    "sunday" -> "Minggu"
                    else -> englishDayName
                }
                
                android.util.Log.d("SiswaViewModel", "Today from phone: $englishDayName -> $dayNameIndonesian")
                
                // First, try to get from API with kehadiran status
                val result = repository.getTodayKehadiranStatus(forceRefresh)
                if (result.isSuccess) {
                    val response = result.getOrNull()
                    if (response != null && response.success && response.schedules.isNotEmpty()) {
                        _todayKehadiranState.value = TodayKehadiranUiState.Success(response)
                        return@launch
                    }
                }
                
                // Fallback: Get from weekly schedule and filter by today's day
                val scheduleResult = repository.getSchedules(forceRefresh)
                if (scheduleResult.isSuccess) {
                    val allSchedules = scheduleResult.getOrNull() ?: emptyList()
                    
                    // Filter jadwal untuk hari ini berdasarkan waktu HP
                    val todaySchedules = allSchedules.filter { 
                        it.dayOfWeek.equals(dayNameIndonesian, ignoreCase = true) 
                    }
                    
                    android.util.Log.d("SiswaViewModel", "Found ${todaySchedules.size} schedules for $dayNameIndonesian")
                    
                    // Convert to ScheduleItem format for kehadiran
                    val scheduleItems = todaySchedules.sortedBy { it.period }.map { schedule ->
                        // Check if teacher is on leave (izin)
                        val isOnLeave = schedule.attendanceStatus?.lowercase() == "izin"
                        
                        ScheduleItem(
                            scheduleId = schedule.id,
                            period = schedule.period,
                            time = "${schedule.startTime} - ${schedule.endTime}",
                            subject = schedule.subjectName ?: "Mata Pelajaran",
                            teacher = schedule.teacherName ?: "Guru",
                            submitted = isOnLeave, // If teacher is on leave, mark as "submitted" (no action needed)
                            status = if (isOnLeave) "izin" else null,
                            catatan = schedule.attendanceCatatan ?: "",
                            teacherOnLeave = isOnLeave,
                            leaveReason = if (isOnLeave) schedule.attendanceCatatan else null,
                            substituteTeacher = schedule.substituteTeacherName
                        )
                    }
                    
                    _todayKehadiranState.value = TodayKehadiranUiState.Success(
                        TodayKehadiranResponse(
                            success = true,
                            tanggal = todayFormatted,
                            dayOfWeek = dayNameIndonesian,
                            schedules = scheduleItems
                        )
                    )
                } else {
                    // Use test data as last resort
                    _todayKehadiranState.value = TodayKehadiranUiState.Success(getTestTodayKehadiran())
                }
            } catch (e: Exception) {
                android.util.Log.e("SiswaViewModel", "Error loading today kehadiran", e)
                // Network error - use test data
                _todayKehadiranState.value = TodayKehadiranUiState.Success(getTestTodayKehadiran())
            }
        }
    }

    private fun getTestTodayKehadiran(): TodayKehadiranResponse {
        val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
        val dayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date()).lowercase()

        // Create test schedules for today
        val testSchedules = mutableListOf<ScheduleItem>()
        val todaySchedules = getTestSchedules().filter { it.dayOfWeek.lowercase() == dayName }

        todaySchedules.forEach { schedule ->
            testSchedules.add(
                ScheduleItem(
                    scheduleId = schedule.id,
                    period = schedule.period,
                    time = "${schedule.startTime} - ${schedule.endTime}",
                    subject = schedule.subjectName ?: "Mata Pelajaran",
                    teacher = schedule.teacherName ?: "Guru",
                    submitted = false,
                    status = null,
                    catatan = ""
                )
            )
        }

        return TodayKehadiranResponse(
            success = true,
            tanggal = today,
            dayOfWeek = dayName,
            schedules = testSchedules
        )
    }

    // ========== SUBMIT KEHADIRAN ==========
    fun submitKehadiran(scheduleId: Int, status: String, catatan: String?) {
        viewModelScope.launch {
            _submitKehadiranState.value = SubmitKehadiranUiState.Loading
            try {
                val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                val result = repository.submitKehadiran(scheduleId, today, status, catatan)

                if (result.isSuccess) {
                    val response = result.getOrNull()
                    if (response != null && response.success) {
                        _submitKehadiranState.value = SubmitKehadiranUiState.Success(response.message)
                        // PENTING: Refresh KEDUA data setelah submit
                        // 1. Refresh today's status (halaman kehadiran)
                        loadTodayKehadiranStatus(forceRefresh = true)
                        // 2. Refresh riwayat (halaman riwayat) - TANPA perlu keluar aplikasi
                        loadRiwayat(forceRefresh = true)
                    } else {
                        _submitKehadiranState.value = SubmitKehadiranUiState.Error(
                            response?.message ?: "Gagal menyimpan kehadiran"
                        )
                    }
                } else {
                    _submitKehadiranState.value = SubmitKehadiranUiState.Error(
                        result.exceptionOrNull()?.message ?: "Gagal menyimpan kehadiran"
                    )
                }
            } catch (e: Exception) {
                _submitKehadiranState.value = SubmitKehadiranUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    fun resetSubmitState() {
        _submitKehadiranState.value = SubmitKehadiranUiState.Idle
    }

    // ========== LOAD RIWAYAT WITH PAGINATION ==========
    fun loadRiwayat(forceRefresh: Boolean = false, page: Int = 1, limit: Int = 20) {
        // Cancel previous job if exists
        riwayatDebounceJob?.cancel()

        riwayatDebounceJob = viewModelScope.launch {
            // Only show loading for first page
            if (page == 1) {
                _riwayatState.value = RiwayatUiState.Loading
            }
            try {
                val result = repository.getKehadiranHistory(forceRefresh, page, limit)
                if (result.isSuccess) {
                    val response = result.getOrNull()
                    // CRITICAL FIX: Always show success even if data is empty
                    if (response != null && response.success) {
                        _riwayatState.value = RiwayatUiState.Success(response.data ?: emptyList())
                    } else {
                        // Empty data is not an error
                        _riwayatState.value = RiwayatUiState.Success(emptyList())
                    }
                } else {
                    // Server failure - show empty instead of error
                    _riwayatState.value = RiwayatUiState.Success(emptyList())
                }
            } catch (e: Exception) {
                // CRITICAL FIX: Show empty instead of error to prevent crash
                _riwayatState.value = RiwayatUiState.Success(emptyList())
            }
        }
    }

    // ========== LOAD MORE RIWAYAT (PAGINATION) ==========
    fun loadMoreRiwayat(currentPage: Int, limit: Int = 20) {
        loadRiwayat(forceRefresh = false, page = currentPage + 1, limit = limit)
    }

    // ========== HELPER FUNCTIONS ==========
    private fun dayOrder(day: String): Int {
        return when (day.lowercase()) {
            "senin", "monday" -> 1
            "selasa", "tuesday" -> 2
            "rabu", "wednesday" -> 3
            "kamis", "thursday" -> 4
            "jumat", "friday" -> 5
            "sabtu", "saturday" -> 6
            "minggu", "sunday" -> 7
            else -> 8
        }
    }
}

// ========== UI STATES ==========

sealed class SchedulesUiState {
    object Loading : SchedulesUiState()
    data class Success(
        val schedules: List<ScheduleApi>,
        val groupedByDay: Map<String, List<ScheduleApi>>,
        val todayDay: String = ""
    ) : SchedulesUiState()
    data class Error(val message: String) : SchedulesUiState()
}

sealed class TodayKehadiranUiState {
    object Loading : TodayKehadiranUiState()
    data class Success(val data: TodayKehadiranResponse) : TodayKehadiranUiState()
    data class Error(val message: String) : TodayKehadiranUiState()
}

sealed class SubmitKehadiranUiState {
    object Idle : SubmitKehadiranUiState()
    object Loading : SubmitKehadiranUiState()
    data class Success(val message: String) : SubmitKehadiranUiState()
    data class Error(val message: String) : SubmitKehadiranUiState()
}

sealed class RiwayatUiState {
    object Loading : RiwayatUiState()
    data class Success(val data: List<RiwayatItem>) : RiwayatUiState()
    data class Error(val message: String) : RiwayatUiState()
}

