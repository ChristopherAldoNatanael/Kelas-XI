package com.christopheraldoo.petheal.ui.screens.doctor

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.Doctor
import com.christopheraldoo.petheal.data.model.TimeSlot
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.data.model.Pet
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import javax.inject.Inject

data class DoctorsUiState(
    val doctors: List<Doctor> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val searchQuery: String = ""
) {
    val filtered: List<Doctor>
        get() = if (searchQuery.isBlank()) doctors
                else doctors.filter {
                    it.name?.contains(searchQuery, ignoreCase = true) == true ||
                    it.specialization?.contains(searchQuery, ignoreCase = true) == true
                }
}

data class DoctorDetailUiState(
    val doctor: Doctor? = null,
    val pets: List<Pet> = emptyList(),
    val slots: List<TimeSlot> = emptyList(),
    val selectedDate: String = LocalDate.now().plusDays(1)
        .format(DateTimeFormatter.ofPattern("yyyy-MM-dd")),
    val isLoading: Boolean = false,
    val isSlotsLoading: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class DoctorsViewModel @Inject constructor(
    private val doctorRepository: DoctorRepository,
    private val petRepository: PetRepository,
    private val preferencesManager: PreferencesManager
) : ViewModel() {

    private val _listState = MutableStateFlow(DoctorsUiState())
    val listState: StateFlow<DoctorsUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(DoctorDetailUiState())
    val detailState: StateFlow<DoctorDetailUiState> = _detailState.asStateFlow()

    init {
        loadDoctors()
    }

    fun loadDoctors() {
        viewModelScope.launch {
            // Tahap 1: tampilkan cache langsung (jika ada) — UI sudah isi data dalam milidetik
            when (val cached = doctorRepository.getDoctors(forceRefresh = false)) {
                is Result.Success -> {
                    _listState.value = _listState.value.copy(
                        doctors = cached.data,
                        isLoading = cached.data.isEmpty() // loading hanya jika cache kosong
                    )
                    // Jika cache sudah ada, lanjut refresh background tanpa blocking UI
                    if (cached.data.isNotEmpty()) {
                        refreshDoctorsInBackground()
                        return@launch
                    }
                }
                else -> _listState.value = _listState.value.copy(isLoading = true)
            }

            // Tahap 2: fetch dari network (hanya jika cache kosong)
            when (val result = doctorRepository.getDoctors(forceRefresh = true)) {
                is Result.Success -> _listState.value = _listState.value.copy(
                    doctors = result.data, isLoading = false
                )
                is Result.Error -> _listState.value = _listState.value.copy(
                    isLoading = false, error = result.message
                )
                else -> _listState.value = _listState.value.copy(isLoading = false)
            }
        }
    }

    /** Refresh diam-diam di background tanpa mengubah loading state */
    private fun refreshDoctorsInBackground() {
        viewModelScope.launch {
            when (val result = doctorRepository.getDoctors(forceRefresh = true)) {
                is Result.Success -> {
                    if (result.data.isNotEmpty()) {
                        _listState.value = _listState.value.copy(doctors = result.data)
                    }
                }
                else -> Unit // Biarkan — cache lama tetap tampil
            }
        }
    }

    fun onSearchChange(q: String) {
        _listState.value = _listState.value.copy(searchQuery = q)
    }    fun loadDoctorDetail(doctorId: Int) {
        viewModelScope.launch {
            _detailState.value = DoctorDetailUiState(isLoading = true)

            // Load doctor info (akan pakai cache per-ID jika ada — instan)
            when (val result = doctorRepository.getDoctor(doctorId)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    doctor = result.data, isLoading = false
                )
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = result.message
                )
                else -> _detailState.value = _detailState.value.copy(isLoading = false)
            }

            // Load user pets (parallel — tidak blocking)
            when (val result = petRepository.getPets()) {
                is Result.Success -> _detailState.value = _detailState.value.copy(pets = result.data)
                else -> Unit
            }

            // Load slots untuk tanggal default
            loadSlots(doctorId, _detailState.value.selectedDate)
        }
    }

    fun onDateSelected(doctorId: Int, date: String) {
        _detailState.value = _detailState.value.copy(selectedDate = date, slots = emptyList())
        loadSlots(doctorId, date)
    }

    private fun loadSlots(doctorId: Int, date: String) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isSlotsLoading = true)
            when (val result = doctorRepository.getDoctorSlots(doctorId, date)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    slots = result.data, isSlotsLoading = false
                )
                is Result.Error   -> _detailState.value = _detailState.value.copy(
                    isSlotsLoading = false
                )
                else -> _detailState.value = _detailState.value.copy(isSlotsLoading = false)
            }
        }
    }

    fun clearError() {
        _listState.value = _listState.value.copy(error = null)
        _detailState.value = _detailState.value.copy(error = null)
    }
}
