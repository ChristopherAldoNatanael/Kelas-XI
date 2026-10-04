package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CompleteSetupUiState(
    val isLoading: Boolean = false,
    val isDone: Boolean = false,
    val error: String? = null
)

/**
 * PHASE 7: repairs Google accounts that were born without a clinic.
 *
 * Such accounts get 403 on everything, so the only correct repair within
 * the frozen backend contract is: drop the empty account, then register
 * again bound to the chosen clinic. Email/password accounts cannot be
 * repaired this way (password unknown) — they are directed to support.
 */
@HiltViewModel
class CompleteSetupViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(CompleteSetupUiState())
    val uiState: StateFlow<CompleteSetupUiState> = _uiState.asStateFlow()

    val authProvider: Flow<String?> = preferencesManager.authProvider

    fun complete(slug: String) {
        viewModelScope.launch {
            _uiState.value = CompleteSetupUiState(isLoading = true)
            when (val result = authRepository.reregisterGoogleAccount(slug)) {
                is Result.Success -> _uiState.value = CompleteSetupUiState(isDone = true)
                is Result.Error -> _uiState.value = CompleteSetupUiState(error = result.message)
                else -> Unit
            }
        }
    }

    fun logout(onDone: () -> Unit) {
        viewModelScope.launch {
            val fcm = runCatching { preferencesManager.fcmToken.first() }.getOrNull()
            authRepository.logout(fcm)
            onDone()
        }
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}
