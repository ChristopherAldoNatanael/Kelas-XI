package com.christopheraldoo.petheal.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Clinic
import com.christopheraldoo.petheal.data.repository.ClinicRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ClinicPickerUiState(
    val isLoading: Boolean = false,
    val clinics: List<Clinic> = emptyList(),
    val selectedSlug: String? = null,
    val error: String? = null
)

/**
 * PHASE 7: tenant picker shared by Register (required) and Login (change).
 * Selection persists via [ClinicRepository]; the backend login response
 * refreshes it authoritatively afterwards.
 */
@HiltViewModel
class ClinicPickerViewModel @Inject constructor(
    private val clinicRepository: ClinicRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _uiState = MutableStateFlow(ClinicPickerUiState())
    val uiState: StateFlow<ClinicPickerUiState> = _uiState.asStateFlow()

    fun load(selectedSlug: String? = null) {
        viewModelScope.launch {
            val stored = selectedSlug
                ?: runCatching { preferencesManager.clinicSlug.first() }.getOrNull()
            _uiState.value = ClinicPickerUiState(isLoading = true, selectedSlug = stored)
            when (val result = clinicRepository.getClinics()) {
                is Result.Success -> _uiState.value = ClinicPickerUiState(
                    clinics = result.data, selectedSlug = stored
                )
                is Result.Error -> _uiState.value = ClinicPickerUiState(
                    selectedSlug = stored, error = result.message
                )
                else -> Unit
            }
        }
    }

    fun select(clinic: Clinic, onSelected: (Clinic) -> Unit = {}) {
        viewModelScope.launch {
            clinicRepository.selectClinic(clinic)
            _uiState.value = _uiState.value.copy(selectedSlug = clinic.slug)
            onSelected(clinic)
        }
    }
}
