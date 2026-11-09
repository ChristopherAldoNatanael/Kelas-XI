package com.christopheraldoo.petheal.ui.screens.discovery

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.ClinicDetail
import com.christopheraldoo.petheal.data.repository.ClinicContextResolver
import com.christopheraldoo.petheal.data.repository.DiscoveryRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ClinicDetailUiState(
    val isLoading: Boolean = true,
    val error: String? = null,
    val clinic: ClinicDetail? = null,
    /** Nama klinik yang benar-benar dipakai saat booking (dari preferensi). */
    val activeClinicName: String? = null,
    /**
     * True bila klinik yang sedang dilihat BUKAN klinik aktif. Layar wajib
     * memberi peringatan eksplisit — booking tidak boleh "berpindah klinik"
     * diam-diam.
     */
    val isDifferentClinic: Boolean = false,
    val hasActiveClinic: Boolean = false
)

@HiltViewModel
class ClinicDetailViewModel @Inject constructor(
    savedStateHandle: SavedStateHandle,
    private val repository: DiscoveryRepository,
    private val clinicContext: ClinicContextResolver
) : ViewModel() {

    private val slug: String = savedStateHandle.get<String>("slug").orEmpty()

    private val _uiState = MutableStateFlow(ClinicDetailUiState())
    val uiState: StateFlow<ClinicDetailUiState> = _uiState.asStateFlow()

    init {
        load()
    }

    fun load() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)

            val active = clinicContext.activeClinic()

            when (val result = repository.loadClinicDetail(slug)) {
                is Result.Success -> _uiState.value = ClinicDetailUiState(
                    isLoading = false,
                    clinic = result.data,
                    activeClinicName = active.name,
                    hasActiveClinic = active.isSet,
                    isDifferentClinic = !active.isSet || active.slug != slug
                )
                is Result.Error -> _uiState.value = ClinicDetailUiState(
                    isLoading = false,
                    error = result.message,
                    activeClinicName = active.name,
                    hasActiveClinic = active.isSet,
                    isDifferentClinic = !active.isSet || active.slug != slug
                )
                else -> _uiState.value = ClinicDetailUiState(isLoading = false)
            }
        }
    }
}
