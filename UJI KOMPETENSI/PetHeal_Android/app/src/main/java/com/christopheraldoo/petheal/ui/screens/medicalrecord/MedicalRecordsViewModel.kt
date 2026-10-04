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
    val selectedFilter: String = "all",
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

    private var hasLoadedAllRecords = false
    private var lastLoadedPetId: Int? = null
    private var lastLoadedRecordId: Int? = null

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
    // Stable filter keys (never shown). Labels are Indonesian via [filterLabel].
    val filterCategories = listOf("all", "vaccination", "checkup", "surgery", "lab")

    fun filterLabel(key: String): String = when (key) {
        "vaccination" -> "Vaksinasi"
        "checkup" -> "Pemeriksaan"
        "surgery" -> "Operasi"
        "lab" -> "Hasil Lab"
        else -> "Semua"
    }

    fun loadRecords(forceRefresh: Boolean = false) {
        if (!forceRefresh && hasLoadedAllRecords && _listState.value.records.isNotEmpty()) {
            return
        }
        viewModelScope.launch {
            val hasCachedRecords = _listState.value.records.isNotEmpty()
            if (!hasCachedRecords || forceRefresh) {
                _listState.value = _listState.value.copy(isLoading = true, error = null)
            } else {
                _listState.value = _listState.value.copy(error = null)
            }
            when (val result = medicalRecordRepository.getMedicalRecords(forceRefresh = forceRefresh)) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    val records = result.data
                    hasLoadedAllRecords = true
                    lastLoadedPetId = null
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

    fun loadRecordsByPet(petId: Int, forceRefresh: Boolean = false) {
        if (!forceRefresh && lastLoadedPetId == petId && _listState.value.records.isNotEmpty()) {
            return
        }
        viewModelScope.launch {
            val hasCachedRecords = _listState.value.records.isNotEmpty()
            if (!hasCachedRecords || forceRefresh) {
                _listState.value = _listState.value.copy(isLoading = true, error = null)
            } else {
                _listState.value = _listState.value.copy(error = null)
            }
            when (val result = medicalRecordRepository.getMedicalRecordsByPet(petId, forceRefresh = forceRefresh)) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    val records = result.data
                    lastLoadedPetId = petId
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

    fun loadRecord(id: Int, forceRefresh: Boolean = false) {
        if (!forceRefresh && lastLoadedRecordId == id && _detailState.value.record?.id == id) {
            return
        }
        viewModelScope.launch {
            val hasCachedRecord = _detailState.value.record?.id == id
            if (!hasCachedRecord || forceRefresh) {
                _detailState.value = _detailState.value.copy(isLoading = true, error = null)
            } else {
                _detailState.value = _detailState.value.copy(error = null)
            }
            when (val result = medicalRecordRepository.getMedicalRecord(id, forceRefresh = forceRefresh)) {
                is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                    lastLoadedRecordId = id
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
        if (filter == "all") return records
        return records.filter { record ->
            val diagnosis = record.diagnosis?.lowercase() ?: ""
            val treatment = record.treatment?.lowercase() ?: ""
            val notes = record.notes?.lowercase() ?: ""
            // Keywords cover both Indonesian backend content and legacy English.
            when (filter) {
                "vaccination" -> listOf("vaksin", "vaccin", "imun").any { diagnosis.contains(it) || treatment.contains(it) || notes.contains(it) }
                "checkup"     -> listOf("periksa", "check", "exam", "kontrol", "konsult").any { diagnosis.contains(it) || treatment.contains(it) || notes.contains(it) }
                "surgery"     -> listOf("operasi", "surg", "operation", "bedah").any { diagnosis.contains(it) || treatment.contains(it) || notes.contains(it) }
                "lab"         -> listOf("lab", "darah", "blood", "test", "uji").any { diagnosis.contains(it) || treatment.contains(it) || notes.contains(it) }
                else          -> true
            }
        }
    }
}
