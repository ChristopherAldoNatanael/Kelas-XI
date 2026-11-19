package com.christopheraldoo.petheal.ui.screens.servicerequest

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.model.Clinic
import com.christopheraldoo.petheal.data.model.Pet
import com.christopheraldoo.petheal.data.model.ServiceRequest
import com.christopheraldoo.petheal.data.model.ServiceRequestCreateRequest
import com.christopheraldoo.petheal.data.repository.ClinicRepository
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.repository.Result
import com.christopheraldoo.petheal.data.repository.ServiceRequestRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.time.LocalDate
import kotlinx.coroutines.launch
import javax.inject.Inject

/** Jenis kebutuhan yang boleh diajukan pemilik (label dari backend). */
val SERVICE_REQUEST_NEED_TYPES: List<Pair<String, String>> = listOf(
    "consultation" to "Konsultasi umum",
    "follow_up" to "Kontrol lanjutan",
    "vaccination" to "Vaksinasi",
    "grooming" to "Grooming / perawatan",
    "surgery" to "Tindakan / operasi",
    "laboratory" to "Pemeriksaan laboratorium",
    "other" to "Lainnya"
)

data class ServiceRequestsUiState(
    val requests: List<ServiceRequest> = emptyList(),
    val isLoading: Boolean = false,
    val error: String? = null,

    val detail: ServiceRequest? = null,
    val isDetailLoading: Boolean = false,

    // Form pengajuan
    val showForm: Boolean = false,
    val clinics: List<Clinic> = emptyList(),
    val pets: List<Pet> = emptyList(),
    val isLoadingForm: Boolean = false,
    val formError: String? = null,
    val selectedClinicId: Int? = null,
    val selectedPetId: Int? = null,
    val needType: String = "consultation",
    val complaint: String = "",
    val preferredDate: LocalDate? = null,
    val preferredTime: String = "",
    val sourceMedicalRecordId: Int? = null,

    val isSubmitting: Boolean = false,
    val submitMessage: String? = null,

    val actionInProgressId: Int? = null,
    val actionMessage: String? = null,
    val actionError: String? = null
) {
    val canSubmit: Boolean
        get() = selectedClinicId != null &&
            selectedPetId != null &&
            complaint.trim().length >= 10 &&
            !isSubmitting
}

@HiltViewModel
class ServiceRequestsViewModel @Inject constructor(
    private val repository: ServiceRequestRepository,
    private val clinicRepository: ClinicRepository,
    private val petRepository: PetRepository
) : ViewModel() {

    private val _state = MutableStateFlow(ServiceRequestsUiState())
    val state: StateFlow<ServiceRequestsUiState> = _state.asStateFlow()

    private var initialized = false

    /**
     * Dipanggil sekali per kedatangan layar. `openId` membuka detail
     * notifikasi; `petId`/`clinicSlug`/`medicalRecordId` membuka form kontrol.
     */
    fun init(openId: Int?, petId: Int?, clinicSlug: String?, medicalRecordId: Int?) {
        if (initialized) return
        initialized = true

        loadRequests()

        when {
            openId != null && openId > 0 -> loadDetail(openId)
            petId != null && petId > 0 -> openForm(petId, clinicSlug, medicalRecordId)
        }
    }

    fun loadRequests() {
        if (_state.value.isLoading) return

        viewModelScope.launch {
            _state.value = _state.value.copy(isLoading = true, error = null)
            when (val result = repository.getServiceRequests()) {
                is Result.Success -> _state.value = _state.value.copy(
                    isLoading = false,
                    requests = result.data
                )
                is Result.Error -> _state.value = _state.value.copy(
                    isLoading = false,
                    error = result.message
                )
                else -> _state.value = _state.value.copy(isLoading = false)
            }
        }
    }

    fun loadDetail(id: Int) {
        viewModelScope.launch {
            _state.value = _state.value.copy(isDetailLoading = true, error = null)
            when (val result = repository.getServiceRequest(id)) {
                is Result.Success -> _state.value = _state.value.copy(
                    isDetailLoading = false,
                    detail = result.data
                )
                is Result.Error -> _state.value = _state.value.copy(
                    isDetailLoading = false,
                    error = result.message
                )
                else -> _state.value = _state.value.copy(isDetailLoading = false)
            }
        }
    }

    fun closeDetail() {
        _state.value = _state.value.copy(detail = null, actionMessage = null, actionError = null)
    }

    /** Tutup pesan galat daftar/detail tanpa memicu muat ulang. */
    fun clearError() {
        _state.value = _state.value.copy(error = null)
    }

    fun openForm(petId: Int? = null, clinicSlug: String? = null, medicalRecordId: Int? = null) {
        _state.value = _state.value.copy(
            showForm = true,
            detail = null,
            formError = null,
            submitMessage = null,
            isLoadingForm = true,
            selectedPetId = petId ?: _state.value.selectedPetId,
            sourceMedicalRecordId = medicalRecordId,
            complaint = ""
        )

        viewModelScope.launch {
            val clinicsResult = clinicRepository.getClinics()
            val petsResult = petRepository.getPets()
            val clinics = if (clinicsResult is Result.Success) clinicsResult.data else emptyList()
            val pets = if (petsResult is Result.Success) petsResult.data else emptyList()

            // Klinik dari alur booking dipilih lebih dulu; kalau tidak ada,
            // biarkan user memilih (jangan menebak klinik).
            val preselected = clinicSlug?.let { slug -> clinics.firstOrNull { it.slug == slug }?.id }
                ?: _state.value.selectedClinicId

            _state.value = _state.value.copy(
                isLoadingForm = false,
                clinics = clinics,
                pets = pets,
                selectedClinicId = preselected,
                selectedPetId = _state.value.selectedPetId ?: pets.firstOrNull()?.id,
                formError = when {
                    clinicsResult is Result.Error -> clinicsResult.message
                    petsResult is Result.Error -> petsResult.message
                    else -> null
                }
            )
        }
    }

    fun closeForm() {
        _state.value = _state.value.copy(
            showForm = false,
            formError = null,
            submitMessage = null
        )
    }

    fun selectClinic(id: Int) {
        _state.value = _state.value.copy(selectedClinicId = id)
    }

    fun selectPet(id: Int) {
        _state.value = _state.value.copy(selectedPetId = id)
    }

    fun setNeedType(value: String) {
        _state.value = _state.value.copy(needType = value)
    }

    fun setComplaint(value: String) {
        _state.value = _state.value.copy(complaint = value)
    }

    fun setPreferredDate(date: LocalDate?) {
        _state.value = _state.value.copy(preferredDate = date)
    }

    fun setPreferredTime(value: String) {
        _state.value = _state.value.copy(preferredTime = value)
    }

    fun submit() {
        val s = _state.value
        if (!s.canSubmit) return

        val clinicId = s.selectedClinicId ?: return
        val petId = s.selectedPetId ?: return
        val time = s.preferredTime.trim().takeIf { it.isNotBlank() }
        if (time != null && !Regex("^([01]\\d|2[0-3]):[0-5]\\d$").matches(time)) {
            _state.value = s.copy(formError = "Format jam harus HH:mm, mis. 10:30.")
            return
        }

        viewModelScope.launch {
            _state.value = s.copy(isSubmitting = true, formError = null)
            val request = ServiceRequestCreateRequest(
                clinicId = clinicId,
                petId = petId,
                needType = s.needType,
                complaint = s.complaint.trim(),
                preferredDate = s.preferredDate?.toString(),
                preferredTime = time,
                medicalRecordId = s.sourceMedicalRecordId
            )

            when (val result = repository.createServiceRequest(request)) {
                is Result.Success -> {
                    _state.value = _state.value.copy(
                        isSubmitting = false,
                        showForm = false,
                        submitMessage = "Permintaan terkirim. Klinik akan meninjaunya.",
                        detail = result.data
                    )
                    loadRequests()
                }
                is Result.Error -> _state.value = _state.value.copy(
                    isSubmitting = false,
                    formError = result.message
                )
                else -> _state.value = _state.value.copy(isSubmitting = false)
            }
        }
    }

    fun acceptOffer(id: Int, note: String? = null) = respond(id, accept = true, note = note)

    fun rejectOffer(id: Int, note: String? = null) = respond(id, accept = false, note = note)

    private fun respond(id: Int, accept: Boolean, note: String?) {
        if (_state.value.actionInProgressId != null) return

        viewModelScope.launch {
            _state.value = _state.value.copy(
                actionInProgressId = id,
                actionMessage = null,
                actionError = null
            )

            val result = if (accept) repository.acceptOffer(id, note) else repository.rejectOffer(id, note)

            when (result) {
                is Result.Success -> {
                    val updated = result.data
                    _state.value = _state.value.copy(
                        actionInProgressId = null,
                        detail = if (_state.value.detail?.id == updated.id) updated else _state.value.detail,
                        requests = _state.value.requests.map { if (it.id == updated.id) updated else it },
                        actionMessage = if (accept) {
                            "Penawaran disetujui. Booking sudah dibuat — lanjutkan pembayaran dari menu Booking."
                        } else {
                            "Penawaran ditolak. Klinik akan melihat keputusan Anda."
                        }
                    )
                }
                is Result.Error -> _state.value = _state.value.copy(
                    actionInProgressId = null,
                    actionError = result.message
                )
                else -> _state.value = _state.value.copy(actionInProgressId = null)
            }
        }
    }

    fun clearMessages() {
        _state.value = _state.value.copy(
            submitMessage = null,
            actionMessage = null,
            actionError = null,
            formError = null
        )
    }
}
