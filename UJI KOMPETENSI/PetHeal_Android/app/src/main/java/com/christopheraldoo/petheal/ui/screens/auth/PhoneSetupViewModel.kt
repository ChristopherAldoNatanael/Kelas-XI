package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.util.canonicalPhoneInput
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PhoneSetupUiState(
    val name: String = "",
    val email: String = "",
    val phone: String = "",
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isSaved: Boolean = false,
    /** Pesan untuk field nomor; `null` berarti belum ada yang salah. */
    val phoneError: String? = null,
    val error: String? = null
)

/**
 * Pengisian nomor WhatsApp untuk akun yang belum punya nomor.
 *
 * Muncul setelah masuk dengan Google, karena Google tidak pernah memberikan
 * nomor telepon. Nomor ini yang dipakai klinik saat perlu menghubungi pasien
 * (mis. pembatalan atau pengembalian dana), jadi formulirnya sengaja pendek:
 * nama (bisa dikoreksi) dan nomor WhatsApp.
 */
@HiltViewModel
class PhoneSetupViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(PhoneSetupUiState())
    val uiState: StateFlow<PhoneSetupUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            val cachedName = preferencesManager.userName.first().orEmpty()
            val cachedEmail = preferencesManager.userEmail.first().orEmpty()
            _uiState.value = _uiState.value.copy(
                name = cachedName,
                email = cachedEmail,
                isLoading = false
            )
            // Ambil versi server bila ada: nama yang tampil harus sama dengan
            // yang tercetak di struk dan rekam medis.
            when (val result = authRepository.getProfile()) {
                is Result.Success -> _uiState.value = _uiState.value.copy(
                    name = result.data.name ?: cachedName,
                    email = result.data.email ?: cachedEmail,
                    phone = result.data.phone.orEmpty(),
                    isLoading = false
                )
                else -> Unit
            }
        }
    }

    fun onNameChange(value: String) {
        _uiState.value = _uiState.value.copy(name = value, error = null)
    }

    fun onPhoneChange(value: String) {
        _uiState.value = _uiState.value.copy(phone = value, phoneError = null, error = null)
    }

    fun save() {
        val state = _uiState.value
        if (state.isSaving) return

        val phone = canonicalPhoneInput(state.phone)
        if (phone == null) {
            _uiState.value = state.copy(
                phoneError = "Nomor belum valid. Contoh: 0812 3456 7890."
            )
            return
        }

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isSaving = true, error = null)
            val name = _uiState.value.name.trim()
            when (val result = authRepository.updateProfile(name, phone)) {
                is Result.Success -> {
                    val user = result.data
                    preferencesManager.saveUserInfo(
                        userId = user.id ?: 0,
                        email = user.email ?: "",
                        name = user.name ?: name,
                        photo = user.photo
                    )
                    _uiState.value = _uiState.value.copy(
                        isSaving = false,
                        isSaved = true,
                        phone = user.phone ?: phone
                    )
                }
                is Result.Error -> _uiState.value = _uiState.value.copy(
                    isSaving = false,
                    error = result.message
                )
                else -> _uiState.value = _uiState.value.copy(isSaving = false)
            }
        }
    }
}
