package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository
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
 * MULTI-CLINIC DISCOVERY (2026-11-09): layar ini TIDAK lagi menghapus akun.
 *
 * Sebelumnya akun Google tanpa klinik "diperbaiki" dengan menghapus akun lalu
 * mendaftar ulang (destruktif, dan menghapus hewan/booking pengguna). Sekarang
 * akun pasien global sah tanpa klinik, jadi langkah ini hanya menyimpan
 * preferensi klinik favorit (opsional) untuk mempercepat booking berikutnya.
 */
@HiltViewModel
class CompleteSetupViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val discoveryRepository: DiscoveryRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(CompleteSetupUiState())
    val uiState: StateFlow<CompleteSetupUiState> = _uiState.asStateFlow()

    val authProvider: Flow<String?> = preferencesManager.authProvider

    /**
     * Simpan klinik favorit (opsional) lalu selesai. Tidak ada akun yang
     * dihapus dan tidak ada token yang dibuang.
     */
    fun complete(slug: String, name: String? = null) {
        viewModelScope.launch {
            _uiState.value = CompleteSetupUiState(isLoading = true)
            preferencesManager.saveClinic(
                id = null,
                name = name,
                slug = slug,
                logoUrl = null,
                color = null,
                address = null
            )
            _uiState.value = CompleteSetupUiState(isDone = true)
        }
    }

    /** Langsung selesai tanpa memilih klinik — akun tetap valid. */
    fun skip() {
        _uiState.value = CompleteSetupUiState(isDone = true)
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
