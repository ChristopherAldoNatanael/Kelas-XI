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
    
    // Pending Attendance State
    private val _pendingState = MutableStateFlow<PendingAttendanceUiState>(PendingAttendanceUiState.Loading)
    val pendingState: StateFlow<PendingAttendanceUiState> = _pendingState.asStateFlow()
    
    // Confirm Attendance State
    private val _confirmState = MutableStateFlow<ConfirmAttendanceUiState>(ConfirmAttendanceUiState.Idle)
    val confirmState: StateFlow<ConfirmAttendanceUiState> = _confirmState.asStateFlow()
    
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
    
    // Week offset: 0 = minggu ini, -1 = minggu lalu, dst.
    private val _weekOffset = MutableStateFlow(0)
    val weekOffset: StateFlow<Int> = _weekOffset.asStateFlow()
    
    // Auto-refresh job
    private var autoRefreshJob: Job? = null
    
    // ===============================================
    // DASHBOARD FUNCTIONS
    // ===============================================
    
    fun setWeekOffset(offset: Int) {
        _weekOffset.value = offset
    }
    
    fun loadDashboard(day: String? = null, classId: Int? = null, subjectId: Int? = null, forceRefresh: Boolean = false) {
        viewModelScope.launch {
            _dashboardState.value = DashboardUiState.Loading
            try {
                val response = repository.getKurikulumDashboard(
                    day = day,
                    classId = classId,
                    subjectId = subjectId,
                    weekOffset = _weekOffset.value,
                    forceRefresh = forceRefresh
                )
                if (response.success) {
                    // Check if requires class filter
                    if (response.requiresClassFilter) {
                        _dashboardState.value = DashboardUiState.RequiresClassFilter(
                            date = response.date,
                            day = response.day,
                            availableClasses = response.availableClasses ?: emptyList(),
                            weekInfo = response.weekInfo
                        )
                    } else {
                        _dashboardState.value = DashboardUiState.Success(
                            date = response.targetDate ?: response.date,
                            day = response.day,
                            stats = response.stats,
                            schedules = response.data,
                            groupedByClass = response.groupedByClass ?: emptyMap(),
                            weekInfo = response.weekInfo,
                            isFutureDate = response.isFutureDate
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
                        summary = response.summary,
                        statusCounts = response.statusCounts,
                        alertClasses = response.alertClasses,
                        groupedByClass = response.groupedByClass ?: emptyList(),
                        presentTeachersByPeriod = response.presentTeachersByPeriod ?: emptyList(),
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
    // PENDING ATTENDANCE FUNCTIONS
    // ===============================================
      fun loadPendingAttendances(date: String? = null) {
        viewModelScope.launch {
            _pendingState.value = PendingAttendanceUiState.Loading
            try {
                val response = repository.getPendingAttendances(date)
                if (response.success && response.data != null) {
                    val data = response.data
                    val groupedList = data.groupedByClass ?: emptyList()
                    val allPendingList = data.allPending ?: emptyList()
                    
                    if (data.totalPending > 0 || groupedList.isNotEmpty()) {
                        _pendingState.value = PendingAttendanceUiState.Success(
                            date = data.date,
                            day = data.day,
                            currentTime = data.currentTime,
                            totalPending = data.totalPending,
                            belumLaporCount = data.belumLaporCount,
                            pendingCount = data.pendingCount,
                            groupedByClass = groupedList,
                            allPending = allPendingList
                        )
                    } else {
                        _pendingState.value = PendingAttendanceUiState.Empty(
                            date = data.date,
                            day = data.day
                        )
                    }
                } else {
                    _pendingState.value = PendingAttendanceUiState.Error(
                        response.message ?: "Gagal memuat data pending"
                    )
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error loading pending attendances", e)
                _pendingState.value = PendingAttendanceUiState.Error(
                    e.message ?: "Terjadi kesalahan"
                )
            }
        }
    }
    
    /**
     * Set attendance status for a schedule
     * Works for both:
     * - schedules without attendance (belum_lapor) - uses scheduleId
     * - schedules with pending status - uses attendanceId
     */    fun setAttendanceStatus(
        scheduleId: Int? = null,
        attendanceId: Int? = null, 
        status: String, 
        keterangan: String? = null,
        date: String? = null
    ) {
        viewModelScope.launch {
            Log.d(TAG, "setAttendanceStatus called: scheduleId=$scheduleId, attendanceId=$attendanceId, status=$status, date=$date")
            _confirmState.value = ConfirmAttendanceUiState.Confirming
            try {
                val request = ConfirmAttendanceRequest(
                    attendanceId = attendanceId,
                    scheduleId = scheduleId,
                    status = status,
                    keterangan = keterangan,
                    date = date
                )
                Log.d(TAG, "Sending confirm request: $request")
                val response = repository.confirmAttendance(request)
                Log.d(TAG, "Confirm response: success=${response.success}, message=${response.message}")
                if (response.success) {
                    _confirmState.value = ConfirmAttendanceUiState.Success(
                        response.message ?: "Kehadiran berhasil disimpan"
                    )
                    // Reload pending list
                    loadPendingAttendances()
                } else {
                    _confirmState.value = ConfirmAttendanceUiState.Error(
                        response.message ?: "Gagal menyimpan kehadiran"
                    )
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error confirming attendance", e)
                _confirmState.value = ConfirmAttendanceUiState.Error(
                    e.message ?: "Terjadi kesalahan"
                )
            }
        }
    }
    
    fun bulkConfirmAttendance(items: List<PendingAttendanceItem>, status: String) {
        viewModelScope.launch {
            _confirmState.value = ConfirmAttendanceUiState.Confirming
            try {
                // Separate items into those with attendance records and those without
                val itemsWithAttendance = items.filter { it.id != null }
                val itemsWithoutAttendance = items.filter { it.id == null }
                
                val attendanceIds = itemsWithAttendance.mapNotNull { it.id }
                val scheduleItems = itemsWithoutAttendance.map { 
                    BulkConfirmScheduleItem(scheduleId = it.scheduleId, date = it.date)
                }
                
                Log.d(TAG, "bulkConfirmAttendance: ${attendanceIds.size} with attendance, ${scheduleItems.size} without")
                
                val request = BulkConfirmRequest(
                    attendanceIds = attendanceIds.ifEmpty { null },
                    scheduleItems = scheduleItems.ifEmpty { null },
                    status = status
                )
                val response = repository.bulkConfirmAttendance(request)
                if (response.success) {
                    _confirmState.value = ConfirmAttendanceUiState.Success(
                        response.message ?: "Kehadiran berhasil dikonfirmasi"
                    )
                    // Reload pending list
                    loadPendingAttendances()
                } else {
                    _confirmState.value = ConfirmAttendanceUiState.Error(
                        response.message ?: "Gagal mengkonfirmasi kehadiran"
                    )
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error bulk confirming attendance", e)
                _confirmState.value = ConfirmAttendanceUiState.Error(
                    e.message ?: "Terjadi kesalahan"
                )
            }
        }
    }
    
    fun resetConfirmState() {
        _confirmState.value = ConfirmAttendanceUiState.Idle
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
        val availableClasses: List<AvailableClass>,
        val weekInfo: WeekInfo? = null
    ) : DashboardUiState()
    data class Success(
        val date: String,
        val day: String,
        val stats: DashboardStats,
        val schedules: List<ScheduleOverview>,
        val groupedByClass: Map<String, List<ScheduleOverview>>,
        val weekInfo: WeekInfo? = null,
        val isFutureDate: Boolean = false
    ) : DashboardUiState()
    data class Error(val message: String) : DashboardUiState()
}

sealed class ClassManagementUiState {
    object Loading : ClassManagementUiState()
    data class Success(
        val date: String,
        val day: String,
        val currentTime: String,
        val summary: ClassManagementSummary?,
        val statusCounts: StatusCounts,
        val alertClasses: List<ClassScheduleItem>,
        val groupedByClass: List<ClassGroup>,
        val presentTeachersByPeriod: List<PeriodTeacherInfo>,
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

sealed class PendingAttendanceUiState {
    object Loading : PendingAttendanceUiState()
    data class Success(
        val date: String,
        val day: String,
        val currentTime: String? = null,
        val totalPending: Int,
        val belumLaporCount: Int = 0,
        val pendingCount: Int = 0,
        val groupedByClass: List<PendingClassGroup> = emptyList(),
        val allPending: List<PendingAttendanceItem> = emptyList() // Optional - may be empty
    ) : PendingAttendanceUiState()
    data class Empty(val date: String, val day: String) : PendingAttendanceUiState()
    data class Error(val message: String) : PendingAttendanceUiState()
}

sealed class ConfirmAttendanceUiState {
    object Idle : ConfirmAttendanceUiState()
    object Confirming : ConfirmAttendanceUiState()
    data class Success(val message: String) : ConfirmAttendanceUiState()
    data class Error(val message: String) : ConfirmAttendanceUiState()
}
