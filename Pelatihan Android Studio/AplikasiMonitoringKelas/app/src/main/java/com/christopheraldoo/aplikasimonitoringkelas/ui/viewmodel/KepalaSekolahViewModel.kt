package com.christopheraldoo.aplikasimonitoringkelas.ui.viewmodel

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.network.NetworkRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * KepalaSekolahViewModel - Manages state for Kepala Sekolah dashboard
 * Implements MVVM architecture with professional dashboard features
 */
class KepalaSekolahViewModel(private val repository: NetworkRepository) : ViewModel() {
    
    companion object {
        private const val TAG = "KepalaSekolahVM"
        private const val AUTO_REFRESH_INTERVAL = 60000L // 60 seconds
    }
    
    // ===============================================
    // STATE MANAGEMENT
    // ===============================================
    
    // Dashboard State
    private val _dashboardState = MutableStateFlow<KepsekDashboardUiState>(KepsekDashboardUiState.Loading)
    val dashboardState: StateFlow<KepsekDashboardUiState> = _dashboardState.asStateFlow()
    
    // Attendance List State
    private val _attendanceListState = MutableStateFlow<KepsekAttendanceListUiState>(KepsekAttendanceListUiState.Loading)
    val attendanceListState: StateFlow<KepsekAttendanceListUiState> = _attendanceListState.asStateFlow()
    
    // Teacher Performance State
    private val _teacherPerformanceState = MutableStateFlow<TeacherPerformanceUiState>(TeacherPerformanceUiState.Loading)
    val teacherPerformanceState: StateFlow<TeacherPerformanceUiState> = _teacherPerformanceState.asStateFlow()
    
    // Week offset: 0 = this week, -1 = last week, etc.
    private val _weekOffset = MutableStateFlow(0)
    val weekOffset: StateFlow<Int> = _weekOffset.asStateFlow()
    
    // Selected status filter
    private val _selectedStatus = MutableStateFlow<String?>(null)
    val selectedStatus: StateFlow<String?> = _selectedStatus.asStateFlow()
    
    // Current tab
    private val _selectedTab = MutableStateFlow(0)
    val selectedTab: StateFlow<Int> = _selectedTab.asStateFlow()
    
    // Auto-refresh job
    private var autoRefreshJob: Job? = null
    
    // ===============================================
    // INITIALIZER
    // ===============================================
    
    init {
        loadDashboard()
    }
    
    // ===============================================
    // DASHBOARD FUNCTIONS
    // ===============================================
    
    fun setWeekOffset(offset: Int) {
        _weekOffset.value = offset
        loadDashboard()
    }
    
    fun setSelectedTab(tab: Int) {
        _selectedTab.value = tab
    }
    
    fun setSelectedStatus(status: String?) {
        _selectedStatus.value = status
        loadAttendanceList()
    }
    
    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = KepsekDashboardUiState.Loading
            
            // Retry logic for network issues
            var lastException: Exception? = null
            for (attempt in 1..3) {
                try {
                    val response = repository.getKepsekDashboard(_weekOffset.value)
                    if (response.success && response.data != null) {
                        _dashboardState.value = KepsekDashboardUiState.Success(response.data)
                        Log.d(TAG, "Dashboard loaded: ${response.data.thisWeek.total} records")
                        return@launch // Success, exit
                    } else {
                        _dashboardState.value = KepsekDashboardUiState.Error(response.message)
                        Log.e(TAG, "Dashboard error: ${response.message}")
                        return@launch
                    }
                } catch (e: com.google.gson.JsonSyntaxException) {
                    Log.w(TAG, "JSON parse error attempt $attempt: ${e.message}")
                    lastException = e
                    if (attempt < 3) kotlinx.coroutines.delay(500L * attempt)
                } catch (e: java.io.EOFException) {
                    Log.w(TAG, "EOF error attempt $attempt: ${e.message}")
                    lastException = e
                    if (attempt < 3) kotlinx.coroutines.delay(500L * attempt)
                } catch (e: Exception) {
                    _dashboardState.value = KepsekDashboardUiState.Error(e.message ?: "Unknown error")
                    Log.e(TAG, "Dashboard exception", e)
                    return@launch
                }
            }
            
            // All retries failed
            _dashboardState.value = KepsekDashboardUiState.Error(
                "Gagal memuat data setelah 3 percobaan. ${lastException?.message ?: ""}"
            )
        }
    }
    
    fun loadAttendanceList(status: String? = null) {
        viewModelScope.launch {
            _attendanceListState.value = KepsekAttendanceListUiState.Loading
            try {
                val response = repository.getKepsekAttendances(
                    status = status ?: _selectedStatus.value,
                    weekOffset = _weekOffset.value
                )
                if (response.success && response.data != null) {
                    _attendanceListState.value = KepsekAttendanceListUiState.Success(response.data)
                    Log.d(TAG, "Attendance list loaded: ${response.data.attendances.size} records")
                } else {
                    _attendanceListState.value = KepsekAttendanceListUiState.Error(response.message)
                }
            } catch (e: Exception) {
                _attendanceListState.value = KepsekAttendanceListUiState.Error(e.message ?: "Unknown error")
                Log.e(TAG, "Attendance list exception", e)
            }
        }
    }
    
    fun loadTeacherPerformance(sortBy: String = "attendance_rate") {
        viewModelScope.launch {
            _teacherPerformanceState.value = TeacherPerformanceUiState.Loading
            try {
                val response = repository.getKepsekTeacherPerformance(_weekOffset.value, sortBy)
                if (response.success && response.data != null) {
                    _teacherPerformanceState.value = TeacherPerformanceUiState.Success(response.data)
                    Log.d(TAG, "Performance loaded: ${response.data.teachers.size} teachers")
                } else {
                    _teacherPerformanceState.value = TeacherPerformanceUiState.Error(response.message)
                }
            } catch (e: Exception) {
                _teacherPerformanceState.value = TeacherPerformanceUiState.Error(e.message ?: "Unknown error")
                Log.e(TAG, "Performance exception", e)
            }
        }
    }
    
    // ===============================================
    // AUTO-REFRESH
    // ===============================================
    
    fun startAutoRefresh() {
        autoRefreshJob?.cancel()
        autoRefreshJob = viewModelScope.launch {
            while (isActive) {
                delay(AUTO_REFRESH_INTERVAL)
                Log.d(TAG, "Auto-refreshing dashboard...")
                loadDashboard()
            }
        }
    }
    
    fun stopAutoRefresh() {
        autoRefreshJob?.cancel()
        autoRefreshJob = null
    }
    
    override fun onCleared() {
        super.onCleared()
        stopAutoRefresh()
    }
}

// ===============================================
// UI STATE CLASSES
// ===============================================

sealed class KepsekDashboardUiState {
    object Loading : KepsekDashboardUiState()
    data class Success(val data: KepalaSekolahDashboardData) : KepsekDashboardUiState()
    data class Error(val message: String) : KepsekDashboardUiState()
}

sealed class KepsekAttendanceListUiState {
    object Loading : KepsekAttendanceListUiState()
    data class Success(val data: AttendanceListData) : KepsekAttendanceListUiState()
    data class Error(val message: String) : KepsekAttendanceListUiState()
}

sealed class TeacherPerformanceUiState {
    object Loading : TeacherPerformanceUiState()
    data class Success(val data: TeacherPerformanceData) : TeacherPerformanceUiState()
    data class Error(val message: String) : TeacherPerformanceUiState()
}
