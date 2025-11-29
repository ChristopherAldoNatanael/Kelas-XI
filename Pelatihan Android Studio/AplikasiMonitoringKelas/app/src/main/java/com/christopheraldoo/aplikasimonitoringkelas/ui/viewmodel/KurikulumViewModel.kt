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
 * KurikulumViewModel - Manages state for Kurikulum role screens
 * Implements MVVM architecture with auto-refresh capability
 */
class KurikulumViewModel(private val repository: NetworkRepository) : ViewModel() {
    
    companion object {
        private const val TAG = "KurikulumViewModel"
        private const val AUTO_REFRESH_INTERVAL = 30000L // 30 seconds
    }
    
    // ===============================================
    // STATE MANAGEMENT
    // ===============================================
    
    // Dashboard State
    private val _dashboardState = MutableStateFlow<DashboardUiState>(DashboardUiState.Loading)
    val dashboardState: StateFlow<DashboardUiState> = _dashboardState.asStateFlow()
    
    // Class Management State
    private val _classManagementState = MutableStateFlow<ClassManagementUiState>(ClassManagementUiState.Loading)
    val classManagementState: StateFlow<ClassManagementUiState> = _classManagementState.asStateFlow()
    
    // History State
    private val _historyState = MutableStateFlow<HistoryUiState>(HistoryUiState.Loading)
    val historyState: StateFlow<HistoryUiState> = _historyState.asStateFlow()
    
    // Statistics State
    private val _statisticsState = MutableStateFlow<StatisticsUiState>(StatisticsUiState.Loading)
    val statisticsState: StateFlow<StatisticsUiState> = _statisticsState.asStateFlow()
    
    // Substitute Teachers State
    private val _substituteState = MutableStateFlow<SubstituteUiState>(SubstituteUiState.Idle)
    val substituteState: StateFlow<SubstituteUiState> = _substituteState.asStateFlow()
    
    // Filter Data
    private val _filterClasses = MutableStateFlow<List<FilterClass>>(emptyList())
    val filterClasses: StateFlow<List<FilterClass>> = _filterClasses.asStateFlow()
    
    private val _filterTeachers = MutableStateFlow<List<FilterTeacher>>(emptyList())
    val filterTeachers: StateFlow<List<FilterTeacher>> = _filterTeachers.asStateFlow()
    
    // Current filters
    private val _selectedDay = MutableStateFlow<String?>(null)
    val selectedDay: StateFlow<String?> = _selectedDay.asStateFlow()
    
    private val _selectedClassId = MutableStateFlow<Int?>(null)
    val selectedClassId: StateFlow<Int?> = _selectedClassId.asStateFlow()
    
    private val _selectedStatus = MutableStateFlow<String?>(null)
    val selectedStatus: StateFlow<String?> = _selectedStatus.asStateFlow()
    
    // Auto-refresh job
    private var autoRefreshJob: Job? = null
    
    // ===============================================
    // DASHBOARD FUNCTIONS
    // ===============================================
    
    fun loadDashboard(day: String? = null, classId: Int? = null, subjectId: Int? = null) {
        viewModelScope.launch {
            _dashboardState.value = DashboardUiState.Loading
            try {
                val response = repository.getKurikulumDashboard(day, classId, subjectId)
                if (response.success) {
                    // Check if requires class filter
                    if (response.requiresClassFilter) {
                        _dashboardState.value = DashboardUiState.RequiresClassFilter(
                            date = response.date,
                            day = response.day,
                            availableClasses = response.availableClasses ?: emptyList()
                        )
                    } else {
                        _dashboardState.value = DashboardUiState.Success(
                            date = response.date,
                            day = response.day,
                            stats = response.stats,
                            schedules = response.data,
                            groupedByClass = response.groupedByClass ?: emptyMap()
                        )
                    }
                } else {
                    _dashboardState.value = DashboardUiState.Error(response.message ?: "Gagal memuat dashboard")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading dashboard", e)
                _dashboardState.value = DashboardUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun startAutoRefresh() {
        stopAutoRefresh()
        autoRefreshJob = viewModelScope.launch {
            while (isActive) {
                delay(AUTO_REFRESH_INTERVAL)
                refreshCurrentScreen()
            }
        }
    }
    
    fun stopAutoRefresh() {
        autoRefreshJob?.cancel()
        autoRefreshJob = null
    }
    
    private fun refreshCurrentScreen() {
        // Refresh based on current state
        when (_dashboardState.value) {
            is DashboardUiState.Success -> loadDashboard(_selectedDay.value, _selectedClassId.value)
            else -> {}
        }
        when (_classManagementState.value) {
            is ClassManagementUiState.Success -> loadClassManagement(_selectedStatus.value)
            else -> {}
        }
    }
    
    // ===============================================
    // CLASS MANAGEMENT FUNCTIONS
    // ===============================================
    
    fun loadClassManagement(status: String? = null) {
        viewModelScope.launch {
            _classManagementState.value = ClassManagementUiState.Loading
            try {
                val response = repository.getKurikulumClasses(status)
                if (response.success) {
                    _classManagementState.value = ClassManagementUiState.Success(
                        date = response.date,
                        day = response.day,
                        currentTime = response.currentTime,
                        statusCounts = response.statusCounts,
                        alertClasses = response.alertClasses,
                        classes = response.data
                    )
                } else {
                    _classManagementState.value = ClassManagementUiState.Error(response.message ?: "Gagal memuat data kelas")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading class management", e)
                _classManagementState.value = ClassManagementUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun loadAvailableSubstitutes(period: Int, subjectId: Int? = null) {
        viewModelScope.launch {
            _substituteState.value = SubstituteUiState.Loading
            try {
                val response = repository.getAvailableSubstitutes(period, subjectId)
                if (response.success) {
                    _substituteState.value = SubstituteUiState.Success(response.data)
                } else {
                    _substituteState.value = SubstituteUiState.Error(response.message ?: "Gagal memuat guru pengganti")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading substitutes", e)
                _substituteState.value = SubstituteUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun assignSubstitute(scheduleId: Int, substituteTeacherId: Int, keterangan: String? = null) {
        viewModelScope.launch {
            _substituteState.value = SubstituteUiState.Assigning
            try {
                val request = AssignSubstituteRequest(scheduleId, substituteTeacherId, keterangan)
                val response = repository.assignSubstitute(request)
                if (response.success) {
                    _substituteState.value = SubstituteUiState.AssignSuccess(response.data!!)
                    // Refresh class management after successful assignment
                    loadClassManagement(_selectedStatus.value)
                } else {
                    _substituteState.value = SubstituteUiState.Error(response.message ?: "Gagal menugaskan guru pengganti")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error assigning substitute", e)
                _substituteState.value = SubstituteUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun resetSubstituteState() {
        _substituteState.value = SubstituteUiState.Idle
    }
    
    // ===============================================
    // HISTORY FUNCTIONS
    // ===============================================
    
    private var currentHistoryPage = 1
    private var hasMoreHistory = true
    private val historyItems = mutableListOf<KurikulumAttendanceHistoryItem>()
    
    fun loadHistory(
        dateFrom: String? = null,
        dateTo: String? = null,
        teacherId: Int? = null,
        classId: Int? = null,
        status: String? = null,
        refresh: Boolean = false
    ) {
        if (refresh) {
            currentHistoryPage = 1
            hasMoreHistory = true
            historyItems.clear()
        }
        
        if (!hasMoreHistory) return
        
        viewModelScope.launch {
            if (historyItems.isEmpty()) {
                _historyState.value = HistoryUiState.Loading
            }
            
            try {
                val response = repository.getKurikulumHistory(
                    page = currentHistoryPage,
                    dateFrom = dateFrom,
                    dateTo = dateTo,
                    teacherId = teacherId,
                    classId = classId,
                    status = status
                )
                
                if (response.success) {
                    historyItems.addAll(response.data)
                    hasMoreHistory = currentHistoryPage < response.pagination.lastPage
                    currentHistoryPage++
                    
                    _historyState.value = HistoryUiState.Success(
                        items = historyItems.toList(),
                        pagination = response.pagination,
                        hasMore = hasMoreHistory
                    )
                } else {
                    _historyState.value = HistoryUiState.Error(response.message ?: "Gagal memuat riwayat")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading history", e)
                _historyState.value = HistoryUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun loadMoreHistory(
        dateFrom: String? = null,
        dateTo: String? = null,
        teacherId: Int? = null,
        classId: Int? = null,
        status: String? = null
    ) {
        loadHistory(dateFrom, dateTo, teacherId, classId, status, refresh = false)
    }
    
    // ===============================================
    // STATISTICS FUNCTIONS
    // ===============================================
    
    fun loadStatistics(month: Int? = null, year: Int? = null, teacherId: Int? = null) {
        viewModelScope.launch {
            _statisticsState.value = StatisticsUiState.Loading
            try {
                val response = repository.getKurikulumStatistics(month, year, teacherId)
                if (response.success) {
                    _statisticsState.value = StatisticsUiState.Success(
                        statistics = response.statistics,
                        dailyBreakdown = response.dailyBreakdown ?: emptyMap(),
                        teacherStats = response.teacherStatistics ?: emptyList()
                    )
                } else {
                    _statisticsState.value = StatisticsUiState.Error(response.message ?: "Gagal memuat statistik")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading statistics", e)
                _statisticsState.value = StatisticsUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    // ===============================================
    // EXPORT FUNCTIONS
    // ===============================================
    
    private val _exportState = MutableStateFlow<ExportUiState>(ExportUiState.Idle)
    val exportState: StateFlow<ExportUiState> = _exportState.asStateFlow()
    
    fun exportData(
        dateFrom: String? = null,
        dateTo: String? = null,
        teacherId: Int? = null,
        classId: Int? = null
    ) {
        viewModelScope.launch {
            _exportState.value = ExportUiState.Exporting
            try {
                val response = repository.exportAttendance(dateFrom, dateTo, teacherId, classId)
                if (response.success) {
                    _exportState.value = ExportUiState.Success(response.data, response.totalRecords)
                } else {
                    _exportState.value = ExportUiState.Error(response.message ?: "Gagal export data")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error exporting data", e)
                _exportState.value = ExportUiState.Error(e.message ?: "Terjadi kesalahan")
            }
        }
    }
    
    fun resetExportState() {
        _exportState.value = ExportUiState.Idle
    }
    
    // ===============================================
    // FILTER DATA FUNCTIONS
    // ===============================================
    
    fun loadFilterData() {
        viewModelScope.launch {
            try {
                val classesResponse = repository.getFilterClasses()
                if (classesResponse.success) {
                    _filterClasses.value = classesResponse.data
                }
                
                val teachersResponse = repository.getFilterTeachers()
                if (teachersResponse.success) {
                    _filterTeachers.value = teachersResponse.data
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading filter data", e)
            }
        }
    }
    
    // ===============================================
    // FILTER SETTERS
    // ===============================================
    
    fun setSelectedDay(day: String?) {
        _selectedDay.value = day
    }
    
    fun setSelectedClassId(classId: Int?) {
        _selectedClassId.value = classId
    }
    
    fun setSelectedStatus(status: String?) {
        _selectedStatus.value = status
    }
    
    // ===============================================
    // CLEANUP
    // ===============================================
    
    override fun onCleared() {
        super.onCleared()
        stopAutoRefresh()
    }
}

// ===============================================
// UI STATE CLASSES
// ===============================================

sealed class DashboardUiState {
    object Loading : DashboardUiState()
    data class RequiresClassFilter(
        val date: String,
        val day: String,
        val availableClasses: List<AvailableClass>
    ) : DashboardUiState()
    data class Success(
        val date: String,
        val day: String,
        val stats: DashboardStats,
        val schedules: List<ScheduleOverview>,
        val groupedByClass: Map<String, List<ScheduleOverview>>
    ) : DashboardUiState()
    data class Error(val message: String) : DashboardUiState()
}

sealed class ClassManagementUiState {
    object Loading : ClassManagementUiState()
    data class Success(
        val date: String,
        val day: String,
        val currentTime: String,
        val statusCounts: StatusCounts,
        val alertClasses: List<ClassScheduleItem>,
        val classes: List<ClassScheduleItem>
    ) : ClassManagementUiState()
    data class Error(val message: String) : ClassManagementUiState()
}

sealed class HistoryUiState {
    object Loading : HistoryUiState()
    data class Success(
        val items: List<KurikulumAttendanceHistoryItem>,
        val pagination: PaginationInfo,
        val hasMore: Boolean
    ) : HistoryUiState()
    data class Error(val message: String) : HistoryUiState()
}

sealed class StatisticsUiState {
    object Loading : StatisticsUiState()
    data class Success(
        val statistics: MonthlyStats,
        val dailyBreakdown: Map<String, DailyStats>,
        val teacherStats: List<TeacherStats>
    ) : StatisticsUiState()
    data class Error(val message: String) : StatisticsUiState()
}

sealed class SubstituteUiState {
    object Idle : SubstituteUiState()
    object Loading : SubstituteUiState()
    object Assigning : SubstituteUiState()
    data class Success(val teachers: List<SubstituteTeacher>) : SubstituteUiState()
    data class AssignSuccess(val result: AssignmentResult) : SubstituteUiState()
    data class Error(val message: String) : SubstituteUiState()
}

sealed class ExportUiState {
    object Idle : ExportUiState()
    object Exporting : ExportUiState()
    data class Success(val data: List<ExportItem>, val totalRecords: Int) : ExportUiState()
    data class Error(val message: String) : ExportUiState()
}
