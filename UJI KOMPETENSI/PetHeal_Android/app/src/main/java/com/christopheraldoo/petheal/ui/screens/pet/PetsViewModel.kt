package com.christopheraldoo.petheal.ui.screens.pet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.PetRequest
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.io.File
import javax.inject.Inject

data class PetsUiState(
    val pets: List<Pet> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val searchQuery: String = ""
)

data class PetDetailUiState(
    val pet: Pet? = null,
    val medicalRecords: List<MedicalRecord> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,
    val isDeleted: Boolean = false
)

data class AddEditPetUiState(
    val isLoading: Boolean = false,
    val isSuccess: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class PetsViewModel @Inject constructor(
    private val petRepository: PetRepository,
    private val medicalRecordRepository: MedicalRecordRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PetsUiState())
    val uiState: StateFlow<PetsUiState> = _uiState.asStateFlow()

    private val _detailState = MutableStateFlow(PetDetailUiState())
    val detailState: StateFlow<PetDetailUiState> = _detailState.asStateFlow()

    private val _addEditState = MutableStateFlow(AddEditPetUiState())
    val addEditState: StateFlow<AddEditPetUiState> = _addEditState.asStateFlow()

    init {
        loadPets()
    }    fun loadPets(forceRefresh: Boolean = false) {
        viewModelScope.launch {
            // If we already have data and not forced, show cached instantly then refresh silently
            val hasCachedData = _uiState.value.pets.isNotEmpty()
            if (!hasCachedData || forceRefresh) {
                _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            }
            when (val result = petRepository.getPets()) {
                is Result.Success -> _uiState.value = _uiState.value.copy(
                    pets = result.data, isLoading = false, error = null
                )
                is Result.Error -> _uiState.value = _uiState.value.copy(
                    error = result.message, isLoading = false
                )
                else -> Unit
            }
        }
    }

    fun loadPetDetail(id: Int) {
        viewModelScope.launch {
            _detailState.value = PetDetailUiState(isLoading = true)
            
            // Load pet details
            when (val result = petRepository.getPet(id)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        pet = result.data,
                        isLoading = false
                    )
                    // Also load medical records for this pet
                    loadMedicalRecords(id)
                }
                is Result.Error -> _detailState.value = PetDetailUiState(error = result.message)
                else -> Unit
            }
        }
    }

    private fun loadMedicalRecords(petId: Int) {
        viewModelScope.launch {
            when (val result = medicalRecordRepository.getMedicalRecordsByPet(petId)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        medicalRecords = result.data
                    )
                }
                is Result.Error -> {
                    // Don't override pet data, just log error for medical records
                }
                else -> Unit
            }
        }
    }

    fun deletePet(id: Int) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true)
            when (val result = petRepository.deletePet(id)) {
                is Result.Success -> _detailState.value = _detailState.value.copy(
                    isLoading = false, isDeleted = true
                )
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isLoading = false, error = result.message
                )
                else -> Unit
            }
        }
    }

    fun addPet(
        name: String, species: String, breed: String?,
        gender: String?, dateOfBirth: String?,
        age: Int?, weight: Double?,
        photoFile: File? = null
    ) {
        viewModelScope.launch {
            _addEditState.value = AddEditPetUiState(isLoading = true)
            val request = PetRequest(
                name = name, species = species, breed = breed,
                gender = gender, dateOfBirth = dateOfBirth,
                age = age, weight = weight
            )
            when (val result = petRepository.createPet(request, photoFile)) {
                is Result.Success -> {
                    _addEditState.value = AddEditPetUiState(isSuccess = true)
                    loadPets()
                }
                is Result.Error -> _addEditState.value = AddEditPetUiState(error = result.message)
                else -> Unit
            }
        }
    }

    fun editPet(
        id: Int, name: String, species: String, breed: String?,
        gender: String?, dateOfBirth: String?,
        age: Int?, weight: Double?,
        photoFile: File? = null
    ) {
        viewModelScope.launch {
            _addEditState.value = AddEditPetUiState(isLoading = true)
            val request = PetRequest(
                name = name, species = species, breed = breed,
                gender = gender, dateOfBirth = dateOfBirth,
                age = age, weight = weight
            )
            when (val result = petRepository.updatePet(id, request, photoFile)) {
                is Result.Success -> {
                    _addEditState.value = AddEditPetUiState(isSuccess = true)
                    loadPets()
                }
                is Result.Error -> _addEditState.value = AddEditPetUiState(error = result.message)
                else -> Unit
            }
        }
    }

    fun setSearchQuery(query: String) {
        _uiState.value = _uiState.value.copy(searchQuery = query)
    }

    fun clearAddEditState() {
        _addEditState.value = AddEditPetUiState()
    }
}
