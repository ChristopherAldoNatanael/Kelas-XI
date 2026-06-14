package com.christopheraldoo.petheal.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Booking
import com.christopheraldoo.petheal.data.model.DashboardData
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.Vaccination
import com.christopheraldoo.petheal.data.remote.ApiService
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class HomeUiState(
    val userName: String = "",
    val userPhoto: String? = null,
    val upcomingBooking: Booking? = null,
    val isLoading: Boolean = true,
    val isBookingLoading: Boolean = false,
    val unreadNotificationCount: Int = 0,
    val totalPets: Int = 0,
    val activePets: Int = 0,
    val pendingBookings: Int = 0,
    val confirmedBookings: Int = 0,
    val paymentAttentionCount: Int = 0,
    val outstandingAmount: Double = 0.0,
    val totalVisits: Int = 0,
    val totalSpent: Double = 0.0,
    val followUpDueCount: Int = 0,
    val dueVaccinationCount: Int = 0,
    val overdueVaccinationCount: Int = 0,
    val recentVisits: List<MedicalRecord> = emptyList(),
    val dueSoonVaccinations: List<Vaccination> = emptyList(),
    val dashboardSummary: String = ""
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val apiService: ApiService,
    private val preferencesManager: PreferencesManager,
    private val authRepository: AuthRepository,
    private val notificationRepository: NotificationRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()

    init {
        // 1. Observe DataStore userName live — updates UI the moment it changes (INSTANT)
        viewModelScope.launch {
            preferencesManager.userName.collect { name ->
                if (!name.isNullOrBlank()) {
                    _uiState.value = _uiState.value.copy(
                        userName = name.trim(),
                        isLoading = false // Cache loaded, stop global loading
                    )
                } else if (_uiState.value.isLoading) {
                    // If still loading and name is null, keep loading
                } else {
                    // If loaded and name is null, stop loading
                    _uiState.value = _uiState.value.copy(isLoading = false)
                }
            }
        }
        // ... (photo observer) ...
        viewModelScope.launch {
            preferencesManager.userPhoto.collect { photo ->
                _uiState.value = _uiState.value.copy(userPhoto = photo)
            }
        }
        // ... (notification observer) ...
        viewModelScope.launch {
            notificationRepository.unreadCount.collect { count ->
                _uiState.value = _uiState.value.copy(unreadNotificationCount = count)
            }
        }
        
        loadBookingData()
    }

    fun loadBookingData() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isBookingLoading = true)

            // Fetch fresh profile (in background to update cache) + upcoming bookings
            val profileDeferred = async {
                try {
                    val result = authRepository.getProfile()
                    if (result is Result.Success) {
                        val user = result.data
                        preferencesManager.saveUserInfo(
                            userId = user.id ?: 0,
                            email = user.email ?: "",
                            name = user.name ?: "",
                            photo = user.photo
                        )
                    }
                } catch (e: Exception) { /* silent */ }
            }

            val dashboardDeferred = async {
                try {
                    val response = apiService.getDashboard()
                    if (response.isSuccessful) response.body()?.data else null
                } catch (e: Exception) { null }
            }

            profileDeferred.await()
            val dashboard = dashboardDeferred.await()

            if (dashboard != null) {
                _uiState.value = _uiState.value.copy(
                    upcomingBooking = dashboard.bookings.upcoming.firstOrNull(),
                    totalPets = dashboard.pets.total,
                    activePets = dashboard.pets.active,
                    pendingBookings = dashboard.bookings.pending,
                    confirmedBookings = dashboard.bookings.confirmed,
                    paymentAttentionCount = dashboard.payments.attentionCount,
                    outstandingAmount = dashboard.payments.outstandingAmount,
                    totalVisits = dashboard.medical.totalVisits,
                    totalSpent = dashboard.medical.totalSpent,
                    followUpDueCount = dashboard.medical.followUpDue,
                    dueVaccinationCount = dashboard.vaccinationAlerts.dueSoon.size,
                    overdueVaccinationCount = dashboard.vaccinationAlerts.overdue.size,
                    recentVisits = dashboard.medical.recentVisits,
                    dueSoonVaccinations = dashboard.vaccinationAlerts.dueSoon,
                    dashboardSummary = dashboard.summary,
                    isBookingLoading = false
                )
            } else {
                _uiState.value = _uiState.value.copy(isBookingLoading = false)
            }
        }
    }

    // Keep loadHomeData for manual refresh (pull-to-refresh)
    fun refresh() {
        loadBookingData()
    }
}
