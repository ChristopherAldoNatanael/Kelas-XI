package com.christopheraldoo.petheal.ui.screens.notification

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.AppNotification
import com.christopheraldoo.petheal.data.repository.NotificationRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch
import javax.inject.Inject

data class NotificationsUiState(
    val notifications: List<AppNotification> = emptyList(),
    val unreadCount: Int = 0,
    val isLoading: Boolean = false
)

@HiltViewModel
class NotificationsViewModel @Inject constructor(
    private val notificationRepository: NotificationRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(NotificationsUiState(isLoading = true))
    val uiState: StateFlow<NotificationsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            notificationRepository.notifications.collect { list ->
                _uiState.value = _uiState.value.copy(
                    notifications = list,
                    isLoading     = false
                )
            }
        }
        viewModelScope.launch {
            notificationRepository.unreadCount.collect { count ->
                _uiState.value = _uiState.value.copy(unreadCount = count)
            }
        }
    }

    fun markAllRead() {
        viewModelScope.launch { notificationRepository.markAllRead() }
    }

    fun markRead(id: String) {
        viewModelScope.launch { notificationRepository.markRead(id) }
    }

    fun clearAll() {
        viewModelScope.launch { notificationRepository.clearAll() }
    }
}
