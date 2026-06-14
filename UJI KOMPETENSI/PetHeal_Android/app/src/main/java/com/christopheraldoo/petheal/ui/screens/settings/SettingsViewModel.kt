package com.christopheraldoo.petheal.ui.screens.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class SettingsUiState(
    val authProvider: String? = null,
    val userEmail: String? = null,
    val isSendingReset: Boolean = false,
    val resetMessage: String? = null,
    val error: String? = null
)

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val preferencesManager: PreferencesManager,
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                authProvider = preferencesManager.authProvider.first(),
                userEmail = preferencesManager.userEmail.first()
            )
        }
    }

    fun sendPasswordReset(email: String) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isSendingReset = true,
                resetMessage = null,
                error = null
            )

            when (val result = authRepository.requestForgotPassword(email.trim())) {
                is Result.Success -> _uiState.value = _uiState.value.copy(
                    isSendingReset = false,
                    resetMessage = "Reset code sent to $email"
                )
                is Result.Error -> _uiState.value = _uiState.value.copy(
                    isSendingReset = false,
                    error = result.message
                )
                else -> Unit
            }
        }
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(resetMessage = null, error = null)
    }
}
