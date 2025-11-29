package com.christopheraldoo.aplikasimonitoringkelas.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.aplikasimonitoringkelas.data.*
import com.christopheraldoo.aplikasimonitoringkelas.network.EnhancedApiService
import com.christopheraldoo.aplikasimonitoringkelas.util.SessionManager
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import android.content.Context
import android.util.Log

/**
 * Ultra Optimized ViewModel untuk Siswa dengan timeout protection dan circuit breaker
 */
class SiswaViewModel(
    private val context: Context,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val enhancedApiService = EnhancedApiService(context)

    // UI State untuk jadwal hari ini
    private val _jadwalHariIni = MutableStateFlow<UiState<List<JadwalHariIni>>>(UiState.Loading)
    val jadwalHariIni: StateFlow<UiState<List<JadwalHariIni>>> = _jadwalHariIni.asStateFlow()

    // UI State untuk riwayat kehadiran
    private val _riwayatKehadiran = MutableStateFlow<UiState<PaginatedData<List<RiwayatKehadiran>>>>(UiState.Loading)
    val riwayatKehadiran: StateFlow<UiState<PaginatedData<List<RiwayatKehadiran>>>> = _riwayatKehadiran.asStateFlow()

    // UI State untuk my schedule
    private val _mySchedule = MutableStateFlow<UiState<PaginatedData<List<Schedule>>>>(UiState.Loading)
    val mySchedule: StateFlow<UiState<PaginatedData<List<Schedule>>>> = _mySchedule.asStateFlow()

    // UI State untuk today status
    private val _todayStatus = MutableStateFlow<UiState<TodayStatusResponse>>(UiState.Loading)
    val todayStatus: StateFlow<UiState<TodayStatusResponse>> = _todayStatus.asStateFlow()

    // Loading states
    private val _isSubmittingKehadiran = MutableStateFlow(false)
    val isSubmittingKehadiran: StateFlow<Boolean> = _isSubmittingKehadiran.asStateFlow()

    companion object {
        private const val TAG = "SiswaViewModel"
    }

    /**
     * Load jadwal hari ini dengan ultra lightweight endpoint
     */
    fun loadJadwalHariIni() {
        viewModelScope.launch {
            try {
                _jadwalHariIni.value = UiState.Loading
                Log.d(TAG, "Loading jadwal hari ini...")

                val response = enhancedApiService.getJadwalHariIni()
                
                if (response.isSuccessful && response.body()?.success == true) {
                    val data = response.body()?.data ?: emptyList()
                    _jadwalHariIni.value = UiState.Success(data)
                    Log.d(TAG, "Jadwal hari ini loaded: ${data.size} items")
                } else {
                    val errorMsg = response.body()?.message ?: "Gagal memuat jadwal hari ini"
                    _jadwalHariIni.value = UiState.Error(errorMsg)
                    Log.w(TAG, "Failed to load jadwal: $errorMsg")
                }
            } catch (e: Exception) {
                val errorMsg = when (e) {
                    is com.christopheraldoo.aplikasimonitoringkelas.network.CircuitBreakerOpenException -> 
                        "Server sedang sibuk. Coba lagi dalam beberapa detik."
                    else -> "Koneksi bermasalah. Periksa jaringan Anda."
                }
                _jadwalHariIni.value = UiState.Error(errorMsg)
                Log.e(TAG, "Exception loading jadwal: ${e.message}")
            }
        }
    }

    /**
     * Load riwayat kehadiran dengan pagination
     */
    fun loadRiwayatKehadiran(page: Int = 1, limit: Int = 10, isLoadMore: Boolean = false) {
        viewModelScope.launch {
            try {
                if (!isLoadMore) {
                    _riwayatKehadiran.value = UiState.Loading
                }
                Log.d(TAG, "Loading riwayat kehadiran page $page...")

                val response = enhancedApiService.getRiwayatKehadiran(page, limit)
                
                if (response.isSuccessful && response.body()?.success == true) {
                    val paginatedResponse = response.body()?.data
                    if (paginatedResponse != null) {
                        val currentState: PaginatedData<List<RiwayatKehadiran>> = if (isLoadMore) {
                            when (val current = _riwayatKehadiran.value) {
                                is UiState.Success -> {
                                    val existingData = current.data.data.toMutableList()
                                    existingData.addAll(paginatedResponse.data)
                                    PaginatedData(
                                        data = existingData.toList(),
                                        pagination = paginatedResponse.pagination
                                    )
                                }
                                else -> PaginatedData(paginatedResponse.data, paginatedResponse.pagination)
                            }
                        } else {
                            PaginatedData(paginatedResponse.data, paginatedResponse.pagination)
                        }
                        
                        _riwayatKehadiran.value = UiState.Success(currentState)
                        Log.d(TAG, "Riwayat kehadiran loaded: ${paginatedResponse.data.size} items")
                    } else {
                        _riwayatKehadiran.value = UiState.Error("Data tidak valid")
                    }
                } else {
                    val errorMsg = response.body()?.message ?: "Gagal memuat riwayat kehadiran"
                    _riwayatKehadiran.value = UiState.Error(errorMsg)
                    Log.w(TAG, "Failed to load riwayat: $errorMsg")
                }
            } catch (e: Exception) {
                val errorMsg = when (e) {
                    is com.christopheraldoo.aplikasimonitoringkelas.network.CircuitBreakerOpenException -> 
                        "Server sedang sibuk. Coba lagi dalam beberapa detik."
                    else -> "Koneksi bermasalah. Periksa jaringan Anda."
                }
                _riwayatKehadiran.value = UiState.Error(errorMsg)
                Log.e(TAG, "Exception loading riwayat: ${e.message}")
            }
        }
    }

    /**
     * Load my schedule dengan timeout protection
     */
    fun loadMySchedule(page: Int = 1) {
        viewModelScope.launch {
            try {
                _mySchedule.value = UiState.Loading
                Log.d(TAG, "Loading my schedule page $page...")

                val response = enhancedApiService.getMySchedule(page)
                
                if (response.isSuccessful && response.body()?.success == true) {
                    val paginatedResponse = response.body()?.data
                    if (paginatedResponse != null) {
                        val paginatedData = PaginatedData(
                            data = paginatedResponse.data,
                            pagination = paginatedResponse.pagination
                        )
                        _mySchedule.value = UiState.Success(paginatedData)
                        Log.d(TAG, "My schedule loaded: ${paginatedResponse.data.size} items")
                    } else {
                        _mySchedule.value = UiState.Error("Data tidak valid")
                    }
                } else {
                    val errorMsg = response.body()?.message ?: "Gagal memuat jadwal pribadi"
                    _mySchedule.value = UiState.Error(errorMsg)
                    Log.w(TAG, "Failed to load my schedule: $errorMsg")
                }
            } catch (e: Exception) {
                val errorMsg = when (e) {
                    is com.christopheraldoo.aplikasimonitoringkelas.network.CircuitBreakerOpenException -> 
                        "Server sedang sibuk. Coba lagi dalam beberapa detik."
                    else -> "Koneksi bermasalah. Periksa jaringan Anda."
                }
                _mySchedule.value = UiState.Error(errorMsg)
                Log.e(TAG, "Exception loading my schedule: ${e.message}")
            }
        }
    }

    /**
     * Load today status
     */
    fun loadTodayStatus() {
        viewModelScope.launch {
            try {
                _todayStatus.value = UiState.Loading
                Log.d(TAG, "Loading today status...")

                val response = enhancedApiService.getTodayStatus()
                
                if (response.isSuccessful && response.body()?.success == true) {
                    val data = response.body()?.data
                    if (data != null) {
                        _todayStatus.value = UiState.Success(data)
                        Log.d(TAG, "Today status loaded: ${data.schedules.size} schedules")
                    } else {
                        _todayStatus.value = UiState.Error("Data tidak valid")
                    }
                } else {
                    val errorMsg = response.body()?.message ?: "Gagal memuat status hari ini"
                    _todayStatus.value = UiState.Error(errorMsg)
                    Log.w(TAG, "Failed to load today status: $errorMsg")
                }
            } catch (e: Exception) {
                val errorMsg = when (e) {
                    is com.christopheraldoo.aplikasimonitoringkelas.network.CircuitBreakerOpenException -> 
                        "Server sedang sibuk. Coba lagi dalam beberapa detik."
                    else -> "Koneksi bermasalah. Periksa jaringan Anda."
                }
                _todayStatus.value = UiState.Error(errorMsg)
                Log.e(TAG, "Exception loading today status: ${e.message}")
            }
        }
    }

    /**
     * Submit kehadiran
     */
    fun submitKehadiran(
        scheduleId: Int,
        tanggal: String,
        status: String,
        catatan: String? = null,
        onSuccess: () -> Unit,
        onError: (String) -> Unit
    ) {
        viewModelScope.launch {
            try {
                _isSubmittingKehadiran.value = true
                Log.d(TAG, "Submitting kehadiran for schedule $scheduleId...")

                val request = KehadiranRequest(
                    scheduleId = scheduleId,
                    tanggal = tanggal,
                    status = status,
                    catatan = catatan
                )

                val response = enhancedApiService.submitKehadiran(request)
                
                if (response.isSuccessful && response.body()?.success == true) {
                    onSuccess()
                    Log.d(TAG, "Kehadiran submitted successfully")
                    // Refresh today status after successful submission
                    loadTodayStatus()
                } else {
                    val errorMsg = response.body()?.message ?: "Gagal menyimpan kehadiran"
                    onError(errorMsg)
                    Log.w(TAG, "Failed to submit kehadiran: $errorMsg")
                }
            } catch (e: Exception) {
                val errorMsg = when (e) {
                    is com.christopheraldoo.aplikasimonitoringkelas.network.CircuitBreakerOpenException -> 
                        "Server sedang sibuk. Coba lagi dalam beberapa detik."
                    else -> "Koneksi bermasalah. Periksa jaringan Anda."
                }
                onError(errorMsg)
                Log.e(TAG, "Exception submitting kehadiran: ${e.message}")
            } finally {
                _isSubmittingKehadiran.value = false
            }
        }
    }

    /**
     * Reset circuit breaker (untuk recovery)
     */
    fun resetCircuitBreaker() {
        enhancedApiService.resetCircuitBreaker()
        Log.i(TAG, "Circuit breaker reset by user")
    }

    /**
     * Check if circuit breaker is open
     */
    fun isCircuitBreakerOpen(): Boolean = enhancedApiService.isCircuitBreakerOpen()
}

/**
 * UI State sealed class
 */
sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
}

/**
 * Paginated data wrapper
 */
data class PaginatedData<T>(
    val data: T,
    val pagination: PaginationMeta
)
