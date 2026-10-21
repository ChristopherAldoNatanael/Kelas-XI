package com.christopheraldoo.petheal.ui.screens.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.repository.ClinicRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.util.ClinicContact
import com.christopheraldoo.petheal.util.buildClinicHelpMessage
import com.christopheraldoo.petheal.util.buildClinicReportMessage
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State halaman Bantuan & Dukungan.
 *
 * Kontak bersumber dari Clinic Profile lewat [ClinicRepository], sama seperti
 * sheet Bantuan di alur booking — jadi admin hanya mengelola nomor di satu
 * tempat dan seluruh aplikasi ikut berubah.
 */
data class HelpSupportUiState(
    val contact: ClinicContact? = null,
    val helpMessage: String = "",
    val reportMessage: String = "",
    val isLoading: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class HelpSupportViewModel @Inject constructor(
    private val clinicRepository: ClinicRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _state = MutableStateFlow(HelpSupportUiState())
    val state: StateFlow<HelpSupportUiState> = _state.asStateFlow()

    fun loadSupportContact(forceRefresh: Boolean = false) {
        if (_state.value.isLoading) return
        viewModelScope.launch {
            val userName = preferencesManager.userName.first()
            val clinicName = preferencesManager.clinicName.first()
            val helpMessage = buildClinicHelpMessage(
                clinicName = clinicName,
                userName = userName,
                doctorName = null,
                serviceName = null,
                date = null,
                time = null,
                topic = "layanan klinik"
            )
            val reportMessage = buildClinicReportMessage(clinicName, userName)

            val current = _state.value
            _state.value = current.copy(
                helpMessage = helpMessage,
                reportMessage = reportMessage,
                isLoading = current.contact == null,
                error = null
            )

            when (val result = clinicRepository.getSupportContact(forceRefresh)) {
                is Result.Success -> _state.value = _state.value.copy(
                    contact = result.data,
                    isLoading = false,
                    error = null
                )
                is Result.Error -> _state.value = _state.value.copy(
                    isLoading = false,
                    error = result.message.ifBlank { "Kontak klinik belum bisa dimuat." }
                )
                else -> _state.value = _state.value.copy(isLoading = false)
            }
        }
    }
}
