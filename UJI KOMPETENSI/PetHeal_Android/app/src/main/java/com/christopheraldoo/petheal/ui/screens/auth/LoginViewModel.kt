package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

data class LoginUiState(
    val isLoading: Boolean = false,
    val isSuccess: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun loginWithEmailPassword(email: String, password: String) {
        viewModelScope.launch {
            _uiState.value = LoginUiState(isLoading = true)
            // Fetch the current FCM token — never send null so the server
            // always has an up-to-date device token for push notifications.
            val fcmToken = try {
                FirebaseMessaging.getInstance().token.await()
            } catch (e: Exception) { null }
            when (val result = authRepository.loginWithEmailPassword(email, password, fcmToken)) {
                is Result.Success -> _uiState.value = LoginUiState(isSuccess = true)
                is Result.Error  -> _uiState.value = LoginUiState(error = result.message)
                else             -> Unit
            }
        }
    }

    fun loginWithGoogleIdToken(idToken: String) {
        viewModelScope.launch {
            _uiState.value = LoginUiState(isLoading = true)
            val fcmToken = try {
                FirebaseMessaging.getInstance().token.await()
            } catch (e: Exception) { null }
            when (val result = authRepository.loginWithGoogle(idToken, fcmToken)) {
                is Result.Success -> _uiState.value = LoginUiState(isSuccess = true)
                is Result.Error  -> _uiState.value = LoginUiState(error = result.message)
                else             -> Unit
            }
        }
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    fun setError(message: String) {
        _uiState.value = LoginUiState(isLoading = false, error = message)
    }
}
