package com.christopheraldoo.petheal.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Booking
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
    val isLoading: Boolean = true, // True until cache is loaded
    val isBookingLoading: Boolean = false, // True while fetching bookings
    val unreadNotificationCount: Int = 0
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
                if (photo != null) {
                    _uiState.value = _uiState.value.copy(userPhoto = photo)
                }
            }
        }
        // ... (notification observer) ...
        viewModelScope.launch {
            notificationRepository.unreadCount.collect { count ->
                _uiState.value = _uiState.value.copy(unreadNotificationCount = count)
            }
        }
        
        // Load Booking Data
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

            val bookingDeferred = async {
                try {
                    val response = apiService.getUpcomingBookings()
                    if (response.isSuccessful) response.body()?.data?.firstOrNull() else null
                } catch (e: Exception) { null }
            }

            profileDeferred.await() // Run in background
            val upcomingBooking = bookingDeferred.await()

            _uiState.value = _uiState.value.copy(
                upcomingBooking = upcomingBooking,
                isBookingLoading = false
            )
        }
    }

    // Keep loadHomeData for manual refresh (pull-to-refresh)
    fun refresh() {
        loadBookingData()
    }
}
