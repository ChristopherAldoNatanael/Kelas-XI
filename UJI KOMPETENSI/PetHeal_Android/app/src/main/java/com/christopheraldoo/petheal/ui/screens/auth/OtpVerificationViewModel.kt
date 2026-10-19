package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

data class OtpVerificationUiState(
    val isLoading: Boolean = false,
    /** Kode benar dan sesi sudah tersimpan → layar boleh lanjut ke Home. */
    val isVerified: Boolean = false,
    /** Akun dibuat tetapi belum terikat klinik (jaring pengaman, sama seperti login). */
    val needsClinicSetup: Boolean = false,
    val isResending: Boolean = false,
    /** Sisa detik sebelum tombol kirim ulang aktif (0 = boleh kirim ulang). */
    val resendCountdown: Int = 0,
    val resendSent: Boolean = false,
    val error: String? = null
)

/**
 * Verifikasi kode OTP pendaftaran email. Akun baru benar-benar dibuat di sini
 * (backend), jadi satu-satunya jalan lanjut ke Home adalah kode yang benar.
 */
@HiltViewModel
class OtpVerificationViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(OtpVerificationUiState())
    val uiState: StateFlow<OtpVerificationUiState> = _uiState.asStateFlow()

    private var countdownJob: Job? = null

    fun verify(email: String, code: String) {
        if (code.length != OTP_LENGTH) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            val fcmToken = try {
                FirebaseMessaging.getInstance().token.await()
            } catch (e: Exception) {
                null
            }

            when (val result = authRepository.verifyRegisterOtp(email, code, fcmToken)) {
                is Result.Success -> {
                    val bound = !result.data.user.clinic?.slug.isNullOrBlank()
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        isVerified = bound,
                        needsClinicSetup = !bound
                    )
                }
                is Result.Error ->
                    _uiState.value = _uiState.value.copy(isLoading = false, error = result.message)
                else ->
                    _uiState.value = _uiState.value.copy(isLoading = false)
            }
        }
    }

    /** Kirim ulang kode. Dibatasi hitung mundur supaya tidak menembak rate limit. */
    fun resend(email: String) {
        if (_uiState.value.resendCountdown > 0 || _uiState.value.isResending) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isResending = true, error = null, resendSent = false)

            when (val result = authRepository.resendRegisterOtp(email)) {
                is Result.Success -> {
                    _uiState.value = _uiState.value.copy(isResending = false, resendSent = true)
                    startCountdown()
                }
                is Result.Error ->
                    _uiState.value = _uiState.value.copy(isResending = false, error = result.message)
                else ->
                    _uiState.value = _uiState.value.copy(isResending = false)
            }
        }
    }

    fun startCountdown(seconds: Int = RESEND_COOLDOWN_SECONDS) {
        countdownJob?.cancel()
        countdownJob = viewModelScope.launch {
            var remaining = seconds
            while (remaining > 0) {
                _uiState.value = _uiState.value.copy(resendCountdown = remaining)
                delay(1_000)
                remaining--
            }
            _uiState.value = _uiState.value.copy(resendCountdown = 0)
        }
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }

    companion object {
        const val OTP_LENGTH = 6
        const val RESEND_COOLDOWN_SECONDS = 60
    }
}
