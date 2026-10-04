package com.christopheraldoo.petheal.ui.screens.pet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.PetRequest
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.model.Vaccination
import com.christopheraldoo.petheal.data.model.WeightChange
import com.christopheraldoo.petheal.data.model.WeightRecord
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.Result
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.supervisorScope
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
    val weightRecords: List<WeightRecord> = emptyList(),
    val weightChange: WeightChange? = null,
    val vaccinations: List<Vaccination> = emptyList(),
    val upcomingVaccinations: List<Vaccination> = emptyList(),
    val isLoading: Boolean = false,
    val isHealthActionLoading: Boolean = false,
    val error: String? = null,
    val healthMessage: String? = null,
    val isDeleted: Boolean = false
)

data class AddEditPetUiState(
    val isLoading: Boolean = false,
    val isSuccess: Boolean = false,
    // PHASE 7: name of the just-created pet for the success confirmation.
    val createdPetName: String? = null,
    val error: String? = null
)

@HiltViewModel
class PetsViewModel @Inject constructor(
    private val petRepository: PetRepository,
    private val medicalRecordRepository: MedicalRecordRepository
) : ViewModel() {

    private var lastLoadedDetailPetId: Int? = null

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

    fun loadPetDetail(id: Int, forceRefresh: Boolean = false) {
        if (!forceRefresh && lastLoadedDetailPetId == id && _detailState.value.pet?.id == id) {
            return
        }
        viewModelScope.launch {
            val hasSamePetLoaded = _detailState.value.pet?.id == id
            _detailState.value = _detailState.value.copy(
                isLoading = !hasSamePetLoaded || forceRefresh,
                error = null,
                isDeleted = false
            )

            supervisorScope {
                val petDeferred = async { petRepository.getPet(id) }
                val recordsDeferred = async { medicalRecordRepository.getMedicalRecordsByPet(id, forceRefresh = forceRefresh) }
                val weightDeferred = async { petRepository.getWeightHistory(id) }
                val vaccinationDeferred = async { petRepository.getVaccinations(id) }

                when (val result = petDeferred.await()) {
                is Result.Success -> {
                        lastLoadedDetailPetId = id

                        val records = when (val recordsResult = recordsDeferred.await()) {
                            is Result.Success -> recordsResult.data
                            else -> _detailState.value.medicalRecords
                        }

                        val weightData = when (val weightResult = weightDeferred.await()) {
                            is Result.Success -> weightResult.data
                            else -> null
                        }

                        val vaccinations = when (val vaccinationResult = vaccinationDeferred.await()) {
                            is Result.Success -> vaccinationResult.data
                            else -> null
                        }

                        _detailState.value = _detailState.value.copy(
                            pet = result.data.copy(weight = weightData?.currentWeight ?: result.data.weight),
                            medicalRecords = records,
                            weightRecords = weightData?.records.orEmpty(),
                            weightChange = weightData?.weightChange,
                            vaccinations = vaccinations?.vaccinations.orEmpty(),
                            upcomingVaccinations = vaccinations?.upcomingDue.orEmpty(),
                            isLoading = false
                        )
                }
                    is Result.Error -> _detailState.value = PetDetailUiState(error = result.message)
                else -> Unit
                }
            }
        }
    }

    private fun loadWeightHistory(petId: Int) {
        viewModelScope.launch {
            when (val result = petRepository.getWeightHistory(petId)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        weightRecords = result.data.records.orEmpty(),
                        weightChange = result.data.weightChange,
                        pet = _detailState.value.pet?.copy(weight = result.data.currentWeight)
                            ?: _detailState.value.pet
                    )
                }
                is Result.Error -> Unit
                else -> Unit
            }
        }
    }

    private fun loadVaccinations(petId: Int) {
        viewModelScope.launch {
            when (val result = petRepository.getVaccinations(petId)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        vaccinations = result.data.vaccinations.orEmpty(),
                        upcomingVaccinations = result.data.upcomingDue.orEmpty()
                    )
                }
                is Result.Error -> Unit
                else -> Unit
            }
        }
    }

    fun addWeightRecord(petId: Int, weight: Double, notes: String?) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(
                isHealthActionLoading = true,
                error = null,
                healthMessage = null
            )
            when (val result = petRepository.addWeightRecord(petId, weight, notes = notes)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isHealthActionLoading = false,
                        healthMessage = "Weight record saved"
                    )
                    loadPetDetail(petId, forceRefresh = true)
                    loadPets(forceRefresh = true)
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isHealthActionLoading = false,
                    error = result.message
                )
                else -> Unit
            }
        }
    }

    fun addVaccination(
        petId: Int,
        vaccineName: String,
        dateAdministered: String,
        nextDueDate: String?,
        veterinarian: String?,
        notes: String?
    ) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(
                isHealthActionLoading = true,
                error = null,
                healthMessage = null
            )
            when (val result = petRepository.addVaccination(
                petId = petId,
                vaccineName = vaccineName,
                dateAdministered = dateAdministered,
                nextDueDate = nextDueDate,
                veterinarian = veterinarian,
                notes = notes
            )) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isHealthActionLoading = false,
                        healthMessage = "Vaccination saved"
                    )
                    loadPetDetail(petId, forceRefresh = true)
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isHealthActionLoading = false,
                    error = result.message
                )
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

    /** PHASE 7: wire declared DELETE endpoints (were endpoint mati). */
    fun deleteWeightRecord(petId: Int, recordId: Int) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(
                isHealthActionLoading = true, error = null, healthMessage = null
            )
            when (val result = petRepository.deleteWeightRecord(petId, recordId)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isHealthActionLoading = false,
                        healthMessage = "Weight record deleted"
                    )
                    loadPetDetail(petId, forceRefresh = true)
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isHealthActionLoading = false, error = result.message
                )
                else -> Unit
            }
        }
    }

    fun deleteVaccination(petId: Int, vaccinationId: Int) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(
                isHealthActionLoading = true, error = null, healthMessage = null
            )
            when (val result = petRepository.deleteVaccination(petId, vaccinationId)) {
                is Result.Success -> {
                    _detailState.value = _detailState.value.copy(
                        isHealthActionLoading = false,
                        healthMessage = "Vaccination deleted"
                    )
                    loadPetDetail(petId, forceRefresh = true)
                }
                is Result.Error -> _detailState.value = _detailState.value.copy(
                    isHealthActionLoading = false, error = result.message
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
                    _addEditState.value = AddEditPetUiState(isSuccess = true, createdPetName = name)
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
