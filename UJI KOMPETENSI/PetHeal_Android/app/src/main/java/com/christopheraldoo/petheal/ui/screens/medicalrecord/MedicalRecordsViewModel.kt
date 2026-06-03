package com.christopheraldoo.petheal.ui.screens.medicalrecord

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.model.Pet
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

// ── UI States ─────────────────────────────────────────────────────────────────

data class MedicalRecordsUiState(
    val isLoading: Boolean = false,
    val records: List<MedicalRecord> = emptyList(),
    val filteredRecords: List<MedicalRecord> = emptyList(),
    val selectedFilter: String = "All",
    val error: String? = null
)

data class MedicalRecordDetailUiState(
    val isLoading: Boolean = false,
    val record: MedicalRecord? = null,
    val error: String? = null
)

@HiltViewModel
class MedicalRecordsViewModel @Inject constructor(
    private val medicalRecordRepository: MedicalRecordRepository,
    private val petRepository: PetRepository
) : ViewModel() {

    // ── List state ────────────────────────────────────────────────────────────
    private val _listState = MutableStateFlow(MedicalRecordsUiState())
    val listState: StateFlow<MedicalRecordsUiState> = _listState.asStateFlow()

    // ── Detail state ──────────────────────────────────────────────────────────
    private val _detailState = MutableStateFlow(MedicalRecordDetailUiState())
    val detailState: StateFlow<MedicalRecordDetailUiState> = _detailState.asStateFlow()

    // ── Pets (for pet name lookup) ────────────────────────────────────────────
    private val _pets = MutableStateFlow<List<Pet>>(emptyList())
    val pets: StateFlow<List<Pet>> = _pets.asStateFlow()

    // ── Available filter categories ───────────────────────────────────────────
    val filterCategories = listOf("All", "Vaccinations", "Checkups", "Surgeries", "Lab Results")

    fun loadRecords() {
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isLoading = true, error = null)
            when (val result = medicalRecordRepository.getMedicalRecords()) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    val records = result.data
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        records = records,
                        filteredRecords = applyFilter(records, _listState.value.selectedFilter)
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Error -> {
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Loading -> {
                    // Already handled by isLoading = true above
                }
            }
        }
    }

    fun loadRecordsByPet(petId: Int) {
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isLoading = true, error = null)
            when (val result = medicalRecordRepository.getMedicalRecordsByPet(petId)) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    val records = result.data
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        records = records,
                        filteredRecords = applyFilter(records, _listState.value.selectedFilter)
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Error -> {
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Loading -> {
                    // Already handled by isLoading = true above
                }
            }
        }
    }

    fun loadRecord(id: Int) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true, error = null)
            when (val result = medicalRecordRepository.getMedicalRecord(id)) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false,
                        record = result.data
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Error -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false,
                        error = result.message
                    )
                }
                is com.christopheraldoo.petheal.data.repository.Result.Loading -> {
                    // Already handled by isLoading = true above
                }
            }
        }
    }

    fun loadPets() {
        viewModelScope.launch {
            when (val result = petRepository.getPets()) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    _pets.value = result.data
                }
                else -> { /* ignore */ }
            }
        }
    }

    fun setFilter(filter: String) {
        val filtered = applyFilter(_listState.value.records, filter)
        _listState.value = _listState.value.copy(
            selectedFilter = filter,
            filteredRecords = filtered
        )
    }

    private fun applyFilter(records: List<MedicalRecord>, filter: String): List<MedicalRecord> {
        if (filter == "All") return records
        return records.filter { record ->
            val diagnosis = record.diagnosis?.lowercase() ?: ""
            val treatment = record.treatment?.lowercase() ?: ""
            val notes = record.notes?.lowercase() ?: ""
            when (filter) {
                "Vaccinations" -> diagnosis.contains("vaccin") || treatment.contains("vaccin") || notes.contains("vaccin")
                "Checkups"     -> diagnosis.contains("check") || treatment.contains("check") || notes.contains("exam") || notes.contains("check")
                "Surgeries"    -> diagnosis.contains("surg") || treatment.contains("surg") || notes.contains("surg") || treatment.contains("operation")
                "Lab Results"  -> diagnosis.contains("lab") || treatment.contains("lab") || notes.contains("blood") || notes.contains("test")
                else           -> true
            }
        }
    }
}
