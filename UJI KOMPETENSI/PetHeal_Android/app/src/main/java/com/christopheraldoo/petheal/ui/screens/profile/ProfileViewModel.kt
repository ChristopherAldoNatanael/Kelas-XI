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

    fun logout(fcmToken: String? = null) {
        viewModelScope.launch {
            authRepository.logout(fcmToken)
        }
    }

    fun clearEditSuccess() {
        _editState.value = _editState.value.copy(isSuccess = false)
    }

    fun clearError() {
        _profileState.value = _profileState.value.copy(error = null)
        _editState.value = _editState.value.copy(error = null)
    }
}
