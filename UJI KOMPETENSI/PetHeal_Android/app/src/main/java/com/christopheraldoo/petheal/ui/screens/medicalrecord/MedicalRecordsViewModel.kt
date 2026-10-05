package com.christopheraldoo.petheal.ui.screens.medicalrecord

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.christopheraldoo.petheal.data.local.PreferencesManager
import com.christopheraldoo.petheal.data.model.MedicalRecord
import com.christopheraldoo.petheal.data.repository.DoctorRepository
import com.christopheraldoo.petheal.data.repository.MedicalRecordRepository
import com.christopheraldoo.petheal.data.repository.MedicalRefreshManager
import com.christopheraldoo.petheal.data.repository.PetRepository
import com.christopheraldoo.petheal.data.model.Pet
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
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

/**
 * Reminder rating dokter — hanya untuk satu rekam medis yang SUDAH tersedia.
 * Dimunculkan sekali per appointment (persisted via ratingPromptedIds +
 * in-memory session guard) dan tidak pernah sebelum record unlocked.
 */
data class MedicalRatingReminder(
    val bookingId: Int,
    val doctorId: Int,
    val doctorName: String,
    val petName: String?
)

@HiltViewModel
class MedicalRecordsViewModel @Inject constructor(
    private val medicalRecordRepository: MedicalRecordRepository,
    private val petRepository: PetRepository,
    private val doctorRepository: DoctorRepository,
    private val preferencesManager: PreferencesManager,
    private val medicalRefreshManager: MedicalRefreshManager
) : ViewModel() {

    private var hasLoadedAllRecords = false
    private var lastLoadedPetId: Int? = null
    private var lastLoadedRecordId: Int? = null
    private var lastHandledMedicalRefresh = 0L

    // ── List state ────────────────────────────────────────────────────────────
    private val _listState = MutableStateFlow(MedicalRecordsUiState())
    val listState: StateFlow<MedicalRecordsUiState> = _listState.asStateFlow()

    // ── Detail state ──────────────────────────────────────────────────────────
    private val _detailState = MutableStateFlow(MedicalRecordDetailUiState())
    val detailState: StateFlow<MedicalRecordDetailUiState> = _detailState.asStateFlow()

    // ── Pets (for pet name lookup) ────────────────────────────────────────────
    private val _pets = MutableStateFlow<List<Pet>>(emptyList())
    val pets: StateFlow<List<Pet>> = _pets.asStateFlow()

    // ── Rating reminder (detail only, sekali per appointment) ─────────────────
    private val _ratingReminder = MutableStateFlow<MedicalRatingReminder?>(null)
    val ratingReminder: StateFlow<MedicalRatingReminder?> = _ratingReminder.asStateFlow()
    private var lastCheckedRatingKey: String? = null
    private val reminderDismissedInSession = mutableSetOf<String>()

    // ── Available filter categories ───────────────────────────────────────────
    // Stable filter keys (never shown). Labels are Indonesian via [filterLabel].
    val filterCategories = listOf("all", "vaccination", "checkup", "surgery", "lab")

    init {
        // Auto-refresh setelah additional payment sukses: lewati cache RAM
        // (root cause: stale cache membuat user harus restart aplikasi).
        // Pola yang sama dengan BookingViewModel + BookingRefreshManager.
        viewModelScope.launch {
            medicalRefreshManager.refreshVersion.collect { version ->
                if (version > 0 && version > lastHandledMedicalRefresh) {
                    lastHandledMedicalRefresh = version
                    medicalRecordRepository.invalidateAll()
                    hasLoadedAllRecords = false
                    lastLoadedPetId = null
                    loadRecords(forceRefresh = true)
                    lastLoadedRecordId?.let { recordId ->
                        loadRecord(recordId, forceRefresh = true)
                    }
                }
            }
        }
    }

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

    /** Refresh manual (pull-to-refresh fallback + retry): tetap aman & idempoten. */
    fun refreshRecords() {
        medicalRecordRepository.invalidateAll()
        hasLoadedAllRecords = false
        loadRecords(forceRefresh = true)
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

    /** Refresh manual untuk detail (dipakai retry + fallback pull-to-refresh). */
    fun refreshRecord(id: Int) {
        medicalRecordRepository.invalidateRecord(id)
        lastCheckedRatingKey = null
        loadRecord(id, forceRefresh = true)
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

    // ── Rating reminder ───────────────────────────────────────────────────────
    // Syarat tampil: record SUDAH unlocked (canViewFullRecord authoritative).
    // Tidak tampil saat terkunci, tidak tampil ulang setelah review/dismiss,
    // dan tidak terpicu ulang oleh recomposition (guard lastCheckedRatingKey).

    fun checkRatingReminder(record: MedicalRecord) {
        val bookingId = record.bookingId ?: record.booking?.id ?: return
        val doctorId = record.doctorId
            ?: record.doctor?.id
            ?: record.booking?.doctorId
            ?: record.booking?.doctor?.id
            ?: return
        val key = "$bookingId-$doctorId"
        // Guard recomposition: satu record hanya dicek sekali per data baru.
        if (key == lastCheckedRatingKey && _ratingReminder.value?.bookingId == bookingId) return
        lastCheckedRatingKey = key
        if (key in reminderDismissedInSession) {
            _ratingReminder.value = null
            return
        }

        val extra = record.extraPaymentStatus
        val unlocked = when {
            record.canViewFullRecord == true -> true
            record.canViewFullRecord == false -> false
            extra == "paid" || extra == "not_required" || extra == null -> true
            else -> false
        }
        if (!unlocked) {
            _ratingReminder.value = null
            return
        }

        viewModelScope.launch {
            try {
                val prompted = preferencesManager.ratingPromptedIds.first()
                if (bookingId.toString() in prompted) {
                    _ratingReminder.value = null
                    return@launch
                }
                // Sudah pernah review untuk booking ini? → jangan tampilkan lagi.
                when (val reviews = doctorRepository.getDoctorReviews(doctorId)) {
                    is com.christopheraldoo.petheal.data.repository.Result.Success -> {
                        val alreadyReviewed = reviews.data.reviews?.any { it.bookingId == bookingId } == true
                        if (alreadyReviewed) {
                            runCatching { preferencesManager.markRatingPrompted(listOf(bookingId)) }
                            _ratingReminder.value = null
                            return@launch
                        }
                    }
                    else -> Unit
                }
                val doctorName = record.doctor?.name
                    ?: record.booking?.doctor?.name
                    ?: "Dokter"
                val petName = record.pet?.name ?: record.booking?.pet?.name
                _ratingReminder.value = MedicalRatingReminder(
                    bookingId = bookingId,
                    doctorId = doctorId,
                    doctorName = doctorName,
                    petName = petName
                )
            } catch (_: Exception) {
                // Reminder adalah courtesy — gagal cek = tidak tampil, tanpa error.
                _ratingReminder.value = null
            }
        }
    }

    /** Tutup reminder ("Nanti" / close): tandai agar tidak muncul lagi untuk appointment ini. */
    fun dismissRatingReminder() {
        val current = _ratingReminder.value ?: return
        val key = "${current.bookingId}-${current.doctorId}"
        reminderDismissedInSession.add(key)
        viewModelScope.launch {
            runCatching { preferencesManager.markRatingPrompted(listOf(current.bookingId)) }
            _ratingReminder.value = null
        }
    }

    /** Dipanggil saat user menekan CTA "Beri Rating": navigasi + tandai sekali. */
    fun consumeRatingReminderForNavigation(): MedicalRatingReminder? {
        val current = _ratingReminder.value ?: return null
        val key = "${current.bookingId}-${current.doctorId}"
        reminderDismissedInSession.add(key)
        viewModelScope.launch {
            runCatching { preferencesManager.markRatingPrompted(listOf(current.bookingId)) }
        }
        _ratingReminder.value = null
        return current
    }
}
