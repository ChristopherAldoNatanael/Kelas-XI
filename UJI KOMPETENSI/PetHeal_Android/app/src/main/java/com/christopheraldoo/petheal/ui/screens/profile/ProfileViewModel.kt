package com.christopheraldoo.petheal.ui.screens.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.User
import com.christopheraldoo.petheal.data.repository.AuthRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ProfileUiState(
    val user: User? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
    // PHASE 7: delete-account flow (Profile → danger zone).
    val isDeleting: Boolean = false,
    val isDeleted: Boolean = false,
    // Upload foto profil via badge kamera.
    val isUploadingPhoto: Boolean = false,
    val photoMessage: String? = null,
    // OTP konfirmasi email: hapus akun & ganti email.
    val deleteOtp: OtpFlowState = OtpFlowState(),
    val emailOtp: OtpFlowState = OtpFlowState(),
    val emailChangeMessage: String? = null
)

/**
 * Status satu alur OTP (kirim kode → verifikasi).
 *
 * [sentTo] adalah alamat tersamarkan dari server (mis. `uj****@mail.com`),
 * bukan email mentah — UI tidak perlu tahu alamat lengkap.
 */
data class OtpFlowState(
    val isSending: Boolean = false,
    val isVerifying: Boolean = false,
    val sentTo: String? = null,
    val expiresInMinutes: Int = 10,
    val mailSent: Boolean = true,
    val isVerified: Boolean = false,
    val error: String? = null
)

data class EditProfileUiState(
    val name: String = "",
    val email: String = "",
    val phone: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val isSuccess: Boolean = false
)

@HiltViewModel
class ProfileViewModel @Inject constructor(
    private val authRepository: AuthRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _profileState = MutableStateFlow(ProfileUiState())
    val profileState: StateFlow<ProfileUiState> = _profileState.asStateFlow()

    private val _editState = MutableStateFlow(EditProfileUiState())
    val editState: StateFlow<EditProfileUiState> = _editState.asStateFlow()

    init {
        loadProfile()
    }

    fun loadProfile() {
        viewModelScope.launch {
            // ── Step 1: Show cached DataStore values immediately ──────────────
            val localName  = preferencesManager.userName.first()?.trim() ?: ""
            val localEmail = preferencesManager.userEmail.first()?.trim() ?: ""
            val localPhoto = preferencesManager.userPhoto.first()

            if (localName.isNotBlank() || localEmail.isNotBlank()) {
                _profileState.value = ProfileUiState(
                    user = User(
                        name  = localName.ifBlank { null },
                        email = localEmail.ifBlank { null },
                        photo = localPhoto
                    ),
                    isLoading = true
                )
                _editState.value = EditProfileUiState(
                    name  = localName,
                    email = localEmail
                )
            } else {
                _profileState.value = ProfileUiState(isLoading = true)
            }

            // ── Step 2: Refresh from API ──────────────────────────────────────
            when (val result = authRepository.getProfile()) {
                is Result.Success -> {
                    val user = result.data
                    preferencesManager.saveUserInfo(
                        userId = user.id    ?: 0,
                        email  = user.email ?: "",
                        name   = user.name  ?: "",
                        photo  = user.photo
                    )
                    _profileState.value = ProfileUiState(user = user, isLoading = false)
                    _editState.value = EditProfileUiState(
                        name  = user.name  ?: localName,
                        email = user.email ?: localEmail,
                        phone = user.phone ?: ""
                    )
                }
                is Result.Error -> {
                    // Keep cached user, just clear loading
                    _profileState.value = _profileState.value.copy(
                        isLoading = false,
                        error     = result.message
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(isLoading = false)
                }
            }
        }
    }

    fun onNameChange(value: String) {
        _editState.value = _editState.value.copy(name = value, error = null)
    }

    fun onPhoneChange(value: String) {
        _editState.value = _editState.value.copy(phone = value, error = null)
    }

    fun updateProfile() {
        val currentName = _editState.value.name
        val phone = _editState.value.phone.trim()
        viewModelScope.launch {
            _editState.value = _editState.value.copy(isLoading = true, error = null, isSuccess = false)
            when (val result = authRepository.updateProfile(currentName, phone.ifBlank { null })) {
                is Result.Success -> {
                    val user = result.data
                    preferencesManager.saveUserInfo(
                        userId = user.id ?: 0,
                        email = user.email ?: "",
                        name = user.name ?: currentName,
                        photo = user.photo
                    )
                    _profileState.value = _profileState.value.copy(user = user)
                    _editState.value = _editState.value.copy(
                        isLoading = false,
                        isSuccess = true,
                        name = user.name ?: currentName,
                        phone = user.phone ?: phone
                    )
                }
                is Result.Error -> {
                    _editState.value = _editState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                else -> _editState.value = _editState.value.copy(isLoading = false)
            }
        }
    }

    fun logout(fcmToken: String? = null, onLoggedOut: () -> Unit = {}) {
        viewModelScope.launch {
            // Wait for the server logout (and local cache invalidation) to
            // finish BEFORE navigating — otherwise the splash / login screen
            // can fire its first request with a still-valid token and bounce
            // straight back to Home on a race.
            authRepository.logout(fcmToken)
            onLoggedOut()
        }
    }

    /**
     * Langkah 1 hapus akun: minta kode ke email akun. Akun belum berubah
     * sampai kode diverifikasi dan penghapusan dikonfirmasi.
     */
    fun requestDeleteOtp() {
        viewModelScope.launch {
            _profileState.value = _profileState.value.copy(
                deleteOtp = OtpFlowState(isSending = true)
            )
            when (val result = authRepository.requestDeleteAccountOtp()) {
                is Result.Success -> {
                    val body = result.data
                    _profileState.value = _profileState.value.copy(
                        deleteOtp = OtpFlowState(
                            sentTo = body.data?.email,
                            expiresInMinutes = body.data?.expiresInMinutes ?: 10,
                            mailSent = body.mailSent ?: true
                        )
                    )
                }
                is Result.Error -> {
                    _profileState.value = _profileState.value.copy(
                        deleteOtp = OtpFlowState(error = result.message)
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(deleteOtp = OtpFlowState())
                }
            }
        }
    }

    /**
     * Langkah 2 hapus akun: verifikasi kode lalu langsung hapus. Digabung agar
     * user tidak perlu menekan tombol kedua dalam jendela 15 menit dan agar
     * kode yang sudah terverifikasi tidak menggantung.
     */
    fun verifyDeleteOtpAndDelete(code: String, onDeleted: () -> Unit = {}) {
        viewModelScope.launch {
            _profileState.value = _profileState.value.copy(
                isDeleting = true,
                isDeleted = false,
                deleteOtp = _profileState.value.deleteOtp.copy(
                    isVerifying = true,
                    error = null
                )
            )

            val verification = authRepository.verifyDeleteAccountOtp(code)
            if (verification is Result.Error) {
                _profileState.value = _profileState.value.copy(
                    isDeleting = false,
                    deleteOtp = _profileState.value.deleteOtp.copy(
                        isVerifying = false,
                        error = verification.message
                    )
                )
                return@launch
            }

            when (val result = authRepository.deleteAccountAndLogout()) {
                is Result.Success -> {
                    _profileState.value = _profileState.value.copy(
                        isDeleting = false,
                        isDeleted = true,
                        deleteOtp = OtpFlowState(isVerified = true)
                    )
                    onDeleted()
                }
                is Result.Error -> {
                    // Kode bisa kedaluwarsa (jendela 15 menit) atau data
                    // berubah di server; tampilkan pesan asli backend.
                    _profileState.value = _profileState.value.copy(
                        isDeleting = false,
                        deleteOtp = _profileState.value.deleteOtp.copy(
                            isVerifying = false,
                            error = result.message
                        )
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(isDeleting = false)
                }
            }
        }
    }

    /** Tutup alur OTP hapus akun (dipanggil saat dialog ditutup). */
    fun resetDeleteOtp() {
        _profileState.value = _profileState.value.copy(deleteOtp = OtpFlowState())
    }

    /** Langkah 1 ganti email: kirim kode ke alamat baru. */
    fun requestEmailChangeOtp(email: String) {
        viewModelScope.launch {
            _profileState.value = _profileState.value.copy(
                emailChangeMessage = null,
                emailOtp = OtpFlowState(isSending = true)
            )
            when (val result = authRepository.requestEmailChangeOtp(email)) {
                is Result.Success -> {
                    val body = result.data
                    _profileState.value = _profileState.value.copy(
                        emailOtp = OtpFlowState(
                            sentTo = body.data?.email,
                            expiresInMinutes = body.data?.expiresInMinutes ?: 10,
                            mailSent = body.mailSent ?: true
                        )
                    )
                }
                is Result.Error -> {
                    _profileState.value = _profileState.value.copy(
                        emailOtp = OtpFlowState(error = result.message)
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(emailOtp = OtpFlowState())
                }
            }
        }
    }

    /** Langkah 2 ganti email: verifikasi kode, lalu simpan alamat baru. */
    fun verifyEmailChangeOtp(email: String, code: String) {
        viewModelScope.launch {
            _profileState.value = _profileState.value.copy(
                emailOtp = _profileState.value.emailOtp.copy(
                    isVerifying = true,
                    error = null
                )
            )
            when (val result = authRepository.verifyEmailChangeOtp(email, code)) {
                is Result.Success -> {
                    val user = result.data
                    preferencesManager.saveUserInfo(
                        userId = user.id ?: 0,
                        email = user.email ?: email,
                        name = user.name ?: _editState.value.name,
                        photo = user.photo
                    )
                    val current = _profileState.value.user
                    _profileState.value = _profileState.value.copy(
                        user = current?.copy(email = user.email) ?: user,
                        emailChangeMessage = "Alamat email berhasil diperbarui.",
                        emailOtp = _profileState.value.emailOtp.copy(
                            isVerifying = false,
                            isVerified = true
                        )
                    )
                    _editState.value = _editState.value.copy(
                        email = user.email ?: email,
                        error = null
                    )
                }
                is Result.Error -> {
                    _profileState.value = _profileState.value.copy(
                        emailOtp = _profileState.value.emailOtp.copy(
                            isVerifying = false,
                            error = result.message
                        )
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(
                        emailOtp = _profileState.value.emailOtp.copy(isVerifying = false)
                    )
                }
            }
        }
    }

    /** Tutup alur OTP ganti email tanpa menghapus pesan sukses di layar. */
    fun resetEmailChangeOtp() {
        _profileState.value = _profileState.value.copy(emailOtp = OtpFlowState())
    }

    fun clearEmailChangeMessage() {
        _profileState.value = _profileState.value.copy(emailChangeMessage = null)
    }

    fun clearEditSuccess() {
        _editState.value = _editState.value.copy(isSuccess = false)
    }

    /** Upload foto profil yang sudah dikompres (≤4096KB) di sisi UI. */
    fun uploadProfilePhoto(photoFile: java.io.File) {
        viewModelScope.launch {
            _profileState.value = _profileState.value.copy(
                isUploadingPhoto = true, error = null, photoMessage = null
            )
            when (val result = authRepository.uploadProfilePhoto(photoFile)) {
                is Result.Success -> {
                    _profileState.value = _profileState.value.copy(
                        user = result.data,
                        isUploadingPhoto = false,
                        photoMessage = "Foto profil berhasil diperbarui"
                    )
                }
                is Result.Error -> {
                    _profileState.value = _profileState.value.copy(
                        isUploadingPhoto = false,
                        error = result.message
                    )
                }
                else -> {
                    _profileState.value = _profileState.value.copy(isUploadingPhoto = false)
                }
            }
        }
    }

    fun clearPhotoMessage() {
        _profileState.value = _profileState.value.copy(photoMessage = null)
    }

    fun clearError() {
        _profileState.value = _profileState.value.copy(
            error = null,
            deleteOtp = _profileState.value.deleteOtp.copy(error = null),
            emailOtp = _profileState.value.emailOtp.copy(error = null)
        )
        _editState.value = _editState.value.copy(error = null)
    }
}
