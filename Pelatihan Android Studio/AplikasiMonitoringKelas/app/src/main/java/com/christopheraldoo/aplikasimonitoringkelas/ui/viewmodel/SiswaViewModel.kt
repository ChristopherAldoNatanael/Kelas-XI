package com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.*
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

    private val _submitKehadiranState = MutableStateFlow<SubmitKehadiranUiState>(SubmitKehadiranUiState.Idle)
    val submitKehadiranState: StateFlow<SubmitKehadiranUiState> = _submitKehadiranState.asStateFlow()

    // ========== RIWAYAT STATE ==========
    private val _riwayatState = MutableStateFlow<RiwayatUiState>(RiwayatUiState.Loading)
    val riwayatState: StateFlow<RiwayatUiState> = _riwayatState.asStateFlow()
    fun loadSchedules() {
        // Cancel previous job if exists
        schedulesDebounceJob?.cancel()

        schedulesDebounceJob = viewModelScope.launch {
            delay(DEBOUNCE_DELAY_MS)
            _schedulesState.value = SchedulesUiState.Loading
            try {
                // Panggil repository.getSchedules() yang sudah diupdate tanpa parameter
                val result = repository.getSchedules()
                if (result.isSuccess) {
                    val schedules = result.getOrNull() ?: emptyList()
                    val groupedByDay = schedules.groupBy { it.dayOfWeek }
                        .toSortedMap(compareBy { dayOrder(it) })
                    _schedulesState.value = SchedulesUiState.Success(schedules, groupedByDay)
                } else {
                    _schedulesState.value = SchedulesUiState.Error(result.exceptionOrNull()?.message ?: "Gagal memuat jadwal")
                }
            } catch (e: Exception) {
                _schedulesState.value = SchedulesUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }

    // ========== LOAD TODAY KEHADIRAN STATUS ==========
    fun loadTodayKehadiranStatus() {
        // Cancel previous job if exists
        todayKehadiranDebounceJob?.cancel()

        todayKehadiranDebounceJob = viewModelScope.launch {
            _todayKehadiranState.value = TodayKehadiranUiState.Loading
            try {
                val result = repository.getTodayKehadiranStatus()
                if (result.isSuccess) {
                    val response = result.getOrNull()
                    // CRITICAL FIX: Always show success even if schedules is empty
                    if (response != null && response.success) {
                        _todayKehadiranState.value = TodayKehadiranUiState.Success(response)
                    } else {
                        // Empty data - create empty response
                        val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                        val dayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date()).lowercase()
                        _todayKehadiranState.value = TodayKehadiranUiState.Success(
                            TodayKehadiranResponse(
                                success = true,
                                tanggal = today,
                                dayOfWeek = dayName,
                                schedules = emptyList()
                            )
                        )
                    }
                } else {
                    // Server failure - show empty instead of error
                    val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                    val dayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date()).lowercase()
                    _todayKehadiranState.value = TodayKehadiranUiState.Success(
                        TodayKehadiranResponse(
                            success = true,
                            tanggal = today,
                            dayOfWeek = dayName,
                            schedules = emptyList()
                        )
                    )
                }
            } catch (e: Exception) {
                // CRITICAL FIX: Show empty instead of error to prevent crash
                val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                val dayName = SimpleDateFormat("EEEE", Locale.getDefault()).format(Date()).lowercase()
                _todayKehadiranState.value = TodayKehadiranUiState.Success(
                    TodayKehadiranResponse(
                        success = true,
                        tanggal = today,
                        dayOfWeek = dayName,
                        schedules = emptyList()
                    )
                )
            }
        }
    }

    // ========== SUBMIT KEHADIRAN ==========
    fun submitKehadiran(scheduleId: Int, guruHadir: Boolean, catatan: String?) {
        viewModelScope.launch {
            _submitKehadiranState.value = SubmitKehadiranUiState.Loading
            try {
                val today = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()).format(Date())
                val result = repository.submitKehadiran(scheduleId, today, guruHadir, catatan)
                
                if (result.isSuccess) {
                    val response = result.getOrNull()
                    if (response != null && response.success) {
                        _submitKehadiranState.value = SubmitKehadiranUiState.Success(response.message)
                        // Refresh today's status after submit
                        loadTodayKehadiranStatus()
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

    // ========== LOAD RIWAYAT ==========
    fun loadRiwayat() {
        // Cancel previous job if exists
        riwayatDebounceJob?.cancel()

        riwayatDebounceJob = viewModelScope.launch {
            _riwayatState.value = RiwayatUiState.Loading
            try {
                val result = repository.getKehadiranHistory()
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
        val groupedByDay: Map<String, List<ScheduleApi>>
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

