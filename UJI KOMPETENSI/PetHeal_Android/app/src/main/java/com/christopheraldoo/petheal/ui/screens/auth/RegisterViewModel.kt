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

data class RegisterUiState(
    val isLoading: Boolean = false,
    val isSuccess: Boolean = false,
    /**
     * Email tujuan kode OTP — non-null berarti kode terkirim dan layar harus
     * pindah ke [OtpVerificationScreen]. Akun belum dibuat sampai kode benar.
     */
    val otpSentEmail: String? = null,
    // PHASE 7: same safety net as login (defensive; email register requires
    // a slug upfront so this should rarely trigger).
    val needsClinicSetup: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(RegisterUiState())
    val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()

    /**
     * Langkah 1 pendaftaran email: minta kode OTP. Akun baru dibuat backend
     * setelah kode diverifikasi (lihat `OtpVerificationViewModel`).
     */
    fun requestOtp(name: String, email: String, password: String, clinicSlug: String? = null) {
        viewModelScope.launch {
            _uiState.value = RegisterUiState(isLoading = true)
            when (val result = authRepository.requestRegisterOtp(name, email, password, clinicSlug)) {
                is Result.Success ->
                    _uiState.value = RegisterUiState(otpSentEmail = result.data.email ?: email)
                is Result.Error  -> _uiState.value = RegisterUiState(error = result.message)
                else             -> Unit
            }
        }
    }

    fun registerWithGoogleIdToken(idToken: String, name: String, clinicSlug: String? = null) {
        viewModelScope.launch {
            _uiState.value = RegisterUiState(isLoading = true)
            val fcmToken = try { FirebaseMessaging.getInstance().token.await() } catch (e: Exception) { null }
            when (val result = authRepository.registerWithGoogle(idToken, name, null, fcmToken, clinicSlug)) {
                is Result.Success ->
                    if (result.data.user.clinic?.slug.isNullOrBlank()) {
                        _uiState.value = RegisterUiState(needsClinicSetup = true)
                    } else {
                        _uiState.value = RegisterUiState(isSuccess = true)
                    }
                is Result.Error  -> _uiState.value = RegisterUiState(error = result.message)
                else             -> Unit
            }
        }
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    /**
     * Dipanggil layar SETELAH pindah ke layar OTP. Tanpa ini, kembali dari
     * layar OTP ke form daftar akan langsung melempar user ke layar OTP lagi
     * (efek navigasi ikut jalan ulang saat form masuk komposisi kembali).
     */
    fun consumeOtpSent() {
        if (_uiState.value.otpSentEmail != null) {
            _uiState.value = _uiState.value.copy(otpSentEmail = null)
        }
    }

    fun setError(message: String) {
        _uiState.value = RegisterUiState(isLoading = false, error = message)
    }
}
