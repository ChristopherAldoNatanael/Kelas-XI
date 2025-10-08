package com.christopheraldoo.adminwafeoffood.dashboard.viewmodel

import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.adminwafeoffood.dashboard.model.DashboardStatistics
import com.christopheraldoo.adminwafeoffood.dashboard.repository.DashboardRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.launch

class DashboardViewModel : ViewModel() {
    
    private val repository = DashboardRepository()
    
    private val _dashboardState = MutableStateFlow(DashboardState())
    val dashboardState: StateFlow<DashboardState> = _dashboardState.asStateFlow()
    
    companion object {
        private const val TAG = "DashboardViewModel"
    }
      fun loadDashboardData() {
        viewModelScope.launch {
            try {
                Log.d(TAG, "Loading dashboard data...")
                _dashboardState.value = DashboardState(isLoading = true)
                
                val result = repository.getDashboardStatistics()
                if (result.isSuccess) {
                    val statistics = result.getOrNull()
                    if (statistics != null) {
                        Log.d(TAG, "Dashboard data loaded successfully")
                        _dashboardState.value = DashboardState(
                            isLoading = false,
                            statistics = statistics
                        )
                    } else {
                        _dashboardState.value = DashboardState(
                            isLoading = false,
                            error = "No data received"
                        )
                    }
                } else {
                    val exception = result.exceptionOrNull()
                    Log.e(TAG, "Error loading dashboard data", exception)
                    _dashboardState.value = DashboardState(
                        isLoading = false,
                        error = "Error loading dashboard: ${exception?.message}"
                    )
                }
                    
            } catch (e: Exception) {
                Log.e(TAG, "Unexpected error loading dashboard", e)
                _dashboardState.value = DashboardState(
                    isLoading = false,
                    error = "Unexpected error: ${e.message}"
                )
            }
        }
    }
    
    fun refreshData() {
        loadDashboardData()
    }
}

data class DashboardState(
    val isLoading: Boolean = false,
    val statistics: DashboardStatistics? = null,
    val error: String? = null
)